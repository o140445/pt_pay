<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use app\common\service\HookService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use fast\Http;
use think\Cache;
use think\Config;
use think\Log;

class SplitPayChannel implements ChannelInterface
{
    public function config()
    {
        return [
            //companyId
            [
                'name' => '公司ID',
                'key' => 'company_id',
                'value' => '',
            ]
        ];
    }

    // pay
    public function pay($channel, $params): array
    {
        // 金额 元转分
        $amount = (int)($params['amount'] * 100);

        $data = [
            'amount' => $amount,
            'currency' => 'BRL',
            'paymentMethod' => 'PIX',
            'installments' => 1,
            'customer' => [
                'id' => 'CUST-123',
                'name' => 'João Teste',
                'email' => 'joao.teste@example.com',
                'document' => [
                    'number' => '24577481600',
                    'type' => 'CPF'
                ],
                'phone' => '11987654321',
                'externalRef' => 'LEAD-1234'
            ],
            'shipping' => [
                'fee' => 0,
                'address' => [
                    'street' => 'Rua dos Testes',
                    'streetNumber' => '123',
                    'complement' => 'Sala 2',
                    'zipCode' => '01001000',
                    'neighborhood' => 'Centro',
                    'city' => 'São Paulo',
                    'state' => 'SP',
                    'country' => 'BR'
                ]
            ],
            'items' => [
                [
                    'title' => 'Produto X',
                    'unitPrice' => 500,
                    'quantity' => 1,
                    'tangible' => true,
                    'externalRef' => 'SKU-001'
                ]
            ],
            'pix' => [
                'expiresInDays' => 1
            ],
            'postbackUrl' => $this->getNotifyUrl($channel, 'innotify'),
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Client-Id' => $channel['mch_id'],
            'X-API-Key' => $channel['mch_key'],
            'Idempotency-Key' => $params['order_no'],
        ];

        $url = $channel['gateway'] . '/api/v1/transactions/create';
        $response = Http::postJson($url, $data, $headers);

        Log::write('SplitPayChannel pay response:' . json_encode($response) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');
        if (!$response || !isset($response['data'])) {
            return [
                'status' => 0,
                'msg' => $response['message'] ?? $response['msg'] ?? 'Request failed',
            ];
        }

        $pay_url = Config::get('pay_url') . '/pay/index?order_id=' . $params['order_no'];

//        echo json_encode($response);die();
        return [
            'status' => 1,
            'pay_url' => $pay_url,
            'order_id' => $response['data']['id'],
            'e_no' => '',
            'msg' => '', // 消息
            'pix_code' => $response['data']['pix']['brcode'] ?? '',
            'request_data' => json_encode($data),
            'response_data' => json_encode($response),
        ];
    }

    // outPay
    public function outPay($channel, $params): array
    {
        //{
        // "companyId": 1,
        // "amount": 2500,
        // "currency": "BRL",
        // "pixKey": "50651470862",
        // "pixKeyType": "CPF",
        // }

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
            'companyId' => $this->getExtraConfig($channel, 'company_id'),
            'amount' => $amount,
            'currency' => 'BRL',
            'pixKey' => $extra['pix_key'],
            'pixKeyType' => $extra['pix_type'],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Client-Id' => $channel['mch_id'],
            'X-API-Key' => $channel['mch_key'],
            'Idempotency-Key' => $params['order_no'],
        ];

        $url = $channel['gateway'] . '/api/Withdraw/request';
        $response = Http::postJson($url, $data, $headers);

        if (!$response || isset($response['code']) || isset($response['message'])) {
            return [
                'status' => 0,
                'msg' => $response['message'] ?? $response['msg'] ?? 'Request failed',
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


    public function payNotify($channel, $params): array
    {
        //{
        // "object": "transaction",
        // "type": "cashin",
        // "status": "successful",
        // "companyId": 1,
        // "transactionId": "6dcf2aee0d6148e1a12b78db78",
        // "subTransactionId": 41394,
        // "externalRef": "LEAD-1764252408748_52740",
        // "method": "pix",
        // "value": 500,
        // "amount": 5,
        // "currency": "BRL",

        // "endToEndId": "E22896431202511271407sjUGQflhcVE",
        // "providerEndToEndId": "E22896431202511271407sjUGQflhcVE",
        // "providerTxId": "6dcf2aee0d6148e1a12b78db78",
        // "pixKey": "c32361fa-44de-4be7-815f-0e782a10860c",
        // "providerAmount": 5,
        // "providerCreditedAt": "2025-11-27T11:07:14.997603",
        // "providerPayload": { },

        // "payer": {
        //     "name": "ANGELO ALVES DE MARCHI",
        //     "documentId": "50651470862",
        //     "bankName": null,
        //     "ispb": null
        // },
        // "receiver": {
        //     "name": null,
        //     "documentId": "48969523000177"
        // },

        // "processedAt": "2025-11-27T11:07:14.997603Z"
        // }
        if ($params['status'] != 'successful') {
            throw new \Exception('订单未支付');
        }

        return [
            'order_no' =>  $params['providerTxId'],
            'channel_no' => $params['transactionId'],
            'amount' =>  number_format($params['amount'] / 100, 2, '.', ''),
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
        // "object": "withdraw",
        // "type": "cashout",
        // "status": "successful",
        // "companyId": 1,
        // "withdrawId": 7184,

        // "value": 10,
        // "valueInCents": 1000,
        // "currency": "BRL",

        // "provider": "A55",
        // "providerStatus": 1,
        // "providerTid": "wld-00007184",
        // "providerPaymentId": "uuid-provider",
        // "endToEndId": "Exxxxxxxx",

        // "providerAmount": 10,
        // "providerConfirmedAt": "2025-11-27T10:12:33Z",
        // "providerDebitedAt": null,
        // "providerPayload": { ... },

        // "pixKey": "chave",
        // "pixKeyType": "CPF",
        // "creditorDocument": "xxx",

        // "payer": {
        //     "name": "NOME DO BANCO/A55",
        //     "document": "xxx",
        //     "ispb": "xxxxx",
        //     "agency": "xxxx",
        //     "account": "xxxx"
        // },

        // "receiver": {
        //     "name": "NOME DO CLIENTE",
        //     "document": "xxx",
        //     "ispb": "xxxx",
        //     "agency": "xxxx",
        //     "account": "xxxx"
        // },

        // "createdAt": "2025-11-27T00:00:00Z",
        // "updatedAt": "2025-11-27T00:00:00Z",
        // "processedAt": "2025-11-27T00:00:00Z",

        // "metadata": null
        $status = OrderOut::STATUS_UNPAID;
        if ($params['status'] == 'successful') {
            $status = OrderOut::STATUS_PAID;
        }
        if ($params['status'] == 'failure') {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($params['status'] == 'refunded') {
            $status = OrderOut::STATUS_REFUND;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('订单未支付');
}
       return [
            'order_no' => $params['providerTxId'],
            'channel_no' => $params['withdrawId'] ?? '',
            'amount' =>  number_format($params['amount'] / 100, 2, '.', ''),
            'pay_date' => date('Y-m-d H:i:s'),
            'status' => $status,
            'e_no' => $params['endToEndId'] ?? '',
            'data' => json_encode($params),
            'msg' => $params['status'] == 'successful' ? 'success' : 'failure',
        ];
    }

    // response
    public function response(): string
    {
        return 'success';
    }

    // getNotifyType
    public function getNotifyType($params): string
    {
        return '';
    }

    public function getNotifyUrl($channel, $type)
    {
        return Config::get('notify_url') . '/v1/pay/' . $type . '/code/' . $channel['sign'];
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

    public function getPayInfo($order) : array
    {
        $response = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_REQUEST)->find();
        if (! $response) {
            throw new \Exception('支付信息获取失败！');
        }
        $response_data = json_decode($response['response_data'], true);

        //{
        //    "data": {
        //        "id": "cdf493737be549ed8163d312bc",
        //        "externalRef": "LEAD-1234",
        //        "amount": 500,
        //        "refundedAmount": 0,
        //        "providerCompanyId": "b6877393-8a98-4e4e-8a20-e086aacf3d8c",
        //        "companyId": 68,
        //        "installments": 1,
        //        "paymentMethod": "PIX",
        //        "status": "WAITING_PAYMENT",
        //        "postbackUrl": "https:\/\/www.tikpaysp.com\/api\/v1\/pay\/innotify\/code\/xuOE",
        //        "metadata": null,
        //        "traceable": true,
        //        "secureId": null,
        //        "secureUrl": null,
        //        "pix": {
        //            "brcode": "00020101021226820014br.gov.bcb.pix2560qrcode.pagsm.com.br\/pix\/607f6ae2-5c42-415f-96ce-fff1d1fccfbb5204000053039865802BR5907WLStore6008SaoPaulo61080100100062070503***630428CC",
        //            "qrcode": null,
        //            "payloadBase64": "MDAwMjAxMDEwMjEyMjY4MjAwMTRici5nb3YuYmNiLnBpeDI1NjBxcmNvZGUucGFnc20uY29tLmJyL3BpeC82MDdmNmFlMi01YzQyLTQxNWYtOTZjZS1mZmYxZDFmY2NmYmI1MjA0MDAwMDUzMDM5ODY1ODAyQlI1OTA3V0xTdG9yZTYwMDhTYW9QYXVsbzYxMDgwMTAwMTAwMDYyMDcwNTAzKioqNjMwNDI4Q0M",
        //            "imagemQrCodeInBase64": null,
        //            "qrcodeBase64": null,
        //            "txId": "cdf493737be549ed8163d312bc",
        //            "expiresAt": "2025-12-10T08:40:41.1759895+00:00"
        //        }
        //    },
        //    "status": 200,
        //    "message":"Transa\u00e7\u00e3o
        //criada e registrada com sucesso."}

         // 使用 Endroid 6.x 生成二维码
         $builder = new Builder(
            writer: new PngWriter(),
            data: $response_data['data']['pix']['brcode'],
            size: 200,
            margin: 10
        );

        $result = $builder->build();

        // 获取 base64 图片数据
        $qrCodeBase64 = $result->getDataUri();

        return [
            'order_no' => $order['order_no'],
            'qrcode'=> $qrCodeBase64,
            'pix_code' => $response_data['data']['pix']['brcode'],
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

        //{
        //    "tx_id": "595f42802f4579b58b44c2b0d21abe",
        //    "copia_e_cola": "00020126850014br.gov.bcb.pix2563pix.voluti.com.br/qr/v3/at/a62c3200-8944-48c1-aca8-af816ed0ee925204000053039865802BR5925MEGA_SERVICOS,_TECNOLOGIA6002SP62070503***6304D208",
        //    "qrcode": "iVBORw0KGgoAAAANSUhEUgAAAUAAAAFACAIAAABC8jL9AAAACXBIWXMAAA7EAAAOxAGVKw4bAAAJKkl...etc",
        //    "amount": "5.00",
        //    "method_code": "pix",
        //    "user_id": "123",
        //    "status": "paid",
        //    "payer_name": "付款人姓名",
        //    "ispb": "付款人CPF",
        //    "e2e": "E00416968202411051528kRNvgsChncG",
        //    "created_at": "05/11/2024 12:27",
        //    "updated_at": "05/11/2024 20:28"
        //}
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

    public function getVoucherUrl($order): string
    {
        return   Config::get('pay_url').'/index/receipt/index?order_id='.$order['order_no'];
    }

}