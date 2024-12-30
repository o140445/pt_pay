<?php

namespace app\common\service;

use app\common\model\merchant\OrderOutDelay;
use think\Cache;
use think\Config;
use think\Log;
use app\common\model\merchant\Channel;
use app\common\model\merchant\Member;
use app\common\model\merchant\MemberProjectChannel;
use app\common\model\merchant\MemberWallerLog;
use app\common\model\merchant\MemberWalletModel;
use app\common\model\merchant\OrderNotifyLog;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use app\common\model\merchant\Profit;
use fast\Http;

class OrderOutService
{

    // TYPE_OUT 代付
    const TYPE_OUT = "OUT";

    /**
     * 创建代付订单
     * @param $data
     *
     */
    public function createOutOrder($params)
    {
        // 订单创建检查
        $channel_id = MemberProjectChannel::where('status', OrderInService::STATUS_OPEN)->where('member_id', $params['merchant_id'])->where('project_id', $params['product_id'])->where('type', 2)->value('channel_id');
        if (!$channel_id) {
            throw new \Exception('Project Channel Not Found');
        }

        $params['channel_id'] = $channel_id;
        $params['type'] = self::TYPE_OUT;

        $validate = new OrderValidator();
        if (!$validate->validateOrder($params)) {
            throw new \Exception($validate->getErrors()[0]);
        }

        $member = Member::where('status', OrderInService::STATUS_OPEN)->find($params['merchant_id']);

        // 设置时区
        date_default_timezone_set($member->area->timezone);
        // 创建订单
        $order = new OrderOut();
        $order->order_no = $this->generateOrderNo();
        $order->member_id = $params['merchant_id'];
        $order->channel_id = $channel_id;
        $order->member_order_no = $params['merchant_order_no'];
        $order->amount = $params['amount'];
        $order->project_id = $params['product_id'];
        $order->notify_url = $params['notify_url'];
        $order->extra = json_encode($params['extra']);

        $order->order_ip = request()->ip();
        $order->area_id = $member->area_id;
        $order->status = OrderOut::STATUS_UNPAID;
        $order->channel_order_no = '';

        // 计算手续费
        $fee = $this->calculateFee($order);
        $order->fee_amount = $fee['fee_amount'];
        $order->channel_fee_amount = $fee['channel_fee_amount'];
        $order->actual_amount = $order->amount + $order->fee_amount;
        $order->save();

        // 冻结
        $feeService = new FreezeService();
        $feeService->freeze($order->member_id, $order->actual_amount, MemberWalletModel::CHANGE_TYPE_PAY_FREEZE, $order->order_no, '代付冻结');

        return $order;
    }

    /**
     * 会员创建订单
     * @param $member_id
     * @param $params
     * @return OrderOut
     * @throws \Exception
     */
    public function memberCreateOrder($member_id, $params)
    {
        $params['merchant_id'] = $member_id;
        $params['merchant_order_no'] = get_order_no('SDO'.$member_id);
        $params['notify_url'] = '';
        $params['is_member'] = 1;
        $order =  $this->createOutOrder($params);
        return  $order;
    }

