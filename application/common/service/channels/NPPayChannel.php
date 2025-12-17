<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use app\common\service\HookService;
use fast\Http;
use think\Config;
use think\Log;

class NPPayChannel implements ChannelInterface
{
    // config
    public function config()
    {
        return [
            [
                'name' => '银行名称',
                'key' => 'bankName',
                'value' => '',
            ],
            [
                'name' => 'CNPJ',
                'key' => 'cnpj',
                'value' => '',
            ]
        ];
    }

    // getExtraConfig
    public function getExtraConfig($channel, $key)
    {
        $extraConfig = json_decode($channel['extra'], true);
        foreach ($extraConfig as $item) {
            if ($item['key'] == $key) {
                return $item['value'];
            }
        }

        return '';
    }
    //function generateBasicAuth(credentials: string): string {
    //    const base64Credentials = Buffer.from(credentials).toString('base64');
    //    return `Basic ${base64Credentials}`;
    // }

    // generateBasicAuth
    public function generateBasicAuth($mch_id, $mch_key)
    {
        return 'Basic ' . base64_encode($mch_id . ':' . $mch_key);
    }

    // pay
    public function pay($channel, $params): array
    {
        // 金额 元 转 分
        $amount = (int)($params['amount'] * 100);
        $data = [
            'value' => $amount,
            'expiration' => 3600,
        ];

        // 请求头
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $this->generateBasicAuth($channel['mch_id'], $channel['mch_key']),
        ];

        // 请求
        $response = Http::postJson($channel['gateway'] . '/payments/new', $data, $headers);
        Log::write('NPPayChannel pay response:' . json_encode($response) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');

        if (isset($response['message']) || isset($response['msg']) || isset($response['error'])) {
            return [
                'status' => 0,
                'msg' => $response['message'] ?? $response['msg'] ?? $response['error'],
            ];
        }

        $pay_url = Config::get('pay_url') . '/pay/index?order_id=' . $params['order_no'];
        return [
            'status' => 1,
            'pay_url' => $pay_url,
            'order_id' => $response['id'],
            'e_no' => '',
            'msg' => '', // 消息
            'pix_code' => $response['qrcode'] ?? '',
            'request_data' => json_encode($data),
            'response_data' => json_encode($response),
        ];
    }

    // outPay
    public function outPay($channel, $params): array
    {
        // 金额 元转分
        $amount = (int)($params['amount'] * 100);


        $extra = json_decode($params['extra'], true);

        // 如果是电话号码 并且是电话号码没有+55
        if ($extra['pix_type'] == 'PHONE' && strpos($extra['pix_key'], '+55') === false) {
            $extra['pix_key'] = '+55'.$extra['pix_key'];
        }

        // 如果类型是CPF, 去除.-等特殊字符
        if ($extra['pix_type'] == 'CPF') {
            $extra['pix_key'] = preg_replace('/[^0-9]/', '', $extra['pix_key']);
        }

        $data = [
            'type' =>$extra['pix_type'],
            'key' => $extra['pix_key'],
            'value' => $amount,
        ];

        // 请求头
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => $this->generateBasicAuth($channel['mch_id'], $channel['mch_key']),
        ];

        // 请求
        $response = Http::postJson($channel['gateway'] . '/payments/withdraw', $data, $headers);
        Log::write('NPPayChannel outPay response:' . json_encode($response) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');
        
        if (isset($response['message']) || isset($response['msg']) || isset($response['error'])) {
            return [
                'status' => 0,
                'msg' => $response['message'] ?? $response['msg'] ?? $response['error'],
            ];
        }
        
        return [
            'status' => 1, // 状态 1成功 0失败
            'order_id' => $response['id'] ?? '', // 订单号
            'msg' =>  '', // 消息
            'e_no' => '', // 业务订单号
            'request_data' => json_encode($params), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];
    }

