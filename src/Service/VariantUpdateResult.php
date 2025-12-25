<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Service;

/**
 * Result object containing statistics and errors from variant update operation.
 */
class VariantUpdateResult
{
    /** @var array<string, int> */
    private array $productsProcessed = [];

    /** @var array<string, array<string, mixed>> */
    private array $variantChanges = [];

    /** @var array<string, string> */
    private array $errors = [];

    /** @var array<string, string> */
    private array $warnings = [];

    private int $nameChanges = 0;
    private int $numberChanges = 0;

    public function addProductProcessed(string $productNumber, int $variantCount): void
    {
        $this->productsProcessed[$productNumber] = $variantCount;
    }

    public function addVariantChange(string $variantId, array $changes): void
    {
        $this->variantChanges[$variantId] = $changes;
    }

    public function addError(string $productNumber, string $message): void
    {
        $this->errors[$productNumber] = $message;
    }

    public function addWarning(string $productNumber, string $message): void
    {
        $this->warnings[$productNumber] = $message;
    }

    public function incrementNameChanges(): void
    {
        $this->nameChanges++;
    }

    public function incrementNumberChanges(): void
    {
        $this->numberChanges++;
    }

    /**
     * Get total number of products processed.
     */
    public function getProductCount(): int
    {
        return \count($this->productsProcessed);
    }

    /**
     * Get total number of variants across all products.
     */
    public function getTotalVariants(): int
    {
        return array_sum($this->productsProcessed);
    }

    /**
     * Get total number of variants that had changes.
     */
    public function getVariantsUpdated(): int
    {
        return \count($this->variantChanges);
    }

    /**
     * Get number of name changes.
     */
    public function getNameChanges(): int
    {
        return $this->nameChanges;
    }

    /**
     * Get number of product number changes.
     */
    public function getNumberChanges(): int
    {
        return $this->numberChanges;
    }

    /**
     * Get all errors.
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all warnings.
     *
     * @return array<string, string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get all variant changes.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getVariantChanges(): array
    {
        return $this->variantChanges;
    }

    /**
     * Check if there were any errors.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there were any warnings.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Check if any changes were made.
     */
    public function hasChanges(): bool
    {
        return !empty($this->variantChanges);
    }
}
