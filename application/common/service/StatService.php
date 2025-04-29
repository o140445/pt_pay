<?php

namespace app\common\service;

use app\common\model\merchant\ChannelStatModel;
use app\common\model\merchant\Member;
use app\common\model\merchant\MemberStatModel;
use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\ProfitStatModel;

class StatService
{
    public function add($order, $type = 'in')
    {
        $this->addChannel($order, $type);
        $this->addMember($order, $type);
    }

    public function update($order, $type = 'in')
    {
        $this->updateChannel($order, $type);
        $this->updateMember($order, $type);
    }

    public function addChannel($order, $type = 'in')
    {
        // 查询今天是否有记录
        $model = ChannelStatModel::where('channel_id', $order->channel_id)
            ->where('date', date('Y-m-d'))
            ->lock(true)
            ->find();

        if ($model) {
            if ($type == 'in') {
                $model->in_order_count += 1;
                $model->in_order_amount += $order->amount;
                $model->in_success_rate = $model->in_order_count ? round($model->in_order_success_count / $model->in_order_count, 2) : 0;
            } else {
                $model->out_order_count += 1;
                $model->out_order_amount += $order->amount;
                $model->out_success_rate = $model->out_order_count ? round($model->out_order_success_count / $model->out_order_count, 2) : 0;
            }

            $model->save();
        } else {
            ChannelStatModel::create([
                'channel_id' => $order->channel_id,
                'date' => date('Y-m-d'),
                'in_order_count' => $type == 'in' ? 1 : 0,
                'in_order_amount' => $type == 'in' ? $order->amount : 0,
                'in_channel_fee' => 0,
                'in_order_success_count' =>  0,
                'in_order_success_amount' => 0,
                'in_success_rate' => 0,
                'out_order_count' => $type == 'out' ? 1 : 0,
                'out_order_amount' => $type == 'out' ? $order->amount : 0,
                'out_channel_fee' => 0,
                'out_order_success_count' => 0,
                'out_order_success_amount' =>  0,
                'out_success_rate' =>  0,
            ]);
        }
    }

    public function updateChannel($order, $type = 'in')
    {
        // 查询今天是否有记录
        $model = ChannelStatModel::where('channel_id', $order->channel_id)
            ->where('date', date(strtotime($order->create_time)))
            ->lock(true)
            ->find();

        if ($model) {
            if ($type == 'in') {
                $model->in_channel_fee += $order->channel_fee_amount;
                $model->in_order_success_count += 1;
                $model->in_order_success_amount += $order->true_amount;
                $model->in_success_rate = $model->in_order_count ? round($model->in_order_success_count / $model->in_order_count, 2) : 0;
            } else {
                $model->out_channel_fee += $order->channel_fee_amount;
                $model->out_order_success_count += 1;
                $model->out_order_success_amount += $order->amount;
                $model->out_success_rate = $model->out_order_count ? round($model->out_order_success_count / $model->out_order_count, 2) : 0;
            }

            $model->save();
        }
    }

    public function addMember($order, $type = "in")
    {
        $model = MemberStatModel::where('member_id', $order->member_id)
            ->where('date', date(strtotime($order->create_time)))
            ->lock(true)
            ->find();

        if ($model) {
            if ($type == 'in') {
                $model->in_order_count += 1;
                $model->in_order_amount += $order->amount;
                $model->in_fee += $order->fee_amount;
                $model->in_success_rate = $model->in_order_count ? round($model->in_order_success_count / $model->in_order_count, 2) : 0;
            } else {
                $model->out_order_count += 1;
                $model->out_order_amount += $order->amount;
                $model->out_fee += $order->fee_amount;
                $model->out_success_rate = $model->out_order_count ? round($model->out_order_success_count / $model->out_order_count, 2) : 0;
            }

            $model->save();
        } else {
            MemberStatModel::create([
                'member_id' => $order->member_id,
                'date' => date(strtotime($order->create_time)),
                'in_order_count' => $type == 'in' ? 1 : 0,
                'in_order_amount' => $type == 'in' ? $order->amount : 0,
                'in_fee' => $type == 'in' ? $order->fee : 0,
                'in_order_success_count' => $type == 'in' ? 1 : 0,
                'in_order_success_amount' => $type == 'in' ? $order->true_amount : 0,
                'in_success_rate' => $type == 'in' ? 1 : 0,
                'out_order_count' => $type == 'out' ? 1 : 0,
                'out_order_amount' => $type == 'out' ? $order->amount : 0,
                'out_fee' => $type == 'out' ? $order->fee : 0,
                'out_order_success_count' => $type == 'out' ? 1 : 0,
                'out_order_success_amount' => $type == 'out' ? $order->true_amount : 0,
                'out_success_rate' => $type == 'out' ? 1 : 0,
            ]);
        }
    }