    // payNotify
    public function payNotify($channel, $params): array
    {
        //{
        // "id": "79cc0edd-8cf7-4f8e-961a-6f3651915323",
        // "value": 10.50,
        // "status": "COMPLETED",
        // "type": "IN",
        // "endToEndId": "E9040088820241221195631832674555",
        // “payer”: {
        //     "name": "Joao da silva oliveira",
        //     "document": "123.456.789-10",
        //     "bank": "BANCO SANTANDER",
        // }


        if ($params['status'] != 'COMPLETED' && $params['type'] != 'IN') {
            throw new \Exception('订单未支付');
        }
        
        return [
            'order_no' => "",
            'channel_no' => $params['id'],
            'amount' => $params['value'],
            'pay_date' => date('Y-m-d H:i:s'),
            'status' => OrderIn::STATUS_PAID,
            'e_no' => $params['endToEndId'] ?? '',
            'data' => json_encode($params),
            'msg' => 'success',
        ];
    }

    // outPayNotify
    public function outPayNotify($channel, $params): array
    {
       //{
        // "id": "79cc0edd-8cf7-4f8e-961a-6f3651915323",
        // "value": 10.50,
        // "status": "COMPLETED", //PENDING | COMPLETED | ERROR | EXPIRED | REFUND
        // "type": "OUT",
        // "endToEndId": "E9040088820241221195631832674555",
        // “receiver”: {
        //     "name": "Joao da silva oliveira",
        //     "document": "123.456.789-10",
        //     "bank": "BANCO SANTANDER",
        // }
        // }

        if ($params['type'] != 'OUT') {
            throw new \Exception('订单类型错误');
        }

        $status = match($params['status']) {
            'COMPLETED' => OrderOut::STATUS_PAID,
            'ERROR' => OrderOut::STATUS_FAILED,
            'EXPIRED' => OrderOut::STATUS_FAILED,
            'REFUND' => OrderOut::STATUS_REFUND,
            default => OrderOut::STATUS_UNPAID,
        };

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('订单未处理');
        }

        return [
            'order_no' => "",
            'channel_no' => $params['id'],
            'amount' =>  $params['value'],
            'pay_date' => $status == OrderOut::STATUS_PAID ? date('Y-m-d H:i:s') : '',
            'status' => $status,
            'e_no' => $params['endToEndId'] ?? '',
            'data' => json_encode($params),
            'msg' => $status == OrderOut::STATUS_PAID ? '' : 'fail',
        ];

    }

    // response
    public function response(): string
    {
        return '';
    }

    public function getPayInfo($order) : array
    {
        $response = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_REQUEST)->find();
        if (! $response) {
            throw new \Exception('支付信息获取失败！');
        }
        $response_data = json_decode($response['response_data'], true);

        return [
            'order_no' => $order['order_no'],
            'qrcode'=> $response_data['qrcode_image'],
            'pix_code' => $response_data['qrcode'],
        ];
    }

    /**
     * 获取凭证
     */
    public function getVoucher($channel, $order) : array
    {
        return  [
            'created_at' => $order['created_at'],
            'e2e' => $order['e_no'],
        ];

    }

    /**
     * 解析凭证
     */
    public function parseVoucher($channel, $order) : array
    {

        $payer_name = $this->getExtraConfig($channel, 'bankName');
        $payer_account = $this->getExtraConfig($channel, 'cnpj');
        return [
            'pay_date' => $order['pay_success_date'], // 支付时间
            'payer_name' => $payer_name, // 付款人姓名B.B INVESTIMENT TRADING SERVICOS LTDA
            'payer_account' => $payer_account, // 付款人CPF 57.709.170/0001-67
            'e_no' => $order['e_no'], // 业务订单号
            'type' => 'cnpj', // 业务订单号
        ];
    }

    /**
     * getNotifyType 获取通知类型
     */
    public function getNotifyType($params) : string
    {
        // 如果status 包含 payment 是代收， withdraw 是代付 其他是其他
        if (isset($params['type'])) {
            if (strpos($params['type'], 'IN') !== false) {
                return HookService::NOTIFY_TYPE_IN;
            }
            if (strpos($params['type'], 'OUT') !== false) {
                return HookService::NOTIFY_TYPE_OUT_PAY;
            }
        }

        return '';
    }

    // getVoucherUrl
    public function getVoucherUrl($order): string
    {
        return   Config::get('pay_url').'/index/receipt/index?order_id='.$order['order_no'];
    }

    //makeSign
    public function makeSign(array $data, $secret): string
    {
        $request = request();
        $rawBody = $request->getContent(); // 原始 JSON 字符串
        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);
        return $expectedSignature;
    }
}