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
use think\Log;

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
            throw new \Exception('Merchant channel not found');
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
            throw new \Exception('Order creation failed');
        }

        return $order;
    }

    /**
     * 请求支付通道
     * @param $order
     * @param $channel
     *
     * @return array
     */
    public function requestChannel($order)
    {
        $channel = Channel::where('status', OrderInService::STATUS_OPEN)->find($order->channel_id);
        $paymentService = new PaymentService($channel->code);
        $res = $paymentService->pay($channel, $order);

        // 支付失败
        if ($res['status'] == OrderInService::CHANNEL_RES_STATUS_FAILED) {
            $order->status = OrderIn::STATUS_FAILED;
            $order->error_msg = $res['msg'];
            $order->save();

            throw new \Exception($res['msg']);
        }else{
            $order->status = OrderIn::STATUS_UNPAID;
            $order->channel_order_no = $res['order_id'];
            $order->e_no = $res['e_no'];
            $order->pay_url = $res['pay_url'];
            $order->save();
        }

        // 写入请求日志
        $log = new OrderRequestService();
        $log->create($order->order_no, OrderRequestLog::REQUEST_TYPE_REQUEST, OrderRequestLog::ORDER_TYPE_IN, $res['request_data'], $res['response_data']);

        return [
            'order_no' => $order->order_no,
            'pay_url' => $res['pay_url'],
            'status' => $order->status,
            'msg' => $res['msg'],
        ];
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
            OrderRequestLog::REQUEST_TYPE_CALLBACK,
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
        $order->error_msg = isset($data['msg']) &&  !empty($data['msg']) ? $data['msg'] : 'O pagamento falhou';

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
        $order->pay_success_date = isset($data['pay_date']) &&  !empty($data['pay_date']) ? $data['pay_date'] : date('Y-m-d H:i:s');
        $order->true_amount = isset($data['amount']) &&  !empty($data['amount']) ? $data['amount'] : $order->amount;
        $order->e_no = isset($data['e_no']) &&  !empty($data['e_no']) ? $data['e_no'] : $order->e_no;
        $order->channel_order_no =  isset($data['channel_no']) &&  !empty($data['channel_no']) ? $data['channel_no'] : $order->channel_order_no;

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
        $commission = $this->calculateCommission($order);

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
    public function calculateCommission($order){
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

        $amount = $order->true_amount * $memberProjectChannel->rate / 100 + $memberProjectChannel->fixed_rate;

        $walletService = new MemberWalletService();
        $walletService->addBalanceByType($agent->id, $amount, MemberWalletModel::CHANGE_TYPE_COMMISSION_ADD, $order->order_no, '代收提成');

        if ($agent->agency_id){
            $amount += $this->getSecondCommission($order, $agent->id);
        }

        return $amount;
    }

    /**
     * 获取二级代理提成
     */
    public function getSecondCommission($order, $agent_id)
    {
        $agent = Member::where('status', OrderInService::STATUS_OPEN)->find($agent_id);
        if (!$agent || !$agent->agency_id){
            return 0;
        }
        $agent = Member::where('status', OrderInService::STATUS_OPEN)->find($agent->agency_id);
        if (!$agent){
            return 0;
        }

        $memberProjectChannel = MemberProjectChannel::where('status', OrderInService::STATUS_OPEN)
            ->where('member_id', $agent->id)
            ->where('project_id', $order->project_id)
            ->where('channel_id', $order->channel_id)
            ->where('type', 1)
            ->where('status', OrderInService::STATUS_OPEN)
            ->where('sub_member_id', $agent_id)
            ->find();

        if (!$memberProjectChannel){
            return 0;
        }

        $amount = $order->true_amount * $memberProjectChannel->rate / 100 + $memberProjectChannel->fixed_rate;

        $walletService = new MemberWalletService();
        $walletService->addBalanceByType($agent->id, $amount, MemberWalletModel::CHANGE_TYPE_COMMISSION_ADD, $order->order_no, '代收提成');

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
            // 修改通知次数和状态
            $order->notify_count += 1;
            $order->notify_status = OrderNotifyLog::STATUS_NOTIFY_SUCCESS;

            return $order->save();
        }

        $data = [
            'order_no' => $order->order_no,
            'merchant_order_no' => $order->member_order_no,
            'merchant_id' => $order->member_id,
            'amount' => $order->true_amount,
            'status' => $order->status,
            'pay_success_date' => $order->pay_success_date ?? '',
            'msg' => $order->error_msg ?? 'OK',
        ];

        $member = Member::where('id', $order->member_id)->find();
        $signService = new SignService();
        $data['sign'] = $signService->makeSign($data, $member->api_key);

        $rse = Http::post_json($order->notify_url, $data);
        if (!$rse){
            $rse = Http::postJson($order->notify_url, $data);
            if (isset($rse['error'])){
                $rse = $rse['error'];
            }
        }

        if (is_array($rse)){
            $rse = json_encode($rse);
        }

        Log::write('代收通知下游：data ' . json_encode($data) . ', result: ' . $rse, 'info');
        $code = $rse == 'success' ? OrderNotifyLog::STATUS_NOTIFY_SUCCESS : OrderNotifyLog::STATUS_NOTIFY_FAIL;

        $log = new OrderNotifyLog();
        $log->order_no = $order->order_no;
        $log->notify_url = $order->notify_url;
        $log->notify_data = json_encode($data);
        $log->notify_status = $code;
        $log->notify_result = $rse ?? '';
        $log->notify_type = 1;
        $log->save();

        // 修改通知次数和状态
        $order->notify_count += 1;
        $order->notify_status = $code;

        return $order->save();

    }

    /**
     * 查询订单
     */
    public function queryOrder($params, $is_sign = true, $is_or_eno = false)
    {

        $merchant = Member::find((int) $params['merchant_id']);
        if (!$merchant){
            throw new \Exception('商户不存在');
        }

        // 签名验证
        if ($is_sign){
            $signService = new SignService();
            if (!$signService->checkSign($params, $merchant->api_key)){
                throw new \Exception('签名错误');
            }
        }
        if ($is_or_eno){
            $order = OrderIn::where('member_id', $params['merchant_id'])
                ->whereRaw('(e_no = :e_no or member_order_no = :member_order_no)',
                    ['e_no' => $params['merchant_order_no'], 'member_order_no' => $params['merchant_order_no']])
                ->find();
        }else{
            $order = OrderIn::where('member_id', $params['merchant_id'])->where('member_order_no', $params['merchant_order_no'])->find();
        }
        if (!$order){
            throw new \Exception('订单不存在');
        }

        return [
            'order_no' => $order->order_no,
            'merchant_order_no' => $order->member_order_no,
            'merchant_id' => $order->member_id,
            'amount' => $order->status == OrderIn::STATUS_PAID ? $order->true_amount : $order->amount,
            'status' => $order->status,
            'pay_success_date' => $order->pay_success_date,
            'msg' => $order->error_msg,
        ];
    }

    /**
     * 查询订单
     * getOrderInfo
     */
    public function getOrderInfo($params, $is_sign = true, $is_or_eno = false)
    {

        $merchant = Member::find((int) $params['merchant_id']);
        if (!$merchant){
            throw new \Exception('商户不存在');
        }

        // 签名验证
        if ($is_sign){
            $signService = new SignService();
            if (!$signService->checkSign($params, $merchant->api_key)){
                throw new \Exception('签名错误');
            }
        }
        if ($is_or_eno){
            $order = OrderIn::where('member_id', $params['merchant_id'])
                ->with('channel')
                ->whereRaw('(e_no = :e_no or member_order_no = :member_order_no)',
                    ['e_no' => $params['merchant_order_no'], 'member_order_no' => $params['merchant_order_no']])
                ->where('status', OrderIn::STATUS_UNPAID)
                ->find();
        }else{
            $order = OrderIn::with('channel')
                ->where('member_id', $params['merchant_id'])
                ->where('member_order_no', $params['merchant_order_no'])
                ->where('status', OrderIn::STATUS_UNPAID)
                ->find();
        }
        if (!$order){
            throw new \Exception('订单不存在,或已支付');
        }
        $paymentService = new PaymentService($order->channel->code);
        $res = $paymentService->getPayInfo($order);

        return $res;
    }

    public function memberCreateOrder($member_id, $params)
    {
        $params['merchant_id'] = $member_id;
        $params['merchant_order_no'] = get_order_no('SDO'.$member_id);
        $params['notify_url'] = '';
        $params['is_member'] = 1;
        $order =  $this->createOrder($params);
        return  $this->requestChannel($order);
    }

    /**
     * 获取支付成功，但未通知的订单
     */
    public function getUnNotifyOrder($time)
    {
        $orders = OrderIn::where('status', OrderIn::STATUS_PAID)
            ->where('pay_success_date', '<=', $time)
            ->where('notify_status', OrderNotifyLog::STATUS_NOTIFY_WAIT)
            ->limit(10)
            ->select();
        return $orders;
    }

    /**
     * 通知失败，小于3次的订单
     */
    public function getNotifyFailOrder($time)
    {
        $orders = OrderIn::where('notify_status', OrderNotifyLog::STATUS_NOTIFY_FAIL)
            ->where('notify_count', '<', 3)
            ->where('create_time', '>=', $time)
            ->limit(50)
            ->select();
        return $orders;
    }

}