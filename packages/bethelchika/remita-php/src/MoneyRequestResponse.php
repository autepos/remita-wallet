<?php
namespace BethelChika\Remita;



/**
 * @property string $message the request response message from api.
 * @property string $code the request response code from api signalling the if the request was successful or not.
 * @property boolean $error Whether the request has got error
 * @property mixed $status The status of the request
 * @property null|array $paymentTransactionDTO Transaction DTO
 */
class MoneyRequestResponse extends RemitaObject{

    const OBJECT_NAME = 'money_request_response';

    /**
     * The request has been sent successfully
     */
    const CODE_SUCCEEDED='00';

    /**
     * The request has not been sent successfully
     */
    const CODE_FAILED='99'; 


}
