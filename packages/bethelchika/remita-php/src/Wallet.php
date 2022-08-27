<?php
namespace BethelChika\Remita;
/**
 * @property int $id object id
 * @property string $accountNumber the account number of the wallet which can be used for money request
 * @property int $currentBalance
 * @property string $dateOpened
 * @property int $schemeId
 * @property int $walletAccountTypeId
 * @property int $accountOwnerId
 * @property string $accountOwnerName
 * @property string $accountOwnerPhoneNumber
 * @property string $accountName
 * @property string $status
 * @property float $actualBalance
 * @property float $walletLimit
 * @property string $trackingRef
 * @property string $nubanAccountNo
 * @property string $accountFullName
 * @property float $totalCustomerBalances
 */
class Wallet extends RemitaObject{

    const OBJECT_NAME = 'wallet';

}
