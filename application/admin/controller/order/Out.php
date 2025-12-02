<?php

namespace app\admin\controller\order;

use app\admin\model\Admin;
use app\common\controller\Backend;
use app\common\model\merchant\OrderOut;
use app\common\service\OrderOutService;
use think\Db;

/**
 * 代付单
 *
 * @icon fa fa-circle-o
 */
class Out extends Backend
{

    /**
     * Out模型对象
     * @var \app\common\model\merchant\OrderOut
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\merchant\OrderOut;
        $this->view->assign("statusList", $this->model->getStatusList());
    }


    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */

    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if (false === $this->request->isAjax()) {
            return $this->view->fetch();
        }
        //如果发送的来源是 Selectpage，则转发到 Selectpage
        if ($this->request->request('keyField')) {
            return $this->selectpage();
        }
        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $list = $this->model
            ->with(['area'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }


    public function add($ids = null)
    {
        $this->error('非法请求');
    }

    public function edit($ids = null)
    {
        $this->error('非法请求');
    }

    public function del($ids = null)
    {
        $this->error('非法请求');
    }

    public function multi($ids = null)
    {
        $this->error('非法请求');
    }


    public function recyclebin($ids = null)
    {
        $this->error('非法请求');
    }

    public function restore($ids = null)
    {
        $this->error('非法请求');
    }

    public function destroy($ids = null)
    {
        $this->error('非法请求');
    }

    /**
     * edit_status
     * @param $ids
     */
    public function edit_status($ids = null)
    {
        $order = $this->model->whereIn('id', $ids)->find();
        if (!$order) {
            $this->error('订单不存在');
        }
        // 判断是否是post请求
        if (false === $this->request->isPost()) {
            $statusList = $this->model->getStatusList();
            $this->view->assign("statusList", $statusList);
            $this->view->assign("row", $order);
            return $this->view->fetch();
        }

        $params = $this->request->post('row/a');
        $password = $this->request->post('password');
        if (!$password) {
            $this->error('请输入密码');
        }
        $orderService = new OrderOutService();

        Db::startTrans();
        try {

            $admin = Admin::get($this->auth->id);
            if ($admin->password != $this->auth->getEncryptPassword($password, $admin->salt)) {
                throw new \Exception('密码错误');
            }

            //$order->status != OrderOut::STATUS_UNPAID
            if (!in_array($order->status, [OrderOut::STATUS_UNPAID, OrderOut::STATUS_UNPAID])) {
                throw new \Exception('订单状态不正确');
            }

            // 设置时区
            date_default_timezone_set($order->area->timezone);
            switch ($params['status']) {
                case OrderOut::STATUS_PAID:
                    $orderService->completeOrder($order, ['error_msg' => $params['remark']]);
                    break;
                case OrderOut::STATUS_FAILED:
                    $orderService->failOrder($order, ['error_msg' => $params['remark']]);
                    break;
                case OrderOut::STATUS_REFUND:
                    $orderService->refundOrder($order, ['error_msg' => $params['remark']]);
                    break;
                default:
                    throw new \Exception('请选择正确的状态');
            }

        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        Db::commit();

        //  通知下游
        Db::startTrans();
        try {
            $orderService->notifyDownstream($ids);
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        Db::commit();
        $this->success("操作成功");

    }

    /**
     * 通知
     */
    public function notify($ids = null)
    {
        // 判断是否是post请求
        if (false === $this->request->isPost()) {
            $this->error('非法请求');
        }
        //  通知下游
        Db::startTrans();
        try {
            $orderService = new OrderOutService();
            $orderService->notifyDownstream($ids);
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        Db::commit();
        $this->success("操作成功");
    }

}
