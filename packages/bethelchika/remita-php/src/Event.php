<?php
namespace BethelChika\Remita;
/**
 */
class Event extends RemitaObject{

    const OBJECT_NAME = 'event';

    /**
     * Possible string representations of event types.
     *
     */

     /**
     * Money request payment status change notification
     */
    const MONEY_REQUEST_PAYMENT_STATUS='money_request.payment_status';


    // Statuses of the event type objects - These should really be in the 
    // respective RemitaObject but the corresponding object returned through 
    // webhook have different values from the same object retrieved directly. 
    // This inconsistency is really inconvenient and forces us to do wired stuff 
    // like add the value of the statuses here.
    const MONEY_REQUEST_PAYMENT_STATUS_SUCCEEDED='successful';


    
}
