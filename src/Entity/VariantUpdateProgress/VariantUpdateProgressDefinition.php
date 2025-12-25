<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Entity\VariantUpdateProgress;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class VariantUpdateProgressDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wsc_variant_update_progress';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return VariantUpdateProgressEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('batch_id', 'batchId'))->addFlags(new Required()),
            (new IntField('total_products', 'totalProducts'))->addFlags(new Required()),
            (new IntField('processed_products', 'processedProducts')),
            (new IntField('failed_products', 'failedProducts')),
            (new IntField('current_batch_size', 'currentBatchSize'))->addFlags(new Required()),
            (new StringField('status', 'status'))->addFlags(new Required()),
            new DateTimeField('started_at', 'startedAt'),
            new DateTimeField('finished_at', 'finishedAt'),
        ]);
    }
}
