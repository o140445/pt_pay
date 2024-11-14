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
            $this->error('请求方式错误');
        }

        $params = $this->request->post();
        if (empty($params['amount']) ||
            empty($params['merchant_id']) ||
            empty($params['product_id']) ||
            empty($params['merchant_order_no']) ||
            empty($params['sign']) ||
            empty($params['notify_url']) ||
            empty($params['nonce'])) {
            $this->error('参数错误');
        }

        // 写请求日志
        Log::write('代收请求参数：data' . json_encode($params), 'info');

        // 加锁同一个单号同时只能有一个请求
        $lock = $params['merchant_order_no'] . '_lock';
        Cache::get($lock) && $this->error('请勿重复提交');

        Cache::set($lock, 1, 10);

        Db::startTrans();
        try {
            $orderService = new OrderInService();
            $order = $orderService->createOrder($params);
        }catch (\Exception $e) {
            Db::rollback();
            Cache::rm($lock);

            Log::write('代收请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }

        Db::commit();
        Cache::rm($lock);

        try {
            $res = $orderService->requestChannel($order);
        }catch (\Exception $e) {
            Log::write('代收请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
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
    public function innotify($sign)
    {
        $params = $this->request->post();
        // 写请求日志
        Log::write('代收请求参数：data' . json_encode($params), 'info');

        Db::startTrans();
        try {
            $orderService = new OrderInService();
            $res = $orderService->notify($sign, $params);
        }catch (\Exception $e) {
            Db::rollback();
            Log::write('代收请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
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
                Log::write('代付通知下游失败：error' . $e->getMessage() .', order_id:' . $res['order_id'], 'error');
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
            $this->error('请求方式错误');
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
            $this->error('参数错误');
        }

        // 写请求日志
        Log::write('代付请求参数：data' . json_encode($params), 'info');

        // 加锁同一个单号同时只能有一个请求
        $lock = $params['merchant_order_no'] . '_lock';
        Cache::get($lock) && $this->error('请勿重复提交');

        Cache::set($lock, 1, 10);

        Db::startTrans();
        try {
            $orderService = new OrderOutService();
            $order = $orderService->createOutOrder($params);
        }catch (\Exception $e) {
            Db::rollback();
            Cache::rm($lock);
            Log::write('代付请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }
        Db::commit();
        Cache::rm($lock);

        try {
            $res = $orderService->requestChannel($order);
        }catch (\Exception $e) {
            Log::write('代付请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }

        $this->success('返回成功', $res);
    }

    /**
     * 代付回调
     * @ApiMethod (POST)
     * @ApiRoute    (api/v1/pay/outnotify/{sign})
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     */
    public function outnotify($sign)
    {
        $params = $this->request->post();
        // 写请求日志
        Log::write('代付请求参数：data' . json_encode($params), 'info');

        Db::startTrans();
        try {
            $orderService = new OrderOutService();
            $res = $orderService->notify($sign, $params);
        }catch (\Exception $e) {
            Db::rollback();
            Log::write('代付请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
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
                Log::write('代付通知下游失败：error' . $e->getMessage() .', order_id:' . $res['order_id'], 'error');
                $this->error($e->getMessage());
            }
            Db::commit();
        }

        echo $res['msg']; die();
    }
}