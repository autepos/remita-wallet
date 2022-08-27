<?php
namespace BethelChika\Remita;



abstract class WebhookSignature
{
    

    /**
     * Verifies the signature header sent by Remita. Throws an
     * Exception\SignatureVerificationException exception if the verification fails for
     * any reason.
     *
     * @param string $payload the payload sent by Remita
     * @param string $header the signature header sent by Remita
     * @param string $secret secret used to generate the signature
     *
     * @throws Exception\SignatureVerificationException if the verification fails
     *
     * @return bool
     * 
     */
    public static function verifyHeader($payload, $header, $secret)
    {
        //TODO: these should be replace when Remita tells us how their verification works

        

        //
        $signature=static::generateHash($payload, $secret);
        
        if (!hash_equals($signature,$header)) {
            throw Exception\SignatureVerificationException::factory(
                'Failed verification',
                $payload,
                $header
            );
        }

        

        return true;
    }

    /**
     * Generate a hash for the given $payload  with a secrete
     *
     * @param mixed $payload the data
     * @param string $secret hash secret
     * @return string hashed payload
     */
    public static function generateHash($payload, string $secret){
        return hash_hmac('sha256', $payload, $secret);
    }
    


}
