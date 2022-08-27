<?php
namespace BethelChika\Remita\Service;

use BethelChika\Remita\Wallet;

class WalletService extends \BethelChika\Remita\Service\AbstractService{

        /**
     * Creates a Wallet object.
     *
     *
     * @param null|array $params
     * @param null|array $opts additional headers to be sent with the request
     *
     * @throws \BethelChika\Remita\Exception\ApiErrorException if the request fails
     *
     * @return \BethelChika\Remita\Wallet
     */
    public function create(array $params = null, array $opts = null)
    {
        $opts['object_name']=Wallet::OBJECT_NAME;
        $wallet= $this->request('post', '/wallet-external', $params, $opts);

        // Remita api returns the wallet object within an array in $wallet->data.
        // Here we will fix this by making sure that $wallet->data is an object 
        // instead of array.
        $values=$wallet->rawValues();
        $values['data']=$values['data'][0];
        $wallet->refreshValuesFrom($values);
        return $wallet;
    }
}