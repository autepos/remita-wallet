<?php
namespace BethelChika\Remita;
/**
 * @property int $id id of the money request.
 * @property string $accountNumber beneficiary account number
 * @property float $amount the requested amount in currency lowest subunit
 * @property string $channel
 * @property string $sourceBankCode
 * @property string $sourceAccountNumber account number of the payer
 * @property string $sourceAccountName
 * @property string $destBankCode
 * @property string $pin
 * @property string $transRef a unique reference for the money request transaction.
 * @property string $phoneNumber
 * @property string $narration 
 * @property string $shortComment 
 * @property string $beneficiaryName
 * @property string $rrr
 * @property string $status the status of the transaction
 * @property string $createdDate datetime string of the created date
 * @property string $agentRef
 * @property string $redeemBonus
 * @property boolean $bonusAmount
 * @property string $charges
 * @property string $bulkAccountNos
 * @property string $transactionDescription
 * @property boolean $walletAccount
 * @property boolean $toBeSaved
 * @property boolean $bulkTrans
 * @property boolean $multipleCredit
 */
class MoneyRequest extends RemitaObject{

    const OBJECT_NAME = 'money_request';


    /**
     * Payment has not been made.
     */
    const STATUS_INCOMPLETE='INCOMPLETE';

    /**
     * Payment has been made for a wallet to wallet transaction.
     */
    const STATUS_SUCCEEDED_OK='OK';

    /**
     * Payment has been made for a wallet to bank transaction.
     */
    const STATUS_SUCCEEDED_COMPLETE='COMPLETE';

    /**
     * Check if the Money has been paid regardless of the transaction type(i.e wallet-to-bank, etc)
     *
     * @return boolean
     */
    public function hasSucceeded(){
        
        return (
                ($this->status==static::STATUS_SUCCEEDED_COMPLETE) 
                or ($this->status==static::STATUS_SUCCEEDED_OK)
            )? true:false;
    }
}
