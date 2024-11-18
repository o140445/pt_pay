<?php

namespace app\manystore\controller\finance;

use app\common\controller\ManystoreBase;
use app\common\model\merchant\Member;
use app\common\model\merchant\MemberStatModel;

/**
 * 商户每日统计
 *
 * @icon fa fa-circle-o
 */
class Stat extends ManystoreBase
{

    /**
     * Stat模型对象
     * @var \app\common\model\merchant\MemberStatModel
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new MemberStatModel();

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

        $id = STORE_ID;
        $member = Member::find($id);
        // 如果是代理商，获取代理商下的商户
        if ($member->is_agency) {
            $member_ids = Member::where('agency_id', $id)->column('member_id');
        }else{
            $member_ids = [$id];
        }

        [$where, $sort, $order, $offset, $limit] = $this->buildparams();
        $sort = 'date';
        $list = $this->model
            ->where($where)
            ->whereIn('member_id', $member_ids)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
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
