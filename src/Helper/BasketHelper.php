<?php

namespace Payreto\Helper;


use IO\Services\BasketService;
use IO\Services\CustomerService;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;


/**
* Class BasketService
* @package Payreto\Helper
*/
class BasketHelper
{
	/**
	 *
	 * @var AddressRepositoryContract
	 */
	private $addressRepository;

	/**
	 *
	 * @var CountryRepositoryContract
	 */
	private $countryRepository;

	/**
	 * @var basketService
	 */
	private $basketService;

	/**
	 * BasketService constructor.
	 */
	public function __construct(
		AddressRepositoryContract $addressRepository,
		CountryRepositoryContract $countryRepository,
		BasketService $basketService)
	{
		$this->basketService = $basketService;
		$this->addressRepository = $addressRepository;
		$this->countryRepository = $countryRepository;
	}


	public function getBasket()
	{
		return $this->basketService->getBasket();
	}

	/**
	 * Payment Confirmation Data
	 * @return Basket data
	 */
	public function paymentConfirmationData()
	{
		$baskets = $this->basketService->getBasketItems();
		 $data = [
        	'data' => [
        		'order' => [
        			'billingAddress' => $this->getBillingAddress(),
        			'deliveryAddress' => $this->getShippingAddress(),
        			'amounts' => 
        			[
        				[
                            'currency' => $baskets->currency,
        					'netTotal' => $baskets->basketAmountNet,
							'grossTotal' => $baskets->basketAmount,
							'invoiceTotal' => $baskets->basketAmount,
							'vats' => $this->getVats()
        				]
        			],
        			'orderItems' => $this->getBasketOrderItems()
        		],
                'valueNet' => $baskets->itemSumNet,
                'valueGross' => $baskets->itemSum,
                'shippingNet' => $baskets->shippingAmountNet,
                'shippingGross' => $baskets->shippingAmount,
        		'paymentMethodName' => $paymentMethod->name
        	],
            'itemURLs' => ''
        ];
	}

	/**
	 * get Vats
	 * @return Order Items
	 */
	public function getVats()
	{
		$baskets = $this->basketService->getBasketForTemplate();

		foreach ($baskets->totalVats as $vats) {
			$itemVats[] = [
				'vatRate' => $vats->vatValue,
                'value' => $vats->vatAmount
			];
		}

		return $itemVats;
	}

	/**
	 * Payment Confirmation Data
	 * @return Order Items
	 */
	public function getBasketOrderItems()
	{
		$basketItems = $this->basketService->getBasketItemsForTemplate();
		$basketOrderItems = [];
		foreach ($basketItems as $basketItem) {
			$basketOrderItems[] = [
				'quantity' => $basketItem->quantity,
                'itemVariationId' => $basketItem->variationId,
				'orderItemName' => $this->getOrderItemName($basketItem)->name1,
                'itemImage' => $this->getItemImages($basketItem)->item[0]['urlPreview'],
				'amounts' => 
				[
					[
						'priceOriginalGross' => $this->getOrderItemPrice($basketItem)->basePriceNet,
						'priceGross' => $this->getOrderItemPrice($basketItem)->unitPrice,
                        'currency' => $basket->currency
					]
				]
			];
		}

		return $basketOrderItems;
	}

	public function getOrderItemPrice($basketItem)
	{
		return $basketItem->variation->data->calculatedPrices->default;
	}

	public function getOrderItemName($basketItem)
	{
		return $basketItem->variation->data->texts;
	}
	
	public function getItemImages($basketItem)
	{
		return $basketItem->variation->data->images;
	}

	/**
	 * get billing address
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	public function getBillingAddress()
	{
		$addressId = $this->basketService->getBasket()->customerInvoiceAddressId;
		return $this->addressRepository->findAddressById($addressId);
	}

	/**
	 * get billing country code
	 *
	 * @param int $customerInvoiceAddressId
	 * @return string
	 */
	public function getBillingCountryCode($customerInvoiceAddressId = '')
	{
		$customerInvoiceAddressId = !empty($customerInvoiceAddressId) ? $customerInvoiceAddressId : $this->basketService->getBasket()->customerShippingAddressId;
		$billingAddress = $this->addressRepository->findAddressById($customerInvoiceAddressId);
		return $this->countryRepository->findIsoCode($billingAddress->countryId, 'iso_code_3');
	}

	/**
	 * get shipping address
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	public function getShippingAddress()
	{
		$addressId = $this->basketService->getBasket()->customerShippingAddressId;
		if ($addressId != null && $addressId != - 99)
		{
			return $this->addressRepository->findAddressById($addressId);
		}
		else
		{
			return $this->getBillingAddress();
		}
	}

}
