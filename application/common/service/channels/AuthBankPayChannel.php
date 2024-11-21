<?php

namespace app\common\service\channels;

class AuthBankPayChannel implements ChannelInterface
{
    public function config()
    {
        return [];
    }
    public function pay($channel, $params) : array
    {
        return [
            'status' => 0,
            'msg' => 'error',
        ];
    }
    public function outPay($channel, $params): array
    {
        return [
            'status' => 0,
            'msg' => 'error',
        ];
    }

    public function outPayNotify($channel, $params): array
    {
        return [
            'status' => 0,
            'msg' => 'error',
        ];
    }

    public function payNotify($channel, $params): array
    {
       return [
            'status' => 0,
            'msg' => 'error',
        ];
    }

    public function getNotifyType($params): string
    {
        return '';
    }

    public function getPayInfo($orderIn): array
    {
        // TODO: Implement getPayInfo() method.
    }

    public function getVoucher($channel, $params): array
    {
        // TODO: Implement getVoucher() method.
    }

    public function parseVoucher($params): array
    {
        // TODO: Implement parseVoucher() method.
    }

    public function response(): string
    {
        return 'ok';
    }


}