<?php

namespace QuickPay\Controller;

use Exception;
use QuickPay\Service\QuickPayService;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;

/**
 * @RouteScope(scopes={"api"})
 */
class AdministrationController
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
     * @Route(
     *     "/api/v{version}/_action/quickpay/capture",
     *     name="api.action.quickpay.capture",
     *     methods={"POST"}
     * )
     */
    public function captureOld(Request $request): JsonResponse
    {
        return $this->capture($request);
    }
    
    /**
     * @Route(
     *     "/api/_action/quickpay/capture",
     *     name="api.action.quickpay.capture",
     *     methods={"POST"}
     * )
     */
    public function capture(Request $request): JsonResponse
    {
        try {
            $body = $request->getContent();
            
            $data = json_decode($body);
            
            if(!isset($data->id) || !isset($data->amount))
                throw new Exception('Invalid request body');
            
            $this->quickpayService->requestCapture($data->id, $data->amount, Context::createDefaultContext());
            
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
        
        return new JsonResponse(['success' => true], 200);
    }

    /**
     * @Route(
     *     "/api/v{version}/_action/quickpay/cancel",
     *     name="api.action.quickpay.cancel",
     *     methods={"POST"}
     * )
     */
    public function cancelOld(Request $request): JsonResponse
    {
        return $this->cancel($request);
    }
    
    /**
     * @Route(
     *     "/api/_action/quickpay/cancel",
     *     name="api.action.quickpay.cancel",
     *     methods={"POST"}
     * )
     */
    public function cancel(Request $request): JsonResponse
    {
        try {
            $body = $request->getContent();
            
            $data = json_decode($body);
            
            if(!isset($data->id))
                throw new Exception('Invalid request body');
            
            $this->quickpayService->requestCancel($data->id, Context::createDefaultContext());
            
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
        
        return new JsonResponse(['success' => true], 200);
    }

    /**
     * @Route(
     *     "/api/v{version}/_action/quickpay/refund",
     *     name="api.action.quickpay.refund",
     *     methods={"POST"}
     * )
     */
    public function refundOld(Request $request): JsonResponse
    {
        return $this->refund($request);
    }
    
    /**
     * @Route(
     *     "/api/_action/quickpay/refund",
     *     name="api.action.quickpay.refund",
     *     methods={"POST"}
     * )
     */
    public function refund(Request $request): JsonResponse
    {
        try {
            $body = $request->getContent();
            
            $data = json_decode($body);
            
            if(!isset($data->id) || !isset($data->amount))
                throw new Exception('Invalid request body');
            
            $this->quickpayService->requestRefund($data->id, $data->amount, Context::createDefaultContext());
            
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
        
        return new JsonResponse(['success' => true], 200);
    }

    /**
     * @Route(
     *     "/api/v{version}/_action/quickpay/refresh",
     *     name="api.action.quickpay.refresh",
     *     methods={"POST"}
     * )
     */
    public function refreshOld(Request $request): JsonResponse
    {
        return $this->refresh($request);
    }
    
    /**
     * @Route(
     *     "/api/_action/quickpay/refresh",
     *     name="api.action.quickpay.refresh",
     *     methods={"POST"}
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $body = $request->getContent();
            
            $data = json_decode($body);
            
            if(!isset($data->id))
                throw new Exception('Invalid request body');
            
            $context = Context::createDefaultContext();
            $transactionId = $this->quickpayService->findTransactionId($data->id, $context);
            
            $this->quickpayService->updateTransaction($transactionId, $context);
            
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
        
        return new JsonResponse(['success' => true], 200);
    }
}
