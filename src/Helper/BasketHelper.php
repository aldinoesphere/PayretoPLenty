<?php

namespace Payreto\Helper;


use IO\Services\BasketService;
use IO\Services\CustomerService;
use Plenty\Modules\Account\Address\Contracts\AddressRepositoryContract;
use Plenty\Modules\Order\Shipping\Countries\Contracts\CountryRepositoryContract;
use Plenty\Plugin\Log\Loggable;


/**
* Class BasketService
* @package Payreto\Helper
*/
class BasketHelper
{
	use Loggable;
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
	 * BasketService constructor.
	 */
	public function __construct(
		AddressRepositoryContract $addressRepository,
		CountryRepositoryContract $countryRepository)
	{
		$this->addressRepository = $addressRepository;
		$this->countryRepository = $countryRepository;
	}


	public function getBasket()
	{
		$basketService = pluginApp(BasketService::class);
		return $basketService->getBasket();
	}

	/**
	 * Payment Confirmation Data
	 * @return Basket data
	 */
	public function paymentConfirmationData()
	{
		$baskets = $this->getBasket();
		$this->getLogger(__METHOD__)->error('Payreto:baskets', $baskets);
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
                'shippingGross' => $baskets->shippingAmount
        	],
            'itemURLs' => ''
        ];

        return $data;
	}

	/**
	 * get Vats
	 * @return Order Items
	 */
	public function getVats()
	{
		$basketService = pluginApp(BasketService::class);
		$baskets = $basketService->getBasketForTemplate();
		$this->getLogger(__METHOD__)->error('Payreto:baskets', $baskets);

		$itemVats = [];

		foreach ($baskets['totalVats'] as $vats) {
			$this->getLogger(__METHOD__)->error('Payreto:vats', $vats);
			$itemVats[] = [
				'vatRate' => $vats['vatValue'],
                'value' => $vats['vatAmount']
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
		$basketService = pluginApp(BasketService::class);
		$basketItems = $basketService->getBasketItemsForTemplate();
		$basketOrderItems = [];
		foreach ($basketItems as $basketItem) {
			$basketOrderItems[] = [
				'quantity' => $basketItem['quantity'],
                'itemVariationId' => $basketItem['variationId'],
				'orderItemName' => $this->getOrderItemName($basketItem)['name1'],
                'itemImage' => $this->getItemImages($basketItem)['item'][0]['urlPreview'],
				'amounts' => 
				[
					[
						'priceOriginalGross' => $this->getOrderItemPrice($basketItem)['basePriceNet'],
						'priceGross' => $this->getOrderItemPrice($basketItem)['unitPrice'],
                        'currency' => $basketItems['currency']
					]
				]
			];
		}

		return $basketOrderItems;
	}

	public function getOrderItemPrice($basketItem)
	{
		return $basketItem['variation']['data']['calculatedPrices']['default'];
	}

	public function getOrderItemName($basketItem)
	{
		return $basketItem['variation']['data']['texts'];
	}
	
	public function getItemImages($basketItem)
	{
		return $basketItem['variation']['data']['images'];
	}

	/**
	 * get billing address
	 *
	 * @param Basket $basket
	 * @return Address
	 */
	public function getBillingAddress()
	{
		$basketService = pluginApp(BasketService::class);
		$addressId = $basketService->getBasket()->customerInvoiceAddressId;
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
		$basketService = pluginApp(BasketService::class);
		$customerInvoiceAddressId = !empty($customerInvoiceAddressId) ? $customerInvoiceAddressId : $basketService->getBasket()->customerShippingAddressId;
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
		$basketService = pluginApp(BasketService::class);
		$addressId = $basketService->getBasket()->customerShippingAddressId;
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
