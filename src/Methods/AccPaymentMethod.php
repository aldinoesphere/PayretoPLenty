<?php

namespace Payreto\Methods;

use Plenty\Plugin\Log\Loggable;

/**
* Class AccPaymentMethod
* @package Payreto\Methods
*/
class AccPaymentMethod extends AbstractPaymentMethod
{
	use Loggable;

	/**
	 * @var name
	 */
	protected $name = 'Credit Card';

	/**
	 * @var logoFileName
	 */
	protected $logoFileName = 'acc.png';

	/**
	 * @var settingsType
	 */
	protected $settingsType = 'PAYRETO_ACC';
}
