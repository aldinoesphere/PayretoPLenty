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

use IO\Api\ApiResponse;
use IO\Api\ResponseCode;

use Payreto\Helper\PaymentHelper;
use Payreto\Controllers\AccountController;
use Payreto\Services\PaymentService;
use Payreto\Services\GatewayService;

/**
* Class PaymentController
* @package Payreto\Controllers
*/
class MyPaymentInformationController extends Controller
{
	use Loggable;

	/**
	 * @var response
	 */
	private $response;

	/**
	 * @var request
	 */
	private $request;

	/**
	 * @var paymentHelper
	 */
	private $paymentHelper;

	/**
	 * @var accountController
	 */
	private $accountController;

	/**
	 * @var paymentService
	 */
	private $paymentService;

	/**
	 *
	 * @var gatewayService
	 */
	private $gatewayService;

	public function __construct(
		Response $response,
		Request $request,
		PaymentHelper $paymentHelper,
		AccountController $accountController,
		PaymentService $paymentService,
		GatewayService $gatewayService
	) {
		$this->response = $response;
		$this->request = $request;
		$this->paymentHelper = $paymentHelper;
		$this->gatewayService = $gatewayService;
		$this->accountController = $accountController;
		$this->paymentService = $paymentService;
	}
	
	public function show(Twig $twig)
	{
		$customerId = $this->paymentHelper->getCustomerId();
		$accounts = $this->accountController->loadAccounts($customerId);
		$accountArray = [];

		foreach ($accounts as $account) {
			$accountArray[$account->paymentGroup][] = $account;
		}

		$this->getLogger(__METHOD__)->error('Payreto:accountArray', $accountArray);

		return $twig->render('Payreto::Information.MyPaymentInformation', $accountArray);
		
	}

	public function addAccount(Twig $twig, $paymentMethod) 
	{
		switch ($paymentMethod) {
			case 'credit-card':
				$paymentKey = 'PAYRETO_ACC_RC';
				break;

			case 'direct-debit':
				$paymentKey = 'PAYRETO_DDS_RC';
				break;

			case 'paypal':
				$paymentKey = 'PAYRETO_PPM_RC';
				break;
		}

		$recurringTranscationParameters = $this->paymentService->getRecurringPaymentParameters($paymentKey);

		$checkoutResponse = $this->gatewayService->getCheckoutResponse($recurringTranscationParameters);

		if ($this->gatewayService->getTransactionResult($checkoutResponse['result']['code']) == 'ACK') 
		{
			$paymentPageUrl = $this->paymentHelper->getDomain().'/payment/payreto/pay-register/' . $checkoutResponse['id'];
			$paymentWidgetUrl = $this->gatewayService->getPaymentWidgetUrl($paymentSettings['server'], $checkoutResponse['id']);

			$this->getLogger(__METHOD__)->error('Payreto:checkoutResponse', $checkoutResponse);
			$this->getLogger(__METHOD__)->error('Payreto:paymentPageUrl', $paymentPageUrl);
			$this->getLogger(__METHOD__)->error('Payreto:paymentWidgetUrl', $paymentWidgetUrl);

			$data = [
				'paymentBrand' => $paymentBrand,
				'checkoutId' => $checkoutResponse['id'],
				'paymentPageUrl' => $paymentPageUrl,
	            'cancelUrl' => '/my-payment-information',
	            'paymentWidgetUrl' => $paymentWidgetUrl,
	            'frameTestMode' => $paymentSettings['server']
			];
			$this->getLogger(__METHOD__)->error('Payreto:data', $data);
			return $twig->render('Payreto::Payment.PaymentRegister', $data);
		} else {
			$returnMessage = $this->gatewayService::getErrorIdentifier($checkoutResponse['result']['code']);
		}
	}

}
