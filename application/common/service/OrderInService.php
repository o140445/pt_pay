<?php

namespace app\common\service;

use app\common\model\merchant\Channel;
use app\common\model\merchant\Member;
use app\common\model\merchant\MemberProjectChannel;
use app\common\model\merchant\MemberWalletModel;
use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderNotifyLog;
use app\common\model\merchant\OrderRequestLog;
use app\common\model\merchant\Profit;
use fast\Http;

class OrderInService
{
    const STATUS_OPEN = 1;
    const STATUS_CLOSE = 0;

    // channelResStatus
    const CHANNEL_RES_STATUS_SUCCESS = 1;
    const CHANNEL_RES_STATUS_FAILED = 0;

    // TYPE_IN
    const TYPE_IN = 'IN';


    /**
     * 创建订单
     * @param $params
     */
    public function createOrder($params)
    {
        // 订单创建检查
        $channel_id = MemberProjectChannel::where('status', OrderInService::STATUS_OPEN)->where('member_id', $params['merchant_id'])->where('project_id', $params['product_id'])->where('type', 1)->value('channel_id');

        if (!$channel_id) {
            throw new \Exception('未开通支付通道');
        }

        $params['channel_id'] = $channel_id;
        $params['type'] = self::TYPE_IN;

        $validate = new OrderValidator();
        if (!$validate->validateOrder($params)) {
            throw new \Exception($validate->getErrors()[0]);
        }

        $member = Member::where('status', OrderInService::STATUS_OPEN)->find($params['merchant_id']);

        // 设置时区
        date_default_timezone_set($member->area->timezone);

        // 创建订单
        $order = new OrderIn();
        $order->order_no = $this->generateOrderNo();
        $order->member_id = $params['merchant_id'];
        $order->project_id = $params['product_id'];
        $order->channel_id = $channel_id;

        $order->member_order_no = $params['merchant_order_no'];
        $order->amount = $params['amount'];
        $order->notify_url = $params['notify_url'];
        $order->order_ip = request()->ip();
        $order->area_id = $member->area_id;
        $order->status = OrderIn::STATUS_UNPAID;
        $order->channel_order_no = '';


        $res = $order->save();

        if (!$res) {
            throw new \Exception('订单创建失败');
        }

        $channel = Channel::where('status', OrderInService::STATUS_OPEN)->find($channel_id);

        // 请求支付通道
        $channelRes = $this->requestChannel($order, $channel);

        // 支付失败
        if ($channelRes['status'] == OrderInService::CHANNEL_RES_STATUS_FAILED) {
            $order->status = OrderIn::STATUS_FAILED;
            $order->error_msg = $channelRes['msg'];
            $order->save();

        }else{
            $order->status = OrderIn::STATUS_UNPAID;
            $order->channel_order_no = $channelRes['order_id'];
            $order->e_no = $channelRes['e_no'];
            $order->pay_url = $channelRes['pay_url'];
            $order->save();
        }

        // 写入请求日志
        $log = new OrderRequestService();
        $log->create($order->order_no, OrderRequestLog::REQUEST_TYPE_REQUEST, OrderRequestLog::ORDER_TYPE_IN, $channelRes['request_data'], $channelRes['response_data']);

        return [
            'order_no' => $order->order_no,
            'pay_url' => $channelRes['pay_url'],
            'status' => $order->status,
            'msg' => $channelRes['msg'],
        ];

    }

    /**
     * 请求支付通道
     * @param $order
     * @param $channel
     *
     * @return array
     */
    public function requestChannel($order, $channel)
    {
        $paymentService = new PaymentService($channel->code);
        $res = $paymentService->pay($channel, $order);
        return $res;
    }


    /**
     * 生成订单号
     * @return string
     */
    public function generateOrderNo()
    {
        $str = get_order_no('DI');
        OrderIn::where('order_no', $str)->find() && $str = $this->generateOrderNo();
        return $str;
    }

