<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Entity\VariantUpdateLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class VariantUpdateLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $batchId;
    protected ?string $productNumber = null;
    protected ?string $errorMessage = null;
    protected ?string $stackTrace = null;

    public function getBatchId(): string
    {
        return $this->batchId;
    }

    public function setBatchId(string $batchId): void
    {
        $this->batchId = $batchId;
    }

    public function getProductNumber(): ?string
    {
        return $this->productNumber;
    }

    public function setProductNumber(?string $productNumber): void
    {
        $this->productNumber = $productNumber;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): void
    {
        $this->errorMessage = $errorMessage;
    }

    public function getStackTrace(): ?string
    {
        return $this->stackTrace;
    }

    public function setStackTrace(?string $stackTrace): void
    {
        $this->stackTrace = $stackTrace;
    }
}
