<?php

namespace app\common\service\validators;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;

class OrderDataValidator implements ValidatorInterface
{
    protected $errorMessage;

    public function validate(array $data): bool {
        if (!isset($data['merchant_order_no'])) {
            $this->errorMessage = "Merchant Order No Not Found";
            return false;
        }

        if (!isset($data['amount']) || $data['amount'] <= 0) {
            $this->errorMessage = "Amount Error";
            return false;
        }

        // 沙盒模式跳过
        if(isset($data['is_sandbox'])) {
            return true;
        }

        // 根据type判断订单类型检查单号是否重复
        if ($data['type'] == "IN") {
            $order = OrderIn::where('member_order_no', $data['merchant_order_no'])->find();
        } else {
            $order = OrderOut::where('member_order_no', $data['merchant_order_no'])->find();
        }

        if ($order) {
            $this->errorMessage = "Merchant Order No Exists";
            return false;
        }


        return true;
    }

    public function getErrorMessage(): string {
        return $this->errorMessage;
    }
}