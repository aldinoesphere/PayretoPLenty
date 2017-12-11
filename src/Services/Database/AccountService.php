<?php

namespace Payreto\Services\Database;

use Plenty\Modules\Plugin\DataBase\Contracts\DataBase;
use Plenty\Modules\System\Contracts\WebstoreRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use Payreto\Models\Database\Account;
use Payreto\Helper\PaymentHelper;

/**
* Class SettingsService
* @package Payreto\Services\Database
*/
class AccountService extends DatabaseBaseService
{
    use Loggable;

    /**
     * SettingsService constructor.
     * @param DataBase $dataBase
     */
    public function __construct(DataBase $dataBase)
    {
        parent::__construct($dataBase);
    }

    /**
     * load the settings by parameters given
     *
     * @param string $webstore
     * @param string $mode
     * @return array|null
     */
    public function loadAccount($customerId, $settingType)
    {
        $database = pluginApp(DataBase::class);
        $account = $database->query(Account::class)
                    ->where('customerId', '=', $customerId)
                    ->where('settingType', '=', $settingType)
                    ->get();
        return $account;
    }

    /**
     * load the settings
     *
     * @param string $settingType
     * @return array
     */
    public function loadAccounts($customerId)
    {
        $database = pluginApp(DataBase::class);
        $accounts = $database->query(Account::class)->where('customerId', '=', $customerId)->get();
        return $accounts;
    }

    /**
     * save the settings
     *
     * @param array $settings
     * @return bool
     */
    public function saveAccount($accounts)
    {
        if ($accounts)
        {
            foreach ($accounts as $account)
            {
                $accountModel = pluginApp(Account::class);
                $database = pluginApp(DataBase::class);

                $accountModel->customerId = $account['customerId'];
                $accountModel->settingType = $account['settingType'];
                $accountModel->paymentGroup = $account['paymentGroup'];
                $accountModel->brand = $account['brand'];
                $accountModel->holder = $account['holder'];
                $accountModel->last4digits = $account['last4digits'];
                $accountModel->expMonth = $account['expMonth'];
                $accountModel->expYear = $account['expYear'];
                $accountModel->serverMode = $account['serverMode'];
                $accountModel->channelId = $account['channelId'];
                $accountModel->refId = $account['refId'];
                $accountModel->paymentDefault = $account['paymentDefault'];
                $accountModel->updatedAt = date('Y-m-d H:i:s');
                    
                $database->save($accountModel);
            }
            return 1;
        }
    }

    /**
     * get Payreto configuration by plentyId and settingType
     *
     * @param string $plentyId
     * @param string $settingType
     * @return array
     */
    public function getConfiguration($plentyId, $settingType)
    {
        $paymentHelper = pluginApp(PaymentHelper::class);
        $url = $paymentHelper->getDomain() . '/rest/payment/payreto/account/' . $plentyId . '/' . $settingType;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $_COOKIE['accessToken']));

        $response = curl_exec($curl);
        if (curl_errno($curl))
        {
            $this->getLogger(__METHOD__)->error('Payreto:error', curl_error($curl));
            throw new \Exception(curl_error($curl));
        }
        curl_close($curl);

        return json_decode($response, true);
    }

    /**
     * save Payreto configuration to database
     *
     * @param string $parameters
     * @return boolean
     */
    public function saveConfiguration($parameters)
    {
        $paymentHelper = pluginApp(PaymentHelper::class);
        $postFields = json_encode($parameters);

        $url = $paymentHelper->getDomain() . '/rest/payment/payreto/account/';
        $header = array(
            'Content-type: application/json',
            'Authorization: Bearer ' . $_COOKIE['accessToken']
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postFields);

        $response = curl_exec($curl);
        if (curl_errno($curl))
        {
            $this->getLogger(__METHOD__)->error('Payreto:error', curl_error($curl));
            return 0;
        }
        curl_close($curl);

        return $response;
    }
}
