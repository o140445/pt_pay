<?php

namespace app\command;

use app\common\model\merchant\OrderOut;
use app\common\service\OrderOutService;
use think\Cache;
use think\Config;
use think\console\Command;
use think\Db;
use think\Log;

class OutOrderRequestChannel extends Command
{
    protected function configure()
    {
        $this->setName('out_order:request_channel')
            ->setDescription('outOrderRequestChannel');
    }

    protected function execute($input, $output)
    {
        Log::init([
            'type'  => 'File',
            'path'  => LOG_PATH . 'pay/out/',
            'level' => ['error', 'info'],
        ]);

        $big_customer_id = Config::get('big_customer');

        // 获取所有未处理的订单 100条
        $orderOut = OrderOut::where('status', OrderOut::STATUS_UNPAID)
            ->whereNotIn('member_id', $big_customer_id)
            ->limit(20)
            ->select();

        if (!$orderOut) {
            $output->writeln('没有未处理的代付单');
            return;
        }

        // ID加入缓存 哈希
        $key_prefix = 'request_channel_';
        foreach ($orderOut as $k => $item) {
            // 检查是否已经处理
            if (Cache::get($key_prefix . $item->id)){
                unset($orderOut[$k]);
                continue;
            }

            Cache::set($key_prefix . $item->id, 1, 600);
        }

        $outService = new OrderOutService();
        foreach ($orderOut as $item) {
            // 发起代付
            Db::startTrans();

            try {
                $res = $outService->requestChannel($item);
                Db::commit();
            }catch (\Exception $e) {
                Db::rollback();
                Log::write('代付回调请求失败：error' . $e->getMessage() .', data:' . json_encode($item), 'error');
                continue;
            }

            $output->writeln('代付回调请求结果：' . json_encode($res));
            Cache::rm($key_prefix . $item->id);
            // 失败
            if ($res['status'] == OrderOut::STATUS_FAILED) {

                //  通知下游
                Db::startTrans();
                try {
                    $outService->notifyDownstream($item->id);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    Log::write('代付回调通知下游失败：error' . $e->getMessage() .', order_id:' . $item->id, 'error');
                }

                $output->writeln('代付回调通知下游失败：order_id:' . $item->id);
            }

        }
    }
}