    /**
     * 请求通道
     * @param $order
     * @return array
     */
    public function requestChannel($order)
    {
        // 检查是否已经提交过
        $key = 'order_out_request_channel_lock_'.$order->order_no;
        $lock = Cache::get($key);
        if ($lock){
            throw new \Exception('订单已经提交');
        }

        Cache::set($key, 1, 600);
        $order = OrderOut::where('id', $order->id)->lock(true)->find();
        if (!$order || $order->status != OrderOut::STATUS_UNPAID){
            throw new \Exception('订单不存在或状态不正确');
        }
        $channel = Channel::where('status', OrderInService::STATUS_OPEN)->find($order->channel_id);
        $channelService = new PaymentService($channel->code);
        $res = $channelService->outPay($channel, $order);

        if ($res['status'] == OrderInService::CHANNEL_RES_STATUS_SUCCESS) {
            $order->status = OrderOut::STATUS_UNPAID;
            $order->channel_order_no = $res['order_id'] ?? '';
            $order->e_no = $res['e_no'] ?? '';
            $order->save();

        } else {
            $order->status = OrderOut::STATUS_FAILED;
            $order->error_msg = $res['msg'];
            $order->save();

            // 解冻
            $feeService = new FreezeService();
            $feeService->unfreeze(MemberWalletModel::CHANGE_TYPE_PAY_UNFREEZE, '', $order->order_no, '代付解冻');
            return [
                'order_no' => $order->order_no,
                'status' => $order->status,
                'msg' => $res['msg'],
            ];
        }


        // 写入请求日志
        $log = new OrderRequestService();
        $log->create(
            $order->order_no,
            OrderRequestLog::REQUEST_TYPE_REQUEST,
            OrderRequestLog::ORDER_TYPE_OUT,
            $res['request_data'],
            $res['response_data']);

        return [
            'order_no' => $order->order_no,
            'status' => $order->status,
            'msg' => $res['msg'],
        ];
    }

    /**
     * 回调处理
     */
    public function notify($sign, $data)
    {
        $channel = Channel::where('status', OrderInService::STATUS_OPEN)->where('sign', $sign)->find();
        if (!$channel) {
            throw new \Exception('通道不存在');
        }

        $paymentService = new PaymentService($channel->code);
        $res = $paymentService->outPayNotify($channel, $data);

        if ($res['status'] == OrderOut::STATUS_UNPAID) {
            return [
                'order_id' => '',
                'msg' => $paymentService->response()
            ];
        }

        if (!$res['order_no'] && !$res['channel_no']) {
            throw new \Exception('参数错误, 订单号或渠道订单号不存在');
        }

        if ($res['order_no']) {
            $order = OrderOut::where('order_no', $res['order_no'])->find();
        } else {
            $order = OrderOut::where('channel_order_no', $res['channel_no'])->find();
        }

        // 订单不存在
        if (!$order) {

            //先查询延迟回调表
            $source = !$res['order_no'] ? $res['channel_no'] : $res['order_no'];
            $log = OrderOutDelay::where('source', $source)->find();
            if ($log){
                return [
                    'order_id' => '',
                    'msg' => $paymentService->response()
                ];
            }

            // 写入延迟回调表
            $log = new OrderOutDelay();
            $log->data = json_encode($data);
            $log->source = $source;
            $log->save();

//            throw new \Exception('订单不存在'.' order_no:'.$res['order_no'].' channel_no:'.$res['channel_no']);

            return [
                'order_id' => '',
                'msg' => '订单不存在'. ' order_no:'.$res['order_no'].' channel_no:'.$res['channel_no']
            ];
        }

        // 状态判断
        if (in_array($order->status, [OrderOut::STATUS_FAILED, OrderOut::STATUS_REFUND])) {
            return [
                'order_id' => '', // 不需要发送通知
                'msg' => $paymentService->response()
            ];
        }

        // 设置时区
        date_default_timezone_set($order->area->timezone);

        // 写入请求日志
        $log = new OrderRequestService();
        $log->create(
            $order->order_no,
            OrderRequestLog::REQUEST_TYPE_CALLBACK,
            OrderRequestLog::ORDER_TYPE_OUT,
            json_encode($res),
            '');

        // 完成订单
        if ($res['status'] == OrderOut::STATUS_PAID && $order->status == OrderOut::STATUS_UNPAID) {
            $this->completeOrder($order, $res);
        }

        // 失败订单
        if ($res['status'] == OrderOut::STATUS_FAILED && $order->status == OrderOut::STATUS_UNPAID) {
            $this->failOrder($order, $res);
        }

        // 退款订单
        if ($res['status'] == OrderOut::STATUS_REFUND && $order->status == OrderOut::STATUS_PAID) {
            $this->refundOrder($order, $res);
        }

        // 直接退款
        if ($res['status'] == OrderOut::STATUS_REFUND && $order->status == OrderOut::STATUS_UNPAID) {
            $this->failOrder($order, $res);
        }

        return [
            'order_id' => $order->id,
            'msg' => $paymentService->response()
        ];
    }

