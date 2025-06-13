<?php

namespace app\command;

use app\common\model\merchant\Channel;
use app\common\service\channels\MBPayChannel;
use MongoDB\BSON\DBPointer;
use think\console\Command;

class MBChannelQuery extends Command
{
    public function configure()
    {
        $this->setName('mb:channel_query')
            ->setDescription('MB Channel Query');
    }

    public function execute($input, $output)
    {
        $output->writeln('MB Channel Query start');
        // 获取MbChannel
        $mbChannels = Channel::where('code', 'MBPay')
            ->where('status', 1)
            ->select();

        if (!$mbChannels || empty($mbChannels)) {
            $output->writeln('No MBPay channels found');
            return;
        }

        // 查询对于的订单
        foreach ($mbChannels as $channel) {
            $channelId = $channel->id;
            $output->writeln("Processing channel ID: {$channelId}");

            // 时间 3分钟前
            $date = date('Y-m-d H:i:s', strtotime('-3 minutes'));

            // 查询该渠道的订单
            $out_orders = \app\common\model\merchant\OrderOut::where('channel_id', $channelId)
                ->where('status', 5) // 只查询支付中
                ->where('channel_order_no',  '') // 没有渠道号的订单
                ->where('create_time', '<', $date)
                ->select();


            if (!$out_orders || empty($out_orders)) {
                $output->writeln("No orders found for channel ID: {$channelId}");
                continue;
            }

            // 遍历订单进行查询
            $channel_service = new MBPayChannel();
            foreach ($out_orders as $order) {
                $output->writeln("Querying order no: {$order->order_no} for channel ID: {$channelId}");

                try {
                    // 调用查询方法
                    $result = $channel_service->queryOrder($channel, $order->order_no);

                    // 只处理失败的
                    if ($result['status'] == 0)  {
                        $output->writeln("Order Order: {$order->order_no} - Query failed, Status: {$result['status']}, Message: {$result['msg']}");
                        $out_service = new \app\common\service\OrderOutService();

                        Db::startTrans();
                        try {
                            // 更新订单状态
                            $out_service->failOrder($order, $result);
                            Db::commit();
                        }catch (\Exception $e) {
                            $output->writeln("Error updating order ID: {$order->id}, Error: " . $e->getMessage());
                            Db::rollback();
                            continue; // 跳过当前订单，继续下一个
                        }

                        // 通知下游
                        Db::startTrans();
                        try {
                            $res = $out_service->notifyDownstream($order->id);
                            Db::commit();
                        } catch (\Exception $e) {
                            Db::rollback();
                            $output->writeln("Error notifying downstream for order ID: {$order->id}, Error: " . $e->getMessage());
                        }

                    }else{
                        $json = json_encode($result);
                        $output->writeln("Order Order: {$order->order_no} - Query successful, res: {$json}");
                    }
                } catch (\Exception $e) {
                    $output->writeln("Error querying order ID: {$order->id}, Error: " . $e->getMessage());
                }
            }
        }

        // 输出查询结果
        $output->writeln('MB Channel Query completed');
    }
}