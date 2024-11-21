<?php

namespace app\common\service;

use app\common\model\merchant\MemberWalletModel;
use app\common\model\merchant\WithdrawOrder;

class WithdrawService
{
    /**
     * 提现单创建
     * @param $member_id
     * @param $amount
     * @param $usdt_amount
     * @param $usdt_address
     * @param $remark
     *
     * @return bool
     */
    public function create($member_id, $amount, $usdt_amount = '', $usdt_address = '', $rate=0, $remark = '手动提现')
    {
        $walletService = new MemberWalletService();
        $amount = abs($amount);
        $wallet = $walletService->getWalletInfo($member_id);
        if (!$wallet) {
            throw new \Exception('用户不存在');
        }

        if  ($wallet->balance < $amount) {
            throw new \Exception('余额不足');
        }

        // 添加提现记录
        $order_no = $this->withdrawOrderNo('TX'.$member_id);
        $data = [
            'member_id' => $member_id,
            'amount' => $amount,
            'usdt_amount' => $usdt_amount,
            'usdt_address' => $usdt_address,
            'order_no' => $order_no,
            'rate' => $rate,
            'remark' => $remark,
        ];

        $res = WithdrawOrder::create($data);
        if (!$res) {
            throw new \Exception('提现失败');
        }

        // 冻结
        $freezeService = new FreezeService();
        $freezeService->freeze($member_id, $amount, MemberWalletModel::CHANGE_TYPE_WITHDRAW_FREEZE, $order_no,'提现冻结');

        return true;
    }

    /**
     * edit
     * @param $id
     * @param $amount
     * @param $usdt_amount
     * @param $usdt_address
     * @param $status
     * @param $remark
     *
     * @return bool
     */
    public function edit($id, $amount, $status, $usdt_amount = "", $usdt_address = "", $rate=0, $remark = "")
    {
        $order = WithdrawOrder::where('id', $id)->find();
        if (!$order) {
            throw new \Exception('提现单不存在');
        }

        if ($order->status != WithdrawOrder::STATUS_WAIT) {
            throw new \Exception('提现单已经处理，无法编辑');
        }

        // 检查金额 如果金额不一致则解冻原金额 冻结新金额
        if ($order->amount != $amount) {
            $freezeService = new FreezeService();
            $freezeService->unfreeze(MemberWalletModel::CHANGE_TYPE_WITHDRAW_UNFREEZE, '', $order->order_no, '提现解冻');
            $freezeService->freeze($order->member_id, $amount, MemberWalletModel::CHANGE_TYPE_WITHDRAW_FREEZE, $order->order_no, '提现冻结');
        }

        // 状态判断
        if ($status == WithdrawOrder::STATUS_PASS) {
            $freezeService = new FreezeService();
            $freezeService->unfreeze(MemberWalletModel::CHANGE_TYPE_WITHDRAW_UNFREEZE, '', $order->order_no, '提现解冻');
            $walletService = new MemberWalletService();
            $walletService->withdraw($order->member_id, $order->amount, $order->order_no, '提现成功');
        } elseif ($status == WithdrawOrder::STATUS_REFUSE) {
            $freezeService = new FreezeService();
            $freezeService->unfreeze(MemberWalletModel::CHANGE_TYPE_WITHDRAW_UNFREEZE, '', $order->order_no, '提现解冻');
        }

        $order->amount = $amount;
        $order->usdt_amount = $usdt_amount;
        $order->usdt_address = $usdt_address;
        $order->status = $status;
        $order->remark = $remark;
        $order->rate = $rate;
        $res = $order->save();
        if (!$res) {
            throw new \Exception('编辑失败');
        }

        return true;
    }



    /**
     * 提现单号
     * @param $prefix
     *
     * @return string
     */
    public function withdrawOrderNo($prefix)
    {
        return get_order_no($prefix);
    }
}