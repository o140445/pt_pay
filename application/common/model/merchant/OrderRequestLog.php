<?php

namespace app\common\model\merchant;

use think\Model;

class OrderRequestLog  extends Model
{
    protected $name = 'order_request_log';

    protected static function init()
    {
        self::beforeInsert(function ($row) {
            $row->create_time = date('Y-m-d H:i:s');
        });

    }

    const ORDER_TYPE_IN = 1;
    const ORDER_TYPE_OUT = 2;

    const REQUEST_TYPE_REQUEST = 1; // 请求
    const REQUEST_TYPE_CALLBACK = 2; // 回调
    const REQUEST_TYPE_VOUCHER = 3; // 凭证
}