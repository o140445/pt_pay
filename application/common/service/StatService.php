<?php

namespace app\common\service;

use app\common\model\merchant\ChannelStatModel;
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
        return [];
    }
}