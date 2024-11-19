<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\service\OrderOutService;

class Receipt extends Frontend
{
    protected $noNeedLogin = ['*'];

    public function index()
    {
        $order_id = $this->request->param('order_id');
        if (!$order_id) {
            $this->redirect('/404.html');
        }

        try {
            $orderService = new OrderOutService();
            $order = $orderService->getVoucherData($order_id);
            $this->view->assign('data', $order);
        }catch (\Exception $e) {
            var_dump($e->getMessage());die();
        }

        return $this->view->fetch();
    }
}