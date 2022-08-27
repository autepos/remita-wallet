<?php
namespace BethelChika\Remita\HttpClient;

use BethelChika\Remita\Exception\ApiErrorException;
use Illuminate\Http\Client\Factory as HttpClientFactory;

class LaravelHttpClient implements ClientInterface
{
    /**
     * The client
     *
     * @var \Illuminate\Http\Client\Factory
     */
    private $client;

    public function __construct()
    {
        $this->client=new HttpClientFactory;
    }

    public function request(string $method,string $absUrl,array $headers,array $params):array
    {
        // Convert raw headers to arrays
        $_headers=[];
        foreach($headers as $header){
            $h=explode(':',$header);
            $_headers[$h[0]]=trim($h[1]);
        }

        //
        $method=strtolower($method);

        //
        try {
            $response=$this->client
                ->retry(3, 100)
                ->withHeaders($_headers)
                ->{$method}($absUrl, $params);
        }catch(\Illuminate\Http\Client\RequestException $ex){
            $data=$ex->response->json();

            $remitaCode='unknown';
            if($data and isset($data['code'])){
                $remitaCode=$data['code'];
            }

            $message=($data and isset($data['message']))?$data['message'] :' ';

            $apiError=ApiErrorException::factory(
                $message,
                $ex->response->status(),
                $ex->response->body(),
                $ex->response->json(),
                $ex->response->headers(),
                $remitaCode
            );
            $apiError->response=['message'];
            throw $apiError;
        }
        return [$response->body(), $response->status(), $response->headers()];
    }
    

}