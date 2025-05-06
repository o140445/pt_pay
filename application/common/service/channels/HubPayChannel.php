<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use fast\Http;
use think\Config;
use think\Log;

class HubPayChannel implements ChannelInterface
{
    public function config()
    {
        return [

            [
                'name'=>'银行名称',
                'key'=>'bankName',
                'value'=>'',
            ],
            [
                'name'=>'CNPJ',
                'key'=>'cnpj',
                'value'=>'',
            ]
        ];

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

    public function getNotifyUrl($channel, $type)
    {
        return Config::get('pay_url') . '/api/v1/pay/' . $type . '/code/' . $channel['sign'];
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
                "unitPrice" => (int)($params['amount'] * 100),
                "tangible" => true,
           ]],
           "postback" =>   $this->getNotifyUrl($channel, "innotify"),
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

        //{"message":["items.0.property price should not exist","items.0.unitPrice must be an integer number"],"error":"Bad Request","statusCode":400}
        //"status":"PENDING"
        if (isset($res['msg']) || isset($res['message']) || isset($res['error']) || (isset($res['status']) && $res['status'] != 'PENDING')) {
            return [
                'status' => 0,
                'msg' => $res['msg'] ?? $res['message'][0] ?? $res['error'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',
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
        $token = $this->getToken($channel);
        if (!$token) {
            return [
                'status' => 0,
                'msg' => '获取token失败',
            ];
        }

        $extra = json_decode($params['extra'], true);

        // 类型转换 CPF = DOCUMENT
        if ($extra['pix_type'] == 'CPF') {
            $extra['pix_type'] = 'DOCUMENT';
        }

        $data = [
            'amount' => (int)($params['amount'] * 100),
            'key' => $extra['pix_key'],
            'key_type' => $extra['pix_type'],
            'postback' => $this->getNotifyUrl($channel, "outnotify"),
        ];

        $url = $channel['gateway'].'/withdraw/pix';

        $headers = [
            'Authorization' => 'Bearer '.$token,
        ];
        $res = Http::postJson(
            $url,
            $data,
            $headers
        );
        Log::write('HubPay outPay response: '.json_encode($res) .' data: '.json_encode($data), 'info');
        if (isset($res['msg']) || !isset($res['statusCode']) || $res['statusCode'] != 201) {
            // {"status":422,"content":{"name":"INSUFFICIENT_FUNDS","message":"Insufficient balance for withdrawal"}}
            return [
                'status' => 0,
                'msg' => $res['msg'] ?? $res['message'][0] ?? $res['error'] ?? $res['content']['message'] ?? '',
            ];
        }

        //{
        //  "statusCode": 201,
        //  "message": "WITHDRAW_REQUEST",
        //  "data": [
        //    {
        //      "amount": 200,
        //      "event": "WITHDRAWAL",
        //      "identifier": "38508223304568832",
        //      "status": "PENDING"
        //    }
        //  ],
        //  "timestamp": "2025-04-10T06:17:56.816Z"
        //}

        return [
            'status' => 1, // 状态 1成功 0失败
            'msg' => '', // 消息
            'order_id' => $res['data'][0]['identifier'], // 订单号
            'e_no' => '',
            'pay_url' => '', // 支付地址
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];

    }

    public function payNotify($channel, $params): array
    {
        // {
        //    "id": "18017579377364992",
        //    "status": "PENDING",
        //    "total": 400,
        //    "method": "PIX",
        //    "qrcode": "00020101021226870014br.gov.bcb.pix2565qrcode.santsbank.com/dynamic/cf1e1064-930c-42ec-9abf-03871cad65c15204000053039865802BR5910SYFRA LTDA6009SAO PAULO62070503***63045B62",
        //    "invoice": null,
        //    "currency": "BRL",
        //    "identifier": "38169107220140032",
        //    "external_ref": "external_ref_order",
        //    "customer": {
        //      "id": "18017579247341568",
        //      "name": "John Doe",
        //      "email": "john.doe@example.com",
        //      "phone": "11999999999",
        //      "document": "12468239008",
        //    },
        //    "created_at": "2025-02-19T17:15:25.688Z",
        //    "updated_at": "2025-02-19T17:15:26.703Z",
        //  },

        $status = OrderIn::STATUS_UNPAID;
        if ($params['status'] == 'APPROVED') {
            $status = OrderIn::STATUS_PAID;
        }

        if ($status == OrderIn::STATUS_UNPAID) {
            throw new \Exception('订单未支付');
        }

        return [
            'order_no' => '',
            'channel_no' => $params['id'],
            'amount' =>  number_format($params['total'] / 100, 2, '.', ''),
            'pay_date' => date('Y-m-d H:i:s', strtotime($params['created_at'])),
            'status' => $status,
            'e_no' => '',
            'data' => json_encode($params),
            'msg' => 'ok',
        ];
    }

    public function outPayNotify($channel, $params): array
    {
        //   {
        //    "id": "38508223304568832",
        //    "status": "PAID",
        //    "requested": 200,
        //    "paid": 200,
        //    "receipt": [
        //      {
        //        "status": "PAID",
        //        "amount": 200,
        //        "endtoend": "E3038525920250417164723123b9c2d",
        //        "identifier": "f49b3c1a-29d7-4c9e-8fa3-3e4c2f21e9a4",
        //        "receiver_name": "John doe",
        //        "receiver_bank": "BANCO INTER S.A.",
        //        "receiver_bank_ispb": "00416968"
        //      }
        //    ]
        //  },

        $status = OrderOut::STATUS_UNPAID;
        if ($params['status'] == 'PAID') {
            $status = OrderOut::STATUS_PAID;
        }
        if ($params['status'] == 'ERROR') {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('订单未支付');
        }

        return [
            'order_no' => '',
            'channel_no' => $params['id'],
            'amount' =>  number_format($params['paid'] / 100, 2, '.', ''),
            'pay_date' => date('Y-m-d H:i:s'),
            'status' => $status,
            'e_no' => $params['receipt'][0]['identifier'],
            'data' => json_encode($params),
            'msg' => 'ok',
        ];
    }
    public function getPayInfo($order): array
    {
        $response = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_REQUEST)->find();
        if (! $response) {
            throw new \Exception('支付信息获取失败！');
        }
        $response_data = json_decode($response['response_data'], true);
        // 使用 Endroid 6.x 生成二维码
        $builder = new Builder(
            writer: new PngWriter(),
            data: $response_data['qrcode'],
            size: 200,
            margin: 10
        );

        $result = $builder->build();

        // 获取 base64 图片数据
        $qrCodeBase64 = $result->getDataUri();


        return [
            'order_no' => $order['order_no'],
            'qrcode'=> $qrCodeBase64,
            'pix_code' => $response_data['qrcode'],
        ];
    }
    public function getVoucher($channel, $params): array
    {
        // TODO: Implement getVoucher() method.
    }
    public function getNotifyType($params): string
    {
        // 区分支付和提现
        if (isset($params['method']) && $params['method'] == 'PIX') {
            return 'in';
        }
        if (isset($params['receipt']) && $params['receipt']) {
            return 'out';
        }

        return '';
    }
    public function parseVoucher($channel, $order): array
    {
        $payer_name = $this->getExtraConfig($channel, 'bankName');
        $payer_account = $this->getExtraConfig($channel, 'cnpj');

        $data = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_CALLBACK)->find();
        if (!$data) {
            return [
                'status' => 0,
                'msg' => '凭证获取失败',
            ];
        }
        $data = json_decode($data['request_data'], true);
        $res['data'] = json_decode($data['data'],true);

        return [
            'pay_date' => date('Y-m-d H:i:s', strtotime($res['data']['horario'])),
            'payer_name' => $payer_name, // 付款人姓名
            'payer_account' =>  $payer_account, // 付款人CPF
            'e_no' => $order['e_no'], // 业务订单号
            'type' => 'cnpj',
        ];
    }
    public function getVoucherUrl($params): string
    {
        return   Config::get('pay_url').'/index/receipt/index?order_id='.$params['order_no'];

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

    public function response(): string
    {
        return "success";
    }
}