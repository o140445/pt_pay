<?php

namespace app\common\service\validators;

use app\common\model\merchant\Member;
use app\common\service\OrderInService;
use app\common\service\OrderOutService;

class MemberValidator implements ValidatorInterface
{
    protected $errorMessage;

    public function validate(array $data): bool {
        if (!isset($data['merchant_id'])) {
            $this->errorMessage = "Merchant ID Not Found";
            return false;
        }

        $member = Member::where('status', OrderInService::STATUS_OPEN)->find($data['merchant_id']);
        if (!$member) {
            $this->errorMessage = "Merchant Not Found";
            return false;
        }

        //对接类型检查
        if ($member->docking_type != Member::DOCKING_TYPE_API && !isset($data['is_member'])) {
            $this->errorMessage = "Merchant Not Support API";
            return false;
        }

        if ($member->docking_type == Member::DOCKING_TYPE_API && isset($data['is_member'])) {
            $this->errorMessage = "Merchant Not Support Member";
            return false;
        }

        // ip白名单检查
        if ($member->ip_white_list) {
            $ip = request()->ip();
            if (!in_array($ip, explode(',', $member->ip_white_list))) {
                $this->errorMessage = "IP Not In White List";
                return false;
            }
        }


        // 代理不支持创建
        if ($member->is_agency) {
            $this->errorMessage = "Merchant Not Support Create";
            return false;
        }


        // 沙盒模式检查
        if ( isset($data['is_sandbox'])) {
            if (!$member->is_sandbox ) {
                $this->errorMessage = "Merchant Not Support Sandbox";
                return false;
            }
            return true;
        }

        // 正常模式检查
        if ($member->is_sandbox) {
            $this->errorMessage = "Merchant Not Support Normal";
            return false;
        }

        // 余额检查 out
        if ($data['type'] == OrderOutService::TYPE_OUT) {
            $memberWallet = $member->wallet;
            if ($memberWallet->balance < $data['amount']) {
                $this->errorMessage = "Balance Not Enough";
                return false;
            }
        }


        return true;
    }

    public function getErrorMessage(): string {
        return $this->errorMessage;
    }
}