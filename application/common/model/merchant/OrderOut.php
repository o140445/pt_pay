<?php

namespace app\common\model\merchant;

use think\Model;


class OrderOut extends Model
{

    // 表名
    protected $name = 'order_out';


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

    // 追加属性
    protected $append = [
        'status_text'
    ];


    // 状态 1未支付 2已支付 3失败 4退款 5支付中
    const STATUS_UNPAID = 1;
    const STATUS_PAID = 2;
    const STATUS_FAILED = 3;
    const STATUS_REFUND = 4;
    const STATUS_PAYING = 5;

    
    public function getStatusList()
    {
        return [
            self::STATUS_UNPAID => __('Status unpaid'),
            self::STATUS_PAID => __('Status paid'),
            self::STATUS_FAILED => __('Status failed'),
            self::STATUS_REFUND => __('Status refund')
        ];
    }


    public function getStatusTextAttr($value, $data)
    {
        $value = $value ? $value : (isset($data['status']) ? $data['status'] : '');
        $list = $this->getStatusList();
        return isset($list[$value]) ? $list[$value] : '';
    }



    // area
    public function area()
    {
        return $this->hasOne('ConfigArea', 'id', 'area_id');
    }

    // channel
    public function channel()
    {
        return $this->hasOne('Channel', 'id', 'channel_id');
    }


}
