<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\MessageHandler;

use Psr\Log\LoggerInterface;
use WSCPlugin\SWVariantUpdater\Message\UpdateVariantsBatchMessage;
use WSCPlugin\SWVariantUpdater\Service\BatchSizeCalculator;
use WSCPlugin\SWVariantUpdater\Service\ProgressTracker;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateService;

/**
 * Worker handler that processes individual batches.
 */
class UpdateVariantsBatchMessageHandler
{
    public function __construct(
        private readonly VariantUpdateService $variantUpdateService,
        private readonly ProgressTracker $progressTracker,
        private readonly BatchSizeCalculator $batchSizeCalculator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(UpdateVariantsBatchMessage $message): void
    {
        $productNumbers = $message->getProductNumbers();
        $config = $message->getConfig();
        $batchId = $message->getBatchId();
        $batchNumber = $message->getBatchNumber();
        $totalBatches = $message->getTotalBatches();
        $context = $message->getContext();

        $this->logger->info('Processing batch', [
            'batch_id' => $batchId,
            'batch_number' => $batchNumber,
            'total_batches' => $totalBatches,
            'products_in_batch' => \count($productNumbers),
        ]);

        $startTime = microtime(true);

        try {
            // Check memory before processing
            if ($this->batchSizeCalculator->isMemoryPressure()) {
                $this->logger->warning('Memory pressure detected, decreasing batch size', [
                    'batch_id' => $batchId,
                    'old_size' => $this->batchSizeCalculator->getCurrentBatchSize(),
                ]);
                $this->batchSizeCalculator->decreaseForMemory();
            }

            // Process variants
            $result = $this->variantUpdateService->updateVariants(
                $productNumbers,
                $config,
                $context
            );

            $processingTime = microtime(true) - $startTime;

            // Log errors
            foreach ($result->getErrors() as $productNumber => $error) {
                $this->progressTracker->logError(
                    $batchId,
                    $productNumber,
                    $error,
                    null,
                    $context
                );
            }

            // Update progress
            $this->progressTracker->updateProgress(
                $batchId,
                $result->getProductCount(),
                \count($result->getErrors()),
                $this->batchSizeCalculator->getCurrentBatchSize(),
                $context
            );

            // Adjust batch size based on performance
            $newBatchSize = $this->batchSizeCalculator->adjustBatchSize(
                $processingTime,
                \count($productNumbers)
            );

            $this->logger->info('Batch processed successfully', [
                'batch_id' => $batchId,
                'batch_number' => $batchNumber,
                'processing_time' => round($processingTime, 2),
                'products_processed' => $result->getProductCount(),
                'variants_updated' => $result->getVariantsUpdated(),
                'errors' => \count($result->getErrors()),
                'new_batch_size' => $newBatchSize,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Batch processing failed', [
                'batch_id' => $batchId,
                'batch_number' => $batchNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->progressTracker->markAsFailed($batchId, $context);

            throw $e;
        }
    }
}
