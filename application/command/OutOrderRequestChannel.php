<?php

namespace app\command;

use app\common\model\merchant\OrderOut;
use app\common\service\OrderOutService;
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

        // 获取所有未处理的订单 100条
        $orderOut = OrderOut::where('status', OrderOut::STATUS_UNPAID)
            ->limit(30)
            ->select();

        if (!$orderOut) {
            $output->writeln('没有未处理的代付单');
            return;
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