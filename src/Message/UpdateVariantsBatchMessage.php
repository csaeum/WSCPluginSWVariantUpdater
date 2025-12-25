<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateConfig;

/**
 * Batch message that processes a subset of products.
 * Created by splitting the initial UpdateVariantsMessage.
 */
class UpdateVariantsBatchMessage implements AsyncMessageInterface
{
    /**
     * @param array<string> $productNumbers Array of parent product numbers for this batch
     * @param VariantUpdateConfig $config Update configuration
     * @param string $batchId Unique identifier for the parent batch operation
     * @param int $batchNumber Sequential number of this batch
     * @param int $totalBatches Total number of batches in the operation
     * @param int $batchSize Current batch size (for adaptive sizing)
     * @param Context $context Shopware context
     */
    public function __construct(
        private readonly array $productNumbers,
        private readonly VariantUpdateConfig $config,
        private readonly string $batchId,
        private readonly int $batchNumber,
        private readonly int $totalBatches,
        private readonly int $batchSize,
        private readonly Context $context
    ) {
    }

    public function getProductNumbers(): array
    {
        return $this->productNumbers;
    }

    public function getConfig(): VariantUpdateConfig
    {
        return $this->config;
    }

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function getBatchNumber(): int
    {
        return $this->batchNumber;
    }

    public function getTotalBatches(): int
    {
        return $this->totalBatches;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
