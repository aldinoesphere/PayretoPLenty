<?php

namespace Payreto\Services;

use IO\Models\LocalizedOrder;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Order\Property\Models\OrderPropertyType;
use IO\Builder\Order\OrderBuilder;
use IO\Builder\Order\OrderType;
use IO\Builder\Order\OrderOptionSubType;
use IO\Builder\Order\AddressType;
use IO\Constants\SessionStorageKeys;
use IO\Services\BasketService;
use IO\Services\SessionStorageService;
use IO\Services\CheckoutService;
use IO\Services\CustomerService;

/**
* Class BasketService
* @package Payreto\Services
*/
class BasketService
{
	/**
	 * @var OrderRepositoryContract
	 */
	private $orderRepository;

	/**
	 * @var basketService
	 */
	private $basketService;

	/**
	 * BasketService constructor.
	 * @param OrderRepositoryContract $orderRepository
	 */
	public function __construct(OrderRepositoryContract $orderRepository,
		BasketService $basketService)
	{
		$this->orderRepository = $orderRepository;
		$this->basketService = $basketService;
	}

	

}
