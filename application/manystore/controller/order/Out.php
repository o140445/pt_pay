<?php

namespace app\manystore\controller\order;


use app\common\controller\ManystoreBase;
use app\common\model\merchant\MemberProjectChannel;
use app\common\model\merchant\OrderOut;
use app\common\model\merchant\Project;
use app\common\service\OrderOutService;
use think\Config;
use think\Db;
use think\Exception;

/**
 * 代付单
 *
 * @icon fa fa-circle-o
 */
class Out extends ManystoreBase
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
            ->where('member_id', STORE_ID)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }


    public function add($ids = null)
    {
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            if ($params) {
                if (empty($params['amount'])) {
                    $this->error('金额不能为空');
                }
                foreach ($params['extra'] as $key => $value) {
                    if (empty($value)) {
                        $this->error('不能为空');
                    }
                }

                $result = false;
                Db::startTrans();
                try {
                    $orderService = new OrderOutService();
                    $result = $orderService->memberCreateOrder(STORE_ID, $params);
                    Db::commit();
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }

                if ($result !== false) {
                    $this->success();
                } else {
                    $this->error(__('No rows were inserted'));
                }
            }
            $this->error(__('Parameter %s can not be empty', ''));
        }

        // 获取通道扩展字段
        $data = MemberProjectChannel::where('member_id', STORE_ID)
            ->with(['member', 'member.area'])
            ->where('status', MemberProjectChannel::STATUS_ON)
            ->where('type', MemberProjectChannel::TYPE_OUT)
            ->select();

        if (empty($data)) {
            $this->error('请先添加代付通道');
        }

        $config = Config::get('out_config');
        $area = $data[0]->member->area->name;

        $this->view->assign('area_config', $config[$area]);
        $this->view->assign('project_id', $data[0]->project_id);

        return $this->view->fetch();
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
     * 完成订单
     * @param $ids
     */
    public function complete($ids = null)
    {
        // 判断是否是post请求
        if (false === $this->request->isPost()) {
            $this->error('非法请求');
        }

        $orderService = new OrderOutService();
        Db::startTrans();
        try {

            $order = $this->model->whereIn('id', $ids)->find();
            if (!$order) {
                throw new \Exception('订单不存在');
            }

            if ($order->status != OrderOut::STATUS_UNPAID) {
                throw new \Exception('订单状态不正确');
            }

            // 设置时区
            date_default_timezone_set($order->area->timezone);
            $orderService->completeOrder($order, ['error_msg' => '手动完成']);

        }catch (\Exception $e) {
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
     * 失败
     */
    public function fail($ids = null)
    {
        // 判断是否是post请求
        if (false === $this->request->isPost()) {
             $this->error('非法请求');
        }

        $orderService = new OrderOutService();
        Db::startTrans();
        try {

            $order = $this->model->whereIn('id', $ids)->find();
            if (!$order) {
                throw new \Exception('订单不存在');
            }

            if ($order->status != OrderOut::STATUS_UNPAID) {
                throw new \Exception('订单状态不正确');
            }

            // 设置时区
            date_default_timezone_set($order->area->timezone);
            $orderService->failOrder($order, ['error_msg' => '手动失败']);

        }catch (\Exception $e) {
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
     * 退款
     */
    public function refund($ids = null)
    {
        // 判断是否是post请求
        if (false === $this->request->isPost()) {
             $this->error('非法请求');
        }

        $orderService = new OrderOutService();
        Db::startTrans();
        try {

            $order = $this->model->whereIn('id', $ids)->find();
            if (!$order) {
                throw new \Exception('订单不存在');
            }

            if ($order->status != OrderOut::STATUS_PAID) {
                throw new \Exception('订单状态不正确');
            }
            // 设置时区
            date_default_timezone_set($order->area->timezone);
            $orderService->refundOrder($order, ['error_msg' => '手动退款']);

        }catch (\Exception $e) {
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
