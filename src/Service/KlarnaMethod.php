<?php

namespace QuickPay\Service;

class KlarnaMethod extends PaymentMethod
{
    public static $name = 'QuickPay - Klarna';
    
    public static $description = 'Pay by Klarna using the QuickPay payment service provider.';
    
    public function getPaymentMethods(): string
    {
        return 'klarna-payments';
    }
}
