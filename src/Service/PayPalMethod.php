<?php

namespace QuickPay\Service;

class PayPalMethod extends PaymentMethod
{
    public static $name = 'QuickPay - PayPal';
    
    public static $description = 'Pay by PayPal using the QuickPay payment service provider.';
    
    public function getPaymentMethods(): string
    {
        return 'paypal';
    }
}
