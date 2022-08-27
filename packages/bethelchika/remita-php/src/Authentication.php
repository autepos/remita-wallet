<?php
namespace BethelChika\Remita;
/**
 * @property string $message message from api
 * @property string $code code returned by the api
 * @property string $token The authentication token
 * @property $user
 * @property string $userType The user type
 * @property array $walletAccountList
 */
class Authentication extends RemitaObject{

    const OBJECT_NAME = 'authentication';

    const CODE_SUCCEEDED = null;

    const MESSAGE_SUCCEEDED = 'Login success';
}
