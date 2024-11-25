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


        // 获取所有未处理的订单 1分钟前的订单 100条
        $date = date('Y-m-d H:i:s', strtotime('-1 minute'));
        $orderOutDelay = \app\common\model\merchant\OrderOutDelay::where('status', 0)
            ->where('create_time', '<', $date)
            ->limit(100)
            ->select();

        $outService = new OrderOutService();
        foreach ($orderOutDelay as $item) {
            $data = json_decode($item->data, true);
            // 发起代付

            Db::startTrans();
            try {
                $res =   $outService->notify($data['code'], $data);
            }catch (\Exception $e) {
                Db::rollback();
                Log::write('代付回调请求失败：error' . $e->getMessage() .', data:' . json_encode($data), 'error');
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
                Log::error('代付回调请求失败 error:  data:' . json_encode($data) . ', msg:' . $res['msg']);
            }

            $output->writeln('Order Out Delay order_id: ' . $item->source . ' code: ' . $data['code'] . ' msg: ' . $res['msg']);
            // 更新订单状态
            $item->status = 1;
            $item->save();
        }

        $output->writeln('Order Out Delay end');
    }
}