# Introduction
This package provides Remita Wallet payment services for [AiPayment](https://github.com/autepos/ai-payment).

## Installation
```
composer require autepos/remita-wallet
```

### Configuration
See [AiPayment](https://github.com/autepos/ai-payment) for using payment service to use a payment provider; 
```php
$paymentService->provider('remita_wallet')
->config($config);
```

The **$config**, is a configuration array with the keys ```username,password,scheme,account_number,api_base, and webhook_secret[optional]```.

The configuration keys must be acquired from Remita

## Usage
The package is currently for internal use only. To use the package in your project, it is your responsibility to contact us or Remita for help. 
