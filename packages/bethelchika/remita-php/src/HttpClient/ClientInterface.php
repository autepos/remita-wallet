<?php

namespace BethelChika\Remita\HttpClient;

interface ClientInterface
{
    /**
     * @param string $method The HTTP method being used
     * @param string $absUrl The URL being requested, including domain and protocol
     * @param array $headers Headers to be used in the request (full strings, not KV pairs)
     * @param array $params KV pairs for parameters. Can be nested for arrays and hashes
     *
     * @throws \BethelChika\Remita\Exception\ApiConnectionException
     * @throws \BethelChika\Remita\Exception\UnexpectedValueException
     *
     * @return array an array whose first element is raw request body, second
     *    element is HTTP status code and third array of HTTP headers
     */
    public function request(string $method,string $absUrl, array $headers, array $params):array;
}
