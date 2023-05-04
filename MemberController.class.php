<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/22
 * Time: 10:49
 */

namespace Admin\Controller;




use Admin\Model\MallWquotaRechargeRecordModel;
use Common\Model\MallWquotaDetailModel;
use Common\Model\MallWquotaUseModel;
use Common\Model\SmsModel;
use Common\Model\UserModel;
use Common\Model\VerifyModel;
use Common\Model\WquotaModel;
use Org\Util\Response;

class MemberController extends CommonController
{
    /**
     *用户列表
     */
    public function index(){
        $nickname       =   I('name');
        $map = [];
//        $map['status']  =   array('egt',0);
        if(!empty($nickname)){
            $map['user_id|user_name']=   array(intval($nickname),array('like','%'.$nickname.'%'),'_multi'=>true);
        }
        $User = new UserModel();
        $list   = $this->lists($User, $map);
        int_to_string($list);
        $this->assign('_list', $list);
        $this->meta_title = '用户信息';
        $this->display();
    }

    /**
     *  查看个人福利豆详情
     */
    public function wquota(){
        $map = [
            'uid'=>I('get.id')
        ];
        $startTime = trim(I('get.startTime'));
        if(!empty($startTime)){
            $map['time'] = ['like',$startTime.'%'];
        }
        $Wquota = new MallWquotaDetailModel();
        $list   = $this->lists($Wquota, $map);
//        int_to_string($list);
        $list = $this->format($list);
        $this->assign('_list', $list);
        $this->meta_title = '福利豆列表';

        //平台的福利豆统计
        $quotaModel = new WquotaModel();
        $quotaBalance = $quotaModel->getQuotaBalance($map);
        $this->assign('totalQuota', $quotaBalance['totalQuota']);
        $this->assign('restQuota', $quotaBalance['restQuota']);
        $this->assign('expireQuota', $quotaBalance['expireQuota']);
        $this->assign('useQuota', $quotaBalance['useQuota']);

        $this->display();
    }

    /**
     * 添加福利豆
     */
    public function addWquota(){
        $info = getCorporateList();
        $ip = get_client_ip();
        $ip_arr = C('sy_ip');
        if(!in_array($ip,$ip_arr)){
            $this->error('非法充值');
        }
        $this->assign('companys',$info);
        $this->display('wquotal_add');
    }

    /**
     * 福利豆的预览功能
     */
    public function view_index(){
        $array = I('array','');
        $data = excel_data($array);
        if(!empty($data)){
            unset($data[1]);
            unset($data[2]);
            $new_data = array();
            foreach($data as $key=>$value) {
                if (!empty($value['A']) && !empty($value['B']) && !empty($value['C'])) {
                    if(check_mobile($value['B']) == 0 || !is_numeric($value['C']) || $value['C']<=0 || round($value['C'], 2) != round($value['C'], 3)){
                        $error = 1;
                    }
                    $array1['name'] = $value['A'];
                    $array1['mobile'] = $value['B'];
                    $array1['num'] = $value['C'];
                    $array1['remarks'] = $value['D'];
                    $new_data[] = $array1;
                }
            }
        }
        $num = 0;
        if($error != 1){
            foreach($new_data as $value){
                $num += $value['num'];
            }
        }
        $this->assign('file_data',$array);
        $this->assign('num',$num);
        $this->assign('error',$error);
        $this->display('view_excel');
    }

