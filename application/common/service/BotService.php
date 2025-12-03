<?php

namespace app\common\service;

use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\RobotBind;
use think\Exception;

class BotService
{

    // bind bot to merchant
    public function bindBotToMerchant($bot_id, $merchant_id)
    {
        // 先查询是否已经绑定
        $existingBind = RobotBind::where('robot_id', $bot_id)
            ->find();

        if ($existingBind) {
            // 已经绑定，更新记录
            $existingBind->member_id = $merchant_id;
            $existingBind->save();
            return true;
        } else {
            // 未绑定，创建新记录
            $newBind = new RobotBind();
            $newBind->robot_id = $bot_id;
            $newBind->member_id = $merchant_id;
            $newBind->create_time = date('Y-m-d H:i:s');
            $newBind->save();
            return true;
        }
    }

    // 解绑
    public function unbindBotFromMerchant($bot_id)
    {
        $bind = RobotBind::where('robot_id', $bot_id)
            ->find();

        if ($bind) {
            $bind->delete();
            return true;
        }

        return false;
    }

    // 通过bot_id获取商户ID
    public function getMerchantIdByBotId($bot_id)
    {
        $bind = RobotBind::where('robot_id', $bot_id)
            ->find();
        if ($bind) {
            return $bind->member_id;
        }
        return null;
    }

    // 查询代收信息
    public function getInOrder($bot_id, $merchant_order_no)
    {
        $member_id = $this->getMerchantIdByBotId($bot_id);
        if (!$member_id) {
            throw new Exception('请先绑定机器人');
        }

        $orderService = new OrderInService();
        $order = $orderService->queryOrder([
            'merchant_order_no' => $merchant_order_no,
            'merchant_id' => $member_id
        ], false, true);

        switch ($order['status']) {
            case OrderIn::STATUS_PAID:
                $order['status'] = "已支付";
                break;
            case OrderIn::STATUS_UNPAID:
                $order['status'] = "未支付";
                break;
            case OrderIn::STATUS_FAILED:
                $order['status'] = "失败";
                break;
            default:
                $order['status'] = "未知";
                break;
        }

        return $order;
    }

    // 查询代付信息
    public function getOutOrder($bot_id, $merchant_order_no)
    {
        $member_id = $this->getMerchantIdByBotId($bot_id);
        if (!$member_id) {
            throw new Exception('请先绑定机器人');
        }

        $orderService = new OrderOutService();
        $order = $orderService->queryOrder([
            'merchant_order_no' => $merchant_order_no,
            'merchant_id' => $member_id
        ], false);

        switch ($order['status']) {
            case OrderOut::STATUS_PAID:
                $order['status'] = "已支付";
                break;
            case OrderOut::STATUS_UNPAID:
                $order['status'] = "未支付";
                break;
            case OrderOut::STATUS_FAILED:
                $order['status'] = "失败";
                break;
            default:
                $order['status'] = "未知";
                break;
        }

        return $order;
    }

    // 凭证
    public function getVoucherUrl($bot_id, $merchant_order_no)
    {
        $member_id = $this->getMerchantIdByBotId($bot_id);
        if (!$member_id) {
            throw new Exception('请先绑定机器人');
        }

        $orderService = new OrderOutService();
        $data = $orderService->getVoucherUrl([
            'merchant_order_no' => $merchant_order_no,
            'merchant_id' => $member_id]
        );

        return $data;
    }

    // 余额查询
    public function getBalance($bot_id)
    {
        $member_id = $this->getMerchantIdByBotId($bot_id);
        if (!$member_id) {
            throw new Exception('请先绑定机器人');
        }

        $walletService = new MemberWalletService();
        $balance = $walletService->queryBalance(['merchant_id' => $member_id], false);

        return $balance;
    }
}