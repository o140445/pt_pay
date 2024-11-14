<?php

namespace app\common\service\channels;

interface ChannelInterface
{
    // config
    public function config();

    // pay
    public function pay($channel, $params) : array;

    // outPay
    public function outPay($channel, $params) : array;

    // payNotify
    public function payNotify($channel, $params) : array;

    //outPayNotify
    public function outPayNotify($channel, $params) : array;

    // 返回
    public function response() : string;

    // getNotifyType
    public function getNotifyType($params) : string;
}