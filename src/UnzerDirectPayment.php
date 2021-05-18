<?php declare(strict_types=1);

namespace UnzerDirect;

use UnzerDirect\Service\PaymentMethod;

class UnzerDirectPayment extends PaymentPlugin
{
    protected function getPaymentMethodClass(): string
    {
        return PaymentMethod::class;
    }

    protected function getPaymentMethodDescription(): string
    {
        return 'Pay using the Unzer Direct payment service provider.';
    }

    protected function getPaymentMethodName(): string
    {
        return 'Unzer Direct';
    }
}