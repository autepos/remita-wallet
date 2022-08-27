<?php

namespace Autepos\AiPayment\Providers\RemitaWallet;



use Illuminate\Support\Facades\Log;
use \Autepos\AiPayment\CustomerResponse;
use \Autepos\AiPayment\Contracts\CustomerData;
use \Autepos\AiPayment\Models\PaymentProviderCustomer;
use \Autepos\AiPayment\Providers\Contracts\ProviderCustomer;


/**
 * @property-read RemitaWalletPaymentProvider $provider
 */
class RemitaWalletCustomer extends ProviderCustomer
{
        /**
     * Convert the customer data to a payment provider customer or create a new one
     * if one does not exit.
     */
    public function toPaymentProviderCustomerOrCreate(CustomerData $customerData): ?PaymentProviderCustomer
    {
        return $this->toPaymentProviderCustomer($customerData);
    }


    /**
     * Since Remita does not have customer id, we will make one up.
     * Returns a unique customer id.
     *
     * @return string
     */
    public static function generateCustomerId(CustomerData $customerData){
        return $customerData->user_type.'-'.$customerData->user_id;
    }
    /**
     * Return a payment provider customer for a given Customer data. If none already exists, it will be created.
     *
     */
    private  function toPaymentProviderCustomer(CustomerData $customerData): PaymentProviderCustomer
    {

        $payment_provider_customer_id=$this->generateCustomerId($customerData);

        $paymentProviderCustomer = PaymentProviderCustomer::where('payment_provider_customer_id', $payment_provider_customer_id)
            ->where('payment_provider', $this->provider->getProvider())->first();

        if (!$paymentProviderCustomer) {
            //
            $paymentProviderCustomer = new PaymentProviderCustomer;

            //
            $paymentProviderCustomer->payment_provider_customer_id =$payment_provider_customer_id;
            $paymentProviderCustomer->payment_provider = $this->provider->getProvider();

            //
            $paymentProviderCustomer->user_type = $customerData->user_type;
            $paymentProviderCustomer->user_id = $customerData->user_id;

            //
            $paymentProviderCustomer->save();
        }

        return $paymentProviderCustomer;
    }




    /**
     * Remove the underlying Remita Wallet for the given customer
     *
     * @throws \BethelChika\Remita\Exception\ApiErrorException — if the request fails
     */
    public function remove(PaymentProviderCustomer $paymentProviderCustomer): bool
    {
        return true;// We return true because the customer was never created at Remita.
    }

    /**
     * Delete the local record of the customer
     *
     */
    public function deletePaymentProviderCustomer(PaymentProviderCustomer $paymentProviderCustomer): bool
    {
        return $paymentProviderCustomer->delete();
    }

    /**
     * @inheritDoc
     */
    public function create(CustomerData $customerData): CustomerResponse
    {
        $customerResponse = new CustomerResponse(CustomerResponse::newType('save'));

        $paymentProviderCustomer = $this->toPaymentProviderCustomer($customerData);
        if ($paymentProviderCustomer) {
            $customerResponse->paymentProviderCustomer($paymentProviderCustomer);
            $customerResponse->success = true;
        }else{
            $msg='The local record could not be created';
            Log::error($msg,[
                'customerData'=>['user_type'=>$customerData->user_type,'user_id'=>$customerData->user_id],
            ]);
            $customerResponse->message=$msg;
            $customerResponse->errors=['A local error occurred while recording wallet information'];

        }
        return $customerResponse;
    }

   
    /**
     * Delete the local record of the customer and remove also the Wallet from Remita
     *
     */
    public function delete(PaymentProviderCustomer $paymentProviderCustomer): CustomerResponse
    {
        $customerResponse = new CustomerResponse(CustomerResponse::newType('delete'));
        if (
            $this->remove($paymentProviderCustomer)
            and $this->deletePaymentProviderCustomer($paymentProviderCustomer)
        ) {
            $customerResponse->success = true;
        }

        return $customerResponse;
    }
}
