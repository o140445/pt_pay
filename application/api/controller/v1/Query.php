<?php

namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\service\MemberWalletService;
use app\common\service\OrderInService;
use app\common\service\OrderOutService;

class Query extends Api
{
    protected $noNeedLogin = '*';

    /**
     * 查询代收单
     *
     */
    public function in()
    {
        // 检查请求类型
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $params = $this->request->post();
        if (empty($params['merchant_order_no']) || empty($params['sign']) || empty($params['merchant_id'])) {
            $this->error('参数错误');
        }
        try {
            $orderInService = new OrderInService();
            $order = $orderInService->queryOrder($params);
            if (empty($order)) {
                $this->error('订单不存在');
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }


        $this->success('查询成功', $order);
    }

    /**
     * 查询代付单
     *
     */
    public function out()
    {
        // 检查请求类型
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $params = $this->request->post();
        if (empty($params['merchant_order_no']) || empty($params['sign']) ||  empty($params['merchant_id'])) {
            $this->error('参数错误');
        }
        try {
            $orderOutService = new OrderOutService();
            $order = $orderOutService->queryOrder($params);
            if (empty($order)) {
                $this->error('订单不存在');
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }


        $this->success('查询成功', $order);
    }

    /**
     * 查询余额
     */
    public function balance()
    {
        // 检查请求类型
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $params = $this->request->post();
        if (empty($params['merchant_id']) || empty($params['sign'])) {
            $this->error('参数错误');
        }
        try {
            $walletService = new MemberWalletService();
            $wallet = $walletService->queryBalance($params);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('查询成功', $wallet);
    }



    /**
     * 查询代收单信息
     */
    public function inpayinfo()
    {
        // 检查请求类型
        if (!$this->request->isPost()) {
            $this->error('请求方式错误');
        }

        $params = $this->request->post();
        if (empty($params['merchant_id']) || empty($params['sign']) ||  empty($params['merchant_order_no'])) {
            $this->error('参数错误');
        }
        try {
            $orderService = new OrderInService();
            $res = $orderService->getOrderInfo($params);
        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('查询成功', $res);
    }
}