<?php

namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\service\OrderSandboxService;
use think\Log;

class Sandbox extends Api
{
    protected $noNeedLogin = '*';

    public function _initialize()
    {
        parent::_initialize();
    }
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

        Log::write('沙盒 请求参数：' . json_encode($params), 'info');
        try {
            $orderService = new OrderSandboxService();
            $result = $orderService->createOrderIn($params);
        }catch (\Exception $e) {
            Log::write('沙盒 请求异常：' . $e->getMessage(), 'error');
            $this->error($e->getMessage());
        }

        $this->success('返回成功', $result);
    }

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

        Log::write('沙盒 代付请求参数：' . json_encode($params), 'info');
        try {
            $orderService = new OrderSandboxService();
            $result = $orderService->createOrderOut($params);
        }catch (\Exception $e) {
            Log::write('沙盒 代付请求异常：' . $e->getMessage(), 'error');
            $this->error($e->getMessage());
        }

        $this->success('返回成功', $result);
    }



    public function notify()
    {
        echo "success";
    }
}