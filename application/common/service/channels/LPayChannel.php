<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use fast\Http;
use think\Config;
use think\Log;

class LPayChannel implements ChannelInterface
{

    public function getExtraConfig($channel, $key) {
        $extraConfig = json_decode($channel['extra'], true);
        foreach ($extraConfig as $item) {
            if ($item['key'] == $key) {
                return $item['value'];
            }
        }

        return '';
    }

    public function md5Sign($data, $key)
    {
        ksort($data);
        $sign = '';
        foreach ($data as $k => $v) {
            // attach 不参与签名
            if ($k == 'attach') {
                continue;
            }
            $sign .= $k . '=' . $v . '&';
        }
        $sign .= 'key=' . $key;

        return strtoupper(md5($sign));
    }

    public function config()
    {
       return [
           ['name'=>'代付通道编码','key'=>'passageInCode','type'=>'text','required'=>true],
           ['name'=>'代收通道编码','key'=>'passageOutCode','type'=>'text','required'=>true],
       ];
    }

    public function getNotifyUrl($channel, $type)
    {
        return Config::get('pay_url') . '/api/v1/pay/' . $type . '/code/' . $channel['sign'];
    }

    public function pay($channel, $params): array
    {
        $data = [
            'orderNo' => $params['order_no'],
            "memberCode" => $channel['mch_id'],
            'passageInCode' => $this->getExtraConfig($channel, 'passageInCode'),
            'orderAmount' => $params['amount'],
            'notifyurl' => $this->getNotifyUrl($channel, "innotify"),
            'callbackurl' => "a",
            'productName' => 'goods',
            'datetime' => strtotime($params['create_time']) * 1000,
        ];

        $header = [
            'sign' => $this->md5Sign($data, $channel['mch_key'])
        ];

        $api_url = $channel['gateway'] . '/v1/inorder/addInOrder';

        $response = Http::postJson($api_url, $data, $header);
        Log::write('LPayChannel pay response:' . json_encode($response) . ' data:' . json_encode($data), 'info');
        if (!$response || $response['code'] != 1) {
            return [
                'status' => 0,
                'msg' => $response['msg'] ?? '支付失败',
            ];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'pay_url' => $response['data']['orderurl'] ?? '', // 支付地址
            'msg' => '', // 消息
            'order_id' => $response['data']['transactionNo'], // 订单号
            'e_no' => '',
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];
    }

    public function outPay($channel, $params): array
    {
        $extra = json_decode($params['extra'], true);
        $data = [
            'memberCode' => $channel['mch_id'],
            'orderAmount' => $params['amount'],
            'memberOrderNo' => $params['order_no'],
            'notifyurl' => $this->getNotifyUrl($channel, "outnotify"),
            'datetime' => strtotime($params['create_time']) * 1000,
            'passageOutCode' => $this->getExtraConfig($channel, 'passageOutCode'),

            'orderCardNo' => $extra['pix_key'],
            'bankCode' => $extra['pix_type'],
            'attach' =>$extra['pix_key'],
            'orderUsername' => $extra['pix_name'],
        ];

        $header = [
            'sign' => $this->md5Sign($data, $channel['mch_key'])
        ];

        $api_url = $channel['gateway'] . '/v1/outorder/addOutOrder';

        $response = Http::postJson($api_url, $data, $header);
        Log::write('LPayChannel outPay response:' . json_encode($response) . ' data:' . json_encode($data), 'info');
        if (!$response || $response['code'] != 1) {
            return [
                'status' => 0,
                'msg' => $response['msg'] ?? '支付失败',
            ];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'order_id' => $response['data']['orderNo'], // 订单号
            'msg' => '', // 消息
            'e_no' => '', // 业务订单号
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];
    }

    public function payNotify($channel, $params): array
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $newSign = $this->md5Sign($params, $channel['mch_key']);
        if ($sign != $newSign) {
            throw new \Exception('签名错误');
        }
        $status = OrderIn::STATUS_UNPAID;

        if ($params['returncode'] == '00') {
            $status = OrderIn::STATUS_PAID;
        } elseif ($params['returncode'] == '33') {
            $status = OrderIn::STATUS_FAILED;
        }

        if ($status == OrderIn::STATUS_UNPAID) {
            throw new \Exception('未支付');
        }
        
        return [
            'order_no' => '', // 订单号
            'channel_no' => $params['orderNo'], // 渠道订单号
            'amount' => $params['amount'], // 金额
            'pay_date' => $status == OrderIn::STATUS_PAID ? date('Y-m-d H:i:s', time()) : '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $status == OrderOut::STATUS_PAID ? 'sucesso' : 'canceled', // 消息
        ];
    }


    public function outPayNotify($channel, $params): array
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $newSign = $this->md5Sign($params, $channel['mch_key']);
        if ($sign != $newSign) {
            throw new \Exception('签名错误');
        }
        $status = OrderOut::STATUS_UNPAID;

        if ($params['returncode'] == '00') {
            $status = OrderOut::STATUS_PAID;
        } elseif ($params['returncode'] == '33') {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('未支付');
        }

        return [
            'order_no' => '', // 订单号
            'channel_no' => $params['orderNo'], // 渠道订单号
            'amount' => $params['amount'], // 金额
            'pay_date' => $status == OrderOut::STATUS_PAID ? date('Y-m-d H:i:s', time()) : '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $status == OrderOut::STATUS_PAID ? 'sucesso' : 'canceled', // 消息
        ];
    }

    public function getVoucher($channel, $params): array
    {
        return [];
    }

    public function parseVoucher($channel): array
    {
        return [];
    }

    public function getVoucherUrl($params): string
    {
        return '';
    }

    public function response(): string
    {
        return "ok";
    }

    public function getPayInfo($orderIn): array
    {
        return [];
    }

    public function getNotifyType($params): string
    {
        return '';
    }



}