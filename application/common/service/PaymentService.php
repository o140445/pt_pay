<?php

namespace app\common\service;

use app\common\service\channels\AcaciaPayChannel;
use app\common\service\channels\AcPayChannel;
use app\common\service\channels\AuthBankPayChannel;
use app\common\service\channels\BPPayChannel;
use app\common\service\channels\ChannelInterface;
use app\common\service\channels\FixPayChannel;
use app\common\service\channels\HeyPayChannel;
use app\common\service\channels\HwPayChannel;
use app\common\service\channels\PPayChannel;

class PaymentService
{
    protected ChannelInterface $channel;

    const PAY_CHANNEL = [
        'AcaciaPay' => 'AcaciaPay',
        'HwPay' => 'HwPay',
        'FixPay' => 'FixPay',
        'HeyPay' => 'HeyPay',
        'AcPay' => 'AcPay',
        'AuthBankPay' => 'AuthBankPay',
        'PPay' => 'PPay',
        'BPPay' => 'BPPay',
    ];

    public function __construct(string $code)
    {
        switch ($code) {
            case 'AcaciaPay':
                $this->channel = new AcaciaPayChannel();
                break;
            case 'HwPay':
                $this->channel = new HwPayChannel();
                break;
            case 'FixPay':
                $this->channel = new FixPayChannel();
                break;
            case 'HeyPay':
                $this->channel = new HeyPayChannel();
                break;
            case 'AcPay':
                $this->channel = new AcPayChannel();
                break;
            case 'AuthBankPay':
                $this->channel = new AuthBankPayChannel();
                break;
            case 'PPay':
                $this->channel = new PPayChannel();
                break;
            case 'BPPay':
                $this->channel = new BPPayChannel();
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
    public function parseVoucher($channel, $data)
    {
        return $this->channel->parseVoucher($channel, $data);
    }

    // getVoucherUrl
    public function getVoucherUrl($data)
    {
        return $this->channel->getVoucherUrl($data);
    }
}
