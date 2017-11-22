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
use Plenty\Modules\Authentication\Events\AfterAccountAuthentication;

use Payreto\Services\OrderService;
use Payreto\Helper\PaymentHelper;
use Payreto\Services\Database\SettingsService;
use Payreto\Services\GatewayService;
use Payreto\Controllers\SettingsController;
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
     *
     * @var settingsController
     */
    private $settingsController;

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
		OrderRepositoryContract $orderRepository,
		SettingsController $settingsController
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
		 $this->settingsController = $settingsController;
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
		$parameters = array_merge(
			$this->getCredentials($paymentMethod),
			$this->getTransactionParameters($basket, $paymentMethod),
			$this->getCustomerParameters($basket)
		);

		$this->getLogger(__METHOD__)->error('Payreto:basket', $basket);
		$this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters);
		$this->getLogger(__METHOD__)->error('Payreto:getCredentials', $this->getCredentials($paymentMethod)); 

		try
		{
			if ($paymentMethod->paymentKey == 'PAYRETO_ECP')
			{
				$parameters = array_merge($parameters, $this->getServerToServerParameters($basket, $paymentMethod));
				$paymentResponse = $this->gatewayService->getServerToServer($parameters);
				$this->getLogger(__METHOD__)->error('Payreto:paymentResponse', $paymentResponse);
				$paymentPageUrl = $paymentResponse['redirect']['url'];
			} else {
				$checkoutResponse = $this->gatewayService->getCheckoutResponse($parameters);
				$this->getLogger(__METHOD__)->error('Payreto:checkoutResponse', $checkoutResponse);
				$paymentPageUrl = $this->paymentHelper->getDomain().'/payment/payreto/pay/' . $checkoutResponse['id'];
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
	public function getCredentials(PaymentMethod $paymentMethod) {
		$payretoSettings = $this->getPayretoSettings();
		$paymentSettings = $this->getPaymentSettings($paymentMethod->paymentKey);
		$credentials = [
						'login' => $payretoSettings['userId'],
						'password' => $payretoSettings['password'],
						'channel_id' => $paymentSettings['entityId']
					];

		return $credentials;
	}

	/**
	 * Get the testMode Parameters
	 *
	 * @param class PaymentMethod
	 * @return array|null
	 */
	public function getTestMode(PaymentMethod $paymentMethod) 
	{

		if ($this->getServerMode($paymentMethod) == "LIVE") {
            return false;
        }
        $this->getLogger(__METHOD__)->error('Payreto:paymentMethod', $paymentMethod);
        if ($paymentMethod->paymentKey == 'PAYRETO_GRP') {
            return 'INTERNAL';
        } else {
            return "EXTERNAL";
        }
	}


	/**
	 * Get the testMode Parameters
	 *
	 * @param class PaymentMethod
	 * @return array|null
	 */
	public function getServerMode(PaymentMethod $paymentMethod) 
	{

		$paymentSettings = $this->getPaymentSettings($paymentMethod->paymentKey);
		return $paymentSettings['server'];
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

		$paymentParameters = [];

		if ($paymentMethod->paymentKey == 'PAYRETO_ECP') {
			$paymentSettings = $this->getPaymentSettings($paymentMethod->paymentKey);
			$paymentParameters =array_merge( 
					[
						'shopperResultUrl' => $this->paymentHelper->getDomain() . '/payment/payreto/confirmation/',
						'customParameters' => [
												'RISK_ANZAHLBESTELLUNGEN' => $this->paymentHelper->getOrderCount(114),
												'RISK_KUNDENSTATUS' => $this->getRiskKundenStatus(),
												'RISK_KUNDESEIT' => '2016-01-01',
												'RISK_BESTELLUNGERFOLGTUEBERLOGIN' => 'true'
											]
					],
					$this->getChartParameters($basket)
				);
		}

		return $paymentParameters;
	}

	/**
     * Get risk kunden status
     *
     * @return string|boolean
     */
    protected function getLoginStatus()
    {
    	
    }

	/**
     * Get risk kunden status
     *
     * @return string|boolean
     */
    protected function getRiskKundenStatus()
    {
    	if ($this->paymentHelper->getOrderCount(114) > 0) {
            return 'BESTANDSKUNDE';
        }
        return 'NEUKUNDE';
    }

	public function getCustomerParameters($basket) 
	{
		$shippings = $this->getShippingAddress($basket);
		$billings = $this->getBillingAddress($basket);
		$customerParameters = [
			'customer' => 
							[
								'email' => 'aldino.said@esphere.id',
								'sex' => 'F',
								'phone' => '+4915111111111',
								'last_name' => 'Jones',
								'birthDate' => '1980-01-01',
								'first_name' => 'Jane'
							],
			'shipping' => 
							[
								'city' => $shippings->town,
								'country' => 'DE',
								'street1' => $shippings->address1,
								'postcode' => $shippings->postalCode
							],
			'billing' =>
							[
								'city' => $billings->town,
								'country_code' => 'DE',
								'street' => $billings->address1,
								'zip' => $billings->postalCode
							]
		];

		return $customerParameters;
	}

	public function getChartParameters($basket) 
	{
		$chartParameters = [];
		$items = $this->itemRepository->show($basket->basketItems);
		$this->getLogger(__METHOD__)->error('Payreto:items', $items);
		foreach ($items as $key => $item) {
			$itemName = $this->paymentHelper->getVariationDescription($item->variationId); 
			$chartParameters['cartItems'][$key]['name'] = $itemName[0]->name;
			$chartParameters['cartItems'][$key]['type'] = 'basic';
			$chartParameters['cartItems'][$key]['price'] = $item->price;
			$chartParameters['cartItems'][$key]['currency'] = $basket->currency;
			$chartParameters['cartItems'][$key]['quantity'] = $item->quantity;
			$chartParameters['cartItems'][$key]['merchantItemId'] = $item->itemId;	
		} 

		return $chartParameters;
	}

	/**
	 * Get the Transaction Parameters payment
	 *
	 * @param class Basket
	 * @return array
	 */
	public function getTransactionParameters(Basket $basket, PaymentMethod $paymentMethod)
	{
		$transactionParameters = [];
		$transactionParameters = [
			'transaction_id' => $basket->id,
			'amount' => $basket->basketAmount,
			'currency' => $basket->currency,
			'payment_type' => $this->getPaymentType($basket),
			'test_mode' => $this->getTestMode($paymentMethod)
		];

		return $transactionParameters;
	}

	/**
	 * get payment type
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	public function getPaymentType(Basket $basket)
	{
		$paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
        $paymentSettings = $this->getPaymentSettings($paymentMethod->paymentKey);
        $optionSetting = $this->settingsController->getOptionSetting($paymentMethod->paymentKey);
        return !empty($paymentSettings['transactionMode']) ? $paymentSettings['transactionMode'] : $optionSetting['transactionMode'];
	}

	/**
	 * get billing address
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	public function getBillingAddress(Basket $basket)
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
	public function getShippingAddress(Basket $basket)
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