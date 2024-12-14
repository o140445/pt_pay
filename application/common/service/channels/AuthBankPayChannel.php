<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use app\common\service\HookService;
use fast\Http;
use think\Config;
use think\Log;

class AuthBankPayChannel implements ChannelInterface
{
    // headers
    protected $headers = [];

    public function config()
    {
        return [
            [
                'name'=>'Token',
                'key'=>'token',
                'value'=>'',
            ],
        ];
    }

    // setHeader
    public function setHeader($channel, $is_auth = false)
    {

        $this->headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($is_auth) {
            $token = $this->getAccessToken($channel);
            $this->headers['Authorization'] = 'Bearer ' . $token['accessToken'];
            $token = $this->getExtraConfig($channel, 'token');
            $this->headers['Token'] = $token;
        }

    }

    // getAccessToken
    public function getAccessToken($channel)
    {
        $key = 'auth_bank_token';
        $token = cache($key);
        if ($token) {
            return json_decode($token, true);
        }

        $url = $channel->gateway . '/no-auth/autenticacao/v1/api/login';
        $data = [
            'clientId' => $channel->mch_id,
            'clientSecret' => $channel->mch_key,
        ];

        $this->setHeader($channel);

        $res = Http::postJson($url, $data, $this->headers);

        Log::write('AuthBank getAccessToken', ['res' => $res, 'params' => $data]);

        if (!$res || isset($res['msg']) || (isset($res['sucesso']) && $res['sucesso'] == false)) {
            $msg = $res['msg'] ?? $res['mensagem'] ?? '获取token失败';
            throw new \Exception($msg);
        }

        // 设置缓存
        cache($key, json_encode($res), 86000);

        return $res;
    }

    public function pay($channel, $params) : array
    {
        $data = [
            'valor' => (int)(round($params['amount'], 2) * 100),
            'tempoExpiracao' => 3600,
        ];

        $this->setHeader($channel, true);

        $url = $channel->gateway . '/qrcode/v1/gerar';

        $res = Http::postJson($url, $data, $this->headers);
        Log::write('AuthBank pay', ['res' => $res, 'params' => $data]);
        if (!$res || isset($res['msg']) || (isset($res['sucesso']) && $res['sucesso'] == false)) {
            return ['status' => 0, 'msg' => $res['msg'] ?? $res['mensagem'] ?? '下单失败'];
        }

        $pay_url = Config::get('pay_url') . '/index/pay/index?order_id=' . $params['order_no'];

        return [
            'status' => 1, // 状态 1成功 0失败
            'pay_url' => $pay_url, // 支付地址
            'msg' => '', // 消息
            'order_id' => $res['txId'], // 订单号
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
            'idEnvio' => $params['order_no'],
            'valor' => (int)(round($params['amount'], 2) * 100),
            'chavePixDestino' => $extra['pix_key'],
        ];

        $this->setHeader($channel, true);

        $url = $channel->gateway . '/pix/v1/transferir';

        $res = Http::postJson($url, $data, $this->headers);

        Log::write('AuthBank outPay', ['res' => $res, 'params' => $data]);
        if (!$res || isset($res['msg']) || (isset($res['sucesso']) && $res['sucesso'] == false)) {
            return ['status' => 0, 'msg' => $res['msg'] ?? $res['mensagem'] ?? '下单失败'];
        }


        return [
            'status' => 1, // 状态 1成功 0失败
            'order_id' => $res['codigoTransacao'] ?? '', // 订单号
            'msg' =>  '', // 消息
            'e_no' =>  '', // 业务订单号
            'request_data' => json_encode($params), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];
    }

