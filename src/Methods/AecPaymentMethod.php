<?php

namespace Payreto\Methods;

use Plenty\Plugin\Log\Loggable;

/**
* Class AecPaymentMethod
* @package Payreto\Methods
*/
class AecPaymentMethod extends AbstractPaymentMethod
{
	use Loggable;

	/**
	 * @var name
	 */
	protected $name = 'ratenkauf by easyCredit';

	/**
	 * @var logoFileName
	 */
	protected $logoFileName = 'aec.png';

	/**
	 * @var settingsType
	 */
	protected $settingsType = 'PAYRETO_AEC';
}
