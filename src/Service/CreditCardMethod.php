<?php

namespace UnzerDirect\Service;

class CreditCardMethod extends PaymentMethod
{
    public static $name = 'UnzerDirect - Credit Card';
    
    public static $description = 'Pay by credit card using the UnzerDirect payment service provider.';
    
    public function getPaymentMethods(): string
    {
        return 'creditcard';
    }
}
