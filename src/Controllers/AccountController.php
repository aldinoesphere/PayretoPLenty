<?php

namespace Payreto\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Application;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Templates\Twig;
use Plenty\Modules\Frontend\Services\SystemService;

use Payreto\Services\Database\AccountService;
use Payreto\Helper\PaymentHelper;

/**
* Class SettingsController
* @package Payreto\Controllers
*/
class AccountController extends Controller
{
	use Loggable;

	/**
	 * @var Request
	 */
	private $request;

	/**
	 * @var Response
	 */
	private $response;

	/**
	 *
	 * @var systemService
	 */
	private $systemService;

	/**
	 * @var accountService
	 */
	private $accountService;

	/**
	 * @var paymentHelper
	 */
	private $paymentHelper;

	/**
	 * SettingsController constructor.
	 * @param accountService $accountService
	 */
	public function __construct(
					Request $request,
					Response $response,
					SystemService $systemService,
					AccountService $accountService,
					PaymentHelper $paymentHelper
	) {
		$this->request = $request;
		$this->response = $response;
		$this->paymentHelper = $paymentHelper;
		$this->systemService = $systemService;
		$this->accountService = $accountService;
	}

	/**
	 * save the settings
	 *
	 * @param Request $request
	 */
	public function saveAccounts($accountData)
	{
		$this->getLogger(__METHOD__)->error('Payreto:accountData', $accountData);
		return $this->accountService->saveAccount($accountData);
	}

	/**
	 * load the settings
	 *
	 * @param string $settingType
	 * @return array
	 */
	public function loadAccounts($customerId)
	{
		return $this->accountService->loadAccounts($customerId);
	}

	/**
	 * Load the settings for one webshop
	 *
	 * @param string $plentyId
	 * @param string $settingType
	 * @return null|mixed
	 */
	public function loadAccount($customerId, $settingType)
	{
		return $this->accountService->loadAccount($customerId, $settingType);
	}

	/**
	 * Save Payreto backend configuration
	 *
	 */
	public function saveAccount($accountData)
	{

		$this->getLogger(__METHOD__)->error('Payreto:accountData', $accountData);
		$this->getLogger(__METHOD__)->error('Payreto:accountData1', $this->accountService->loadAccountByRefId($accountData['refId']));

		if (!$this->accountService->loadAccountByRefId($accountData['refId'])) {
			$result = $this->saveAccounts($accountData);
		}

		if ($result == 1)
		{
			$status = 'success';
		}
		else
		{
			$status = 'failed';
		}
		$this->getLogger(__METHOD__)->error('Payreto:saveAccount', $result);

		return $status;
	}
}