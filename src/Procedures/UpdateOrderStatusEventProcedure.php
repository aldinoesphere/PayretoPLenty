<?php
namespace Payreto\Procedures;

use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;

// use Payreto\Services\PaymentService;
// use Payreto\Helper\PaymentHelper;

/**
* Class UpdateOrderStatusEventProcedure
* @package Payreto\Procedures
*/
class UpdateOrderStatusEventProcedure
{
	use Loggable;

	/**
	 * @param EventProceduresTriggered $eventTriggered
	 * @param PaymentRepositoryContract $paymentRepository
	 * @param PaymentService $paymentService
	 * @param PaymentHelper $paymentHelper
	 * @throws \Exception
	 */
	public function run(
					EventProceduresTriggered $eventTriggered,
					PaymentRepositoryContract $paymentRepository
	) {
		/** @var Order $order */
		$order = $eventTriggered->getOrder();
		$this->getLogger(__METHOD__)->error('Payreto:order', $order);

		// only sales orders are allowed order types to upate order status
		if ($order->typeId == 1)
		{
			$orderId = $order->id;
		}

		if (empty($orderId))
		{
			throw new \Exception('Update order status Skrill payment failed! The given order is invalid!');
		}

		/** @var Payment[] $payment */
		$payments = $paymentRepository->getPaymentsByOrderId($orderId);
		$this->getLogger(__METHOD__)->error('Payreto:payments', $payments);
		
	}
}
