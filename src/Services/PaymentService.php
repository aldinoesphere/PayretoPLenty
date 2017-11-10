<?php  

namespace Payreto\Services;

use Plenty\Modules\Basket\Models\Basket;
use Plenty\Modules\Item\Item\Contracts\ItemRepositoryContract;
use Plenty\Modules\Basket\Models\BasketItem;
use Plenty\Modules\Account\Address\Models\Address;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Modules\Payment\Events\Checkout\GetPaymentMethodContent;
use Plenty\Modules\Payment\Method\Models\PaymentMethod;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Frontend\Services\SystemService;
use Plenty\Plugin\Log\Loggable;

use Payreto\Services\OrderService;
use Payreto\Helper\PaymentHelper;
use Payreto\Services\Database\SettingsService;
use Payreto\Services\GatewayService;
/**
* 
*/
class PaymentService
{
	use Loggable;

	/**
	 *
	 * @var ItemRepositoryContract
	 */
	private $itemRepository;

	/**
	 *
	 * @var FrontendSessionStorageFactoryContract
	 */
	private $session;

	/**
	 *
	 * @var AddressRepositoryContract
	 */
	private $addressRepository;

	/**
	 *
	 * @var CountryRepositoryContract
	 */
	private $countryRepository;

	/**
	 *
	 * @var PaymentHelper
	 */
	private $paymentHelper;

	/**
	 *
	 * @var systemService
	 */
	private $systemService;

	/**
	 *
	 * @var settingsService
	 */
	private $settingsService;

	/**
	 *
	 * @var gatewayService
	 */
	private $gatewayService;

	/**
	 *
	 * @var orderService
	 */
	private $orderService;

	/**
	 *
	 * @var orderRepository
	 */
	private $orderRepository;

	/**
	 * @var array
	 */
	public $settings = [];

	function __construct(
		ItemRepositoryContract $itemRepository,
		FrontendSessionStorageFactoryContract $session,
		AddressRepositoryContract $addressRepository,
		CountryRepositoryContract $countryRepository,
		PaymentHelper $paymentHelper,
		SystemService $systemService,
		SettingsService $settingsService,
		GatewayService $gatewayService,
		OrderService $orderService,
		OrderRepositoryContract $orderRepository
	){
		$this->itemRepository = $itemRepository;
		$this->session = $session;
		$this->addressRepository = $addressRepository;
		$this->countryRepository = $countryRepository;
		$this->paymentHelper = $paymentHelper;
		$this->systemService = $systemService;
		$this->settingsService = $settingsService;
		$this->gatewayService = $gatewayService;
		$this->orderService = $orderService;
		$this->orderRepository = $orderRepository;
	}

	/**
	 * Load the settings from the database for the given settings type
	 *
	 * @param $settingsType
	 * @return array|null
	 */
	public function loadCurrentSettings($settingsType = 'general-setting')
	{
		$setting = $this->settingsService->loadSetting($this->systemService->getPlentyId(), $settingsType);
		if (is_array($setting) && count($setting) > 0)
		{
			$this->settings = $setting;
		}
	}

	/**
	 * get the settings from the database for the given settings type is payreto_general
	 *
	 * @return array|null
	 */
	public function getPayretoSettings()
	{
		$this->loadCurrentSettings();
		return $this->settings;
	}

	/**
	 * get the settings from the database for the given settings type is payreto_general
	 *
	 * @return array|null
	 */
	public function getPaymentSettings($settingType)
	{
		$this->loadCurrentSettings($settingType);
		return $this->settings;
	}

	/**
	 * this function will execute after we are doing a payment and show payment success or not.
	 *
	 * @param int $orderId
	 * @return array
	 */
	public function executePayment($orderId)
	{
		$transactionId = $this->session->getPlugin()->getValue('PayretoTransactionId');
		
		$this->getLogger(__METHOD__)->error('Payreto:executePayment', $transactionId);

		$this->session->getPlugin()->setValue('PayretoTransactionId', null);

		return $this->paymentHelper->getOrderPaymentStatus($transactionId);
	}

	/**
	 * Get the PayPal payment content
	 *
	 * @param Basket $basket
	 * @return string
	 */
	public function getPaymentContent(Basket $basket, PaymentMethod $paymentMethod)
	{
		
		$this->getLogger(__METHOD__)->error('Payreto:paymentMethod', $paymentMethod->paymentKey);
		
		$parameters = array_merge(
			$this->getCredentials(),
			$this->getTransactionParameters($basket),
			$this->getCcParameters($paymentMethod),
			$this->getServerToServerParameters($basket, $paymentMethod)
		);

		$this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters);
		$this->getLogger(__METHOD__)->error('Payreto:Items', $this->itemRepository);

