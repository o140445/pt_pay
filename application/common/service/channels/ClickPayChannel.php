<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use app\common\service\HookService;
use fast\Http;
use think\Cache;
use think\Config;
use think\Log;

class ClickPayChannel implements ChannelInterface
{
    /**
     * config 配置
     */
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

    /**
     * pay 支付
     */
    public function pay($channel, $params): array
    {
//        $userId = $this->getExtraConfig($channel, 'userId');
        $data = [
            'userId' => $params['order_no'],
            'amount' => (float)$params['amount'],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'partnerId' => $channel['mch_id'],
            'authKey' => $channel['mch_key'],
        ];


        $response = Http::postJson($channel['gateway'] . '/api/pix', $data, $headers);
        Log::write('ClickPayChannel pay response:' . json_encode($response) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');

        if (isset($response['error'])) {
            return [
                'status' => 0,
                'msg' => $response['error'],
            ];
        }

        // 请求超时
        if (isset($response['msg']) && strpos($response['msg'], 'cURL error 28:') !== false) {
            // 重新请求
            $response = Http::postJson($channel['gateway'] . '/api/pix', $data, $headers);
            Log::write('ClickPayChannel pay response:' . json_encode($response) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');
            if (isset($response['error'])) {
                return [
                    'status' => 0,
                    'msg' => $response['error'],
                ];
            }
        }

        if (isset($response['msg'])) {
            if (strpos($response['msg'], 'response') !== false) {
                $res = json_decode(substr($response['msg'], strpos($response['msg'], '{')), true);
                if (isset($res['error'])) {
                    return [
                        'status' => 0,
                        'msg' => $res['error'],
                    ];
                }
            } else {
                return [
                    'status' => 0,
                    'msg' => 'Excepção de pagamento, por favor tente de novo mais tarde',
                ];
            }
        }

        $pay_url = Config::get('pay_url') . '/index/pay/index?order_id=' . $params['order_no'];

        // 缓存订单信息$response
        Cache::set('order_info_' . $params['order_no'], json_encode($response), 600);

        return [
            'status' => 1, // 状态 1成功 0失败
            'pay_url' => $pay_url, // 支付地址
            'msg' => '', // 消息
            'order_id' => $response['tx_id'], // 订单号
            'e_no' => '',
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];
    }

    /**
     * 获取扩展配置
     */
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

    /**
     * outPay 出款
     */
    public function outPay($channel, $params): array
    {
        $extra = json_decode($params['extra'], true);

        // 如果是电话号码 并且是电话号码没有+55
        if ($extra['pix_type'] == 'PHONE' && strpos($extra['pix_key'], '+55') === false) {
            $extra['pix_key'] = '+55' . $extra['pix_key'];
        }

        // 如果类型是CPF 并且不是数字
        if ($extra['pix_type'] == 'CPF' && !is_numeric($extra['pix_key'])) {
            return [
                'status' => 0,
                'msg' => 'CPF inválido',
            ];
        }


        $data = [
            'userId' => $params['order_no'],
            'amount' => (float)$params['amount'],
            'pixKeyType' => $extra['pix_type'] == "PHONE" ? "TELEFONE" : $extra['pix_type'], // PHONE TELEFONE CPF CNPJ
            'pixKey' => $extra['pix_key']
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'partnerId' => $channel['mch_id'],
            'authKey' => $channel['mch_key'],
        ];

        $url = $channel['gateway'] . '/api/withdraw';
        $res = Http::postJson($url, $data, $headers);
        Log::write('ClickPayChannel outPay response:' . json_encode($res) . ' data:' . json_encode($data) . ' headers:' . json_encode($headers), 'info');


        if (isset($res['error'])) {
            return [
                'status' => 0,
                'msg' => $res['error'],
            ];
        }
        //$res['msg'] = string(207) "Server error: `POST https://paynex.live/api/withdraw` resulted in a `500 Internal Server Error` response: {"error":"Error create withdraw #001930 - The selected pix key type is invalid. (and 1 more error)"}
        //{"code":0,"msg":"cURL error 28: Operation timed out after 10002 milliseconds with 0 bytes received (see https:\/\/curl.haxx.se\/libcurl\/c\/libcurl-errors.html)
        // 获取 response
        if (isset($res['msg'])) {
            if (strpos($res['msg'], 'response') !== false) {
                $response = json_decode(substr($res['msg'], strpos($res['msg'], '{')), true);
                if (isset($response['error'])) {
                    return [
                        'status' => 0,
                        'msg' => $response['error'],
                    ];
                }
            }

            // 不是请求超时
            if (strpos($res['msg'], 'cURL error 28') === false) {
                return [
                    'status' => 0,
                    'msg' => 'Excepção de pagamento, por favor tente de novo mais tarde',
                ];
            }
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'order_id' => $res['tx_id'] ?? '', // 订单号
            'msg' => '', // 消息
            'e_no' => $res['e2e_id'] ?? '', // 业务订单号
            'request_data' => json_encode($params), // 请求数据
            'response_data' => json_encode($res), // 响应数据
        ];
    }

    /**
     * payNotify 支付回调
     */
    public function payNotify($channel, $params): array
    {
//        $secureCode = $this->getExtraConfig($channel, 'secureCode');
//        if ($params['header']['securecode'] != $secureCode) {
//            throw new \Exception('secureCode 验证失败');
//        }

        $status = OrderIn::STATUS_UNPAID;
        if ($params['event'] == 'payment.paid') {
            $status = OrderIn::STATUS_PAID;
        }
        if ($params['event'] == 'payment.canceled') {
            $status = OrderIn::STATUS_FAILED;
        }

        if ($status == OrderIn::STATUS_UNPAID) {
            throw new \Exception('支付状态错误');
        }

        return [
            'order_no' => $params['data']['userId'], // 订单号
            'channel_no' => $params['data']['txid'], // 渠道订单号
            'amount' => $params['data']['amount'], // 金额
            'pay_date' => '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $status == OrderOut::STATUS_PAID ? 'sucesso' : 'canceled', // 消息
        ];
    }

    /**
     * outPayNotify 出款回调
     */
    public function outPayNotify($channel, $params): array
    {
//        $secureCode = $this->getExtraConfig($channel, 'secureCode');
//        if ($params['header']['securecode'] != $secureCode) {
//            throw new \Exception('secureCode 验证失败');
//        }

        $status = OrderOut::STATUS_UNPAID;
        if ($params['status'] == 'withdraw.paid') {
            $status = OrderOut::STATUS_PAID;
        }
        if ($params['status'] == 'withdraw.failed' || $params['status'] == 'withdraw.canceled') {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('支付状态错误');
        }

        return [
            'order_no' => $params['data']['user_id'] ?? '', // 订单号
            'channel_no' => $params['data']['tx_id'], // 渠道订单号
            'pay_date' => '', // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => $params['data']['e2e'] ?? '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $status == OrderOut::STATUS_PAID ? 'sucesso' : 'falham', // 消息
        ];
    }

    /**
     * response 返回
     */
    public function response(): string
    {
        return 'success';
    }

    /**
     * getNotifyType 获取通知类型
     */
    public function getNotifyType($params): string
    {
        // 如果status 包含 payment 是代收， withdraw 是代付 其他是其他
        if (isset($params['event'])) {
            if (strpos($params['event'], 'payment') !== false) {
                return HookService::NOTIFY_TYPE_IN;
            }
        }

        if (isset($params['status'])) {
            if (strpos($params['status'], 'withdraw') !== false) {
                return HookService::NOTIFY_TYPE_OUT_PAY;
            }
        }

        return '';
    }

    public function getPayInfo($order): array
    {
        $response = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_REQUEST)->find();
        if (!$response) {
            throw new \Exception('支付信息获取失败！');
        }
        $response_data = json_decode($response['response_data'], true);

        return [
            'order_no' => $order['order_no'],
            'qrcode' => "data:image/png;base64," . $response_data['qrcode'],
            'pix_code' => $response_data['copia_e_cola'],
        ];
    }

    /**
     * 获取凭证
     */
    public function getVoucher($channel, $order): array
    {
        return [
            'data' => [
                'pay_success_date' => $order['pay_success_date'],
                'bankName' => $this->getExtraConfig($channel, 'bankName'),
                'cnpj' => $this->getExtraConfig($channel, 'cnpj'),
                'e_no' => $order['e_no'] ?? '',
            ],
            'status' => 1,
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
            'pay_date' => date('d/m/Y', strtotime($order['pay_success_date'])), // 支付时间
            'payer_name' => $payer_name, // 付款人姓名B.B INVESTIMENT TRADING SERVICOS LTDA
            'payer_account' => $payer_account, // 付款人CPF 57.709.170/0001-67
            'e_no' => $order['e_no'], // 业务订单号
            'type' => 'cnpj', // 业务订单号
        ];
    }

    public function getVoucherUrl($order): string
    {
        return Config::get('pay_url') . '/index/receipt/index?order_id=' . $order['order_no'];
    }

}