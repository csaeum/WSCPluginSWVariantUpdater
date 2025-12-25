<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Entity\VariantUpdateLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class VariantUpdateLogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'wsc_variant_update_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return VariantUpdateLogEntity::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('batch_id', 'batchId'))->addFlags(new Required()),
            new StringField('product_number', 'productNumber'),
            new LongTextField('error_message', 'errorMessage'),
            new LongTextField('stack_trace', 'stackTrace'),
        ]);
    }
}
