<?php

namespace BethelChika\Remita\Service;



/**
 * Service factory class for API resources in the root namespace.
 *
 * @property WalletService $wallets
 * @property MoneyRequestService $wallets
 */
class CoreServiceFactory extends \BethelChika\Remita\Service\AbstractServiceFactory
{
    /**
     * @var array<string, string>
     */
    private static $classMap = [
        'authentication' => AuthenticationService::class,
        'wallets' => WalletService::class,
        'moneyRequests' => MoneyRequestService::class,
        'transactionLogs' => TransactionLogService::class,
    ];

    protected function getServiceClass($name)
    {
        return \array_key_exists($name, self::$classMap) ? self::$classMap[$name] : null;
    }
}
