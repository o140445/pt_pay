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

class CooperChannel implements ChannelInterface
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

    // pay
    public function pay($channel, $params): array
    {
        $data['order'] = [
            'amount' => $params['amount'],
            'external_id' => $params['order_no'],
            'expiration_time' =>3600,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getToken($channel),
            'X-ClientId' => $channel['mch_id'],
        ];

        $response = Http::postJson($channel['gateway'] . '/pay/prd/order', $data, $headers);
        Log::write('CooperChannel pay response:' . json_encode($response) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');

        if (!isset($response['id'])) {
            return [
                'status' => 0,
                'msg' =>  $response[0]['errorMessage'] ?? $response['msg'] ?? $response['error'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',
            ];
        }


        $pay_url = Config::get('pay_url') . '/pay/index?order_id=' . $params['order_no'];
        return [
            'status' => 1,
            'pay_url' => $pay_url,
            'order_id' => $response['id'],
            'e_no' => '',
            'msg' => '', // 消息
            'pix_code' => $response['transaction']['transactionPix']['cobResponse']['pixCopiaECola'] ?? '',
            'request_data' => json_encode($data),
            'response_data' => json_encode($response),
        ];
    }

    // outPay
    public function outPay($channel, $params): array
    {
        $extra = json_decode($params['extra'], true);

        // 如果是电话号码 并且是电话号码没有+55
//        if ($extra['pix_type'] == 'PHONE' && strpos($extra['pix_key'], '+55') === false) {
//            $extra['pix_key'] = '+55'.$extra['pix_key'];
//        }

        // 如果类型是CPF, 去除.-等特殊字符
        if ($extra['pix_type'] == 'CPF') {
            $extra['pix_key'] = preg_replace('/[^0-9]/', '', $extra['pix_key']);
        }
        //pixCPF
        // The CPF (Cadastro de Pessoas Físicas) associated with the PIX transfer
        // pixCelular
        // This is the type of key-pix, from the recipient, that uses the Cell Phone.
        // pixEmail
        // This is the type of key-pix, of the recipient, that uses the E-mail.
        // pixAleatorio
        // This is the recipient's pix-key type, that uses a random key.

        $keyType = 'pixCPF';
        if ($extra['pix_type'] == 'PHONE') {
            $keyType = 'pixCelular';
        } else if ($extra['pix_type'] == 'EMAIL') {
            $keyType = 'pixEmail';
        } else if ($extra['pix_type'] == 'EVP') {
            $keyType = 'pixAleatorio';
        }

        $data = [
            'bankAccount' => [
                $keyType => $extra['pix_key']
            ],
            'amount' => $params['amount'],
            'externalId' => $params['order_no'],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getToken($channel),
            'X-ClientId' => $channel['mch_id'],
        ];

        $response = Http::postJson($channel['gateway'] . '/pay/prd/transfer', $data, $headers);
        Log::write('CooperChannel outPay response:' . json_encode($response) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');

        if (!isset($response['transferId'])) {
            return [
                'status' => 0,
                'msg' =>   $response[0]['errorMessage'] ?? $response['msg'] ?? $response['error'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',
            ];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'order_id' => $response['transferId'] ?? '', // 订单号
            'msg' =>  '', // 消息
            'e_no' => '', // 业务订单号
            'request_data' => json_encode($params), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];
    }

    // payNotify
    public function payNotify($channel, $params): array
    {
        // "id": "***277013930adabacc45224",
        // "orderId": "XXXX2F5A1D4D",
        // "amount": 5,
        // "amountPayed": 5,
        // "amountFormatted": "R$ 5,00",
        // "installments": 1,
        // "typeOrder": "pix",
        // "status": "payed",

        if ($params['status'] != '1' && $params['status'] != '5') {
            throw new \Exception('订单未支付');
        }

        $status = OrderIn::STATUS_UNPAID;
        if ($params['status'] == '1') {
            $status = OrderIn::STATUS_PAID;
        }
        if ($params['status'] == '5') {
            $status = OrderIn::STATUS_REFUND;
        }

        return [
            'order_no' => '',
            'channel_no' => $params['Id'],
            'amount' => $params['amountPayed'],
            'pay_date' => $status == OrderIn::STATUS_PAID ? date('Y-m-d H:i:s', time()) : '',
            'status' => $status,
            'e_no' => $params['endToEndId'] ?? '',
            'data' => json_encode($params),
            'msg' => $status == OrderIn::STATUS_PAID ? '' : $params['errorMessage'][0]['errorMessage'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',

        ];
    }

    // outPayNotify
    public function outPayNotify($channel, $params): array
    {
        //{
        // "id": "***277414303cf9550aba33e",
        // "amount": 0.1,
        // "status": "Transferred",
        // "endToEndId": "E11165756*****************R1I",
        // "account": {
        //     "ispb": "***65756",
        //     "owner": {
        //     "name": "Empresa X",
        //     "socialName": "Empresa X",
        //     "taxId": "**.***.***/****-**",
        //     "taxIdTypeName": "CPF",
        //     "pixKey": "***.***.***-**"
        //     }
        // },
        // "type": "pix",
        // "createdAt": "2025-10-29T17:21:21",
        // "transferDate": "2025-10-29T20:21:35.124Z",
        // "receiptURL": "***277414303cf9550aba33e"
        // }

        //*Transfer Return Codes*
        // 0 = created
        // 1 = transferred
        // 2 = error
        // 3 = scheduled
        // 4 = refunded

        $status = match ($params['status']) {
            '0' => OrderOut::STATUS_UNPAID,
            '1' => OrderOut::STATUS_PAID,
            '2' => OrderOut::STATUS_FAILED,
            '3' => OrderOut::STATUS_UNPAID,
            '4' => OrderOut::STATUS_REFUND,
            default => OrderOut::STATUS_UNPAID,
        };

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('订单未处理');
        }

        return [
            'order_no' => '',
            'channel_no' => $params['id'],
            'amount' => $params['amount'],
            'pay_date' => $status == OrderOut::STATUS_PAID ? date('Y-m-d H:i:s', time()) : '',
            'status' => $status,
            'e_no' => $params['endToEndId'],
            'data' => json_encode($params),
            'msg' => $status == OrderOut::STATUS_PAID ? '' : $params['errorMessage'][0]['errorMessage'] ?? 'Excepção de pagamento, por favor tente de novo mais tarde',
        ];

    }

    //getToken
    public function getToken($channel)
    {

        $key = 'cooper_token_' . $channel['mch_id'];
        $token = cache($key);
        if ($token) {
            return $token;
        }

        $data = [
            'clientId' => $channel['mch_id'],
            'clientSecret' => $channel['mch_key'],
            'X-ClientId' => $channel['mch_id'],
        ];

        $response = Http::postJson($channel['gateway'] . '/pay/prd/auth', $data);
//var_dump($channel['gateway'] . '/pay/prd/auth');die();
        if (!isset($response['token'])) {
            throw new \Exception("获取token失败");
        }
        //{
        // "token": "eyJhbGciOiJIUzI1NiIsImtpZCI6InNpbTIiLCJ0eXAiOiJKV1QifQ.eyJpZCI6Ik5qVmxNV1ExT1daa05HWXpZbVJpTXpBNVptTmtOREEwIiwibmFtZSI6IlBSSURFIFNFQ1VSSVRZIElOVEVMSUdFTkNJQSBESUdJVEFMIExUREEiLCJuYmYiOjE3MTA3ODU4NjQsImV4cCI6MTcxMDg3Mjg2NCwiaWF0IjoxNzEwNzg1ODY0fQ.fM58dvekqayeeFul9-Llap1QB58mJlMyDH9525pmdzI",
        // "expires": "2024-03-19T18:27:44.2186781Z"
        //}

        $time = strtotime($response['expires']) - time() > 0 ? strtotime($response['expires']) - time() : 3600;

        cache($key, $response['token'], $time - 60); // 提前60秒过期
        return $response['token'];
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

         // $response['transaction']['transactionPix']['pixCopiaECola']
         $builder = new Builder(
            writer: new PngWriter(),
            data: $response_data['transaction']['transactionPix']['cobResponse']['pixCopiaECola'],
            size: 200,
            margin: 10
        );

        $result = $builder->build();

        // 获取 base64 图片数据
        $qrCodeBase64 = $result->getDataUri();
 
         return [
             'order_no' => $order['order_no'],
             'qrcode'=> $qrCodeBase64,
             'pix_code' => $response_data['transaction']['transactionPix']['cobResponse']['pixCopiaECola'],
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
        
         return '';
     }
 
     // getVoucherUrl
     public function getVoucherUrl($order): string
     {
         return   Config::get('pay_url').'/index/receipt/index?order_id='.$order['order_no'];
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

    // setHook
    public function setHook($channel, $type = 'out')
    {
        $data = [
            'url' => $this->getNotifyUrl($channel, $type == 'out' ?  "outnotify" : "innotify"), 
            'type' => $type == 'out' ? 'pix_transfer' : 'pix_order',
        ];
        
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->getToken($channel),
            'X-ClientId' => $channel['mch_id'],
        ];

        $response = Http::postJson($channel['gateway'] . '/pay/prd/webhook', $data, $headers);
        Log::write('CooperChannel setHook response:' . json_encode($response) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');

        var_dump($response);
        exit;
    }

    public function getNotifyUrl($channel, $type)
    {
        return Config::get('notify_url') . '/v1/pay/' . $type . '/code/' . $channel['sign'];
    }
}