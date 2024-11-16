<?php

namespace app\index\controller;

use app\common\controller\Frontend;
use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderRequestLog;
use think\Cache;

class Pay extends Frontend
{
    protected $noNeedLogin = ['*'];

    /**
     * 支付页面
     */
    public function index()
    {
        $order_id = $this->request->param('order_id');
        if (!$order_id) {
            $this->redirect('/404.html');
        }

        $order = OrderIn::where('order_no', $order_id)->with(['area'])->find();
        if (!$order) {
//            $this->error('订单不存在');
            $this->redirect('/404.html');
        }

        if ($order['status'] != 1) {
//            $this->error('订单已支付');
            $this->redirect('/404.html');
        }

        // 过期时不显示
        if (strtotime($order['create_time']) + 3600 < time()) {
            $this->redirect('/404.html');
        }


        $key =  'order_in_info_' . $order['order_no'];
        $response = Cache::get($key);
        if (!$response) {
            $response = OrderRequestLog::where('order_no', $order['order_no'])->where('request_type', OrderRequestLog::REQUEST_TYPE_REQUEST)->find();
            $response_data = json_decode($response['response_data'], true);
        }else{
            $response_data = $response;
        }


        // base64 qrcode转图片
        $qrcode = "data:image/png;base64," . $response_data['qrcode'];
        $pix_code = $response_data['copia_e_cola'];

        $data = [
            'order_id' => $order['order_no'],
            'amount' => 'R$ ' . number_format($order['amount'], 2, '.', ''),
            'expire_time' => date('Y-m-d H:i:s', strtotime($order['create_time']) + 3600),
            'qrcode'=> $qrcode,
            'pix_code' => $pix_code,
        ];

        $this->view->assign('data', $data);
        return $this->view->fetch();
    }
}