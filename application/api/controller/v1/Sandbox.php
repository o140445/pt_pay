<?php

namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\service\OrderSandboxService;

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
            empty($params['notify_url'])) {
            $this->error('参数错误');
        }

        try {
            $orderService = new OrderSandboxService();
            $result = $orderService->createOrderIn($params);
        }catch (\Exception $e) {
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
            empty($params['extra'])) {
            $this->error('参数错误');
        }

        try {
            $orderService = new OrderSandboxService();
            $result = $orderService->createOrderOut($params);
        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('返回成功', $result);
    }



    public function notify()
    {
        echo "success";
    }
}