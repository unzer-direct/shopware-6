<?php

namespace UnzerDirect\Service;

use Exception;
use Monolog\Logger;
use UnzerDirect\UnzerDirectPayment;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use UnzerDirect\Entity\PaymentEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PaymentService
{
    private $baseUrl = 'https://api.unzerdirect.com';

    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_GET = 'GET';
    const METHOD_PATCH = 'PATCH';

    /**
     * @var SystemConfigService
     */
    private $configService;
    
    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $transactionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentOperationRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepository;
        
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(SystemConfigService $configService, StateMachineRegistry $stateMachineRegistry, EntityRepositoryInterface $transactionRepository, EntityRepositoryInterface $paymentOperationRepository, EntityRepositoryInterface $paymentRepository, EntityRepositoryInterface $languageRepository, EntityRepositoryInterface $pluginRepository, RouterInterface $router, Logger $logger)
    {
        $this->configService = $configService;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->transactionRepository = $transactionRepository;
        $this->paymentOperationRepository = $paymentOperationRepository;
        $this->paymentRepository = $paymentRepository;
        $this->languageRepository = $languageRepository;
        $this->pluginRepository = $pluginRepository;
        $this->router = $router;
        $this->logger = $logger;
    }

    private function log($level, $message, $context = [])
    {
        if(!is_array($context))
            $context = get_object_vars ($context);
        
        $this->logger->log($level, $message, $context);
    }
    
    /**
     * Get or create a payment for a given transaction
     *
     * @param string $transactionId
     * @param PaymentMethod $paymentMethod
     * @param string $returnUrl
     * @param SalesChannelContext $context
     * @return string Payment Link
     */
    public function createOrUpdatePayment(string $transactionId, PaymentMethod $paymentMethod, string $returnUrl, SalesChannelContext $context)
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociations(['order', 'order.currency', 'order.orderCustomer', 'unzerdirectPamyent']);
        
        /** @var OrderTransactionEntity $transaction */
        $transaction = $this->transactionRepository->search($criteria, $context->getContext())->first();
        
        if(!$transaction){
            throw new Exception('Invalid transaction ID');
        }
        
        //Create UnzerDirect Payment if necessary
        $currency = $transaction->getOrder()->getCurrency()->getIsoCode();
        
        if($transaction->hasExtension('unzerdirectPayment'))
        {
            /** @var PaymentEntity $payment */
            $payment = $transaction->getExtension('unzerdirectPayment');
            
            if($payment->getStatus() !== PaymentEntity::PAYMENT_CREATED) {
                throw new Exception('Payment already processed');
            }
            
            if($currency !== $payment->getCurrency()) {
                throw new Exception('Payment Currency cannot be changed');
            }
                
            $paymentId = $payment->getId();
            $unzerdirectOrderId = $payment->getUnzerDirectOrderId();
            $unzerdirectId = $payment->getUnzerDirectId();
            $this->updatePayment($returnUrl, $paymentMethod, $context);
        }
        else
        {
            $paymentId = Uuid::randomHex();
            $unzerdirectId = $this->createPayment($transactionId, $paymentMethod, $currency, $unzerdirectOrderId, $context);
            $unzerdirectOrderId = $this->createOrderId();
            $isNew = true;
        }
        
        //Create PaymentLink
        $amount = intval(round($transaction->getAmount()->getTotalPrice() * 100));
        $email = $transaction->getOrder()->getOrderCustomer()->getEmail();
        
        $languageCriteria = new Criteria([
            $context->getContext()->getLanguageId()
        ]);
        $languageCriteria->addAssociation('locale');
        
        /** @var LanguageEntity $language */
        $language = $this->languageRepository->search($languageCriteria, $context->getContext())->first();
        
        $link = $this->createPaymentLink($unzerdirectId, $paymentMethod, $amount, $email, $language->getLocale()->getCode(), $returnUrl, $context->getSalesChannelId());
        
        $this->transactionRepository->update([[
            'id' => $transactionId,
            'unzerdirectPayment' => [
                'id' => $paymentId,
                'unzerdirectOrderId' => $unzerdirectOrderId,
                'unzerdirectId' => "$unzerdirectId",
                'currency' => $currency,
                'amount' => $amount,
                'link' => $link
            ]
        ]], $context->getContext());
        
        if($isNew)
        {
            $this->handleNewOperation($paymentId, 'create', $context->getContext(), ['unzerdirect_order_id' => $unzerdirectOrderId]);
        }
        
        return $link;
    }
    
    /**
     * @param string $unzerdirectId
     * @param string $transactionId
     * @param PaymentMethod $paymentMethod
     * @param SalesChannelContext $context
     */
    private function updatePayment(string $unzerdirectId, string $transactionId, PaymentMethod $paymentMethod, SalesChannelContext $context)
    {
        $parameters = [
            'basket' => $this->getBasketParameter($transactionId, $paymentMethod, $context->getContext()),
            'shipping' => $this->getShippingParameter($transactionId, $paymentMethod, $context->getContext())
        ];
        
        $this->log(Logger::DEBUG, 'payment update requested', $parameters);
        //Create payment
        $resource = sprintf('/payments/%s', $unzerdirectId);
        $paymentData = $this->request(self::METHOD_PATCH, $resource, $context->getSalesChannelId(), $parameters);
        $this->log(Logger::INFO, 'payment updated', $paymentData);
    }
    
    /**
     * @param string $transactionId
     * @param PaymentMethod $paymentMethod
     * @param string $currency
     * @param string $orderId
     * @param SalesChannelContext $context
     * @return string
     */
    private function createPayment(string $transactionId, PaymentMethod $paymentMethod, string $currency, string $orderId, SalesChannelContext $context)
    {
        $parameters = [
            'currency' => $currency,
            'order_id' => $orderId,
            'basket' => $this->getBasketParameter($transactionId, $paymentMethod, $context->getContext()),
            'shipping' => $this->getShippingParameter($transactionId, $paymentMethod, $context->getContext()),
            'shopsystem' => [
                'name' => 'Shopware 6',
                'version' => $this->getPluginVersion($context->getContext())
            ],
            'branding_id' => $this->getBrandingIdConfig($context->getSalesChannelId()),
            'variables' => [
                'transaction_id' => $transactionId
            ]
        ];
        
        $this->log(Logger::DEBUG, 'payment creation requested', $parameters);
        //Create payment
        $paymentData = $this->request(self::METHOD_POST, '/payments', $context->getSalesChannelId(), $parameters);
        $this->log(Logger::INFO, 'payment created', $paymentData);
        
        return $paymentData->id;
    }
    
    /**
     * @param string $transactionId
     * @param PaymentMethod $paymentMethod
     * @return array
     */
    private function getBasketParameter(string $transactionId, PaymentMethod $paymentMethod, Context $context): array
    {
        if(!$paymentMethod->withBasket())
            return [];
        
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociations(['order', 'order.lineItems', 'order.lineItems.product']);
        
        /** @var OrderTransactionEntity $transaction */
        $transaction = $this->transactionRepository->search($criteria, $context)->first();
        
        $basket = [];
        
        /** @var OrderLineItemEntity $lineItem */
        foreach($transaction->getOrder()->getLineItems() as $lineItem)
        {
            $price = $lineItem->getPrice();
            $basket[] = [
                'qty' => $lineItem->getQuantity(),
                'item_no' => $lineItem->getProduct()->getProductNumber(),
                'item_name' => $lineItem->getLabel(),
                'item_price' => $price->getTotalPrice() * 100,
                'vat_rate' => $price->getTaxRules()->count() > 0 ? $price->getTaxRules()->first()->getTaxRate() / 100.0 : 0
            ];
        }
        
        $shippingPrice = $transaction->getOrder()->getShippingCosts();

        return $basket;
    }
    
    /**
     * @param string $transactionId
     * @param PaymentMethod $paymentMethod
     * @return array
     */
    private function getShippingParameter(string $transactionId, PaymentMethod $paymentMethod, Context $context): array
    {
        if(!$paymentMethod->withBasket())
            return [];
        
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociations(['order', 'order.lineItems', 'order.lineItems.product']);
        
        /** @var OrderTransactionEntity $transaction */
        $transaction = $this->transactionRepository->search($criteria, $context)->first();
        
        $shippingPrice = $transaction->getOrder()->getShippingCosts();
        $shipping = [
            'amount' => $shippingPrice->getTotalPrice() * 100,
            'vat_rate' => $shippingPrice->getTaxRules()->count() > 0 ? $shippingPrice->getTaxRules()->first()->getTaxRate() / 100.0 : 0
        ];

        return $shipping;
    }
    
    /**
     * Create payment link
     *
     * @param string $paymentId QuickPay payment ID
     * @param PaymentMethod $paymentMethod
     * @param string $paymentId UnzerDirect payment ID
     * @param string $transactionId UnzerDirect transactionId
     * @param integer $amount invoice amount of the order
     * @param string $email Mail-address of the customer
     * @param string $locale Locale of the customer
     * @param string $returnUrl Shopware return URL
     * @param string $salesChannelId
     *
     * @return string link for UnzerDirect payment
     */
    private function createPaymentLink(string $paymentId, PaymentMethod $paymentMethod, int $amount, string $email, string $locale,  string $returnUrl, string $salesChannelId)
    {
        $continueUrl = $returnUrl;
        $cancelUrl = str_contains($returnUrl, '?') ?
            $returnUrl . '&cancel=true' :
            $returnUrl . '?cancel=true';
        $callbackUrl = $this->getCallbackUrl();
        
        $resource = sprintf('/payments/%s/link', $paymentId);
        $parameters = [
            'amount'             => $amount,
            'continueurl'        => $continueUrl,
            'cancelurl'          => $cancelUrl,
            'callbackurl'        => $callbackUrl,
            'customer_email'     => $email,
            'language'           => substr($locale, 0, 2),
            'payment_methods'    => $paymentMethod->getPaymentMethods()
        ];
        
        $this->log(Logger::DEBUG, 'payment link creation requested', $parameters);
        $paymentLink = $this->request(self::METHOD_PUT, $resource, $salesChannelId, $parameters);
        $this->log(Logger::INFO, 'payment link created', $paymentLink);

        return $paymentLink->url;
    }
    
    public function findTransactionId(string $paymentId, Context $context)
    {
        $criteria = new Criteria([$paymentId]);
        /** @var PaymentEntity $payment */
        $payment = $this->paymentRepository->search($criteria, $context)->first();
        
        if(!$payment)
            throw new Exception('Invalid payment ID');
        
        return $payment->getTransactionId();
    }
    
    /**
     * Update the state of a transaction using the unzerdirect payment data
     * 
     * @param string $transactionId
     * @param Context $context
     * @param type $paymentData
     * @throws Exception
     */
    public function updateTransaction(string $transactionId, Context $context, $paymentData = null)
    {
        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociations(['unzerdirectPayment', 'unzerdirectPayment.operations', 'order']);
        
        /** @var OrderTransactionEntity $transaction */
        $transaction = $this->transactionRepository->search($criteria, $context)->first();

        if(!$transaction || !$transaction->hasExtension('unzerdirectPayment')) {
            throw new \Exception('Invalid transaction');
        }
        
        $salesChannelId = $transaction->getOrder()->getSalesChannelId();
        
        /** @var PaymentEntity $payment */
        $payment = $transaction->getExtension('unzerdirectPayment');
        
        if(!$paymentData)
        {
            $paymentData = $this->fetchPaymentData($payment->getUnzerDirectId(), $salesChannelId);
        }
        
        if($paymentData->test_mode && !$this->getTestModeConfig($salesChannelId))
        {
            $this->registerTestModeViolationCallback($payment->getId(), $context, $paymentData);
            $this->log(Logger::WARNING, 'payment with wrong test card attempted', $paymentData);
            
            throw new \Exception('payment attempt with test data');
        }
        
        $this->updatePaymentOperations($payment, $paymentData, $context);
        $this->updateStatus($payment, $context);
    }
    
    /**
     * @param string $paymentId
     * @param string $salesChannelId
     * @return object
     */
    private function fetchPaymentData(string $paymentId, string $salesChannelId)
    {
        $resource = sprintf('/payments/%s', $paymentId);
        
        $this->log(Logger::DEBUG, 'fetching payment data', [$paymentId]);
        $paymentData = $this->request(self::METHOD_GET, $resource, $salesChannelId);
        $this->log(Logger::INFO, 'payment data fetched', $paymentData);
        
        return $paymentData;
    }
    
    /**
     * Update the stored payment operations
     * 
     * @param object $payment
     */
    private function updatePaymentOperations(PaymentEntity $payment, object $paymentData, Context $context)
    {
        $operations = $payment->getOperations();
        
        $operationsData = $paymentData->operations ?? [];
        
        $updateData = [];
        
        foreach($operationsData as $operation)
        {
            $existing = $operations->filterByProperty('unzerdirectOperationId', $operation->id)->first();
            
            $operationId = $existing ? $existing->getId() : Uuid::randomHex();
            
            $updateData[] = [
                'id' => $operationId,
                'unzerdirectOperationId' => $operation->id,
                'type' => $operation->type,
                'status' => $operation->qp_status_code,
                'amount' => $operation->amount ?? 0,
                'rawJson' => json_decode(json_encode($operation),true)
            ];
        }
        
        $this->paymentRepository->update([[
            'id' => $payment->getId(),
            'operations' => $updateData
        ]], $context);
    }

    /**
     * Update the status of the UnzerDirect payment according to the operations
     * 
     * @param PaymentEntity $payment
     * @param Context $context
     */
    private function updateStatus(PaymentEntity $payment, Context $context)
    {
        $previousStatus = $payment->getStatus();
        
        // Update operations
        $criteria = new Criteria([$payment->getId()]);
        $criteria->addAssociation('operations');
        $criteria->getAssociation('operations')->addSorting(
            new FieldSorting('createdAt'),
            new FieldSorting('unzerdirectOperationId')
        );
        $payment = $this->paymentRepository->search($criteria, $context)->first();
            
        $operations = $payment->getOperations();
        
        $amount = $payment->getAmount();
        $amountAuthorized = 0;
        $amountCaptured = 0;
        $amountRefunded = 0;
        $status = PaymentEntity::PAYMENT_CREATED;
        
        /** @var UnzerDirectPaymentOperation $operation */
        foreach($operations as $operation)
        {
            
            switch ($operation->getType())
            {
                case 'authorize':
                    if($operation->isSuccessfull())
                    {
                        $amountAuthorized += $operation->getAmount();

                        if($amount <= $amountAuthorized)
                        {
                            $status = PaymentEntity::PAYMENT_FULLY_AUTHORIZED;
                        }
                    }
                    break;

                case 'capture_request':
                    
                    $status = PaymentEntity::PAYMENT_CAPTURE_REQUESTED;

                    break;

                case 'capture':
                    if($operation->isSuccessfull())
                    {
                        $amountCaptured += $operation->getAmount();

                        if($amount <= $amountCaptured)
                        {
                            $status = PaymentEntity::PAYMENT_FULLY_CAPTURED;
                        }
                        else
                        {
                            $status = PaymentEntity::PAYMENT_PARTLY_CAPTURED;
                        }
                    }
                    else if($operation->isFinished())
                    {
                        if($amountCaptured > 0)
                        {
                            $status = PaymentEntity::PAYMENT_PARTLY_CAPTURED;
                        }
                        else
                        {
                            $status = PaymentEntity::PAYMENT_FULLY_AUTHORIZED;
                        }
                    }
                    break;

                case 'cancel_request':
                    $status = PaymentEntity::PAYMENT_CANCEL_REQUSTED;

                    break;

                case 'cancel':
                    if($operation->isSuccessfull())
                    {
                        $status = PaymentEntity::PAYMENT_CANCELLED;
                    }
                    else if($operation->isFinished())
                    {
                        $status = PaymentEntity::PAYMENT_FULLY_AUTHORIZED;
                    }

                    break;

                case 'refund_request':

                    $status = PaymentEntity::PAYMENT_REFUND_REQUSTED;

                    break;

                case 'refund':
                    if($operation->isSuccessfull())
                    {
                        $amountRefunded += $operation->getAmount();

                        if($amountCaptured <= $amountRefunded)
                        {
                            $status = PaymentEntity::PAYMENT_FULLY_REFUNDED;
                        }
                        else
                        {
                            $status = PaymentEntity::PAYMENT_PARTLY_REFUNDED;
                        }
                    }
                    else
                    {
                        if($amountRefunded > 0)
                        {
                            $status = PaymentEntity::PAYMENT_PARTLY_REFUNDED;
                        }
                        else
                        {
                            if($amountCaptured < $amount)
                            {
                                $status = PaymentEntity::PAYMENT_PARTLY_CAPTURED;
                            }
                            else
                            {
                                $status = PaymentEntity::PAYMENT_FULLY_CAPTURED;
                            }
                        }
                    }

                    break;

                case 'checksum_failure':
                case 'test_mode_violation':
                    $status = PaymentEntity::PAYMENT_INVALIDATED;
                    break;

                default:
                    break;
            }

        }

        $this->paymentRepository->update([[
            'id' => $payment->getId(),
            'amount' => $amount,
            'amountAuthorized' => $amountAuthorized,
            'amountCaptured' => $amountCaptured,
            'amountRefunded' => $amountRefunded,
            'status' => $status,
        ]], $context);
        
        $this->updateTransactionStatus($payment->getTransactionId(), $status, $context);
    }
    
    private function updateTransactionStatus(string $transactionId, int $paymentStatus, Context $context)
    {
        $actions = [];
        switch($paymentStatus)
        {
            case PaymentEntity::PAYMENT_FULLY_AUTHORIZED:
                $actions[] = StateMachineTransitionActions::ACTION_AUTHORIZE;
                break;
            case PaymentEntity::PAYMENT_PARTLY_CAPTURED:
                $actions[] = StateMachineTransitionActions::ACTION_DO_PAY;
                $actions[] = StateMachineTransitionActions::ACTION_PAID_PARTIALLY;
                break;
            case PaymentEntity::PAYMENT_FULLY_CAPTURED:
                $actions[] = StateMachineTransitionActions::ACTION_DO_PAY;
                $actions[] = StateMachineTransitionActions::ACTION_PAID;
                break;
            case PaymentEntity::PAYMENT_CANCELLED:
                $actions[] = StateMachineTransitionActions::ACTION_CANCEL;
                break;
            case PaymentEntity::PAYMENT_PARTLY_REFUNDED:
                $actions[] = StateMachineTransitionActions::ACTION_REFUND_PARTIALLY;
                break;
            case PaymentEntity::PAYMENT_FULLY_REFUNDED:
                $actions[] = StateMachineTransitionActions::ACTION_REFUND;
                break;
            case PaymentEntity::PAYMENT_INVALIDATED:
                $actions[] = StateMachineTransitionActions::ACTION_FAIL;
                break;
            
        }
        if($actions)
        {
            foreach($actions as $action)
            {
                try {
                
                    $this->stateMachineRegistry->transition(
                        new Transition(
                            OrderTransactionDefinition::ENTITY_NAME,
                            $transactionId,
                            $action,
                            'stateId'
                        ),
                        $context
                    );
                } catch (Exception $e) {
                }
            }
        }
    }
    
    /**
     * Register a callback containing a bad checksum
     * 
     * @param string $paymentId the linked payment object
     * @param Context $context
     * @param mixed $data data contained in the request body
     */
    private function registerFalseChecksumCallback($paymentId, Context $context, $data)
    {        
        $this->handleNewOperation($paymentId, 'checksum_failure', $context, $data);
    }
    
    /**
     * Register a callback containing wrong test mode settings
     * 
     * @param string $paymentId the linked payment object
     * @param Context $context
     * @param mixed $data data contained in the request body
     */
    private function registerTestModeViolationCallback($paymentId, Context $context, $data)
    {
        $this->handleNewOperation($paymentId, 'test_mode_violation', $context, $data);
    }

    /**
     * send a capture request to the UnzerDirect API
     * 
     * @param string $paymentId
     * @param integer $amount
     * @param Context $context
     */
    public function requestCapture($paymentId, int $amount, Context $context)
    {
        $criteria = new Criteria([$paymentId]);
        $criteria->addAssociation('transaction.order');
        /** @var PaymentEntity $payment */
        $payment = $this->paymentRepository->search($criteria, $context)
            ->first();
        
        if(!$payment)
            throw new Exception('Invalid payment ID');
        
        $salesChannelId = $payment->getTransaction()->getOrder()->getSalesChannelId();
        
        if($payment->getStatus() != PaymentEntity::PAYMENT_FULLY_AUTHORIZED
            && $payment->getStatus() != PaymentEntity::PAYMENT_PARTLY_CAPTURED)
        {
            throw new Exception('Invalid payment state');
        }
        
        if($amount <= 0 || $amount > $payment->getAmountAuthorized() - $payment->getAmountCaptured())
        {
            throw new Exception('Invalid amount');
        }
        
        try
        {

            $resource = sprintf('/payments/%s/capture', $payment->getUnzerDirectId());
            $this->log(Logger::DEBUG, 'payment capture requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, $salesChannelId, [
                    'amount' => $amount
                ], 
                [
                    'QuickPay-Callback-Url' => $this->getCallbackUrl($paymentId)
                ]);
            $this->log(Logger::INFO, 'payment captured', $paymentData);

            $this->handleNewOperation($paymentId, 'capture_request', $context, [], $amount);
            $this->updateStatus($payment, $context);
            
        }
        catch (Exception $ex)
        {
            $this->log(Logger::ERROR, 'exception during capture', ['message' => $ex->getMessage()]);
            
            throw $ex;
        }
    }

    /**
     * send a capture request to the UnzerDirect API
     * 
     * @param string $paymentId
     * @param Context $context
     */
    public function requestCancel($paymentId, Context $context)
    {
        $criteria = new Criteria([$paymentId]);
        $criteria->addAssociation('transaction.order');
        /** @var PaymentEntity $payment */
        $payment = $this->paymentRepository->search($criteria, $context)
            ->first();
        
        if(!$payment)
            throw new Exception('Invalid payment ID');
        
        $salesChannelId = $payment->getTransaction()->getOrder()->getSalesChannelId();
        
        if($payment->getStatus() != PaymentEntity::PAYMENT_FULLY_AUTHORIZED
            && $payment->getStatus() != PaymentEntity::PAYMENT_CREATED)
        {
            throw new Exception('Invalid payment state');
        }
        
        if($payment->getAmountCaptured() > 0)
        {
            throw new Exception('Payment already (partly) captured');
        }
        
        try
        {

            $resource = sprintf('/payments/%s/cancel', $payment->getUnzerDirectId());
            $this->log(Logger::DEBUG, 'payment cancellation requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, $salesChannelId, [], 
                [
                    'QuickPay-Callback-Url' => $this->getCallbackUrl($paymentId)
                ]);
            $this->log(Logger::DEBUG, 'payment canceled', $paymentData);

            $this->handleNewOperation($paymentId, 'cancel_request', $context);
            $this->updateStatus($payment, $context);
            
        } catch (Exception $ex) {
            $this->log(Logger::ERROR, 'exception during cancellation', ['message' => $ex->getMessage()]);
            
            throw $ex;
        }        
    }

    /**
     * send a capture request to the UnzerDirect API
     * 
     * @param string $paymentId
     * @param integer $amount
     * @param Context $context
     */
    public function requestRefund(string $paymentId, int $amount, Context $context)
    {
        $criteria = new Criteria([$paymentId]);
        $criteria->addAssociation('transaction.order');
        /** @var PaymentEntity $payment */
        $payment = $this->paymentRepository->search($criteria, $context)
            ->first();
        
        if(!$payment)
            throw new Exception('Invalid payment ID');
        
        $salesChannelId = $payment->getTransaction()->getOrder()->getSalesChannelId();
        
        if($payment->getStatus() != PaymentEntity::PAYMENT_FULLY_CAPTURED
            && $payment->getStatus() != PaymentEntity::PAYMENT_PARTLY_CAPTURED
            && $payment->getStatus() != PaymentEntity::PAYMENT_PARTLY_REFUNDED)
        {
            throw new Exception('Invalid payment state');
        }
        
        if($amount <= 0 || $amount > $payment->getAmountCaptured() - $payment->getAmountRefunded())
        {
            throw new Exception('Invalid amount');
        
        }
        
        try
        {
            
            $resource = sprintf('/payments/%s/refund', $payment->getUnzerDirectId());
            $this->log(Logger::DEBUG, 'payment refund requested');
            $paymentData = $this->request(self::METHOD_POST, $resource, $salesChannelId, [
                    'amount' => $amount
                ], 
                [
                    'QuickPay-Callback-Url' => $this->getCallbackUrl($paymentId)
                ]);
            $this->log(Logger::DEBUG, 'payment refunded', $paymentData);

            $this->handleNewOperation($paymentId, 'refund_request', $context, [], $amount);
            $this->updateStatus($payment, $context);
            
        } catch (Exception $ex) {
            $this->log(Logger::ERROR, 'exception during refund', ['message' => $ex->getMessage()]);
            
            throw $ex;
        }
        
    }

    /**
     * Create a UnzerDirect payment operation
     * 
     * @param string $paymentId
     * @param string $type
     * @param Context $context
     * @param integer $amount
     */
    private function handleNewOperation(string $paymentId, string $type, Context $context, array $payload = [], int $amount = 0)
    {
        $this->paymentOperationRepository->create([[
            'id' => Uuid::randomHex(),
            
            'unzerdirectPaymentId' => $paymentId,
            'type' => $type,
            'amount' => $amount,
            'rawJson' => json_decode(json_encode($payload), true)
        ]], $context);
    
    }
    
    /**
     * Perform API request
     *
     * @param string $method
     * @param $resource
     * @param string $salesChannelId
     * @param array $params
     * @param bool $headers
     */
    private function request(string $method, string $resource, string $salesChannelId, array $params = [], array $headers = [])
    {
        $ch = curl_init();

        $url = $this->baseUrl . $resource;
        
        //Set CURL options
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_HTTPHEADER     => $this->getHeaders($headers, $salesChannelId),
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($params),
        ];

        curl_setopt_array($ch, $options);

        $this->log(Logger::DEBUG, 'request sent', $options);
        //Get response
        $result = curl_exec($ch);
        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->log(Logger::DEBUG, 'request finished', ['code' => $responseCode, 'response' => $result]);
        
        curl_close($ch);

        //Validate reponsecode
        if (! in_array($responseCode, [200, 201, 202])) {
            throw new Exception('Invalid gateway response ' . $result);
        }

        $response = json_decode($result);

        //Check for JSON errors
        if (! $response || (json_last_error() !== JSON_ERROR_NONE)) {
            throw new Exception('Invalid json response');
        }

        return $response;
    }

    /**
     * Get CURL headers
     *
     * @param array $headers list of additional headers
     * @param string $salesChannelId
     * @return array
     */
    private function getHeaders(array $headers, string $salesChannelId)
    {
        $result = [
            'Authorization: Basic ' . base64_encode(':' . $this->getApiKeyConfig($salesChannelId)),
            'Accept-Version: v10',
            'Accept: application/json',
            'Content-Type:application/json'
        ];
        
        foreach ($headers as $key => $value)
        {
            $result[] = $key. ': '. $value;
        }
        
        return $result;
    }

    public function validateChecksum(string $unzerdirectId, Request $request, SalesChannelContext $context)
    {
        $key = $this->getPrivateKeyConfig($context->getSalesChannelId());
        $checksum = hash_hmac('sha256', $request->getContent(), $key);
        $submittedChecksum = $request->headers->get('quickpay-checksum-sha256');

        if($checksum !== $submittedChecksum)
        {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('unzerdirectId', $unzerdirectId));
            $paymentId = $this->paymentRepository->searchIds($criteria, $context->getContext())
                ->firstId();
            
            if(!$paymentId)
                throw new Exception('Unknown payment id');
            
            $this->registerFalseChecksumCallback($paymentId, $context->getContext(), [
                'submitted' => $submittedChecksum,
                'calculated' => $checksum,
                'headers' => $request->headers->all()
            ]);
            
            throw new Exception('Invalid Checksum');
        }
    }
    
    /**
     * Get API key from config
     *
     * @param string $salesChannelId
     * @return mixed
     */
    private function getApiKeyConfig(string $salesChannelId)
    {
        return $this->configService->get('UnzerDirectPayment.config.publicKey', $salesChannelId);
    }

    /**
     * Get private key from config
     *
     * @param string $salesChannelId
     * @return mixed
     */
    private function getPrivateKeyConfig(string $salesChannelId)
    {
        return $this->configService->get('UnzerDirectPayment.config.privateKey', $salesChannelId);
    }

    /**
     * Get API key from config
     *
     * @param string $salesChannelId
     * @return mixed
     */
    private function getTestModeConfig(string $salesChannelId): bool
    {
        return $this->configService->get('UnzerDirectPayment.config.testmode', $salesChannelId);
    }

    /**
     * Get Branding ID from config
     *
     * @param string $salesChannelId
     * @return string
     */
    private function getBrandingIdConfig(string $salesChannelId): ?string
    {
        return $this->configService->get('UnzerDirectPayment.config.brandingId', $salesChannelId);
    }
    
    /**
     * Get the Version number of the payment plugin
     * 
     * @param Context $context
     * @return string
     */
    private function getPluginVersion(Context $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('baseClass', UnzerDirectPayment::class));
        
        /** @var PluginEntity $plugin */
        $plugin = $this->pluginRepository->search($criteria, $context)->first();
        
        return $plugin ? $plugin->getVersion() : '1.0.0';
    }
    
    /**
     * Creates a unique order id
     * 
     * @return string
     */
    private function createOrderId()
    {
        return Random::getAlphanumericString(20);
    }
    
    /**
     * Get the URL for UnzerDirect-Callbacks
     * 
     * @return string
     */
    private function getCallbackUrl()
    {
        return $this->router->generate('unzerdirect.callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
    
}
