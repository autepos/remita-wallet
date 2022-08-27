<?php
namespace Tests\Feature\Payment\Providers\RemitaWallet;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Autepos\AiPayment\Providers\RemitaWallet\Tests\TestCase;
use Autepos\AiPayment\Providers\Contracts\ProviderPaymentMethod;
use Autepos\AiPayment\Models\PaymentProviderCustomerPaymentMethod;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletCustomerData;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentMethod;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider;
use Autepos\AiPayment\Tests\ContractTests\ProviderPaymentMethodContractTest;

/**
 * 
 */
class RemitaWalletPaymentMethodTest extends TestCase{

    use RefreshDatabase;
    use ProviderPaymentMethodContractTest;

    private $provider=RemitaWalletPaymentProvider::PROVIDER;

    private function providerInstance(){
        
        $paymentProvider= (new RemitaWalletPaymentProvider)->config(static::$remita_config);
        $customerData=new RemitaWalletCustomerData([
            'user_type' => 'test-user', 'user_id' => '1', 'email' => 'test@test.com',
            'phone'=>'087'.rand(10000000,99999999),
            'first_name'=>'first_name',
            'last_name'=>'last_name',
            'email'=>'test@test.com',
            'gender'=>strtoupper('female'), 
            'date_of_birth'=>'1970-01-06',
        ]);

        return (new RemitaWalletPaymentMethod)
        ->provider($paymentProvider)
        ->customerData($customerData);
    }

    public function createContract():ProviderPaymentMethod{
        return $this->providerInstance();
    }

    public function paymentMethodDataForSave():array{
        return [

        ];
    }
    
    public function test_can_add_existing_payment_method(){
        $phone='0786452437456';
        $wallet_id='029013'.'9123';

        $paymentMethod=$this->providerInstance()->addExisting($wallet_id,$phone);

       

        $this->assertInstanceOf(PaymentProviderCustomerPaymentMethod::class,$paymentMethod);
        $this->assertTrue($paymentMethod->exists);
        $this->assertEquals($phone,$paymentMethod->meta['phone']);
        $this->assertEquals('Remita wallet',$paymentMethod->brand);
        $this->assertEquals('9123',$paymentMethod->last_four);
        $this->assertFalse($paymentMethod->livemode);
        $this->assertEquals($this->provider,$paymentMethod->payment_provider);

        //
        $customerData=$this->providerInstance()->getCustomerData();
        $this->assertEquals($customerData->user_id,$paymentMethod->customer->user_id);
        $this->assertEquals($customerData->user_type,$paymentMethod->customer->user_type);

    }
}