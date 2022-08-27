<?php
namespace BethelChika\Remita;
/**
 * A transaction log object. The data property represents a RemitaObject. The retrieve 
 * method of the service for such a RemitaObject represented by the data property may 
 * be used to retrieve the transaction log instead of using TransactionLogService.
 * 
 * Todo: Currently not in use. This class may be unnecessary and may be later removed.
 * 
 * @property array $data the response data from api.
 */
class TransactionLog extends RemitaObject{

    const OBJECT_NAME = 'transaction_log';

    
}
