<?php

namespace Autepos\AiPayment\Providers\RemitaWallet;

use Exception;
use BethelChika\Remita\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use BethelChika\Remita\MoneyRequest;
use BethelChika\Remita\RemitaObject;
use \Autepos\AiPayment\SimpleResponse;
use BethelChika\Remita\Authentication;
use \Autepos\AiPayment\PaymentResponse;
use \Autepos\AiPayment\Models\Transaction;
use BethelChika\Remita\MoneyRequestResponse;
use \Autepos\AiPayment\Contracts\CustomerData;
use Illuminate\Contracts\Auth\Authenticatable;
use \Autepos\AiPayment\Models\PaymentProviderCustomer;
use \Autepos\AiPayment\Providers\Contracts\PaymentProvider;
use \Autepos\AiPayment\Providers\Contracts\ProviderCustomer;
use Autepos\AiPayment\Providers\Contracts\ProviderPaymentMethod;
use Autepos\AiPayment\Providers\RemitaWallet\Concerns\PaymentProviderUtils;

class RemitaWalletPaymentProvider extends PaymentProvider
{
    use PaymentProviderUtils;

    public const PROVIDER = 'remita_wallet';
    /**
     * The provider library version.
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * The endpoint path for webhook. 
     *  @var string
     */
    public static $webhookEndpoint = 'remita-wallet/webhook';

    public function up(): SimpleResponse
    {
        return new SimpleResponse(SimpleResponse::newType('save'), true);
    }

    public function down(): SimpleResponse
    {
        return new SimpleResponse(SimpleResponse::newType('save'), true);
    }

