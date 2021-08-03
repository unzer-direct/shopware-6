<?php declare(strict_types=1);

namespace UnzerDirect;

use UnzerDirect\Service\CreditCardMethod;
use UnzerDirect\Service\KlarnaMethod;
use UnzerDirect\Service\PayPalMethod;

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