    /**
     * 完成订单
     * @param $order
     * @param $data
     */
    public function completeOrder($order, $data)
    {
        if ($order->status != OrderOut::STATUS_UNPAID){
            throw new \Exception('订单状态不正确');
        }

        $order->status = OrderOut::STATUS_PAID;
        $order->pay_success_date = isset($data['pay_date']) && $data['pay_date'] ? $data['pay_date'] : date('Y-m-d H:i:s');
        $order->channel_order_no =  isset($data['channel_no']) && $data['channel_no'] ? $data['channel_no'] : $order->channel_order_no;
        $order->e_no = $order->e_no ? $order->e_no : (isset($data['e_no']) && $data['e_no'] ? $data['e_no'] : '');

        $order->save();
        // 解冻
        $feeService = new FreezeService();
        $feeService->unfreeze(MemberWalletModel::CHANGE_TYPE_PAY_UNFREEZE, '', $order->order_no, '代付解冻');

         // 减少余额
        $memberWalletService = new MemberWalletService();
        $memberWalletService->subBalanceByType($order->member_id, $order->actual_amount , MemberWalletModel::CHANGE_TYPE_PAY_SUB, $order->order_no, '代付扣款');

        // 计算提成
        $commission = $this->calculateCommission($order);

        // 计算利润
        $this->calculateProfit($order, $commission);

    }

    /**
     * 订单失败
     * @param $order
     * @param $data
     */
    public function failOrder($order, $data)
    {
        $order->status = OrderOut::STATUS_FAILED;
        $order->error_msg = isset($data['msg']) && $data['msg'] ? $data['msg'] : 'O pagamento falhou';
        $order->save();

        // 解冻
        // 解冻
        $feeService = new FreezeService();
        $feeService->unfreeze(MemberWalletModel::CHANGE_TYPE_PAY_UNFREEZE, '', $order->order_no, '代付解冻');
    }

    /**
     * 订单退款
     * @param $order
     * @param $data
     */
    public function refundOrder($order, $data)
    {
        $order->status = OrderOut::STATUS_REFUND;
        $order->error_msg = $data['error_msg'] ?? '';
        $order->channel_order_no =  $data['channel_no'] ?? $order->channel_order_no;
        $order->save();

        // 退款
        $memberWalletService = new MemberWalletService();
        $memberWalletService->subBalanceByType($order->member_id, $order->amount, MemberWalletModel::CHANGE_TYPE_PAY_REFUND, $order->order_no, '代付退款');

        // 退款提成
        $commission = $this->getRefundCommission($order);
        return $commission;
    }

    /**
     * 计算手续费
     * @param $order
     * @return array
     */

    private function calculateFee($order)
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
            ->where('type', 2)
            ->find();

        if ($channel){
            $res['channel_fee_amount'] = $order->amount * $channel->out_rate / 100 + $channel->out_fixed_rate;
        }

        if ($memberProjectChannel){
            $res['fee_amount'] = $order->amount * $memberProjectChannel->rate / 100 + $memberProjectChannel->fixed_rate;
        }

