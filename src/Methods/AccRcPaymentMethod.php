<?php

namespace Payreto\Methods;

use Plenty\Plugin\Log\Loggable;

/**
* Class AccPaymentMethod
* @package Payreto\Methods
*/
class AccRcPaymentMethod extends AbstractPaymentMethod
{
	use Loggable;

	/**
	 * @var name
	 */
	protected $name = 'Credit Cards';

	/**
	 * @var logoFileName
	 */
	protected $logoFileName = 'acc.png';

	/**
	 * @var settingsType
	 */
	protected $settingsType = 'PAYRETO_ACC_RC';
}
