<?php
namespace Autepos\AiPayment\Providers\RemitaWallet\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\URL;
use BethelChika\Remita\MoneyRequest;
use BethelChika\Remita\RemitaClient;
use BethelChika\Remita\RemitaObject;
use \Autepos\AiPayment\Tenancy\Tenant;
use \Autepos\AiPayment\Models\Transaction;

trait PaymentProviderUtils{
    
    /**
     * Get raw payment  config.
     *
     */
    public function getRawConfig(): array
    {
        return $this->config;
        //return count($this->config) ? $this->config : config('services.'.static::PROVIDER,[]);
    }

    /**
     * Get payment  config.
     *
     */
    public function getConfig(): array
    {

        $configurations=$this->getRawConfig();


        // if specific config keys used by the non-background service are not set we can 
        // try to read them from the config for the background service. e.g if 
        // account_number is missing we try accountNumber (b/s Lawal uses accountNumber instead of account_number for the config).
        $configurations['test_username'] = $configurations['test_username'] ?? $configurations['username']??null;
        
        $configurations['test_password'] = $configurations['test_password'] ?? $configurations['password']??null;
        $configurations['test_account_number'] = $configurations['test_account_number'] ?? $configurations['account_number'] ?? $configurations['accountNumber']??null;
        $configurations['test_scheme'] = $configurations['test_scheme'] ?? $configurations['scheme'] ?? $configurations['schemeid']??null;
        $configurations['test_api_base'] = $configurations['test_api_base'] ?? $configurations['api_base']??null;
        
        $configurations['account_number'] = $configurations['account_number'] ?? $configurations['accountNumber']??null;
        $configurations['scheme'] = $configurations['scheme'] ?? $configurations['schemeid']??null;
        
        $configurations['in_background'] = $configurations['in_background'] ?? 0;

        $configurations['webhook_secret'] = $configurations['webhook_secret'] ?? null;
        
        
        
        //
        if (!$this->isLivemode()) {
            // copy the test data since we are not in live mode
            foreach($configurations as $key=>$val){
                if(strpos($key,'test_')!==false){
                    $key_live=substr($key,strlen('test_'));
                    $configurations_live[$key_live]=$val;
                }
            }
            $configurations=array_merge($configurations,$configurations_live);

            // $configurations['username'] = $configurations['test_username'];
            // $configurations['password'] = $configurations['test_password'];
            // $configurations['account_number'] = $configurations['test_account_number'];
            // $configurations['scheme'] = $configurations['test_scheme'];
            // $configurations['api_base'] = $configurations['test_api_base'];
        }

        // Remove the test configs, but this is not necessary
        // $configurations=array_filter($configurations,function($config_name){
        //     return !in_array($config_name,[
        //         'test_username',
        //         'test_password',
        //         'test_account_number',
        //         'test_scheme',
        //         'test_api_base',
        //     ]);
        // },\ARRAY_FILTER_USE_KEY);


        //
        return $configurations;

    }


    /**
     * Get the Remita SDK client.
     *
     * @param boolean $authenticate Indicate if the client should be authenticated
     */
    public function client(array $options = [],$authenticate=true):RemitaClient
    { 
        if(count($options)){
            return new RemitaClient($options);
        }
        $config=$this->getConfig();
        return new RemitaClient([
            'username'=>$config['username'],
            'password'=>$config['password'],
            'scheme'=>$config['scheme'],
            'livemode'=>$this->isLivemode(),
            'auto_authenticate'=>$authenticate,
        ]);
    }

    /**
     * A convenient helper to get an unauthenticated client
     *
     */
    public function clientWithoutAuthentication():RemitaClient{
        return $this->client([],false);
    }

    /**
     * Get the webhook url.
     * @param string $event_type If not given the base event url is returned
     */
    public static function webhookEndpointUrl(string $event_type=null):string{
        $endpoint=static::$webhookEndpoint;
        
        $url= strpos($endpoint,'http')===0
                                        ? $endpoint
                                        :URL::to($endpoint);
        
        if ($event_type) {
            $url=trim($url,'/').'/'.$event_type;
        }
        
        return trim($url,'/');
    }



