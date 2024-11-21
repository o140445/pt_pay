<?php

namespace app\admin\controller\payment;

use app\common\controller\Backend;
use app\common\model\merchant\ConfigArea;
use app\common\service\PaymentService;
use think\Db;
use think\Exception;
use think\exception\PDOException;

class Channel extends Backend
{
    /**
     * @var \app\common\model\merchant\Channel
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = new \app\common\model\merchant\Channel;
        $this->view->assign("statusList", $this->model->getStatusList());

        $configChannelList = PaymentService::PAY_CHANNEL;
        $configChannelList[0] = __('请选择');

        $configAreaList = ConfigArea::column('id,name');
        $configAreaList[0] = __('请选择');
        $this->view->assign('configAreaList', $configAreaList);
        $this->view->assign('configChannelList', $configChannelList);
    }

    public function index()
    {
//        config_area
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
            ->with(['configArea'])
            ->where($where)
            ->order($sort, $order)
            ->paginate($limit);
        $result = ['total' => $list->total(), 'rows' => $list->items()];
        return json($result);
    }


    public function add()
    {
        if (false === $this->request->isPost()) {
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {

            // 参数检查
            if (empty($params['title'])) {
                throw new Exception('通道名称不能为空');
            }

            if (empty($params['code'])) {
                throw new Exception('通道编码不能为空');
            }

            if (empty($params['area_id'])) {
                throw new Exception('地区不能为空');
            }

            // 检查是否存在
            $res = $this->model->where('title', $params['title'])->find();
            if ($res) {
                throw new Exception('通道名称已存在');
            }

            $params['sign'] = $this->getSign();

            $params['extra'] = isset($params['extra']) ? json_encode($params['extra']) : '[]';
            $result = $this->model->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were inserted'));
        }
        $this->success();
    }

    public function edit($ids = null)
    {
        $row = $this->model->get($ids);
        if (!$row) {
            $this->error(__('No Results were found'));
        }
        $adminIds = $this->getDataLimitAdminIds();
        if (is_array($adminIds) && !in_array($row[$this->dataLimitField], $adminIds)) {
            $this->error(__('You have no permission'));
        }
        if (false === $this->request->isPost()) {
            $row['extra'] = json_decode($row['extra'], true);
            $this->view->assign("row", $row);
            return $this->view->fetch();
        }
        $params = $this->request->post('row/a');
        if (empty($params)) {
            $this->error(__('Parameter %s can not be empty', ''));
        }
        $params = $this->preExcludeFields($params);

        if ($this->dataLimit && $this->dataLimitFieldAutoFill) {
            $params[$this->dataLimitField] = $this->auth->id;
        }
        $result = false;
        Db::startTrans();
        try {
            //是否采用模型验证
            if ($this->modelValidate) {
                $name = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                $validate = is_bool($this->modelValidate) ? ($this->modelSceneValidate ? $name . '.edit' : $name) : $this->modelValidate;
                $this->model->validateFailException()->validate($validate);
            }
            $params['extra'] = $params['extra'] ? json_encode($params['extra']) : '[]';
            $result = $row->allowField(true)->save($params);
            Db::commit();
        } catch (ValidateException|PDOException|Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($result === false) {
            $this->error(__('No rows were updated'));
        }
        $this->success();
    }

    protected  function getSign()
    {

        $string = get_random_string(4);

        // 检查是否已经存在
        $count = $this->model->where('sign', $string)->count();
        if ($count > 0) {
            return $this->getSign();
        }

        return $string;

    }

    public function config()
    {
        $code = $this->request->get('code');
        if (empty($code)) {
            $this->success('', null, []);
        }
        $paymentService = new PaymentService($code);
        $config = $paymentService->getConfig();

        $this->success('', null, $config);
    }

    public function del($ids = "")
    {

        $this->error(__('No rows were deleted'));
    }

    public function destroy($ids = "")
    {
        $this->error(__('No rows were deleted'));
    }

}