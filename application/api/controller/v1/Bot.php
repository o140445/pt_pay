<?php

namespace app\api\controller\v1;

use app\common\controller\Api;
use app\common\model\merchant\OrderIn;
use app\common\model\merchant\OrderOut;
use app\common\service\MemberWalletService;
use app\common\service\OrderInService;
use app\common\service\OrderOutService;
use app\common\service\StatService;
use think\Config;
use think\Request;

class Bot extends Api
{
    protected $noNeedLogin = ['*'];


    public function __construct(Request $request = null)
    {
        parent::__construct($request);

        // 检查token
        $token = $this->request->get('api_token');
        if (empty($token)) {
            $this->error(__('Token is empty'));
        }

        // 检查token是否有效
        $sysToken =  Config::get('api.token');
        if ($token != $sysToken) {
            $this->error(__('Token is invalid'));
        }

    }

    /**
     * 查询代收单
     */
    public function query_in()
    {
        $merchant_order_no = $this->request->get('merchant_order_no');
        $merchant_id = $this->request->get('merchant_id');
        if (empty($merchant_order_no) || empty($merchant_id)) {
            $this->error(__('Parameter error'));
        }

        try {
            $orderService = new OrderInService();
            $order = $orderService->queryOrder(['merchant_order_no' => $merchant_order_no, 'merchant_id' => $merchant_id], false, true);
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

        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('ok',$order);

    }

    /**
     * 查询代付单
     */
    public function query_out()
    {
        $merchant_order_no = $this->request->get('merchant_order_no');
        $merchant_id = $this->request->get('merchant_id');
        if (empty($merchant_order_no) || empty($merchant_id)) {
            $this->error(__('Parameter error'));
        }

        try {
            $orderService = new OrderOutService();
            $order = $orderService->queryOrder(['merchant_order_no' => $merchant_order_no, 'merchant_id' => $merchant_id], false);
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
                case OrderOut::STATUS_REFUND:
                    $order['status'] = "退款";
                    break;
                default:
                    $order['status'] = "未知";
                    break;
            }
        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('ok',$order);
    }

    /**
     * 余额查询
     */
    public function balance()
    {
        $merchant_id = $this->request->get('merchant_id');
        if (empty($merchant_id)) {
            $this->error(__('Parameter error'));
        }

        try {
            $walletService = new MemberWalletService();
            $balance = $walletService->queryBalance(['merchant_id' => $merchant_id], false);
        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('ok', $balance);
    }

    /**
     * 凭证获取
     */
    public function voucher()
    {
        $merchant_order_no = $this->request->get('merchant_order_no');
        $merchant_id = $this->request->get('merchant_id');
        if (empty($merchant_order_no) || empty($merchant_id)) {
            $this->error(__('Parameter error'));
        }

        try {
            $orderService = new OrderOutService();
            $data = $orderService->getVoucherUrl(['merchant_order_no' => $merchant_order_no, 'merchant_id' => $merchant_id]);

//
//            $data['url'] =  Config::get('pay_url').'/index/receipt/index?order_id='.$order['order_no'];

        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('ok', $data);
    }

    /**
     * 获取当天详情
     */
    public function day()
    {
        $merchant_id = $this->request->get('merchant_id');
        if (empty($merchant_id)) {
            $this->error(__('Parameter error'));
        }

        try {
            $service = new StatService();
            $data = $service->getDayDetail($merchant_id, date('Y-m-d'));
        }catch (\Exception $e) {
            $this->error($e->getMessage());
        }

        $this->success('ok', $data);
    }
}