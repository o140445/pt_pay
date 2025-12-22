<?php

namespace app\common\service;

use app\common\model\merchant\Channel;
use think\Db;

class HookService
{
    const NOTIFY_TYPE_IN = 'in';
    const NOTIFY_TYPE_OUT_PAY = 'out';

    /**
     *  支付回调
     * @param $sign
     * @param $data
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function notify($sign, $data)
    {
        $channel = Channel::where('status', OrderInService::STATUS_OPEN)->where('sign', $sign)->find();
        if (!$channel) {
            throw new \Exception('未知支付渠道');
        }

        $paymentService = new PaymentService($channel->code);
        $notifyType = $paymentService->getNotifyType($data);


        if ($notifyType == self::NOTIFY_TYPE_IN) {
            $order = new OrderInService();
            $res = $order->notify($sign, $data);
            $res['type'] = self::NOTIFY_TYPE_IN;
            return $res;
        } elseif ($notifyType == self::NOTIFY_TYPE_OUT_PAY) {
            $order = new OrderOutService();
            $res = $order->notify($sign, $data);
            $res['type'] = self::NOTIFY_TYPE_OUT_PAY;
            return $res;
        }

        return [
            'order_id' => '',
            'msg' => $paymentService->response()
        ];
    }

    // setHook
    public function setHook($sign, $type)
    {
        $channel = Channel::where('status', OrderInService::STATUS_OPEN)->where('sign', $sign)->find();
        if (!$channel) {
            throw new \Exception('未知支付渠道');
        }

        $paymentService = new PaymentService($channel->code);
        $res = $paymentService->setHook($channel, $type);
        return $res;
    }
}