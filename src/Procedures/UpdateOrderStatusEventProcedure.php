<?php
namespace Payreto\Procedures;

use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Order\Models\Order;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;

use Payreto\Services\PaymentService;
use Payreto\Services\GatewayService;
use Payreto\Helper\PaymentHelper;

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
					PaymentRepositoryContract $paymentRepository,
					paymentService $paymentService,
					GatewayService $gatewayService,
					PaymentHelper $paymentHelper
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
		if (count($payments) > 0) {
			foreach ($payments as $payment) {
				$transactionData = array_merge(
					$paymentService->getCredentials($payment->method),
					[
						'amount' => $payment->amount,
						'currency' => $payment->currency,
						'payment_type' => 'CP',
						'test_mode' => $paymentService->getTestMode($payment->method)
					]
				);
				$checkoutId = $payment->properties[0]->value;
				if ($order->statusId == 4.5 && $payment->status == 1) {
					$paymentResult = $gatewayService->backOfficePayment($checkoutId, $transactionData);
					$this->getLogger(__METHOD__)->error('Payreto:paymentResult', $paymentResult);

					if ($gatewayService->getTransactionResult($paymentResult['result']['code']) == 'ACK') {
						$paymentData['transaction_id'] = $paymentResult['id'];
			            $paymentData['paymentKey'] = $payment->paymentKey;
			            $paymentData['amount'] = $paymentResult['amount'];
			            $paymentData['currency'] = $paymentResult['currency'];
			            $paymentData['status'] = $paymentHelper->mapTransactionState('2');
			            $paymentData['orderId'] = $orderId;

			            $paymentHelper->updatePlentyPayment($paymentData);
					}
				}
			}
		}
	}
}
