<?php

namespace app\common\service\channels;

use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use fast\Http;
use http\Exception;
use think\Config;
use think\Log;

/**
 * Fix 支付渠道
 */
class FixPayChannel implements ChannelInterface
{


    public function config()
    {
        return [
            [
                'name'=>'代付类型',
                'key'=>'method',
                'value'=>'BAR01',
            ],

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

    public function pay($channel, $params): array
    {

        $data = [
            'merchantNo' => $channel['mch_id'],
            'method' => $this->getExtraConfig($channel, 'method'),
            'merchantOrderNo' => $params['order_no'],
            'description' => 'int',
            'payAmount' => $params['amount'],
            'mobile' => '12345678901',
            'name' => 'tikpay',
            'email' => 'tikpay@gmail.com',
            'notifyUrl' => $this->getNotifyUrl($channel, "innotify"),
            'returnUrl' => $this->getNotifyUrl($channel, "inreturn"),
        ];

        $data['sign'] = $this->sign($data, $channel['mch_key']);
        $url = $channel['gateway'] . '/api/payin/order';

        $response = Http::postJson($url, $data);
        Log::write('FixPayChannel pay response:' . json_encode($response) . ' data:' . json_encode($data), 'info');

        if (isset($response['msg']) && strpos($response['msg'], 'cURL error 7:') !== false) {
            // 重新请求
            $response = Http::postJson($url, $data);
            Log::write('FixPayChannel pay response:' . json_encode($response) . ' data:' . json_encode($data), 'info');
        }

        if (!$response || isset($response['msg']) || $response['status'] != 200) {

            return [
                'status' => 0,
                'msg' =>  $response['msg'] ?? $response['message'] ?? '请求失败',
            ];
        }

        //{"status":"200","message":"success","data":{"orderStatus":"CREATED","orderMessage":"SUCCESS","merchantOrderNo":"DI20250425044802nuJKdn","platOrderNo":"Hwpay17455672854600709491484","paymentInfo":"https:\/\/pay.cxddc.top\/barzh\/Hwpay17455672854600709491484","payAmount":"5","qrcode":"00020101021226900014br.gov.bcb.pix2568qrcode.siliumpay.com.br\/dynamic\/aaf497c7-4462-474e-9b31-ecd264a5df055204000053039865802BR5904GD 36009Sao Paulo62070503***6304DC7D","description":"int","sign":"8ed7da17f604b2210a8437a1253d08c8"}}

        if (!isset($response['data']['qrcode']) || empty($response['data']['qrcode'])) {
            $pay_url = Config::get('pay_url') . '/index/pay/pay?order_id=' . $params['order_no'];
        }else{
            $pay_url = Config::get('pay_url') . '/index/pay/index?order_id=' . $params['order_no'];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'pay_url' => $pay_url, // 支付地址
            'msg' => '', // 消息
            'order_id' => $response['data']['platOrderNo'], // 订单号
            'e_no' => '',
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];
    }

    public function outPay($channel, $params): array
    {
        $mobile = '12345678901';
        $email = 'tikpay@gmail.com';
        $extra = json_decode($params['extra'], true);

        // 如果是电话号码 并且是电话号码没有+55
        if ($extra['pix_type'] == 'PHONE' && strpos($extra['pix_key'], '+55') === false) {
            $extra['pix_key'] = '+55'.$extra['pix_key'];
        }

        $data = [
            'merchantNo' => $channel['mch_id'],
            'merchantOrderNo' => $params['order_no'],
            'description' => 'out',
            'payAmount' => $params['amount'],
            'mobile' => $mobile,
            'email' => $email,
            'bankNumber' =>  $extra['pix_key'],
            'bankCode' => $extra['pix_type'],
            'accountHoldName' => $extra['pix_name'] ?? 'tikpay',
            'notifyUrl' => $this->getNotifyUrl($channel, "outnotify"),
       ];
        $data['sign'] = $this->sign($data, $channel['mch_key']);

        $url = $channel['gateway'] . '/api/payout/order';

        $response = Http::postJson($url, $data);

        Log::write('FixPayChannel outPay response:' . json_encode($response) . ' data:' . json_encode($data), 'info');

        if (!$response || isset($response['msg']) || $response['status'] != 200) {
            if (isset($response['msg']) && strpos($response['msg'], 'cURL error 7:') !== false) {
                return [
                    'status' => 0,
                    'msg' => 'Excepção de pagamento, por favor tente de novo mais tarde',
                ];
            }
            return [
                'status' => 0,
                'msg' =>  $response['msg'] ?? $response['message'] ?? '请求失败',
            ];
        }

        return [
            'status' => 1, // 状态 1成功 0失败
            'msg' => '', // 消息
            'order_id' => $response['data']['platOrderNo'], // 订单号
            'e_no' => '',
            'request_data' => json_encode($data), // 请求数据
            'response_data' => json_encode($response), // 响应数据
        ];
    }

    public function getNotifyUrl($channel, $type)
    {
        return Config::get('pay_url') . '/api/v1/pay/' . $type . '/code/' . $channel['sign'];
    }

    public function sign($data, $key)
    {
        unset($data['sign']);
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            // 为空不参与签名
            if (is_null($v) || $v == '') {
                continue;
            }

            $str .= $k . '=' . $v . '&';
        }

        $sign = md5(md5($str) . $key);
        return $sign;
    }

    public function payNotify($channel, $params): array
    {
        //{
        //    "merchantNo": "Hwpay",
        //    "merchantOrderNo": "DI20250425044802nuJKdn",
        //    "amount": "5.00",
        //    "factAmount": "5.00",
        //    "platOrderNo": "Hwpay17455672854600709491484",
        //    "orderStatus": "SUCCESS",
        //    "orderMessage": "SUCCESS",
        //    "sign": "f7e386dc297de580a451ce78df3d81c5"
        //}
        $sign = $params['sign'];
        unset($params['sign']);
        $newSign = $this->sign($params, $channel['mch_key']);
        if ($sign != $newSign) {
            throw new \Exception('签名错误');
        }

        $status = OrderOut::STATUS_UNPAID;
        //ARRIVED/SUCCESS/CLEARED中
        if ($params['orderStatus'] == 'SUCCESS' || $params['orderStatus'] == 'ARRIVED' || $params['orderStatus'] == 'CLEARED') {
            $status = OrderOut::STATUS_PAID;
        }
        if ($params['orderStatus'] == 'FAILED') {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('未支付');
        }

        return  [
            'order_no' => $params['merchantOrderNo'], // 订单号
            'channel_no' => $params['platOrderNo'], // 渠道订单号
            'pay_date' => date('Y-m-d H:i:s'), // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $params['orderMessage'] ?? '', // 消息
        ];


    }

    public function outPayNotify($channel, $params): array
    {

        $sign = $params['sign'];
        unset($params['sign']);
        $newSign = $this->sign($params, $channel['mch_key']);
        if ($sign != $newSign) {
            throw new \Exception('签名错误');
        }

        $status = OrderOut::STATUS_UNPAID;
        if ($params['orderStatus'] == 'SUCCESS') {
            $status = OrderOut::STATUS_PAID;
        }

        if ($params['orderStatus'] == 'FAILED') {
            $status = OrderOut::STATUS_FAILED;
        }

        if ($status == OrderOut::STATUS_UNPAID) {
            throw new \Exception('未支付');
        }

        return  [
            'order_no' => $params['merchantOrderNo'], // 订单号
            'channel_no' => $params['platOrderNo'], // 渠道订单号
            'pay_date' => date('Y-m-d H:i:s'), // 支付时间
            'status' => $status, // 状态 2成功 3失败 4退款
            'e_no' => '', // 业务订单号
            'data' => json_encode($params), // 数据
            'msg' => $params['orderMessage'] ?? '', // 消息
        ];
    }

    public function response(): string
    {
        return "SUCCESS";
    }


    public function getPayInfo($orderIn): array
    {
        $response = OrderRequestLog::where('order_no', $orderIn['order_no'])
            ->where('request_type', OrderRequestLog::REQUEST_TYPE_REQUEST)
            ->find();

        if (!$response) {
            throw new \Exception('支付信息获取失败！');
        }

        $responseData = json_decode($response['response_data'], true);

        if (!$responseData || empty($responseData['data']['qrcode'])) {
            return [
                'url' => $response_data['data']['orderurl'] ?? '',
            ];
        }

        // 使用 Endroid 6.x 生成二维码
        $builder = new Builder(
            writer: new PngWriter(),
            data: $responseData['data']['qrcode'],
            size: 200,
            margin: 10
        );

        $result = $builder->build();

        // 获取 base64 图片数据
        $qrCodeBase64 = $result->getDataUri();

        return [
            'order_no' => $orderIn['order_no'],
            'qrcode' => $qrCodeBase64,
            'pix_code' => $responseData['data']['qrcode'],
        ];
    }


    public function getNotifyType($params): string
    {
       return "";
    }

    public function parseVoucher($channel, $params): array
    {

//        $html = file_get_contents("https://pay.paythere.top/getfeedback/".$params['order_no']);
        // 解析数据 html
        $data = Http::postJson("{$channel['gateway']}/getfeedback/{$params['order_no']}", []);
        if (empty($data)) {
            throw new \Exception('Voucher parsing failed.');
        }

        $payer_name = $this->getExtraConfig($channel, 'bankName');
        $payer_account = $this->getExtraConfig($channel, 'cnpj');
        return [
            'pay_date' => $params['pay_success_date'], // 支付时间
            'payer_name' => $payer_name, // 付款人姓名B.B INVESTIMENT TRADING SERVICOS LTDA
            'payer_account' => $payer_account, // 付款人CPF 57.709.170/0001-67
            'e_no' => $data['data']['a11'],
            'type' => 'cnpj', // 业务订单号
        ];
    }

    public function getVoucher($channel, $order): array
    {
        //https://pay.paythere.top/getfeedback/DO20250508073425E9EXvm

//        $html = file_get_contents("https://pay.paythere.top/getfeedback/".$order['order_no']);
        $html = file_get_contents("https://pay.paythere.top/getfeedback/DO20250508073425E9EXvm");
        // 解析数据 html
        $pattern = '/<th[^>]*?>\s*EndToEndId\s*<\/th>.*?<td[^>]*?>(.*?)<\/td>/is';
        $e_no= '';
        if (preg_match($pattern, $html, $matches)) {
            $e_no = $matches[1];
        }

        if (empty($e_no)) {
            throw new \Exception('Voucher parsing failed.');
        }


        $payer_name = $this->getExtraConfig($channel, 'bankName');
        $payer_account = $this->getExtraConfig($channel, 'cnpj');
        return [
            'pay_date' => $order['pay_success_date'], // 支付时间
            'payer_name' => $payer_name, // 付款人姓名B.B INVESTIMENT TRADING SERVICOS LTDA
            'payer_account' => $payer_account, // 付款人CPF 57.709.170/0001-67
            'e_no' => $e_no, // 业务订单号
            'type' => 'cnpj', // 业务订单号
        ];
    }

    public function getVoucherUrl($order): string
    {
        // https://pay.paythere.top/getfeedback/
        return   Config::get('pay_url').'/index/receipt/index?order_id='.$order['order_no'];
    }

}