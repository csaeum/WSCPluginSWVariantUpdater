<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use WSCPlugin\SWVariantUpdater\Service\VariantUpdateConfig;

/**
 * Initial message that contains all product numbers to update.
 * This message will be split into multiple batch messages by the handler.
 */
class UpdateVariantsMessage implements AsyncMessageInterface
{
    /**
     * @param array<string> $productNumbers Array of parent product numbers
     * @param VariantUpdateConfig $config Update configuration
     * @param string $batchId Unique identifier for this batch operation
     * @param Context $context Shopware context
     */
    public function __construct(
        private readonly array $productNumbers,
        private readonly VariantUpdateConfig $config,
        private readonly string $batchId,
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

    public function getContext(): Context
    {
        return $this->context;
    }
}
