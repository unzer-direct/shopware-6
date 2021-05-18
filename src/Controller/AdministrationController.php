<?php

namespace UnzerDirect\Controller;

use Exception;
use UnzerDirect\Service\PaymentService;
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
     * @var PaymentService
     */
    private $paymentService;
    
    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @Route(
     *     "/api/v{version}/_action/unzerdirect/capture",
     *     name="api.action.unzerdirect.capture_old",
     *     methods={"POST"}
     * )
     */
    public function captureOld(Request $request): JsonResponse
    {
        return $this->capture($request);
    }
    
    /**
     * @Route(
     *     "/api/_action/unzerdirect/capture",
     *     name="api.action.unzerdirect.capture",
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
            
            $this->paymentService->requestCapture($data->id, $data->amount, Context::createDefaultContext());
            
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
     *     "/api/v{version}/_action/unzerdirect/cancel",
     *     name="api.action.unzerdirect.cancel_old",
     *     methods={"POST"}
     * )
     */
    public function cancelOld(Request $request): JsonResponse
    {
        return $this->cancel($request);
    }
    
    /**
     * @Route(
     *     "/api/_action/unzerdirect/cancel",
     *     name="api.action.unzerdirect.cancel",
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
            
            $this->paymentService->requestCancel($data->id, Context::createDefaultContext());
            
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
     *     "/api/v{version}/_action/unzerdirect/refund",
     *     name="api.action.unzerdirect.refund_old",
     *     methods={"POST"}
     * )
     */
    public function refundOld(Request $request): JsonResponse
    {
        return $this->refund($request);
    }
    
    /**
     * @Route(
     *     "/api/_action/unzerdirect/refund",
     *     name="api.action.unzerdirect.refund",
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
            
            $this->paymentService->requestRefund($data->id, $data->amount, Context::createDefaultContext());
            
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
     *     "/api/v{version}/_action/unzerdirect/refresh",
     *     name="api.action.unzerdirect.refresh_old",
     *     methods={"POST"}
     * )
     */
    public function refreshOld(Request $request): JsonResponse
    {
        return $this->refresh($request);
    }
    
    /**
     * @Route(
     *     "/api/_action/unzerdirect/refresh",
     *     name="api.action.unzerdirect.refresh",
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
            $transactionId = $this->paymentService->findTransactionId($data->id, $context);
            
            $this->paymentService->updateTransaction($transactionId, $context);
            
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
        
        return new JsonResponse(['success' => true], 200);
    }
}
