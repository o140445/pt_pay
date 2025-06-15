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
use think\Log;

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
            //修改日志路径
            Log::init([
                'type'  => 'File',
                'path'  => LOG_PATH . 'pay/',
                'level' => ['error', 'info'],
            ]);

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

                Db::startTrans();
                try {
                    $orderService = new OrderOutService();
                    $order = $orderService->memberCreateOrder(STORE_ID, $params);
                } catch (\Exception $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
                Db::commit();
                $this->success();
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



}
