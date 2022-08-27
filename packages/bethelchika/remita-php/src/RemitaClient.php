<?php

namespace BethelChika\Remita;

use BethelChika\Remita\HttpClient\LaravelHttpClient;


/** 
 * Client used to send requests to Remita's API.
 *
 * @property \BethelChika\Remita\Service\WalletService $wallets
 * @property \BethelChika\Remita\Service\AuthenticationService $authentication
 * @property \BethelChika\Remita\Service\MoneyRequestService $moneyRequests
 * @property \BethelChika\Remita\Service\TransactionLogService $transactionLogs
 */
class RemitaClient implements RemitaClientInterface
{
    /** @var string default base URL for API */
    const DEFAULT_API_BASE = 'https://remita.com/api';
    
    /** 
     * @var string The base URL for the Remita API. 
     * 
     */
    public const DEFAULT_DEMO_API_BASE = 'https://walletdemo.remita.net/api';

    /** 
     * @var array<string, mixed>
     */
    private const DEFAULT_CONFIG = [
        'api_base' => null,
        'api_key' => null,
        'username' => null,
        'password' => null,
        'scheme' => null,
        'livemode' => false,
        'auto_authenticate' => false,
    ];

    /**
     * Names of request options that should not be sent as headers.
     */
    private const NONE_HEADER_OPTIONS = [
        'api_key',
    ];

    /**
     * Options for internal use.
     */
    private const INTERNAL_OPTIONS = [
        'authentication_request',
        'object_name'
    ];

    /** @var array<string, mixed> */
    private $config;

    /**
     * The http client
     *
     * @var \BethelChika\Remita\HttpClient\ClientInterface
     */
    private $httpClient = null;

    /**
     * Initialise the Remita client.
     *
     *  Configuration settings include the following options:
     *
     * - username (string): the Remita API username, to be used in regular API requests.
     * - password (string): the Remita API password, to be used in OAuth requests.
     * - scheme (string): the Remita API scheme , to be used in OAuth requests.
     * - livemode (boolean): Determines if request to be made by the client will be in livemode or not.
     * - api_base (string): the API base for requests.
     * - auto_authenticate (boolean): Determines if the client should automatically authenticate with Remita.
     * 
     * @throws \BethelChika\Remita\Exception\AuthenticationException When auto_authenticate config option is true and authentication failed
     * @throws \BethelChika\Remita\Exception\InvalidArgumentException If the config is invalid
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {

        $config = \array_merge(self::DEFAULT_CONFIG, $config);
        $this->validateConfig($config);





        $this->config = $config;


        if ($config['auto_authenticate']) {
            $params = array_filter($config, function ($config_name) {
                return ($config_name == 'username' or $config_name == 'password' or $config_name == 'schemeid');
            }, \ARRAY_FILTER_USE_KEY);


            $authentication = $this->authentication->authenticate($params);
            Remita::$apiKey = $authentication->token;
        }
    }
    /**
     * @inheritDoc
     *
     */
    public function isLivemode()
    {
        return $this->config['livemode'];
    }
    /**
     * @inheritDoc
     *
     */
    public function getApiKey()
    {
        return $this->config['api_key'];
    }

    /**
     * @inheritDoc
     *
     */
    public function getScheme()
    {
        return $this->config['scheme'];
    }


    /**
     * @inheritDoc
     *
     */
    public function getUsername()
    {
        return $this->config['username'];
    }

    /**
     * @inheritDoc
     *
     */
    public function getPassword()
    {
        return $this->config['password'];
    }

    /**
     * @inheritDoc
     *
     */
    public function getApiBase()
    {
        if (is_null($this->config['api_base'])) {
            $this->config['api_base'] =  $this->isLivemode() ? self::DEFAULT_API_BASE : self::DEFAULT_DEMO_API_BASE;
        }

        return $this->config['api_base'];
    }
    /**
     * @var BethelChika\Remita\Service\CoreServiceFactory
     */
    private $coreServiceFactory;

    public function __get($name)
    {
        if (null === $this->coreServiceFactory) {
            $this->coreServiceFactory = new \BethelChika\Remita\Service\CoreServiceFactory($this);
        }

        return $this->coreServiceFactory->__get($name);
    }

