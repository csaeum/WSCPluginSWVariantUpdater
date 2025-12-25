<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Service;

/**
 * Calculates optimal batch sizes based on performance metrics.
 *
 * Algorithm:
 * - Start: 100 products
 * - Success < 30s: +20% (max 500)
 * - Slow > 45s: -30% (min 10)
 * - Memory warning: -50%
 */
class BatchSizeCalculator
{
    private const DEFAULT_BATCH_SIZE = 100;
    private const MIN_BATCH_SIZE = 10;
    private const MAX_BATCH_SIZE = 500;

    private const TARGET_DURATION_FAST = 30.0; // seconds
    private const TARGET_DURATION_SLOW = 45.0; // seconds

    private const INCREASE_FACTOR = 1.2; // +20%
    private const DECREASE_FACTOR = 0.7; // -30%
    private const MEMORY_DECREASE_FACTOR = 0.5; // -50%

    private int $currentBatchSize;

    public function __construct()
    {
        $this->currentBatchSize = self::DEFAULT_BATCH_SIZE;
    }

    /**
     * Get the current batch size.
     */
    public function getCurrentBatchSize(): int
    {
        return $this->currentBatchSize;
    }

    /**
     * Adjust batch size based on processing time.
     *
     * @param float $processingTimeSeconds Time taken to process the batch
     * @param int $productsProcessed Number of products processed
     *
     * @return int New batch size
     */
    public function adjustBatchSize(float $processingTimeSeconds, int $productsProcessed): int
    {
        if ($processingTimeSeconds < self::TARGET_DURATION_FAST) {
            // Fast processing - increase batch size
            $newSize = (int) ceil($this->currentBatchSize * self::INCREASE_FACTOR);
            $this->currentBatchSize = min($newSize, self::MAX_BATCH_SIZE);
        } elseif ($processingTimeSeconds > self::TARGET_DURATION_SLOW) {
            // Slow processing - decrease batch size
            $newSize = (int) ceil($this->currentBatchSize * self::DECREASE_FACTOR);
            $this->currentBatchSize = max($newSize, self::MIN_BATCH_SIZE);
        }
        // If between 30-45s, keep current size

        return $this->currentBatchSize;
    }

    /**
     * Decrease batch size due to memory pressure.
     *
     * @return int New batch size
     */
    public function decreaseForMemory(): int
    {
        $newSize = (int) ceil($this->currentBatchSize * self::MEMORY_DECREASE_FACTOR);
        $this->currentBatchSize = max($newSize, self::MIN_BATCH_SIZE);

        return $this->currentBatchSize;
    }

    /**
     * Reset to default batch size.
     */
    public function reset(): void
    {
        $this->currentBatchSize = self::DEFAULT_BATCH_SIZE;
    }

    /**
     * Set a specific batch size (useful for testing or manual override).
     */
    public function setBatchSize(int $size): void
    {
        $this->currentBatchSize = max(
            self::MIN_BATCH_SIZE,
            min($size, self::MAX_BATCH_SIZE)
        );
    }

    /**
     * Check if current memory usage is approaching limits.
     *
     * @return bool True if memory usage is above 80%
     */
    public function isMemoryPressure(): bool
    {
        $memoryLimit = $this->getMemoryLimitInBytes();
        if ($memoryLimit === -1) {
            return false; // No limit
        }

        $memoryUsage = memory_get_usage(true);
        $usagePercent = ($memoryUsage / $memoryLimit) * 100;

        return $usagePercent > 80;
    }

    /**
     * Get PHP memory limit in bytes.
     *
     * @return int Memory limit in bytes, or -1 if unlimited
     */
    private function getMemoryLimitInBytes(): int
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return -1;
        }

        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int) substr($memoryLimit, 0, -1);

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $memoryLimit,
        };
    }
}
