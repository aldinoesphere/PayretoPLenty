<?php

namespace Payreto\Migrations;

use Payreto\Models\Database\Settings;
use Payreto\Services\Database\SettingsService;
use Payreto\Models\Database\Account;
use Payreto\Services\Database\AccountService;
use Plenty\Modules\Plugin\DataBase\Contracts\Migrate;

/**
* Migration to create Payreto configuration tables
*
* Class CreatePayretoTables
* @package Payreto\Migrations
*/
class CreatesPayretoTables_b_1_0_2
{
	/**
	 * Run on plugin build
	 *
	 * Create Payreto configuration tables.
	 *
	 * @param Migrate $migrate
	 */
	public function run(Migrate $migrate)
	{
		/**
		 * Create the settings table
		 */
		try {
			$migrate->deleteTable(Settings::class);
		}
		catch (\Exception $e)
		{
			//Table does not exist
		}

		$migrate->createTable(Settings::class);

		/**
		 * Create the account table
		 */
		try {
			$migrate->deleteTable(Account::class);
		}
		catch (\Exception $e)
		{
			//Table does not exist
		}

		$migrate->createTable(Account::class);

		// Set default payment method name in all supported languages.
		// $service = pluginApp(SettingsService::class);
		// $service->setInitialSettings();
	}
}
