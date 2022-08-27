<?php

namespace Autepos\AiPayment\Providers\RemitaWallet\Tests;

use Autepos\AiPayment\AiPaymentServiceProvider;
use Autepos\AiPayment\Providers\RemitaWallet\RemitaWalletServiceProvider;



abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    
    public static $remita_config=[
    ];
    protected function getEnvironmentSetUp($app)
    {

        config()->set('database.connections.mysql.engine', 'InnoDB');

        static::$remita_config=[
            'username'=>env('REMITA_TEST_USERNAME'),
            'password'=>env('REMITA_TEST_PASSWORD'),
            'scheme'=>env('REMITA_TEST_SCHEME'),
            'account_number'=>env('REMITA_TEST_ACCOUNT_NUMBER'),
            'api_base'=>env('REMITA_TEST_API_BASE'),
            'webhook_secret'=>env('REMITA_WEBHOOK_SECRET')
        ];
    }


    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            AiPaymentServiceProvider::class,
            RemitaWalletServiceProvider::class,
        ];
    }
}
