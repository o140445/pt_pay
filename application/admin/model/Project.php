<?php

namespace app\admin\model;

use think\Model;

class Project extends Model
{
    // 表名
    protected $name = 'project';


    // 定义时间戳字段名
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    protected static function init()
    {
        self::beforeInsert(function ($row) {
            $row->create_time = date('Y-m-d H:i:s');
            $row->update_time = date('Y-m-d H:i:s');
        });
    }


    //projectChannel
    public function projectChannel()
    {
        return $this->hasMany('ProjectChannel', 'project_id', 'id');
    }

    // configArea
    public function configArea()
    {
        return $this->hasOne('ConfigArea', 'id', 'area_id');
    }

}