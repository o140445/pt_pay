<?php

namespace app\command;

use app\common\service\OrderOutService;
use think\console\Command;
use think\Db;
use think\Log;

class OrderOutDelay extends Command
{
    protected function configure()
    {
        $this->setName('order_out_delay:notify')
            ->setDescription('Order Out Delay');
    }

    protected function execute($input, $output)
    {
        $output->writeln('Order Out Delay start');
        Log::init([
            'type'  => 'File',
            'path'  => LOG_PATH . 'pay/',
            'level' => ['error', 'info'],
        ]);


        // 获取所有未处理的订单 30s前的订单 50条
        $date = date('Y-m-d H:i:s', strtotime('-30 seconds'));
        $orderOutDelay = \app\common\model\merchant\OrderOutDelay::where('status', 0)
            ->where('create_time', '<', $date)
            ->limit(50)
            ->select();

        $outService = new OrderOutService();
        foreach ($orderOutDelay as $item) {
            $data = json_decode($item->data, true);
            // 查询超过3次的订单就不再处理
            if ($item->retry_count >= 3) {
                // 更新订单状态
                $item->status = 1;
                $item->save();
                continue;
            }

            Db::startTrans();
            try {
                $res =   $outService->notify($data['out_code'], $data);
            }catch (\Exception $e) {
                Db::rollback();
                Log::write('代付请求失败：error' . $e->getMessage() .', data:' . json_encode($data), 'error');
                $output->writeln('代付请求失败：error' . $e->getMessage() .', data:' . json_encode($data));
                $item->retry_count += 1; // 重试次数加1
                $item->save();
                return;
            }
            Db::commit();

            // 成功
            if ($res['order_id']) {
                Db::startTrans();
                try {
                    $outService->notifyDownstream($res['order_id']);
                }catch (\Exception $e) {
                    Db::rollback();
                    Log::write('代付回调通知下游失败：error' . $e->getMessage() .', order_id:' . $res['order_id'], 'error');
                }
                Db::commit();
            }else{
                Db::rollback();
                Log::error('代付回调请求失败 error:  data:' . json_encode($data) . ', msg:' . $res['msg']);
            }

            $output->writeln('Order Out Delay order_id: ' . $item->source . ' channel: ' . $data['out_code'] . ' msg: ' . $res['msg']);
            // 更新订单状态
            $item->status = 1;
            $item->retry_count += 1; // 重试次数加1
            $item->save();
        }

        $output->writeln('Order Out Delay end');
    }
}