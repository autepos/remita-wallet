<?php

namespace Autepos\AiPayment\Providers\RemitaWallet;

use Illuminate\Support\ServiceProvider;
use Autepos\AiPayment\Contracts\PaymentProviderFactory;





class RemitaWalletServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Boot he service provider
     *
     * @return void
     */
    public function boot()
    {

        
        $paymentManager = $this->app->make(PaymentProviderFactory::class);

        $paymentManager->extend(RemitaWalletPaymentProvider::PROVIDER, function ($app) {
            return $app->make(RemitaWalletPaymentProvider::class);
        });

        /**
         * Load routes for Remita
         */
        $this->loadRoutesFrom(__DIR__ . '/routes/routes.php');
    }


}