    /**
     * @inheritDoc
     * 
     */
    public function ping(): SimpleResponse
    {
        // For now, we assume that if we can login, we can ping
        $client = $this->clientWithoutAuthentication();
        $auth = $client->authentication->authenticate();
        $response = new SimpleResponse(SimpleResponse::newType('ping'));
        $response->success = ($auth->code == Authentication::CODE_SUCCEEDED);
        $response->message = $auth->message;
        return $response;
    }
    public function init(int $amount = null, array $data = [], Transaction $transaction = null): PaymentResponse
    {
        return new PaymentResponse(PaymentResponse::newType('init'));
    }
    /**
     * @inheritDoc
     * @param array $data Arbitrary data. 
     * [
     * 'payment_provider_payment_method_id'=>(string) The Remita Wallet id which should be used to init the payment.
     *  'notes'=>(string) a note to be stored in transaction
     *  'send_money_request'=>(int){0,1(default)}. money request is not sent if this value is sent and it is =0.
     * ]
     * 
     */
    public function cashierInit(Authenticatable $cashier, int $amount = null, array $data = [], Transaction $transaction = null): PaymentResponse
    {

        $payment_provider_payment_method_id = $data['payment_provider_payment_method_id'] ?? null;

        // Extract input data
        $send_money_request = 1;
        if (array_key_exists('send_money_request', $data)) {
            $send_money_request = intval($data['send_money_request']);
        }
        $should_send_money_request = boolval($send_money_request);

        //
        $paymentResponse = new PaymentResponse(PaymentResponse::newType('init'));

        // Amount
        $amount = $amount ?? $this->order->getAmount();

        //
        $transaction = $this->getInitTransaction($amount, $transaction);
        $transaction->cashier_id = $cashier->getAuthIdentifier();
        $transaction->notes = isset($data['notes']) ? $data['notes'] : null;

        $customerData = $this->getCustomerData();

        //
        $paymentProviderCustomer = PaymentProviderCustomer::fromCustomerData($customerData, $this->getProvider());
        if (!$paymentProviderCustomer) {
            $paymentResponse->message = 'Error with  Remita wallet provider account';
            $paymentResponse->errors = ['Missing Remita wallet provider account'];
            return $paymentResponse;
        }

        $wallet_id = null; // This is the wallet id that will be used for payment.

        // Select a payment method. When one is specified, make sure we select it.
        $paymentProviderCustomerPaymentMethod = $paymentProviderCustomer->paymentMethods()
            ->when($payment_provider_payment_method_id, function ($query, $payment_provider_payment_method_id) {
                $query->where('payment_provider_payment_method_id', $payment_provider_payment_method_id);
            })
            ->orderBy('is_default', 'desc') //i.e is_default=1 should be at the top.
            ->first();

        if ($paymentProviderCustomerPaymentMethod) {
            $wallet_id = $paymentProviderCustomerPaymentMethod->payment_provider_payment_method_id;

            // Capture some info about the payment method, these could be useful for troubleshooting.
            $transaction->meta = [
                'payment_provider_customer_id' => $paymentProviderCustomer->id,
                'payment_provider_customer_payment_method_id' => $paymentProviderCustomerPaymentMethod->id,
                'payment_provider_payment_method_id' => $wallet_id,
                'wallet_phone' => $paymentProviderCustomerPaymentMethod->meta['phone'] ?? '',
                'wallet_livemode' => $paymentProviderCustomerPaymentMethod->livemode,
            ];
            $transaction->last_four = $paymentProviderCustomerPaymentMethod->last_four;
            $transaction->card_type = $paymentProviderCustomerPaymentMethod->brand;
        }

        if (!$wallet_id) {
            $paymentResponse->message = 'Problem finding a wallet';
            $paymentResponse->errors = ['A wallet could not be identified'];
            return $paymentResponse;
        }

        // Set client side data
        $paymentResponse->setClientSideData('payment_provider_payment_method_id', $wallet_id); // Send the wallet id that will be used for payment.
        $paymentResponse->setClientSideData('current_phone', $customerData->phone);
        $payment_methods = [];
        foreach ($paymentProviderCustomer->paymentMethods as $pm) {
            $payment_methods[] = [
                'pid' => $pm->pid,
                'payment_provider_payment_method_id' => $pm->payment_provider_payment_method_id,
                'meta' => [
                    'phone' => $pm->meta['phone'] ?? ''
                ],
                'livemode' => $pm->livemode,
                'payment_provider_customer_id' => $paymentProviderCustomer->id,
            ];
        }
        $paymentResponse->setClientSideData('payment_provider_customer_payment_methods', $payment_methods);

        // Return early if we won't be sending the money request
        if (!$should_send_money_request) {
            $paymentResponse->success = true;
            return $paymentResponse;
        }

        //
        $moneyRequestResponse = null;
        if (!$transaction->exists) {
            $transaction->save(); // Save the transaction so we can get its id.
        }
        $transaction->transaction_family_id = static::generateTransRef($transaction);
        $transaction->save();

        //
        try {
            if ($this->inBackground()) {
                $payload = [
                    'amount' => $transaction->orderable_amount,
                    'transRef' => $transaction->transaction_family_id,
                    'accountNumber' => $this->getConfig()['account_number'],
                    'sourceAccountNumber' => $wallet_id,
                    'channel' => 'INVOICE',
                ];
                $submitted = $this->cashierSubmitInitInBackground($cashier, $customerData, $payload);
                if ($submitted == true) {
                    $paymentResponse->success = true;
                } else {
                    throw new Exception('Payment failed');
                }
            } else {
                $moneyRequestResponse = $this->client()->moneyRequests->create([
                    'amount' => $transaction->orderable_amount,
                    'transRef' => $transaction->transaction_family_id,
                    'accountNumber' => $this->getConfig()['account_number'],
                    'sourceBankCode' => '',
                    'sourceAccountNumber' => $wallet_id,
                    'destBankCode' => '',
                    'channel' => 'INVOICE',
                ]);

                if ($moneyRequestResponse->code == MoneyRequestResponse::CODE_SUCCEEDED) {
                    $paymentResponse->success = true;
                } else {
                    throw new Exception('Payment failed');
                }
            }
        } catch (\Exception $ex) {
            $paymentResponse->message = 'Error requesting money';
            $paymentResponse->errors = [$ex->getMessage()];
        }

        //
        return $paymentResponse->transaction($transaction);
    }

    public function charge(Transaction $transaction = null, array $data = []): PaymentResponse
    {
        $paymentResponse = new PaymentResponse(PaymentResponse::newType('charge'));

        $paymentResponse->message = 'Access denied';
        $paymentResponse->httpStatusCode = 403;
        $paymentResponse->errors = ['Access to payment denied'];

        return $paymentResponse;
    }

