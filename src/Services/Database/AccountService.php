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
                    ->where('paymentGroup', '=', $settingType)
                    ->get();
        return $account;
    }

    public function loadAccountByRefId($refId)
    {
        $database = pluginApp(DataBase::class);
        $account = $database->query(Account::class)
                    ->where('refId', '=', $refId)
                    ->get();
        return $account;
    }

    public function loadAccountById($id)
    {
        $database = pluginApp(DataBase::class);
        $account = $database->query(Account::class)
                    ->where('id', '=', $id)
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
            $accountModel = pluginApp(Account::class);
            $database = pluginApp(DataBase::class);

            $accountModel->customerId = $accounts['customerId'];
            $accountModel->paymentGroup = $accounts['paymentGroup'];
            $accountModel->brand = $accounts['brand'];
            $accountModel->email = $accounts['email'];
            $accountModel->holder = $accounts['holder'];
            $accountModel->last4digits = $accounts['last4digits'];
            $accountModel->expMonth = $accounts['expMonth'];
            $accountModel->expYear = $accounts['expYear'];
            $accountModel->serverMode = $accounts['serverMode'];
            $accountModel->channelId = $accounts['channelId'];
            $accountModel->refId = $accounts['refId'];
            $accountModel->paymentDefault = $accounts['paymentDefault'];
            $accountModel->updatedAt = date('Y-m-d H:i:s');
            $this->getLogger(__METHOD__)->error('Payreto:accountModel', $accountModel);
            $this->getLogger(__METHOD__)->error('Payreto:accounts', $accounts);
                
            $database->save($accountModel);
            return 1;
        }
    }

    public function deleteAccount($id)
    {
        $database = pluginApp(DataBase::class);

        $query = $this->loadAccountById($id);

        $account = $query[0];
        $delete = $database->delete($account);

        return $delete;
    }

    public function updateAccount($refId, $accounts)
    {
        /**
         * @var DataBase $database
         */
        $database = pluginApp(DataBase::class);

        $resultAccount = $this->loadAccountByRefId($refId);
        $accountModel = $resultAccount[0];
        $accountModel->customerId = $accounts['customerId'];
        $accountModel->paymentGroup = $accounts['paymentGroup'];
        $accountModel->brand = $accounts['brand'];
        $accountModel->email = $accounts['email'];
        $accountModel->holder = $accounts['holder'];
        $accountModel->last4digits = $accounts['last4digits'];
        $accountModel->expMonth = $accounts['expMonth'];
        $accountModel->expYear = $accounts['expYear'];
        $accountModel->serverMode = $accounts['serverMode'];
        $accountModel->channelId = $accounts['channelId'];
        $accountModel->refId = $accounts['refId'];
        $accountModel->paymentDefault = $accounts['paymentDefault'];
        $accountModel->updatedAt = date('Y-m-d H:i:s');
        $this->getLogger(__METHOD__)->error('Payreto:accountModel', $accountModel);
        $this->getLogger(__METHOD__)->error('Payreto:accounts', $accounts);
            
        $database->save($accountModel);
        return 1;
    }

}
