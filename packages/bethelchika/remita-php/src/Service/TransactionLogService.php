<?php
namespace BethelChika\Remita\Service;

use BethelChika\Remita\TransactionLog;


class TransactionLogService extends \BethelChika\Remita\Service\AbstractService{

    /**
     * Retrieves the log of a transaction. It can be used to check the status of 
     * the transaction.
     *
     * Todo: Currently not in use. This class may be unnecessary and may be later removed.
     * 
     * @param null|array $transRef transaction reference of the transaction whose log is to be retrieved.
     * @param null|array $opts additional headers to be sent with the request
     *
     * @throws \BethelChika\Remita\Exception\ApiErrorException if the request fails
     *
     * @return \BethelChika\Remita\TransactionLog
     */
    public function retrieve(string $transRef, array $opts = null)
    {

        $opts['object_name']=TransactionLog::OBJECT_NAME;
        return $this->request('get', '/transaction/logs/'.$transRef,null, $opts);
    }
}