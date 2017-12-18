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
	public function handleReturnRegister($checkoutId = 0)
	{
		#error_log
		$registrationId = $this->request->get('registrationId');

		if ($registrationId) {
			$validation = $this->debitRecurringPaypal($registrationId);
		} else {
			$validation = $this->validationRegister($checkoutId);
		}

		if ($validation) {
            return $this->response->redirectTo('my-payment-information');
		} else {
            return $this->apiResponse->error(ResponseCode::OK, 'test');
        }
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

	/**
	 * handle validation payment
	 */
	public function validationRegister($checkoutId)
	{
		$paymentData = [];
		$paymentKey = '';
		
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);

		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$parameters = $this->paymentService->getCredentials($paymentKey);

        $this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters);
        $paymentConfirmation = $this->gatewayService->paymentConfirmation($checkoutId, $parameters);

		if ($this->gatewayService->getTransactionResult($paymentConfirmation['result']['code']) == 'ACK') 
		{
			$paymentConfirmation = array_merge($paymentConfirmation, [
				'paymentKey' => $paymentKey, 
				'entityId' => $paymentSettings['entityId'],
				'server' => $paymentSettings['server']
				]
			);
			$accountData = $this->paymentHelper->setAccountData($paymentConfirmation);
			$this->accountController->saveAccount($accountData);

			if ($paymentKey == 'PAYRETO_PPM_RC') 
			{
				$paymentConfirmation = $this->payAndSavePaypal('', $paymentConfirmation, '');
			}

			$this->paymentHelper->updatePlentyPayment($paymentData);
			// return $orderData;
		} else {
			return false;
		}
	}

	public function captureRegister()
	{

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


	public function payAndSavePaypal($paymentMethod, $paymentConfirmation, $basket)
	{
		$registrationId = $paymentConfirmation['id'];
        $paymentData = $this->paymentService->getCredentials($paymentMethod);
        $paymentData['amount'] = $basket->basketAmount;
        $paymentData['currency'] = $basket->currency;
        $paymentData['transaction_id'] = $paymentConfirmation['merchantTransactionId'];
        $paymentData['payment_recurring'] = 'INITIAL';
        $paymentData['test_mode'] = $this->paymentService->getTestMode($paymentMethod);
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

	public function handleConfirmation(Twig $twig) 
	{
        $basketHelper = pluginApp(basketHelper::class);
        $basket = $basketHelper->getBasket();
		$paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
		$checkoutId = $this->request->get('id');
        $this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId); 
		$paymentSettings = $this->paymentService->getPaymentSettings($paymentMethod->paymentKey);

		$parameters = [
			'authentication.userId' => $this->payretoSettings['userId'],
			'authentication.password' => $this->payretoSettings['password'],
			'authentication.entityId' => $paymentSettings['entityId']
		];

		$paymentServerToServer = $this->gatewayService->paymentServerToServer($checkoutId, $parameters);
        $this->getLogger(__METHOD__)->error('Payreto:paymentServerToServer', $paymentServerToServer); 
        
        $paymentConfirmationData = $this->basketHelper->paymentConfirmationData();
        $paymentConfirmationData = array_merge($paymentConfirmationData, [
            'informationUrl' => $paymentServerToServer['resultDetails']['vorvertraglicheInformationen'],
            'tilgungsplan' => $paymentServerToServer['resultDetails']['tilgungsplanText'],
            'checkoutId' => $paymentServerToServer['id'],
            'paymentMethodName' => $paymentMethod->name
        ]);
        $this->getLogger(__METHOD__)->error('Payreto:paymentConfirmationData', $paymentConfirmationData);

        return $twig->render('Payreto::Payment.PaymentConfirmation' , $paymentConfirmationData);
	}

}
