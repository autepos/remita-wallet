<?php
namespace Tests\Feature\Payment\Providers\RemitaWallet;


use Mockery;
use BethelChika\Remita\Event;
use Autepos\AiPayment\ResponseType;
use BethelChika\Remita\MoneyRequest;
use Autepos\AiPayment\PaymentResponse;
use Autepos\AiPayment\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use \Autepos\AiPayment\Providers\RemitaWallet\Tests\TestCase;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider;

class RemitaWallet_PaymentProviderWebhookChargeByRetrieval_Test extends TestCase{
    use RefreshDatabase;




    private $provider = RemitaWalletPaymentProvider::PROVIDER;


    public function test_can_charge_money_request_from_webhook(){
        $amount=1000;
        Transaction::unguard();
        $transaction=Transaction::create([
            'payment_provider'=>$this->provider,
            'orderable_id'=>1,
            'orderable_amount'=>$amount,
            'transaction_family'=>Transaction::TRANSACTION_FAMILY_PAYMENT,
            'transaction_family_id'=>null,//We will set this after we got an payment intent
        ]);
        $transaction->transaction_family_id=RemitaWalletPaymentProvider::generateTransRef($transaction);
        

        $moneyRequest=MoneyRequest::constructFrom([
            'status'=>MoneyRequest::STATUS_SUCCEEDED_COMPLETE,
            'transRef'=>$transaction->transaction_family_id,
        ]);

        
        // Create the webhook status event for the money request
        $event=Event::constructFrom([
            'reference'=>$transaction->transaction_family_id,
            'status'=>Event::MONEY_REQUEST_PAYMENT_STATUS_SUCCEEDED,
        ]);

        //
        $mockPaymentProvider=Mockery::mock(RemitaWalletPaymentProvider::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();
        $mockPaymentProvider->shouldReceive('retrieveMoneyRequest')
        ->once()
        ->andReturn($moneyRequest);

        $response=$mockPaymentProvider->webhookChargeByRetrieval($event,$transaction);

        $this->assertInstanceOf(PaymentResponse::class, $response);
        $this->assertEquals(ResponseType::TYPE_CHARGE, $response->getType()->getName());
        $this->assertTrue($response->success);

        $this->assertInstanceOf(Transaction::class, $response->getTransaction());
        $this->assertEquals($this->provider, $response->getTransaction()->payment_provider);
        $this->assertTrue($response->getTransaction()->success);
        
        $this->assertEquals($transaction->orderable_amount, $response->getTransaction()->orderable_amount);
        
        $this->assertEquals($transaction->orderable_amount, $response->getTransaction()->amount);
        
        $this->assertEquals($transaction->orderable_id, $response->getTransaction()->orderable_id);

        $this->assertDatabaseHas((new Transaction)->getTable(),['id'=>$response->getTransaction()->id]);
    }

    public function test_can_charge_money_request_from_webhook_when_transaction_is_missing(){
        $amount=1000;
        $missing_transaction_id=20000;
        $missing_trans_ref='TR-'.$missing_transaction_id.'-IDVHOIH';


        $moneyRequest=MoneyRequest::constructFrom([
            'status'=>MoneyRequest::STATUS_SUCCEEDED_COMPLETE,
            'transRef'=>$missing_trans_ref,
            'amount'=>$amount,
        ]);

        
        // Create the webhook status event for the money request
        $event=Event::constructFrom([
            'reference'=>$missing_trans_ref,
            'status'=>Event::MONEY_REQUEST_PAYMENT_STATUS_SUCCEEDED,
        ]);


        

        $mockPaymentProvider=Mockery::mock(RemitaWalletPaymentProvider::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();
        $mockPaymentProvider->shouldReceive('retrieveMoneyRequest')
        ->once()
        ->andReturn($moneyRequest);

        $response=$mockPaymentProvider->webhookChargeByRetrieval($event,null);

        $this->assertInstanceOf(PaymentResponse::class, $response);
        $this->assertEquals(ResponseType::TYPE_CHARGE, $response->getType()->getName());
        
       
        $this->assertTrue($response->success);

        $this->assertInstanceOf(Transaction::class, $response->getTransaction());
        $this->assertEquals($this->provider, $response->getTransaction()->payment_provider);
        $this->assertTrue($response->getTransaction()->success);
        //$this->assertEquals($amount, $response->getTransaction()->orderable_amount);
        
        $this->assertEquals($amount, $response->getTransaction()->amount);
        
        $this->assertDatabaseHas((new Transaction)->getTable(),['id'=>$response->getTransaction()->id]);
    }

    public function test_cannot_charge_unsuccessful_money_request_from_webhook(){
        $amount=1000;
        Transaction::unguard();
        $transaction=Transaction::create([
            'payment_provider'=>$this->provider,
            'orderable_id'=>1,
            'orderable_amount'=>$amount,
            'transaction_family'=>Transaction::TRANSACTION_FAMILY_PAYMENT,
            'transaction_family_id'=>null,//We will set this after we got an payment intent
        ]);
        $transaction->transaction_family_id=RemitaWalletPaymentProvider::generateTransRef($transaction);

        //
        $moneyRequest=MoneyRequest::constructFrom([
            'status'=>MoneyRequest::STATUS_INCOMPLETE,
            'transRef'=>$transaction->transaction_family_id,
        ]);

        
        // Create the webhook status event for the money request
        $event=Event::constructFrom([
            'reference'=>$transaction->transaction_family_id,
            'status'=>'we dont know what status remita gives this yet',
        ]);


        //
        $mockPaymentProvider=Mockery::mock(RemitaWalletPaymentProvider::class)->makePartial()
        ->shouldAllowMockingProtectedMethods();
        $mockPaymentProvider->shouldReceive('retrieveMoneyRequest')
        ->once()
        ->andReturn($moneyRequest);
        $response=$mockPaymentProvider->webhookChargeByRetrieval($event,$transaction);

        $this->assertInstanceOf(PaymentResponse::class, $response);
        $this->assertEquals(ResponseType::TYPE_CHARGE, $response->getType()->getName());
        $this->assertFalse($response->success);
    }


}