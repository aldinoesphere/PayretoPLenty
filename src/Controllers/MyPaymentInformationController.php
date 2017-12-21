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
use IO\Services\NotificationService;

use Payreto\Helper\PaymentHelper;
use Payreto\Controllers\AccountController;
use Payreto\Services\PaymentService;
use Payreto\Services\GatewayService;
use Payreto\Controllers\SettingsController;

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
     * @var notification
     */
    private $notification;

	/**
	 * @var settingsController
	 */
	private $settingsController;

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
		GatewayService $gatewayService,
		SettingsController $settingsController,
		NotificationService $notification
	) {
		$this->response = $response;
		$this->request = $request;
		$this->paymentHelper = $paymentHelper;
		$this->gatewayService = $gatewayService;
		$this->accountController = $accountController;
		$this->paymentService = $paymentService;
		$this->settingsController = $settingsController;
		$this->notification = $notification;
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

		if (!$customerId) {
			return $this->response->redirectTo('login');
		}

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

		$paymentSettings = $this->paymentService->getPaymentSettings($paymentKey);
        $optionSetting = $this->settingsController->getOptionSetting($paymentKey);
		$paymentBrand = $paymentSettings['cardType'] ? str_replace(',', ' ', $paymentSettings['cardType']) : $optionSetting['paymentBrand'];
		$isRedirect = $this->paymentService->isRedirectPayment($paymentKey);

		$recurringTranscationParameters = $this->paymentService->getRecurringPaymentParameters($paymentKey);

		$widgetResult = $this->gatewayService->getCheckoutResponse($recurringTranscationParameters);
		$resultWidget = $this->gatewayService->getTransactionResult($widgetResult['result']['code']);

		if ($resultWidget == 'ACK') 
		{
			$paymentPageUrl = $this->paymentHelper->getDomain().'/payment/payreto/pay-register/' . $widgetResult['id'] .'/' . $paymentKey;
			$paymentWidgetUrl = $this->gatewayService->getPaymentWidgetUrl($paymentSettings['server'], $widgetResult['id']);

			$this->getLogger(__METHOD__)->error('Payreto:checkoutResponse', $widgetResult);
			$this->getLogger(__METHOD__)->error('Payreto:paymentPageUrl', $paymentPageUrl);
			$this->getLogger(__METHOD__)->error('Payreto:paymentWidgetUrl', $paymentWidgetUrl);

			$data = [
				'paymentBrand' => $paymentBrand,
				'checkoutId' => $widgetResult['id'],
				'paymentPageUrl' => $paymentPageUrl,
				'redirect' => $isRedirect,
	            'cancelUrl' => '/my-payment-information',
	            'paymentWidgetUrl' => $paymentWidgetUrl,
	            'frameTestMode' => $paymentSettings['server']
			];
			$this->getLogger(__METHOD__)->error('Payreto:data', $data);
			switch ($isRedirect) {
				case true:
					$template = 'PaymentRedirect';
					break;
				
				default:
					$template = 'PaymentWidget';
					break;
			}
			// return $twig->render('Payreto::Payment.' . $template, $data);
			return $twig->render('Payreto::Payment.PaymentRegister', $data);
		} elseif ($resultWidget == 'NOK') {
			$returnMessage = $this->gatewayService->getErrorIdentifier($widgetResult);
			$this->notification->error($this->gatewayService->getErrorMessage('ERROR_GENERAL_REDIRECT'));
			return $this->response->redirectTo('my-payment-information');
		}
	}

	public function deleteAccountConfirmation(Twig $twig, $id)
	{
		$account = $this->accountController->loadAccountById($id)[0];
		$this->getLogger(__METHOD__)->error('Payreto:account', $account);

		$data = [
			'id' => $account->id,
			'paymentKey' => $account->paymentGroup,
			'deleteResponseUrl' => '/payment/payreto/do/delete',
			'referenceId' => $account->refId,
			'cancelUrl' => 'my-payment-information'
		];

		return $twig->render('Payreto::Payment.PaymentDeregister', $data);
	}

}
