<?php
namespace Payreto\Services;

use Plenty\Plugin\Log\Loggable;

/**
* Class GatewayService
* @package Payreto\Services
*/
class GatewayService
{
	use Loggable;

	/**
	 * @var string
	 */
	protected $oppwaCheckoutUrlTest = 'https://test.oppwa.com/v1/checkouts';
	protected $oppwaPaymentUrlTest = 'https://test.oppwa.com/v1/payments';

	protected $oppwaCheckoutUrl = 'https://oppwa.com/v1/checkouts';
	protected $oppwaPaymentUrl = 'https://oppwa.com/v1/payments';

	protected $paymentWidgetUrlLive = 'https://oppwa.com/v1/paymentWidgets.js?checkoutId=';
    protected $paymentWidgetUrlTest = 'https://test.oppwa.com/v1/paymentWidgets.js?checkoutId=';

	/**
	 * Get gateway response
	 *
	 * @param string $url
	 * @param array $parameters
	 * @throws \Exception
	 * @return string
	 */
	private function getGatewayResponse($url, $checkoutParameters)
	{

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $checkoutParameters);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// this should be set to true in production
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responseData = curl_exec($ch);
		if(curl_errno($ch)) {
			return curl_error($ch);
		}
		curl_close($ch);
		return $responseData;
	}

	/**
	 * gateway payment confirmation
	 *
	 * @param string $confirmationUrl
	 * @throws \Exception
	 * @return string
	 */
	private function getGatewayPaymentConfirmation($confirmationUrl)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $confirmationUrl);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);// this should be set to true in production
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responseData = curl_exec($ch);
		if(curl_errno($ch)) {
			return curl_error($ch);
		}
		curl_close($ch);
		return $responseData;
	}

	/**
	 * Get payment widget server mode
	 *
	 * @param $serverWidget
	 * @param $checkoutId
	 * @return string
	 */
	public function getPaymentWidgetUrl($serverMode, $checkoutId) {
		if ($serverMode == 'LIVE') {
			return $this->paymentWidgetUrlLive . $checkoutId;
		} else {
			return $this->paymentWidgetUrlTest . $checkoutId;
		}
	}

	/**
	 * Get Sid from gateway to use at payment page url
	 *
	 * @param array $transactionData
	 * @throws \Exception
	 * @return string
	 */
	public function getCheckoutResponse($transactionData)
	{
		$checkoutUrl = $this->oppwaCheckoutUrlTest;
		$checkoutParameters = $this->getCheckoutParameters($transactionData);
		$response = $this->getGatewayResponse($checkoutUrl, $checkoutParameters);

		if (!$response)
		{
			throw new \Exception('Sid is not valid : ' . $response);
		}

		$response = json_decode($response, true);
		$this->getLogger(__METHOD__)->error('Payreto:responseId', $responseId);
		$this->getLogger(__METHOD__)->error('Payreto:checkoutUrl', $checkoutUrl);
		return $response;
	}

	/**
	 * Get Sid from gateway to use at payment page url
	 *
	 * @param array $transactionData
	 * @throws \Exception
	 * @return string
	 */
	public function getServerToServer($transactionData)
	{
		$checkoutUrl = $this->oppwaPaymentUrlTest;
		$checkoutParameters = $this->getCheckoutParameters($transactionData);
		$response = $this->getGatewayResponse($checkoutUrl, $checkoutParameters);

		if (!$response)
		{
			throw new \Exception('Sid is not valid : ' . $response);
		}

		$response = json_decode($response, true);
		return $response;
	}

	/**
	 * Get Sid from gateway to use at payment page url
	 *
	 * @param array $parameters
	 * @throws \Exception
	 * @return array
	 */
	public function paymentConfirmation($checkoutId, $transactionData)
	{
		$confirmationUrl = $this->oppwaCheckoutUrlTest . '/' . $checkoutId . '/payment';
		$confirmationUrl .= '?' . self::getCredentialParameter($transactionData);
		$this->getLogger(__METHOD__)->error('Payreto:confirmationUrl', $confirmationUrl);
		$response = $this->getGatewayPaymentConfirmation($confirmationUrl);

		if (!$response)
		{
			throw new \Exception('Sid is not valid : ' . $response);
		}

		$response = json_decode($response, true);
		return $response;
	}

	/**
	 * Get Sid from gateway to use at payment page url
	 *
	 * @param array $parameters
	 * @throws \Exception
	 * @return array
	 */
	public function paymentServerToServer($checkoutId, $parameters)
	{
		$confirmationUrl = $this->oppwaPaymentUrlTest . '/' . $checkoutId;
		$confirmationUrl .= '?' . http_build_query($parameters, '', '&');

		$response = $this->getGatewayPaymentConfirmation($confirmationUrl);

		if (!$response)
		{
			throw new \Exception('Sid is not valid : ' . $response);
		}

		$response = json_decode($response, true);
		return $response;
	}

	/**
	 * Get Sid from gateway to use at payment page url
	 *
	 * @param array $transactionData
	 * @throws \Exception
	 * @return array
	 */
	public function backOfficePayment($checkoutId, $transactionData)
	{
		$url = $this->oppwaPaymentUrlTest . '/' . $checkoutId;
		$checkoutParameters = $this->getCheckoutParameters($transactionData);
		
		$response = $this->getGatewayResponse($url, $checkoutParameters);

		if (!$response)
		{
			throw new \Exception('Sid is not valid : ' . $response);
		}

		$response = json_decode($response, true);
		return $response;
	}

	/**
	 * get currenty payment status from gateway
	 *
	 * @param $parameters
	 * @throws \Exception
	 * @return array
	 */
	public function getPaymentStatus($parameters)
	{
		
	}

	public static function getCredentialParameter($transactionData) 
	{
		$parameters = array();
        $parameters['authentication.userId'] = $transactionData['login'];
        $parameters['authentication.password'] = $transactionData['password'];
        $parameters['authentication.entityId'] = $transactionData['channel_id'];

        // test mode parameters (true)
        if (!empty($transactionData['test_mode'])) {
            $parameters['testMode'] = $transactionData['test_mode'];
        }

        return $parameters;
	}


    /**
     * get detail of cart item
     *
     * @param array $cartItems
     * @return array $parameters
     */
    private static function getCartItemParameter($cartItems)
    {
        $parameters = array();
        for ($i=0; $i < count($cartItems); $i++) {
            $parameters['cart.items['.$i.'].merchantItemId'] = $cartItems[$i]['merchantItemId'];
            $parameters['cart.items['.$i.'].currency'] = $cartItems[$i]['currency'];
            $parameters['cart.items['.$i.'].quantity'] = $cartItems[$i]['quantity'];
            $parameters['cart.items['.$i.'].name'] = $cartItems[$i]['name'];
            $parameters['cart.items['.$i.'].price'] = $cartItems[$i]['price'];
            $parameters['cart.items['.$i.'].type'] = $cartItems[$i]['type'];
        }
        return $parameters;
    }


	/**
	 * get currenty payment status from gateway
	 *
	 * @param $parameters
	 * @throws \Exception
	 * @return array
	 */
	public function getCheckoutParameters($transactionData)
	{
		$parameters = [];
		$parameters = self::getCredentialParameter($transactionData);
        $parameters['merchantTransactionId'] = $transactionData['transaction_id'];
        $parameters['customer.email'] = $transactionData['customer']['email'];
        $parameters['customer.givenName'] = $transactionData['customer']['first_name'];
        $parameters['customer.surname'] = $transactionData['customer']['last_name'];
        $parameters['billing.street1'] = $transactionData['billing']['street'];
        $parameters['billing.city'] = $transactionData['billing']['city'];
        $parameters['billing.postcode'] = $transactionData['billing']['zip'];
        $parameters['billing.country'] = $transactionData['billing']['country_code'];
        $parameters['amount'] = $transactionData['amount'];
        $parameters['currency'] = $transactionData['currency'];

        $customerDateOfBirth = strtotime($transactionData['customer']['birthdate']);

        if (isset($transactionData['customer']['sex'])) {
            $parameters['customer.sex'] = $transactionData['customer']['sex'];
        } if (isset($transactionData['customer']['birthdate']) && $customerDateOfBirth > 0) {
            $parameters['customer.birthDate'] = $transactionData['customer']['birthdate'];
        } if (isset($transactionData['customer']['phone'])) {
            $parameters['customer.phone'] = $transactionData['customer']['phone'];
        } if (!empty($transactionData['customer']['mobile'])) {
            $parameters['customer.mobile'] = $transactionData['customer']['mobile'];
        }

        //klarna parameters
        if (!empty($transactionData['cartItems'])) {
            $parameters = array_merge($parameters, self::getCartItemParameter($transactionData['cartItems']));
        } if (!empty($transactionData['customParameters']['KLARNA_CART_ITEM1_FLAGS'])) {
            $parameters['customParameters[KLARNA_CART_ITEM1_FLAGS]'] =
            $transactionData['customParameters']['KLARNA_CART_ITEM1_FLAGS'];
        } if (!empty($transactionData['customParameters']['KLARNA_PCLASS_FLAG'])
              && trim($transactionData['customParameters']['KLARNA_PCLASS_FLAG'])!=='') {
            $parameters['customParameters[KLARNA_PCLASS_FLAG]'] =
            $transactionData['customParameters']['KLARNA_PCLASS_FLAG'];
        }

        //paydirekt parameters
        if (!empty($transactionData['customParameters']['PAYDIREKT_minimumAge'])) {
            $parameters['customParameters[PAYDIREKT_minimumAge]'] =
            $transactionData['customParameters']['PAYDIREKT_minimumAge'];
        } if (!empty($transactionData['customParameters']['PAYDIREKT_payment.isPartial'])) {
            $parameters['customParameters[PAYDIREKT_payment.isPartial]'] =
            $transactionData['customParameters']['PAYDIREKT_payment.isPartial'];
        } if (!empty($transactionData['customParameters']['PAYDIREKT_payment.shippingAmount'])) {
            $parameters['customParameters[PAYDIREKT_payment.shippingAmount]'] =
            $transactionData['customParameters']['PAYDIREKT_payment.shippingAmount'];
        }

        // payment type for RG.DB or only RG
        if (!empty($transactionData['payment_type'])) {
            $parameters['paymentType'] = $transactionData['payment_type'];
        }

        // registration parameter (true)
        if (!empty($transactionData['payment_registration'])) {
            $parameters['createRegistration'] = $transactionData['payment_registration'];
            if (!empty($transactionData['3D'])) {
                  $parameters['customParameters[presentation.amount3D]'] =
                  $transactionData['3D']['amount'];
                  $parameters['customParameters[presentation.currency3D]'] = $transactionData['3D']['currency'];
            }
        }

        // recurring payment parameters : initial/repeated
        if (!empty($transactionData['payment_recurring'])) {
            $parameters['recurringType'] = $transactionData['payment_recurring'];
        }

        if (!empty($transactionData['customer_ip'])) {
            $parameters['customer.ip'] = $transactionData['customer_ip'];
        }

        //easycredit parameter
        if (!empty($transactionData['paymentBrand'])) {
            $parameters['paymentBrand'] = $transactionData['paymentBrand'];
        }

        if (!empty($transactionData['shopperResultUrl'])) {
            $parameters['shopperResultUrl'] = $transactionData['shopperResultUrl'];
        }

        if (isset($transactionData['customer']['birthdate']) && $customerDateOfBirth > 0) {
            $parameters['customer.birthDate'] = $transactionData['customer']['birthdate'];
        }

        if (!empty($transactionData['customParameters']['RISK_ANZAHLPRODUKTEIMWARENKORB'])) {
            $parameters['customParameters[RISK_ANZAHLPRODUKTEIMWARENKORB]'] =
            $transactionData['customParameters']['RISK_ANZAHLPRODUKTEIMWARENKORB'];
        }

        if (isset($transactionData['customParameters']['RISK_ANZAHLBESTELLUNGEN'])) {
            $parameters['customParameters[RISK_ANZAHLBESTELLUNGEN]'] =
            $transactionData['customParameters']['RISK_ANZAHLBESTELLUNGEN'];
        }

        if (isset($transactionData['customParameters']['RISK_BESTELLUNGERFOLGTUEBERLOGIN'])) {
            $parameters['customParameters[RISK_BESTELLUNGERFOLGTUEBERLOGIN]'] =
            $transactionData['customParameters']['RISK_BESTELLUNGERFOLGTUEBERLOGIN'];
        }

        if (isset($transactionData['customParameters']['RISK_KUNDENSTATUS'])) {
            $parameters['customParameters[RISK_KUNDENSTATUS]'] =
            $transactionData['customParameters']['RISK_KUNDENSTATUS'];
        }

        if (isset($transactionData['customParameters']['RISK_KUNDESEIT'])) {
            $parameters['customParameters[RISK_KUNDESEIT]'] = $transactionData['customParameters']['RISK_KUNDESEIT'];
        }

        if (!empty($transactionData['customParameters']['RISK_NEGATIVEZAHLUNGSINFORMATION'])) {
            $parameters['customParameters[RISK_NEGATIVEZAHLUNGSINFORMATION]'] =
            $transactionData['customParameters']['RISK_NEGATIVEZAHLUNGSINFORMATION'];
        }

        if (!empty($transactionData['customParameters']['RISK_RISIKOARTIKELIMWARENKORB'])) {
            $parameters['customParameters[RISK_RISIKOARTIKELIMWARENKORB]'] =
            $transactionData['customParameters']['RISK_RISIKOARTIKELIMWARENKORB'];
        }
        if (isset($transactionData['shipping']['city'])) {
            $parameters['shipping.city'] = $transactionData['shipping']['city'];
        }
        if (isset($transactionData['shipping']['street1'])) {
            $parameters['shipping.street1'] = $transactionData['shipping']['street1'];
        }
        if (isset($transactionData['shipping']['postcode'])) {
            $parameters['shipping.postcode'] = $transactionData['shipping']['postcode'];
        }
        if (isset($transactionData['shipping']['country'])) {
            $parameters['shipping.country'] = $transactionData['shipping']['country'];
        }

        if (!empty($transactionData['registrations'])) {
            foreach ($transactionData['registrations'] as $key => $value) {
                  $parameters['registrations['.$key.'].id'] = $value;
            }
        }

        return http_build_query($parameters);
	}

	/**
	 * send request and get refund status from gateway
	 *
	 * @param $parameters
	 * @throws \Exception
	 * @return xml
	 */
	public function doRefund($transactionId, $parameters)
	{
		$checkoutUrl = $this->oppwaPaymentUrlTest . '/' . $transactionId;
		$response = $this->getGatewayResponse($checkoutUrl, $parameters);

		if (!$response)
		{
			throw new \Exception('Sid is not valid : ' . $response);
		}

		$responseId = json_decode($response, true);
		return $responseId;
	}

}
