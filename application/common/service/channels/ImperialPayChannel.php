<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use app\common\service\HookService;
use app\common\service\OrderInService;
use fast\Http;
use think\Config;
use think\Log;

class ImperialPayChannel implements ChannelInterface
{
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
    public function config()
    {
        return [
            [
                "name" => "公钥",
                "key" => "public_key",
                "value" => "",
            ],
            [
                "name" => "私钥",
                "key" => "private_key",
                "value" => "",
            ]
        ];
    }

    public function setHeaders($channel)
    {
        return [
            "x-public-key" => $this->getExtraConfig($channel, 'public_key'),
            "x-secret-key" => $this->getExtraConfig($channel, 'private_key'),
        ];
    }

    public function pay($channel, $params): array
    {
        $data = [
            "identifier" => $params['order_no'],
            // 2位
            "amount" => (float)number_format($params['amount'], 2, '.', ''),
            "client" => [
                "name" => "Renan Renan Vicente das Neves",
                "email" => "renan.renan.dasneves@futureteeth.com.br",
                "phone" => "(97) 2982-7280",
                "document" => "576.457.682-23"
            ]
        ];

        $headers = $this->setHeaders($channel);

        //https://app.imperialpay.com.br/api/v1/gateway/pix/receive
        $url = $channel['gateway'] . "/gateway/pix/receive";
        $response = Http::postJson($url, $data, $headers);
        Log::write('ImperialPayChannel pay response:' . json_encode($response) . ' data:' . json_encode($data), 'info');
        if (!$response || isset($response['msg']) || isset($response['errorCode'])) {
            return [
                'status' => 0,
                'msg' =>  $response['msg'] ?? $response['message'] ?? '请求失败',
            ];
        }

        //{
        //  "transactionId": "clwuwmn4i0007emp9lgn66u1h",
        //  "status": "OK",
        //  "order": {
        //    "id": "cm92389asdaskdjkasjdka",
        //    "url": "https://api-de-pagamentos.com/order/cm92389asdaskdjkasjdka"
        //  },
        //  "pix": {
        //    "code": "00020101021126530014BR.GOV.BCB.PIX0136254e-7f7b-4f4a-8e4b-2b5b3d3b3d3d52040000530398654041.005802BR5923Nome do Beneficiário6008Brasília62070503***6304A8E3",
        //    "base64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABjElEQVR42mNk",
        //    "image": "https://api.gateway.com/pix/qr/00020101021126530014BR.GOV.BCB.PIX0136254e-7f7b-4f4a-8e4b-2b5b3d3b3d3d52040000530398654041.005802BR5923Nome do Beneficiário6008Brasília62070503***6304A8E3"
        //  }
        //}

        $pay_url = Config::get('pay_url') . '/index/pay/index?order_id=' . $params['order_no'];

        return [
            'status' => 1, // 状态 1成功 0失败
            'msg' => '', // 消息
            'order_id' => $response['order']['id'], // 订单号
            'e_no' => '',
            'pay_url' => $pay_url, // 支付地址
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];

    }

    public function outPay($channel, $params): array
    {
        // TODO: Implement outPay() method.
    }

