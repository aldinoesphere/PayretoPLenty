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
	public function saveAccounts(Request $request)
	{
		return $this->accountService->saveSettings($request->get('accounts'));
	}

	/**
	 * load the settings
	 *
	 * @param string $settingType
	 * @return array
	 */
	public function loadAccounts($settingType)
	{
		return $this->accountService->loadSettings($settingType);
	}

	/**
	 * Load the settings for one webshop
	 *
	 * @param string $plentyId
	 * @param string $settingType
	 * @return null|mixed
	 */
	public function loadAccount($plentyId, $settingType)
	{
		return $this->accountService->loadSetting($plentyId, $settingType);
	}

	/**
	 * Save Payreto backend configuration
	 *
	 */
	public function saveAccount($accountData)
	{
		$accountSettings['accounts'] = [
			'customerId' => '' ,
			'paymentGroup' => '' ,
			'brand' => '' ,
			'holder' => '' ,
			'email' => '' ,
			'last4digits' => '' ,
			'expMonth' => '' ,
			'expYear' => '' ,
			'serverMode' => '' ,
			'channelId' => '' ,
			'refId' => '' ,
			'paymentDefault' => '' 
		];

		$this->getLogger(__METHOD__)->error('Payreto:accountSettings', $accountSettings);

		$result = $this->accountService->saveConfiguration($accountSettings);

		if ($result == 1)
		{
			$status = 'success';
		}
		else
		{
			$status = 'failed';
		}
		$this->getLogger(__METHOD__)->error('Payreto:saveConfiguration', $result);

		return $status;
	}
}