<?php

namespace QuickPay\Service;

class CreditCardMethod extends PaymentMethod
{
    public static $name = 'QuickPay - Credit Card';
    
    public static $description = 'Pay by credit card using the QuickPay payment service provider.';
    
    public function getPaymentMethods(): string
    {
        return 'creditcard';
    }
}
