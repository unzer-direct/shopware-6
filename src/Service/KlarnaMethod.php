<?php

namespace UnzerDirect\Service;

class KlarnaMethod extends PaymentMethod
{
    public static $name = 'UnzerDirect - Klarna';
    
    public static $description = 'Pay by Klarna using the UnzerDirect payment service provider.';
    
    public function getPaymentMethods(): string
    {
        return 'klarna-payments';
    }
}
