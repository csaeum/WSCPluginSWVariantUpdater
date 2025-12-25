<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Entity\VariantUpdateProgress;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class VariantUpdateProgressEntity extends Entity
{
    use EntityIdTrait;

    protected string $batchId;
    protected int $totalProducts;
    protected int $processedProducts;
    protected int $failedProducts;
    protected int $currentBatchSize;
    protected string $status;
    protected ?\DateTimeInterface $startedAt = null;
    protected ?\DateTimeInterface $finishedAt = null;

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function setBatchId(string $batchId): void
    {
        $this->batchId = $batchId;
    }

    public function getTotalProducts(): int
    {
        return $this->totalProducts;
    }

    public function setTotalProducts(int $totalProducts): void
    {
        $this->totalProducts = $totalProducts;
    }

    public function getProcessedProducts(): int
    {
        return $this->processedProducts;
    }

    public function setProcessedProducts(int $processedProducts): void
    {
        $this->processedProducts = $processedProducts;
    }

    public function getFailedProducts(): int
    {
        return $this->failedProducts;
    }

    public function setFailedProducts(int $failedProducts): void
    {
        $this->failedProducts = $failedProducts;
    }

    public function getCurrentBatchSize(): int
    {
        return $this->currentBatchSize;
    }

    public function setCurrentBatchSize(int $currentBatchSize): void
    {
        $this->currentBatchSize = $currentBatchSize;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeInterface $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getFinishedAt(): ?\DateTimeInterface
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTimeInterface $finishedAt): void
    {
        $this->finishedAt = $finishedAt;
    }
}
