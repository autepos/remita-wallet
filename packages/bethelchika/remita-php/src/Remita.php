<?php
namespace BethelChika\Remita;
class Remita{

    /**
     * Version of the client library
     */
    const VERSION = '0.1.0';


    /** @var string The Remita API key to be used for requests. */
    public static $apiKey;    


    /** 
     * @var null|string The version of the Remita API to use for requests. 
     */
    public static $apiVersion = null;
}