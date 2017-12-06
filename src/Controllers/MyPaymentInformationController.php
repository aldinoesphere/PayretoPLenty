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

use IO\Api\ApiResource;
use IO\Api\ApiResponse;
use IO\Api\ResponseCode;

use Payreto\Services\PaymentService;

/**
* Class PaymentController
* @package Payreto\Controllers
*/
class MyPaymentInformationController extends Controller
{
	use Loggable;

	/**
	 * @var paymentService
	 */
	private $paymentService;

	/**
	 * @var response
	 */
	private $response;

	public function __construct(PaymentService $paymentService,
		Response $response
	) {
		$this->paymentService = $paymentService;
		$this->response = $response;
	}
	
	public function show(Twig $twig)
	{
		if (!$this->paymentService->checkCustomerLoginStatus()) {
			return $this->response->redirectTo('login');
		} else {
			return $this->response->redirectTo('login');
		}

		return $twig->render('Payreto::Information.MyPaymentInformation', []);
	}

}
