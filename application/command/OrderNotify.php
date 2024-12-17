<?php

namespace app\command;

use app\common\service\OrderInService;
use app\common\service\OrderOutService;
use think\console\Command;
use think\Db;
use think\Log;

class OrderNotify  extends Command
{
    protected function configure()
    {
        $this->setName('order_notify:notify')
            ->setDescription('Order Notify');
    }

    protected function execute($input, $output)
    {
       // 支付成功，未通知的代收单
        $this->getOrderIns();
        // 代付成功，未通知的代付单
        $this->getOrderOuts();
    }

    /**
     * 获取未通知的代收单
     */
    private function getOrderIns()
    {
        // 获取5分钟前的订单
        $endTime = date('Y-m-d H:i:s', strtotime('-5 minute'));
        $orderInService = new OrderInService();
        $orderIns = $orderInService->getUnNotifyOrder($endTime);
        if (!$orderIns) {
            Log::write('没有未通知的代收单', 'info');
            $this->output->writeln('没有未通知的代收单');
            return;
        }

        foreach ($orderIns as $orderIn) {
            // 开启事务
            Db::startTrans();
            try {

                $orderInService->notifyDownstream($orderIn->id);
            }catch (\Exception $e) {
                Db::rollback();
                Log::write('定时任务代收回调通知下游失败：error' . $e->getMessage() .', order_id:' . $orderIn->order_no, 'error');
                $this->output->writeln('定时任务代收回调通知下游失败：error' . $e->getMessage() .', order_id:' . $orderIn->order_no);
            }
            Db::commit();
            Log::write('定时任务代收回调通知下游成功：order_id:' . $orderIn->order_no, 'info');
            $this->output->writeln('定时任务代收回调通知下游成功：order_id:' . $orderIn->order_no);
        }
    }

    /**
     * 获取未通知的代付单
     */
    private function getOrderOuts()
    {
        // 获取5分钟前的订单
        $endTime = date('Y-m-d H:i:s', strtotime('-5 minute'));
        $orderOutService = new OrderOutService();
        $orderOuts = $orderOutService->getUnNotifyOrder($endTime);
        if (!$orderOuts) {
            Log::write('没有未通知的代付单', 'info');
            $this->output->writeln('没有未通知的代付单');
            return;
        }

        foreach ($orderOuts as $orderOut) {
            // 开启事务
            Db::startTrans();
            try {
                $orderOutService->notifyDownstream($orderOut->id);
            }catch (\Exception $e) {
                Db::rollback();
                Log::write('定时任务代付回调通知下游失败：error' . $e->getMessage() .', order_id:' . $orderOut->order_no, 'error');
                $this->output->writeln('定时任务代付回调通知下游失败：error' . $e->getMessage() .', order_id:' . $orderOut->order_no);
            }
            Db::commit();
            Log::write('定时任务代付回调通知下游成功：order_id:' . $orderOut->order_no, 'info');
            $this->output->writeln('定时任务代付回调通知下游成功：order_id:' . $orderOut->order_no);
        }
    }
}