    /**
     * Creates a transaction from a money request payment event, with an option to save the transaction to disk. 
     * The transaction is miscellaneous because it has not orderable_id
     */
    private function moneyRequestPaymentEventToMiscellaneousTransaction(RemitaObject $event,bool $save=true):?Transaction{
        //
        $transaction=new Transaction();
        $transaction->transaction_family=Transaction::TRANSACTION_FAMILY_PAYMENT;
        $transaction->transaction_family_id=$event->reference;

        $transaction->payment_provider=$this->getProvider();
        $transaction->orderable_id='unknown';
        //$transaction->amount=$event->amount;// the $event does not contain the amount 


        //
        $transaction->livemode=$this->isLivemode();
        $transaction->status=$event->status;
        if($event->status=='successful'){
            $transaction->success=true;
        }
        $transaction->meta=['money_request'=>$event->rawValues()];

        if($save){
            $transaction->save();
        }

        return $transaction;
    }


    /**
     * Creates a transaction from a payment request, with an option to save the transaction to disk. 
     * The transaction is miscellaneous because it has not orderable_id
     */
    private function moneyRequestToMiscellaneousTransaction(MoneyRequest $moneyRequest,bool $save=true):?Transaction{
        

        //
        $transaction=new Transaction();
        $transaction->transaction_family=Transaction::TRANSACTION_FAMILY_PAYMENT;
        $transaction->transaction_family_id=$moneyRequest->transRef;

        $transaction->payment_provider=$this->getProvider();
        $transaction->orderable_id='unknown';
        $transaction->amount=$moneyRequest->amount;


        //
        $transaction->livemode=$this->livemode;
        $transaction->status=$moneyRequest->status;
        if($moneyRequest->hasSucceeded()){
            $transaction->success=true;
        }
        $transaction->meta=['money_request'=>$moneyRequest->rawValues()];

        // Note we stop short of setting the status and success on the transaction
        // since those should be done through dedicated procedures.

        if($save){
            $transaction->save();

            
            // NOTE: Commenting out the following as orderable_id is now non-null
            // // Since the transaction was missing(there won't be orderable id) this need to avoid 
            // // triggering events such as those that will call totalPaid when saving
            // Transaction::withoutEvents(function()use($transaction){
            //     $transaction->save();
            // });
            
        }

        return $transaction;

    }


    /**
     * Check if the background service is set to be used.
     *
     * @return boolean
     */
    public function inBackground(){
        return (intval($this->getConfig()['in_background'])==1);
    }

    /**
     * Generate a new Remita transRef for the given transaction
     *
     * @param Transaction $transaction
     * @return string
     * @throws \UnexpectedValueException when the transaction is not saved
     */
    public static function generateTransRef(Transaction $transaction){
        if(!$transaction->exists){
            throw new \UnexpectedValueException('The given transaction must be saved');
        }
        return 'TR-'.$transaction->id.'-'.strtoupper(Str::random(9));
    }
    /**
     * Converts a Remita transaction reference, transRef to Transaction::id
     *
     * @param string $transRef
     * @return integer|null
     */
    public static function transRefToTransactionId(string $transRef){
        $tr=explode('-',$transRef)[1];
        if(is_numeric($tr)){
            return intval($tr);
        }
        return null;
    }

    /**
     * Returns a transaction given a Remita transaction reference, transRef.
     */
    public static function transRefToTransaction(string $transRef):?Transaction{
        $transaction_id=(static::transRefToTransactionId($transRef));
        $transaction=null;
        if ($transaction_id) {
            if (true) {// include the primary key in the query for better sql performance
                $transaction=Transaction::query()
                ->withoutGlobalScope(Tenant::globalScopeName())
                //->where('payment_provider', RemitaWalletPaymentProvider::PROVIDER)// No need for this => https://twitter.com/beta_v1_1/status/1537786505330032640
                ->where('transaction_family_id', $transRef)// Note: this ensures that even if someone predicts the transaction id (since it is sequential) they should not predict the whole reference which is random.
                ->where('id', $transaction_id)
                ->first();
            } else {// Just use the transaction family id; but as the primary key is not involved here, it should be a slower query.
                $transaction=Transaction::query()
                ->withoutGlobalScope(Tenant::globalScopeName())
                ->where('payment_provider', static::PROVIDER)
                ->where('transaction_family_id', $transRef)//
                ->first();
            }
        }

        return $transaction;
    }
}
