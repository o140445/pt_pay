<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\service\HookService;
use fast\Http;
use think\Cache;
use think\Config;
use think\Log;

class AcaciaPayChannel implements ChannelInterface
{
    /**
     * config 配置
     */
    public function config()
    {
        return [
            [
                'name'=>'secureCode',
                'key'=>'secureCode',
                'value'=>'',
            ],
            [
                'name'=>'银行名称',
                'key'=>'bankName',
                'value'=>'',
            ]
        ];
    }

    /**
     * pay 支付
     */
    public function pay($channel, $params) : array
    {
//        $userId = $this->getExtraConfig($channel, 'userId');
        $data = [
            'userId' => $params['order_no'],
            'amount' => (float)$params['amount'],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'partnerId' => $channel['mch_id'],
            'authKey' => $channel['mch_key'],
        ];


        $response = Http::postJson($channel['gateway'].'/api/pix', $data, $headers);
        Log::write('AcaciaPayChannel pay response:'.json_encode($response) . ' data:'.json_encode($data) . ' headers:'.json_encode($headers), 'info');

        if (isset($response['error'])) {
            return [
                'status' => 0,
                'msg' => $response['error'],
            ];
        }

        $pay_url = Config::get('pay_url') . '/index/pay/index?order_id=' . $params['order_no'];

        // 缓存订单信息$response
        Cache::set('order_info_'.$params['order_no'], json_encode($response), 600);

        return [
            'status' => 1, // 状态 1成功 0失败
            'pay_url' => $pay_url, // 支付地址
            'msg' => '下单成功', // 消息
            'order_id' => $response['tx_id'], // 订单号
            'e_no' => '',
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];
    }

    /**
     * 获取扩展配置
     */
    public function getExtraConfig($channel, $key) {
        $extraConfig = json_decode($channel['extra'], true);
        foreach ($extraConfig as $item) {
            if ($item['key'] == $key) {
                return $item['value'];
            }
        }

        return '';
    }

    /**
     * outPay 出款
     */
    public function outPay($channel, $params) : array
    {
        $extra = json_decode($params['extra'], true);

        // 如果是电话号码 并且是电话号码没有+55
        if ($extra['pix_type'] == 'PHONE' && strpos($extra['pix_key'], '+55') === false) {
            $extra['pix_key'] = '+55'.$extra['pix_key'];
        }

        $data = [
            'userId' => (int)$channel['mch_id'],
            'amount' => (float)$params['amount'],
            'pixKeyType' => $extra['pix_type'] == "PHONE" ? "TELEFONE" : $extra['pix_type'], // PHONE TELEFONE CPF CNPJ
            'pixKey' => $extra['pix_key']
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'partnerId' => $channel['mch_id'],
            'authKey' => $channel['mch_key'],
        ];

        $url = $channel['gateway'].'/api/withdraw';
        $res = Http::postJson($url, $data, $headers);

        Log::write('AcaciaPayChannel outPay response:'.json_encode($res) . ' data:'.json_encode($data) . ' headers:'.json_encode($headers), 'info');
        if (isset($res['error'])) {
            return [
                'status' => 0,
                'msg' => $res['error'],
            ];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'order_id' => $res['tx_id'], // 订单号
            'msg' =>  '下单成功', // 消息
            'e_no' => '', // 业务订单号息
            'request_data' => json_encode($params), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];
    }

    /**
     * payNotify 支付回调
     */
    public function payNotify($channel, $params) : array
    {
        $secureCode = $this->getExtraConfig($channel, 'secureCode');
        if ($params['header']['securecode'] != $secureCode) {
            throw new \Exception('secureCode 验证失败');
        }

       $status = OrderIn::STATUS_UNPAID;
        if ($params['status'] == 'payment.paid') {
            $status = OrderIn::STATUS_PAID;
        }
        if ($params['status'] == 'payment.canceled') {
            $status = OrderIn::STATUS_FAILED;
        }

        if ($status == OrderIn::STATUS_UNPAID) {
            throw new \Exception('支付状态错误');
        }

        return [
            'order_no' => $params['user_id'], // 订单号
            'channel_no' => $params['tx_id'], // 渠道订单号
            'amount' => $params['valor_cobrado'], // 金额
            'pay_date' => '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $status == OrderOut::STATUS_PAID ? '支付成功' : '支付失败', // 消息
        ];
    }

    /**
     * outPayNotify 出款回调
     */
    public function outPayNotify($channel, $params) : array
    {
        $secureCode = $this->getExtraConfig($channel, 'secureCode');
        if ($params['header']['securecode'] != $secureCode) {
            throw new \Exception('secureCode 验证失败');
        }

        $status = OrderOut::STATUS_UNPAID;
        if ($params['status'] == 'withdraw.paid') {
            $status = OrderOut::STATUS_PAID;
        }
        if ($params['status'] == 'withdraw.failed' || $params['status'] == 'withdraw.cancelled') {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('支付状态错误');
        }

        return [
            'order_no' => "", // 订单号
            'channel_no' => $params['data']['tx_id'], // 渠道订单号
            'pay_date' => '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' =>  '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $status == OrderOut::STATUS_PAID ? '支付成功' : '支付失败', // 消息
        ];
    }

    /**
     * response 返回
     */
    public function response() : string
    {
        return 'success';
    }

    /**
     * getNotifyType 获取通知类型
     */
    public function getNotifyType($params) : string
    {
        // 如果status 包含 payment 是代收， withdraw 是代付 其他是其他
        if (isset($params['status'])) {
            if (strpos($params['status'], 'payment') !== false) {
                return HookService::NOTIFY_TYPE_IN;
            }
            if (strpos($params['status'], 'withdraw') !== false) {
                return HookService::NOTIFY_TYPE_OUT_PAY;
            }
        }

        return '';
    }
}