    public function updateMember($order, $type = "in")
    {
        $model = MemberStatModel::where('member_id', $order->member_id)
            ->where('date', date(strtotime($order->create_time)))
            ->lock(true)
            ->find();

        if ($model) {
            if ($type == 'in') {
                $model->in_order_success_count += 1;
                $model->in_order_success_amount += $order->true_amount;
                $model->in_success_rate = $model->in_order_count ? round($model->in_order_success_count / $model->in_order_count, 2) : 0;
            } else {
                $model->out_order_success_count += 1;
                $model->out_order_success_amount += $order->amount;
                $model->out_success_rate = $model->out_order_count ? round($model->out_order_success_count / $model->out_order_count, 2) : 0;
            }

            $model->save();
        }
    }

    public function addProfits($profit, $type = "in")
    {
        $model = ProfitStatModel::where('create_time', date(strtotime($profit->create_time)))
            ->where('area_id', $profit['area_id'])
            ->lock(true)
            ->find();

        if ($model) {
            if ($type == 'in') {
                $model->in_order_count += 1;
                $model->in_order_amount += $profit->order_amount;
                $model->in_fee += $profit->fee;
                $model->in_channel_fee += $profit->channel_fee;
                $model->in_commission += $profit->commission;
                $model->in_profit += $profit->profit;
            } else {
                $model->out_order_count += 1;
                $model->out_order_amount += $profit->order_amount;
                $model->out_fee += $profit->fee;
                $model->out_channel_fee += $profit->channel_fee;
                $model->out_commission += $profit->commission;
                $model->out_profit += $profit->profit;
            }

            $model->profit += $profit->profit;
        }else{
            ProfitStatModel::create([
                'area_id' => $profit->area_id,
                'create_time' => date(strtotime($profit->create_time)),
                'in_order_count' => $type == 'in' ? 1 : 0,
                'in_order_amount' => $type == 'in' ? $profit->order_amount : 0,
                'in_fee' => $type == 'in' ? $profit->fee : 0,
                'in_channel_fee' => $type == 'in' ? $profit->channel_fee : 0,
                'in_commission' => $type == 'in' ? $profit->commission : 0,
                'in_profit' => $type == 'in' ? $profit->profit : 0,
                'out_order_count' => $type == 'out' ? 1 : 0,
                'out_order_amount' => $type == 'out' ? $profit->order_amount : 0,
                'out_fee' => $type == 'out' ? $profit->fee : 0,
                'out_channel_fee' => $type == 'out' ? $profit->channel_fee : 0,
                'out_commission' => $type == 'out' ? $profit->commission : 0,
                'out_profit' => $type == 'out' ? $profit->profit : 0,
                'profit' => $profit->profit,
            ]);
        }

    }

