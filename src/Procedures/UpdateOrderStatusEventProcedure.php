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
use Payreto\Services\OrderService;
use Payreto\Helper\PaymentHelper;

use IO\Services\NotificationService;

/**
* Class UpdateOrderStatusPreAuthorizationEventProcedure
* @package Payreto\Procedures
*/
class UpdateOrderStatusEventProcedure
{
	use Loggable;

	/**
	 * @var PayementService
	 */
	private $paymentService;

	/**
	 * @var paymentRepository
	 */
	private $paymentRepository;

	/**
	 * @var gatewayService
	 */
	private $gatewayService;

	/**
	 * @var paymentHelper
	 */
	private $paymentHelper;

	/**
     *
     * @var notification
     */
    private $notification;

	/**
	 * @var orderService
	 */
	private $orderService;

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
					OrderService $orderService,
					PaymentHelper $paymentHelper,
					NotificationService $notification
	) {
		/** @var Order $order */
		$order = $eventTriggered->getOrder();
		$this->orderService = $orderService;
		$this->paymentRepository = $paymentRepository;
		$this->paymentService = $paymentService;
		$this->paymentHelper = $paymentHelper;
		$this->gatewayService = $gatewayService;
		$this->notification = $notification;

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

				$transactionData = $paymentService->getCredentials($payment->method->paymentKey);
				// Check payment status From Gateway
				$checkoutId = $payment->properties[0]->value;
				$inReviewStatus = $gatewayService->updateStatus($checkoutId, $transactionData);

				$this->getLogger(__METHOD__)->error('Payreto:inReviewStatus', $inReviewStatus);
				$this->getLogger(__METHOD__)->error('Payreto:payment', $payment);
				if (!$inReviewStatus['is_valid']) {
					$notification->error($gatewayService->getErrorMessage('ERROR_UNKNOWN'));
					$orderService->updateOrderStatus($orderId, 'IR');
				} elseif(!isset($inReviewStatus['response']['id'])) {
					$notification->error($gatewayService->getErrorMessage('ERROR_UNKNOWN'));
					$orderService->updateOrderStatus($orderId, 'IR');
				} else {
					$returnCode = $inReviewStatus['response']['result']['code'];
        			$transactionResult = $gatewayService->getTransactionResult($returnCode);
        			if ($transactionResult === 'ACK') {
        				$paymentType = $gatewayService->getPaymentTypeResponse($inReviewStatus['response']);
        				$this->getLogger(__METHOD__)->error('Payreto:paymentType', $paymentType);
        				$this->getLogger(__METHOD__)->error('Payreto:paymentStatus', $payment->status);

        				if ($payment->status == 1 && $paymentType != 'IR') {
        					$paymentData['transaction_id'] = $inReviewStatus['response']['id'];
				            $paymentData['payment_type'] = $payment->method->paymentKey;
				            $paymentData['amount'] = $inReviewStatus['response']['amount'];
				            $paymentData['currency'] = $inReviewStatus['response']['currency'];
				            $paymentData['status'] = $paymentHelper->getPaymentStatus($paymentType);
							$paymentData['orderId'] = $orderId;

							$paymentHelper->updatePaymentPropertyValue(
									$payment->properties,
									PaymentProperty::TYPE_BOOKING_TEXT,
									$paymentHelper->getPaymentBookingText($paymentData)
							);

							$payment->status = $paymentHelper->getPaymentStatus($paymentType);
							
							$orderService->updateOrderStatus($orderId, $paymentType);
							if ($paymentType == 'CP' || $paymentType == 'DB' || $paymentType == 'RC') {
								$this->paymentHelper->updatePlentyPayment($paymentData);
							} else {
								$paymentRepository->updatePayment($payment);
							}
        				} elseif ($payment->status == 2 && $paymentType == 'PA' && $order->statusId == 5) {
        					$this->doCapturePayment($payment, $order);
        				}
        			} elseif ($transactionResult === 'NOK') {
        				$returnMessage = $gatewayService->getErrorIdentifier($returnCode);
						$notification->error($gatewayService->getErrorMessage($returnMessage));
						$orderService->updateOrderStatus($orderId, 'IR');
        			} else {
        				$notification->error($gatewayService->getErrorMessage('ERROR_UNKNOWN'));
        				$orderService->updateOrderStatus($orderId, 'IR');
        			}
				}
			}
		}
	}

	private function doCapturePayment($payment, $order) {
		// Update Payment Payment Accepted PA->DB
		// only sales orders are allowed order types to upate order status
		if ($order->typeId == 1)
		{
			$orderId = $order->id;
		}
		$getCredentials = $this->paymentService->getCredentials($payment->method->paymentKey);
		$checkoutId = $payment->properties[0]->value;
		$transactionData = array_merge(
			$getCredentials,
			[
				'amount' => $payment->amount,
				'currency' => $payment->currency,
				'payment_type' => 'CP',
				'test_mode' => $this->paymentService->getTestMode($payment->method->paymentKey)
			]
		);
		$paymentResult = $this->gatewayService->backOfficeOperation($checkoutId, $transactionData);
		$this->getLogger(__METHOD__)->error('Payreto:payments', $payments);
		$this->getLogger(__METHOD__)->error('Payreto:paymentResult', $paymentResult);

		if (!$paymentResult['is_valid']) {
			$this->notification->error($this->gatewayService->getErrorMessage('ERROR_UNKNOWN'));
		} elseif (!isset($paymentResult['response']['id'])) {
			$this->notification->error($this->gatewayService->getErrorMessage('ERROR_UNKNOWN'));
		} else {
			$transactionResult = $this->gatewayService->getTransactionResult($paymentResult['response']['result']['code']);
			if ($transactionResult == 'ACK') {
				$paymentData['transaction_id'] = $paymentResult['response']['id'];
	            $paymentData['payment_type'] = $payment->paymentKey;
	            $paymentData['amount'] = $paymentResult['response']['amount'];
	            $paymentData['currency'] = $paymentResult['response']['currency'];
	            $paymentData['status'] = $this->paymentHelper->getPaymentStatus($paymentResult['response']['paymentType']);
	            $paymentData['orderId'] = $orderId;

	            $this->paymentHelper->updatePaymentPropertyValue(
							$payment->properties,
							PaymentProperty::TYPE_BOOKING_TEXT,
							$this->paymentHelper->getPaymentBookingText($paymentData)
					);

	            $this->getLogger(__METHOD__)->error('Payreto:paymentData', $paymentData);

				$payment->status = $this->paymentHelper->getPaymentStatus($paymentResult['response']['paymentType']);
				$payment->unaccountable = null;

				$this->getLogger(__METHOD__)->error('Payreto:payment', $payment);

				$this->getLogger(__METHOD__)->error('Payreto:paymentType', $paymentResult['response']['paymentType']);

	            $this->orderService->updateOrderStatus($orderId, $paymentResult['response']['paymentType']);
	            $this->paymentHelper->updatePlentyPayment($paymentData);
			} elseif ($transactionResult == 'NOK') {
				$returnMessage = $this->gatewayService->getErrorIdentifier($paymentResult['response']['result']['code']);
				$this->notification->error($this->gatewayService->getErrorMessage($returnMessage));
				$this->orderService->updateOrderStatus($orderId, 'PA');
			} else {
				$this->notification->error($this->gatewayService->getErrorMessage('ERROR_UNKNOWN'));
				$this->orderService->updateOrderStatus($orderId, 'PA');
			}
		}
	}
}
