<?php

namespace app\common\model\merchant;

use think\Model;

class OrderOutDelay extends Model
{
    protected $name = 'order_out_delay';

    protected static function init()
    {
        self::beforeInsert(function ($row) {
            $row->create_time = date('Y-m-d H:i:s');
            $row->update_time = date('Y-m-d H:i:s');
        });

        self::beforeUpdate(function ($row) {
            $row->update_time = date('Y-m-d H:i:s');
        });


    }

    const ORDER_TYPE_IN = 1;
    const ORDER_TYPE_OUT = 2;
}