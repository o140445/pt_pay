<?php

namespace app\common\service\channels;

use fast\Http;
use think\Config;
use think\Log;

class HubPayChannel implements ChannelInterface
{
    public function config()
    {

    }

    public function getToken($channel) :string
    {
        $key  = 'HubPay:'.$channel['mch_id'];
        $cache = cache($key);
        if ($cache) {
            return $cache;
        }

        $data = [
            'code' => $channel['mch_id'],
            'token' => $channel['mch_key'],
        ];

        $url = $channel['gateway'].'/auth/token';
        $res = Http::postJson(
            $url,
            $data
        );

        Log::write('HubPay auth response: '.json_encode($res), 'info');
        //{
        //  "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI...",
        //  "expires_in": 3600
        //}
        if (isset($res['access_token']) && $res['access_token']) {
            $token = $res['access_token'];
            cache($key, $token, 3580);
            return $token;
        }

        return '';
    }

    public function pay($channel, $params): array
    {
        $token = $this->getToken($channel);
        if (!$token) {
            return [
                'status' => 0,
                'msg' => '获取token失败',
            ];
        }

       $data = [
           // 单位 分
           'amount' => (int)($params['amount'] * 100),
           'method' => 'PIX',
           "externalRef" => $params['order_no'],
           "customer" =>
                [
                     "name" => "Pietro Matheus Ramos",
                     "email" => "pietro_ramos@care-br.com",
                     "phone" => "97 98108-5306",
                     "document" => "294.423.412-94",
                    "address" => [
                        "street"=> "Rua Manoel Pinto Brandão, s/n",
                        "number"=>"107",
                        "city"=> "Anori",
                        "state"=> "AM",
                        "country"=> "Brasil",
                        "zip"=> "69440-970"
                    ]
                ],
           "items" => [[
                "title" => "Produto 1",
                "quantity" => 1,
                "price" => (int)($params['amount'] * 100),
               "tangible" => true,
           ]]
       ];

        $url = $channel['gateway'].'/transaction';
        $headers  = [
            'Authorization' => 'Bearer '.$token,
        ];

        $res = Http::postJson(
            $url,
            $data,
            $headers
        );

        Log::write('HubPay pay response: '.json_encode($res) .' data: '.json_encode($data), 'info');

        if (isset($res['msg']) || !isset($res['message']) || !$res['error']) {
            return [
                'status' => 0,
                'msg' => $res['msg'] ?? $res['message'] ?? $res['error'],
            ];
        }

        $pay_url = Config::get('pay_url') . '/index/pay/index?order_id=' . $params['order_no'];
        //{
        //  "id": "18017579377364992",
        //  "status": "PENDING",
        //  "total": 400,
        //  "method": "PIX",
        //  "qrcode": "00020101021226870014br.gov.bcb.pix2565qrcode.santsbank.com/dynamic/cf1e1064-930c-42ec-9abf-03871cad65c15204000053039865802BR5910SYFRA LTDA6009SAO PAULO62070503***63045B62",
        //  "invoice": null,
        //  "currency": "BRL",
        //  "identifier": "38169107220140032",
        //  "external_ref": "external_ref_order",
        //  "customer": {
        //    "id": "18017579247341568",
        //    "name": "John Doe",
        //    "email": "john.doe@example.com",
        //    "phone": "11999999999",
        //    "document": "12468239008",
        //  },
        //  "created_at": "2025-02-19T17:15:25.688Z",
        //  "updated_at": "2025-02-19T17:15:26.703Z",
        //}
        return [
            'status' => 1, // 状态 1成功 0失败
            'msg' => '', // 消息
            'order_id' => $res['id'], // 订单号
            'e_no' => '',
            'pay_url' => $pay_url, // 支付地址
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];
    }

    public function outPay($channel, $params): array
    {
        // TODO: Implement outPay() method.
    }

    public function payNotify($channel, $params): array
    {
        // TODO: Implement payNotify() method.
    }

    public function outPayNotify($channel, $params): array
    {
        // TODO: Implement outPayNotify() method.
    }
    public function getPayInfo($orderIn): array
    {
        // TODO: Implement getPayInfo() method.
    }
    public function getVoucher($channel, $params): array
    {
        // TODO: Implement getVoucher() method.
    }
    public function getNotifyType($params): string
    {
        // TODO: Implement getNotifyType() method.
    }
    public function parseVoucher($channel, $params): array
    {
        // TODO: Implement parseVoucher() method.
    }
    public function getVoucherUrl($params): string
    {
        // TODO: Implement getVoucherUrl() method.
    }
    public function response(): string
    {
        return "success";
    }
}