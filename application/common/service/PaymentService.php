<?php

namespace app\common\service;

use app\common\service\channels\AcaciaPayChannel;
use app\common\service\channels\ChannelInterface;

class PaymentService
{
    protected ChannelInterface $channel;

    const PAY_CHANNEL = [
        'AcaciaPay' => 'AcaciaPay',
    ];

    public function __construct(string $code)
    {
       switch ($code) {
           case 'AcaciaPay':
               $this->channel = new AcaciaPayChannel();
               break;
           default:
               throw new \Exception('未知支付渠道');
       }
    }

    public function pay($channel, $data)
    {
        return $this->channel->pay($channel, $data);
    }

    public function outPay($channel, $data)
    {
        return $this->channel->outPay($channel, $data);
    }

    public function payNotify($channel, $data)
    {
        return $this->channel->payNotify($channel, $data);
    }

    public function getConfig()
    {
        return $this->channel->config();
    }

    public function outPayNotify($channel, $data)
    {
        return $this->channel->outPayNotify($channel, $data);
    }

    public function response()
    {
        return $this->channel->response();
    }

    // 获取回调类型
    public function getNotifyType($data)
    {
        return $this->channel->getNotifyType($data);
    }

    // 获取支付信息
    public function getPayInfo($orderIn)
    {
        return $this->channel->getPayInfo($orderIn);
    }

    // 获取凭证
    public function getVoucher($channel, $data)
    {
        return $this->channel->getVoucher($channel, $data);
    }

    // 解析凭证
    public function parseVoucher($data)
    {
        return $this->channel->parseVoucher($data);
    }
}
