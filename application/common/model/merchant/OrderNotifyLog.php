<?php

namespace app\common\model\merchant;

use think\Model;

class OrderNotifyLog extends Model
{
    protected $name = 'notify_log';


    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

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

    // STATUS_NOTIFY_SUCCESS
    const STATUS_NOTIFY_SUCCESS = 1;
    // STATUS_NOTIFY_FAIL
    const STATUS_NOTIFY_FAIL = 2;
    const STATUS_NOTIFY_WAIT = 0;

    public function getNotifyStatusAttr($value = '')
    {
        $status = [
            self::STATUS_NOTIFY_SUCCESS => '通知成功',
            self::STATUS_NOTIFY_FAIL => '通知失败',
        ];

        if ($value === '') {
            return $status;
        }

        return $status[$value];
    }
}