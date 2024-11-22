<?php

namespace app\common\service\validators;

use app\common\model\merchant\Member;
use app\common\service\OrderInService;
use app\common\service\SignService;

class SignatureValidator implements ValidatorInterface
{
    protected $errorMessage;

    public function validate(array $data): bool {

        // 会员后台不需要签名验证
        if (isset($data['is_member'])) {
            return true;
        }

        if (!isset($data['sign'])) {
            $this->errorMessage = "Sign Not Found";
            return false;
        }

        $secret = Member::where('status', OrderInService::STATUS_OPEN)->find($data['merchant_id'])->api_key;

        $signService = new SignService();
        $signData = [
            'merchant_id' => $data['merchant_id'],
            'merchant_order_no' => $data['merchant_order_no'],
            'amount' => $data['amount'],
            'product_id' => $data['product_id'],
            'notify_url' => $data['notify_url'],
            'nonce' => $data['nonce'],
            'sign' => $data['sign'],
        ];

        if (isset($data['extra'])) {
            $signData['extra'] = $data['extra'];
        }

        if (!$signService->checkSign($signData, $secret)) {
            $this->errorMessage = "Sign Error";
            return false;
        }
        return true;
    }

    public function getErrorMessage(): string {
        return $this->errorMessage;
    }

}