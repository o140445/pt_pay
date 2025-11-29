<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use fast\Http;
use think\Config;
use think\Log;

class PPayChannel implements ChannelInterface
{

    const CUSTOMER_NAME = 'happay';

    const CUSTOMER_EMAIL = 'happay@gmail.com';

    const CUSTOMER_PHONE = '1234567890';
    
    public function config()
    {
        //appId
        return [
            [
                'name' => '应用ID',
                'key' => 'appId',
                'value' => '',
            ]
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

    public function getNotifyUrl($channel, $type)
    {
        return Config::get('pay_url') . '/api/v1/pay/' . $type . '/code/' . $channel['sign'];
    }

    public function sign($data, $key)
    {
        // 排序
        ksort($data);

        // 拼接
        $str = '';
        foreach ($data as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }

            $str .= $k . '=' . $v . '&';
        }

        $str .= 'key=' . $key;

        return strtoupper(md5($str));
    }

    public function pay($channel, $params): array
    {
        $data = [
            'mchNo' => $channel->mch_id,
            'appId' => $this->getExtraConfig($channel, 'appId'),
            'mchOrderNo' => $params['order_no'],
            'amount' => (int)(round($params['amount'], 2) * 100),
            'customerName' => self::CUSTOMER_NAME,
            'customerEmail' => self::CUSTOMER_EMAIL,
            'customerPhone' => self::CUSTOMER_PHONE,
            'notifyUrl' => $this->getNotifyUrl($channel, "innotify"),
        ];

        $data['sign'] = $this->sign($data, $channel->mch_key);
        $url  = $channel->gateway . '/api/pay/pay';
        $res = Http::postJson($url, $data);

        Log::write('PpPayChannel pay response:' . json_encode($res) . ' data:' . json_encode($data), 'info');
        if (!$res || $res['msg'] !== "SUCCESS" ) {
            return ['status' => 0, 'msg' => $res['msg']  ?? '下单失败'];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'pay_url' => $res['data']['payData'],
            'msg' => '', // 消息
            'order_id' => $res['data']['payOrderId'], // 订单号
            'e_no' => '',
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];
    }

    public function outPay($channel, $params): array
    {
        $extra = json_decode($params['extra'], true);

        // 如果是电话号码 并且是电话号码没有+55
        if ($extra['pix_type'] == 'PHONE' && strpos($extra['pix_key'], '+55') === false) {
            $extra['pix_key'] = '+55'.$extra['pix_key'];
        }

        $data = [
            'mchNo' => $channel->mch_id,
            'appId' => $this->getExtraConfig($channel, 'appId'),
            'mchOrderNo' => $params['order_no'],
            'amount' => (int)(round($params['amount'], 2) * 100),
            'entryType' => $extra['pix_type'],
            'accountNo' => $extra['pix_key'],
            'accountCode' => $extra['pix_key'],
            'accountName' => $extra['pix_name'],
            'accountEmail' => self::CUSTOMER_EMAIL,
            'accountPhone' => self::CUSTOMER_PHONE,
            'notifyUrl' => $this->getNotifyUrl($channel, "outnotify"),
        ];

        $data['sign'] = $this->sign($data, $channel->mch_key);
        $url  = $channel->gateway . '/api/payout/pay';
        $res = Http::postJson($url, $data);
        Log::write('PpPayChannel outPay response:' . json_encode($res) . ' data:' . json_encode($data), 'info');
        if (!$res || $res['msg'] !== "SUCCESS" ) {
            return ['status' => 0, 'msg' => $res['msg']  ?? '下单失败'];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'order_id' => $res['data']['transferId'], // 订单号
            'msg' =>  '', // 消息
            'e_no' =>  '', // 业务订单号
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];
    }

    public function payNotify($channel, $params): array
    {
        // {"amount":"1000","payOrderId":"P1870020340861177857","mchOrderNo":"DI20241220051605uZMGnm","appId":"6762b4fce4b0f0fdf578609b","sign":"BF40F8FAB2BD6E6472F96297241F028A","channelOrderNo":"2024122005160790271011","currency":"BRL","state":"2","mchNo":"M986066"}

        $sign = $params['sign'];
        unset($params['sign']);
        $mySign = $this->sign($params, $channel->mch_key);
        if ($sign !== $mySign) {
            throw new \Exception('签名错误');
        }

        //订单支付状态
        //0: 订单生成
        //1: 支付中
        //2: 支付成功
        //3: 支付失败
        //4: 已撤销
        //5: 已退款
        //6: 订单关闭
        //7: 订单待结算

        $status = OrderIn::STATUS_UNPAID;
        if ($params['state'] == '2') {
            $status = OrderIn::STATUS_PAID;
        }

        if ($params['state'] == '5') {
            Log::write('PPayChannel payNotify 退款', ['params' => $params]);
        }

        if ($status == OrderIn::STATUS_UNPAID) {
            throw new \Exception('未支付');
        }

        $amount = abs($params['amount']);
        $amount = substr($amount, 0, -2) . '.' . substr($amount, -2);

        return [
            'order_no' => $params['mchOrderNo'], // 订单号
            'channel_no' => $params['payOrderId'], // 渠道订单号
            'amount' => $amount, // 金额
            'pay_date' => '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => 'sucesso', // 消息
        ];

    }

    public function outPayNotify($channel, $params): array
    {
        //{"amount":"500","mchOrderNo":"DO20241220061907Mz7Rwy","appId":"6762b4fce4b0f0fdf578609b","errMsg":"","sign":"51E84BBDA8C3EBEA616ED3307271B866","currency":"BRL","state":"3","transferId":"T1870036203995729922","mchNo":"M986066"}

        $sign = $params['sign'];
        unset($params['sign']);
        $mySign = $this->sign($params, $channel->mch_key);
        if ($sign !== $mySign) {
            throw new \Exception('签名错误');
        }

        //订单支付状态
        //2: 支付成功
        // 3: 支付失败

        $status = OrderOut::STATUS_UNPAID;
        if ($params['state'] == '2') {
            $status = OrderOut::STATUS_PAID;
        }
        if ($params['state'] == '3') {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('未支付');
        }

        $amount = abs($params['amount']);
        $amount = substr($amount, 0, -2) . '.' . substr($amount, -2);

        return [
            'order_no' => $params['mchOrderNo'], // 订单号
            'channel_no' => $params['transferId'], // 渠道订单号
            'amount' => $amount, // 金额
            'pay_date' => '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $params['errMsg'] ?? 'sucesso', // 消息
        ];

    }


    public function getNotifyType($params): string
    {
        return '';
    }

    public function getPayInfo($orderIn): array
    {
       throw new \Exception('暂不支持支付信息获取');
    }

    public function getVoucher($channel, $params): array
    {
       return [
           'status' => 0,
           'msg' => '暂不支持凭证获取',
       ];
    }

    public function response(): string
    {
       return 'success';
    }

    public function parseVoucher($channel, $params): array
    {
        return [
            'status' => 0,
            'msg' => '暂不支持凭证解析',
        ];
    }

    public function getVoucherUrl($params): string
    {
        return '';
    }
}