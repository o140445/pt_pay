<?php

namespace app\common\service;

use app\common\model\merchant\OrderRequestLog;

class OrderRequestService
{
    /**
     * 创建
     */
    public function create($order_no, $request_type, $order_type,  $request_data, $response_data)
    {
        $model = new OrderRequestLog();
        $model->order_no = $order_no;
        $model->request_type = $request_type;
        $model->order_type = $order_type;
        $model->request_data = $request_data;
        $model->response_data = $response_data;
        $model->save();
    }

    public function checkRequest($order_no, $request_type, $order_type)
    {
        $model = OrderRequestLog::where('order_no', $order_no)
            ->where('request_type', $request_type)
            ->where('order_type', $order_type)
            ->find();

        if ($model && $model->response_data) {
            return true;
        }

        return false;
    }
}