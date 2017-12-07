<?php

namespace Payreto\Methods;

use Plenty\Plugin\Log\Loggable;

/**
* Class PpmPaymentMethod
* @package Payreto\Methods
*/
class PpmRcPaymentMethod extends AbstractPaymentMethod
{
	use Loggable;

	/**
	 * @var name
	 */
	protected $name = 'Paypal';

	/**
	 * @var logoFileName
	 */
	protected $logoFileName = 'ppm.png';

	/**
	 * @var settingsType
	 */
	protected $settingsType = 'PAYRETO_PPM_RC';
}
