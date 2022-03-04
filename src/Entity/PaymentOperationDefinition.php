<?php


namespace UnzerDirect\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class PaymentOperationDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'unzerdirect_payment_operation';
    }
    
    public function getEntityClass(): string
    {
        return PaymentOperationEntity::class;
    }
    
    public function getCollectionClass(): string
    {
        return PaymentOperationCollection::class;
    }
    
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('unzerdirect_payment_id', 'unzerdirectPaymentId', PaymentDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(PaymentDefinition::class, 'unzerdirect_payment_version_id'))->addFlags(new Required()),
            (new IntField('unzerdirect_operation_id', 'unzerdirectOperationId')),
            (new StringField('type', 'type'))->addFlags(new Required()),
            new StringField('status', 'status'),
            (new IntField('amount', 'amount'))->addFlags(new Required()),
            (new JsonField('raw_json', 'rawJson'))->addFlags(new Required()),
            new ManyToOneAssociationField('payment', 'unzerdirect_payment_id', PaymentDefinition::class),
        ]);
    }
}
