<?php

namespace app\command;

use app\common\model\merchant\OrderNotifyLog;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\OrderRequestLog;
use app\common\service\OrderOutService;
use app\common\service\PaymentService;
use think\console\Command;
use think\Db;

class QueryOutStatus extends Command
{
    /**
     * @var OrderOutService
     */
    protected  $outService;

    protected function configure()
    {
        $this->setName('query:outStatus')
            ->setDescription('Query out status');
    }

    protected function execute($input, $output)
    {
        $this->output->writeln('Query out status');
        // 查询支付中的订单 and 通知状态为通知成功的
        $order = OrderOut::where('status', OrderOut::STATUS_PAYING) // 支付中
            ->where('channel_id',  7) // 通道为 8
            ->limit(480)
            ->select();

        $this->outService = new OrderOutService();

        foreach ($order as $item) {

            $result =  [
                'order_no' => $item->order_no, // 订单号
                'channel_no' => $item->channel_order_no, // 渠道订单号
                'pay_date' => '', // 支付时间
                'status' => OrderOut::STATUS_PAID, // 状态 2成功 3失败 4退款
                'e_no' =>  '', // 业务订单号
                'data' => '', // 数据
                'msg' => 'sucesso', // 消息
            ];


//            // 检查状态
            if ($result['status'] == OrderOut::STATUS_PAID) {
                // 完成订单
                $this->completeOrder($item, $result);
            }
        }

    }

    /**
     * 完成订单
     */
    protected function completeOrder($order, $data){

        Db::startTrans();
        try {
            $this->outService->completeOrder($order, $data);
            Db::commit();

        } catch (\Exception $e) {
            Db::rollback();
            $this->output->writeln('Complete order error: ' . $e->getMessage() . ' order_no: ' . $order->order_no);
            return;
        }
        $this->output->writeln('Complete order success, order_no: ' . $order->order_no);
        // 通知下游
        $this->notifyDownstream($order);
    }

    /**
     * 失败订单
     */
    protected function failOrder($order, $data)
    {
        Db::startTrans();
        try {
            $this->outService->failOrder($order, $data);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->output->writeln('Fail order error: ' . $e->getMessage() . ' order_no: ' . $order->order_no);
            return;
        }

        $this->output->writeln('Fail order success, order_no: ' . $order->order_no);

        // 通知下游
        $this->notifyDownstream($order);

    }

    /**
     * 通知下游
     */
    protected function notifyDownstream($order)
    {
        Db::startTrans();
        try {
            $this->outService->notifyDownstream($order->id);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->output->writeln('Notify downstream error: ' . $e->getMessage(). ' order_no: ' . $order->order_no);
            return;
        }

        $this->output->writeln('Notify downstream success, order_no: ' . $order->order_no);
    }
}