    /**
     * @inheritDoc
     * 
     */
    public function cashierCharge(Authenticatable $cashier, Transaction $transaction, array $data = []): PaymentResponse
    {
        $paymentResponse = new PaymentResponse(PaymentResponse::newType('charge'));



        try {

            $moneyRequest = $this->retrieveMoneyRequest($transaction->transaction_family_id);

            if ($moneyRequest->hasSucceeded()) {
                $transaction->amount = $transaction->orderable_amount;
                $paymentResponse->success = true;
                $transaction->local_status = Transaction::LOCAL_STATUS_COMPLETE;
                $transaction->through_webhook = false;
            } else {
                throw new Exception('Payment is not completed');
            }

            $transaction->status = $moneyRequest->status;
            $transaction->success = $paymentResponse->success;

            $transaction->save();
        } catch (\Exception $ex) {
            $paymentResponse->message = 'Error retrieving requested money status';
            $paymentResponse->errors = [$ex->getMessage()];
        }

        return $paymentResponse;
    }


    /**
     * Charge a transaction on webhook event without calling Remita to re-retrieve 
     * the money request object.
     * 
     * 
     */
    public function webhookCharge(RemitaObject $event, Transaction $transaction = null): PaymentResponse
    {
        $paymentResponse = new PaymentResponse(PaymentResponse::newType('charge'));

        //
        $transRef = $event->reference;
        $transaction = $transaction ?? Transaction::where('payment_provider', $this->getProvider())->where('transaction_family_id', $transRef)->first();


        if (!$transaction) { // If we still cannot retrieve transaction then we will have no choice but to quit.
            $paymentResponse->message = 'Missing transaction';
            $miscTransaction = $this->moneyRequestPaymentEventToMiscellaneousTransaction($event);
            if ($miscTransaction) {
                $paymentResponse->message = $paymentResponse->message . '. A miscellaneous transaction(#' . $miscTransaction->id . ') was recorded';
                $paymentResponse->transaction($miscTransaction);
            } else {
                $msg = 'Money request payment event was received from Remita but its local 
                    transaction was missing ans miscellaneous transaction could not be 
                    created';
                Log::error($msg, [
                    'event' => $event->rawValues()
                ]);
                $paymentResponse->message = 'Could not record money request payment event from Remita';
                $paymentResponse->errors = [$msg];
            }
            if ($event->status == Event::MONEY_REQUEST_PAYMENT_STATUS_SUCCEEDED) {
                $paymentResponse->success = true;
            }
            $paymentResponse->status = $event->status;
            return $paymentResponse;
        }

        //
        if ($event->status == Event::MONEY_REQUEST_PAYMENT_STATUS_SUCCEEDED) {
            $paymentResponse->success = true;
        }
        $paymentResponse->status = $event->status;
        //

        $transaction->success = $paymentResponse->success;
        if ($transaction->success) {
            $transaction->amount = $transaction->orderable_amount;
            $transaction->local_status = Transaction::LOCAL_STATUS_COMPLETE;
            $transaction->through_webhook = true;
        }
        $transaction->status = $paymentResponse->status;


        $transaction->save();

        //
        return $paymentResponse->transaction($transaction);
    }

