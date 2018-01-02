<?php  
namespace Payreto\Methods;

use Plenty\Modules\Payment\Method\Contracts\PaymentMethodService;
use Plenty\Modules\Frontend\Contracts\Checkout;
use Plenty\Modules\Frontend\Session\Storage\Contracts\FrontendSessionStorageFactoryContract;
use Plenty\Plugin\Application;
use Plenty\Plugin\Log\Loggable;

use Payreto\Services\PaymentService;
/**
* 
*/
class AbstractPaymentMethod extends PaymentMethodService
{
	use Loggable;

	/**
	 * @var Checkout
	 */
	protected $checkout;

	/**
	 * @var PaymentService
	 */
	protected $paymentService;

	/**
	 * @var paymentSettings
	 */
	protected $paymentSettings;

	/**
	 * @var generalSettings
	 */
	protected $generalSettings;

	/**
	 * @var name
	 */
	protected $name = '';

	/**
	 * @var allowedBillingCountries
	 */

	/**
	 * @var logoFileName
	 */
	protected $logoFileName = '';

	/**
	 * @var settingsType
	 */
	protected $settingsType = '';
	
	function __construct(Checkout $checkout, PaymentService $paymentService)
	{
		$this->checkout         = $checkout;
		$this->paymentService   = $paymentService;
		$this->generalSettings 	= $this->paymentService->getPayretoSettings();
		$this->paymentSettings 	= $this->paymentService->getPaymentSettings($this->settingsType);
	}

	/**
	 * Check whether the payment setting is display
	 *
	 * @return bool
	 */
	protected function isEnabled()
	{
		if (array_key_exists('display', $this->paymentService->settings) && $this->paymentService->settings['display'] == 1)
		{
			if ($this->generalSettings['recurring'] == 1) {
				if ( $this->settingsType == 'PAYRETO_ACC' 
					|| $this->settingsType == 'PAYRETO_DDS' 
					|| $this->settingsType == 'PAYRETO_PPM' 
				) {
					return false;
				} else {
					return true;
				}
			} else {
				if ($this->settingsType == 'PAYRETO_ACC_RC' 
					|| $this->settingsType == 'PAYRETO_DDS_RC' 
					|| $this->settingsType == 'PAYRETO_PPM_RC' 
				) {
					return false;
				} else {
					return true;
				}
				
			}
		}
		
		return false;
	}

	/**
	 * get logo file name
	 *
	 * @return string
	 */
	protected function getLogoFileName()
	{
		return $this->logoFileName;
	}

	/**
	 * Check whether the payment method is active
	 *
	 * @return bool
	 */
	public function isActive()
	{
		if ($this->isEnabled())
		{
			return true;
		}
		return false;
	}

	/**
	 * Get the name of the payment method
	 *
	 * @return string
	 */
	public function getName()
	{
		$session = pluginApp(FrontendSessionStorageFactoryContract::class);
		$lang = $session->getLocaleSettings()->language;

		if ($this->paymentService->settings[$this->settingsType]['language'][$lang]) {
			return $this->paymentService->settings[$this->settingsType]['language'][$lang];
		}
		return $this->name;
	}

	/**
	 * Get additional costs for Payreto.
	 * Payreto did not allow additional costs
	 *
	 * @return float
	 */
	public function getFee()
	{
		return 0.00;
	}

	/**
	 * Get the path of the icon
	 *
	 * @return string
	 */
	public function getIcon()
	{
		$app = pluginApp(Application::class);
		$icon = $app->getUrlPath('payreto').'/images/logos/'.$this->getLogoFileName();

		return $icon;
	}

	/**
	 * Get the description of the payment method.
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return '';
	}

	/**
	 * Check if it is allowed to switch to this payment method
	 *
	 * @param int $orderId
	 * @return bool
	 */
	public function isSwitchableTo($orderId)
	{
		return false;
	}

	/**
	 * Check if it is allowed to switch from this payment method
	 *
	 * @param int $orderId
	 * @return bool
	 */
	public function isSwitchableFrom($orderId)
	{
		return true;
	}
}

?>