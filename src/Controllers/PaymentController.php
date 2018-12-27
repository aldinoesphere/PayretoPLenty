<?php

namespace Payreto\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Modules\Basket\Events\Basket\AfterBasketChanged;
use Plenty\Modules\Basket\Contracts\BasketItemRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Authorization\Services\AuthHelper;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Templates\Twig;

use IO\Api\ApiResource;
use IO\Api\ApiResponse;
use IO\Services\NotificationService;

use Payreto\Services\GatewayService;
use Payreto\Helper\PaymentHelper;
use Payreto\Helper\BasketHelper;
use Payreto\Services\PaymentService;
use Payreto\Services\OrderService;
use Payreto\Controllers\SettingsController;
use Payreto\Controllers\AccountController;
/**
* Class PaymentController
* @package Payreto\Controllers
*/
class PaymentController extends Controller
{
	use Loggable;

	/**
	 * @var Request
	 */
	private $request;

	/**
	 * @var Response
	 */
	private $response;

	/**
	 * @var BasketItemRepositoryContract
	 */
	private $basketItemRepository;

	/**
	 * @var SessionStorage
	 */
	private $sessionStorage;

	/**
	 *
	 * @var gatewayService
	 */
	private $gatewayService;

	/**
	 *
	 * @var paymentHelper
	 */
	private $paymentHelper;

	/**
	 *
	 * @var orderService
	 */
	private $orderService;

	/**
     * @var OrderRepositoryContract
     */
    private $orderContract;

	/**
	 *
	 * @var paymentService
	 */
	private $paymentService;

    /**
     *
     * @var settingsController
     */
    private $settingsController;

    /**
     *
     * @var authHelper
     */
    private $authHelper;

    /**
     *
     * @var apiResponse
     */
    private $apiResponse;

    /**
     *
     * @var notification
     */
    private $notification;

    /**
     *
     * @var accountController
     */
    private $accountController;

    /**
     *
     * @var basketHelper
     */
    private $basketHelper;

	private $payretoSettings;

	/**
	 * PaymentController constructor.
	 *
	 * @param Request $request
	 * @param Response $response
	 * @param BasketItemRepositoryContract $basketItemRepository
	 * @param SessionStorageService $sessionStorage
	 */
	public function __construct(
					Request $request,
					Response $response,
					BasketItemRepositoryContract $basketItemRepository,
					FrontendSessionStorageFactoryContract $sessionStorage,
					GatewayService $gatewayService,
					PaymentHelper $paymentHelper,
					OrderService $orderService,
					OrderRepositoryContract $orderContract,
					PaymentService $paymentService,
                    AuthHelper $authHelper,
                    SettingsController $settingsController,
                    BasketHelper $basketHelper,
                    ApiResponse $apiResponse,
                    AccountController $accountController,
                    NotificationService $notification
	) {
		$this->request = $request;
		$this->response = $response;
		$this->basketItemRepository = $basketItemRepository;
		$this->sessionStorage = $sessionStorage;
		$this->gatewayService = $gatewayService;
		$this->paymentHelper = $paymentHelper;
		$this->orderService = $orderService;
		$this->orderContract    = $orderContract;
		$this->paymentService = $paymentService;
        $this->authHelper = $authHelper;
        $this->settingsController = $settingsController;
        $this->basketHelper = $basketHelper;
        $this->apiResponse = $apiResponse;
        $this->accountController = $accountController;
        $this->notification = $notification;

		$this->payretoSettings = $paymentService->getPayretoSettings();
	}

	/**
	 * handle return_url from payment gateway
	 */
	public function handleReturn($checkoutId = 0)
	{
		#error_log
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);
		$this->getLogger(__METHOD__)->error('Payreto:return_url', $this->request->all());
		$registrationId = $this->request->get('registrationId');

		if ($registrationId) {
			$validation = $this->debitRecurringPaypal($registrationId);
		} else {
			$validation = $this->validation($checkoutId);
		}

