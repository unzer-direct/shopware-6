<?php

namespace QuickPay\Controller;

use Exception;
use QuickPay\Service\QuickPayService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

/**
 * @RouteScope(scopes={"storefront"})
 */
class StorefrontController
{
    /**
     * @var QuickPayService
     */
    private $quickpayService;
    
    public function __construct(QuickPayService $quickpayService)
    {
        $this->quickpayService = $quickpayService;
    }

    /**
     * @Route("/quickpay/callback", name="quickpay.callback", defaults={"csrf_protected"=false}, options={"seo"="false"}, methods={"GET", "POST"})
     */
    public function callback(Request $request, SalesChannelContext $context): Response
    {
        try {
            $body = $request->getContent();
            
            $paymentData = json_decode($body);
            $paymentId = $paymentData->id ?? null;
            $transactionId = $paymentData->variables->transaction_id ?? null;
            
            if(!$paymentId || !$transactionId)
                throw new Exception('Invalid request body');
            
            
            $this->quickpayService->validateChecksum($paymentId, $request, $context);
            
            $this->quickpayService->updateTransaction($transactionId, $context->getContext(), $paymentData);
            
        } catch (Exception $e) {
            return new Response($e->getMessage(), 400);
        }
        
        return new Response('OK', 200);
    }
    
}
