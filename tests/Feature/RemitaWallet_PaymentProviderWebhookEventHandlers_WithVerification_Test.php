<?php

namespace Tests\Feature\Payment\Providers\RemitaWallet;

use Mockery;
use Illuminate\Support\Facades\Log;
use BethelChika\Remita\RemitaObject;
use Autepos\AiPayment\PaymentResponse;
use Autepos\AiPayment\Models\Transaction;
use BethelChika\Remita\Event as RemitaEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Autepos\AiPayment\Contracts\PaymentProviderFactory;
use \Autepos\AiPayment\Providers\RemitaWallet\Tests\TestCase;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider;
use Autepos\AiPayment\Providers\RemitaWallet\Http\Controllers\RemitaWalletWebhookController;

class RemitaWallet_PaymentProviderWebhookEventHandlers_WithVerification_Test extends TestCase
{
    use RefreshDatabase;
    

    private $provider = RemitaWalletPaymentProvider::PROVIDER;

    /**
     * Mock of RemitaWalletPaymentProvider
     *
     * @var \Mockery\MockInterface
     */
    private $partialMockPaymentProvider;

    /**
     * Get the instance of the payment manager
     */
    private function paymentManager(): PaymentProviderFactory
    {
        return app(PaymentProviderFactory::class);
    }


    /**
     * Get the instance of the provider directly
     */
    private function providerInstance(): RemitaWalletPaymentProvider
    {
        return new RemitaWalletPaymentProvider;
    }

    public function setUp(): void
    {
        parent::setUp();

        // Set webhook secret to enable webhook verification.
        $paymentProvider=$this->providerInstance();

        $rawConfig = $paymentProvider->getRawConfig();
        $rawConfig['webhook_secret'] = 'secret';// ensure we have verification

        // Use a controller stub to allow us to control the webhook verification
        app()->bind(RemitaWalletWebhookController::class, WebhookControllerWithVerificationTestStub::class);


        // Mock the payment provider
        $partialMockPaymentProvider=Mockery::mock(RemitaWalletPaymentProvider::class)->makePartial();
        
        $partialMockPaymentProvider->config($rawConfig,false);
        $partialMockPaymentProvider->shouldReceive('configUsingFcn')
        ->byDefault()
        ->once()
        ->andReturnSelf();
        
        
        $partialMockPaymentProvider->shouldReceive('webhookCharge')
        ->byDefault()
        ->with(Mockery::type(RemitaObject::class), Mockery::type(Transaction::class))
        ->once()
        ->andReturn(new PaymentResponse(PaymentResponse::newType('charge'), true));

        // Use the mock to replace the payment provider in the manager
        $this->paymentManager()->extend($this->provider, function () use ($partialMockPaymentProvider) {
            return $partialMockPaymentProvider;
        });

        // Now empty the manager drivers cache to ensure that our new mock will be used
        // to recreate the payment provider on the next access to the manager driver
        //$this->paymentManager()->forgetDrivers();// This method is not available in Laravel 7.0

        //
        $this->partialMockPaymentProvider=$partialMockPaymentProvider;
    }

    /**
     * Tests that when verification for the webhook is performed the correct provider method
     * is used to carry out charge.
     *
     * @return void
     */
    public function test_can_handle_money_request_payment_status_webhook_event()
    {
        $transaction=new Transaction();
        $transaction->payment_provider=$this->provider;
        $transaction->orderable_id='1';
        
        $transaction->save();
        
        
        $transRef=RemitaWalletPaymentProvider::generateTransRef($transaction);
        $transaction->transaction_family_id=$transRef;
        $transaction->save();

        // Now post an succeeded status
        $payload=[
            'reference'=>$transRef,
            'status'=>RemitaEvent::MONEY_REQUEST_PAYMENT_STATUS_SUCCEEDED,
        ];

        $webhookEndpoint=RemitaWalletPaymentProvider::webhookEndpointUrl(RemitaEvent::MONEY_REQUEST_PAYMENT_STATUS);
        
        
        
        //
        $response=$this->postJson($webhookEndpoint, $payload);
        $response->assertOk();
        $this->assertEquals('Webhook Handled', $response->getContent());
    }


    

    public function test_cannot_handle_money_request_payment_status_webhook_event_when_transaction_is_missing()
    {

        // Set a specific expectation on the payment provider partial mock
        $this->partialMockPaymentProvider->shouldNotReceive('configUsingFcn');
        $this->partialMockPaymentProvider->shouldNotReceive('webhookCharge');

        $transaction=new Transaction();
        $transaction->payment_provider=$this->provider;
        $transaction->orderable_id='1';
        $transaction->save();
    
        $transRef=RemitaWalletPaymentProvider::generateTransRef($transaction);
        $transaction->transaction_family_id=$transRef;
        $transaction->save();

        // Now post an succeeded status
        $payload=[
            'reference'=>'not-transaction_id',
            'status'=>RemitaEvent::MONEY_REQUEST_PAYMENT_STATUS_SUCCEEDED,
        ];

        //
        Log::shouldReceive('error')
        ->once();

        //
        $webhookEndpoint=RemitaWalletPaymentProvider::webhookEndpointUrl(RemitaEvent::MONEY_REQUEST_PAYMENT_STATUS);
        
        $response=$this->postJson($webhookEndpoint, $payload);
        
        $response->assertStatus(404);
        $this->assertEquals('There was an issue with processing the webhook', $response->getContent());
    }
}

class WebhookControllerWithVerificationTestStub extends RemitaWalletWebhookController
{

    public function validateWebhookRequest($request){
        // prevent verification from  running and say that verification has 
        // be performed
        $this->isUsingWebhookVerification=true;
        
    }
    

}