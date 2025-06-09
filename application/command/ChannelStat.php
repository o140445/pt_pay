<?php

namespace app\command;

use app\common\model\merchant\ChannelStatModel;
use think\console\Command;
use think\Log;

class ChannelStat extends Command
{
    protected function configure()
    {
        $this->setName('channel:stat')
            ->setDescription('渠道统计');
    }

    protected function execute($input, $output)
    {
        // 查询10分钟的渠道统计
        $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $is_yesterday_key = 'channel_stat_' . date('Y-m-d', strtotime('-1 day'));
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
        Log::write('开始统计渠道数据: start=' . $start . ', end=' . $end, 'info');

        // 先统计代收单
        $in_sql = "SELECT 
            channel_id,
            count(1) as in_order_count,
            sum(amount) as in_order_amount,
            sum(channel_fee_amount) as in_channel_fee,
            sum(if(status = 2, 1, 0)) as in_order_success_count,
            sum(if(status = 2, true_amount, 0)) as in_order_success_amount,
            DATE_FORMAT(create_time, '%Y-%m-%d') as date
            FROM fa_order_in WHERE  create_time BETWEEN '{$start}' AND '{$end}' GROUP BY channel_id, date";

        $in_order = db()->query($in_sql);

        // 再统计代付单
        $out_sql = "SELECT 
            channel_id,
            count(1) as out_order_count,
            sum(amount) as out_order_amount,
            sum(if(status = 2, channel_fee_amount, 0)) as out_channel_fee,
            sum(if(status = 2, 1, 0)) as out_order_success_count,
            sum(if(status = 2, amount, 0)) as out_order_success_amount,
            DATE_FORMAT(create_time, '%Y-%m-%d') as date
            FROM fa_order_out WHERE create_time BETWEEN '{$start}' AND '{$end}' GROUP BY channel_id, date";

        $out_order = db()->query($out_sql);

        // 统计完成
        $channel_stat = [];
        if ($in_order) {
            foreach ($in_order as $item) {
                $channel_id = $item['channel_id'];
                $date = $item['date'];
                $channel_stat[$channel_id][$date]['in_order_count'] = $item['in_order_count'];
                $channel_stat[$channel_id][$date]['in_order_amount'] = $item['in_order_amount'];
                $channel_stat[$channel_id][$date]['in_channel_fee'] = $item['in_channel_fee'];
                $channel_stat[$channel_id][$date]['in_order_success_count'] = $item['in_order_success_count'];
                $channel_stat[$channel_id][$date]['in_order_success_amount'] = $item['in_order_success_amount'];
                $channel_stat[$channel_id][$date]['in_success_rate'] = $item['in_order_count'] ? round($item['in_order_success_count'] / $item['in_order_count'], 2) : 0;
                $channel_stat[$channel_id][$date]['out_order_count'] = 0;
                $channel_stat[$channel_id][$date]['out_order_amount'] = 0;
                $channel_stat[$channel_id][$date]['out_channel_fee'] = 0;
                $channel_stat[$channel_id][$date]['out_order_success_count'] = 0;
                $channel_stat[$channel_id][$date]['out_order_success_amount'] = 0;
                $channel_stat[$channel_id][$date]['out_success_rate'] = 0;
            }
        }

        if ($out_order) {
            foreach ($out_order as $item) {
                $channel_id = $item['channel_id'];
                $date = $item['date'];
                $channel_stat[$channel_id][$date]['out_order_count'] = $item['out_order_count'];
                $channel_stat[$channel_id][$date]['out_order_amount'] = $item['out_order_amount'];
                $channel_stat[$channel_id][$date]['out_channel_fee'] = $item['out_channel_fee'];
                $channel_stat[$channel_id][$date]['out_order_success_count'] = $item['out_order_success_count'];
                $channel_stat[$channel_id][$date]['out_order_success_amount'] = $item['out_order_success_amount'];
                $channel_stat[$channel_id][$date]['out_success_rate'] = $item['out_order_count'] ? round($item['out_order_success_count'] / $item['out_order_count'], 2) : 0;
                $channel_stat[$channel_id][$date]['in_order_count'] = $channel_stat[$channel_id][$date]['in_order_count'] ?? 0;
                $channel_stat[$channel_id][$date]['in_order_amount'] = $channel_stat[$channel_id][$date]['in_order_amount'] ?? 0;
                $channel_stat[$channel_id][$date]['in_channel_fee'] = $channel_stat[$channel_id][$date]['in_channel_fee'] ?? 0;
                $channel_stat[$channel_id][$date]['in_order_success_count'] = $channel_stat[$channel_id][$date]['in_order_success_count'] ?? 0;
                $channel_stat[$channel_id][$date]['in_order_success_amount'] = $channel_stat[$channel_id][$date]['in_order_success_amount'] ?? 0;
                $channel_stat[$channel_id][$date]['in_success_rate'] = $channel_stat[$channel_id][$date]['in_success_rate'] ?? 0;
            }
        }

        // 保存到数据库
        foreach ($channel_stat as $channel_id => $stat) {
            foreach ($stat as $date => $item) {
                // 查询是否已经存在
                $exist = ChannelStatModel::where('channel_id', $channel_id)->where('date', $date)->find();
                if ($exist) {
                    ChannelStatModel::where('channel_id', $channel_id)->where('date', $date)->update($item);
                    Log::write('更新渠道统计: channel_id=' . $channel_id . ', date=' . $date, 'info');
                } else {
                    $item['channel_id'] = $channel_id;
                    $item['date'] = $date;
                    ChannelStatModel::create($item);
                    Log::write('新增渠道统计: channel_id=' . $channel_id . ', date=' . $date, 'info');
                }
            }
        }

        $output->writeln('渠道统计完成');

        // 标记已经统计过
        if ($is_yesterday <= 12) {
            cache($is_yesterday_key, $is_yesterday, 86400);
        }

    }
}