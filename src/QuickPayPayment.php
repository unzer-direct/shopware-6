<?php declare(strict_types=1);

namespace QuickPay;

use QuickPay\Service\PaymentMethod;

class QuickPayPayment extends PaymentPlugin
{
    protected function getPaymentMethodClass(): string
    {
        return PaymentMethod::class;
    }

    protected function getPaymentMethodDescription(): string
    {
        return 'Pay using the QuickPay payment service provider.';
    }

    protected function getPaymentMethodName(): string
    {
        return 'QuickPay';
    }
}