<?php
namespace Tests\Feature\Payment\Providers\RemitaWallet;

use Illuminate\Http\Request;
use Autepos\AiPayment\Contracts\PaymentProviderFactory;
use \Autepos\AiPayment\Providers\RemitaWallet\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider;
use Autepos\AiPayment\Providers\RemitaWallet\Http\Controllers\RemitaWalletWebhookController;

/**
 * TODO: this tests are currently a sample- it should be replace when Remita confirms how their verification works
 */
class RemitaWalletVerifyWebhookSignatureTest extends TestCase
{
    

    private $provider = RemitaWalletPaymentProvider::PROVIDER;

    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;



    /**
     * Webhook endpoint secret
     *
     * @var string
     */
    private $webhookEndpointSecret='secret';

        /**
     * Get the instance of the payment manager
     */
    private function paymentManager(): PaymentProviderFactory
    {
        return app(PaymentProviderFactory::class);

    }

    /**
     * Get the instance of the provider by resolving it from the 
     * container. i.e how the base app will use it
     */
    private function resolveProvider(): RemitaWalletPaymentProvider
    {
        $paymentManager = $this->paymentManager();
        $paymentProvider = $paymentManager->driver($this->provider);
        return $paymentProvider;
    }

    public function setUp(): void
    {
        parent::setUp();

        

        // Set the config on the payment provider
        $paymentProvider=$this->resolveProvider();
        $rawConfig = $paymentProvider->getRawConfig();
        $rawConfig['webhook_secret'] = $this->webhookEndpointSecret;
        $paymentProvider->config($rawConfig);

        
        //
        $this->request = new Request([], [], [], [], [], [], 'Signed Body');
    }

    public function test_true_is_returned_when_secret_matches()
    {
        
        $this->withSignedSignature($this->webhookEndpointSecret);

        $result = (new RemitaWalletWebhookController($this->paymentManager()))
            ->validateWebhookRequest($this->request);

        $this->assertTrue($result);

    }





    public function test_app_aborts_when_secret_does_not_match()
    {

        $this->withSignature('fail');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Failed verification');


        $result = (new RemitaWalletWebhookController($this->paymentManager()))
            ->validateWebhookRequest($this->request);
    }





    public function withSignedSignature($secret)
    {
        return $this->withSignature(
            $this->sign($this->request->getContent(), $secret)
        );
    }

    public function withSignature($signature)
    {
        // 'Remita-Signature' becomes $_SERVER['HTTP_REMITA_SIGNATURE'] in the request
        $this->request->headers->set('Remita-Signature', $signature);

        return $this;
    }

    private function sign($payload, $secret)
    {
        return hash_hmac('sha256', $payload, $secret);
    }
}