<?php

namespace Autepos\AiPayment\Providers\RemitaWallet\Http\Controllers;


use Illuminate\Support\Str;
use Illuminate\Http\Request;
use BethelChika\Remita\Event;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use \Autepos\AiPayment\Tenancy\Tenant;
use BethelChika\Remita\WebhookSignature;
use \Autepos\AiPayment\Contracts\PaymentProviderFactory;
use BethelChika\Remita\Exception\SignatureVerificationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider;
use Autepos\AiPayment\Providers\RemitaWallet\Events\RemitaWalletWebhookHandled;
use Autepos\AiPayment\Providers\RemitaWallet\Events\RemitaWalletWebhookReceived;


class RemitaWalletWebhookController extends Controller
{
    /**
     * @var \Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider
     */
    protected $paymentProvider;


    /**
     * Tells if webhook is being verified or not.
     *
     * @var boolean
     */
    protected $isUsingWebhookVerification=false;

    /**
     * The request from the payment provider
     *
     * @var Request
     */
    protected $request;

    


    public function __construct(PaymentProviderFactory $paymentManager)
    {
        
        /**
         * @var \Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider
         */
        $this->paymentProvider = $paymentManager->driver(RemitaWalletPaymentProvider::PROVIDER);
        
        
    }

    /**
     * Validate request only if it is possible
     *
     * @return bool
     * @throws AccessDeniedHttpException If the request fails validation
     */
    protected function validateWebhookRequestIfPossible(){
       
        //
        $config=$this->paymentProvider->getConfig();
        $endpoint_secret = $config['webhook_secret'];
        if (is_null($endpoint_secret)) {
            return;
        }

        $this->validateWebhookRequest($this->request);
        
        return true;
    }

    /**
     * Validate request
     * NOTE: Unfortunately for testing purposes, we have to make this method public
     *
     * @return bool
     * @throws AccessDeniedHttpException If the request fails validation
     */
    public function validateWebhookRequest(Request $request){
        $payload = $request->getContent();
        $sig_header = $request->header('Remita-Signature');        

        //
        $config=$this->paymentProvider->getConfig();
        
        
        $endpoint_secret = $config['webhook_secret'];

        try {
            WebhookSignature::verifyHeader(
                $payload,
                $sig_header,
                $endpoint_secret,
            );
            $this->isUsingWebhookVerification=true;
        } catch (SignatureVerificationException $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return true;
    }
    

    /**
     * Handle a Remita wallet webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param string $event_type The name of the event that was received
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request,string $event_type)
    {
        
        $payload = $request->getContent();
        $data = \json_decode($payload, true);
        $jsonError = \json_last_error();
        if (null === $data && \JSON_ERROR_NONE !== $jsonError) {

            Log::error('Error decoding Remita wallet webhook', ['payload' => $payload, 'json_last_error' => $jsonError]);
            return new Response('Invalid input', 400);
        }

        $event = \BethelChika\Remita\Event::constructFrom($data);

        //
        $this->request=$request;
        

        //
        RemitaWalletWebhookReceived::dispatch($data);

        $method = 'handle'.Str::studly(str_replace('.', '_', $event_type));
        if (method_exists($this, $method)) {

            $response = $this->{$method}($event);

            RemitaWalletWebhookHandled::dispatch($data);

            return $response;
        }

        

        return $this->missingMethod($data,$event_type);
    }

    /**
     * Handler for \BethelChika\Remita\Event::MONEY_REQUEST_PAYMENT_STATUS
     */
    protected function handleMoneyRequestPaymentStatus(Event $event):Response{
        
        $transRef=$event->reference;
        $transaction=RemitaWalletPaymentProvider::transRefToTransaction($transRef);
        
        
        if($transaction){
            $tenant_id=$transaction->{Tenant::getColumnName()};

            // Set the tenant
            RemitaWalletPaymentProvider::tenant($tenant_id);
            
            // Configure the payment provider using a callback
            $this->paymentProvider->configUsingFcn();

            // Validate request if possible
            $this->validateWebhookRequestIfPossible();
            
            // Process the request based on whether validation was performed or not. 
            if($this->isUsingWebhookVerification){
                
                $paymentResponse = $this->paymentProvider
                ->webhookCharge($event, $transaction);
            }else{
                
                $paymentResponse = $this->paymentProvider
                ->webhookChargeByRetrieval($event, $transaction);
            }


            return $this->successMethod();
        } else {
            Log::error('Remita Wallet webhook - received : money_request.payment_status - Missing transaction model:', ['event' => $event->rawValues()]);
            return response('There was an issue with processing the webhook', 404);

        }

    }
    


    /**
     * Handle successful calls on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function successMethod($parameters = [])
    {
        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $parameters
     * @param string $event_type The webhook event type that occurred
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function missingMethod($parameters = [],$event_type='unknown_event')
    {
        return response('Unknown webhook - it may not have been set up', 404);
    }
}
