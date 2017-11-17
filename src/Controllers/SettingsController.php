<?php

namespace Payreto\Controllers;

use Plenty\Plugin\Controller;
use Plenty\Plugin\Http\Request;
use Plenty\Plugin\Http\Response;
use Plenty\Plugin\Application;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Templates\Twig;
use Plenty\Modules\Frontend\Services\SystemService;
use Payreto\Services\Database\SettingsService;
use Payreto\Helper\PaymentHelper;

/**
* Class SettingsController
* @package Payreto\Controllers
*/
class SettingsController extends Controller
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
	 * @var settingsService
	 */
	private $settingsService;

	/**
	 * @var paymentHelper
	 */
	private $paymentHelper;

	/**
	 * SettingsController constructor.
	 * @param SettingsService $settingsService
	 */
	public function __construct(
					Request $request,
					Response $response,
					SystemService $systemService,
					SettingsService $settingsService,
					PaymentHelper $paymentHelper
	) {
		$this->request = $request;
		$this->response = $response;
		$this->paymentHelper = $paymentHelper;
		$this->systemService = $systemService;
		$this->settingsService = $settingsService;
	}

	/**
	 * save the settings
	 *
	 * @param Request $request
	 */
	public function saveSettings(Request $request)
	{
		return $this->settingsService->saveSettings($request->get('settingType'), $request->get('settings'));
	}

	/**
	 * load the settings
	 *
	 * @param string $settingType
	 * @return array
	 */
	public function loadSettings($settingType)
	{
		return $this->settingsService->loadSettings($settingType);
	}

	/**
	 * Load the settings for one webshop
	 *
	 * @param string $plentyId
	 * @param string $settingType
	 * @return null|mixed
	 */
	public function loadSetting($plentyId, $settingType)
	{
		return $this->settingsService->loadSetting($plentyId, $settingType);
	}

	/**
	 * Display Payreto backend configuration
	 *
	 * @param Twig $twig
	 * @param string $settingType
	 * @return void
	 */
	public function loadConfiguration(Twig $twig, $settingType)
	{
		$plentyId = $this->systemService->getPlentyId();

		$this->getLogger(__METHOD__)->error('Payreto:plentyId', $plentyId);

		try {
			$configuration = $this->settingsService->getConfiguration($plentyId, $settingType);
		}
		catch (\Exception $e)
		{
			die('something wrong, please try again...');
		}
		if ($configuration['error']['code'] == '401')
		{
			die('access denied...');
		}

		$this->getLogger(__METHOD__)->error('Payreto:loadConfiguration', $configuration);

		return $twig->render(
						'Payreto::Settings.Configuration',
						array(
							'status' => $this->request->get('status'),
							'locale' => substr($_COOKIE['plentymarkets_lang_'], 0, 2),
							'plentyId' => $plentyId,
							'settingType' => $settingType,
							'optionSetting'	=> $this->getOptionSetting($settingType),
							'setting' => $configuration
						)
		);
	}

	protected function getOptionSetting($settingType)
	{
		switch ($settingType) {
			case 'general-setting':
				return [
					'title' =>	'General Setting'
				];
				break;

			case 'credit-card-recurring':
				return [
						'title' => 'Credit Card (recurring)',
						'paymentBrand' => ''
					];
				break;

			case 'PAYRETO_ACC':
				return [
						'title' => 'Credit Card',
						'paymentBrand' => ''
					];
				break;

			case 'direct-debit-recurring':
				return [
						'title' => 'Direct Debit (recurring)',
						'paymentBrand' => 'DIRECTDEBIT_SEPA'
					];
				break;

			case 'PAYRETO_DDS':
				return [
						'title' => 'DIrect Debit',
						'paymentBrand' => 'DIRECTDEBIT_SEPA'
					];
				break;

			case 'PAYRETO_PDR':
				return [
						'title' => 'Paydirect',
						'paymentBrand' => 'PAYDIREKT'
					];
				break;

			case 'paypal-recurring':
				return [
						'title' => 'Paypal (recurring)'
						'paymentBrand' => 'PAYPAL'
					];
				break;

			case 'PAYRETO_PPM':
				return [
						'title' => 'Paypal',
						'paymentBrand' => 'PAYPAL'
					];
				break;

			case 'PAYRETO_ADB':
				return [
						'title' => 'Online Bank Transfer',
						'paymentBrand' => 'SOFORTUEBERWEISUNG'
					];
				break;

			case 'PAYRETO_GRP':
				return [
						'title' => 'Giropay',
						'paymentBrand' => 'GIROPAY'
					];
				break;

			case 'PAYRETO_ECP':
				return [
						'title' => 'Easy Credit',
						'paymentBrand' => 'RATENKAUF'
					];
				break;
		}
	}

	/**
	 * Save Payreto backend configuration
	 *
	 */
	public function saveConfiguration()
	{
		$settingType = $this->request->get('settingType');
		$plentyId = $this->request->get('plentyId');
		$cardTypes = $this->request->get('cardTypes');
		$newCardTypes = [];

		foreach ($cardTypes as $key => $value) {
			array_push($newCardTypes, $value);
		}

		$settings['settingType'] = $settingType;

		if ($settingType == 'general-setting') {
			$settings['settings'][0]['PID_'.$plentyId] = array(
							'userId' => $this->request->get('userId'),
							'password' => $this->request->get('password'),
							'merchantEmail' => $this->request->get('merchantEmail'),
							'shopUrl' => $this->request->get('shopUrl')
						);
		} elseif($settingType == 'PAYRETO_ACC') {
			$settings['settings'][0]['PID_'.$plentyId] = array(
							'language' => $this->request->get('language'),
							'display' => $this->request->get('display'),
							'cardType' => implode(',', $newCardTypes),
							'transactionMode' => $this->request->get('transactionMode'),
							'entityId' => $this->request->get('entityId')
						);
		} elseif($settingType == 'PAYRETO_GRP' 
			|| $settingType == 'PAYRETO_ADB' 
			|| $settingType == 'PAYRETO_ECP' 
			|| $settingType == 'PAYRETO_PPM') {
			$settings['settings'][0]['PID_'.$plentyId] = array(
							'server' => $this->request->get('server'),
							'display' => $this->request->get('display'),
							'entityId' => $this->request->get('entityId')
						);
		}

		$this->getLogger(__METHOD__)->error('Payreto:settings', $settings);

		$result = $this->settingsService->saveConfiguration($settings);

		if ($result == 1)
		{
			$status = 'success';
		}
		else
		{
			$status = 'failed';
		}
		$this->getLogger(__METHOD__)->error('Payreto:saveConfiguration', $settings);

		return $this->response->redirectTo('payreto/settings/'.$settingType.'?status=' . $status);
	}
}