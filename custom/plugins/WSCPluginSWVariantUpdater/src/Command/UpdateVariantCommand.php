<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Command;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'wsc:variant:update',
    description: 'Updates variant names and product numbers based on parent product and options'
)]
class UpdateVariantCommand extends Command
{
    public function __construct(
        private readonly EntityRepository $productRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'product-numbers',
                null,
                InputOption::VALUE_REQUIRED,
                'Comma-separated list of parent product numbers'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be changed without saving'
            )
            ->addOption(
                'name-only',
                null,
                InputOption::VALUE_NONE,
                'Update only the product name'
            )
            ->addOption(
                'number-only',
                null,
                InputOption::VALUE_NONE,
                'Update only the product number'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = Context::createDefaultContext();

        // Check if --product-numbers is provided
        $productNumbersInput = $input->getOption('product-numbers');
        if (empty($productNumbersInput)) {
            $io->error('The --product-numbers option is required. Please provide at least one product number.');
            return Command::FAILURE;
        }

        // Parse product numbers
        $productNumbers = array_map('trim', explode(',', $productNumbersInput));
        $productNumbers = array_filter($productNumbers);

        if (empty($productNumbers)) {
            $io->error('No valid product numbers provided.');
            return Command::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');
        $nameOnly = $input->getOption('name-only');
        $numberOnly = $input->getOption('number-only');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be saved');
        }

        $totalUpdated = 0;

        foreach ($productNumbers as $productNumber) {
            $io->section("Processing product: {$productNumber}");

            // Load parent product
            $parentProduct = $this->loadParentProduct($productNumber, $context);

            if (!$parentProduct) {
                $io->error("Product with number '{$productNumber}' not found.");
                continue;
            }

            // Load variants
            $variants = $this->loadVariants($parentProduct->getId(), $context);

            if ($variants->count() === 0) {
                $io->warning("No variants found for product '{$productNumber}'.");
                continue;
            }

            $io->text("Found {$variants->count()} variant(s) for '{$parentProduct->getName()}'");
            $io->newLine();

            // Process each variant
            foreach ($variants as $variant) {
                $updated = $this->processVariant(
                    $variant,
                    $parentProduct,
                    $context,
                    $io,
                    $dryRun,
                    $nameOnly,
                    $numberOnly
                );

                if ($updated) {
                    $totalUpdated++;
                }
            }

            $io->newLine();
        }

        // Summary
        $io->success("Successfully processed {$totalUpdated} variant(s)" . ($dryRun ? ' (dry run)' : ''));

        return Command::SUCCESS;
    }

    private function loadParentProduct(string $productNumber, Context $context): ?ProductEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productNumber', $productNumber));
        $criteria->addFilter(new EqualsFilter('parentId', null));

        $result = $this->productRepository->search($criteria, $context);

        return $result->first();
    }

    private function loadVariants(string $parentId, Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addAssociation('options.group');

        return $this->productRepository->search($criteria, $context)->getEntities();
    }

    private function processVariant(
        ProductEntity $variant,
        ProductEntity $parent,
        Context $context,
        SymfonyStyle $io,
        bool $dryRun,
        bool $nameOnly,
        bool $numberOnly
    ): bool {
        $updates = [];
        $hasChanges = false;

        // Get option names
        $optionNames = [];
        if ($variant->getOptions()) {
            foreach ($variant->getOptions() as $option) {
                $optionNames[] = $option->getName();
            }
        }

        $optionString = implode(' ', $optionNames);

        // Update name
        if (!$numberOnly) {
            $newName = trim($parent->getName() . ' ' . $optionString);
            if ($variant->getName() !== $newName) {
                $io->text("  Name: '{$variant->getName()}' -> '{$newName}'");
                $updates['name'] = $newName;
                $hasChanges = true;
            }
        }

        // Update product number
        if (!$nameOnly) {
            $newProductNumber = $this->generateProductNumber(
                $parent->getProductNumber(),
                $optionNames
            );

            if ($variant->getProductNumber() !== $newProductNumber) {
                $io->text("  Number: '{$variant->getProductNumber()}' -> '{$newProductNumber}'");
                $updates['productNumber'] = $newProductNumber;
                $hasChanges = true;
            }
        }

        // Save changes
        if ($hasChanges && !$dryRun) {
            $updates['id'] = $variant->getId();
            $this->productRepository->update([$updates], $context);
        }

        if ($hasChanges) {
            $io->newLine();
        }

        return $hasChanges;
    }

    private function generateProductNumber(string $parentNumber, array $optionNames): string
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

    private function normalizeString(string $string): string
    {
        // Replace special characters
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

        // Replace spaces with hyphens
        $string = str_replace(' ', '-', $string);

        return $string;
    }
}
