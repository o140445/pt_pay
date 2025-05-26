<?php

namespace app\command;

use app\common\model\merchant\ProfitStatModel;
use think\console\Command;
use think\Log;

class ProfitsStat extends Command
{
    protected function configure()
    {
        $this->setName('profits:stat')
            ->setDescription('利润统计');
    }

    protected function execute($input, $output)
    {
        $start = date('Y-m-d 00:00:00', strtotime('-1 day'));
        // 昨天是否已经统计完成
        $is_yesterday_key = 'profits_stat_' . date('Y-m-d', strtotime('-1 day'));
        $is_yesterday = cache($is_yesterday_key);
        if ($is_yesterday < 10) {
            $start = date('Y-m-d 00:00:00');
        } else {
            $is_yesterday += 1;
        }
        $end = date('Y-m-d 23:59:59', strtotime($start));
        Log::write('开始统计利润数据: start=' . $start . ', end=' . $end, 'info');

        $sql = "SELECT 
            area_id,
            SUM(IF(order_type = 1, 1, 0)) AS in_order_count,
            SUM(IF(order_type = 1, order_amount, 0)) AS in_order_amount,
            SUM(IF(order_type = 1, fee, 0)) AS in_fee,
            SUM(IF(order_type = 1, channel_fee, 0)) AS in_channel_fee,
            SUM(IF(order_type = 1, commission, 0)) AS in_commission,
            SUM(IF(order_type = 1, profit, 0)) AS in_profit,
            SUM(IF(order_type = 2, 1, 0)) AS out_order_count,
            SUM(IF(order_type = 2, order_amount, 0)) AS out_order_amount,
            SUM(IF(order_type = 2, fee, 0)) AS out_fee,
            SUM(IF(order_type = 2, channel_fee, 0)) AS out_channel_fee,
            SUM(IF(order_type = 2, commission, 0)) AS out_commission,
            SUM(IF(order_type = 2, profit, 0)) AS out_profit,
            SUM(profit) AS profit,
            DATE_FORMAT(create_time, '%Y-%m-%d') AS date
            FROM fa_profit WHERE create_time BETWEEN '{$start}' AND '{$end}' GROUP BY area_id, date";

        $profit = db()->query($sql);

        if ($profit) {
            foreach ($profit as $item) {
                $data = [
                    'in_order_count' => $item['in_order_count'],
                    'in_order_amount' => $item['in_order_amount'],
                    'in_fee' => $item['in_fee'],
                    'in_channel_fee' => $item['in_channel_fee'],
                    'in_commission' => $item['in_commission'],
                    'in_profit' => $item['in_profit'],
                    'out_order_count' => $item['out_order_count'],
                    'out_order_amount' => $item['out_order_amount'],
                    'out_fee' => $item['out_fee'],
                    'out_channel_fee' => $item['out_channel_fee'],
                    'out_commission' => $item['out_commission'],
                    'out_profit' => $item['out_profit'],
                    'profit' => $item['profit'],
                ];

                // 查询是否已经统计过
                $count = ProfitStatModel::where('area_id', $item['area_id'])
                    ->where('date', $item['date'])
                    ->find();
                if ($count) {
                    ProfitStatModel::where('area_id', $item['area_id'])
                        ->where('date',  $item['date'])
                        ->update($data);
                    Log::write('利润统计更新:' . $item['area_id'] . ' _ ' . $item['date'], 'info');
                    $output->writeln('利润统计更新:' . $item['area_id'] . ' _ ' . $item['date']);
                } else {
                    $data['area_id'] = $item['area_id'];
                    $data['date'] =  $item['date'];
                    ProfitStatModel::create($data);
                    Log::write('利润统计新增:' . $item['area_id'] . ' _ ' . $item['date'], 'info');
                    $output->writeln('利润统计新增:' . $item['area_id'] . ' _ ' . $item['date']);
                }

            }
        }

        // 写入日志
        Log::write('利润统计完成', 'info');
        $output->writeln('利润统计完成');

        // 标记昨天
        if ($is_yesterday < 10) {
            cache($is_yesterday_key, $is_yesterday, 86400);
        }
    }
}