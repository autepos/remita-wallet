<?php
namespace Tests\Feature\Payment\Providers\RemitaWallet;

use Mockery;
use Autepos\AiPayment\ResponseType;
use Autepos\AiPayment\PaymentResponse;
use Autepos\AiPayment\Models\Transaction;
use Autepos\AiPayment\Contracts\CustomerData;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Autepos\AiPayment\Providers\Contracts\Orderable;
use Autepos\AiPayment\Models\PaymentProviderCustomer;
use Autepos\AiPayment\Providers\Contracts\PaymentProvider;
use Autepos\AiPayment\Providers\RemitaWallet\Tests\TestCase;
use Autepos\AiPayment\Models\PaymentProviderCustomerPaymentMethod;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletCustomer;
use Autepos\AiPayment\Tests\ContractTests\PaymentProviderContractTest;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider;

/**
 * 
 */
class RemitaWalletPaymentProviderTest extends TestCase{

    use RefreshDatabase;
    use PaymentProviderContractTest;

    private $provider=RemitaWalletPaymentProvider::PROVIDER;

    private function providerInstance(){

        return (new RemitaWalletPaymentProvider)->config(static::$remita_config);
    }

    public function createContract():PaymentProvider{
        return $this->providerInstance();
    }

    private function createProviderCustomer(CustomerData $customerData):PaymentProviderCustomer{
        
        return PaymentProviderCustomer::factory()->create([
            'user_type'=>$customerData->user_type,
            'user_id'=>$customerData->user_id,
            'payment_provider'=>$this->provider,
            'payment_provider_customer_id'=>RemitaWalletCustomer::generateCustomerId($customerData),
        ]);
    }

    public function createProviderPaymentMethod(CustomerData $customerData):PaymentProviderCustomerPaymentMethod{
        $providerCustomer=$this->createProviderCustomer($customerData);
        $providerPaymentMethod= PaymentProviderCustomerPaymentMethod::factory()->make([

            // TODO: Instead of using a precreated wallet_id (i.e REMITA_TEST_WALLET_ID) it may be better to create a new wallet id for every test run???
            'payment_provider_payment_method_id'=>env('REMITA_TEST_WALLET_ID'),

            //
            'payment_provider'=>$providerCustomer->payment_provider,
        ]);

        $providerCustomer->paymentMethods()->save($providerPaymentMethod);
        return $providerPaymentMethod;
    }

    public function test_can_cashier_init_payment(): Transaction
    {

        $customerData=new CustomerData(['user_type' => 'test-user', 'user_id' => '1', 'email' => 'test@test.com']);
        $this->createProviderPaymentMethod($customerData);// Precreate a wallet that will be used under the hood.


        //
        $amount = 1000;

        /**
         * @var \Autepos\AiPayment\Providers\Contracts\Orderable
         */
        $mockOrder = Mockery::mock(Orderable::class);
        $mockOrder->shouldReceive('getAmount')
            ->atLeast()
            ->once()
            ->andReturn($amount);

        $mockOrder->shouldReceive('getKey')
            ->atLeast()
            ->once()
            ->andReturn(1);

        $mockOrder->shouldReceive('getCurrency')
            ->atLeast()
            ->once()
            ->andReturn('NGN');

        $mockOrder->shouldReceive('getCustomer')
            ->atLeast()
            ->once()
            ->andReturn($customerData);

        $mockOrder->shouldReceive('getDescription')
            //->once() // Uncomment out to make calling getDescription mandatory.
            ->andReturn('test_can_cashier_init_payment');
        /**
         * @var \Illuminate\Contracts\Auth\Authenticatable
         */
        $mockCashier = Mockery::mock(Authenticatable::class);
        $mockCashier->shouldReceive('getAuthIdentifier')
            ->atLeast()
            ->once()
            ->andReturn(1);

        $providerInstance=$this->createContract();
        $response = $providerInstance
            ->order($mockOrder)
            ->cashierInit($mockCashier, null);

        $this->assertInstanceOf(PaymentResponse::class, $response);
        $this->assertEquals(ResponseType::TYPE_INIT, $response->getType()->getName());
        $this->assertTrue($response->success);

        $this->assertInstanceOf(Transaction::class, $response->getTransaction());
        $this->assertEquals($providerInstance->getProvider(), $response->getTransaction()->payment_provider);
        $this->assertEquals($amount, $response->getTransaction()->orderable_amount);
        $this->assertEquals(1, $response->getTransaction()->orderable_id);
        $this->assertEquals(1, $response->getTransaction()->cashier_id);
        $this->assertTrue($response->getTransaction()->exists, 'Failed asserting that transaction not stored');

        return $response->getTransaction();
    }
    public function test_can_cashier_init_split_payment()
    {

        $customerData=new CustomerData(['user_type' => 'test-user', 'user_id' => '1', 'email' => 'test@test.com']);
        $this->createProviderPaymentMethod($customerData);// Precreate a wallet that will be used under the hood.


        //
        $amount = 1000;

        /**
         * @var \Autepos\AiPayment\Providers\Contracts\Orderable
         */
        $mockOrder = Mockery::mock(Orderable::class);
        $mockOrder->shouldReceive('getKey')
            ->atLeast()
            ->once()
            ->andReturn(1);

        $mockOrder->shouldReceive('getCurrency')
            ->atLeast()
            ->once()
            ->andReturn('gbp');

        $mockOrder->shouldReceive('getCustomer')
            ->atLeast()
            ->once()
            ->andReturn($customerData);

        $mockOrder->shouldReceive('getDescription')
            //->once() // Uncomment out to make calling getDescription mandatory.
            ->andReturn('test_can_cashier_init_payment');

        /**
         * @var \Illuminate\Contracts\Auth\Authenticatable
         */
        $mockCashier = Mockery::mock(Authenticatable::class);
        $mockCashier->shouldReceive('getAuthIdentifier')
            ->atLeast()
            ->once()
            ->andReturn(1);

        $providerInstance=$this->createContract();
        $response = $providerInstance
            ->order($mockOrder)
            ->cashierInit($mockCashier, $amount);

        $this->assertInstanceOf(PaymentResponse::class, $response);
        $this->assertEquals(ResponseType::TYPE_INIT, $response->getType()->getName());
        $this->assertTrue($response->success);

        $this->assertInstanceOf(Transaction::class, $response->getTransaction());
        $this->assertEquals($providerInstance->getProvider(), $response->getTransaction()->payment_provider);
        $this->assertEquals($amount, $response->getTransaction()->orderable_amount);
        $this->assertTrue($response->getTransaction()->exists, 'Failed asserting that transaction not stored');
    }

    public function test_can_customer_init_payment(): Transaction
    {
        $this->markTestSkipped('Customer payment is not implemented yet');
        return new Transaction();// return a dummy
    }

    public function test_can_customer_init_split_payment()
    {
        $this->markTestSkipped('Customer payment is not implemented yet');
    }

    public function test_can_cashier_charge_payment(){
        $this->markTestSkipped('It is currently possible to mark transaction as paid at remita during test. This must be possible for us to make a charge in test mode.');
    }

    public function test_can_cashier_refund_payment()
    {
        $this->markTestSkipped('Refund is not implemented yet');
    }

    public function test_can_cashier_refund_part_of_payment()
    {
        $this->markTestSkipped('Refund is not implemented yet');
    }
}