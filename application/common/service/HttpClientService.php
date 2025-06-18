<?php

namespace app\common\service;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\ConnectException;

class HttpClientService
{
    protected static $client;

    public static function getClient(): Client
    {
        if (!self::$client) {
            $stack = HandlerStack::create();
            $stack->push(self::retryMiddleware());

            self::$client = new Client([
                'handler' => $stack,
                'timeout' => 10,
                'connect_timeout' => 10,
                'headers' => [
                    'Connection' => 'keep-alive', // ⚠️ 关键
                ],
                'http_errors' => true,
            ]);
        }

        return self::$client;
    }

    protected static function retryMiddleware()
    {
        return Middleware::retry(
            function ($retries, $request, $response = null, $exception = null) {
                if ($retries >= 3) return false;
                if ($exception instanceof ConnectException) return true;
                if ($response && $response->getStatusCode() >= 500) return true;
                return false;
            },
            function ($retries) {
                return 100 * $retries;
            }
        );
    }
}