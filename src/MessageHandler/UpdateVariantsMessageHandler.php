<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\MessageHandler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use WSCPlugin\SWVariantUpdater\Message\UpdateVariantsBatchMessage;
use WSCPlugin\SWVariantUpdater\Message\UpdateVariantsMessage;
use WSCPlugin\SWVariantUpdater\Service\BatchSizeCalculator;
use WSCPlugin\SWVariantUpdater\Service\ProgressTracker;

/**
 * Splitter handler that splits the initial message into batch messages.
 */
class UpdateVariantsMessageHandler
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ProgressTracker $progressTracker,
        private readonly BatchSizeCalculator $batchSizeCalculator,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(UpdateVariantsMessage $message): void
    {
        $productNumbers = $message->getProductNumbers();
        $config = $message->getConfig();
        $batchId = $message->getBatchId();
        $context = $message->getContext();

        $this->logger->info('Starting variant update batch operation', [
            'batch_id' => $batchId,
            'total_products' => \count($productNumbers),
        ]);

        // Initialize progress tracking
        $batchSize = $this->batchSizeCalculator->getCurrentBatchSize();
        $this->progressTracker->initializeProgress(
            $batchId,
            \count($productNumbers),
            $batchSize,
            $context
        );

        // Split into batches
        $batches = array_chunk($productNumbers, $batchSize);
        $totalBatches = \count($batches);

        $this->logger->info('Split into batches', [
            'batch_id' => $batchId,
            'total_batches' => $totalBatches,
            'batch_size' => $batchSize,
        ]);

        // Dispatch batch messages
        foreach ($batches as $batchNumber => $batchProductNumbers) {
            $batchMessage = new UpdateVariantsBatchMessage(
                $batchProductNumbers,
                $config,
                $batchId,
                $batchNumber + 1,
                $totalBatches,
                $batchSize,
                $context
            );

            $this->messageBus->dispatch($batchMessage);
        }

        $this->logger->info('Dispatched all batch messages', [
            'batch_id' => $batchId,
            'batches_dispatched' => $totalBatches,
        ]);
    }
}