    public function outPayNotify($channel, $params): array
    {
       //{
        //  "evento": "PixOut",
        //  "token": "lerkh7r79nkdjda1yg9zxvuhvc64kbfethixv9k6vt7fxmx8",
        //  "idEnvio": "hayjqnv754f6f",
        //  "endToEndId": "E5151512675793904351659710459689",
        //  "codigoTransacao": "nbyayb64-pws9-4jd6-v4yg-5v04ljwqlpto",
        //  "status": "Em processamento",
        //  "chavePix": "teste@hotmail.com",
        //  "valor": -1,
        //  "horario": "2024-11-04T14:47:12.221Z",
        //  "recebedor": {
        //    "nome": "Teste da Silva LTDA",
        //    "codigoBanco": "13140088",
        //    "cpf_cnpj": "12345678912345"
        //  },
        //  "erro":{
        //  "origem": "Origem interna",
        //  "motivo": "Erro de processamento, transação não executada. Reenvie a transação"
        //  }
        //}

        $status = OrderOut::STATUS_UNPAID;
        if ($params['status'] == 'Sucesso') {
            $status = OrderOut::STATUS_PAID;
        }

        if ($params['status'] == 'Erro' || $params['status'] == 'Falha') {
            $status = OrderOut::STATUS_FAILED;
        }

        if  ($params['evento'] == 'PixOutReversalExternal' && $params['status'] == 'Sucesso') {
            $status = OrderOut::STATUS_REFUND;
        }

        return [
            'order_no' => $params['idEnvio'],
            'channel_no' => $params['codigoTransacao'],
            'amount' =>  bcdiv(abs($params['valor']), 100, 2),
            'pay_date' => date('Y-m-d H:i:s', strtotime($params['horario'])),
            'status' => $status,
            'eno' => $params['endToEndId'],
            'data' => json_encode($params),
            'msg' =>  $params['erro']['motivo'] ?? 'ok',
        ];
    }

    public function payNotify($channel, $params): array
    {
       //{
        //    "evento": "PixIn",
        //    "token": "j8m17eqrxblf6s38upn969qoxvdzca",
        //    "endToEndId": "E22ZDINC1P1NLTKLF16YJU0FTWS4",
        //    "txid": "qzzcp3e8srj383fy2kudy3jial",
        //    "codigoTransacao": "8ayu4nku-6dyr-umji-v9qz-illzklc4ex33",
        //    "chavePix": "8ayu4nku-6dyr-umji-v9qz-illzklc4ex33",
        //    "valor": 10,
        //    "horario": "2020-12-21T13:40:34.000Z",
        //    "infoPagador": "pagando o pix",
        //    "pagador": {
        //        "nome": "TESTE PAGADOR",
        //        "cpf_cnpj": "***.123.456-**",
        //        "codigoBanco": "00123456"
        //    }
        //}

        $status = OrderIn::STATUS_PAID;
        return [
            'order_no' => $params['txid'],
            'channel_no' => $params['codigoTransacao'],
            'amount' =>  bcdiv($params['valor'], 100, 2),
            'pay_date' => date('Y-m-d H:i:s', strtotime($params['horario'])),
            'status' => $status,
            'eno' => $params['endToEndId'],
            'data' => json_encode($params),
            'msg' => 'ok',
        ];
    }

    public function getNotifyType($params): string
    {
        // event == PixIn 是收款 event == PixOut 是付款
        if ($params['evento'] == 'PixIn') {
            return HookService::NOTIFY_TYPE_IN;
        }

        if ($params['evento'] == 'PixOut' || $params['evento'] == 'PixOutReversalExternal') {
            return HookService::NOTIFY_TYPE_OUT_PAY;
        }

        return '';

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
            'qrcode'=> "data:image/png;base64," .$response_data['qrcode']['imagem'],
            'pix_code' => $response_data['qrcode']['emv'],
        ];
    }

    public function getVoucher($channel, $order): array
    {
        $data = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_CALLBACK)->find();
        if (! $data) {
            return [
                'status' => 0,
                'msg' => '凭证获取失败',
            ];
        }
        $data['data'] = $data['response_data'];
        $data['status'] = 1;
        return $data;
    }

    public function parseVoucher($params): array
    {

        return [
            'pay_date' => date('Y-m-d H:i:s', strtotime($params['horario'])),
            'payer_name' => '', // 付款人姓名
            'payer_account' =>  '', // 付款人CPF
            'e_no' => $params['endToEndId'], // 业务订单号
            'type' => 'isbank', // 业务订单号
        ];
    }

    public function response(): string
    {
        return 'ok';
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

}