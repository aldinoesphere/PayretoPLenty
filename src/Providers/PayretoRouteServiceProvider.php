<?php  
namespace Payreto\Providers;

use Plenty\Plugin\RouteServiceProvider;
use Plenty\Plugin\Routing\Router;
use Plenty\Plugin\Routing\ApiRouter;


class PayretoRouteServiceProvider extends RouteServiceProvider
{
	public function map(Router $router, ApiRouter $apiRouter) {

		$apiRouter->version(
			['v1'],
			['namespace' => 'Payreto\Controllers', 'middleware' => 'oauth'],
			function ($apiRouter) {
				$apiRouter->post('payment/payreto/settings/', 'SettingsController@saveSettings');
				$apiRouter->post('payment/payreto/account/', 'AccountController@saveAccounts');
				$apiRouter->get('payment/payreto/settings/{settingType}', 'SettingsController@loadSettings');
				$apiRouter->get('payment/payreto/setting/{plentyId}/{settingType}', 'SettingsController@loadSetting');
			}
		);

		// Routes for display General settings
		$router->get('payreto/settings/{settingType}','Payreto\Controllers\SettingsController@loadConfiguration');

		// Routes for display My Payment Information
		$router->get('my-payment-information','Payreto\Controllers\MyPaymentInformationController@show');

		// Routes for display My Payment Information
		$router->get('add-account/{paymentMethod}','Payreto\Controllers\MyPaymentInformationController@addAccount');

		// Routes for 
		$router->post('payreto/settings/save','Payreto\Controllers\SettingsController@saveConfiguration');

		// Routes for Payreto payment widget
		$router->get('payment/payreto/confirmation/{id?}', 'Payreto\Controllers\PaymentController@handleConfirmation');

		// Routes for Payreto payment widget
		$router->get('payment/payreto/account/{customerId}', 'Payreto\Controllers\AccountController@loadAccounts');

		$router->get('payment/payreto/confirmation/account/delete/{id}', 'Payreto\Controllers\MyPaymentInformationController@deleteAccountConfirmation');

		$router->post('payment/payreto/do/delete', 'Payreto\Controllers\PaymentResultController@postProcess');

		$router->get('payment/payreto/pay-register/{id}/{paymentKey}/{refrenceId}/', 'Payreto\Controllers\PaymentResultController@handleReturnRegister');

		// Routes for Payreto order confirmation
		$router->get('payment/payreto/order-confirmation/{id?}', 'Payreto\Controllers\PaymentController@orderConfirmation');

		// Routes for Payreto payment widget
		$router->get('payment/payreto/pay/{id}', 'Payreto\Controllers\PaymentController@handlePayment');

		// Routes for Payreto status_url
		$router->get('payment/payreto/status', 'Payreto\Controllers\PaymentNotificationController@handleStatus');

		// Routes for Payreto payment return
		$router->get('payment/payreto/return/{id}/', 'Payreto\Controllers\PaymentController@handleReturn'); 
	}
}

?>