<?php

namespace app\admin\controller\finance\wallet;

use app\admin\model\MemberWallerLog;
use app\admin\model\MemberWalletModel;
use app\common\controller\Backend;

/**
 * 会员余额变动记录管理
 *
 * @icon fa fa-circle-o
 */
class Log extends Backend
{

    /**
     * Log模型对象
     * @var \app\admin\model\MemberWallerLog
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new MemberWallerLog();

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
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);

        $data = $list->items();

        // 设置type_name
        $type = MemberWalletModel::CHANGE_TYPE;
        foreach ($data as $key => $value) {
            $data[$key]['type_name'] = $type[$value['type']] ?? '';
        }

        $result = ['total' => $list->total(), 'rows' => $data];
        return json($result);
    }

    /**
     * 获取会员余额变动类型
     */
    public function getChangeType()
    {
        return MemberWalletModel::CHANGE_TYPE;
    }

    public function add()
    {
         $this->error('请求错误');
    }

    public function edit($ids = null)
    {
         $this->error('请求错误');
    }

    public function del($ids = "")
    {
         $this->error('请求错误');
    }

    public function multi($ids = "")
    {
         $this->error('请求错误');
    }

    public function destroy($ids = "")
    {
         $this->error('请求错误');
    }


    public function restore($ids = "")
    {
         $this->error('请求错误');
    }

    public function recyclebin()
    {
         $this->error('请求错误');
    }

}