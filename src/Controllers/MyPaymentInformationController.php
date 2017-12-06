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

	public function __construct(PaymentService $paymentService) {
		if (!$paymentService->checkCustomerLoginStatus()) {
			return $this->response->redirectTo('login');
		}

	}
	
	public function show(Twig $twig)
	{
		return $twig->render('Payreto::Information.MyPaymentInformation', []);
	}

}
