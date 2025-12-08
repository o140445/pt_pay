<?php

namespace app\common\service;

use GuzzleHttp\Client;

class HttpClientService
{
    protected static $client;

    public static function getClient(): Client
    {
        if (!self::$client) {
            self::$client = new Client([
                'timeout' => 10,
                'connect_timeout' => 10,
                'headers' => [
                    'Connection' => 'keep-alive',
                ],
                'http_errors' => true,
            ]);
        }

        return self::$client;
    }
}