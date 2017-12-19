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
use IO\Api\ResponseCode;

use Payreto\Services\GatewayService;
use Payreto\Helper\PaymentHelper;
use Payreto\Helper\BasketHelper;
use Payreto\Services\PaymentService;
use Payreto\Services\OrderService;
use Payreto\Controllers\SettingsController;
use Payreto\Controllers\AccountController;
/**
* Class PaymentResultController
* @package Payreto\Controllers
*/
class PaymentResultController extends Controller
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
                    AccountController $accountController
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

		$this->payretoSettings = $paymentService->getPayretoSettings();
	}

	/**
	 * handle return_url from payment gateway
	 */
	public function handleReturnRegister($checkoutId = 0, $paymentKey = '')
	{
		$transactionData = $this->paymentService->getCredentials($paymentKey);
		$resultJson = $this->gatewayService->paymentConfirmation($checkoutId, $transactionData);

		if ($this->gatewayService->getTransactionResult($resultJson['result']['code']) == 'ACK') 
		{
			$validation = $this->validationRegister($paymentKey, $resultJson);

		} elseif ($this->gatewayService->getTransactionResult($resultJson['result']['code']) == 'NOK') {
			return false;
		} else {
			return false;
		}

		if ($validation) {
            return $this->response->redirectTo('/my-payment-information?status=success');
		} else {
            return $this->response->redirectTo('/my-payment-information?status=failed');
        }
	}

	/**
	 * handle validation payment
	 */
	public function validationRegister($paymentKey, $resultJson)
	{
		
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);

		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$transactionData = $this->getRegisterParameter($paymentKey);

		if ($this->gatewayService->getTransactionResult($resultJson['result']['code']) == 'ACK') 
		{
			if ($paymentKey == 'PAYRETO_PPM_RC') 
			{
				$resultJson = $this->payAndSavePaypalRegister($paymentKey, $resultJson, '');
			} elseif ($paymentSettings['transactionMode'] == 'PA') {
				$this->captureRegister($paymentKey, $transactionData, $resultJson);
			} else {
				$this->saveAccount($resultJson, $paymentKey);
			}

			$this->refundPayment($resultJson['id'], $transactionData);

		} elseif ($this->gatewayService->getTransactionResult($resultJson['result']['code']) == 'NOK') {
			return false;
		} else {
			return false;
		}
	}

	private function getRegisterParameter($paymentKey)
    {

        $currency = 'EUR';
        $paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

        $transactionData = $this->paymentService->getCredentials($paymentKey);
        $transactionData['amount'] = $paymentSettings['amount'];
        $transactionData['currency'] = $currency;
        $transactionData['transaction_id'] = (int)$this->paymentHelper->getCustomerId();
        $transactionData['payment_recurring'] = 'INITIAL';
        $transactionData['test_mode'] = $this->paymentService->getTestMode($paymentKey);

        return $transactionData;
    }

	public function saveAccount($resultJson, $paymentKey)
	{
		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$resultJson = array_merge($resultJson, [
			'paymentKey' => $paymentKey, 
			'entityId' => $paymentSettings['entityId'],
			'server' => $paymentSettings['server']
			]
		);
		$accountData = $this->paymentHelper->setAccountData($resultJson);
		$this->accountController->saveAccount($accountData);
	}

	public function captureRegister($paymentKey, $transactionData, $resultJson)
	{
		$referenceId = $resultJson['id'];
        $registrationId = $resultJson['registrationId'];

        $transactionData['payment_type'] = "CP";
        $paymentResult = $this->gatewayService->backOfficePayment($referenceId, $transactionData);
        $this->getLogger(__METHOD__)->error('Payreto:paymentResult', $paymentResult);

        if ($this->gatewayService->getTransactionResult($paymentResult['result']['code']) == 'ACK') {
			$this->saveAccount($resultJson, $paymentKey);
		} elseif ($this->gatewayService->getTransactionResult($paymentResult['result']['code']) == 'NOK') {
			
		}
	}

	public function getPaymentStatus($paymentType) 
	{
		switch ($paymentType) {
			case 'PA':
				return $this->paymentHelper->mapTransactionState('0');
				break;
			
			default:
				return $this->paymentHelper->mapTransactionState('2');
				break;
		}
	}


	public function payAndSavePaypalRegister($paymentKey, $resultJson, $basket)
	{
		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);
		$registrationId = $resultJson['id'];
        $paymentData = $this->paymentService->getCredentials($paymentKey);
        $paymentData['amount'] = $basket->basketAmount;
        $paymentData['currency'] = $basket->currency;
        $paymentData['transaction_id'] = $resultJson['merchantTransactionId'];
        $paymentData['payment_recurring'] = 'INITIAL';
        $paymentData['test_mode'] = $this->paymentService->getTestMode($paymentKey);
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
            } else {
                $returnMessage = 'ERROR_UNKNOWN';
            }
        }
        return false;
	}

	public function debitRecurringPaypal($registrationId) 
	{
		$basketHelper = pluginApp(basketHelper::class);
        $basket = $basketHelper->getBasket();
        $paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
        $paymentKey = $paymentMethod->paymentKey;
        $paymentType = $this->paymentService->getPaymentType($basket);

        $paymentData = $this->paymentService->getCredentials($paymentMethod);
        $paymentData['amount'] = $basket->basketAmount;
        $paymentData['currency'] = $basket->currency;
        $paymentData['payment_recurring'] = 'REPEATED';
        $paymentData['test_mode'] = $this->paymentService->getTestMode($paymentMethod);
        $paymentData['paymentType'] = 'DB';

        $debitResponse = $this->gatewayService->getRecurringPaymentResult($registrationId, $paymentData);
        $this->getLogger(__METHOD__)->error('Payreto:debitResponse', $debitResponse);

        $returnCode = $debitResponse['result']['code'];
        $transactionResult = $this->gatewayService->getTransactionResult($returnCode);

        if ($transactionResult == 'ACK') {
        	$paymentData['transaction_id'] = $debitResponse['id'];
            $paymentData['paymentKey'] = $paymentKey;
            $paymentData['amount'] = $debitResponse['amount'];
            $paymentData['currency'] = $debitResponse['currency'];
            $paymentData['status'] = $this->getPaymentStatus($paymentType);
            $orderData = $this->orderService->placeOrder($paymentType);
            $orderId = $orderData->order->id;
			
			$paymentData['orderId'] = $orderId;

			$this->paymentHelper->updatePlentyPayment($paymentData);
            return $debitResponse;
        } else {
            if ($transactionResult == 'NOK') {
                $returnMessage = $this->gatewayService->getErrorIdentifier($returnCode);
            } else {
                $returnMessage = 'ERROR_UNKNOWN';
            }
        }
        return false;
	}

	private function refundPayment($transactionData, $referenceId)
    {
        $transactionData['payment_type'] = "RF";
        $this->getLogger(__METHOD__)->error('Payreto:transactionData', $transactionData);
        $resultJson = $this->gatewayService->backOfficePayment($referenceId, $transactionData);
        $this->getLogger(__METHOD__)->error('Payreto:resultJson', $resultJson);
    }

}
