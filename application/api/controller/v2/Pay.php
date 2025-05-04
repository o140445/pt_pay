<?php

namespace app\api\controller\v2;

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
            // 获取支付信息
            if ($res['status'] == 1) {
                $order->channel();
                $pay_info = $orderService->getPayInfo($order);
                $res['pix_code'] = $pay_info['pix_code'] ?? '';
            }

        }catch (\Exception $e) {
            Log::write('代收请求失败：error 2 ' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }


        $this->success('返回成功', $res);
    }

}