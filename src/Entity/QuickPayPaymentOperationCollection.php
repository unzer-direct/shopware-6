<?php declare(strict_types=1);

namespace QuickPay\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(QuickPayPaymentOperationEntity $entity)
 * @method void              set(string $key, QuickPayPaymentOperationEntity $entity)
 * @method QuickPayPaymentOperationEntity[]    getIterator()
 * @method QuickPayPaymentOperationEntity[]    getElements()
 * @method QuickPayPaymentOperationEntity|null get(string $key)
 * @method QuickPayPaymentOperationEntity|null first()
 * @method QuickPayPaymentOperationEntity|null last()
 */
class QuickPayPaymentOperationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return QuickPayPaymentOperationEntity::class;
    }
}