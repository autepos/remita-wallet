<?php

use Illuminate\Support\Facades\Route;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletPaymentProvider;
use Autepos\AiPayment\Providers\RemitaWallet\Http\Controllers\RemitaWalletWebhookController;

// Remita Webhook - We are putting it in its own file so that we can have it 
// outside of the web middleware group to avoid csrf checks
Route::post(rtrim(RemitaWalletPaymentProvider::$webhookEndpoint,'/').'/{event_type}',[RemitaWalletWebhookController::class,'handleWebhook']);

