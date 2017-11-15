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

	private $payretoSettings;

	/**
	 *
	 * @var ackReturnCodes
	 */
	protected $ackReturnCodes = array (
        '000.000.000',
        '000.100.110',
        '000.100.111',
        '000.100.112',
        '000.100.200',
        '000.100.201',
        '000.100.202',
        '000.100.203',
        '000.100.204',
        '000.100.205',
        '000.100.206',
        '000.100.207',
        '000.100.208',
        '000.100.209',
        '000.100.210',
        '000.100.220',
        '000.100.221',
        '000.100.222',
        '000.100.223',
        '000.100.224',
        '000.100.225',
        '000.100.226',
        '000.100.227',
        '000.100.228',
        '000.100.229',
        '000.100.230',
        '000.100.299',
        '000.200.000',
        '000.300.000',
        '000.300.100',
        '000.300.101',
        '000.300.102',
        '000.400.000',
        '000.400.010',
        '000.400.020',
        '000.400.030',
        '000.400.040',
        '000.400.050',
        '000.400.060',
        '000.400.070',
        '000.400.080',
        '000.400.090',
        '000.400.101',
        '000.400.102',
        '000.400.103',
        '000.400.104',
        '000.400.105',
        '000.400.106',
        '000.400.107',
        '000.400.108',
        '000.400.200',
        '000.500.000',
        '000.500.100',
        '000.600.000'
    );

	/**
	 *
	 * @var nokReturnCodes
	 */
    protected $nokReturnCodes = array (
        '100.100.100',
        '100.100.101',
        '100.100.200',
        '100.100.201',
        '100.100.300',
        '100.100.301',
        '100.100.303',
        '100.100.304',
        '100.100.400',
        '100.100.401',
        '100.100.402',
        '100.100.500',
        '100.100.501',
        '100.100.600',
        '100.100.601',
        '100.100.650',
        '100.100.651',
        '100.100.700',
        '100.100.701',
        '100.150.100',
        '100.150.101',
        '100.150.200',
        '100.150.201',
        '100.150.202',
        '100.150.203',
        '100.150.204',
        '100.150.205',
        '100.150.300',
        '100.200.100',
        '100.200.103',
        '100.200.104',
        '100.200.200',
        '100.210.101',
        '100.210.102',
        '100.211.101',
        '100.211.102',
        '100.211.103',
        '100.211.104',
        '100.211.105',
        '100.211.106',
        '100.212.101',
        '100.212.102',
        '100.212.103',
        '100.250.100',
        '100.250.105',
        '100.250.106',
        '100.250.107',
        '100.250.110',
        '100.250.111',
        '100.250.120',
        '100.250.121',
        '100.250.122',
        '100.250.123',
        '100.250.124',
        '100.250.125',
        '100.250.250',
        '100.300.101',
        '100.300.200',
        '100.300.300',
        '100.300.400',
        '100.300.401',
        '100.300.402',
        '100.300.501',
        '100.300.600',
        '100.300.601',
        '100.300.700',
        '100.300.701',
        '100.350.100',
        '100.350.101',
        '100.350.200',
        '100.350.201',
        '100.350.301',
        '100.350.302',
        '100.350.303',
        '100.350.310',
        '100.350.311',
        '100.350.312',
        '100.350.313',
        '100.350.314',
        '100.350.315',
        '100.350.400',
        '100.350.500',
        '100.350.600',
        '100.350.601',
        '100.350.610',
        '100.360.201',
        '100.360.300',
        '100.360.303',
        '100.360.400',
        '100.370.100',
        '100.370.101',
        '100.370.102',
        '100.370.110',
        '100.370.111',
        '100.370.121',
        '100.370.122',
        '100.370.123',
        '100.370.124',
        '100.370.125',
        '100.370.131',
        '100.370.132',
        '100.380.100',
        '100.380.101',
        '100.380.110',
        '100.380.201',
        '100.380.305',
        '100.380.306',
        '100.380.401',
        '100.380.501',
        '100.390.101',
        '100.390.102',
        '100.390.103',
        '100.390.104',
        '100.390.105',
        '100.390.106',
        '100.390.107',
        '100.390.108',
        '100.390.109',
        '100.390.110',
        '100.390.111',
        '100.390.112',
        '100.390.113',
        '100.395.101',
        '100.395.102',
        '100.395.501',
        '100.395.502',
        '100.396.101',
        '100.396.102',
        '100.396.103',
        '100.396.104',
        '100.396.106',
        '100.396.201',
        '100.397.101',
        '100.397.102',
        '100.400.000',
        '100.400.001',
        '100.400.002',
        '100.400.005',
        '100.400.007',
        '100.400.020',
        '100.400.021',
        '100.400.030',
        '100.400.039',
        '100.400.040',
        '100.400.041',
        '100.400.042',
        '100.400.043',
        '100.400.044',
        '100.400.045',
        '100.400.051',
        '100.400.060',
        '100.400.061',
        '100.400.063',
        '100.400.064',
        '100.400.065',
        '100.400.071',
        '100.400.080',
        '100.400.081',
        '100.400.083',
        '100.400.084',
        '100.400.085',
        '100.400.086',
        '100.400.087',
        '100.400.091',
        '100.400.100',
        '100.400.120',
        '100.400.121',
        '100.400.122',
        '100.400.123',
        '100.400.130',
        '100.400.139',
        '100.400.140',
        '100.400.141',
        '100.400.142',
        '100.400.143',
        '100.400.144',
        '100.400.145',
        '100.400.146',
        '100.400.147',
        '100.400.148',
        '100.400.149',
        '100.400.150',
        '100.400.151',
        '100.400.152',
        '100.400.241',
        '100.400.242',
        '100.400.243',
        '100.400.260',
        '100.400.300',
        '100.400.301',
        '100.400.302',
        '100.400.303',
        '100.400.304',
        '100.400.305',
        '100.400.306',
        '100.400.307',
        '100.400.308',
        '100.400.309',
        '100.400.310',
        '100.400.311',
        '100.400.312',
        '100.400.313',
        '100.400.314',
        '100.400.315',
        '100.400.316',
        '100.400.317',
        '100.400.318',
        '100.400.319',
        '100.400.320',
        '100.400.321',
        '100.400.322',
        '100.400.323',
        '100.400.324',
        '100.400.325',
        '100.400.326',
        '100.400.327',
        '100.400.328',
        '100.400.500',
        '100.500.101',
        '100.500.201',
        '100.500.301',
        '100.500.302',
        '100.550.300',
        '100.550.301',
        '100.550.303',
        '100.550.310',
        '100.550.311',
        '100.550.312',
        '100.550.400',
        '100.550.401',
        '100.550.601',
        '100.550.603',
        '100.550.605',
        '100.600.500',
        '100.700.100',
        '100.700.101',
        '100.700.200',
        '100.700.201',
        '100.700.300',
        '100.700.400',
        '100.700.500',
        '100.700.800',
        '100.700.801',
        '100.700.802',
        '100.700.810',
        '100.800.100',
        '100.800.101',
        '100.800.102',
        '100.800.200',
        '100.800.201',
        '100.800.202',
        '100.800.300',
        '100.800.301',
        '100.800.302',
        '100.800.400',
        '100.800.401',
        '100.800.500',
        '100.800.501',
        '100.900.100',
        '100.900.101',
        '100.900.105',
        '100.900.200',
        '100.900.300',
        '100.900.301',
        '100.900.400',
        '100.900.401',
        '100.900.450',
        '100.900.500',
        '200.100.101',
        '200.100.102',
        '200.100.103',
        '200.100.150',
        '200.100.151',
        '200.100.199',
        '200.100.201',
        '200.100.300',
        '200.100.301',
        '200.100.302',
        '200.100.401',
        '200.100.402',
        '200.100.403',
        '200.100.404',
        '200.100.501',
        '200.100.502',
        '200.100.503',
        '200.100.504',
        '200.200.106',
        '200.300.403',
        '200.300.404',
        '200.300.405',
        '200.300.406',
        '200.300.407',
        '500.100.201',
        '500.100.202',
        '500.100.203',
        '500.100.301',
        '500.100.302',
        '500.100.303',
        '500.100.304',
        '500.100.401',
        '500.100.402',
        '500.100.403',
        '500.200.101',
        '600.100.100',
        '600.200.100',
        '600.200.200',
        '600.200.201',
        '600.200.202',
        '600.200.300',
        '600.200.310',
        '600.200.400',
        '600.200.500',
        '600.200.600',
        '600.200.700',
        '600.200.800',
        '600.200.810',
        '700.100.100',
        '700.100.200',
        '700.100.300',
        '700.100.400',
        '700.100.500',
        '700.100.600',
        '700.100.700',
        '700.100.701',
        '700.100.710',
        '700.300.100',
        '700.300.200',
        '700.300.300',
        '700.300.400',
        '700.300.500',
        '700.300.600',
        '700.300.700',
        '700.400.000',
        '700.400.100',
        '700.400.101',
        '700.400.200',
        '700.400.300',
        '700.400.400',
        '700.400.402',
        '700.400.410',
        '700.400.420',
        '700.400.510',
        '700.400.520',
        '700.400.530',
        '700.400.540',
        '700.400.550',
        '700.400.560',
        '700.400.561',
        '700.400.562',
        '700.400.570',
        '700.400.700',
        '700.450.001',
        '800.100.100',
        '800.100.150',
        '800.100.151',
        '800.100.152',
        '800.100.153',
        '800.100.154',
        '800.100.155',
        '800.100.156',
        '800.100.157',
        '800.100.158',
        '800.100.159',
        '800.100.160',
        '800.100.161',
        '800.100.162',
        '800.100.163',
        '800.100.164',
        '800.100.165',
        '800.100.166',
        '800.100.167',
        '800.100.168',
        '800.100.169',
        '800.100.170',
        '800.100.171',
        '800.100.172',
        '800.100.173',
        '800.100.174',
        '800.100.175',
        '800.100.176',
        '800.100.177',
        '800.100.178',
        '800.100.179',
        '800.100.190',
        '800.100.191',
        '800.100.192',
        '800.100.195',
        '800.100.196',
        '800.100.197',
        '800.100.198',
        '800.100.402',
        '800.100.500',
        '800.100.501',
        '800.110.100',
        '800.120.100',
        '800.120.101',
        '800.120.102',
        '800.120.103',
        '800.120.200',
        '800.120.201',
        '800.120.202',
        '800.120.203',
        '800.120.300',
        '800.120.401',
        '800.121.100',
        '800.130.100',
        '800.140.100',
        '800.140.101',
        '800.140.110',
        '800.140.111',
        '800.140.112',
        '800.140.113',
        '800.150.100',
        '800.160.100',
        '800.160.110',
        '800.160.120',
        '800.160.130',
        '800.200.159',
        '800.200.160',
        '800.200.165',
        '800.200.202',
        '800.200.208',
        '800.200.220',
        '800.300.101',
        '800.300.102',
        '800.300.200',
        '800.300.301',
        '800.300.302',
        '800.300.401',
        '800.300.500',
        '800.300.501',
        '800.400.100',
        '800.400.101',
        '800.400.102',
        '800.400.103',
        '800.400.104',
        '800.400.105',
        '800.400.110',
        '800.400.150',
        '800.400.151',
        '800.400.200',
        '800.400.500',
        '800.500.100',
        '800.500.110',
        '800.600.100',
        '800.700.100',
        '800.700.101',
        '800.700.201',
        '800.700.500',
        '800.800.102',
        '800.800.202',
        '800.800.302',
        '800.800.800',
        '800.800.801',
        '800.900.100',
        '800.900.101',
        '800.900.200',
        '800.900.201',
        '800.900.300',
        '800.900.301',
        '800.900.302',
        '800.900.303',
        '800.900.401',
        '800.900.450',
        '900.100.100',
        '900.100.200',
        '900.100.201',
        '900.100.202',
        '900.100.203',
        '900.100.300',
        '900.100.400',
        '900.100.500',
        '900.100.600',
        '900.200.100',
        '900.300.600',
        '900.400.100',
        '999.999.999'
    );

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

		$orderData = $this->orderService->placeOrder();
		$orderId = $orderData->order->id;

		$validation = $this->validation($checkoutId, $orderData);

		#error_log
		$this->getLogger(__METHOD__)->error('Payreto:orderData', $orderData);

		$basketItems = $this->basketItemRepository->all();

		#error_log
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
	public function validation($checkoutId, $orderData)
	{
		$paymentData = [];
		$orderId = $orderData->order->id;
		$paymentMethod = $this->paymentHelper->getPaymentMethodById($orderData->order->properties[0]->value);
		$paymentKey = $paymentMethod->paymentKey;
		
		$this->getLogger(__METHOD__)->error('Payreto:Value', $orderData->order->properties[0]->value);
		$this->getLogger(__METHOD__)->error('Payreto:checkoutId', $checkoutId);

		$ccSettings = $this->paymentService->getPaymentSettings($paymentKey);

		$this->getLogger(__METHOD__)->error('Payreto:orderId', $orderId);

		$parameters = [
			'authentication.userId' => $this->payretoSettings['userId'],
			'authentication.password' => $this->payretoSettings['password'],
			'authentication.entityId' => $ccSettings['entityId']
		];

		if ($paymentKey == 'PAYRETO_ECP') {
			$parameters = array_merge($parameters, [
				'amount' => $orderData->order->amounts[0]->invoiceTotal,
				'currency' => $orderData->order->amounts[0]->currency,
				'paymentType' => 'CP',
				'testMode' => 'EXTERNAL'
			]);
			$this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters);
			$paymentConfirmation = $this->gatewayService->backOfficePayment($checkoutId, $parameters);
		} else {
			$paymentConfirmation = $this->gatewayService->paymentConfirmation($checkoutId, $parameters);
		}

		
		$this->sessionStorage->getPlugin()->setValue('PayretoTransactionId', $paymentConfirmation['id']);

		$this->getLogger(__METHOD__)->error('Payreto:paymentConfirmation', $paymentConfirmation);

		if (in_array($paymentConfirmation['result']['code'], $this->ackReturnCodes)) {
			$paymentData['transaction_id'] = $paymentConfirmation['id'];
			$paymentData['paymentKey'] = $paymentKey;
			$paymentData['amount'] = $paymentConfirmation['amount'];
			$paymentData['currency'] = $paymentConfirmation['currency'];
			$paymentData['status'] = 2;
			$paymentData['orderId'] = $orderId;
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

	public function getBasketOrderItems($basket)
	{
		$itemContract = pluginApp(\Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract::class);

		$basketOrderItems = [];
		foreach ($basket->basketItems as $basketItem) {
			$item = $itemContract->show($basketItem->itemId);
			$itemText = $item->texts;

			$basketOrderItems[] = [
				'quantity' => $basketItem->quantity,
				'orderItemName' => $itemText->first()->name1,
				'amounts' => 
				[
					[
						'priceOriginalGross' => $basketItem->price,
						'priceGross' => $basketItem->price
					]
				]
			];
		}

		return $basketOrderItems;
	}

	public function getItemImages($basket)
	{
		$imageRepository = pluginApp(\Plenty\Modules\Item\ItemImage\Contracts\ItemImageRepositoryContract::class);
		$itemImages = [];
		foreach ($basket->basketItems as $basketItem) {
            $authHelper = pluginApp(AuthHelper::class);
            $variationId = $basketItem->itemId;
            $itemImage = $authHelper->processUnguarded(
                function () use ($imageRepository, $variationId) {
                    return $imageRepository->findByItemId($variationId);
                }
            );
			$itemImages[$basketItem->variationId] = $itemImage[0];
		}
        $this->getLogger(__METHOD__)->error('Payreto:itemImages', $itemImages);

		return $itemImages;
	}

	public function handleConfirmation(Twig $twig) 
	{
		$orderContract = $this->orderContract;
		$basket = $this->getBasket();
		$paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
		$basketItems = $this->basketItemRepository->all();
		$checkoutId = $this->request->get('id');
		$ccSettings = $this->paymentService->getPaymentSettings($paymentMethod->paymentKey);

		$parameters = [
			'authentication.userId' => $this->payretoSettings['userId'],
			'authentication.password' => $this->payretoSettings['password'],
			'authentication.entityId' => $ccSettings['entityId']
		];

		$paymentServerToServer = $this->gatewayService->paymentServerToServer($checkoutId, $parameters);

        $data = [
        	'data' => [
        		'order' => [
        			'billingAddress' => $this->paymentService->getBillingAddress($basket),
        			'deliveryAddress' => $this->paymentService->getShippingAddress($basket),
        			'amounts' => 
        			[
        				[
        					'netTotal' => $basket->basketAmountNet,
							'grossTotal' => $basket->basketAmount,
							'vatTotal' => '',
							'invoiceTotal' => $basket->basketAmount
        				]
        			],
        			'orderItems' => $this->getBasketOrderItems($basket)
        		],
        		'paymentMethodName' => $paymentMethod->name
        	],
        	'itemImages' => $this->getItemImages($basket),
        	'informationUrl' => $paymentServerToServer['resultDetails']['vorvertraglicheInformationen'],
        	'tilgungsplan' => $paymentServerToServer['resultDetails']['tilgungsplanText'],
        	'checkoutId' => $paymentServerToServer['id']
        ];

		$this->getLogger(__METHOD__)->error('Payreto:data', $data);
		$this->getLogger(__METHOD__)->error('Payreto:orders', $order);
		$this->getLogger(__METHOD__)->error('Payreto:paymentMethod', $paymentMethod);
		$this->getLogger(__METHOD__)->error('Payreto:paymentServerToServer', $paymentServerToServer);

		return $twig->render('Payreto::Payment.PaymentConfirmation', $data);
	}

}