    /**
     * Return the http client interface to be used
     *
     * @return \BethelChika\Remita\HttpClient\ClientInterface
     */
    private function getHttpClient()
    {
        if (is_null($this->httpClient)) {
            $this->httpClient = new LaravelHttpClient;
        }
        return $this->httpClient;
    }

    /**
     * Prep the request
     *
     * @param string $path The relative path of the request
     * @param string $params The request parameters
     * @param array $options KV pair of headers
     * @return array [$absPath,$raw_headers] 
     */
    private function _prepareRequest(string $path, $params, array $options)
    {

        $headers = [];

        // If this is authentication request, we do not need token else, we do
        if (($options['authentication_request'] ?? false) == true) {
            $params['rememberMe'] = true;
            $params['username'] = $this->getUsername();
            $params['password'] = $this->getPassword();
        } else {
            $headers['Authorization'] = 'Bearer ' . $this->apiKeyForRequest($options);
        }



        // Remove none header information from options
        $non_header_options = array_merge(self::NONE_HEADER_OPTIONS, self::INTERNAL_OPTIONS);
        $options = array_filter($options, function ($option_name) use ($non_header_options) {
            return !in_array($option_name, $non_header_options);
        }, \ARRAY_FILTER_USE_KEY);



        //
        $headers = array_merge($headers, $options);



        //
        $raw_headers = [];
        foreach ($headers as $header => $header_value) {
            $raw_headers[] = $header . ': ' . $header_value;
        }

        //
        $params['scheme'] = $this->getScheme();

        //
        $absPath = $this->getApiBase() . '/' . trim($path, '/');

        //
        return [$absPath, $raw_headers, $params];
    }
    /**
     * Perform request
     *
     */
    public function request($method, $path, $params, $options)
    {

        list($absUrl, $raw_headers, $params) = $this->_prepareRequest($path, $params, $options);

        
        [$response_body, $status, $response_headers] = $this->getHttpClient()->request($method, $absUrl, $raw_headers, $params);

        $options['response_headers']=$response_headers;
        $options['response_status']=$status;

        return \BethelChika\Remita\Util\Util::convertToRemitaObject(json_decode($response_body, true), $options, $options['object_name'] ?? null);
    }

    /**
     * @param array $opts Request header/options
     *
     * @throws \BethelChika\Remita\Exception\AuthenticationException
     *
     * @return string
     */
    private function apiKeyForRequest($opts)
    {
        $apiKey =  $this->getApiKey() ?: Remita::$apiKey;

        if (null === $apiKey) {
            $msg = 'No API key/token provided. Set your API key when constructing the '
                . 'RemitaClient instance';

            throw new \BethelChika\Remita\Exception\AuthenticationException($msg);
        }

        return $apiKey;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @throws \BethelChika\Remita\Exception\InvalidArgumentException
     */
    private function validateConfig($config)
    {
        // api_key
        if (null !== $config['api_key'] && !\is_string($config['api_key'])) {
            throw new \BethelChika\Remita\Exception\InvalidArgumentException('api_key must be null or a string');
        }

        // username
        if (null === $config['username'] or ('' === $config['username'])) {
            $msg = 'username cannot be the empty string or null';

            throw new \BethelChika\Remita\Exception\InvalidArgumentException($msg);
        }

        // password
        if (null === $config['password'] or ('' === $config['password'])) {
            $msg = 'password cannot be the empty string or null';

            throw new \BethelChika\Remita\Exception\InvalidArgumentException($msg);
        }


        // schemeid
        if (is_null($config['scheme'])) {
            $msg = 'scheme cannot be the empty string or null';

            throw new \BethelChika\Remita\Exception\InvalidArgumentException($msg);
        }


        // api_base
        if (null !== $config['api_base'] && !\is_string($config['api_base'])) {
            throw new \BethelChika\Remita\Exception\InvalidArgumentException('api_base must be null or a string');
        }

        // livemode
        if (!\is_bool($config['livemode'])) {
            throw new \BethelChika\Remita\Exception\InvalidArgumentException('livemode must be a boolean');
        }

        // auto_authenticate
        if (!\is_bool($config['auto_authenticate'])) {
            throw new \BethelChika\Remita\Exception\InvalidArgumentException('auto_authenticate must be a boolean');
        }
    }
}
