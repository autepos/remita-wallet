<?php
namespace Tests\Feature\Payment\Providers\RemitaWallet;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Autepos\AiPayment\Providers\Contracts\ProviderCustomer;
use Autepos\AiPayment\Providers\RemitaWallet\Tests\TestCase;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletCustomer;
use Autepos\AiPayment\Tests\ContractTests\ProviderCustomerContractTest;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider;

/**
 * 
 */
class RemitaWalletCustomerTest extends TestCase{

    use RefreshDatabase;
    use ProviderCustomerContractTest;

    private $provider=RemitaWalletPaymentProvider::PROVIDER;

    private function providerInstance(){

        $paymentProvider= (new RemitaWalletPaymentProvider)->config(static::$remita_config);
        return (new RemitaWalletCustomer)->provider($paymentProvider);
    }

    public function createContract():ProviderCustomer{
        return $this->providerInstance();
    }

    
}