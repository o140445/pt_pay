<?php

namespace app\manystore\controller\withdraw;

use app\common\controller\ManystoreBase;
use app\common\model\merchant\Member;
use app\common\service\WithdrawService;
use think\Db;

/**
 * 提款单
 *
 * @icon fa fa-circle-o
 */
class Order extends ManystoreBase
{

    /**
     * Order模型对象
     * @var \app\common\model\merchant\WithdrawOrder
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\merchant\WithdrawOrder;
        $this->view->assign("statusList", $this->model->getStatusList());
    }


    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            //如果发送的来源是Selectpage，则转发到Selectpage
            if ($this->request->request('keyField')) {
                return $this->selectpage();
            }
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->where('member_id', STORE_ID)
                ->order($sort, $order)
                ->paginate($limit);

            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }


    /**
     * 默认生成的控制器所继承的父类中有index/add/edit/del/multi五个基础方法、destroy/restore/recyclebin三个回收站方法
     * 因此在当前控制器中可不用编写增删改查的代码,除非需要自己控制这部分逻辑
     * 需要将application/admin/library/traits/Backend.php中对应的方法复制到当前控制器,然后进行修改
     */


    public function add($ids = "")
    {
        if (false === $this->request->isPost()) {
            $usdt_address = Member::where('id', STORE_ID)->value('usdt_address');
            $this->view->assign('usdt_address', $usdt_address);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        $result = false;

        // id 和 amount 是必填的
        if (!isset($params['amount']) && $params['amount'] < 0) {
            $this->error('参数错误');
        }

        // 事务
        Db::startTrans();
        try {
            // 冻结余额
            $withdrawService = new WithdrawService();
            $wallet = $withdrawService->create(STORE_ID, $params['amount'], 0, $params['usdt_address'], $params['remark']);
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        Db::commit();
        $this->success();
    }

    /**
     * edit
     */
    public function edit($ids = "")
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }

        if (false === $this->request->isPost()) {
            $this->view->assign('row', $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);
        $result = false;

        // id 和 amount 是必填的
        if ( !isset($params['amount']) && $params['amount'] < 0) {
            $this->error('参数错误');
        }

        // 事务
        Db::startTrans();
        try {
            // 冻结余额
            $withdrawService = new WithdrawService();
            $result = $withdrawService->edit(
                $ids,
                $params['amount'],
                1,
                0,
                $params['usdt_address'],
                $params['remark']
            );
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }

        Db::commit();
        $this->success();

    }

    public function del($ids = "")
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
}
