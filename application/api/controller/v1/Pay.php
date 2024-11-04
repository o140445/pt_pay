<?php

namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\service\OrderService;
use think\Db;
use think\Log;

class Pay extends Api
{
    protected $noNeedLogin = '*';

    public function _initialize()
    {
        parent::_initialize();
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
        $params = $this->request->post();
        if (empty($params['amount']) || empty($params['merchant_id']) || empty($params['product_id']) || empty($params['merchant_order_no']) || empty($params['sign']) || empty($params['notify_url']) || empty($params['nonce'])) {
            $this->error('参数错误');
        }

        // 写请求日志
        Log::write('代收请求参数：data' . json_encode($params), 'info');

        Db::startTrans();
        try {
            $orderService = new OrderService();
            $res = $orderService->createOrder($params);
        }catch (\Exception $e) {
            Db::rollback();
            Log::write('代收请求失败：error' . $e->getMessage() .', data:' . json_encode($params), 'error');
            $this->error($e->getMessage());
        }
        Db::commit();

        // 失败
        if ($res['status'] == OrderService::CHANNEL_RES_STATUS_FAILED) {
            Log::write('代收请求失败：error' . $res['msg'] .', data:' . json_encode($params), 'error');
            $this->error($res['msg']);
        }

        $this->success('返回成功', $res);
    }

    /**
     * 代收回调
     * @ApiMethod (POST)
     * @ApiRoute    (api/v1/pay/notify/{sign})
     * @ApiReturnParams   (name="code", type="integer", required=true, sample="0")
     */
    public function notify($sign)
    {
        $params = $this->request->post();
        // 写请求日志
        Log::write('代收请求参数：data' . json_encode($params), 'info');

        Db::startTrans();
        try {
            $orderService = new OrderService();
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

        echo $res['msg'];
    }
}