    /**
     * getDayDetail
     */
    public function getDayDetail($member_id, $date)
    {
        // 查询member_id是否存在
        $member = Member::where('id', $member_id)->find();
        if (!$member) {
            throw new \Exception('会员不存在');
        }

        // 查询今天是否有记录
//        $model = MemberStatModel::where('member_id', $member_id)
//            ->where('date', date(strtotime($date)))
//            ->lock(true)
//            ->find();
//
//        if (!$model) {
//            throw new \Exception('没有统计数据');
//        }

        // 总代收单，代收成功单，成功率，代收金额，平局金额, 按小时分类统计[总比数，成功比数，成功率，代收金额]
        $orderIn = OrderIn::where('member_id', $member_id)
            ->field('id,amount,true_amount,status,create_time')
            ->where('create_time', '>=', $date . ' 00:00:00')
            ->where('create_time', '<=', $date . ' 23:59:59')
            ->select();
        if (!$orderIn) {
            $orderIn = [];
        }
        $orderInCount = count($orderIn);
        $orderInSuccessCount = 0;
        $orderInSuccessAmount = 0;
        $orderInSuccessRate = 0;
        $orderInAmount = 0;
        // 平均金额
        $orderInAverageAmount = 0;
        $orderInHour = [];
        foreach ($orderIn as $item) {
//            $item = $item->toArray();
            $hour = date('H', strtotime($item['create_time']));
            if (!isset($orderInHour[$hour])) {
                $orderInHour[$hour] = [
                    'hour' => $hour,
                    'count' => 0,
                    'success_count' => 0,
                    'success_rate' => 0,
                    'amount' => 0,
                    'success_amount' => 0,
                ];
            }
            $orderInHour[$hour]['count'] += 1;
            $orderInHour[$hour]['amount'] += $item['amount'];
            $orderInAmount += $item['amount'];
            if ($item['status'] == OrderIn::STATUS_PAID) {
                $orderInSuccessCount += 1;
                $orderInSuccessAmount += $item['true_amount'];
                $orderInHour[$hour]['success_count'] += 1;
                $orderInHour[$hour]['success_rate'] = round($orderInHour[$hour]['success_count'] / $orderInHour[$hour]['count'], 2);
                $orderInHour[$hour]['success_amount'] += $item['true_amount'];
            }
        }

        if ($orderInCount > 0) {
            $orderInSuccessRate = round($orderInSuccessCount / $orderInCount, 2);
            $orderInAverageAmount = $orderInSuccessAmount > 0 ? round($orderInSuccessAmount / $orderInSuccessCount, 2) : 0;
        }

        // 总代付单，代付成功单，成功率，代付金额，平局金额, 按小时分类统计[总比数，成功比数，成功率，代付金额]
        $orderOut = OrderOut::where('member_id', $member_id)
            ->field('id,amount,status,create_time')
            ->where('create_time', '>=', $date . ' 00:00:00')
            ->where('create_time', '<=',$date . ' 23:59:59')
            ->select();
        if (!$orderOut) {
            $orderOut = [];
        }
        $orderOutCount = count($orderOut);
        $orderOutSuccessCount = 0;
        $orderOutSuccessAmount = 0;
        $orderOutSuccessRate = 0;
        $orderOutAmount = 0;
        // 平均金额
        $orderOutAverageAmount = 0;
        $orderOutHour = [];
        foreach ($orderOut as $item) {
            $hour = date('H', strtotime($item['create_time']));
            if (!isset($orderOutHour[$hour])) {
                $orderOutHour[$hour] = [
                    'hour' => $hour,
                    'count' => 0,
                    'success_count' => 0,
                    'success_rate' => 0,
                    'amount' => 0,
                    'success_amount' => 0,
                ];
            }
            $orderOutHour[$hour]['count'] += 1;
            $orderOutHour[$hour]['amount'] += $item['amount'];
            $orderOutAmount += $item['amount'];
            if ($item['status'] == OrderOut::STATUS_PAID) {
                $orderOutSuccessCount += 1;
                $orderOutSuccessAmount += $item['amount'];
                $orderOutHour[$hour]['success_count'] += 1;
                $orderOutHour[$hour]['success_rate'] = round($orderOutHour[$hour]['success_count'] / $orderOutHour[$hour]['count'], 2);
                $orderOutHour[$hour]['success_amount'] += $item['amount'];
            }
        }

        if ($orderOutCount > 0) {
            $orderOutSuccessRate = round($orderOutSuccessCount / $orderOutCount, 2);
            $orderOutAverageAmount = $orderOutSuccessAmount > 0 ? round($orderOutSuccessAmount / $orderOutSuccessCount, 2) : 0;
        }

        // 统计数据
        $data = [
            'order_in' => [
                'count' => $orderInCount,
                'success_count' => $orderInSuccessCount,
                'success_rate' => $orderInSuccessRate,
                'amount' => $orderInAmount,
                'success_amount' => $orderInSuccessAmount,
                'average_amount' => $orderInAverageAmount,
                'detail' => array_values($orderInHour),
            ],
            'order_out' => [
                'count' => $orderOutCount,
                'success_count' => $orderOutSuccessCount,
                'success_rate' => $orderOutSuccessRate,
                'amount' => $orderOutAmount,
                'success_amount' => $orderOutSuccessAmount,
                'average_amount' => $orderOutAverageAmount,
                'detail' => array_values($orderOutHour),
            ],
        ];

        return $data;
    }
}