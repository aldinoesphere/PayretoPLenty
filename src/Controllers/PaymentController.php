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
                    ApiResponse $apiResponse
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

		$this->payretoSettings = $paymentService->getPayretoSettings();
	}

	/**
	 * handle return_url from payment gateway
	 */
	public function handleReturn($checkoutId)
	{
		#error_log
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);
		$this->getLogger(__METHOD__)->error('Payreto:return_url', $this->request->all());

		// $orderData = $this->orderService->placeOrder();
		// $orderId = $orderData->order->id;

		$validation = $this->validation($checkoutId);

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
            return $this->apiResponse->info(ResponseCode::OK, 'test');
            // return $this->response->redirectTo('checkout');
        }
	}

	/**
	 * show payment widget
	 */
	public function handlePayment(Twig $twig, $checkoutId)
	{
		$paymentPageUrl = $this->paymentHelper->getDomain() . '/payment/payreto/return/' . $checkoutId . '/';
        $basket = $this->basketHelper->getBasket();
        $this->getLogger(__METHOD__)->error('Payreto:basket', $basket); 
        $paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
        $paymentSettings = $this->paymentService->getPaymentSettings($paymentMethod->paymentKey);
        $optionSetting = $this->settingsController->getOptionSetting($paymentMethod->paymentKey);
        $paymentWidgetUrl = $this->gatewayService->getPaymentWidgetUrl($paymentSettings['server'], $checkoutId);
		$paymentBrand = $paymentSettings['cardType'] ? str_replace(',', ' ', $paymentSettings['cardType']) : $optionSetting['paymentBrand'];
		$this->getLogger(__METHOD__)->error('Payreto:paymentSettings', $paymentSettings);
        $this->getLogger(__METHOD__)->error('Payreto:paymentMethod', $paymentMethod); 

		$data = [
			'paymentBrand' => $paymentBrand,
			'checkoutId' => $checkoutId,
			'paymentPageUrl' => $paymentPageUrl,
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
        $paymentType = $this->paymentService->getPaymentType($basket);
		
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);

		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$parameters = [
			'login' => $this->payretoSettings['userId'],
			'password' => $this->payretoSettings['password'],
			'channel_id' => $paymentSettings['entityId']
		];

		if ($paymentKey == 'PAYRETO_ECP') {
			$parameters = array_merge($parameters, [
				'amount' => $basket->basketAmount,
				'currency' => $basket->currency,
				'payment_type' => 'CP',
				'test_mode' => $this->paymentService->getTestMode($paymentMethod)
			]);
			$this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters);
			$paymentConfirmation = $this->gatewayService->backOfficePayment($checkoutId, $parameters);
		} else {
            $this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters);
            $paymentConfirmation = $this->gatewayService->paymentConfirmation($checkoutId, $parameters);
		}

		
		$this->sessionStorage->getPlugin()->setValue('PayretoTransactionId', $paymentConfirmation['id']);

		$this->getLogger(__METHOD__)->error('Payreto:paymentConfirmation', $paymentConfirmation);

		if ($this->gatewayService->getTransactionResult($paymentConfirmation['result']['code']) == 'ACK') {

            $paymentData['transaction_id'] = $paymentConfirmation['id'];
            $paymentData['paymentKey'] = $paymentKey;
            $paymentData['amount'] = $paymentConfirmation['amount'];
            $paymentData['currency'] = $paymentConfirmation['currency'];
 
            if ($paymentType == 'PA') {
                $paymentData['status'] = $this->paymentHelper->mapTransactionState('0');
            } else {
                $paymentData['status'] = $this->paymentHelper->mapTransactionState('2');
            }

            $orderData = $this->orderService->placeOrder($paymentType);
            $orderId = $orderData->order->id;
			
			$paymentData['orderId'] = $orderId;

			$this->paymentHelper->updatePlentyPayment($paymentData);
			return $orderData;
		} else {
			return false;
		}
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
