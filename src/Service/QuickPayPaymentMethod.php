<?php

namespace QuickPay\Service;

use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class QuickPayPaymentMethod implements AsynchronousPaymentHandlerInterface
{
    /**
     * @var QuickPayService
     */
    private $quickpayService;
    
    public function __construct(QuickPayService $quickpayService)
    {
        $this->quickpayService = $quickpayService;
    }
    
    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $transactionId = $transaction->getOrderTransaction()->getId();
        
        try{
            
            $url = $this->quickpayService->createOrUpdatePayment($transactionId, $transaction->getReturnUrl(), $salesChannelContext);
            
        } catch (\Exception $e) {
            throw new AsyncPaymentProcessException($transactionId, $e->getMessage());
        }
        
        return new RedirectResponse($url);
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, Request $request, SalesChannelContext $salesChannelContext): void
    {
        if($request->query->get('cancel'))
            throw new CustomerCanceledAsyncPaymentException($transaction->getOrderTransaction()->getId());
        
        $transactionId = $transaction->getOrderTransaction()->getId();
        
        try {
          
            $this->quickpayService->updateTransaction($transactionId, $salesChannelContext->getContext());
            
        } catch (Exception $ex) {
            throw new CustomerCanceledAsyncPaymentException($transaction->getOrderTransaction()->getId());
        }
    }
}