        return $res;
    }

    /**
     * 计算提成
     * @param $order
     */
    private function calculateCommission($order)
    {
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
            ->where('type', 2)
            ->where('status', OrderInService::STATUS_OPEN)
            ->where('sub_member_id', $member->id)
            ->find();

        if (!$memberProjectChannel){
            return 0;
        }

        $amount = $order->amount * $memberProjectChannel->rate / 100 + $memberProjectChannel->fixed_rate;

        $walletService = new MemberWalletService();
        $walletService->addBalanceByType($agent->id, $amount, MemberWalletModel::CHANGE_TYPE_COMMISSION_ADD, $order->order_no, '代付提成');

        if ($agent->agency_id){
            $amount += $this->calculateSecondCommission($order, $agent->id);
        }

        return $amount;
    }

    /**
     * 二级提成
     */
    private function calculateSecondCommission($order, $agent_id)
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
            ->where('type', 2)
            ->where('status', OrderInService::STATUS_OPEN)
            ->where('sub_member_id', $agent_id)
            ->find();

        if (!$memberProjectChannel){
            return 0;
        }

        $amount = $order->amount * $memberProjectChannel->rate / 100 + $memberProjectChannel->fixed_rate;

        $walletService = new MemberWalletService();
        $walletService->addBalanceByType($agent->id, $amount, MemberWalletModel::CHANGE_TYPE_COMMISSION_ADD, $order->order_no, '代付提成');


        return $amount;
    }

    /**
     * 退款提成
     */
    private function getRefundCommission($order)
    {
        $member = Member::where('status', OrderInService::STATUS_OPEN)->find($order->member_id);
        if (!$member || !$member->agency_id){
            return 0;
        }
        $agent = Member::where('status', OrderInService::STATUS_OPEN)->find($member->agency_id);
        if (!$agent){
            return 0;
        }
        // 获取这笔订单的提成
        $data =  MemberWallerLog::where('order_no', $order->order_no)
            ->where('type', MemberWalletModel::CHANGE_TYPE_COMMISSION_ADD)
            ->select();

        if (!$data) {
            return 0;
        }

        $amount = 0;
        // 退款提成
        foreach ($data as $v) {
            $walletService = new MemberWalletService();
            $walletService->subBalanceByType($v->member_id, $v->amount, MemberWalletModel::CHANGE_TYPE_COMMISSION_REFUND, $order->order_no, '代付退款提成');
            $amount += $v->amount;
        }

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
        $profit->order_type = 2;
        $profit->order_amount = $order->actual_amount;
        $profit->fee = $order->fee_amount;
        $profit->channel_fee = $order->channel_fee_amount;
        $profit->commission = $commission;
        $profit->profit = $order->fee_amount - $order->channel_fee_amount - $commission;
        $profit->save();
    }


    /**
     * 生成订单号
     * @return string
     */
    private function generateOrderNo()
    {
        $str = get_order_no('DO');
        OrderOut::where('order_no', $str)->find() && $str = $this->generateOrderNo();
        return $str;
    }

    /**
     * 通知下游
     * @param $ids
     * @throws \Exception
     */
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

        $order = OrderOut::where('id', $order_id)->find();
        if (!$order || $order->status == OrderOut::STATUS_UNPAID){
            throw new \Exception('订单不存在或未支付');
        }

        if ($order->notify_url == ''){
            // 修改通知次数和状态
            $order->notify_count += 1;
            $order->notify_status = OrderNotifyLog::STATUS_NOTIFY_SUCCESS;

            return $order->save();
        }

        // 设置时区
        date_default_timezone_set($order->area->timezone);

        $data = [
            'order_no' => $order->order_no,
            'merchant_order_no' => $order->member_order_no,
            'merchant_id' => $order->member_id,
            'amount' => $order->amount,
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

        Log::write('代付通知下游：data ' . json_encode($data) . ', result: ' . $rse, 'info');
        $code = $rse == 'success' ? OrderNotifyLog::STATUS_NOTIFY_SUCCESS : OrderNotifyLog::STATUS_NOTIFY_FAIL;

        $log = new OrderNotifyLog();
        $log->order_no = $order->order_no;
        $log->notify_url = $order->notify_url;
        $log->notify_data = json_encode($data);
        $log->notify_status = $code;
        $log->notify_result = $rse ?? '';
        $log->notify_type = 2;
        $log->save();

        // 修改通知次数和状态
        $order->notify_count += 1;
        $order->notify_status = $code;

        return $order->save();

    }

    /**
     * 查询订单
     * @param $params
     * @return array
     * @throws \Exception
     */
    public function queryOrder($params, $is_sign = true, $is_eno = false)
    {
        // 获取用户
        $member = Member::where('status', OrderInService::STATUS_OPEN)->find($params['merchant_id']);
        if (!$member){
            throw new \Exception('用户不存在');
        }
        // 签名验证
        if ($is_sign){
            $signService = new SignService();
            if (!$signService->checkSign($params, $member->api_key)){
                throw new \Exception('签名错误');
            }
        }
        if ($is_eno){
            $order = OrderOut::where('member_id', $params['merchant_id'])
                ->with('channel')
                ->whereRaw('(e_no = :e_no or member_order_no = :member_order_no)',
                    ['e_no' => $params['merchant_order_no'], 'member_order_no' => $params['merchant_order_no']])
                ->find();
        }else{
            $order = OrderOut::where('member_id', $params['merchant_id'])->where('member_order_no', $params['merchant_order_no'])->with('channel')->find();

        }

        if (!$order){
            throw new \Exception('订单不存在');
        }

        return [
            'order_no' => $order->order_no,
            'merchant_order_no' => $order->member_order_no,
            'merchant_id' => $order->member_id,
            'status' => $order->status,
            'amount' => $order->amount,
            'pay_date' => $order->pay_success_date ?? '',
            'msg' => $order->error_msg ?? 'OK',
        ];
    }


    /**
     * 获取凭证
     * @param $order
     * @return array
     */
    public function getVoucher($order)
    {

        // 查询是否已经生成凭证
        $response = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_VOUCHER)->find();

        if (!$response){
            $paymentService = new PaymentService($order->channel->code);
            $response = $paymentService->getVoucher($order->channel, $order);
            if (!$response['status']){
                throw new \Exception($response['msg']);
            }

            // 保存
            $log = new OrderRequestService();
            $log->create(
                $order->order_no,
                OrderRequestLog::REQUEST_TYPE_VOUCHER,
                OrderRequestLog::ORDER_TYPE_OUT,
                '',
                json_encode($response['data'])
            );

            // 修改E_NO
            $order->e_no = $response['data']['e2e'];
            $order->save();
            $response['response_data'] = json_encode($response['data']);
        }

        Cache::set('voucher_'.$order->order_no,  $response['response_data'], 600);


        return json_decode($response['response_data'], true);

    }

    /**
     * 获取凭证数据
     * @param $order_no
     * @return array
     * @throws \Exception
     */
    public function getVoucherData($order_no)
    {

        $order = OrderOut::with('channel')
            ->where('order_no', $order_no)
            ->where('status', OrderOut::STATUS_PAID)
            ->find();

        if (!$order){
            throw new \Exception('order not found');
        }

        $data = Cache::get('voucher_'.$order_no);
        if (!$data){
            $data = $this->getVoucher($order);
            Cache::set('voucher_'.$order_no, json_encode($data), 600);
        }else{
            $data = json_decode($data, true);
        }

        // 解析数据
        $paymentService = new PaymentService($order->channel->code);
        $voucher = $paymentService->parseVoucher($data);

        $extra = json_decode($order->extra, true);

        $res = [
            'amount' => 'R$ ' . $order->amount,
            'order_no' => $order->order_no,
            'e_no' => $voucher['e_no'],
            'date' => $voucher['pay_date'],
            'payer_name' => $voucher['payer_name'],
            'payer_account' => $voucher['payer_account'],
            'payer_type' => $voucher['type'],
            // 收款
            'payee_name' => $extra['pix_name'] ?? '',
            'payee_account' => $extra['pix_key'] ?? '',
        ];

        return $res;
    }


    /**
     * 获取支付成功，但未通知的订单
     */
    public function getUnNotifyOrder($time)
    {
        $orders = OrderOut::where('status', OrderOut::STATUS_PAID)
            ->where('pay_success_date', '<', $time)
            ->where('notify_status', OrderNotifyLog::STATUS_NOTIFY_WAIT)
            ->limit(10)
            ->select();
        return $orders;
    }
}