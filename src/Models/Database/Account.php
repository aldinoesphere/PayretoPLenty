<?php

namespace Payreto\Models\Database;

use Plenty\Modules\Plugin\DataBase\Contracts\Model;

/**
 * Class Account
 *
 * @property int $id
 * @property string $name
 * @property array $value
 * @property string $createdAt
 * @property string $updatedAt
 */
class Account extends Model
{
    public $id = 0;
    public $customerId = 0;
    public $paymentGroup = '';
    public $brand = '';
    public $holder = '';
    public $email = '';
    public $last4digits = '';
    public $expMonth = '';
    public $expYear = '';
    public $serverMode = '';
    public $channelId = '';
    public $refId = '';
    public $paymentDefault = '';
    public $createdAt = '';
    public $updatedAt = '';

    /**
     * @return string
     */
    public function getTableName():string
    {
        return 'Payreto::accounts';
    }
}