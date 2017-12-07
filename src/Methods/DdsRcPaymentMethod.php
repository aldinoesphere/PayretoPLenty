<?php

namespace Payreto\Methods;

use Plenty\Plugin\Log\Loggable;

/**
* Class AdbPaymentMethod
* @package Payreto\Methods
*/
class DdsRcPaymentMethod extends AbstractPaymentMethod
{
	use Loggable;

	/**
	 * @var name
	 */
	protected $name = 'Direct Debit (Sepa)';

	/**
	 * @var logoFileName
	 */
	protected $logoFileName = 'dds.png';

	/**
	 * @var settingsType
	 */
	protected $settingsType = 'PAYRETO_DDS_RC';
}
