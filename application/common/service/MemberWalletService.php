<?php

namespace app\common\service;

use app\admin\model\MemberWalletFreezeModel;
use app\admin\model\MemberWalletModel;
use app\admin\model\MemberWalletLogModel;
use think\Db;
use think\Model;

class MemberWalletService
{
     /**
      * 获取用户钱包信息
      * @param int $id
      * @return MemberWalletModel|MemberWalletModel[]|null
      *
      */
    public function getWalletInfo($id)
    {
        $data = MemberWalletModel::where('member_id', $id)->lock(true)->find();
        return $data;
    }

    /**
     * 手动添加余额
     * @param int $member_id
     * @param float $amount
     * @param string $remark
     * @return bool
     */
    public function addBalance($member_id, $amount, $remark = '手动添加')
    {
        $amount = abs($amount);
        $wallet = $this->getWalletInfo($member_id);
        if (!$wallet) {
            return false;
        }
        $before_balance = $wallet->balance;
        $wallet->balance = Db::raw('balance + ' . $amount);
        $after_balance = $before_balance + $amount;
        $wallet->save();

        $this->addBalanceLog($member_id, $amount, $before_balance, $after_balance, MemberWalletModel::CHANGE_TYPE_ADD, '', $remark);

        return true;
    }

    /**
     * 手动减少余额
     * @param int $member_id
     * @param float $amount
     * @param string $remark
     * @return bool
     */
    public function subBalance($member_id, $amount, $remark = '手动减少')
    {
        $amount = abs($amount);
        $wallet = $this->getWalletInfo($member_id);
        if (!$wallet) {
            return false;
        }

        if ($wallet->balance < $amount) {
            throw new \Exception('余额不足');
        }

        $before_balance = $wallet->balance;
        $wallet->balance = Db::raw('balance - ' . $amount);
        $after_balance = $before_balance - $amount;
        $wallet->save();
        $this->addBalanceLog($member_id, $amount, $before_balance, $after_balance, MemberWalletModel::CHANGE_TYPE_SUB, '',  $remark);

        return true;
    }


    /**
     * 冻结余额
     * @param int $member_id
     * @param float $amount
     * @param string $remark
     * @return bool
     */
    public function freeze($member_id, $amount, $type, $order_no, $remark = '手动冻结')
    {
        $amount = abs($amount);
        $wallet = $this->getWalletInfo($member_id);
        if (!$wallet) {
            return false;
        }

        if ($wallet->balance < $amount) {
            throw new \Exception('余额不足');
        }

        $before_balance = $wallet->balance;
        $wallet->balance = Db::raw('balance - ' . $amount);
        $wallet->blocked_balance = Db::raw('blocked_balance + ' . $amount);
        $after_balance = $before_balance - $amount;
        $wallet->save();
        $this->addBalanceLog($member_id, $amount, $before_balance, $after_balance, $type, $order_no, $remark);

        return true;
    }

    /**
     * 解冻余额
     * @param int $member_id
     * @param float $amount
     * @param string $type
     * @param string $remark
     */

    public function unfreeze($member_id, $amount, $type, $order_no, $remark = '手动解冻')
    {
        $amount = abs($amount);
        $wallet = $this->getWalletInfo($member_id);
        if (!$wallet) {
            return false;
        }

        if ($wallet->blocked_balance < $amount) {
            throw new \Exception('冻结余额不足');
        }

        $before_balance = $wallet->balance;
        $wallet->blocked_balance = Db::raw('blocked_balance - ' . $amount);
        $wallet->balance = Db::raw('balance + ' . $amount);
        $after_balance = $before_balance + $amount;
        $wallet->save();
        $this->addBalanceLog($member_id, $amount, $before_balance, $after_balance, $type, $order_no, $remark);

        return true;
    }

    /**
     * 添加余额变动记录
     * @param $member_id
     * @param $amount
     * @param $type
     * @param $before_balance // 变动前余额
     * @param $after_balance // 变动后余额
     * @param $remark
     * @return void
     */
    public function addBalanceLog($member_id, $amount, $before_balance, $after_balance, $type, $order_no, $remark = '')
    {
        $data = [
            'member_id' => $member_id,
            'amount' => $amount,
            'before_balance' => $before_balance,
            'after_balance' => $after_balance,
            'type' => $type,
            'remark' => $remark,
            'order_no' => $order_no,
        ];
        MemberWalletLogModel::create($data);
    }


    /**
     * 提现减少余额
     * @param int $member_id
     * @param float $amount
     * @param string $order_no
     * @param string $remark
     */
    public function withdraw($member_id, $amount, $order_no, $remark = '提现')
    {
        $amount = abs($amount);
        $wallet = $this->getWalletInfo($member_id);
        if (!$wallet) {
            return false;
        }

        if ($wallet->balance < $amount) {
            throw new \Exception('余额不足');
        }

        $before_balance = $wallet->balance;
        $wallet->balance = Db::raw('balance - ' . $amount);
        $after_balance = $before_balance - $amount;
        $wallet->save();
        $this->addBalanceLog($member_id, $amount, $before_balance, $after_balance, MemberWalletModel::CHANGE_TYPE_WITHDRAW_SUB, $order_no, $remark);

        return true;
    }


}