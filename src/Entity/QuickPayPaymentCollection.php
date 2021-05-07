<?php declare(strict_types=1);

namespace QuickPay\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(QuickPayPaymentEntity $entity)
 * @method void              set(string $key, QuickPayPaymentEntity $entity)
 * @method QuickPayPaymentEntity[]    getIterator()
 * @method QuickPayPaymentEntity[]    getElements()
 * @method QuickPayPaymentEntity|null get(string $key)
 * @method QuickPayPaymentEntity|null first()
 * @method QuickPayPaymentEntity|null last()
 */
class QuickPayPaymentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return QuickPayPaymentEntity::class;
    }
}