    /**
     * 回调处理
     * @param $sign
     * @param $params
     * @return array
     */
    public function notify($sign, $params)
    {
        $channel = Channel::where('status', OrderInService::STATUS_OPEN)->where('sign', $sign)->find();
        if (!$channel) {
            throw new \Exception('通道不存在');
        }

        $paymentService = new PaymentService($channel->code);
        $data = $paymentService->payNotify($channel, $params);

        // 订单不存在
        if ($data['status'] == OrderIn::STATUS_UNPAID) {
            return [
                'order_id' => '',
                'msg' => $paymentService->response()
            ];
        }

        if (!$data['order_no'] && !$data['channel_no']) {
            throw new \Exception('参数错误, 订单号或渠道订单号不存在');
        }

        // 查询订单
        if ($data['order_no']) {
            $order = OrderIn::where('order_no', $data['order_no'])->lock(true)->find();
        } else {
            $order = OrderIn::where('channel_order_no', $data['channel_no'])->lock(true)->find();
        }

        // 设置时区
        date_default_timezone_set($order->area->timezone);

        if (!$order) {
            throw new \Exception( '订单不存在');
        }

        if ($order->status !== OrderIn::STATUS_UNPAID) {
            return [
                'order_id' => $order->id,
                'msg' => $paymentService->response()
            ];
        }

        // 写入请求日志
        $log = new OrderRequestService();
        $log->create(
            $order->order_no,
            OrderRequestLog::REQUEST_TYPE_RESPONSE,
            OrderRequestLog::ORDER_TYPE_IN,
            json_encode($params),
             '');


        // 支付成功
        if ($data['status'] == OrderIn::STATUS_PAID) {
            $this->completeOrder($order, $data);
        } else {
            // 支付失败
            $this->failOrder($order, $data);
        }

        return [
            'order_id' => $order->id,
            'msg' => $paymentService->response()
        ];
    }

    /**
     * 支付失败
     * @param $order
     * @param $data ['error_msg']
     */
    public function failOrder($order, $data)
    {
        $order->status = OrderIn::STATUS_FAILED;
        $order->error_msg = $data['msg'] ?? '';
        $order->e_no = $data['e_no'] ?? $order->e_no;
        $order->channel_order_no = $data['channel_no'] ?? $order->channel_order_no;
        $order->save();
    }

    /**
     * 完成订单
     * @param $order
     * @param $data ['pay_date', 'true_amount', 'e_no']
     */
    public function completeOrder($order, $data)
    {
        $order->status = OrderIn::STATUS_PAID;
        $order->pay_success_date = $data['pay_date'] ?? date('Y-m-d H:i:s');
        $order->true_amount = $data['true_amount'] ?? $order->amount;
        $order->e_no = $data['e_no'] ?? $order->e_no;
        $order->channel_order_no =  $data['channel_no'] ?? $order->channel_order_no;

        // 计算手续费
        $fee = $this->calculateFee($order);
        $order->fee_amount = $fee['fee_amount'];
        $order->channel_fee_amount = $fee['channel_fee_amount'];
        $order->actual_amount = $order->true_amount - $order->fee_amount;
        $order->save();

        // 更新商户余额
        $walletService = new MemberWalletService();
        $walletService->addBalanceByType($order->member_id, $order->actual_amount, MemberWalletModel::CHANGE_TYPE_PAY_ADD, $order->order_no, '代收完成');

        // 计算提成
        $commission = $this->calculateCommission($order, 0);

        // 计算利润
        $this->calculateProfit($order, $commission);
    }

