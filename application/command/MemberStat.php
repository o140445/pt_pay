<?php

namespace app\command;


use app\common\model\merchant\MemberStatModel;
use think\console\Command;

class MemberStat extends Command
{
    protected function configure()
    {
        $this->setName('member:stat')
            ->setDescription('会员统计');
    }

    protected function execute($input, $output)
    {
        // 查询昨天的会员统计
        $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $is_yesterday_key = 'member_stat_' . date('Y-m-d', strtotime('-1 day'));
        $is_yesterday = cache($is_yesterday_key);
        if ($is_yesterday >= 12) {
            $start = date('Y-m-d 00:00:00');
        }else{
           if (empty($is_yesterday)) {
                $is_yesterday = 1;
            }else{
                $is_yesterday += 1;
            }
        }

        $end = date('Y-m-d 23:59:59', strtotime($start));
        $output->writeln('开始统计会员数据: start=' . $start . ', end=' . $end);

        // 先统计代收单
        $in_sql = "SELECT 
            member_id,
            count(1) as in_order_count,
            sum(amount) as in_order_amount,
            sum(if(status = 2, fee_amount, 0)) as in_fee,
            sum(if(status = 2, 1, 0)) as in_order_success_count,
            sum(if(status = 2, actual_amount, 0)) as in_order_success_amount,
            DATE_FORMAT(create_time, '%Y-%m-%d') as date
            FROM fa_order_in WHERE  create_time BETWEEN '{$start}' AND '{$end}' GROUP BY member_id, date";

        $in_order = db()->query($in_sql);

        // 再统计代付单
        $out_sql = "SELECT 
            member_id,
            count(1) as out_order_count,
            sum(amount) as out_order_amount,
            sum(if(status = 2, fee_amount, 0)) as out_fee,
            sum(if(status = 2, 1, 0)) as out_order_success_count,
            sum(if(status = 2, actual_amount, 0)) as out_order_success_amount,
            DATE_FORMAT(create_time, '%Y-%m-%d') as date
            FROM fa_order_out WHERE create_time BETWEEN '{$start}' AND '{$end}' GROUP BY member_id, date";

        $out_order = db()->query($out_sql);

        // 统计完成
        $member_stat = [];
        if ($in_order) {
            foreach ($in_order as $item) {
                $member_id = $item['member_id'];
                $date = $item['date'];
                $member_stat[$member_id][$date]['in_order_count'] = $item['in_order_count'];
                $member_stat[$member_id][$date]['in_order_amount'] = $item['in_order_amount'];
                $member_stat[$member_id][$date]['in_fee'] = $item['in_fee'];
                $member_stat[$member_id][$date]['in_order_success_count'] = $item['in_order_success_count'];
                $member_stat[$member_id][$date]['in_order_success_amount'] = $item['in_order_success_amount'];
                $member_stat[$member_id][$date]['in_success_rate'] = $item['in_order_count'] ? round($item['in_order_success_count'] / $item['in_order_count'], 2) : 0;
                $member_stat[$member_id][$date]['out_order_count'] = 0;
                $member_stat[$member_id][$date]['out_order_amount'] = 0;
                $member_stat[$member_id][$date]['out_fee'] = 0;
                $member_stat[$member_id][$date]['out_order_success_count'] = 0;
                $member_stat[$member_id][$date]['out_order_success_amount'] = 0;
                $member_stat[$member_id][$date]['out_success_rate'] = 0;

            }
        }

        if ($out_order) {
            foreach ($out_order as $item) {
                $member_id = $item['member_id'];
                $date = $item['date'];
                $member_stat[$member_id][$date]['out_order_count'] = $item['out_order_count'];
                $member_stat[$member_id][$date]['out_order_amount'] = $item['out_order_amount'];
                $member_stat[$member_id][$date]['out_fee'] = $item['out_fee'];
                $member_stat[$member_id][$date]['out_order_success_count'] = $item['out_order_success_count'];
                $member_stat[$member_id][$date]['out_order_success_amount'] = $item['out_order_success_amount'];
                $member_stat[$member_id][$date]['out_success_rate'] = $item['out_order_count'] ? round($item['out_order_success_count'] / $item['out_order_count'], 2) : 0;
                $member_stat[$member_id][$date]['in_order_count'] = $member_stat[$member_id][$date]['in_order_count'] ?? 0;
                $member_stat[$member_id][$date]['in_order_amount'] = $member_stat[$member_id][$date]['in_order_amount'] ?? 0;
                $member_stat[$member_id][$date]['in_fee'] = $member_stat[$member_id][$date]['in_fee'] ?? 0;
                $member_stat[$member_id][$date]['in_order_success_count'] = $member_stat[$member_id][$date]['in_order_success_count'] ?? 0;
                $member_stat[$member_id][$date]['in_order_success_amount'] = $member_stat[$member_id][$date]['in_order_success_amount'] ?? 0;
                $member_stat[$member_id][$date]['in_success_rate'] = $member_stat[$member_id][$date]['in_success_rate'] ?? 0;
            }
        }

        // 写入数据库 先查询是否已经统计过
        foreach ($member_stat as $member_id => $stat) {
            foreach ($stat as $date => $data) {
                $count = MemberStatModel::where('member_id', $member_id)
                    ->where('date', $date)
                    ->find();

                if ($count) {
                    MemberStatModel::where('member_id', $member_id)
                        ->where('date', $date)
                        ->update($data);
                    $output->writeln('会员统计更新:' . $member_id . ' _ ' . $date);
                } else {
                    $data['member_id'] = $member_id;
                    $data['date'] = $date;
                    MemberStatModel::create($data);
                    $output->writeln('会员统计新增:' . $member_id . ' _ ' . $date);
                }
            }
        }


        $output->writeln('会员统计完成');

        if ($is_yesterday < 12) {
            cache($is_yesterday_key, $is_yesterday, 86400);
        }
    }

}