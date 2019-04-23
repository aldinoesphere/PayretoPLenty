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
	public function handleReturnRegister($checkoutId = 0, $paymentKey = '', $recurringId = 0)
	{
		$transactionData = $this->paymentService->getCredentials($paymentKey);
		$resultJson = $this->gatewayService->getPaymentStatus($checkoutId, $transactionData);
		$this->getLogger(__METHOD__)->error('Payreto:StartHandleRegister', 'Start Handle Register');
		$this->getLogger(__METHOD__)->error('Payreto:resultJson', $resultJson);

		if ($this->gatewayService->getTransactionResult($resultJson['response']['result']['code']) == 'ACK') 
		{
			$this->getLogger(__METHOD__)->error('Payreto:transactionResult', 'ACK');
			$validation = $this->validationRegister($recurringId, $paymentKey, $resultJson);

		} elseif ($this->gatewayService->getTransactionResult($resultJson['response']['result']['code']) == 'NOK') {
			return false;
		} else {
			return false;
		}

		$this->notification->success('Payment account has been saved');
        return $this->response->redirectTo('my-payment-information');
	}

	/**
	 * handle validation payment
	 */
	public function validationRegister($recurringId, $paymentKey, $resultJson)
	{
		
		$this->getLogger(__METHOD__)->error('Payreto:StartValidationRegister', 'Start Validation Register');
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);

		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$transactionData = $this->getRegisterParameter($paymentKey);
		if ($recurringId) {
            $transactionData['transaction_id'] = $resultJson['response']['merchantTransactionId'];
            $transactionData['server_mode'] = 'TEST';
        }

		if ($paymentKey == 'PAYRETO_PPM_RC') 
		{
			$this->getLogger(__METHOD__)->error('Payreto:doPaypalRegister', 'do Paypal Registration');
			$paypalResult = $this->doPaypalRegister($recurringId, $paymentKey, $transactionData, $resultJson);
			$referenceId = $paypalResult['id'];
		} elseif ($paymentSettings['transactionMode'] == 'PA') {
			$this->getLogger(__METHOD__)->error('Payreto:captureRegister', 'capture Registration');
			$captureResult = $this->captureRegister($recurringId, $paymentKey, $transactionData, $resultJson);
			$referenceId = $captureResult['id'];
		} else {
			$this->saveAccount($recurringId, $resultJson, $paymentKey);
		}

		$this->getLogger(__METHOD__)->error('Payreto:transactionData', $transactionData);

		$this->refundPayment($referenceId, $transactionData);

		if ($recurringId) {
			$this->gatewayService->deleteRegistration($recurringId, $transactionData);
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

	public function saveAccount($recurringId, $resultJson, $paymentKey)
	{
		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$resultJson = array_merge($resultJson['response'], [
			'paymentKey' => $paymentKey, 
			'entityId' => $paymentSettings['entityId'],
			'server' => $paymentSettings['server']
			]
		);
		$accountData = $this->paymentHelper->setAccountData($resultJson);
		if ($recurringId) {
			$this->accountController->updateAccount($recurringId, $accountData);
		} else {
			$this->accountController->saveAccount($accountData);	
		}
	}

	public function captureRegister($recurringId, $paymentKey, $transactionData, $resultJson)
	{
		$referenceId = $resultJson['response']['id'];
        $registrationId = $resultJson['response']['registrationId'];

        $transactionData['payment_type'] = "CP";
        $captureResult = $this->gatewayService->backOfficeOperation($referenceId, $transactionData);
        $this->getLogger(__METHOD__)->error('Payreto:captureResult', $captureResult);

        if ($this->gatewayService->getTransactionResult($captureResult['result']['code']) == 'ACK') {
			$this->saveAccount($recurringId, $resultJson, $paymentKey);
		} elseif ($this->gatewayService->getTransactionResult($captureResult['result']['code']) == 'NOK') {
			
		}
		return $captureResult;
	}

	public function doPaypalRegister($recurringId, $paymentKey, $transactionData, $resultJson)
	{
		$registrationId = $resultJson['response']['id'];

        $transactionData['paymentType'] = 'DB';

        $paypalResult = $this->gatewayService->getRecurringPaymentResult($registrationId, $transactionData);
        $this->getLogger(__METHOD__)->error('Payreto:paypalResult', $paypalResult);

        $returnCode = $paypalResult['response']['result']['code'];
        $resultPaypal = $this->gatewayService->getTransactionResult($returnCode);
        $this->getLogger(__METHOD__)->error('Payreto:resultPaypal', $resultPaypal);

        if ($resultPaypal == 'ACK') {
            $this->saveAccount($recurringId, $resultJson, $paymentKey);
        } else {
            if ($resultPaypal == 'NOK') {
                $returnMessage = $this->gatewayService->getErrorIdentifier($returnCode);
            } else {
                $returnMessage = 'ERROR_UNKNOWN';
            }
        }
        return $paypalResult;
	}

	private function refundPayment($referenceId, $transactionData)
    {
        $transactionData['payment_type'] = "RF";
        $this->getLogger(__METHOD__)->error('Payreto:transactionData', $transactionData);
        $resultJson = $this->gatewayService->backOfficeOperation($referenceId, $transactionData);
        $this->getLogger(__METHOD__)->error('Payreto:resultJson', $resultJson);
    }

    public function postProcess()
    {
        $id = $this->request->get('id');
        $paymentKey = $this->request->get('paymentKey');
        $referenceId = $this->request->get('referenceId');
        $customerId = (int)$this->paymentHelper->getCustomerId();

        $transactionData = $this->getDeleteParameter($paymentKey, $customerId);
        $this->getLogger(__METHOD__)->error('Payreto:transactionData', $transactionData);
        $response = $this->gatewayService->deleteRegistration($referenceId, $transactionData);
        
        $returnCode = $response['result']['code'];
        $transactionResult = $this->gatewayService->getTransactionResult($returnCode);
        $this->getLogger(__METHOD__)->error('Payreto:transactionResult', $transactionResult);
        if ($transactionResult == "ACK") {
        	$this->accountController->deleteAccount($id);
            $this->notification->success('Successfully Your account has been deleted.');
        } else {
			$this->notification->error($this->gatewayService->getErrorMessage('ERROR_MC_DELETE'));
        }

        return $this->response->redirectTo('my-payment-information');
    }

    private function getDeleteParameter($paymentKey, $customerId)
    {
        $transactionData = $this->paymentService->getCredentials($paymentKey);
        $transactionData['transaction_id'] = $customerId;
        $transactionData['test_mode'] = $this->paymentService->getTestMode($paymentKey);
        $transactionData['server_mode'] = 'TEST';

        return $transactionData;
    }

}
