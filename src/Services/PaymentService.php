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
use Payreto\Helper\BasketHelper;
use Payreto\Services\Database\SettingsService;
use Payreto\Services\GatewayService;
use Payreto\Controllers\SettingsController;
use Payreto\Controllers\AccountController;
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
     * @var notification
     */
    private $notification;

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
     *
     * @var basketHelper
     */
    private $basketHelper;

    /**
     *
     * @var accountController
     */
    private $accountController;

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
		SettingsController $settingsController,
		AccountController $accountController
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
		$this->accountController = $accountController;
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
	public function getPaymentSettings($paymentKey)
	{
		$this->loadCurrentSettings($paymentKey);
		return $this->settings;
	}

	public function getRecurringSetting()
	{
		$this->loadCurrentSettings();
		return $this->settings['recurring'];
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
			$this->getCredentials($paymentMethod->paymentKey),
			$this->getTransactionParameters($basket, $paymentMethod),
			$this->getCustomerParameters()
		);

		// $this->paymentHelper->mapStatus();

		if ($paymentMethod->paymentKey == 'PAYRETO_ECP')
		{
			$parameters = array_merge($parameters, $this->getServerToServerParameters($basket, $paymentMethod));
			$this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters); 

			$paymentResponse = $this->gatewayService->getServerToServer($parameters);
			$this->getLogger(__METHOD__)->error('Payreto:paymentResponse', $paymentResponse);

			if ((float)$basket->basketAmount < 200 || (float)$basket->basketAmount > 3000) {
				return [
					'type' => GetPaymentMethodContent::RETURN_TYPE_ERROR,
					'content' => 'The financing amount is outside the permitted amounts (200 - 3,000 EUR)'
				];
			}

			if ($this->gatewayService->getTransactionResult($paymentResponse['result']['code']) == 'ACK' ) {
				$paymentPageUrl = $paymentResponse['redirect']['url'];
			} else {
				$returnMessage = $this->gatewayService::getErrorIdentifier($paymentResponse['result']['code']);
				return [
					'type' => GetPaymentMethodContent::RETURN_TYPE_ERROR,
					'content' => $this->gatewayService->getErrorMessage('ERROR_GENERAL_REDIRECT')
				];
			}
			
		} else {

			$this->getLogger(__METHOD__)->error('Payreto:parameters', $parameters); 
			$paymentWidgetContent = $this->gatewayService->getCheckoutResponse($parameters);	
			$this->getLogger(__METHOD__)->error('Payreto:checkoutResponse', $paymentWidgetContent);

			if ($this->gatewayService->getTransactionResult($paymentWidgetContent['result']['code']) == 'ACK') {
				$paymentPageUrl = $this->paymentHelper->getDomain().'/payment/payreto/pay/' . $paymentWidgetContent['id'];
			} else {
				$returnMessage = $this->gatewayService::getErrorIdentifier($paymentWidgetContent['result']['code']);
				return [
					'type' => GetPaymentMethodContent::RETURN_TYPE_ERROR,
					'content' => $this->gatewayService->getErrorMessage('ERROR_GENERAL_REDIRECT')
				];	
			}
			
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
	public function getCredentials($paymentKey) {
		$payretoSettings = $this->getPayretoSettings();
		$paymentSettings = $this->getPaymentSettings($paymentKey);
		$credentials = [
						'login' 		=> $payretoSettings['userId'],
						'password' 		=> $payretoSettings['password'],
						'channel_id' 	=> $paymentSettings['entityId']
					];

		return $credentials;
	}

	/**
	 * Get the testMode Parameters
	 *
	 * @param class PaymentMethod
	 * @return array|null
	 */
	public function getTestMode($paymentKey) 
	{

		if ($this->getServerMode($paymentKey) == "LIVE") {
            return false;
        }

        
        $this->getLogger(__METHOD__)->error('Payreto:paymentKey', $paymentKey);
        if ($paymentKey == 'PAYRETO_GRP') {
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
	public function getServerMode($paymentKey) 
	{
		$paymentSettings = $this->getPaymentSettings($paymentKey);
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
			$paymentParameters =array_merge( 
					$this->getChartParameters($basket),
					[
						'paymentBrand' => $this->getPaymentBrand($basket),
						'shopperResultUrl' => $this->paymentHelper->getDomain() . '/payment/payreto/confirmation/',
						'customParameters' => [
												'RISK_ANZAHLBESTELLUNGEN' => $this->paymentHelper->getOrderCount((int)$this->paymentHelper->getCustomerId()),
												'RISK_KUNDENSTATUS' => $this->getRiskKundenStatus(),
												'RISK_KUNDESEIT' => '2016-01-01',
												'RISK_BESTELLUNGERFOLGTUEBERLOGIN' => $this->checkCustomerLoginStatus()
											]
					]
				);
		}

		return $paymentParameters;
	}

	/**
     * Get risk kunden status
     *
     * @return string|boolean
     */
    public function checkCustomerLoginStatus()
    {
    	$customerId = $this->paymentHelper->getCustomerId();
    	$this->getLogger(__METHOD__)->error('Payreto:customerId', $customerId);
    	if ($customerId) {
			return 'true';
		} else {
			return 'false';
		}
    }

	/**
     * Get risk kunden status
     *
     * @return string|boolean
     */
    protected function getRiskKundenStatus()
    {
    	if ($this->paymentHelper->getOrderCount((int)$this->paymentHelper->getCustomerId()) > 0) {
            return 'BESTANDSKUNDE';
        }
        return 'NEUKUNDE';
    }

	public function getCustomerParameters() 
	{
		$shippings = pluginApp(basketHelper::class)->getShippingAddress();
		$billings = pluginApp(basketHelper::class)->getBillingAddress();
		$customer = pluginApp(basketHelper::class)->getCustomer(); 
		$this->getLogger(__METHOD__)->error('Payreto:customer', $customer);

		$customerParameters = [
			'customer' => 
							[
								'email' => $customer->email,
								'phone' => $customer->privatePhone,
								'last_name' => $customer->lastName,
								'birthdate' => date('Y-m-d', strtotime($customer->birthdayAt)),
								'first_name' => $customer->firstName
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
		foreach ($basket->basketItems as $key => $item) {
			$itemName = $this->paymentHelper->getVariationDescription($item->variationId); 
			$chartParameters['cartItems'][$key]['name'] = $itemName[0]->name;
			$chartParameters['cartItems'][$key]['type'] = 'basic';
			$chartParameters['cartItems'][$key]['price'] = (int)$item->price;
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
			'test_mode' => $this->getTestMode($paymentMethod->paymentKey)
		];
		$transactionParameters['payment_type'] = $this->getPaymentType($basket);

		if ($paymentMethod->paymentKey == 'PAYRETO_PPM_RC') {
			unset($transactionParameters['payment_type']);
		}

		if ($this->getRecurringSetting()) {
			$recurringParameter = $this->getRecurringPrameter($paymentMethod, $transactionParameters);
			$transactionParameters = array_merge($transactionParameters, $recurringParameter);
		}

		return $transactionParameters;
	}


	private function getRecurringPrameter($paymentMethod, $transaction)
    {
    	$paymentSettings = $this->getPaymentSettings($paymentMethod->paymentKey);
        $recurringParameter = $this->getPaymentReference($paymentMethod);

        if ($paymentSettings['recurring']) {
        	$transactionData['payment_registration'] = 'true';
        }

        if ($paymentMethod->paymentKey == 'PAYRETO_ACC_RC') {
            $recurringParameter['3D']['amount'] = $transaction['amount'];
            $recurringParameter['3D']['currency'] = $transaction['currency'];
        }

        return $recurringParameter;
    }

    public function getRecurringPaymentParameters($paymentKey)
    {
    	$payretoSettings = $this->getPayretoSettings();
    	$paymentSettings = $this->getPaymentSettings($paymentKey);

        $transactionData = array_merge(
            $this->getCredentials($paymentKey),
            $this->getCustomerParameters()
        );

        $transactionData['amount'] = $this->getRegisterAmount($paymentKey);
        $transactionData['currency'] = 'EUR';
        $transactionData['3D']['amount'] = $this->get3dAmount($paymentKey);
        $transactionData['3D']['currency'] = $this->get3dCurrency($paymentKey, 'EUR');
        $transactionData['test_mode'] = $this->getTestMode($paymentKey);
        if ($paymentKey <> 'PAYRETO_PPM_RC') {
        	$transactionData['payment_type'] = $this->getPaymentType(false, $paymentKey);
        }
        $transactionData['payment_recurring'] = 'INITIAL';

        if ($paymentSettings['recurring']) {
        	$transactionData['payment_registration'] = 'true';
        }
        $transactionData['transaction_id'] = $this->getTransactionIdbyReference();

        return $transactionData;
    }

    protected function getRegisterAmount($paymentKey)
    {
    	$paymentSettings = $this->getPaymentSettings($paymentKey);

    	return $paymentSettings['amount'];
    }

    protected function get3dAmount($paymentKey)
    {
        if ($paymentKey == 'PAYRETO_ACC_RC') {
            return $this->getRegisterAmount($paymentKey);
        }
    }

    protected function get3dCurrency($paymentKey, $currency)
    {
        if ($paymentKey == 'PAYRETO_ACC_RC') {
            return $currency;
        }
    }

    protected function getTransactionIdbyReference()
    {
        return (int)$this->paymentHelper->getCustomerId();
    }

    public function isRedirectPayment($paymentKey)
    {
        if ($paymentKey == 'PAYRETO_PPM_RC') {
            return  true;
        }

        return false;
    }


    public function getPaymentReference($paymentMethod)
    {
        $registeredPayments = $this->getRegisteredPayment($paymentMethod); 

        $paymentReference = array();
        $i = 0;
        foreach ($registeredPayments as $value) {
            $paymentReference['registrations'][$i ] = $value->refId;
            $i++;
        }

        return $paymentReference;
    }


    public function getRegisteredPayment($paymentMethod)
    {
        $registeredPayments = $this->accountController->loadAccount($this->paymentHelper->getCustomerId(), $paymentMethod->paymentKey);
        return $registeredPayments;
    }

	/**
	 * get payment type
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	public function getPaymentType($basket = false, $paymentKey = false)
	{
		if ($basket) {
			$paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
			$paymentKey = $paymentMethod->paymentKey;
		}
		
        $paymentSettings = $this->getPaymentSettings($paymentKey);
        $optionSetting = $this->settingsController->getOptionSetting($paymentKey);
        return !empty($paymentSettings['transactionMode']) ? $paymentSettings['transactionMode'] : $optionSetting['paymentType'];
	}

	/**
	 * get payment brand
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	public function getPaymentBrand(Basket $basket)
	{
		$paymentMethod = $this->paymentHelper->getPaymentMethodById($basket->methodOfPaymentId);
        $optionSetting = $this->settingsController->getOptionSetting($paymentMethod->paymentKey);
        return $optionSetting['paymentBrand'];
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
			$transactionId = $payment->properties[0]->value;
			$transactionData = array_merge(
				$this->getCredentials($payment->method),
				[
					'amount' => $payment->amount,
					'currency' => $payment->currency,
					'payment_type' => 'RF',
					'test_mode' => $this->getTestMode($payment->method->paymentKey)
				]
			);

			$this->getLogger(__METHOD__)->error('Payreto:refund', $payment->properties[0]->value);

			$refundResult = $this->gatewayService->backOfficePayment($transactionId, $transactionData);

			$resultRefund = $this->gatewayService->getTransactionResult($refundResult);

			if ($resultRefund == 'ACK') 
			{
				// $this->notification->success('Refunded');
			} elseif ($resultRefund == 'NOK') {
				$returnMessage = $this->gatewayService->getErrorIdentifier($resultRefund);
				// $this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
			} else {
				// $this->notification->error('ERROR_UNKNOWN');
			}

			$this->getLogger(__METHOD__)->error('Payreto:refundResult', $refundResult);

		}
		catch (\Exception $e)
		{
			// $this->notification->error($e->getMessage());
		}
	}

	

}

?>