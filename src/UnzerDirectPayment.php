<?php declare(strict_types=1);

namespace UnzerDirect;

use UnzerDirect\Service\PaymentMethod;

class UnzerDirectPayment extends PaymentPlugin
{
    protected function getPaymentMethodClasses(): array
    {
        return [
            CreditCardMethod::class,
            PayPalMethod::class,
            KlarnaMethod::class
        ];
    }
}