    /**
     * 计算手续费
     * @param $order
     */
    public function calculateFee($order)
    {
        $res=[
            'fee_amount' => 0,
            'channel_fee_amount' => 0,
        ];

        $channel = Channel::where('status', OrderInService::STATUS_OPEN)->find($order->channel_id);
        $memberProjectChannel = MemberProjectChannel::where('status', OrderInService::STATUS_OPEN)
            ->where('member_id', $order->member_id)
            ->where('project_id', $order->project_id)
            ->where('channel_id', $order->channel_id)
            ->where('type', 1)
            ->find();

        if ($channel){
            $res['channel_fee_amount'] = $order->amount * $channel->in_rate / 100 + $channel->in_fixed_rate;
        }

        if ($memberProjectChannel){
            $res['fee_amount'] = $order->amount * $memberProjectChannel->rate / 100 + $memberProjectChannel->fixed_rate;
        }

        return $res;
    }

    /**
     * 计算提成
     * @param $order
     * @return float
     */
    public function calculateCommission($order, $amount){
        $member = Member::where('status', OrderInService::STATUS_OPEN)->find($order->member_id);
        if (!$member || !$member->agency_id){
            return 0;
        }
        $agent = Member::where('status', OrderInService::STATUS_OPEN)->find($member->agency_id);
        if (!$agent){
            return 0;
        }

        $memberProjectChannel = MemberProjectChannel::where('status', OrderInService::STATUS_OPEN)
            ->where('member_id', $agent->id)
            ->where('project_id', $order->project_id)
            ->where('channel_id', $order->channel_id)
            ->where('type', 1)
            ->where('status', OrderInService::STATUS_OPEN)
            ->where('sub_member_id', $member->id)
            ->find();

        if (!$memberProjectChannel){
            return 0;
        }

        $amount += $order->amount * $memberProjectChannel->rate / 100 + $memberProjectChannel->fixed_rate;

        $walletService = new MemberWalletService();
        $walletService->addBalanceByType($agent->id, $amount, MemberWalletModel::CHANGE_TYPE_COMMISSION_ADD, '', '代收提成');

//        if ($agent->agency_id){
//            $this->calculateCommission($order, $amount);
//        }

        return $amount;
    }

    /**
     * 计算利润
     * @param $order
     * @param int $commission
     */
    public function calculateProfit($order, $commission = 0)
    {
        $profit = new Profit();
        $profit->order_no = $order->order_no;
        $profit->member_id = $order->member_id;
        $profit->area_id = $order->area_id;
        $profit->order_type = 1;
        $profit->order_amount = $order->true_amount;
        $profit->fee = $order->fee_amount;
        $profit->channel_fee = $order->channel_fee_amount;
        $profit->commission = $commission;
        $profit->profit = $order->fee_amount - $order->channel_fee_amount - $commission;
        $profit->save();
    }

    /**
     * 通知下游
     * @param $order_id
     * @return bool
     */
    public function notifyDownstream($order_id)
    {
        if (!$order_id){
            throw new \Exception('订单ID不能为空');
        }

        $order = OrderIn::where('id', $order_id)->find();
        if (!$order || $order->status == OrderIn::STATUS_UNPAID){
            throw new \Exception('订单不存在或未支付');
        }

        if ($order->notify_url == ''){
            return true;
        }

        $data = [
            'order_no' => $order->order_no,
            'merchant_order_no' => $order->member_order_no,
            'amount' => $order->true_amount,
            'status' => $order->status,
            'pay_success_date' => $order->pay_success_date ?? '',
            'msg' => $order->error_msg ?? 'OK',
        ];

        $rse = Http::post($order->notify_url, $data);
        $code = $rse == 'success' ? OrderNotifyLog::STATUS_NOTIFY_SUCCESS : OrderNotifyLog::STATUS_NOTIFY_FAIL;

        $log = new OrderNotifyLog();
        $log->order_no = $order->order_no;
        $log->notify_url = $order->notify_url;
        $log->notify_data = json_encode($data);
        $log->notify_status = $code;
        $log->notify_result = $rse;
        $log->notify_type = 1;
        $log->save();

        // 修改通知次数和状态
        $order->notify_count += 1;
        $order->notify_status = $code;

        return $order->save();

    }
}