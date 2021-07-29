<?php

namespace UnzerDirect\Service;

class PayPalMethod extends PaymentMethod
{
    public static $name = 'UnzerDirect - PayPal';
    
    public static $description = 'Pay by PayPal using the UnzerDirect payment service provider.';
    
    public function getPaymentMethods(): string
    {
        return 'paypal';
    }
}
