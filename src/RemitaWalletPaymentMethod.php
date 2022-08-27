<?php

namespace Autepos\AiPayment\Providers\RemitaWallet;


use BethelChika\Remita\Wallet;
use Illuminate\Support\Facades\DB;
use Autepos\AiPayment\PaymentMethodResponse;
use \Autepos\AiPayment\Contracts\CustomerData;
use Illuminate\Contracts\Auth\Authenticatable;
use \Autepos\AiPayment\Models\PaymentProviderCustomer;
use Autepos\AiPayment\Providers\Contracts\ProviderPaymentMethod;
use Autepos\AiPayment\Models\PaymentProviderCustomerPaymentMethod;

/**
 * @property-read RemitaWalletPaymentProvider $provider
 */
class RemitaWalletPaymentMethod extends ProviderPaymentMethod
{

    public function init(array $data): PaymentMethodResponse{
        return new PaymentMethodResponse(PaymentMethodResponse::newType('init'));
    }


    public function save(array $data): PaymentMethodResponse{

        $paymentMethodResponse= new PaymentMethodResponse(PaymentMethodResponse::newType('save'));


        $paymentProviderCustomer = $this->provider->customer()
            ->toPaymentProviderCustomerOrCreate($this->customerData);
        if (!$paymentProviderCustomer) {
            $paymentMethodResponse->message = "There was an issue";
            $paymentMethodResponse->errors = ['Could not create or retrieve customer'];
            return $paymentMethodResponse;
        }


         //
         $wallet=null;
         try{
             $wallet=$this->createRemitaWallet($this->customerData);
         }catch(\BethelChika\Remita\Exception\ApiErrorException $ex){
            $paymentMethodResponse->message='Error code '.$ex->getCode().' occurred while attempting to create wallet';
            $paymentMethodResponse->errors=[$ex->getMessage()];
 
             return $paymentMethodResponse;
         }


         $paymentProviderCustomerPaymentMethod = $this->record(
            $wallet,
            $paymentProviderCustomer
        );

        //
        $paymentMethodResponse->success = true;
        return $paymentMethodResponse->paymentProviderCustomerPaymentMethod($paymentProviderCustomerPaymentMethod);

    }


    public function remove(PaymentProviderCustomerPaymentMethod $paymentMethod): PaymentMethodResponse{
        
        // Note that we are only able to delete the payment method 
        // locally, we are unable to do so at Remita currently
        return new PaymentMethodResponse(
            PaymentMethodResponse::newType('delete'),
            $paymentMethod->delete()
        );
    }

    

     /**
     * Add the given remita wallet to local data. If a local model is supplied
     * we will use it to store the record by overwriting it.
     */
    private  function record(Wallet $remitaWallet,PaymentProviderCustomer $paymentProviderCustomer,PaymentProviderCustomerPaymentMethod $ppcpm = null
    ): PaymentProviderCustomerPaymentMethod 
    {
        if (is_null($ppcpm)) {
            $ppcpm = new PaymentProviderCustomerPaymentMethod;
        }
        $wallet_id=$remitaWallet->accountNumber;

        $temp = PaymentProviderCustomerPaymentMethod::where('payment_provider_payment_method_id', $wallet_id)
            ->where('payment_provider', $this->provider->getProvider())->first();

        if ($temp) {
            return $temp;// It already exist so we return early
        }

        //
        $ppcpm->payment_provider_payment_method_id = $wallet_id;
        $ppcpm->payment_provider = $this->provider->getProvider();

        //
        $ppcpm->brand='Remita wallet';
        $ppcpm->last_four=\substr($wallet_id,-4);
        
        //
        $ppcpm->livemode=$this->provider->isLivemode();

        //
        $ppcpm->meta=['phone'=>$this->customerData->phone];

        //
        $paymentProviderCustomer->paymentMethods()
        ->save($ppcpm);
        
        return $ppcpm;
    }


        /**
     * Create a new Remita wallet
     *
     * @throws \BethelChika\Remita\Exception\ApiErrorException — if the request fails
     */
    private function createRemitaWallet(CustomerData $customerData): ?Wallet
    {
        
        return $this->provider->client()
            ->wallets->create([
                'phoneNumber'=>$customerData->phone,
                'firstName'=>$customerData->first_name,
                'lastName'=>$customerData->last_name,
                'email'=>$customerData->email,
                'gender'=>strtoupper($customerData->gender), 
                'dateOfBirth'=>$customerData->date_of_birth,
                'scheme'=>$this->provider->getConfig()['scheme'],
                'accountName'=>$customerData->first_name,
                'state'=> null,
                'localGovt'=> null,
                'address'=> null
            ]);

           
    }

    /**
     * Add an existing wallet as payment method.
     *
     * @param string $wallet_id
     * @param string $phone
     * @return PaymentProviderCustomerPaymentMethod
     */
    public function addExisting(string $wallet_id,string $phone): PaymentProviderCustomerPaymentMethod {
        $ppcpm=new PaymentProviderCustomerPaymentMethod;
        $ppcpm->payment_provider_payment_method_id=$wallet_id;
        $ppcpm->meta=[
            'phone'=>$phone
        ];

        //
        $ppcpm->payment_provider=$this->provider->getProvider();

        //
        $ppcpm->brand='Remita wallet';
        $ppcpm->last_four=\substr($wallet_id,-4);
        
        //
        $ppcpm->livemode=$this->provider->isLivemode();

        //
        $paymentProviderCustomer = $this->provider->customer()
        ->toPaymentProviderCustomerOrCreate($this->customerData);

        $paymentProviderCustomer->paymentMethods()->save($ppcpm);
        //
        
        return $ppcpm;
    }
    

    /**
     * Create a Remita wallet using the alternative background process created by Lawal.
     *
     * @param Authenticatable $creator The admin user who is creating the wallet for the customer.
     */
    public function createInBackground(Authenticatable $creator):PaymentMethodResponse{
        $customerData=$this->customerData;

        $payload=[
            'phoneNumber'=> $customerData->phone,
            'firstName'=> $customerData->first_name,
            'lastName'=> $customerData->last_name,
            'dateOfBirth'=> $customerData->date_of_birth,
            'gender'=> strtoupper($customerData->gender),
            'state'=> null,
            'localGovt'=> null,
            'address'=> null,
            'scheme'=> $this->provider->getConfig()['scheme'],
            'accountName'=> $customerData->first_name.' '.$customerData->last_name.' '.$customerData->user_id,
            'email'=> $customerData->email,
        ];

        $sql='INSERT INTO `payment_api_requests` 
        (par_hospital_id,par_patient_id,
        par_provider_id,par_request_type,
        par_start_time,
        par_payload,
        par_status,par_userid) 
        VALUES (?,?,?,?,?,?,?,?)';

        $insert_values=[
            $this->provider->getTenant(),$customerData->user_id,
            $this->provider->getProvider(),'CREATE_WALLET',
            date('Y-m-d H:i:s'),
            json_encode($payload),
            1,$creator->getAuthIdentifier(),
        ];



        DB::insert($sql,$insert_values);
        $paymentMethodResponse = new PaymentMethodResponse(PaymentMethodResponse::newType('save'));
        $paymentMethodResponse->success=true;
        $paymentMethodResponse->message='Wallet creation was requested successfully';
        return $paymentMethodResponse;
    }

    


}
