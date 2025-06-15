<?php

namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\service\OrderInService;
use app\common\service\OrderOutService;
use think\Cache;
use think\Db;
use think\Log;

class Pay extends Api
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

    /**
     * 代收接口
     *
     * @ApiMethod (POST)
     * @ApiRoute    (api/v1/pay/in)
     * @ApiParams (name="amount", type="string", required=true, description="支付金额")
     * @ApiParams (name="merchant_id", type="int", required=true, description="商户ID")
     * @ApiParams (name="product_id", type="int", required=true, description="产品ID")
     * @ApiParams (name="merchant_order_no", type="string", required=true, description="商户订单号")
     * @ApiParams (name="sign", type="string", required=true, description="签名")
     * @ApiParams (name="notify_url", type="string", required=true, description="回调地址")
     * @ApiParams (name="nonce", type="string", required=true, description="随机字符串")
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     * @ApiReturnParams   (name="msg", type="string", required=true, sample="返回成功")
     * @ApiReturnParams   (name="data", type="object", sample="{'order_id':'int','pay_url':'string', 'status':'int'}", description="订单ID和支付链接")
     * @ApiReturn   ({
     *     'code':'1',
     *     'msg':'返回成功'
     *     'data':{
     *     'order_id':'int',
     *     'pay_url':'string',
     *     'status':'int'
     *     }
     *     })
     */
    public function in()
    {
        if (!$this->request->isPost()) {
            $this->error('Request method error');
        }

        $params = $this->request->post();
        if (empty($params['amount']) ||
            empty($params['merchant_id']) ||
            empty($params['product_id']) ||
            empty($params['merchant_order_no']) ||
            empty($params['sign']) ||
            empty($params['notify_url']) ||
            empty($params['nonce'])) {
            $this->error('Parameter error');
        }

        // 写请求日志
        Log::write('代收请求参数：data ' . json_encode($params), 'info');

        // 加锁同一个单号同时只能有一个请求
        $lock = $params['merchant_order_no'] . '_lock';
        Cache::get($lock) && $this->error('Submit repeatedly');

        Cache::set($lock, 1, 10);

        Db::startTrans();
        try {
            $orderService = new OrderInService();
            $order = $orderService->createOrder($params);
            Db::commit();
        }catch (\Exception $e) {
            Db::rollback();
            Cache::rm($lock);

            Log::write('代收请求失败：error 1 ' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }

        try {
            $res = $orderService->requestChannel($order);
        }catch (\Exception $e) {
            Log::write('代收请求失败：error 2 ' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }


        $this->success('返回成功', $res);
    }

    /**
     * 代收回调
     * @ApiMethod (POST)
     * @ApiRoute    (api/v1/pay/innotify/{sign})
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     */
    public function innotify($code)
    {
        $params = $this->request->post();
        // 写请求日志
        Log::write('代收回调请求参数：data' . json_encode($params), 'info');

        Db::startTrans();
        try {
            $orderService = new OrderInService();
            $res = $orderService->notify($code, $params);
        }catch (\Exception $e) {
            Db::rollback();
            Log::write('代收回调失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }
        Db::commit();

        // 成功
        if ($res['order_id']) {
            Db::startTrans();
            try {
                $orderService->notifyDownstream($res['order_id']);
            }catch (\Exception $e) {
                Db::rollback();
                Log::write('代收回调通知下游失败：error' . $e->getMessage() .', order_id:' . $res['order_id'], 'error');
                $this->error($e->getMessage());
            }
            Db::commit();
        }

        echo $res['msg']; die();
    }

    /**
     * 代付
     */
    public function out()
    {

        if (!$this->request->isPost()) {
            $this->error('Request method error');
        }

        $params = $this->request->post();
        if (empty($params['amount']) ||
            empty($params['merchant_id']) ||
            empty($params['product_id']) ||
            empty($params['merchant_order_no']) ||
            empty($params['sign']) ||
            empty($params['notify_url']) ||
            empty($params['nonce']) ||
            empty($params['extra'])) {
            $this->error('Parameter error');
        }

        // 写请求日志
        Log::write('代付请求参数：data' . json_encode($params), 'info');

        // 加锁同一个单号同时只能有一个请求
        $lock = $params['merchant_order_no'] . '_lock';
        Cache::get($lock) && $this->error('Submit repeatedly');

        Cache::set($lock, 1, 10);

        Db::startTrans();
        try {
            $orderService = new OrderOutService();
            $order = $orderService->createOutOrder($params);
            Db::commit();
        }catch (\Exception $e) {
            Db::rollback();
            Cache::rm($lock);
            Log::write('代付请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }

        $res  = [
            'order_id' => $order->id,
            'status' => OrderOut::STATUS_UNPAID,
            'msg' => 'ok',
        ];

        $this->success('返回成功', $res);
    }

    /**
     * 代付回调
     * @ApiMethod (POST)
     * @ApiRoute    (api/v1/pay/outnotify/{sign})
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     */
    public function outnotify($code)
    {
        $params = $this->request->post();
        // 写请求日志
        Log::write('代付回调请求参数：data' . json_encode($params), 'info');

        Db::startTrans();
        try {
            $orderService = new OrderOutService();
            $res = $orderService->notify($code, $params);
        }catch (\Exception $e) {
            Db::rollback();
            Log::write('代付回调请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }
        Db::commit();

        // 成功
        if ($res['order_id']) {
            Db::startTrans();
            try {
                $orderService->notifyDownstream($res['order_id']);
            }catch (\Exception $e) {
                Db::rollback();
                Log::write('代付回调通知下游失败：error' . $e->getMessage() .', order_id:' . $res['order_id'], 'error');
                $this->error($e->getMessage());
            }
            Db::commit();
        }else{
            Log::error('代付回调请求失败 error:  data:' . json_encode($params) . ', msg:' . $res['msg']);
        }

        echo $res['msg']; die();
    }
}