    /**
     * 批量添加个人福利豆
     * @return mixed
     */
    public function add(){
        $company = ADMINSHOPID;
        $type = I('type','');
        $file_name = $_POST['file_name'];
        $name = trim(I('name',''));
        $start_time = date("Y-m-d",time());
        $end_time = I('end_time', '');
        if(empty($company)||empty($type)||empty($file_name)||empty($name)||empty($end_time)){
            return Response::show('300','请填写完整后提交');
        }
        $now = date('Y-m-d');
        if($start_time<$now || $end_time<$now){
            return Response::show(400,'不能选择过去时间');
        }
        if($start_time>$end_time){
            return Response::show(400,'开始时间不能大于结束时间');
        }
        //查询名称是否重复
        $where_check_name['name'] = array('eq',$name);
        $check_name = M('mall_wquota_recharge_record')->where($where_check_name)->find();
        if(!empty($check_name)){
            return Response::show(400,'活动名称重复');
        }
        if(empty($name)){
            return Response::show(400,'活动名称不能为空');
        }

        $data = excel_data($file_name);
        unlink($file_name);
        $new_data = array();
        if(!empty($data)){
            unset($data[1]);
            unset($data[2]);
            foreach ($data as $key => $value) {
                if (empty($value['A']) && empty($value['B']) && empty($value['C']) && empty($value['D'])) {
                    unset($data[$key]);
                }
            }
            foreach($data as $key=>$value) {
                $value['A'] = trim($value['A']);
                $value['B'] = trim($value['B']);
                $value['C'] = trim($value['C']);
                if (!empty($value['A']) && !empty($value['B']) && !empty($value['C'])) {
                    if(check_mobile($value['B']) == 0){
                        return Response::show(304,'第'.($key).'行用户手机号码异常');
                    }
                    if(!is_numeric($value['C']) || $value['C']<=0 || round($value['C'], 2) != round($value['C'], 3)){
                        return Response::show(304,'第'.($key).'行充值额度不符合规则: 数字型，必须大于0，最多保留两位小数');
                    }
                    $array1['name'] = $value['A'];
                    $array1['mobile'] = $value['B'];
                    $array1['num'] = $value['C'];
                    $array1['remarks'] = $value['D'];
                    $new_data[] = $array1;
                }
            }
            $all = count($new_data);
        }
        $num = 0;
        $user = new UserModel();
        foreach ($new_data as $key=>$value){
            //判断是否存在这个账户,如果不存在创建当前用户
            $check = M('User','t_')->where(['user_name'=>$value['mobile']])->getField('user_id');
            if(!$check){
                $password = substr($value['mobile'], -6, 6);
                $uid = $user->registerByMobile($value['mobile'],$password);
                //给这个手机号码发送密码
                if(0<$uid) {
                    SmsModel::sendSmsForRegister($value['mobile'], $password);
                }
                $check = $uid;
            }
            $new_data[$key]['uid'] = $check;
            $num += $value['num'];
        }
        $Record= $model = new MallWquotaRechargeRecordModel();
        $Detail = new MallWquotaDetailModel();
        $model->startTrans();

        $endTimeS = strtotime($end_time . ' 00:00:00');
        $one = $Record->addPersonalRecord($num,$all,$type,$name,$end_time);

        $addall = true;
        foreach($new_data as $k=>$v){
            $detailAdd = $Detail->addRecord($v['uid'], 1, $v['num'], $endTimeS, 0, $company, $v['remarks'], $one);
            if ($detailAdd == false) {
                $addall = false;
                break;
            }

        }

        //添加操作日志
        $admin_log = '个人批量充值福利豆，活动名:' . $name;
        if($one !=false && $addall !=false ){
            $model->commit();
            $cname = getcnamebycid($company);
            //导入成功后发送推送
            foreach($new_data as $key=>$value){
                SmsModel::sendQuotaSms($value['mobile'], $value['name'], $cname, $value['num'], $end_time);
                //重置福利豆提醒已看时间
                resetQuotaDate($value['uid'], $end_time);
            }
            //发放福利豆安全提醒
            SmsModel::sendQuotaSmsSafety($num,2,$name);
            admin_log($admin_log, 1, 'dsy_mall_wquota_recharge_record:' . $one);
            return Response::show('200','导入成功,短信通知成功');
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_mall_wquota_recharge_record');
            return Response::show('400','导入失败,请检查人员信息');
        }

    }

    /**
     * 预览功能
     * @return string
     */
    public function view(){
        if(!empty($_FILES['file'])){
            $config=array(
                'exts'=>array('xlsx','xls'),
                'rootPath'=>"./Public/",
                'savePath'=>'Uploads/temp/',
                'subName'    =>    array('date','Ymd'),
            );

            $upload = new \Think\Upload($config);
            if (!$info=$upload->uploadOne($_FILES['file'])) {
                $error = $upload->getError();
            }

            if(!empty($error)){
                if($error=='上传文件后缀不允许'){
                    $error = '请选择正确的文件类型!';
                }
                return Response::show(400,$error);
            }
            $file_name=$upload->rootPath.$info['savepath'].$info['savename'];
            $this->ajaxReturn($file_name);
        }
    }

    private function format($data){
//        $Use = new MallWquotaUseModel();
        foreach ($data as $k=>$detail){
//            if($detail['use_id']!=0){
//                $orders = explode(',',$detail['use_id']);
//                $str = '';
//                foreach ($orders as $order){
//                    $str.= $Use->where(['id'=>$order])->getField('ordernum').' ';
//                }
//                $data[$k]['use_id'] = $str;
//            }
            $data[$k]['balance'] = $detail['balance'] > 0 ? $detail['balance'] : '--';
            $data[$k]['expire_quota'] = $detail['expire_quota'] > 0 ? $detail['expire_quota'] : '--';
            $use_quota = number_format(round($detail['num'] - $detail['expire_quota'] - $detail['balance'], 2), 2, '.', '');
            $data[$k]['use_quota'] = $use_quota > 0 ? $use_quota : '--';
        }
        return $data;
    }
}