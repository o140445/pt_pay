<?php

namespace app\common\model\merchant;

use think\Model;
class RobotBind extends Model
{
    // 表名
    protected $name = 'robot_bind';


    // 定义时间戳字段名
    protected $createTime = 'create_time';

    protected static function init()
    {
        self::beforeInser(function ($row) {
            $row->create_time = date('Y-m-d H:i:s');
        });
    }
}