		try
		{
			if ($paymentMethod->paymentKey != 'PAYRETO_ECP') {
				$checkoutId = $this->gatewayService->getCheckoutId($parameters);
				$paymentPageUrl = $this->paymentHelper->getDomain().'/payment/payreto/pay/' . $checkoutId;
			} else {
				$paymentResponse = $this->gatewayService->getServerToServer($parameters);
				$this->getLogger(__METHOD__)->error('Payreto:paymentResponse', $paymentResponse);
				$paymentPageUrl = $paymentResponse;
			}
		}
		catch (\Exception $e)
		{
			$this->getLogger(__METHOD__)->error('Payreto:getCheckoutId', $e);
			return [
				'type' => GetPaymentMethodContent::RETURN_TYPE_ERROR,
				'content' => 'An error occurred while processing your transaction. Please contact our support.'
			];
		}

		return [
			'type' => GetPaymentMethodContent::RETURN_TYPE_REDIRECT_URL,
			'content' => $paymentPageUrl
		];
	}

	/**
	 * Get the Credential payment
	 *
	 * @return array
	 */
	public function getCredentials() {
		$payretoSettings = $this->getPayretoSettings();
		$credentials = [
			'authentication.userId' => $payretoSettings['userId'],
			'authentication.password' => $payretoSettings['password']
		];

		return $credentials;
	}

	/**
	 * Get the Credit Card Parameters payment
	 *
	 * @param class PaymentMethod
	 * @return array|null
	 */
	public function getCcParameters(PaymentMethod $paymentMethod) 
	{

		$ccParameters = '';

		if ($paymentMethod->paymentKey != 'PAYRETO_ACC') {
			$ccSettings = $this->getPaymentSettings($paymentMethod->paymentKey);
			$ccParameters = [
				'authentication.entityId' => $ccSettings['entityId'],
				'paymentType' => $ccSettings['transactionMode']
			];
		}

		return $ccParameters;
	}

	/**
	 * Get the Server To Server Parameters paymen
	 *
	 * @param Basket $basket
	 * @param PaymentMethod $paymentMethod
	 * @return array|null
	 */
	public function getServerToServerParameters(Basket $basket, PaymentMethod $paymentMethod) 
	{

		$ccParameters = '';

		if ($paymentMethod->paymentKey == 'PAYRETO_ECP') {
			$ccSettings = $this->getPaymentSettings($paymentMethod->paymentKey);
			$ccParameters =array_merge( 
					[
						'authentication.entityId' => $ccSettings['entityId'],
						'paymentType' => 'PA',
						'paymentBrand' => 'RATENKAUF',
						'shopperResultUrl' => $this->paymentHelper->getDomain() . 'checkout',
						'customParameters[RISK_ANZAHLBESTELLUNGEN]' =>0,
						'customParameters[RISK_ANZAHLPRODUKTEIMWARENKORB]' =>1,
						'customParameters[RISK_KUNDENSTATUS]' => 'NEUKUNDE',
						'customParameters[RISK_KUNDESEIT]' => '2016-01-01',
						'customParameters[RISK_NEGATIVEZAHLUNGSINFORMATION]' => 'KEINE_ZAHLUNGSSTOERUNGEN',
						'customParameters[RISK_RISIKOARTIKELIMWARENKORB]' => false,
						'testMode' => 'EXTERNAL'
					],
					$this->getCustomerParameters(),
					$this->getBillingParameters($basket),
					$this->getShippingParameters($basket),
					$this->getChartParameters()
				);
		}

		return $ccParameters;
	}

	public function getCustomerParameters() 
	{
		$customerParameters = [
			'customer.email' => 'aldino.said@esphere.id',
			'customer.sex' => 'F',
			'customer.phone' => '+4915111111111',
			'customer.surname' => 'Jones',
			'customer.birthDate' => '1980-01-01',
			'customer.givenName' => 'Jane'
		];

		return $customerParameters;
	}

	public function getShippingParameters($basket) 
	{
		$shippings = $this->getShippingAddress($basket);
		$shippingParameters = [
			'shipping.city' => $shippings->town,
			'shipping.country' => 'DE',
			'shipping.street1' => $shippings->address1,
			'shipping.postcode' => $shippings->postalCode
		];

		return $shippingParameters;
	}

	public function getChartParameters() 
	{
		$chartParameters = [
			'cart.items[0].name' => 'Product 1',
			'cart.items[0].type' => 'basic',
			'cart.items[0].price' => 19.00,
			'cart.items[0].currency' => 'EUR',
			'cart.items[0].quantity' => 1,
			'cart.items[0].merchantItemId' => 1
		];

		return $chartParameters;
	}

	public function getBillingParameters($basket) 
	{
		$billings = $this->getBillingAddress($basket);
		$billingParameters = [
			'billing.city' => $billings->town,
			'billing.country' => 'DE',
			'billing.postcode' => $billings->postalCode
		];

		return $billingParameters;
	}

	/**
	 * Get the Transaction Parameters payment
	 *
	 * @param class Basket
	 * @return array
	 */
	public function getTransactionParameters(Basket $basket)
	{
		$transactionParameters = [];
		$transactionParameters = [
			'amount' => $basket->basketAmount,
			'currency' => $basket->currency
		];

		return $transactionParameters;
	}

	/**
	 * get billing address
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	private function getBillingAddress(Basket $basket)
	{
		$addressId = $basket->customerInvoiceAddressId;
		return $this->addressRepository->findAddressById($addressId);
	}

	/**
	 * get billing country code
	 *
	 * @param int $customerInvoiceAddressId
	 * @return string
	 */
	public function getBillingCountryCode($customerInvoiceAddressId)
	{
		$billingAddress = $this->addressRepository->findAddressById($customerInvoiceAddressId);
		return $this->countryRepository->findIsoCode($billingAddress->countryId, 'iso_code_3');
	}

	/**
	 * get shipping address
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	private function getShippingAddress(Basket $basket)
	{
		$addressId = $basket->customerShippingAddressId;
		if ($addressId != null && $addressId != - 99)
		{
			return $this->addressRepository->findAddressById($addressId);
		}
		else
		{
			return $this->getBillingAddress($basket);
		}
	}

	/**
	 * get address by given parameter
	 *
	 * @param Address $address
	 * @return array
	 */
	private function getAddress(Address $address)
	{
		return [
			'email' => $address->email,
			'firstName' => $address->firstName,
			'lastName' => $address->lastName,
			'address' => $address->street . ' ' . $address->houseNumber,
			'postalCode' => $address->postalCode,
			'city' => $address->town,
			'country' => $this->countryRepository->findIsoCode($address->countryId, 'iso_code_3'),
			'birthday' => $address->birthday,
			'companyName' => $address->companyName,
			'phone' => $address->phone
		];
	}

	/**
	 * get basket items
	 *
	 * @param Basket $basket
	 * @return array
	 */
	private function getBasketItems(Basket $basket)
	{
		$items = [];
		/** @var BasketItem $basketItem */
		foreach ($basket->basketItems as $basketItem)
		{
			$item = $basketItem->getAttributes();
			$item['name'] = $this->getBasketItemName($basketItem);
			$items[] = $item;
		}
		$this->getLogger(__METHOD__)->error('Payreto:getBasketItems', $items);

		return $items;
	}

	/**
	 * get basket item name
	 *
	 * @param BasketItem $basketItem
	 * @return string
	 */
	private function getBasketItemName(BasketItem $basketItem)
	{
		$this->getLogger(__METHOD__)->error('Payreto::item name', $basketItem);
		/** @var \Plenty\Modules\Item\Item\Models\Item $item */
		$item = $this->itemRepository->show($basketItem->itemId);

		/** @var \Plenty\Modules\Item\Item\Models\ItemText $itemText */
		$itemText = $item->texts;

		$this->getLogger(__METHOD__)->error('Payreto:getBasketItemName', $itemText);

		return $itemText->first()->name1;
	}

	/**
	 * Returns a random number with length as parameter given.
	 *
	 * @param int $length
	 * @return string
	 */
	private function getRandomNumber($length)
	{
		$result = '';

		for ($i = 0; $i < $length; $i++)
		{
			$result .= rand(0, 9);
		}

		return $result;
	}

	/**
	 * send refund to the gateway with transaction_id and returns error or success.
	 *
	 * @param string $transactionId
	 * @param Payment $payment
	 */
	public function refund($transactionId, Payment $payment)
	{
		try
		{
			$payretoSettings = $this->getPayretoSettings();
			$transactionId = $payment->properties[0]->value;
			$ccSettings = $this->getPaymentSettings('credit-card');
			$parameters = [
				'authentication.userId' => $payretoSettings['userId'],
				'authentication.password' => $payretoSettings['password'],
				'authentication.entityId' => $ccSettings['entityId'],
				'amount' => $payment->amount,
				'currency' => $payment->currency,
				'paymentType' => 'RF'
			];

			$this->getLogger(__METHOD__)->error('Payreto:refund', $payment->properties[0]->value);

			$response = $this->gatewayService->doRefund($transactionId, $parameters);

			$this->getLogger(__METHOD__)->error('Payreto:response', $response);

		}
		catch (\Exception $e)
		{
			$this->getLogger(__METHOD__)->error('Payreto:refundFailed', $e);

			return [
				'error' => true,
				'errorMessage' => $e->getMessage()
			];
		}

		return [
			'success' => true,
			'response' => $response
		];
	}

	

}

?>