    public function payNotify($channel, $params): array
    {
        //{
        //  "event": "TRANSACTION_PAID",
        //  "token": "y9funmim14",
        //  "offerCode": "ABCK181",
        //  "client": {
        //    "id": "tuznq01l06",
        //    "name": "John Doe",
        //    "email": "jondoe@gmail.com",
        //    "phone": "(11) 9 8888-7777",
        //    "cpf": "123.456.789-10",
        //    "cnpj": null,
        //    "address": {
        //      "country": "BR",
        //      "zipCode": "01304-000",
        //      "state": "SP",
        //      "city": "São Paulo",
        //      "neighborhood": "Consolação",
        //      "street": "Rua Augusta",
        //      "number": "6312",
        //      "complement": "6 andar"
        //    }
        //  },
        //  "transaction": {
        //    "id": "qa87z0hk6p",
        //    "status": "COMPLETED",
        //    "paymentMethod": "CREDIT_CARD",
        //    "originalCurrency": "USD",
        //    "originalAmount": 20,
        //    "currency": "BRL",
        //    "amount": 100,
        //    "createAt": "2025-04-29T23:27:11.188Z",
        //    "payedAt": "2025-04-29T23:28:11.188Z"
        //  },
        //  "subscription": null,
        //  "orderItems": [
        //    {
        //      "id": "8chesbv44u",
        //      "price": 100,
        //      "product": {
        //        "id": "tbsx49ctyu",
        //        "name": "Curso de marketing",
        //        "externalId": "KSA912"
        //      }
        //    }
        //  ],
        //  "trackProps": {
        //    "utm_id": "12345",
        //    "utm_source": "facebook",
        //    "utm_medium": "cpc",
        //    "utm_campaign": "lancamento",
        //    "utm_content": "newsletter",
        //    "utm_term": "summer+venda",
        //    "fbc": "fb.1.1234567890.0987654321",
        //    "fbp": "fb.1.0987654321.1234567890",
        //    "ip": "179.241.195.127",
        //    "country": "BR",
        //    "user_agent": "Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36",
        //    "zip_code": "01304-000",
        //    "city": "São Paulo",
        //    "state": "SP"
        //  }
        //}

        $status = OrderIn::STATUS_UNPAID;
        if (isset($params['event'])
            && $params['event'] == 'TRANSACTION_PAID'
            && isset($params['transaction'])
            && $params['transaction']['status'] == 'COMPLETED'
        ) {
            $status = OrderIn::STATUS_PAID;
        }

        if ($status == OrderIn::STATUS_UNPAID) {
            throw new \Exception('支付状态错误');
        }

        return [
            'order_no' => $params['transaction']['identifier'], // 订单号
            'channel_no' => $params['transaction']['id'], // 渠道订单号
            'amount' => $params['transaction']['amount'], // 支付金额
            'pay_date' => '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $status == OrderOut::STATUS_PAID ? 'sucesso' : 'canceled', // 消息
        ];

    }

    public function outPayNotify($channel, $params): array
    {
        // TODO: Implement outPayNotify() method.
    }

    public function getPayInfo($order): array
    {
        $response = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_REQUEST)->find();
        if (! $response) {
            throw new \Exception('支付信息获取失败！');
        }
        $response_data = json_decode($response['response_data'], true);

        return [
            'order_no' => $order['order_no'],
            'qrcode'=> "data:image/png;base64," .$response_data['pix']['base64'],
            'pix_code' => $response_data['pix']['code'],
        ];
    }

    public function response(): string
    {
        return "SUCCESS";
    }
    public function getVoucherUrl($params): string
    {
        // TODO: Implement getVoucherUrl() method.
    }

    public function parseVoucher($channel, $params): array
    {
        // TODO: Implement parseVoucher() method.
    }

    public function getNotifyType($params): string
    {
        //{
        //  "event": "TRANSACTION_PAID",
        //  "token": "y9funmim14",
        //  "offerCode": "ABCK181",
        //  "client": {
        //    "id": "tuznq01l06",
        //    "name": "John Doe",
        //    "email": "jondoe@gmail.com",
        //    "phone": "(11) 9 8888-7777",
        //    "cpf": "123.456.789-10",
        //    "cnpj": null,
        //    "address": {
        //      "country": "BR",
        //      "zipCode": "01304-000",
        //      "state": "SP",
        //      "city": "São Paulo",
        //      "neighborhood": "Consolação",
        //      "street": "Rua Augusta",
        //      "number": "6312",
        //      "complement": "6 andar"
        //    }
        //  },
        //  "transaction": {
        //    "id": "qa87z0hk6p",
        //    "status": "COMPLETED",
        //    "paymentMethod": "CREDIT_CARD",
        //    "originalCurrency": "USD",
        //    "originalAmount": 20,
        //    "currency": "BRL",
        //    "amount": 100,
        //    "createAt": "2025-04-29T23:27:11.188Z",
        //    "payedAt": "2025-04-29T23:28:11.188Z"
        //  },
        //  "subscription": null,
        //  "orderItems": [
        //    {
        //      "id": "8chesbv44u",
        //      "price": 100,
        //      "product": {
        //        "id": "tbsx49ctyu",
        //        "name": "Curso de marketing",
        //        "externalId": "KSA912"
        //      }
        //    }
        //  ],
        //}

        if (isset($params['event']) && $params['event'] == 'TRANSACTION_PAID' && isset($params['transaction'])) {
            return HookService::NOTIFY_TYPE_IN;
        }

        return  "";
    }
    public function getVoucher($channel, $params): array
    {
        // TODO: Implement getVoucher() method.
    }

}