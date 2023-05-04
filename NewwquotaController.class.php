<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/2/23 0023
 * Time: 上午 11:32
 */
namespace Admin\Controller;
use Common\Model\MallWquotaDetailModel;
use Common\Model\WquotaModel;
use Admin\Model\MallWquotaRechargeRecordModel;
use Common\Model\SmsModel;
use Org\Util\Response;
use Think\Controller;

class NewwquotaController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 福利豆充值列表
    **/
    public function wquotal_recharge(){
        $start = date('Y-m-01', strtotime(date("Y-m-d")));
        $end = date('Y-m-d');
        $this->assign('start',$start);
        $this->assign('end',$end);

        $this->display('wquotal_recharge_index');
    }

    /**
     * 列表数据
    **/
    public function list_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $cname = I('cname','');
        $type = I('type','');
        $start = I('start1','');
        $end = I('end','');
        if( (empty($start)&&!empty($end)) || (!empty($start)&&empty($end)) ){
            return Response::show('400','请选择完整日期');
        }
        $where = array();
        $model = M('mall_wquota_recharge_record','dsy_');
        if(!empty($cname)){
            $where['b.corporate_name'] = array('like',"%$cname%");
        }
        if(!empty($type)){
            $where['a.type'] = array('eq',$type);
        }
        if(!empty($start)&&!empty($end)){
            if($start == $end){
                $where['a.time'] = array('eq',$start);
            }else{
                $where['a.time'] = array('between',array($start,$end));
            }
        }else{
            $start = date('Y-m-01', strtotime(date("Y-m-d")));
            $end = date('Y-m-d');
            if($start == $end){
                $where['a.time'] = array('eq',$start);
            }else{
                $where['a.time'] = array('between',array($start,$end));
            }
        }
        $where['a.send_type'] = 1;
        $info = $model
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->where($where)
            ->field('b.corporate_name,a.id,a.cid,a.wquota,a.num,a.type,a.time,a.name')
            ->page($page,$limit)
            ->order('a.id desc')
            ->select();

        $numArr = $model
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->where($where)
            ->field('a.id')
            ->select();
        $num = count($numArr);

        $quotaModel = new WquotaModel();
        foreach ($info as $key=>$item){
            //平台的福利豆统计
            $quotaBalance = $quotaModel->getQuotaBalance(['rid' => $item['id'], 'type' => 1]);
            $info[$key]['totalQuota'] = $quotaBalance['totalQuota'];
            $info[$key]['restQuota'] = $quotaBalance['restQuota'];
            $info[$key]['expireQuota'] = $quotaBalance['expireQuota'];
            $info[$key]['useQuota'] = $quotaBalance['useQuota'];
        }

        //平台的福利豆统计
        $rids = array_merge(array_column($numArr, 'id'), [-1]);
        $quotaBalance = $quotaModel->getQuotaBalance(['rid' => ['in', $rids], 'type' => 1]);
        $extraData['totalQuota'] = $quotaBalance['totalQuota'];
        $extraData['restQuota'] = $quotaBalance['restQuota'];
        $extraData['expireQuota'] = $quotaBalance['expireQuota'];
        $extraData['useQuota'] = $quotaBalance['useQuota'];
        return Response::mjson($info,$num, $extraData);
    }



    /**
     * 添加页面
    **/
    public function wquotal_add(){
        $info = getCorporateList();
        $this->assign('companys',$info);
        $this->display('wquotal_add');
    }


    /**
     * 详情页面
     **/
    public function wquotal_detail(){
        $rid = I('id','');
        $cid = I('cid','');
        $this->assign('rid',$rid);
        $this->assign('cid',$cid);
        $this->display('wquotal_recharge_detail');
    }


    /**
     * 列表数据
     **/
    public function wquota_person(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $rid = I('rid','');
        $cid = I('cid','');
        $where_company['corporate_id'] = array('eq',$cid);
        $cname = M('corporate','t_')->where($where_company)->getField('corporate_name');
        $where['rid'] = array('eq',$rid);
        $info = M('mall_wquota_detail','dsy_')
            ->where($where)
            ->page($page,$limit)
            ->select();
        $num = M('mall_wquota_detail','dsy_')
            ->where($where)
            ->count();
        foreach($info as $key=>$value){
            $info[$key]['cname'] = $cname;
            $info[$key]['eid'] = getNameByEid($value['eid']);
        }

        return Response::mjson($info,$num);

    }

    /**
     * 预览页面
     **/
    public function view_index(){
        $array = I('array','');
        $company = I('company',0);
        $data = excel_data($array);

        $error = [];
        $noEmployee = [];
        $new_data = array();
        if(!empty($data)){
            unset($data[1]);
            unset($data[2]);
            foreach($data as $key=>$value) {
                $value['A'] = trim($value['A']);
                $value['B'] = trim($value['B']);
                $value['C'] = trim($value['C']);
                if (!empty($value['A']) && !empty($value['B']) && !empty($value['C'])) {
                    if(check_mobile($value['B']) == 0 || !is_numeric($value['C']) || $value['C']<=0 || round($value['C'], 2) != round($value['C'], 3)){
                        $error[] = $key;
                        continue;
                    }
                    if ($company > 0) {
                        //获取员工信息
                        $employeeInfo = getEmployeeByMobile($value['B'], $company);
                        if (empty($employeeInfo)) {
                            $noEmployee[] = $key;
                            continue;
                        }
                    }
                    $array1['name'] = $value['A'];
                    $array1['mobile'] = $value['B'];
                    $array1['num'] = $value['C'];
                    $array1['remarks'] = $value['D'];
                    $new_data[] = $array1;
                }
            }
        }
        $error = empty($error) ? '' : ('第' . implode('、', $error) . '行数据有误，请检查手机号和福利豆额（ 数字型，必须大于0，最多保留两位小数）格式！');
        if (!empty($noEmployee)) {
            if (!empty($error)) {
                $error .= '；';
            }
            $error .= '第' . implode('、', $noEmployee) . '行人员非本企业员工';
        }

        $num = 0;
        foreach($new_data as $value){
            $num += $value['num'];
        }

        $this->assign('file_data',$array);
        $this->assign('num',$num);
        $this->assign('error',$error);
        $this->assign('dataList',json_encode($new_data));
        $this->display('view_excel');
    }


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

    public function excel_dataQy(){
        //最后添加成功后删除文件
        $pageIndex = I('pageIndex',0);
        if($pageIndex==0){
            $page = 0;
        }else{
            $page = ($pageIndex)*10;
        }
        $array = $_GET['array'];
        $data = excel_data($array);
        $new_data = array();
        $error = [];
        if(!empty($data)){
            unset($data[1]);
            unset($data[2]);
            foreach($data as $key=>$value) {
                $value['A'] = trim($value['A']);
                $value['B'] = trim($value['B']);
                $value['C'] = trim($value['C']);
                if (!empty($value['A']) && !empty($value['B']) && !empty($value['C'])) {
                    if(check_mobile($value['B']) == 0 || !is_numeric($value['C']) || $value['C']<=0 || round($value['C'], 2) != round($value['C'], 3)){
                        $error[] = $key;
                        continue;
                    }
                    $array1 = [];
                    $array1['name'] = $value['A'];
                    $array1['mobile'] = $value['B'];
                    $array1['num'] = $value['C'];
                    $array1['remarks'] = $value['D'];
                    $new_data[] = $array1;
                }
            }
        }
        $error = empty($error) ? '' : ('第' . implode('、', $error) . '行数据有误，请检查手机号和福利豆额（ 数字型，必须大于0，最多保留两位小数）格式！');
        $all = count($new_data);
        $info = array_slice($new_data,$page,10);

        return Response::mjson($info,$all,$error);

    }

    /**
     * 添加操作
     **/
    public function add(){
        $company = I('company','');
        $type = I('type','');
        $file_name = $_POST['file_name'];
        $name = trim(I('name',''));
        $start_time = date("Y-m-d",time());
        $end_time = I('end_time','');
        if(empty($company)||empty($type)||empty($file_name)||empty($name)||empty($start_time)||empty($end_time)){
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
        $datalist = [];
        $num = 0;
        $error = [];
        $noEmployee = [];
        if(!empty($data)){
            unset($data[1]);
            unset($data[2]);
            foreach($data as $key=>$value) {
                $u_name = trim($value['A']);
                $u_mobile = trim($value['B']);
                $u_num = trim($value['C']);
                $u_remark = $value['D'];
                if (!empty($u_name) && !empty($u_mobile) && !empty($u_num)) {
                    if(check_mobile($u_mobile) == 0 || !is_numeric($u_num) || $u_num<=0 || round($u_num, 2) != round($u_num, 3)){
                        $error[] = $key;
                        continue;
                    }
                    //获取员工信息
                    $employeeInfo = getEmployeeByMobile($u_mobile, $company);
                    if(!empty($employeeInfo)){
                        $eid = $employeeInfo['employee_id'];
                        $uid = $employeeInfo['user_id'];
                        $new_data[] = [
                            'name' => $u_name,
                            'mobile' => $u_mobile,
                            'num' => $u_num,
                            'remarks' => $u_remark,
                            'uid' => $uid,
                        ];
                        $datalist[] = [
                            'eid' => $eid,
                            'uid' => $uid,
                            'num' => $u_num,
                            'balance' => $u_num,
                            'remarks' => $u_remark,
                            'cid' => $company,
                            'time' => date('Y-m-d H:i:s'),
                            'end_time' => strtotime($end_time . ' 00:00:00'),
                        ];
                        $num += $u_num;
                    } else {
                        $noEmployee[] = $key;
                        continue;
                    }
                }
            }
        }

        $error = empty($error) ? '' : ('第' . implode('、', $error) . '行数据有误，请检查手机号和福利豆额（ 数字型，必须大于0，最多保留两位小数）格式！');
        if (!empty($noEmployee)) {
            if (!empty($error)) {
                $error .= '；';
            }
            $error .= '第' . implode('、', $noEmployee) . '行人员非本企业员工';
        }
        if (!empty($error))
            return Response::show(304, $error);

        if (empty($new_data)) {
            return Response::show(304, '请检查人员信息或公司');
        }
        $all = count($new_data);

        //企业是否发放短信通知：1-通知，0-不通知
        $is_send = 1;
        $permission = M('cor_permission')->where(['cor_id' => $company, 'type' => 0])->find();
        if (!empty($permission)) {
            $detailIds = explode(',', $permission['detail_ids']);
            if (!empty($detailIds)) {
                if (in_array(21, $detailIds)) {
                    $is_send = 0;
                }
            }
        }

        $model = M('mall_wquota_recharge_record','dsy_');
        $model->startTrans();

        $Detail = new MallWquotaDetailModel();
        $Record = new MallWquotaRechargeRecordModel();

        $endTimeS = strtotime($end_time . ' 00:00:00');
        $one = $Record->addPersonalRecord($num, $all, $type, $name, $end_time, $company);

        $addall = true;
        foreach ($datalist as $k => $v) {
            $detailAdd = $Detail->addRecord($v['uid'], 1, $v['num'], $endTimeS, $v['eid'], $v['cid'], $v['remarks'], $one);
            if ($detailAdd == false) {
                $addall = false;
                break;
            }
//            $datalist[$k]['rid'] = $one;
        }
//        $addall = M('mall_wquota_detail','dsy_')->addAll($datalist);

        //添加操作日志
        $admin_log = '企业批量充值福利豆，活动名:' . $name;
        if($one !=false && $addall !=false ){
            $model->commit();

            if ($is_send == 1) {
                $cname = getcnamebycid($company);
                switch ($company) {
                    case 780:
                        $cname = '步科公司';
                        $end_time = '';
                        break;
                }
            }
            //导入成功后发送推送
            $error = 1;
            foreach($new_data as $key=>$value){
                if ($is_send == 1) {
                    $result = SmsModel::sendQuotaSms($value['mobile'], $value['name'], $cname, $value['num'], $end_time);
                    if ($result !== 0) {
                        $error = 0;
                    }
                }
                //重置福利豆提醒已看时间
                resetQuotaDate($value['uid'], $end_time);
            }

            admin_log($admin_log, 1, 'dsy_mall_wquota_recharge_record:' . $one);
            if ($is_send == 1) {
                SmsModel::sendQuotaSmsSafety($num,3,$name);
                if ($error == 0) {
                    return Response::show('200', '导入成功,短信通知失败');
                } else {
                    return Response::show('200', '导入成功,短信通知成功');
                }
            } else {
                SmsModel::sendQuotaSmsSafety($num,3);
                return Response::show('200', '导入成功');
            }
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_mall_wquota_recharge_record');
            return Response::show('400','导入失败,请检查人员信息或公司');
        }
    }



    /**福利豆消费**/

    public function use_wquotal_index(){

        $this->display('wquotal_use_index');
    }

    public function use_list_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $start = I('start1','');
        $end = I('end','');
        $num = I('num','');
        $cname = I('cname','');
        $status = I('type','');
        $model = M('mall_order_notpay','dsy_');
        $where['a.order_type'] = array('eq',2);
        $where['a.isdelete'] = array('eq',2);
        $where['a.quota_type'] = array('eq',1);
        $where['a.status'] = array('eq',2);
        if(!empty($num)){
            $where['a.ordernum'] = array('eq',$num);
        }
        if(!empty($cname)){
            $where['c.corporate_name'] = array('like',"%$cname%");
        }
        if(!empty($status)){
            $where['a.status'] = array('eq',$status);
        }
        if(!empty($start) && !empty($end) ){
            if($start == $end){
                $where['a.time'] = array('like',"%$start%");
            }else{
                $where['a.time'] = array('between',array($start,$end));
            }
        }

        $info = $model
            ->join('as a left join dsy_mall_wquota_use as b on a.ordernum = b.ordernum')
            ->join('left join t_corporate as c on b.cid = c.corporate_id')
            ->field('a.id,a.uid,c.corporate_name,a.ordernum,a.wz_orderid,a.time,a.status,a.total_amount,a.quota_amount,a.receipt_amount,b.eid')
            ->where($where)
            ->order('a.id desc')
            ->page($page,$limit)
            ->select();

        $count = $model
            ->join('as a left join dsy_mall_wquota_use as b on a.ordernum = b.ordernum')
            ->join('left join t_corporate as c on b.cid = c.corporate_id')
            ->field('a.id,a.uid,c.corporate_name,a.ordernum,a.wz_orderid,a.time,a.status,a.total_amount,a.quota_amount,a.receipt_amount,b.eid')
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                if($value['status'] >2){
                    $info[$key]['quota_amount'] = 0;
                }
                if(!empty($value['eid'])){
                    $name = getNameByEid($value['eid']);
                }elseif(!empty($value['uid'])){
                    $name = getUserNmae($value['uid']);
                    if(empty($name)){
                        $where_u['user_id'] = array('eq',$value['uid']);
                        $name = M('user','t_')->where($where_u)->getField('user_name');
                    }
                }
                $info[$key]['name'] = $name;
            }
        }
        return Response::mjson($info,$count);
    }


    public function detail_wquotal_index(){
        $num = I('num','');
        $this->assign('order_num',$num);
        $this->display('wquotal_use_detail');
    }

    /**
     * 详情数据
     **/
    public function detail_info(){
        $order_num = I('num','');
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $where['a.order_notpay_num'] = array('eq',$order_num);
        $info = M('mall_order','dsy_')
            ->join('as a left join dsy_mall_shops as b on a.sid = b.id')
            ->where($where)
            ->field('a.ordernum,a.status,a.price,b.name')
            ->page($page,$limit)
            ->select();
        $count = M('mall_order','dsy_')
            ->join('as a left join dsy_mall_shops as b on a.sid = b.id')
            ->where($where)
            ->count();
        return Response::mjson($info,$count);
    }



    /**
     * 充值汇总
    **/
    public function wquotal_add_all_index(){
        $this->display();
    }


    /**
     * 充值汇总列表数据
    **/
    public function wquotal_add_all_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $cname = trim(I('cname','') );
        if(!empty($cname)){
            $where['b.corporate_name'] = array('like',"%$cname%");
        }
        $model = M('mall_wquota_recharge_record');
        //查询充值记录中有哪些公司
        $info = $model
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->where($where)
            ->field('a.*,b.corporate_name')
            ->page($page,$limit)
            ->group('a.cid')
            ->select();
        $count = count($info);
        if(!empty($info)){
            foreach($info as $key=>$value){
                //查询该企业的充值总额度
                $where_add_all['cid'] = array('eq',$value['cid']);
                $where_add_all['type'] = array('eq',1);
                $add_all = M('mall_wquota_detail')->where($where_add_all)->sum('num');
                //查询所有消费额度
                $where_buy['cid']= array('eq',$value['cid']);
                $buy_all = M('mall_wquota_use')->where($where_buy)->sum('usequota');
                //剩余额度
                $left = $add_all-$buy_all;
                $info[$key]['add_all'] = $add_all;
                $info[$key]['use_all'] = $buy_all;
                $info[$key]['left'] = $left;
            }
        }


        return Response::mjson($info,$count);
    }





    /**
     * 导出充值汇总记录
    **/
    public function output(){
        $id = I('id');
        //根据id获取cid
        $info = M('mall_wquota_recharge_record')->find($id);
        $cid = $info['cid'];

        $where_add_all['cid'] = array('eq',$cid);
        $where_add_all['type'] = array('eq',1);
        $add_all = M('mall_wquota_detail')->where($where_add_all)->sum('num');
        //查询所有消费额度
        $where_buy['cid']= array('eq',$cid);
        $buy_all = M('mall_wquota_use')->where($where_buy)->sum('usequota');
        //剩余额度
        $left = $add_all-$buy_all;

        $all_data = array();
        $xlsCell = array(
            array('id', '序号'),
            array('', '企业名称'),
            array('user_name', '充值总额度'),
            array('status', '兑换状态'),
            array('pid', '兑换套餐'),
            array('address', '收货地址'),
            array('name', '收货人'),
            array('mobile', '联系号码'),
            array('panme', '商品'),
            array('trians', '物流状态'),
        );

        $xlsName = '兑换结果导出';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$all_data);
    }



    /**
     * 充值汇总明细
    **/
    public function add_all_detail(){
        $cid = I('cid','');
        $this->assign('cid',$cid);
        $this->display('wquotal_add_all_detail');
    }

    /**
     * 充值汇总明细列表数据
    **/
    public function add_all_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $cid = I('cid','');
        if(empty($cid)){
            return false;
        }
        $model = M('mall_wquota_recharge_record');
        $where['cid'] = array('eq',$cid);
        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->select();
        $count = $model
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                //单次充值总额
                $all = $value['wquota'] * $value['num'];
                //计算单次消费总额
                $rid = $value['id'];
                $where_r['rid'] = array('eq',$rid);
                $buy = M('mall_wquota_use')->where($where_r)->sum('usequota');
                //单次剩余总额度
                $left = $all-$buy;
                $info[$key]['all'] = $all;
                $info[$key]['buy'] = $buy;
                $info[$key]['left'] = $left;
            }
        }
        return Response::mjson($info,$count);
    }


    /**福利豆使用记录**/
    public function use_wquotal_list()
    {
        $this->display('wquotal_use_list');
    }

    public function use_wquotal_list_page()
    {
        $pageIndex = I('pageIndex', '');
        $page = !empty($pageIndex) ? $pageIndex + 1 : '';
        $limit = !empty($limit) ? $limit : 10;
        $start = I('start1', '');
        $end = I('end', '');
        $num = I('num', '');
        $cname = I('cname', '');

        $where = [];
        if (!empty($cname)) {
            $userIds = M('user', 't_')->where(['user_name' => $cname])->getField('user_id', true);
            if (empty($userIds)) {
                $userIds = [-1];
            }
            $where['uid'] = ['in', $userIds];
        }
        if (!empty($num)) {
            $_string = "`ordernum`='" . $num . "'";
            $orderNos = M('mall_order')->where(['ordernum' => $num])->getField('order_notpay_num', true);
            if (!empty($orderNos)) {
                $_string .= " OR `ordernum` IN('" . implode("','", $orderNos) . "')";
            }
            $where['_string'] = $_string;
        }
        if (!empty($start)) {
            $where['time'][] = ['egt', $start . ' 00:00:00'];
        }
        if (!empty($end)) {
            $where['time'][] = ['elt', $end . ' 23:59:59'];
        }

        $model = M('mall_wquota_use');
        $count = $model
            ->where($where)
            ->count();

        $list = [];
        if ($count > 0) {
            $list = $model
                ->where($where)
                ->order('id desc')
                ->page($page, $limit)
                ->select();
            foreach ($list as $k => $info) {
                $list[$k]['name'] = M('user', 't_')->where(['user_id' => $info['uid']])->getField('user_name');
                $list[$k]['type'] = formatOrderType($info['utype']);
                if ($info['utype'] > 0) {
                    $o_where = ['order_no' => $info['ordernum']];
                    $field = '`order_status`,`pay_status`,`refund_status`,`total_money` as total_amount,`rest_money` as receipt_amount';
                    switch ($info['utype']) {
                        case 2:
                        case 3:
                            $order_model = M('mobile_order');
                            break;
                        case 4:
                            $order_model = M('oil_order');
                            break;
                        case 5:
                            $order_model = M('traffic_order');
                            break;
                        case 6:
                            $order_model = M('ticket_order');
                            $field = '`order_status`,`pay_status`,`total_money` as total_amount,`rest_money` as receipt_amount';
                            break;
                        case 7:
                            $order_model = M('didi_order');
                            $field = '`order_status`,`pay_status`,`total_money` as total_amount,`rest_money` as receipt_amount';
                            break;
                        case 8:
                        case 9:
                        case 10:
                        case 11:
                        case 12:
                        case 13:
                        case 14:
                        case 15:
                            $order_model = M('meituan_order');
                            $field = '`order_status`,`pay_status`,`refund_status`,`total_money` as total_amount,`rest_money` as receipt_amount';
                            break;
                        case 16:
                            $pay_info = M('tn_order_pay')->where(['pay_no' => $info['ordernum']])->find();
                            $order_model = M('tn_order');
                            $o_where = ['id' => $pay_info['order_id']];
                            $field = '`order_status`,`pay_money`,`refund_status`,`total_money` as total_amount';
                            break;
                        default:
                            $order_model = M('wartercoal_order');
                            break;
                    }
                    $order_info = $order_model
                        ->where($o_where)
                        ->field($field)
                        ->find();
                    switch ($info['utype']) {
                        case 16:
                            if ($pay_info['third_pay_no'] != 1) {
                                $list[$k]['usequota'] = 0;
                            }
                            $order_info['total_amount'] = $pay_info['pay_money'];
                            $order_info['receipt_amount'] = $pay_info['pay_rest'];
                            break;
                        case 6|7:
                            if ($order_info['order_status'] == 2
                                || ($order_info['order_status'] == 0 && $order_info['pay_status'] != 1)) {
                                $list[$k]['usequota'] = 0;
                            }
                            break;
                        default:
                            if ($order_info['order_status'] == 2
                                || ($order_info['order_status'] == 0 && $order_info['pay_status'] != 2)
                                || ($order_info['order_status'] == 1 && $order_info['refund_status'] != 0)) {
                                $list[$k]['usequota'] = 0;
                            }
                            break;
                    }
                } else {
                    $order_count = strlen($info['ordernum']);
                    if($order_count == 16){
                        $order_info = M('sn_order')
                                        ->where(['order_num' => $info['ordernum']])
                                        ->field('order_num,status,total_fee,total_freight,order_fee as receipt_amount')
                                        ->find();
                        $order_info['total_amount'] = $order_info['total_freight'] + $order_info['total_fee'];
                        if ($order_info['status'] == 7) {
                            $list[$k]['usequota'] = 0;
                        }
                        $list[$k]['ordernum'] = $order_info['order_num'];
                    }else{
                        $order_info = M('mall_order_notpay')
                                        ->where(['ordernum' => $info['ordernum']])
                                        ->field('`status`,`total_amount`,`receipt_amount`')
                                        ->find();
                        if ($order_info['status'] > 2) {
                            $list[$k]['usequota'] = 0;
                        }
                        $orderNos = M('mall_order')->where(['order_notpay_num' => $info['ordernum']])->getField('ordernum', true);
                        $list[$k]['ordernum'] = implode(',', array_unique(array_filter($orderNos)));
                    }
                }
                $list[$k]['total_amount'] = $order_info['total_amount'];
                $list[$k]['receipt_amount'] = $order_info['receipt_amount'];
            }
        }

        return Response::mjson($list, $count);
    }

    /**
     * 福利豆使用记录详情
     **/
    public function use_wquotal_detail()
    {
        $id = I('id', '');
        $this->assign('id', $id);
        $this->display('wquotal_use_order');
    }

    public function use_wquotal_detail_page()
    {
        $id = I('id', '');

        $info = M('mall_wquota_use')
            ->where(['id' => $id])
            ->find();

        $o_where = ['order_no' => $info['ordernum']];
        if ($info['utype'] > 0) {
            switch ($info['utype']) {
                case 2://话费
                case 3://流量
                    $order_model = M('mobile_order');
                    $field = '`order_no` as ordernum,`bill_status`,`order_status`,`pay_status`,`refund_status`,`total_money` as price,`item_name` as name';
                    break;
                case 4://加油卡
                    $order_model = M('oil_order');
                    $field = '`order_no` as ordernum,`bill_status`,`order_status`,`pay_status`,`refund_status`,`total_money` as price,`account` as name';
                    break;
                case 5://交通罚款
                    $order_model = M('traffic_order');
                    $field = '`order_no` as ordernum,`bill_status`,`order_status`,`pay_status`,`refund_status`,`total_money` as price,`fine_no` as name';
                    break;
                case 6://购买火车票
                    $order_model = M('ticket_order');
                    $field = '`order_no` as ordernum,`bill_status`,`order_status`,`pay_status`,`total_money` as price,`start_station`,`end_station`';
                    break;
                case 7://滴滴打车
                    $order_model = M('didi_order');
                    $field = '`order_no` as ordernum,`order_status`,`pay_status`,`total_money` as price,`start_name`,`end_name`';
                    break;
                case 8://美团外卖
                case 9://猫眼电影
                case 10://门票
                case 11://酒店
                case 12://美食
                case 13://休闲娱乐
                case 14://生活娱乐
                case 15://丽人
                    $order_model = M('meituan_order');
                    $field = '`order_no` as ordernum,`order_status`,`pay_status`,`refund_status`,`total_money` as price,`sqt_serial_num`';
                    break;
                case 16:
                    $pay_info = M('tn_order_pay')->where(['pay_no' => $info['ordernum']])->find();
                    $order_model = M('tn_order');
                    $o_where = ['id' => $pay_info['order_id']];
                    $field = '`book_name`,`depart_name`,`dest_name`,`depart_date`';
                    break;
                default://生活缴费
                    $order_model = M('wartercoal_order');
                    $field = '`order_no` as ordernum,`bill_status`,`order_status`,`pay_status`,`refund_status`,`total_money` as price,`unit_name` as name';
                    break;
            }
            $list = $order_model
                ->where($o_where)
                ->field($field)
                ->select();
            foreach ($list as $k => $order_info) {
                switch ($info['utype']) {
                    case 16:
                        switch ($pay_info['pay_status']) {
                            case 0:
                                $status = '待支付';
                                break;
                            case 1:
                                $status = '已支付';
                                break;
                            case 2:
                                $status = '已退款';
                                break;
                            default://未充值
                                $status = '--';
                                break;
                        }
                        $list[$k]['name'] = '途牛（' . $order_info['depart_name'] . ' 至 ' . $order_info['dest_name'] . '，团期：' . $order_info['depart_date'] . '）';
                        $list[$k]['price'] = $pay_info['pay_money'];
                        $list[$k]['ordernum'] = $pay_info['pay_no'];
                        break;
                    case 6:
                        switch ($order_info['bill_status']) {
                            case 0://预定中
                                $status = '预定中';
                                break;
                            case 1://已完成
                                $status = '已完成';
                                break;
                            case 2://待付款
                                $status = '待付款';
                                break;
                            case 9://已取消
                                $status = '已取消';
                                break;
                            default://未充值
                                $status = '--';
                                break;
                        }
                        $list[$k]['name'] = '火车票（' . $order_info['start_station'] . ' 至 ' . $order_info['end_station'] . '）';
                        break;
                    case 7:
                        if ($order_info['pay_status'] == 1) {
                            $status = '付款成功';
                        } else {
                            $status = '待付款';
                        }
                        $list[$k]['name'] = '滴滴打车（' . $order_info['start_name'] . ' 至 ' . $order_info['end_name'] . '）';
                        break;
                    case 8:
                        switch ($order_info['order_status']) {
                            case 2:
                                $status = '已取消';
                                break;
                            default:
                                $status = '---';
                                if ($order_info['order_status'] == 0 && $order_info['pay_status'] == 0) {
                                    $status = '待支付';
                                }
                                if ($order_info['pay_status'] == 1 && $order_info['refund_status'] == 0) {
                                    $status = '已支付';
                                }
                                if ($order_info['refund_status'] == 1) {
                                    $status = '已退款';
                                }
                                break;
                        }
                        $list[$k]['name'] = '美团支付序列号' . $order_info['sqt_serial_num'];
                        break;
                    default:
                        switch ($order_info['bill_status']) {
                            case 0://充值中
                                $status = '充值中';
                                break;
                            case 1://充值成功
                                $status = '充值成功';
                                break;
                            default://未充值
                                if ($order_info['order_status'] == 2) {
                                    $status = '已取消';
                                } else {
                                    $status = '充值失败';//充值失败 已退款
                                    if ($order_info['pay_status'] != 2 && $order_info['refund_status'] == 0) {
                                        $status = '待付款';//未付款 未退款
                                    }
                                }
                                break;
                        }
                        break;
                }
                $list[$k]['status'] = $status;
            }
        } else {
            $count = strlen($info['ordernum']);
            if($count == 16){
                $sn_state = [0=>'待付款',1=>'待发货',2=>'待收货',3=>'交易成功',4=>'已取消',5=>'交易关闭',6=>'待处理',7=>'已取消',9=>'评价完成'];
                $list = M('sn_order')
                    ->where(['order_num' => $info['ordernum']])
                    ->field('order_num as ordernum,status,total_fee,total_freight')
                    ->select();
                foreach($list as $k => $v){
                    $list[$k]['price'] = $v['total_fee'] + $v['total_freight'];
                    $list[$k]['name'] = '苏宁';
                    $list[$k]['status'] = $sn_state[$v['status']];
                }
            }else{
                $list = M('mall_order')
                    ->join('as a left join dsy_mall_shops as b on a.sid = b.id')
                    ->where(['a.order_notpay_num' => $info['ordernum']])
                    ->field('a.ordernum,a.status,a.price,b.name')
                    ->select();
                foreach ($list as $k => $order_info) {
                    switch ($order_info['status']) {
                        case 1:
                            $status = '未付款';
                            break;
                        case 2:
                            $status = '已付款';
                            break;
                        case 3:
                            $status = '已取消';
                            break;
                        case 4:
                            $status = '已发货';
                            break;
                        case 5:
                            $status = '已收货';
                            break;
                        case 6:
                            $status = '全部退款';
                            break;
                        case 7:
                            $status = '评价完成';
                            break;
                        case 8:
                            $status = '全部退货';
                            break;
                        default:
                            $status = '';
                            break;
                    }
                    $list[$k]['status'] = $status;
                }
            }
        }

        return Response::mjson($list, count($list));
    }

    public function refundQuotaPage()
    {
        $this->display();
    }

    public function refundQuotaList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $title = I('title', '');
        $order_no = I('order_no', '');
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');

        $where = [
            'type' => 2
        ];
        if (!empty($title)) {
            $userIds = M('user', 't_')->where(['user_name' => $title])->getField('user_id', true);
            if (empty($userIds)) {
                $userIds = [-1];
            }
            $where['user_id'] = ['in', $userIds];
        }
        if (!empty($order_no)) {
            $where['order_no'] = $order_no;
        }
        if (!empty($startDate)) {
            $where['create_date'][] = ['egt', $startDate];
        }
        if (!empty($endDate)) {
            $where['create_date'][] = ['elt', $endDate];
        }

        $logModel = M('log_quota');
        $list = $logModel
            ->where($where)
            ->page($page, $limit)
            ->order('id desc')
            ->select();
        $count = $logModel
            ->where($where)
            ->count();

        $userModel = M('user', 't_');
        $useModel = M('mall_wquota_use');
        foreach ($list as $k => $v) {
            $list[$k]['user_name'] = $userModel->where(['user_id' => $v['user_id']])->getField('user_name');
            $useInfo = $useModel->where(['id' => $v['data_id']])->field('ordernum,utype')->find();
            $list[$k]['typeName'] = formatOrderType($useInfo['utype']);
        }

        return Response::mjson($list, $count);
    }

}