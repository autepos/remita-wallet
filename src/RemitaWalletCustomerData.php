<?php
namespace Autepos\AiPayment\Providers\RemitaWallet;

use Autepos\AiPayment\Contracts\CustomerData;

class RemitaWalletCustomerData extends CustomerData
{       
    /**
    * The gender
    *
    * @var string
    */
   protected $gender;

   /**
    * The date of birth
    *
    * @var string
    */
   protected $date_of_birth;
}