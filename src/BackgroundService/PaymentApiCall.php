<?php
namespace Autepos\AiPayment\Providers\RemitaWallet\BackgroundService;

use Illuminate\Database\Eloquent\Model;

class PaymentApiCall extends Model{

    protected $primaryKey='pac_id';

    protected $casts = [
        'pac_request_time'=>'datetime',
        'pac_response_time'=>'datetime',
    ];
}