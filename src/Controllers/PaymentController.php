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

use Payreto\Services\GatewayService;
use Payreto\Helper\PaymentHelper;
use Payreto\Services\PaymentService;
use Payreto\Services\OrderService;
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
					PaymentService $paymentService
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
	}

	/**
	 * handle return_url from payment gateway
	 */
	public function handleReturn($checkoutId)
	{
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);
		$this->getLogger(__METHOD__)->error('Payreto:return_url', $this->request->all());
		$orderData = $this->orderService->placeOrder();

		$orderId = $orderData->order->id;

		$validation = $this->handleValidation($checkoutId, $orderData);

		$this->getLogger(__METHOD__)->error('Payreto:orderData', $orderData);

		$basketItems = $this->basketItemRepository->all();

		$this->getLogger(__METHOD__)->error('Payreto:basketItems', $basketItems);

		#Reset all basket.
		foreach ($basketItems as $basketItem)
		{
			$this->basketItemRepository->removeBasketItem($basketItem->id);
		}

		if ($validation) {
			return $this->response->redirectTo('execute-payment/'.$orderId);
		}
	}

	/**
	 * show payment widget
	 */
	public function handlePayment(Twig $twig, $checkoutId)
	{
		$paymentPageUrl = $this->paymentHelper->getDomain() . '/payment/payreto/return/' . $checkoutId . '/';
		$this->getLogger(__METHOD__)->error('Payreto:paymentPageUrl', $paymentPageUrl);

		$ccSettings = $this->paymentService->getPaymentSettings('credit-card');
		$cardType = str_replace(',', ' ', $ccSettings['cardType']);
		
		$data = [
			'cardType' => $cardType,
			'checkoutId' => $checkoutId,
			'paymentPageUrl' => $paymentPageUrl
		];

		return $twig->render('Payreto::Payment.PaymentWidget', $data);
	}

	/**
	 * handle validation payment
	 */
	public function handleValidation($checkoutId, $orderData)
	{
		$paymentData = [];
		$orderId = $orderData->order->id;

		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);
		
		$payretoSettings = $this->paymentService->getPayretoSettings();
		$ccSettings = $this->paymentService->getPaymentSettings('credit-card');

		$this->getLogger(__METHOD__)->error('Payreto:orderId', $orderId);

		$parameters = [
			'authentication.userId' => $payretoSettings['userId'],
			'authentication.password' => $payretoSettings['password'],
			'authentication.entityId' => $ccSettings['entityId']
		];

		$paymentConfirmation = $this->gatewayService->paymentConfirmation($checkoutId, $parameters);
		$paymentData['transaction_id'] = $paymentConfirmation['id'];
		$paymentData['paymentKey'] = 'PAYRETO_ACC';
		$paymentData['amount'] = $paymentConfirmation['amount'];
		$paymentData['currency'] = $paymentConfirmation['currency'];
		$paymentData['status'] = 2;
		$paymentData['orderId'] = $orderId;
		$this->sessionStorage->getPlugin()->setValue('PayretoTransactionId', $paymentConfirmation['id']);

		$this->getLogger(__METHOD__)->error('Payreto:paymentConfirmation', $paymentConfirmation);

		if ($paymentConfirmation['result']['code'] == '000.100.110') {
			$this->paymentHelper->updatePlentyPayment($paymentData);
			return true;
		}
		else 
		{
			return false;
		}
	}

	public function getBasket()
	{
		$Basket = pluginApp(AfterBasketChanged::class);
		return $Basket->getBasket();
	}

	public function handleConfirmation(Twig $twig, $id) 
	{
		$orderContract = $this->orderContract;
		$paymentMethod = $this->paymentHelper->getPaymentMethodById($this->getBasket()->methodOfPaymentId);
		// $shippingProviders = $this->paymentHelper->getShippingServiceProviderById($this->getBasket()->shippingProviderId);

        $data = [
        	'data' => [
        		'order' => [
        			'billingAddress' => $this->paymentService->getBillingAddress($this->getBasket()),
        			'deliveryAddress' => $this->paymentService->getShippingAddress($this->getBasket())
        		],
        		'paymentMethodName' => $paymentMethod->name
        	],
        	'checkoutId' => $this->request->get('id')
        ];

		$this->getLogger(__METHOD__)->error('Payreto:data', $this->request->get('id'));
		$this->getLogger(__METHOD__)->error('Payreto:orders', $order);
		$this->getLogger(__METHOD__)->error('Payreto:paymentMethod', $paymentMethod);
		$this->getLogger(__METHOD__)->error('Payreto:shippingProviders', $shippingProviders);

		return $twig->render('Payreto::Payment.PaymentConfirmation', $data);
	}

}
