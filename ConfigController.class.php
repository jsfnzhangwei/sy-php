<?php
/**
 * Created by PhpStorm.
 * User: 86183
 * Date: 2018/7/10
 * Time: 17:00
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Common\Model;

class ConfigController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    //系统配置更改
    public function updataSystemConfig() {
//        $info = M('config', 'sys_')->select();
        $this->display('configUpdata');
    }

    public function list_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;

        $info = M('config', 'sys_')
            ->page($page,$limit)
            ->select();
        $count = M('config', 'sys_')
            ->count();

        return Response::mjson($info,$count);
    }

    public function configEdit() {
        $data['id'] = I('id','');
        $data['name'] = I('name','');
        $data['caption'] = I('caption','');
        $data['type'] = I('type','');
        $data['value'] = I('value','');
        $this->assign('data', $data);
        $this->display('configEdit');
    }


    public function updataConfig() {
        $id = I('id','');
        $data['value'] = I('value','');
        if (!empty($data) && !empty($id)) {
            $config = D('Common/Config');
            $result = $config->systemConfig($id, $data);
            if ($result) {
                return Response::show(200,'true');
            } else {
                return Response::show(300,'false');
            }
        } else {
            return Response::show(300,'false');
        }
    }

    /**
     * 获取第三方接口余额
     */
    public function getThirdAmount()
    {
        //微知余额
        $token = selAccess_token();
        $wzAmount = getMoney($token);

        //千米余额
        $WartercoalApi = A('app/WartercoalApi');
        $qmRes = $WartercoalApi->getAmount();
        $qmAmount = ($qmRes['code'] == 200) ? $qmRes['data'] : '--';

    }

    public function servicePage()
    {
        $list = M('service_telephone')->select();
        $this->assign('list', $list);
        $this->display();
    }

    public function serviceEdit()
    {
        $telArr = I('tel', []);
        if (empty($telArr)) {
            return Response::show(300, '没有可修改的数据');
        }

        foreach ($telArr as $k => $v) {
            if (!empty($v)) {
                M('service_telephone')->where(['id' => $k])->setField('num', $v);
            }
        }

        return Response::show(200, '修改成功');
    }
}