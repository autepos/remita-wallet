<?php



namespace BethelChika\Remita\Util;

class ObjectTypes
{
    /**
     * @var array Mapping from object types to resource classes
     */
    const mapping = [
        \BethelChika\Remita\Wallet::OBJECT_NAME => \BethelChika\Remita\Wallet::class,
        \BethelChika\Remita\Authentication::OBJECT_NAME => \BethelChika\Remita\Authentication::class,
        \BethelChika\Remita\MoneyRequest::OBJECT_NAME => \BethelChika\Remita\MoneyRequest::class,
        \BethelChika\Remita\MoneyRequestResponse::OBJECT_NAME => \BethelChika\Remita\MoneyRequestResponse::class,
        \BethelChika\Remita\TransactionLog::OBJECT_NAME => \BethelChika\Remita\TransactionLog::class,
        \BethelChika\Remita\Event::OBJECT_NAME => \BethelChika\Remita\Event::class,
        
    ];
}
