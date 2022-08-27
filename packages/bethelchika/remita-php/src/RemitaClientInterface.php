<?php

namespace BethelChika\Remita;

/**
 * Interface for a Remita client.
 */
interface RemitaClientInterface
{
    /**
     * Sends a request to Remita's API.
     *
     * @param string $method the HTTP method
     * @param string $path the path of the request
     * @param array $params the parameters of the request
     * @param array $opts the special modifiers of the request. These are sent as headers unless they are non-header options:
     * NON-HEADER OPTIONS INCLUDE:
     * 'api_key' : (string) Api token that has already been obtained
     * 'authentication_request': (boolean) Indicate that the request is for authentication (mainly used internally)
     * 'object_name': (string) Specify the name of the request object (mainly used internally)
     *
     * @return \BethelChika\Remita\RemitaObject the object returned by Remita's API
     * @throws \BethelChika\Remita\Exception\ApiErrorException when the request fails
     */
    public function request($method, $path, $params, $opts);


        /**
     * Gets the API key used by the client to send requests.
     *
     * @return null|string the API key used by the client to send requests
     */
    public function getApiKey();

    /**
     * Gets the scheme id.
     *
     * @return null|string the scheme id
     */
    public function getScheme();



       /**
     * Get the api username for authentication
     *@return string
     */
    public function getUsername();

       /**
     * Get the api password for authentication
     *
     * @return string
     */
    public function getPassword();

    /**
     * Gets the base URL for Remita's API.
     *
     * @return string the base URL for Remita's API
     */
    public function getApiBase();

    /**
     * Check if the client is in live mode
     *
     * @return boolean
     */
    public function isLivemode();
}
