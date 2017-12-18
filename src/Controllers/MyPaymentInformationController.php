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

	public function __construct(
		Response $response,
		Request $request,
		PaymentHelper $paymentHelper,
		AccountController $accountController
	) {
		$this->response = $response;
		$this->request = $request;
		$this->paymentHelper = $paymentHelper;
		$this->accountController = $accountController;
	}
	
	public function show(Twig $twig)
	{
		$customerId = $this->paymentHelper->getCustomerId();
		$accounts = $this->accountController->loadAccounts($customerId);
		$accountArray = [];

		foreach ($accounts as $account) {
			$accountArray[$account->paymentGroup][] = $accounts;
		}

		$this->getLogger(__METHOD__)->error('Payreto:accountArray', $accountArray);

		// return $twig->render('Payreto::Information.MyPaymentInformation', $accountArray);
		
	}

	public function addAccount($paymentMethod) 
	{
		$this->getLogger(__METHOD__)->error('Payreto:paymentMethod', $paymentMethod);
	}

}
