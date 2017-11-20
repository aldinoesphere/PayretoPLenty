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

	protected static $paymentWidgetUrlLive = 'https://oppwa.com/v1/paymentWidgets.js?checkoutId=';
    protected static $paymentWidgetUrlTest = 'https://test.oppwa.com/v1/paymentWidgets.js?checkoutId=';

	/**
	 * Get gateway response
	 *
	 * @param string $url
	 * @param array $parameters
	 * @throws \Exception
	 * @return string
	 */
	private function getGatewayResponse($url, $parameters)
	{
		$postFields = http_build_query($parameters, '', '&');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
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
			return $paymentWidgetUrlLive . $checkoutId;
		} else {
			return $paymentWidgetUrlTest . $checkoutId;
		}
	}

	/**
	 * Get Sid from gateway to use at payment page url
	 *
	 * @param array $parameters
	 * @throws \Exception
	 * @return string
	 */
	public function getCheckoutId($parameters)
	{
		$checkoutUrl = $this->oppwaCheckoutUrlTest;
		$response = $this->getGatewayResponse($checkoutUrl, $parameters);

		if (!$response)
		{
			throw new \Exception('Sid is not valid : ' . $response);
		}

		$responseId = json_decode($response, true);
		$this->getLogger(__METHOD__)->error('Payreto:responseId', $responseId);
		$this->getLogger(__METHOD__)->error('Payreto:checkoutUrl', $checkoutUrl);
		return $responseId["id"];
	}

	/**
	 * Get Sid from gateway to use at payment page url
	 *
	 * @param array $parameters
	 * @throws \Exception
	 * @return string
	 */
	public function getServerToServer($parameters)
	{
		$checkoutUrl = $this->oppwaPaymentUrlTest;
		$response = $this->getGatewayResponse($checkoutUrl, $parameters);

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
	public function paymentConfirmation($checkoutId, $parameters)
	{
		$confirmationUrl = $this->oppwaCheckoutUrlTest . '/' . $checkoutId . '/payment';
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
	 * @param array $parameters
	 * @throws \Exception
	 * @return array
	 */
	public function paymentServerToServer($checkoutId, $parameters)
	{
		$confirmationUrl = $this->oppwaPaymentUrlTest. $checkoutId;
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
	 * @param array $parameters
	 * @throws \Exception
	 * @return array
	 */
	public function backOfficePayment($checkoutId, $parameters)
	{
		$url = $this->oppwaPaymentUrlTest . $checkoutId;
		$response = $this->getGatewayResponse($url, $parameters);

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
