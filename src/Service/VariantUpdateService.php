<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class VariantUpdateService
{
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Update variants for given product numbers.
     *
     * @param array<string> $productNumbers Array of parent product numbers
     * @param VariantUpdateConfig $config Configuration for the update
     * @param Context $context Shopware context
     *
     * @return VariantUpdateResult Result containing statistics and errors
     */
    public function updateVariants(
        array $productNumbers,
        VariantUpdateConfig $config,
        Context $context
    ): VariantUpdateResult {
        $result = new VariantUpdateResult();

        foreach ($productNumbers as $productNumber) {
            try {
                $parentProduct = $this->loadParentProduct($productNumber, $context);

                if (!$parentProduct) {
                    $result->addError($productNumber, "Product with number '{$productNumber}' not found.");
                    continue;
                }

                $variants = $this->loadVariants($parentProduct->getId(), $context);

                if ($variants->count() === 0) {
                    $result->addWarning($productNumber, "No variants found for product '{$productNumber}'.");
                    continue;
                }

                $result->addProductProcessed($productNumber, $variants->count());

                // Process each variant
                foreach ($variants as $variant) {
                    $this->processVariant(
                        $variant,
                        $parentProduct,
                        $config,
                        $context,
                        $result
                    );
                }
            } catch (\Exception $e) {
                $result->addError($productNumber, $e->getMessage());
                $this->logger->error('Failed to process product', [
                    'product_number' => $productNumber,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Load parent product by product number.
     */
    public function loadParentProduct(string $productNumber, Context $context): ?ProductEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));
        $criteria->addFilter(new EqualsFilter('parentId', null));

        $result = $this->productRepository->search($criteria, $context);

        return $result->first();
    }

    /**
     * Load all variants for a parent product.
     */
    public function loadVariants(string $parentId, Context $context): \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addAssociation('options.group');

        return $this->productRepository->search($criteria, $context)->getEntities();
    }

    /**
     * Process a single variant.
     */
    private function processVariant(
        ProductEntity $variant,
        ProductEntity $parent,
        VariantUpdateConfig $config,
        Context $context,
        VariantUpdateResult $result
    ): void {
        $updates = [];
        $changes = [];

        // Get option names
        $optionNames = [];
        if ($variant->getOptions()) {
            foreach ($variant->getOptions() as $option) {
                $optionNames[] = $option->getName();
            }
        }

        $optionString = implode(' ', $optionNames);

        // Update name
        if (!$config->numberOnly) {
            $newName = trim($parent->getName() . ' ' . $optionString);
            if ($variant->getName() !== $newName) {
                $updates['name'] = $newName;
                $changes['name'] = [
                    'old' => $variant->getName(),
                    'new' => $newName,
                ];
                $result->incrementNameChanges();
            }
        }

        // Update product number
        if (!$config->nameOnly) {
            $newProductNumber = $this->generateProductNumber(
                $parent->getProductNumber(),
                $optionNames
            );

            // Check for duplicates
            if ($newProductNumber !== $variant->getProductNumber()) {
                if ($this->productNumberExists($newProductNumber, $variant->getId(), $context)) {
                    $result->addWarning(
                        $parent->getProductNumber(),
                        "Product number '{$newProductNumber}' already exists! Skipping variant '{$variant->getId()}'."
                    );
                    $this->logger->warning('Duplicate product number detected', [
                        'parent_product_number' => $parent->getProductNumber(),
                        'variant_id' => $variant->getId(),
                        'duplicate_number' => $newProductNumber,
                    ]);
                    return;
                }

                $updates['productNumber'] = $newProductNumber;
                $changes['number'] = [
                    'old' => $variant->getProductNumber(),
                    'new' => $newProductNumber,
                ];
                $result->incrementNumberChanges();
            }
        }

        // Save changes
        if (!empty($updates) && !$config->dryRun) {
            $updates['id'] = $variant->getId();
            $this->productRepository->update([$updates], $context);

            $this->logger->info('Variant updated', [
                'product_number' => $parent->getProductNumber(),
                'variant_id' => $variant->getId(),
                'changes' => $changes,
            ]);
        }

        if (!empty($changes)) {
            $result->addVariantChange($variant->getId(), $changes);
        }
    }

    /**
     * Generate product number from parent number and option names.
     */
    public function generateProductNumber(string $parentNumber, array $optionNames): string
    {
        if (empty($optionNames)) {
            return $parentNumber;
        }

        // Convert option names to lowercase
        $optionParts = array_map(function ($name) {
            return $this->normalizeString(mb_strtolower($name));
        }, $optionNames);

        // Join with hyphen
        $optionSuffix = implode('-', $optionParts);

        return $parentNumber . '-' . $optionSuffix;
    }

    /**
     * Normalize string by replacing special characters and spaces.
     *
     * Uses transliterator if available (intl extension), otherwise falls back
     * to basic German umlaut replacement.
     */
    public function normalizeString(string $string): string
    {
        // Try transliterator first (broader international support)
        if (\function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate(
                'Any-Latin; Latin-ASCII',
                $string
            );
            if ($transliterated !== false) {
                $string = $transliterated;
            }
        } else {
            // Fallback: Replace German umlauts manually
            $replacements = [
                'ä' => 'ae',
                'ö' => 'oe',
                'ü' => 'ue',
                'ß' => 'ss',
                'Ä' => 'ae',
                'Ö' => 'oe',
                'Ü' => 'ue',
            ];

            $string = str_replace(array_keys($replacements), array_values($replacements), $string);
        }

        // Replace spaces with hyphens
        $string = str_replace(' ', '-', $string);

        return $string;
    }

    /**
     * Check if a product number already exists (excluding the current variant).
     */
    private function productNumberExists(string $productNumber, string $excludeId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));
        $criteria->setLimit(1);

        $result = $this->productRepository->searchIds($criteria, $context);

        // Check if exists and is not the current variant
        if ($result->getTotal() === 0) {
            return false;
        }

        $existingIds = $result->getIds();
        return !\in_array($excludeId, $existingIds, true);
    }
}
