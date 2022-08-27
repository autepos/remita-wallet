<?php
namespace BethelChika\Remita\Service;
class AuthenticationService extends \BethelChika\Remita\Service\AbstractService{

    /**
     * Authentication with Remita API.
     *
     *
     * @param null|array $params
     * @param null|array $opts
     *
     * @throws \BethelChika\Remita\Exception\ApiErrorException if the request fails
     *
     * @return \BethelChika\Remita\Authentication
     */
    public function authenticate(array $params = null, array $opts = null)
    {
        $opts['authentication_request']=true;
        $opts['object_name']=\BethelChika\Remita\Authentication::OBJECT_NAME;

        return $this->request('post', '/authenticate',$params,$opts);
    }
}