		$this->getLogger(__METHOD__)->error('Payreto:validation', $validation);

		$basketItems = $this->basketItemRepository->all();

		#error_log
		$this->getLogger(__METHOD__)->error('Payreto:basketItems', $basketItems);

		if ($validation) {

            #Reset all basket.
            foreach ($basketItems as $basketItem)
            {
                $this->basketItemRepository->removeBasketItem($basketItem->id);
            }

            if($validation->order->id > 0)
            {
                return $this->response->redirectTo('confirmation/' . $validation->order->id);
            }
            else
            {
                return $this->response->redirectTo('confirmation');
            }
		} else {
			return $this->response->redirectTo('checkout');
        }
	}

	public function debitRecurringPaypal($registrationId) 
	{
		$basketHelper = pluginApp(basketHelper::class);
        $basket = $basketHelper->getBasket();
        $paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
        $paymentKey = $paymentMethod->paymentKey;
        $paymentType = $this->paymentService->getPaymentType($basket);

        $paymentData = $this->paymentService->getCredentials($paymentMethod->paymentKey);
        $paymentData['amount'] = $basket->basketAmount;
        $paymentData['currency'] = $basket->currency;
        $paymentData['payment_recurring'] = 'REPEATED';
        $paymentData['test_mode'] = $this->paymentService->getTestMode($paymentMethod->paymentKey);
        $paymentData['paymentType'] = 'DB';

        $debitResponse = $this->gatewayService->getRecurringPaymentResult($registrationId, $paymentData);
        $this->getLogger(__METHOD__)->error('Payreto:debitResponse', $debitResponse);

        $returnCode = $debitResponse['result']['code'];
        $transactionResult = $this->gatewayService->getTransactionResult($returnCode);

        if ($transactionResult == 'ACK') {
        	$paymentData['transaction_id'] = $debitResponse['id'];
            $paymentData['payment_type'] = $paymentKey;
            $paymentData['amount'] = $debitResponse['amount'];
            $paymentData['currency'] = $debitResponse['currency'];
            $paymentData['status'] = $this->paymentHelper->getPaymentStatus($paymentType);
            $orderData = $this->orderService->placeOrder($paymentType);
            $orderId = $orderData->order->id;
			
			$paymentData['orderId'] = $orderId;

			$this->paymentHelper->updatePlentyPayment($paymentData);
            return $debitResponse;
        } else {
            if ($transactionResult == 'NOK') {
                $returnMessage = $this->gatewayService->getErrorIdentifier($returnCode);
				$this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
            } else {
				$this->notification->error($this->gatewayService->getErrorMessage('ERROR_UNKNOWN'));
            }
        }
        return false;
	}

	/**
	 * show payment widget
	 */
	public function handlePayment(Twig $twig, $checkoutId)
	{
        $basket = $this->basketHelper->getBasket();
        $this->getLogger(__METHOD__)->error('Payreto:basket', $basket); 
        $paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
        $paymentSettings = $this->paymentService->getPaymentSettings($paymentMethod->paymentKey);
        $optionSetting = $this->settingsController->getOptionSetting($paymentMethod->paymentKey);
        $paymentWidgetUrl = $this->gatewayService->getPaymentWidgetUrl($paymentSettings['server'], $checkoutId);
		$paymentBrand = $paymentSettings['cardType'] ? str_replace(',', ' ', $paymentSettings['cardType']) : $optionSetting['paymentBrand'];
		$paymentPageUrl = $this->paymentHelper->getDomain() . '/payment/payreto/return/' . $checkoutId . '/';
		
		$this->getLogger(__METHOD__)->error('Payreto:paymentSettings', $paymentSettings);
        $this->getLogger(__METHOD__)->error('Payreto:paymentMethod', $paymentMethod); 

		$data = [
			'paymentBrand' => $paymentBrand,
			'checkoutId' => $checkoutId,
			'paymentPageUrl' => $paymentPageUrl,
			'paymentRegistered' => $this->paymentService->getRegisteredPayment($paymentMethod),
            'cancelUrl' => '/checkout',
            'paymentWidgetUrl' => $paymentWidgetUrl,
            'frameTestMode' => $paymentSettings['server']
		];

        $this->getLogger(__METHOD__)->error('Payreto:data', $data); 

		return $twig->render('Payreto::Payment.' . $optionSetting['paymentTemplate'] , $data);
	}

	/**
	 * handle validation payment
	 */
	public function validation($checkoutId)
	{
		$paymentData = [];
		$basketHelper = pluginApp(basketHelper::class);
        $basket = $basketHelper->getBasket();
        $paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
		$paymentKey = $paymentMethod->paymentKey;
		
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);

		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$parameters = $this->paymentService->getCredentials($paymentKey);

		if ($paymentKey == 'PAYRETO_AEC') {
			$parameters = array_merge($parameters, [
				'amount' => $basket->basketAmount,
				'currency' => $basket->currency,
				'payment_type' => 'CP',
				'test_mode' => $this->paymentService->getTestMode($paymentMethod->paymentKey)
			]);
			$this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters);
			return $this->capturePayment($checkoutId, $parameters);
		} else {
            $this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters);
            $paymentConfirmation = $this->gatewayService->getPaymentStatus($checkoutId, $parameters);

            if (!$paymentConfirmation['is_valid']) {
            	$returnMessage = $this->gatewayService->getErrorIdentifier($paymentConfirmation['response']['result']['code']);
				$this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
            } else {
            	$this->sessionStorage->getPlugin()->setValue('PayretoTransactionId', $paymentConfirmation['response']['id']);
            	$this->getLogger(__METHOD__)->error('Payreto:paymentConfirmation', $paymentConfirmation);

				$paymentResult = $this->gatewayService->getTransactionResult($paymentConfirmation['response']['result']['code']);

				if ( $paymentResult == 'ACK') 
				{

					if ($this->paymentHelper->isPaymentRecurring($paymentKey)) {
						$this->saveRecurringPayment($paymentConfirmation, $paymentKey);
						
						if ($paymentKey == 'PAYRETO_PPM_RC') 
						{
							$paymentConfirmation = $this->payAndSavePaypal($paymentMethod, $paymentConfirmation, $basket);
						}
					}
						
		            return $this->doSuccessPayment($paymentMethod, $paymentConfirmation);
				} elseif ($paymentResult == 'NOK') {
		        	$returnMessage = $this->gatewayService->getErrorIdentifier($paymentConfirmation['response']['result']['code']);
					$this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
				} else {
					$this->notification->error($this->gatewayService->getErrorMessage('ERROR_UNKNOWN'));
				}
            }
		}
	}

	private function doSuccessPayment($paymentMethod, $resultJson)
	{
		$paymentData['transaction_id'] = $resultJson['response']['id'];
        $paymentData['payment_type'] = $paymentMethod->paymentKey;
        $paymentData['amount'] = $resultJson['response']['amount'];
        $paymentData['currency'] = $resultJson['response']['currency'];
        $paymentType = $this->gatewayService->getPaymentTypeResponse($resultJson['response']);
    	$paymentData['status'] = $this->paymentHelper->getPaymentStatus($paymentType);
    	$orderData = $this->orderService->placeOrder($paymentType);
        $this->getLogger(__METHOD__)->error('Payreto:orderData', $orderData);
        $orderId = $orderData->order->id;
		
		$paymentData['orderId'] = $orderId;

		$this->paymentHelper->updatePlentyPayment($paymentData);
		return $orderData;
	}

	public function capturePayment($checkoutId, $parameters)
	{
		$paymentData = [];
		$basketHelper = pluginApp(basketHelper::class);
        $basket = $basketHelper->getBasket();
        $paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
		$paymentKey = $paymentMethod->paymentKey;
        $paymentType = $this->paymentService->getPaymentType($basket);
		$paymentConfirmation = $this->gatewayService->backOfficeOperation($checkoutId, $parameters);

		$this->sessionStorage->getPlugin()->setValue('PayretoTransactionId', $paymentConfirmation['response']['id']);
		if (!$paymentConfirmation['is_valid']) {
			$returnMessage = $this->gatewayService->getErrorIdentifier($paymentConfirmation['response']['result']['code']);
			$this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
			return $this->response->redirectTo('checkout');
		} else {
			$paymentResult = $this->gatewayService->getTransactionResult($paymentConfirmation['response']['result']['code']);
			if ( $paymentResult == 'ACK') 
			{
				return $this->doSuccessPayment($paymentMethod, $paymentConfirmation);

			} elseif ($paymentResult == 'NOK') {
				$returnMessage = $this->gatewayService->getErrorIdentifier($paymentConfirmation['response']['result']['code']);
				$this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
	        	return $this->response->redirectTo('checkout');
			} else {
				$this->notification->error($this->gatewayService->getErrorMessage('ERROR_UNKNOWN'));
	        	return $this->response->redirectTo('checkout');
			}
		}
	}

	public function saveRecurringPayment($paymentConfirmation, $paymentKey)
	{
		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$paymentConfirmation = array_merge($paymentConfirmation, [
			'paymentKey' => $paymentKey, 
			'entityId' => $paymentSettings['entityId'],
			'server' => $paymentSettings['server']
			]
		);
		$accountData = $this->paymentHelper->setAccountData($paymentConfirmation);
		$this->accountController->saveAccount($accountData);
	}

	public function payAndSavePaypal($paymentMethod, $paymentConfirmation, $basket)
	{
		$registrationId = $paymentConfirmation['id'];
        $paymentData = $this->paymentService->getCredentials($paymentMethod->paymentKey);
        $paymentData['amount'] = $basket->basketAmount;
        $paymentData['currency'] = $basket->currency;
        $paymentData['transaction_id'] = $paymentConfirmation['merchantTransactionId'];
        $paymentData['payment_recurring'] = 'INITIAL';
        $paymentData['test_mode'] = $this->paymentService->getTestMode($paymentMethod->paymentKey);
        $paymentData['paymentType'] = 'DB';

        $debitResponse = $this->gatewayService->getRecurringPaymentResult($registrationId, $paymentData);
        $this->getLogger(__METHOD__)->error('Payreto:debitResponse', $debitResponse);

        $returnCode = $debitResponse['result']['code'];
        $transactionResult = $this->gatewayService->getTransactionResult($returnCode);

        if ($transactionResult == 'ACK') {
            return $debitResponse;
        } else {
            if ($transactionResult == 'NOK') {
                $returnMessage = $this->gatewayService->getErrorIdentifier($returnCode);
				$this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
            } else {
				$this->notification->error($this->gatewayService->getErrorMessage('ERROR_UNKNOWN'));
            }
        }
        return false;
	}

	public function handleConfirmation(Twig $twig) 
	{
        $basketHelper = pluginApp(basketHelper::class);
        $basket = $basketHelper->getBasket();
		$paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
		$checkoutId = $this->request->get('id');
        $this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);

		$paymentData = $this->paymentService->getCredentials($paymentMethod->paymentKey);

		$paymentServerToServer = $this->gatewayService->getPaymentServerToServerStatus($checkoutId, $paymentData);
        $this->getLogger(__METHOD__)->error('Payreto:paymentServerToServer', $paymentServerToServer); 

        if (!$paymentServerToServer['is_valid']) {
        	$returnMessage = $this->gatewayService->getErrorIdentifier($paymentServerToServer['response']['result']['code']);
			$this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
        	return $this->response->redirectTo('checkout');
        } else {
        	$transactionResult = $this->gatewayService->getTransactionResult($paymentServerToServer['response']['result']['code']);

	        if ($transactionResult == 'ACK') {
	     		$paymentConfirmationData = $this->basketHelper->paymentConfirmationData();
		        $paymentConfirmationData = array_merge($paymentConfirmationData, [
		            'informationUrl' => $paymentServerToServer['response']['resultDetails']['vorvertraglicheInformationen'],
		            'tilgungsplan' => $paymentServerToServer['response']['resultDetails']['tilgungsplanText'],
		            'sumOfInterest' => $paymentServerToServer['response']['resultDetails']['ratenplan.zinsen.anfallendeZinsen'],
		            'orderTotal' => $paymentServerToServer['response']['resultDetails']['ratenplan.gesamtsumme'],
		            'checkoutId' => $paymentServerToServer['response']['id'],
		            'paymentMethodName' => $paymentMethod->name
		        ]);
		        $this->getLogger(__METHOD__)->error('Payreto:paymentConfirmationData', $paymentConfirmationData);

		        return $twig->render('Payreto::Payment.PaymentConfirmation' , $paymentConfirmationData);       
	        } else {
	            if ($transactionResult == 'NOK') {
	                $returnMessage = $this->getEasyCreditErrorMessage($paymentServerToServer['response']);
					$this->notification->error($returnMessage);
					return $this->response->redirectTo('checkout');
	            } else {
					$this->notification->error($this->gatewayService->getErrorMessage('ERROR_UNKNOWN'));
					return $this->response->redirectTo('checkout');
	            }
	        }
        }
	}

	/**
     * get error message from easycredit
     *
     * @param array $paymentResponse
     * @return string
     */
    public function getEasyCreditErrorMessage($paymentResponse)
    {
        if ($this->isRiskPayment($paymentResponse) && isset($paymentResponse['resultDetails']['Error'])) {
            $easyCreditErrorDetail = $this->getEasyCreditErrorDetail($paymentResponse);
            if (isset($easyCreditErrorDetail['field']) && $easyCreditErrorDetail['field'] !== 'null') {
                return $easyCreditErrorDetail['field'] . ': ' . $easyCreditErrorDetail['renderedMessage'];
            } else {
                return $easyCreditErrorDetail['renderedMessage'];
            }
        } elseif (isset($paymentResponse['resultDetails']['decisionNOK'])) {
            return $paymentResponse['resultDetails']['decisionNOK'];
        } else {
            return $this->gatewayService->getErrorIdentifier($paymentResponse['result']['code']);
        }
    }

    /**
     * validate payment risk score
     *
     * @param array $paymentResponse
     * @return boolean
     */
    protected function isRiskPayment($paymentResponse)
    {
        if (isset($paymentResponse['risk']['score'])) {
            if ((int)$paymentResponse['risk']['score'] < 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * get error details from easycredit
     *
     * @param array $paymentResponse
     * @return array $easyCreditErrorDetail
     */
    protected function getEasyCreditErrorDetail($paymentResponse)
    {
        $easyCreditErrorDetail = array();
        $errorResults = $this->explodeByMultiDelimiter(
            array("{", "}"),
            $paymentResponse['resultDetails']['Error']
        );
        $errorResults = explode(", ", $errorResults[1]);
        foreach ($errorResults as $errorResult) {
            $errorResultValue = explode("=", $errorResult);
            $easyCreditErrorDetail[$errorResultValue[0]] = trim($errorResultValue[1], "'");
        }

        return $easyCreditErrorDetail;
    }

    /**
     * explode string with multi delimiter
     *
     * @param array $delimiters
     * @param string $string
     * @return array $explodedString
     */
    protected function explodeByMultiDelimiter($delimiters, $string)
    {
        $string = str_replace($delimiters, $delimiters[0], $string);
        $explodedString = explode($delimiters[0], $string);
        return $explodedString;
    }

}