    /**
     * Charge a transaction on webhook event by calling Remita to re-retrieve 
     * the money request object.
     */
    public function webhookChargeByRetrieval(RemitaObject $event, Transaction $transaction = null): PaymentResponse
    {
        $paymentResponse = new PaymentResponse(PaymentResponse::newType('charge'));


        // Connect to Remita to retrieve the money request object
        try {

            $moneyRequest = $this->retrieveMoneyRequest($event->reference);
        } catch (\BethelChika\Remita\Exception\ApiErrorException $ex) {
            $msg = 'Remita api error while retrieving money request in ' . __METHOD__;
            Log::error($msg, [
                'api error msg' => $ex->getMessage(),
                'api error code' => $ex->getCode(),
                'event' => $event->rawValues()
            ]);
        }


        //
        $transRef = $moneyRequest->transRef;
        $transaction = $transaction ?? Transaction::where('payment_provider', $this->getProvider())->where('transaction_family_id', $transRef)->first();


        if (!$transaction) { // If we still cannot retrieve transaction then we will have no choice but to quit.
            $paymentResponse->message = 'Missing transaction';
            $miscTransaction = $this->moneyRequestToMiscellaneousTransaction($moneyRequest);
            if ($miscTransaction) {
                $paymentResponse->message = $paymentResponse->message . '. A miscellaneous transaction(#' . $miscTransaction->id . ') was recorded';
                $paymentResponse->transaction($miscTransaction);
            } else {
                $msg = 'Money request payment event was received from Remita but its local 
                    transaction was missing ans miscellaneous transaction could not be 
                    created';
                Log::error($msg, [
                    'event' => $event->rawValues(),
                    'moneyRequest' => $moneyRequest->rawValues(),
                ]);
                $paymentResponse->message = 'Could not record money request payment event from Remita';
                $paymentResponse->errors = [$msg];
            }
            $paymentResponse->success = $moneyRequest->hasSucceeded();
            $paymentResponse->status = $moneyRequest->status;
            return $paymentResponse;
        }

        //
        $paymentResponse->success = $moneyRequest->hasSucceeded();
        $paymentResponse->status = $moneyRequest->status;
        //
        $transaction->success = $paymentResponse->success;

        if ($transaction->success) {
            $transaction->amount = $transaction->orderable_amount;
            $transaction->local_status = Transaction::LOCAL_STATUS_COMPLETE;
            $transaction->through_webhook = true;
        }
        $transaction->status = $paymentResponse->status;
        $transaction->save();

        //
        return $paymentResponse->transaction($transaction);
    }

    public function refund(Authenticatable $cashier, Transaction $transaction = null, int $amount, string $description): PaymentResponse
    {
        return new PaymentResponse(PaymentResponse::newType('refund'));
    }

    public function getProvider(): string
    {
        return self::PROVIDER;
    }

    public function customer(): ?ProviderCustomer
    {
        return (new RemitaWalletCustomer)
            ->provider($this);
    }

    public function paymentMethod(CustomerData $customerData): ?ProviderPaymentMethod
    {
        return (new RemitaWalletPaymentMethod)
            ->provider($this)
            ->customerData($customerData);
    }

    public function syncTransaction(Transaction $transaction): PaymentResponse
    {
        $paymentResponse = new PaymentResponse(PaymentResponse::newType('retrieve'));

        try {
            $moneyRequest = $this->retrieveMoneyRequest($transaction->transaction_family_id);

            if ($moneyRequest->hasSucceeded()) {
                $transaction->amount = $transaction->orderable_amount;
                $transaction->success = true;
                $transaction->local_status = Transaction::LOCAL_STATUS_COMPLETE;
            }
            $transaction->status = $moneyRequest->status;

            $transaction->save();

            $paymentResponse->success = true; // i.e the response is successful as long as the retrieval is successful.
        } catch (\BethelChika\Remita\Exception\ApiErrorException $ex) {
            $paymentResponse->message = 'Error retrieving requested money status (' . $ex->getCode() . ')';
            $paymentResponse->errors = [$ex->getMessage()];
        }
        return $paymentResponse;
    }

    /**
     * Retrieve a money request object from Remita
     *
     * @throws \BethelChika\Remita\Exception\ApiErrorException when the request fails for any reason
     */
    protected function retrieveMoneyRequest(string $transRef): MoneyRequest
    {
        return $this->client()->moneyRequests->retrieve($transRef);
    }

    /**
     * @inheritDoc
     */
    public function getStaticConfig(): array
    {
        return [
            'webhook: ' . Event::MONEY_REQUEST_PAYMENT_STATUS => static::webhookEndpointUrl(Event::MONEY_REQUEST_PAYMENT_STATUS),
        ];
    }

    /**
     * Insert REQUEST_MONEY entry for the background service
     */
    private function cashierSubmitInitInBackground(Authenticatable $cashier, CustomerData $customerData, array $payload): bool
    {

        $sql = 'INSERT INTO `payment_api_requests` 
        (par_hospital_id,par_patient_id,
        par_provider_id,par_request_type,
        par_start_time,
        par_payload,
        par_status,par_userid) 
        VALUES (?,?,?,?,?,?,?,?)';

        $insert_values = [
            $this->getTenant(), $customerData->user_id,
            $this->getProvider(), 'REQUEST_MONEY',
            date('Y-m-d H:i:s'),
            json_encode($payload),
            1, $cashier->getAuthIdentifier(),
        ];

        return DB::insert($sql, $insert_values);
    }
}
