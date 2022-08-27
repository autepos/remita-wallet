# Remita PHP
Remita api client for PHP.

## Installation
Download the package and register the /src directory and subdirectories as include path.

## Usage
### Get the client
```php
$remitaClient=\BethelChika\Remita\RemitaClient([
    'username'=>'user123',
    'password'=>'pass133',
    'scheme'=>'scheme123',
    'livemode'=>true,
    'auto_authenticate'=>true,
    'api_base'=>null
]);
```
### Get the status of a money request
```php
$moneyRequest=$remitaClient->moneyRequests->retrieve($transRef);
$status=$moneyRequest->status;
```

## Note
You should use the **rawValues**  on a RemitaObject if you want to inspect the actual values returned by Remita api after a request. To get the request options/headers and response headers and http code for a request, use **getOptions** method on a RemitaObject after a request that returns the object.
```php
$values=$moneyRequest->rawValues();
$options=$moneyRequest->getOptions();
```

## Wallet
### Create wallet
```php
$wallet=$remitaClient->wallets->create([
	"phoneNumber"=> "+2348409335109",
	"firstName"=> "Sola",
	"lastName"=> "Olawale",
	"dateOfBirth"=> "2020-11-17",
	"gender"=> "MALE",
	"state"=> null,
	"localGovt"=> null,
	"address"=> null,
	"scheme"=> {SCHEME},
	"accountName"=> "Ward366 Wallet",
	"email"=> "solagOlawale@gmail.com"
])
```
## MoneyRequest
### Request money
```php
$moneyRequest=$remitaClient->moneyRequests->create([
    "accountNumber"=> {ACCOUNT_NUMBER}, //beneficiaries account number
    "amount"=> 100000, // in kobo
    "channel"=> "INVOICE",
    "destBankCode"=> "",
    "sourceAccountNumber"=> {SOURCE_ACCOUNT_NUMBER},// customer account number
    "sourceBankCode"=> "",
    "transRef" => "lhc-101" // unique reference for this transaction
])
```
### Retrieve the money request object
```php
$moneyRequest=$remitaClient->moneyRequests->retrieve($transRef);
```

## Contributions
Only a few Remita API are implemented. Please send your request if you would like to see an API implemented. You can also send a pull request.  