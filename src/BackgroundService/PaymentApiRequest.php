<?php
namespace Autepos\AiPayment\Providers\RemitaWallet\BackgroundService;

use Illuminate\Database\Eloquent\Model;

class PaymentApiRequest extends Model{

    protected $primaryKey='par_id';

    protected $casts = [
        'par_start_time'=>'datetime',
        'par_end_time'=>'datetime',
    ];

    /**
     * Relation with payment_api_calls for CREATE_WALLET
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function createWalletPaymentApiCall(){
        $foreign_tbl=(new PaymentApiCall)->getTable();
        return $this->hasOne(PaymentApiCall::class,'pac_request_id','par_id')
        ->where($foreign_tbl.'.pac_request_name','CREATE_WALLET');
    }

    /**
     * Relation with payment_api_calls for REQUEST_MONEY
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function requestMoneyPaymentApiCall(){
        $foreign_tbl=(new PaymentApiCall)->getTable();
        return $this->hasOne(PaymentApiCall::class,'pac_request_id','par_id')
        ->where($foreign_tbl.'.pac_request_name','REQUEST_MONEY');
    }
}