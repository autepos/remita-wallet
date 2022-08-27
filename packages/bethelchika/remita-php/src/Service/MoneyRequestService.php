<?php
namespace BethelChika\Remita\Service;

use BethelChika\Remita\MoneyRequest;
use BethelChika\Remita\MoneyRequestResponse;

class MoneyRequestService extends \BethelChika\Remita\Service\AbstractService{

        /**
     * Creates a Money request object.
     *
     *
     * @param null|array $params
     * @param null|array $opts additional headers to be sent with the request
     *
     * @throws \BethelChika\Remita\Exception\ApiErrorException if the request fails
     *
     * @return \BethelChika\Remita\MoneyRequestResponse
     */
    public function create(array $params = null, array $opts = null)
    {
        $opts['object_name']=MoneyRequestResponse::OBJECT_NAME;
        return $this->request('post', '/request-money', $params, $opts);

    }

    /**
     * Retrieves the log of a transaction of a money request. It can be used to check the status of 
     * the transaction.
     *
     *
     * @param null|array $transRef money request is to be retrieved.
     * @param null|array $opts
     *
     * @throws \BethelChika\Remita\Exception\ApiErrorException if the request fails
     *
     * @return \BethelChika\Remita\MoneyRequest
     */
    public function retrieve(string $transRef, array $opts = null)
    {

        $opts['object_name']=MoneyRequest::OBJECT_NAME;
        return $this->request('get', '/transaction/logs/'.$transRef,null, $opts);
    }
}