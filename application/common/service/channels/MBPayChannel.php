<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use app\common\service\OrderInService;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use fast\Http;
use think\Config;
use think\Log;

class MBPayChannel implements ChannelInterface
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
            ],
            [
                'name'=>'应用Token',
                'key'=>'applicationToken',
                'value'=>'',
            ],
            //CRP-TOKEN
            [
                'name'=>'CRP-TOKEN',
                'key'=>'crpToken',
                'value'=>'',
            ],
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
     * get token
     * @param $channel
     * @param $params
     * @return string
     */
    public function getToken($channel, $params): string
    {
        $key  = 'mb_pay:'.$channel['mch_id'];
        $cache = cache($key);
        if ($cache) {
            return $cache;
        }

        $data = [
            'clientid' => $channel['mch_id'],
            'clientsecret' => $channel['mch_key'],
            'grant_type' => 'password',
        ];

        $url = $channel['gateway'].'/token';
        $res = Http::postJson(
            $url,
            $data,
            [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
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
        $token = $this->getToken($channel, $params);
        if (!$token) {
            return [
                'code' => 0,
                'msg' => 'get token failed',
            ];
        }
        $data = [
            'amount' => (int)($params['amount'] * 100),
            'dueDate' => date('d/m/Y', strtotime($params['create_time'])),//vencimento (dd/mm/aaaa)
            'customer' =>   [
                "name" => "Pietro Matheus Ramos",
                "email" => "pietro_ramos@care-br.com",
                "address" => "Rua Manoel Pinto Brandão, s/n",
                "neighborhood" => "Rua Manoel Pinto Brandão, s/n",
                "city" => "Anori",
                "state" => "AM",
                "country" => "Brasil",
                "zipcode" => "69440-970",
                "cpfcnpj" => "294.423.412-94",
                ],
            'recipient' => [
                "name" => "Pietro Matheus Ramos",
                "cpfcnpj" => "294.423.412-94",
                "email" => "pietro_ramos@care-br.com",
            ],
            "additionalInformation" => [],
            // 大写
            "CustomId"=> $params['order_no'],
            "txId" => "",
            'confirmationUrl' => $this->getNotifyUrl($channel, "innotify"),
        ];

        $header = [
            'Authorization' => 'Bearer '.$token,
            'ApplicationToken' =>  $this->getExtraConfig($channel, 'applicationToken'),
            // Parâmetro opcional onde o cliente pode informar um código hash no momento do depósito. Ao utilizar o parâmetro x-request-id, o cliente deve enviar em /Maiúsculas.
//            'x-request-Id' => $params['order_no'],
        ];

        $url = $channel['gateway'].'/payment/pix';
        $res = Http::postJson(
            $url,
            $data,
            $header
        );

        Log::write('MBPayChannel pay response: '.json_encode($res).' data:'.json_encode($data).' header:'.json_encode($header), 'info');

        //{
        // "error": false,
        // "returnCode": "00",
        // "returnMessage": "Success",
        // "customId": "teste2",
        // "txId": "2e092ddb684e482f89c1e67c3ebe9320",
        // "id": "51366b31-a45b-40d7-b23d-7a760f43ec27",
        // "invoiceCode": "YUVXMX0",
        // "amount": 100,
        // "dueDate": "",
        //"qrCodeString":
        //"00020101021226830014br.gov.bcb.pix2561pix.delbank.com.br/v1/qrcode/vchargeGJQKuYYKtakwAE6dAm3tlFyPF5204
        //000053039865802BR5907DELBANK6007ARACAJU62070503***63045D80",
        //"qrCodeBase64":
        //"iVBORw0KGgoAAAANSUhEUgAAAZ8AAAGfCAYAAACA4t+UAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv
        //8Y”
        // "recurrence": false,
        // "split": false
        //}
        // 请求超时
        if (isset($res['msg']) && strpos($res['msg'], 'cURL error 7:') !== false) {
            // 重新请求
            $res = Http::postJson(
                $url,
                $data,
                $header
            );
        }


        if (isset($res['msg']) || isset($res['message']) ||  (isset($res['returnCode']) && $res['returnCode'] != '00')) {
            Log::write('MBPayChannel pay error: '.json_encode($res).' data:'.json_encode($data), 'error');
            return [
                'status' => 0,
                'msg' => $res['message'] ??  $res['returnMessage'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',
            ];
        }

        $pay_url = Config::get('pay_url') . '/pay/index?order_id=' . $params['order_no'];

        return [
            'status' => 1, // 状态 1成功 0失败
            'msg' => '', // 消息
            'order_id' => $res['id'], // 订单号
            'e_no' => '',
            'pay_url' => $pay_url, // 支付地址
            'pix_code' => $res['qrCodeString'] ?? '', // pix code
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];

    }

    public function getNotifyUrl($channel, $type)
    {
        return Config::get('notify_url') . '/v1/pay/' . $type . '/code/' . $channel['sign'];
    }

    public function outPay($channel, $params): array
    {
        $token = $this->getToken($channel, $params);
        if (!$token) {
            return [
                'code' => 0,
                'msg' => '获取token失败',
            ];
        }
        $extra = json_decode($params['extra'], true);
        // 如果是电话号码 并且是电话号码没有+55
        if ($extra['pix_type'] == 'PHONE' && strpos($extra['pix_key'], '+55') === false) {
            $extra['pix_key'] = '+55'.$extra['pix_key'];
        }

        $pix_type = 1;
        //idTipoChavePIX 转换 CNPJ/CPF/PHONE/EMAIL/EVP)
        if ($extra['pix_type'] == "PHONE") {
            $pix_type = 2;
        } elseif ($extra['pix_type'] == "EMAIL") {
            $pix_type = 3;
        } elseif ($extra['pix_type'] == "EVP") {
            $pix_type = 4;
        }

        $data = [
            'amount' => (int)($params['amount'] * 100),
            "favorecido" => [
               "nome" => $extra['pix_name'],
                "cpfcnpj"=> "63260232036",
                "chave" => [
                    "idTipoChavePIX" => $pix_type,
                    "chavePIX" => $extra['pix_key'],
                ]
            ],
            "customId" => $params['order_no'],
            "confirmationUrl" => $this->getNotifyUrl($channel, "outnotify"),
            "updateUrl" => $this->getNotifyUrl($channel, "outnotify"),
        ];

        $data['digitalSignature'] = $this->getDigitalSignature( $channel, $token);

        $header = [
            'Authorization' => 'Bearer '.$token,
            'ApplicationToken' =>  $this->getExtraConfig($channel, 'applicationToken'),
            ];

        $url = $channel['gateway'].'/transfer/pix';
        $res = Http::postJson(
            $url,
            $data,
            $header
        );

        Log::write('MBPayChannel outPay response: '.json_encode($res).' data:'.json_encode($data).' header:'.json_encode($header), 'info');
        
        if (isset($res['msg']) || isset($res['message']) ||  (isset($res['returnCode']) && $res['returnCode'] != '00')) {
            Log::write('MBPayChannel outPay error: '.json_encode($res).' data:'.json_encode($data), 'error');
            // 其他错误
            if (!isset($res['msg']) ) {
                return [
                    'status' => 0,
                    'msg' => $res['message'] ??  $res['returnMessage'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',
                ];
            }

            // 不是 cURL error 28 的错误
            if (isset($res['msg']) && strpos($res['msg'], 'cURL error 28') === false) {
                return [
                    'status' => 0,
                    'msg' => $res['message'] ??  $res['returnMessage'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',
                ];
            }
        }
        //{
        // "error": false,
        // "returnCode": "00",
        // "returnMessage": "Success",
        // "keyPIX": "ysilva@pigpag.com.br",
        // "nameAccount holder": "Yuri Monteiro da Silva",
        // "cpfCnpj": "12065348771",
        // "contaDto": {
        // "account": "722554403",
        // "typeAccount": 3,
        // "agency": "1",
        // "ISPB": "18236120"
        // },
        // "bancoDto": {
        // "description": "NU PAGAMENTOS S.A. - INSTITUIÇÃO DE PAGAMENTO",
        // "number": "18236120",
        // "ISPB": "18236120"
        // },
        // "ticket": "602ca15b159e4d52a4ff7f03e12682e5"
        //}
        return [
            'status' => 1, // 状态 1成功 0失败
            'msg' => '', // 消息
            'order_id' => $res['transactionId'] ?? '', // 订单号
            'e_no' => '',
            'pay_url' => '', // 支付地址
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];
    }

    public function getDigitalSignature( $channel, $token)
    {

// SANDBOX 密钥（作为 HMAC key）
        $hmacKey = $this->getExtraConfig($channel, 'crpToken');

// 执行 HMAC-SHA256
        $hmacRaw = hash_hmac('sha256', $token, $hmacKey, true);

// 转换为十六进制字符串
        $signHex = bin2hex($hmacRaw);
        return $signHex;
    }

    public function payNotify($channel, $params): array
    {
        //{
        //    "customId": "Pedido123",
        //    "invoiceCode": "INV-987654",
        //    "id": "TXN-ABC123456",
        //    "paymentmethod": "PIX",
        //    "installments": 1,
        //    "paymentDate": "2025-02-25T14:30:45.123",
        //    "total": 10000,
        //    "totalPaid": 10000,
        //    "original_currency": "BRL",
        //    "payment_currency": "BRL",
        //    "status": "Paid",
        //    "paid_by_cripto_currency": "false",
        //    "txId": "TXID-XYZ987654321",
        //    "endToEnd": "E2E-555888777",
        //    "nomePagador": "João da Silva",
        //    "CPFCNPJPagador": "12345678901"
        //}

        $status = OrderIn::STATUS_UNPAID;
        if ($params['status'] == 'Paid') {
            $status = OrderIn::STATUS_PAID;
        }

        if ($status != OrderIn::STATUS_PAID) {
            throw new \Exception('订单未支付');
        }

        return [
            'order_no' =>  $params['customId'],
            'channel_no' => '',
            'amount' =>  number_format($params['totalPaid'] / 100, 2, '.', ''),
            'pay_date' => date('Y-m-d H:i:s'),
            'status' => $status,
            'e_no' => $params['endToEnd'] ?? '',
            'data' => json_encode($params),
            'msg' => '',
        ];
    }

    public function outPayNotify($channel, $params): array
    {
        //{
        //    "error": false,
        //    "returnCode": "00",
        //    "returnMessage": "Success",
        //    "withdrawCode": "A1B2C3D4E5",
        //    "customId": "Pedido98765",
        //    "transactionId": "TXN-123456789",
        //    "authenticationCode": "E2E-987654321",
        //    "transactionCode": "E2E-987654321",
        //    "amount": 150000,
        //    "transaction": {
        //        "operationType": "PIX",
        //        "transactionDate": "2025-02-25T14:00:00",
        //        "completedDate": "2025-02-25T14:05:00",
        //        "chargebackDate": null,
        //        "paymentDate": "2025-02-25T14:05:00"
        //    },
        //    "recipient": {
        //        "recipientName": "Maria Oliveira",
        //        "recipientDocumentID": "12345678901",
        //        "recipientBankName": "Banco Exemplo S.A.",
        //        "recipientBankAgency": "1234",
        //        "recipientBankAccount": "987654-0",
        //        "recipientPIXKeyType": "CPF",
        //        "recipientPIXKey": "12345678901"
        //    },
        //    "requestDate": "2025-02-25T14:00:00",
        //    "paymentDate": "2025-02-25T14:05:00",
        //    "chargebackDate": null,
        //    "completionDate": "2025-02-25T14:05:00",
        //    "endToEnd": "E2E-987654321",
        //    "status": "Completed"
        //}

        $status = OrderOut::STATUS_UNPAID;
        if (isset($params['status']) && $params['status'] == 'Completed' && isset($params['returnCode']) && $params['returnCode'] == '00') {
            $status = OrderOut::STATUS_PAID;
        }
//        {"withdrawCode":"6831b3e7dd804","customId":"DO20250524085623KHpGdb","transactionId":"","transactionCode":"","updateCode":"02","updateMessage":"RETURNED TRANSACTION","amount":"30000","reason":"DICT entry not found. Please check if the key is correct."}
        if (isset($params['updateCode']) && isset($params['updateCode']) && ($params['updateCode'] == '03' || $params['updateCode'] == '02')) {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('订单未支付');
        }


        return [
            'order_no' => $params['customId'],
            'channel_no' => $params['transactionId'] ?? '',
            'amount' =>  number_format($params['amount'] / 100, 2, '.', ''),
            'pay_date' => date('Y-m-d H:i:s'),
            'status' => $status,
            'e_no' => $params['endToEnd'] ?? '',
            'data' => json_encode($params),
            'msg' => $params['reason'] ?? '',
        ];
    }

    public function response(): string
    {
        return 'ok';
    }

    public function getNotifyType($params): string
    {
        return '';
    }

    public function getPayInfo($order): array
    {
        $response = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_REQUEST)->find();
        if (! $response) {
            throw new \Exception('支付信息获取失败！');
        }
        $response_data = json_decode($response['response_data'], true);
        //{
        // "error": false,
        // "returnCode": "00",
        // "returnMessage": "Success",
        // "customId": "teste2",
        // "txId": "2e092ddb684e482f89c1e67c3ebe9320",
        // "id": "51366b31-a45b-40d7-b23d-7a760f43ec27",
        // "invoiceCode": "YUVXMX0",
        // "amount": 100,
        // "dueDate": "",
        //"qrCodeString":
        //"00020101021226830014br.gov.bcb.pix2561pix.delbank.com.br/v1/qrcode/vchargeGJQKuYYKtakwAE6dAm3tlFyPF5204
        //000053039865802BR5907DELBANK6007ARACAJU62070503***63045D80",
        //8Y”
        // "recurrence": false,
        // "split": false
        //}

        // 使用 Endroid 6.x 生成二维码
        $builder = new Builder(
            writer: new PngWriter(),
            data: $response_data['qrCodeString'],
            size: 200,
            margin: 10
        );

        $result = $builder->build();

        // 获取 base64 图片数据
        $qrCodeBase64 = $result->getDataUri();
        return [
            'order_no' => $order['order_no'],
            'qrcode'=> $qrCodeBase64,
            'pix_code' => $response_data['qrCodeString'] ?? '',
        ];
    }

    public function getVoucher($channel, $params): array
    {
        return [];
    }

    public function parseVoucher($channel, $order): array
    {
        $payer_name = $this->getExtraConfig($channel, 'bankName');
        $payer_account = $this->getExtraConfig($channel, 'cnpj');

//        $data = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_CALLBACK)->find();
//        if (!$data) {
//            return [
//                'status' => 0,
//                'msg' => '凭证获取失败',
//            ];
//        }
//        $data = json_decode($data['request_data'], true);
//        $res['data'] = json_decode($data['data'],true);

        return [
            'pay_date' => date('d/m/Y', strtotime($order['pay_success_date'])), // 付款日期
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
     * query order
     * @param $channel
     * @param $order_no
     */
    public function queryOrder($channel, $order_no): array
    {
        $token = $this->getToken($channel, []);
        if (!$token) {
            return [
                'code' => 0,
                'msg' => '获取token失败',
            ];
        }

        $header = [
            'Authorization' => 'Bearer '.$token,
            'ApplicationToken' =>  $this->getExtraConfig($channel, 'applicationToken'),
        ];

        $url = $channel['gateway'].'/transfer/getstatus?CustomId='.$order_no;
        $res = Http::getJson(
            $url,
            $header
        );
;
        Log::write('MBPayChannel queryOrder response: '.json_encode($res).' order_no:'.$order_no, 'info');

        if (isset($res['returnCode']) && $res['returnCode'] != '00') {
            Log::write('MBPayChannel queryOrder error: '.json_encode($res).' order_no:'.$order_no, 'error');
            return [
                'status' => 0,
                'msg' =>  $res['returnMessage'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',
            ];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'msg' => '', // 消息
            'data' => $res, // 响应数据
        ];
    }
}