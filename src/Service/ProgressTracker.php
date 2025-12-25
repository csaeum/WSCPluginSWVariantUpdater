<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use WSCPlugin\SWVariantUpdater\Entity\VariantUpdateProgress\VariantUpdateProgressDefinition;
use WSCPlugin\SWVariantUpdater\Entity\VariantUpdateProgress\VariantUpdateProgressEntity;

/**
 * Tracks progress of variant update operations.
 */
class ProgressTracker
{
    public function __construct(
        private readonly EntityRepository $progressRepository,
        private readonly EntityRepository $logRepository
    ) {
    }

    /**
     * Initialize progress tracking for a new batch operation.
     */
    public function initializeProgress(
        string $batchId,
        int $totalProducts,
        int $initialBatchSize,
        Context $context
    ): string {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'batchId' => $batchId,
            'totalProducts' => $totalProducts,
            'processedProducts' => 0,
            'failedProducts' => 0,
            'currentBatchSize' => $initialBatchSize,
            'status' => VariantUpdateProgressDefinition::STATUS_PENDING,
            'startedAt' => new \DateTime(),
        ];

        $this->progressRepository->create([$data], $context);

        return $id;
    }

    /**
     * Update progress after processing a batch.
     */
    public function updateProgress(
        string $batchId,
        int $processedProducts,
        int $failedProducts,
        int $currentBatchSize,
        Context $context
    ): void {
        $progress = $this->getProgressByBatchId($batchId, $context);
        if (!$progress) {
            return;
        }

        $newProcessed = $progress->getProcessedProducts() + $processedProducts;
        $newFailed = $progress->getFailedProducts() + $failedProducts;

        $data = [
            'id' => $progress->getId(),
            'processedProducts' => $newProcessed,
            'failedProducts' => $newFailed,
            'currentBatchSize' => $currentBatchSize,
            'status' => VariantUpdateProgressDefinition::STATUS_PROCESSING,
        ];

        // Check if completed
        if ($newProcessed + $newFailed >= $progress->getTotalProducts()) {
            $data['status'] = $newFailed > 0
                ? VariantUpdateProgressDefinition::STATUS_FAILED
                : VariantUpdateProgressDefinition::STATUS_COMPLETED;
            $data['finishedAt'] = new \DateTime();
        }

        $this->progressRepository->update([$data], $context);
    }

    /**
     * Mark batch as failed.
     */
    public function markAsFailed(string $batchId, Context $context): void
    {
        $progress = $this->getProgressByBatchId($batchId, $context);
        if (!$progress) {
            return;
        }

        $this->progressRepository->update([
            [
                'id' => $progress->getId(),
                'status' => VariantUpdateProgressDefinition::STATUS_FAILED,
                'finishedAt' => new \DateTime(),
            ],
        ], $context);
    }

    /**
     * Log an error for a specific product.
     */
    public function logError(
        string $batchId,
        string $productNumber,
        string $errorMessage,
        ?string $stackTrace,
        Context $context
    ): void {
        $data = [
            'id' => Uuid::randomHex(),
            'batchId' => $batchId,
            'productNumber' => $productNumber,
            'errorMessage' => $errorMessage,
            'stackTrace' => $stackTrace,
        ];

        $this->logRepository->create([$data], $context);
    }

    /**
     * Get progress by batch ID.
     */
    public function getProgressByBatchId(string $batchId, Context $context): ?VariantUpdateProgressEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('batchId', $batchId));

        $result = $this->progressRepository->search($criteria, $context);

        return $result->first();
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentage(string $batchId, Context $context): float
    {
        $progress = $this->getProgressByBatchId($batchId, $context);
        if (!$progress || $progress->getTotalProducts() === 0) {
            return 0.0;
        }

        $processed = $progress->getProcessedProducts() + $progress->getFailedProducts();

        return ($processed / $progress->getTotalProducts()) * 100;
    }

    /**
     * Clean up old progress entries (older than 7 days).
     */
    public function cleanupOldProgress(Context $context): int
    {
        $sevenDaysAgo = new \DateTime('-7 days');

        // This would need a custom query or scheduled task
        // For now, return 0 as placeholder
        return 0;
    }
}
