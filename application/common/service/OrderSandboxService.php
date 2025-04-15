<?php

namespace app\common\service;

use app\common\model\merchant\Member;
use app\common\model\merchant\MemberProjectChannel;
use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderNotifyLog;
use app\common\model\merchant\OrderSandbox;
use fast\Http;
use think\Log;

class OrderSandboxService
{
    /**
     * 创建沙箱订单
     */
    public function createOrderIn($params)
    {
        $params['is_sandbox'] = 1;
        $params['type'] = OrderInService::TYPE_IN;

        $validate = new OrderValidator();
        if (!$validate->validateOrder($params)) {
            throw new \Exception($validate->getErrors()[0]);
        }

        $order = new OrderSandbox();
        $order->order_no = $this->generateOrderNo();
        $order->member_id = $params['merchant_id'];
        $order->member_order_no = $params['merchant_order_no'];
        $order->amount = $params['amount'];
        $order->project_id = $params['product_id'];
        $order->status = OrderIn::STATUS_UNPAID;
        $order->notify_url = $params['notify_url'];
        $order->msg =  'OK';
        $order->save();

        return [
            'order_no' => $order->order_no,
            'pay_url' => "test_url",
            'status' => $order->status,
            'msg' => 'OK',
        ];
    }

    /**
     * 生成订单号
     */
    private function generateOrderNo()
    {
        return date('YmdHis') . rand(100000, 999999);
    }

    /**
     * 创建沙箱订单
     */
    public function createOrderOut($params)
    {
        $params['is_sandbox'] = 1;
        $params['type'] = OrderOutService::TYPE_OUT;

        $validate = new OrderValidator();
        if (!$validate->validateOrder($params)) {
            throw new \Exception($validate->getErrors()[0]);
        }

        $order = new OrderSandbox();
        $order->order_no = $this->generateOrderNo();
        $order->member_id = $params['merchant_id'];
        $order->member_order_no = $params['merchant_order_no'];
        $order->amount = $params['amount'];
        $order->project_id = $params['product_id'];
        $order->status = OrderIn::STATUS_UNPAID;
        $order->notify_url = $params['notify_url'];
        $order->msg =  'OK';
        $order->save();

        return [
            'order_no' => $order->order_no,
            'status' => $order->status,
            'msg' => $order['msg'],
        ];
    }

    /**
     * 通知下游
     */
    public function notify($order_no)
    {
        $order = OrderSandbox::where('order_no', $order_no)->find();
        if (!$order) {
            throw new \Exception('订单不存在');
        }

        if ($order->status == OrderIn::STATUS_UNPAID) {
            throw new \Exception('订单未支付');
        }

        $data = [
            'order_no' => $order->order_no,
            'merchant_order_no' => $order->member_order_no,
            'merchant_id' => $order->member_id,
            'amount' => $order->amount,
            'status' => $order->status,
            'pay_success_date' => $order->create_time ?? '',
            'msg' => $order->error_msg ?? 'OK',
        ];

        $member = Member::where('id', $order->member_id)->find();
        $signService = new SignService();
        $data['sign'] = $signService->makeSign($data, $member->api_key);

        $rse = Http::post_json($order->notify_url, $data);
        Log::write('sandbox notify response:' . json_encode($rse) . ' data:' . json_encode($data), 'info');
        $code = $rse == 'success' ? OrderNotifyLog::STATUS_NOTIFY_SUCCESS : OrderNotifyLog::STATUS_NOTIFY_FAIL;

        // 修改通知次数和状态
        $order->notify_count += 1;
        $order->notify_status = $code;

        return $order->save();
    }
}