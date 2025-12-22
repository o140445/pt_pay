<?php

namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\service\HookService;
use app\common\service\OrderInService;
use app\common\service\OrderOutService;
use think\Db;
use think\Log;

class Hook extends Api
{
    protected $noNeedLogin = '*';
    public function _initialize()
    {
        parent::_initialize();

        //修改日志路径
        Log::init([
            'type'  => 'File',
            'path'  => LOG_PATH . 'pay/',
            'level' => ['error', 'info'],
        ]);
    }
    public function index($code)
    {
        $params = $this->request->param();
        $hared = $this->request->header();
        $params['header'] = $hared;
        //写入日志
        Log::info('Hook: ' . $code . ' ' . json_encode($params));
        Db::startTrans();
        try {
            $hookService = new HookService();
            $res = $hookService->notify($code, $params);
        }catch (\Exception $e) {
            Db::rollback();
            Log::error('Hook error: ' . $code . ' ' . $e->getMessage());
            $this->error($e->getMessage());
        }
        Db::commit();

        // 普通通知
        if (!isset($res['type'])) {
            $this->success($res['msg']);
        }

        // 成功
        if ($res['order_id']) {
            Db::startTrans();
            try {
                if ($res['type'] == HookService::NOTIFY_TYPE_IN) {
                    $orderService = new OrderInService();
                } else {
                    $orderService = new OrderOutService();
                }
                $orderService->notifyDownstream($res['order_id']);
            }catch (\Exception $e) {
                Db::rollback();
                Log::write('代付通知下游失败：error' . $e->getMessage() .', order_id:' . $res['order_id'], 'error');
                $this->error($e->getMessage());
            }
            Db::commit();
        }else{
            Log::error('Hook error: ' . $code . ' ' . $res['msg']);
        }

        $this->success($res['msg']);
    }

    // set hook
    public function set($code)
    {
        $type = $this->request->param('type');
        $hookService = new HookService();
        $res = $hookService->setHook($code, $type);
        $this->success($res['msg']);
       
    }
}