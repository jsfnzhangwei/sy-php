<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/22 0022
 * Time: 下午 4:29
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;

class AdminController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 获取企业行业
     * @param $key
     * @return mixed|string
     */
    private function getIndustryType($key)
    {
        $industryArr = [
            1 => 'IT/通信',
            2 => '金融业',
            3 => '房地产/建筑业',
            4 => '商业服务',
            5 => '贸易/批发/零售/租赁业',
            6 => '文体教育/工业美术',
            7 => '生产/加工/制造',
            8 => '交通/运输/物流/仓储',
            9 => '服务业',
            10 => '文化/传媒/娱乐/体育',
            11 => '能源/矿产/环保'
        ];
        return isset($industryArr[$key]) ? $industryArr[$key] : '其他';
    }

    public function admin_index(){
        //总企业数
        $company = M('employee', 't_')->where('`review_status`=1 AND `emp_type`=1 AND `del_status`=0 AND `status`!=5 ')->count();
        $this->assign('company', $company);
        //总员工数
        $allEnum = M('employee', 't_')->where('`del_status`=0')->count();
        $this->assign('allenum', $allEnum);
        //总在职员工数
        $enum = M('employee', 't_')->where('`del_status`=0 AND `status`!=5')->count();
        $this->assign('enum', $enum);
        //总离职员工数
        $enum_lizhi = M('employee', 't_')->where('`del_status`=0 AND `status`=5')->count();
        $this->assign('enum_lizhi', $enum_lizhi);
        //总删除员工数
        $enum_del = M('employee', 't_')->where('`del_status`!=0')->count();
        $this->assign('enum_del', $enum_del);
        //总激活员工数
        $sql = 'SELECT COUNT(1) as num FROM `t_employee` e LEFT JOIN `t_personal` p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` u ON p.`user_id`=u.`user_id` WHERE e.`del_status`=0 AND u.`is_login_app`=1';
        $arr1 = M()->query($sql);
        $cnum = $arr1[0]['num'];
        $this->assign('cnum', $cnum);

        $this->display('index');
    }


    /**
     * 主页列表信息
     */
    public function admin_info(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = 10;
        $cname = I('cname','');
        $cname = trim($cname);
        $cmobile = I('cmobile','');
        $cmobile = trim($cmobile);

        $where = [];
        if(!empty($cname)){
            $where['c.corporate_name'] = array('like',"%$cname%");
        }
        if(!empty($cmobile)){
            $where['p.mobile'] = array('like',"%$cmobile%");
        }
        $info = (array)M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=c.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where("e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
            ->where($where)
            ->field('p.`mobile` uname,p.`name` user_name,c.`corporate_id` cid,c.`corporate_name` cname,c.`contacts_name`,c.`contacts_mobile` cmobile,c.`user_id`,c.`create_time`,c.`owned_industry`,c.`number`,c.`address`,c.`province`,c.`city`,c.`area`,c.`business_licence`,c.`customer_id`')
            ->order('c.`create_time` desc')
            ->page($page, $limit)
            ->select();
        $num = (int)M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=c.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where("e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
            ->where($where)
            ->count();

        $local = M('provincial','dsy_');

        foreach($info as $key=>$value){
            $cid = $value['cid'];
            //在职员工数
            $enum = M('employee', 't_')->where('`del_status`=0 AND `status`!=5 AND `corporate_id`=' . $cid)->count();
            $info[$key]['enum'] = $enum;
            //总激活员工数
            $sql = 'SELECT COUNT(1) as num FROM `t_employee` e LEFT JOIN `t_personal` p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` u ON p.`user_id`=u.`user_id` WHERE e.`del_status`=0 AND u.`is_login_app`=1 AND e.`corporate_id`=' . $cid;
            $arr1 = M()->query($sql);
            $cnum = $arr1[0]['num'];
            $info[$key]['cnum'] = $cnum;
            $arr2 = M()->query($sql . ' AND e.`status`!=5');
            $jobActiveNum = $arr2[0]['num'];
            $info[$key]['jobActiveNum'] = $jobActiveNum;

            $string = $value['province'].','.$value['city'].','.$value['area'];
            $where1['id'] = array('in',$string);
            $localcation = $local->where($where1)->field('name')->select();
            $info[$key]['area'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'];
            $info[$key]['address'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'].$value['address'];

            $info[$key]['owned_industry'] = $this->getIndustryType($value['owned_industry']);
            $info[$key]['customer_id'] = empty($value['customer_id']) ? '' : $value['customer_id'];
        }

        return Response::mjson($info,$num);
    }

    /*新增游客管理*/
    public function person_index(){
        $model = M('user','t_');
        $where_all['user_type'] = array('eq',3);
        $all = $model->where($where_all)->count();
        $where_no['user_type'] = array('eq',3);
        $where_no['is_login_app'] = array('eq',0);
        $no = $model->where($where_no)->count();
        $where_yes['user_type'] = array('eq',3);
        $where_yes['is_login_app'] = array('eq',1);
        $yes = $model->where($where_yes)->count();
        $this->assign('all',$all);
        $this->assign('no',$no);
        $this->assign('yes',$yes);
        $this->display('person_index');
    }

    public function person_info(){
        $name = trim(I('name',''));
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = 10;
        $model = M('user','t_');
        $where['user_type'] = array('eq',3);
        if(!empty($name)){
            $where['user_name'] = array('like',"%$name%");
        }
        $info = $model
            ->where($where)
            ->field('user_id,user_name')
            ->page($page,$limit)
            ->order('user_id desc')
            ->select();
        $count = $model
            ->where($where)
            ->field('user_id,user_name')
            ->count();
        return Response::mjson($info,$count);
    }

    /**
     * 注册审核主页
     */
    public function reg_check_index(){
        $this->display();
    }

    /**
     * 注册列表信息
     */
    public function reg_check_index_info(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $status = I('status','');
        $cname = I('cname','');

        $where = [];
        if ($status != '' && in_array($status, [0, 1, 2])) {
            $where['e.review_status'] = $status;
        } else {
            $where['e.review_status'] = array('lt', 3);
        }
        if(!empty($cname)){
            $where['c.corporate_name'] = array('like',"%$cname%");
        }

        $info = (array)M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=c.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where("e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
            ->where($where)
            ->field('p.`mobile` uname,p.`name` user_name,e.`review_status`,c.`corporate_id`,c.`corporate_name`,c.`contacts_name`,c.`contacts_mobile` cmobile,c.`create_time`,c.`owned_industry`,c.`number`,c.`address`,c.`province`,c.`city`,c.`area`,c.`business_licence`')
            ->order('e.`review_status` ASC,c.`create_time` DESC')
            ->page($page, $limit)
            ->select();
        $num = (int)M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=c.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where("e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
            ->where($where)
            ->count();

        $local = M('provincial','dsy_');
        if(!empty($info)){
            foreach($info as $key=>$value){
                $string = $value['province'].','.$value['city'].','.$value['area'];
                $where1['id'] = array('in',$string);
                $localcation = $local->where($where1)->field('name')->select();
                if (in_array($localcation[1]['name'], ['市辖区', '县'])) {
                    $localcation[1]['name'] = '';
                }
                if (in_array($localcation[2]['name'], ['市辖区', '县'])) {
                    $localcation[2]['name'] = '';
                }
                $info[$key]['area'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'];
                $info[$key]['address'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'].$value['address'];

                $info[$key]['owned_industry'] = $this->getIndustryType($value['owned_industry']);
                if(empty($value['business_licence'])){
                    $info[$key]['business_licence'] = '';
                }else{
                    $img = format_img($value['business_licence'], IMG_VIEW);
                    $info[$key]['business_licence'] = '<a href="'.$img.'" target="view_window">点击查看</a>';
                }
            }
        }

        return Response::mjson($info,$num);
    }


    /**
     * 注册审核同意操作
     */
    public function reg_agree(){
        $id = I('ids','');
        $id = $id[0];
        if(empty($id)){
            return Response::show(300,'Miss Params');
        }

        $info = M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=c.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where("e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND e.`corporate_id`='".$id."'")
            ->field('e.`employee_id`,c.`corporate_name`,p.`mobile`')
            ->find();

        $cname = $info['corporate_name'];
        $mobile = $info['mobile'];
        $message = '恭喜您！您申请注册'.$cname.'已审核通过,快去登录使用吧~';
        //添加操作日志
        $admin_log = '企业【' . $cname . '】注册审核通过';

        $new_data = [
            'review_status' => 1,
            'review_time' => NOW,
        ];
        $one = M('employee', 't_')->where(['employee_id' => $info['employee_id']])->save($new_data);
        if($one == true){
            try{
                $to = $mobile;
                $datas = array(
                    0=>$message,
                    1=>5
                );
                $tempId = '379186';
                SendTemplateSMS($to,$datas,$tempId);
            }catch(Exception $e){
            }
            admin_log($admin_log, 1, 't_corporate:' . $id);
            return Response::show(200,'Success');
        }else{
            admin_log($admin_log, 0, 't_corporate:' . $id);
            return Response::show(400,'Fail');
        }
    }

    /**
     * 注册审核驳回操作
     */
    public function reg_cancle(){
        $reson = I('reson','');
        $reson = trim($reson);
        $ids = I('ids','');
        $id = $ids[0];
        if(empty($id)){
            return Response::show(300,'Miss Params');
        }

        $info = M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=c.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where("e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND e.`corporate_id`='".$id."'")
            ->field('e.`employee_id`,c.`corporate_name`,p.`mobile`')
            ->find();

        $cname = $info['corporate_name'];
        $mobile = $info['mobile'];
        $message = '您申请注册的'.$cname.'被驳回,疑问请咨询:400-800-5198';
        //添加操作日志
        $admin_log = '企业【' . $cname . '】注册审核不通过，驳回原因：' . $reson;

        $new_data = [
            'review_status' => 2,
            'review_time' => NOW,
        ];
        if (!empty($reson)) {
            $new_data['review_remark'] = $reson;
        }
        $one = M('employee', 't_')->where(['employee_id' => $info['employee_id']])->save($new_data);
        if($one == true){
            try{
                $to = $mobile;
                $datas = array(
                    0=>$reson,
//                    1=>5
                );
                $tempId = '379188';
                SendTemplateSMS($to,$datas,$tempId);
            }catch(Exception $e){
            }
            admin_log($admin_log, 1, 't_corporate:' . $id);
            return Response::show(200,'Success');
        }else{
            admin_log($admin_log, 0, 't_corporate:' . $id);
            return Response::show(400,'Fail');
        }
    }


    /**
     * 资料变更审核
     */
    public function change_info_index(){
        $this->display();
    }

    public function company_lis(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $cname = I('cname','');
        $cname = trim($cname);
        $status = I('status','');
        if(!empty($cname)){
            $where['a.corporate_name'] = array('like',"%$cname%");
        }
        if(empty($status)){
            if($status === (string)0){
                $where['a.status'] = array('eq',$status);
            }
        }else{
            $where['a.status'] = array('eq',$status);
        }
        //可能用到
        $corporate_auditing = M('corporate_auditing','t_');
        $info = $corporate_auditing
            ->join('as a left join t_corporate as b on a.corporate_id = b.corporate_id')
            ->where($where)
            ->field('a.id,a.corporate_id as cid,a.corporate_name as cname,a.owned_industry,a.number,a.address,b.create_time,a.province,a.city,a.area,a.business_licence,a.status')
            ->order('a.id desc')
            ->page($page,$limit)
            ->select();

        $num = $corporate_auditing
            ->join('as a left join t_corporate as b on a.corporate_id = b.corporate_id')
            ->where($where)
            ->count();
        $local = M('provincial','dsy_');
        foreach($info as $key=>$value){
            $string = $value['province'].','.$value['city'].','.$value['area'];
            $where1['id'] = array('in',$string);
            $localcation = $local->where($where1)->field('name')->select();
            $info[$key]['area'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'];
            $info[$key]['address'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'].$value['address'];
            $info[$key]['owned_industry'] = $this->getIndustryType($value['owned_industry']);
            if(empty($value['business_licence'])){
                $info[$key]['business_licence'] = '';
            }else{
                $img = format_img($value['business_licence'], IMG_VIEW);
                $info[$key]['business_licence'] = '<a href="'.$img.'" target="view_window">点击查看</a>';
            }

        }
        return Response::mjson($info,$num);
    }

    /**
     * 修改资料审核通过
     */
    public function change_agree(){
        $id = I('ids','');
        $id = $id[0];
        $corporate_auditing = M('corporate_auditing','t_');
        $corporate_auditing->startTrans();
        $corporate_auditing->status = 1;
        $where['id'] = array('eq',$id);
        $one = $corporate_auditing->where($where)->save();

        $all = $corporate_auditing->where($where)->find();
        $cname = trim($all['corporate_name']);
        $where_check['corporate_name'] = array('eq',$cname);
        $where_check['corporate_id'] = array('neq',$all['corporate_id']);
        $is_exist = M('corporate','t_')->where($where_check)->find();
        if(!empty($is_exist)){
            return Response::show(400,'公司名称重复');
        }
        //判断修改的企业名称是否重复
        $cid = $all['corporate_id'];
        $data['owned_industry'] = $all['owned_industry'];
        $data['province'] = $all['province'];
        $data['city'] = $all['city'];
        $data['area'] = $all['area'];
        $data['number'] = $all['number'];
        $data['corporate_name'] = $all['corporate_name'];
//        $data['contacts_name'] = $all['contacts_name'];
        $data['business_licence'] = $all['business_licence'];
        $data['proxy'] = $all['proxy'];
//        $data['contacts_mobile'] = $all['contacts_mobile'];
        $data['address'] = $all['address'];
        $where1['corporate_id'] = array('eq',$cid);
        $two = M('corporate','t_')->where($where1)->save($data);

        $info = $corporate_auditing->where($where)->find();
        $cid = $info['corporate_id'];
        $where1['corporate_id'] = array('eq',$cid);
        $cinfo = M('corporate','t_')->where($where1)->find();
        $mobile = $cinfo['contacts_mobile'];
//        $cname = $info['corporate_name'];
        $message = '恭喜您！您申请修改公司资料已通过审核,请登录人事邦系统查看~';
        //添加操作日志
        $admin_log = '企业【' . $cname . '】修改申请同意';
        if($one&&$two !== false){
            $corporate_auditing->commit();
            try{
                $to = $mobile;
                $datas = array(
                    0=>$message,
                    1=>5
                );
                $tempId = '379186';
                SendTemplateSMS($to,$datas,$tempId);

            }catch(Exception $e){

            }
            admin_log($admin_log, 1, 't_corporate_auditing:' . $id);
            return Response::show(200,'Success');
        }else{
            $corporate_auditing->rollback();
            admin_log($admin_log, 0, 't_corporate_auditing:' . $id);
            return Response::show(400,'Fail');
        }
    }

    /**
     * 修改资料审核不通过
     */
    public function change_disagree(){
        $reson = I('reson','');
        $ids = I('ids','');
        $id = $ids[0];
        $reson = trim($reson);
        $corporate_auditing = M('corporate_auditing','t_');
        $where['id'] = array('eq',$id);
        if(!empty($reson)){
            $corporate_auditing->remark = $reson;
        }
        $corporate_auditing->status = 2;
        $one = $corporate_auditing->where($where)->save();

        $info = $corporate_auditing->where($where)->find();
        $cid = $info['corporate_id'];
        $corporate = M('corporate','t_');
        $where1['corporate_id'] = array('eq',$cid);
        $linfo = $corporate->where($where1)->find();

        $mobile = $linfo['contacts_mobile'];
        $message = '您申请修改公司资料已被驳回,请登录人事邦系统查看,疑问请咨询:400-800-5198';
        //添加操作日志
        $admin_log = '企业【' . $linfo['corporate_name'] . '】修改申请驳回，驳回原因：' . $reson;
        if($one == true){
            try{
                $to = $mobile;
                $datas = array(
                    0=>$reson,
//                    1=>5
                );
                $tempId = '379188';
                SendTemplateSMS($to,$datas,$tempId);

            }catch(Exception $e){

            }
            admin_log($admin_log, 1, 't_corporate_auditing:' . $id);
            return Response::show(200,'Success');
        }else{
            admin_log($admin_log, 0, 't_corporate_auditing:' . $id);
            return Response::show(400,'Fail');
        }

    }

    public function setFun()
    {
        $cid = (int)$_GET['cid'];
        $this->assign('cid', $cid);
        $code = M('corporate', 't_')->where(['corporate_id' => $cid])->getField('customer_id');
        $this->assign('code', empty($code) ? '' : $code);

        $result = text_curl(JAVA_API_URL_ADMIN . 'corMenu/selectAll?app=app&corId=' . $cid, []);
        $msg = text_curl_get(JAVA_API_URL_ADMIN . 'after/entryLeaceMessage/selMenuDetail?app=app&cid=' . $cid);
        $arr = ['t2'=>'','t3'=>'','t1'=>''];
        $switch = 1;
        if ($result['errorCode'] == 1) {
            $menus = $result['corMenu']['listCorMenu'];
            $menus_choose = $result['corMenu']['listCorPer'];
            if(in_array(8,$menus_choose)){
                $switch = 0;
            }
            foreach ($menus as $v) {
                if (in_array($v['id'], $menus_choose)) {
                    $v['checked'] = true;
                }
                if($v['parentId'] != 8){
                    $arr['t' . $v['type']][] = $v;
                }else{
                    $arr['t3'][] = $v;
                }
            }
        }
        $this->assign('menus', $arr);
        $this->assign('switch', $switch);
        $this->assign('site_url', RSB_URL);
        $this->assign('msg',$msg);
        $this->display();
    }

    public function setContractCode()
    {
        $rid = I('rid', 0);
        $code = I('code', "");
        if (empty($code)) {
            return Response::show(300, "请填写电子合同编号");
        }
        if (!preg_match('/^[0-9A-Za-z]+$/', $code)) {
            return Response::show(300, "请填写正确的电子合同编号");
        }
        $res = M('corporate', 't_')->where(['corporate_id' => $rid])->setField('customer_id', $code);
        if ($res === false) {
            return Response::show(300, "操作失败");
        }
        return Response::show(200, "操作成功");
    }
}