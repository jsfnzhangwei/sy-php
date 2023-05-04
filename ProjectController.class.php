<?php
/**
 * Created by PhpStorm.
 * User: 苏鹰-马洁
 * Date: 2018/12/28
 * Time: 10:55
 */

namespace Admin\Controller;
use Org\Util\Response;

class ProjectController extends CommonController
{
    public function configPage()
    {
        $this->display();
    }

    public function configEditPage()
    {
        $rid = (int)I('rid', 0);
        $this->assign('rid', $rid);
        $admin_id = intval(M('admin')->where(['username' => $_COOKIE['username']])->getField('id'));
        if ($admin_id <= 0)
            $admin_id = 1;
        $this->assign('admin_id', $admin_id);
        $this->display();
    }

    public function valuePage()
    {
        $this->display();
    }

    public function userPage()
    {
        $this->display();
    }

    public function editNumPage()
    {
        $mobile = I('mobile', '');
        $ocrStatus = I('ocrStatus', 0);
        $bankStatus = I('bankStatus', 0);
        $this->assign('mobile', $mobile);
        $this->assign('ocrStatus', $ocrStatus);
        $this->assign('bankStatus', $bankStatus);
        $this->display();
    }
    /**
     * 慧薪福-企业管理
     */
    public function company_index(){
        $company_count = M('company','t_','db_hxf')->count();
        $staff_count = 200;
        $this->assign('company_count',$company_count);
        $this->assign('staff_count',$staff_count);
        $this->display();
    }
    /**
     * 慧薪福-企业列表数据
     */
    public function company_list_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $company_name = I('company_name');
        $contacts_phone = I('contacts_phone');

        if(!empty($company_name)){
            $where['company_name'] = array('like',"%$company_name%");
        }
        if(!empty($contacts_phone)){
            $where['contacts_phone'] = array('eq',$contacts_phone);
        }
        $model = M('company','t_','db_hxf');
        $result = $model->where($where)->page($page,$limit)->order('create_time desc')->select();
        foreach($result as $key => $val){
            $result[$key]['company_industry'] = empty($val['company_industry']) ? '': company_industry($val['company_industry']);
            $result[$key]['company_type'] = empty($val['company_type']) ? '' : company_type($val['company_type']);
            $result[$key]['company_scale'] = empty($val['company_scale']) ? '': company_scale($val['company_scale']);
        }

        $count = $model->where($where)->count();

        return Response::mjson($result,$count);
    }
    /**
     * 慧薪福-企业注册
     */
    public function company_index_add(){
        $company_industry = company_industry();
        $number = company_scale();
        $company_type = company_type();
        $this->assign('company_industry',$company_industry);
        $this->assign('company_type',$company_type);
        $this->assign('number',$number);
        $this->display();
    }
    /**
     * 慧薪福-添加企业
     */
    public function company_insert(){
        $company_name = str_replace(' ','',I('company_name'));
        $contacts_name = str_replace(' ','',I('contacts_name'));
        $contacts_phone = str_replace(' ','',I('contacts_phone'));
        $password = str_replace(' ','',I('password'));
        $company_type = I('company_type');
        $company_industry = I('company_industry');
        $company_scale = I('company_scale');
        $company_legal_person = str_replace(' ','',I('company_legal_person'));
        $service_charge_rate = str_replace(' ','',I('service_charge_rate'));
        $contract_path = I('contract_path_url');
        $contract_name = I('contract_path_name');
        $business_license = I('business_license_url');
        $taxpayer_name = I('taxpayer_name');
        $taxpayers_registration_no = I('taxpayers_registration_no');
        $address = I('address');
        $phone = I('phone');
        $bank_name = I('bank_name');
        $bank_code = I('bank_code');

        $model = M('company','t_','db_hxf');
        if(empty($company_name)){
            return Response::show(300,'企业名称为必填项，请输入');
        }
        if(empty($contacts_name)){
            return Response::show(300,'联系人姓名为必填项，请输入');
        }
        if(empty($contacts_phone)){
            return Response::show(300,'联系人手机/账号为必填项，请输入');
        }
        if (check_mobile($contacts_phone) == 0) {
            return Response::show(300, '手机号码格式不正确，请重新输入');
        }
        $check_phone = $model->where(['contacts_phone'=>$contacts_phone,'is_del'=>0])->count();
        if($check_phone > 0){
            return Response::show(300,'联系人手机号已注册');
        }
        if(empty($password)){
            return Response::show(300,'登陆密码为必填项，请输入');
        }
        if (preg_match("/[\x7f-\xff]/",$password)) {
            return Response::show(300,'登陆密码中不能有汉字，请重新输入');
        }
        if(empty($service_charge_rate)){
            return Response::show(300,'服务费率不能为空，请输入');
        }
        if(empty($business_license)){
            return Response::show(300,'营业执照不能为空');
        }
        $data = [
            'company_name' => $company_name,
            'contacts_name' => $contacts_name,
            'contacts_phone' => $contacts_phone,
            'password' => md5(MD5_PREFIX . md5($password)),
            'company_type' => $company_type,
            'company_industry' => $company_industry,
            'company_scale' => $company_scale,
            'company_legal_person' => $company_legal_person,
            'service_charge_rate' => $service_charge_rate,
            'contract_path' => $contract_path,
            'contract_name' => $contract_name,
            'business_license' => $business_license,
            'create_time' => date('Y-m-d H:i:s',time())
        ];

        $user_model = M('user','t_','db_hxf');
        $user_id = $user_model->field('user_id')->where(['user_name'=>$contacts_phone])->find();
        if(empty($user_id)){
            $user_data = [
                'user_name' => $contacts_phone,
                'password' => md5(MD5_PREFIX . md5(MD5_PREFIX . substr($contacts_phone,5,6)))
            ];
            $user_id['user_id'] = $user_model->add($user_data);
        }
        $data['user_id'] = $user_id['user_id'];
        $result = $model->add($data);
        $admin_log = '注册新企业账户。企业名称：' . $company_name;
        if($result){
            $data2 = [
                'name' => $taxpayer_name,
                'taxpayers_registration_no' => $taxpayers_registration_no,
                'address' => $address,
                'phone' => $phone,
                'bank_name' => $bank_name,
                'bank_code' => $bank_code,
                'is_del' => 1,
                'company_id' => $result
            ];
            M('detail','company_','db_hxf')->add($data2);
            admin_log($admin_log, 1, 't_company:' . $result);
            return Response::show(200,'企业注册成功');
        }else{
            admin_log($admin_log, 0, 't_company:' . $result);
            return Response::show(300,'企业注册失败');
        }
    }
    /**
     * 慧薪福启用
     */
    public function company_open_stop(){
        $id = I('id');
        $status = I('status');//0是启用  1是禁用
        if(empty($id) || $status == ''){
            return Response::show(300,'缺少参数');
        }
        $model = M('company','t_','db_hxf');
        $company_name = $model->field('company_name')->where(['id'=>$id])->find();
        $msg = '';
        $admin_log = '';
        if($status == 0){
            $msg = '启用';
            $admin_log = '启用企业账户。企业名称：' . $company_name['company_name'];
        }else{
            $msg = '禁用';
            $admin_log = '禁用企业账户。企业名称：' . $company_name['company_name'];
        }
        $result = $model->where(['id'=>$id])->setField(['status'=>$status]);
        if($result){
            admin_log($admin_log, 1, 't_company:' . $id);
            return Response::show(200,$msg . '成功');
        }else{
            admin_log($admin_log, 0, 't_company:' . $id);
            return Response::show(300,$msg . '失败');
        }
    }
    /**
     * 慧薪福-修改企业信息首页
     */
    public function company_index_edit(){
        $id = I('id');
        $result = M('company','t_','db_hxf')
                    ->alias('a')
                    ->field('a.*,b.name,b.taxpayers_registration_no,b.address,b.phone,b.bank_name,b.bank_code')
                    ->join('left join company_detail as b on a.id = b.company_id')
                    ->where(['a.id'=>$id])
                    ->find();
        $company_industry = company_industry();
        $number = company_scale();
        $company_type = company_type();
        $this->assign('company_industry',$company_industry);
        $this->assign('number',$number);
        $this->assign('company_type',$company_type);
        $this->assign('result',$result);
        $this->display();
    }
    /**
     * 慧薪福-修改
     */
    public function company_save(){
        $id = I('id');
        $company_name = str_replace(' ','',I('company_name'));
        $contacts_name = str_replace(' ','',I('contacts_name'));
        $contacts_phone = str_replace(' ','',I('contacts_phone'));
        $password = str_replace(' ','',I('password'));
        $company_type = I('company_type');
        $company_industry = I('company_industry');
        $company_scale = I('company_scale');
        $company_legal_person = str_replace(' ','',I('company_legal_person'));
        $service_charge_rate = str_replace(' ','',I('service_charge_rate'));
        $contract_path = I('contract_path_url');
        $contract_name = I('contract_path_name');
        $business_license = I('business_license_url');
        $taxpayer_name = I('taxpayer_name');
        $taxpayers_registration_no = I('taxpayers_registration_no');
        $address = I('address');
        $business_license_phone = I('phone');
        $bank_name = I('bank_name');
        $bank_code = I('bank_code');

        $model = M('company','t_','db_hxf');
        $model->startTrans();
        if(empty($company_name)){
            return Response::show(300,'企业名称为必填项，请输入');
        }
        if(empty($contacts_name)){
            return Response::show(300,'联系人姓名为必填项，请输入');
        }
        if(empty($contacts_phone)){
            return Response::show(300,'联系人手机/账号为必填项，请输入');
        }
        if (check_mobile($contacts_phone) == 0) {
            return Response::show(300, '手机号码格式不正确，请重新输入');
        }
        $check_phone = $model->where(['contacts_phone'=>$contacts_phone,'id'=>array('neq',$id),'is_del'=>0])->count();
        if($check_phone > 0){
            return Response::show(300,'联系人手机号已注册');
        }
        if(empty($service_charge_rate)){
            return Response::show(300,'服务费率为必填项，请输入');
        }
        if(!empty($password)){
            if(strlen($password) < 6){
                return Response::show(300,'登陆密码不得小于6字符，请重新输入');
            }
            if (preg_match("/[\x7f-\xff]/",$password)) {
                return Response::show(300,'登陆密码中不能有汉字，请重新输入');
            }
            $password = md5(MD5_PREFIX . md5($password));
        }
        $data = [
            'company_name' => $company_name,
            'contacts_name' => $contacts_name,
            'company_type' => $company_type,
            'company_industry' => $company_industry,
            'company_scale' => $company_scale,
            'company_legal_person' => $company_legal_person,
            'service_charge_rate' => $service_charge_rate,
            'contract_path' => $contract_path,
            'contract_name' => $contract_name,
            'business_license' => $business_license,
        ];
        if(!empty($password)){
            $data['password'] = $password;
        }
        $phone = $model->field('contacts_phone')->where(['id'=>$id])->find();
        if($phone['contacts_phone'] != $contacts_phone){
            $user_model = M('user','t_','db_hxf');
            $user_id = $user_model->field('user_id')->where(['user_name'=>$contacts_phone])->find();
            if(empty($user_id)){
                $user_data = [
                    'user_name' => $contacts_phone,
                    'password' => md5(MD5_PREFIX . md5(MD5_PREFIX . substr($contacts_phone,5,6)))
                ];
                $user_id['user_id'] = $user_model->add($user_data);
            }
            $data['user_id'] = $user_id['user_id'];
            $data['contacts_phone'] = $contacts_phone;
        }
        $result = $model->where(['id'=>$id])->save($data);
        $data2 = [
            'name' => $taxpayer_name,
            'taxpayers_registration_no' => $taxpayers_registration_no,
            'address' => $address,
            'phone' => $business_license_phone,
            'bank_name' => $bank_name,
            'bank_code' => $bank_code,
        ];
        $check_business = M('detail','company_','db_hxf')->where(['company_id'=>$id])->find();
        if(empty($check_business)){
            $data2['company_id'] = $id;
            $result2 = M('detail','company_','db_hxf')->add($data2);
        }else{
            $result2 = M('detail','company_','db_hxf')->where(['company_id'=>$id])->save($data2);
        }
        //同步公司信息到一键发薪平台
        $s_company = M('company','s_','db_salary')->where(['id'=>$id])->count();
        if($s_company > 0){
            $s_result = M('company','s_','db_salary')->where(['id'=>$id])->save($data);
            $s_data = [];
            if(!empty($data['password'])){
                $s_data['password'] = $data['password'];
            }
            if($phone['contacts_phone'] != $contacts_phone){
                $s_data['username'] = $data['contacts_phone'];
                $s_data['user_id'] = $data['user_id'];
            }
            if(!empty($s_data)){
                $s_user_result = M('user','sys_','db_salary')->where(['username'=>$phone['contacts_phone']])->save($s_data);
            }
        }
        $admin_log = '修改企业信息。企业名称 ' . $company_name;
        if($result !== false){
            $model->commit();
            admin_log($admin_log, 1, 't_company:' . $id);
            return Response::show(200,'企业信息修改成功');
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 't_company:' . $id);
            return Response::show(300,'企业信息修改失败');
        }
    }
    public function company_contract_upload(){
        //文件路径
        $files = $_FILES['contract_path'];
        $upload = new \Think\Upload();// 实例化上传类
	    $upload->maxSize   =     10485760 ;// 设置附件上传大小
	    $upload->exts      =     array('pdf');// 设置附件上传类型
	    $upload->rootPath  =     './'; // 设置附件上传根目录
        $upload->savePath  =     'Public/Uploads/contract/'; // 设置附件上传（子）目录
        $upload->subName   =     array('date','Ymd');
	    $info   =   $upload->uploadOne($files);
	    if(!$info) {
            // 上传错误提示错误信息
            $error = $upload->getError();
            if($error == '上传文件后缀不允许'){
                $error = "上传文件后缀只允许PDF";
            }
            if($error == "上传文件大小不符！"){
                $error = "文件大小超过5M，请重新上传";
            }
            return Response::show(300,$error); 
		}else{
			// 上传成功 获取上传文件信息
            $data['contract_path'] = WEB_URL . "/dashengyun/" . $info['savepath'].$info['savename'];
            $data['contract_name'] = $files['name'];
            return Response::show(200,$data);
		}
    }


    /**
     * 畅安通-企业管理
     */
    public function company_cat_index(){
        $this->display();
    }
    /**
     * 畅安通-企业列表数据
     */
    public function company_cat_list_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $company_name = I('company_name');
        $contacts_phone = I('contacts_phone');
        $url = CAT_URL . "/getCompanyList";
        $where = [
            'pageNum' => $pageIndex + 1,
            'pageSize' => $limit,
            'companyName' => $company_name,
            'contactsPhone' => $contacts_phone,
        ];
        $result = text_curl($url,$where);
        $data = $result['data']['items'];
        foreach($data as $key => $val){
            $data[$key]['companyIndustry'] = empty($val['companyIndustry']) ? '': company_industry($val['companyIndustry']);
            $data[$key]['companyType'] = empty($val['companyType']) ? '' : company_type($val['companyType']);
            $data[$key]['companyScale'] = empty($val['companyScale']) ? '': company_scale($val['companyScale']);
        }

        return Response::mjson($data,$result['data']['total']);
    }
    /**
     * 畅安通-企业注册
     */
    public function company_cat_index_add(){
        $company_industry = company_industry();
        $number = company_scale();
        $company_type = company_type();
        $this->assign('company_industry',$company_industry);
        $this->assign('company_type',$company_type);
        $this->assign('number',$number);
        $this->display();
    }
    /**
     * 畅安通 -添加企业
     */
    public function company_cat_insert(){
        $company_name = str_replace(' ','',I('company_name'));
        $contacts_name = str_replace(' ','',I('contacts_name'));
        $contacts_phone = str_replace(' ','',I('contacts_phone'));
        $password = str_replace(' ','',I('password'));
        $company_type = I('company_type');
        $company_industry = I('company_industry');
        $company_scale = I('company_scale');
        $company_legal_person = str_replace(' ','',I('company_legal_person'));
        $legal_person_card_id = str_replace(' ','',I('legal_person_card_id'));
        $legal_person_card_front_img = I('front_img_url');
        $legal_person_card_back_img = I('back_img_url');
        $service_charge_rate = str_replace(' ','',I('service_charge_rate'));
        $contract_path = I('contract_path_url');
        $contract_name = I('contract_path_name');
        $business_license = I('business_license_url');
        $taxpayer_name = I('taxpayer_name');
        $taxpayers_registration_no = I('taxpayers_registration_no');
        $address = I('address');
        $phone = I('phone');
        $bank_name = I('bank_name');
        $bank_code = I('bank_code');
        $name = I('name');
        $cardId = I('cardId');

        $personDJG = [];
        foreach($name as $key => $val){
            $personDJG[$key]['name'] = $val;
            $personDJG[$key]['cardId'] = $cardId[$key];
        }
        if(empty($company_name)){
            return Response::show(300,'企业名称为必填项，请输入');
        }
        if(empty($contacts_name)){
            return Response::show(300,'联系人姓名为必填项，请输入');
        }
        if(empty($contacts_phone)){
            return Response::show(300,'联系人手机/账号为必填项，请输入');
        }
        if (check_mobile($contacts_phone) == 0) {
            return Response::show(300, '手机号码格式不正确，请重新输入');
        }
        if(empty($password)){
            return Response::show(300,'登陆密码为必填项，请输入');
        }
        if (preg_match("/[\x7f-\xff]/",$password)) {
            return Response::show(300,'登陆密码中不能有汉字，请重新输入');
        }
        if(empty($service_charge_rate)){
            return Response::show(300,'服务费率不能为空，请输入');
        }
        $url = CAT_URL . "/insertOrUpdateCompany";
        $data = [
            'companyName' => $company_name,
            'contactsName' => $contacts_name,
            'contactsPhone' => $contacts_phone,
            'password' => md5(MD5_PREFIX . md5($password)),
            'companyType' => $company_type,
            'companyIndustry' => $company_industry,
            'companyScale' => $company_scale,
            'companyLegalPerson' => $company_legal_person,
            'legalPersonCardId' => $legal_person_card_id,
            'legalPersonCardFrontImg' => $legal_person_card_front_img,
            'legalPersonCardBackImg' => $legal_person_card_back_img,
            'serviceChargeRate' => $service_charge_rate,
            'contractPath' => $contract_path,
            'contractName' => $contract_name,
            'businessLicense' => $business_license,
            'name' => $taxpayer_name,
            'taxpayersRegistrationNo' => $taxpayers_registration_no,
            'address' => $address,
            'phone' => $phone,
            'bankName' => $bank_name,
            'bankCode' => $bank_code,
            'personOfDJG' => json_encode($personDJG)
        ];
        $result = text_curl($url,$data);
        $admin_log = '注册畅安通新企业账户。企业名称：' . $company_name;
        if($result['code'] == 200){
            admin_log($admin_log, 1, 't_company:');
            return Response::show(200,'企业注册成功');
        }else{
            admin_log($admin_log, 0, 't_company:');
            return Response::show(300,'企业注册失败');
        }
    }
    /**
     * 畅安通启用
     */
    public function company_cat_open_stop(){
        $id = I('id');
        $status = I('status');//0是启用  1是禁用
        if(empty($id) || $status == ''){
            return Response::show(300,'缺少参数');
        }
        $model = M('company','t_','db_cat_hxf');
        $company_name = $model->field('company_name')->where(['id'=>$id])->find();
        $msg = '';
        $admin_log = '';
        if($status == 0){
            $msg = '启用';
            $admin_log = '启用畅安通企业账户。企业名称：' . $company_name['company_name'];
        }else{
            $msg = '禁用';
            $admin_log = '禁用畅安通企业账户。企业名称：' . $company_name['company_name'];
        }
        $result = $model->where(['id'=>$id])->setField(['status'=>$status]);
        if($result){
            admin_log($admin_log, 1, 't_company:' . $id);
            return Response::show(200,$msg . '成功');
        }else{
            admin_log($admin_log, 0, 't_company:' . $id);
            return Response::show(300,$msg . '失败');
        }
    }
    /**
     * 畅安通-修改企业信息首页
     */
    public function company_cat_index_edit(){
        $id = I('id');
        $url = CAT_URL . "/getCompany";
        $where = [
            'id' => $id
        ];
        $data = text_curl($url,$where);
        if($data['code'] == 200){
            $result = $data['data'];
            $result['personOfDJG'] = json_decode($result['personOfDJG'],true);
            $num = count($result['personOfDJG']);
            $company_industry = company_industry();
            $number = company_scale();
            $company_type = company_type();
            $this->assign('company_industry',$company_industry);
            $this->assign('number',$number);
            $this->assign('company_type',$company_type);
            $this->assign('result',$result);
            $this->assign('num',$num);
            $this->display();
        }
    }
    /**
     * 畅安通-修改
     */
    public function company_cat_save(){
        $id = I('id');
        $company_name = str_replace(' ','',I('company_name'));
        $contacts_name = str_replace(' ','',I('contacts_name'));
        $contacts_phone = str_replace(' ','',I('contacts_phone'));
        $password = str_replace(' ','',I('password'));
        $company_type = I('company_type');
        $company_industry = I('company_industry');
        $company_scale = I('company_scale');
        $company_legal_person = str_replace(' ','',I('company_legal_person'));
        $legal_person_card_id = str_replace(' ','',I('legal_person_card_id'));
        $legal_person_card_front_img = I('front_img_url');
        $legal_person_card_back_img = I('back_img_url');
        $service_charge_rate = str_replace(' ','',I('service_charge_rate'));
        $contract_path = I('contract_path_url');
        $contract_name = I('contract_path_name');
        $business_license = I('business_license_url');
        $taxpayer_name = I('taxpayer_name');
        $taxpayers_registration_no = I('taxpayers_registration_no');
        $address = I('address');
        $phone = I('phone');
        $bank_name = I('bank_name');
        $bank_code = I('bank_code');
        $name = I('name');
        $cardId = I('cardId');

        $personDJG = [];
        foreach($name as $key => $val){
            $personDJG[$key]['name'] = $val;
            $personDJG[$key]['cardId'] = $cardId[$key];
        }
        if(empty($company_name)){
            return Response::show(300,'企业名称为必填项，请输入');
        }
        if(empty($contacts_name)){
            return Response::show(300,'联系人姓名为必填项，请输入');
        }
        if(empty($contacts_phone)){
            return Response::show(300,'联系人手机/账号为必填项，请输入');
        }
        if (check_mobile($contacts_phone) == 0) {
            return Response::show(300, '手机号码格式不正确，请重新输入');
        }
        if(empty($service_charge_rate)){
            return Response::show(300,'服务费率为必填项，请输入');
        }
        if(!empty($password)){
            if(strlen($password) < 6){
                return Response::show(300,'登陆密码不得小于6字符，请重新输入');
            }
            if (preg_match("/[\x7f-\xff]/",$password)) {
                return Response::show(300,'登陆密码中不能有汉字，请重新输入');
            }
            $password = md5(MD5_PREFIX . md5($password));
        }
        $url = CAT_URL . "/insertOrUpdateCompany";
        $data = [
            'id' => $id,
            'companyName' => $company_name,
            'contactsPhone' => $contacts_phone,
            'contactsName' => $contacts_name,
            'companyType' => $company_type,
            'companyIndustry' => $company_industry,
            'companyScale' => $company_scale,
            'companyLegalPerson' => $company_legal_person,
            'legalPersonCardId' => $legal_person_card_id,
            'legalPersonCardFrontImg' => $legal_person_card_front_img,
            'legalPersonCardBackImg' => $legal_person_card_back_img,
            'serviceChargeRate' => $service_charge_rate,
            'contractPath' => $contract_path,
            'contractName' => $contract_name,
            'businessLicense' => $business_license,
            'name' => $taxpayer_name,
            'taxpayersRegistrationNo' => $taxpayers_registration_no,
            'address' => $address,
            'phone' => $phone,
            'bankName' => $bank_name,
            'bankCode' => $bank_code,
            'personOfDJG' => json_encode($personDJG)
        ];
        if(!empty($password)){
            $data['password'] = $password;
        }
        $result = text_curl($url,$data);
        $admin_log = '修改畅安通企业信息。企业名称 ' . $company_name;
        if($result['code'] == 200){
            admin_log($admin_log, 1, 't_company:' . $id);
            return Response::show(200,'企业信息修改成功');
        }else{
            admin_log($admin_log, 0, 't_company:' . $id);
            return Response::show(300,'企业信息修改失败');
        }
    }

    //慧薪福消息通知
    public function company_operation_log(){
        $this->display();
    }
    public function company_operation_list(){
        $type = I('type');
        $this->assign('type',$type);
        $this->display();
    }
    public function company_operation_data(){
        $type = I('type');
        $pageIndex = $_REQUEST['pageIndex'];
        $limit = 10;
        $page = $pageIndex+1;
        $start_time = I('StartTime');
        $end_time = I('EndTime');

        if($type == 1){
            if(!empty($start_time)&&!empty($end_time)){
                if($start_time==$end_time){
                    $where['create_date'] = array('like',"%$start_time%");
                }else{
                    $start_time = $start_time.' 00:00:00';
                    $end_time = $end_time.' 23:59:59';
                    $where['create_date'] = array('between',array($start_time,$end_time));
                }
            }else{
                $day = date('Y-m');
                $where['create_date'] = array('like',"%$day%");
            }
            $result = M('transfer_log','s_','db_salary')
                        ->where($where)
                        ->page($page,$limit)
                        ->select();
            $num = M('transfer_log','s_','db_salary')
                        ->where($where)
                        ->count();
            $data = [];
            foreach($result as $key=>$val){
                $data[] = [
                    'id' => $val['id'],
                    'outSubAccount' => $val['out_sub_account'],
                    'outSubAccountName' => $val['out_sub_account_name'],
                    'tranAmount' => $val['tran_amount'],
                    'inSubAccNo' => $val['in_sub_acc_no'],
                    'inSubAccName' => $val['in_sub_acc_name'],
                    'operator' => $val['operator'],
                    'createDate' => $val['create_date']
                ];
            }
        }else{
            $where = [
                'pageNum' => $page,
                'pageSize' => $limit,
                'createDate1' => $start_time,
                'createDate2' => $end_time
            ];
            $url = CAT_YJFX_URL . "/bank/getTransferLogList";
            $result = text_curl($url,$where);
            $data = $result['data']['list'];
            $num = $result['data']['total'];
        }
        return Response::mjson($data,$num);
    }

    public function send_msg_index(){
        $this->display();
    }
    public function send_msg_list(){
        $type = I('type');
        $this->assign('type',$type);
        if($type == 0){
            $this->display('send_msg_untreated_list');
        }else{
            $this->display('send_msg_treated_list');
        }
    }
    public function send_msg_data(){
        $type = I('type');
        $pageIndex = $_REQUEST['pageIndex'];
        $limit = 10;
        $page = $pageIndex+1;
        $companyName = I('companyName');
        $typeName = I('typeName');

        $where = [
            'pageNum' => $page,
            'pageSize' => $limit,
            'companyName' => $companyName,
            'typeName' => $typeName,
            'remindStatus' => $type,
        ];
        $url = HXF_URL . "/remind/list";
        $result = text_curl($url,$where);
        $data = $result['data']['items'];
        $num = $result['data']['total'];

        return Response::mjson($data,$num);
    }
    public function send_msg_deal(){
        $ids = I('ids');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'ids'=>$ids,
            );
            $url = HXF_URL . "/remind/confirm";
            $result = text_curl($url,$data);
            if ($result['code'] == 200) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的消息');
        }
    }
    public function send_msg_del(){
        $ids = I('ids');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'ids'=>$ids,
            );
            $url = HXF_URL . "/remind/delete";
            $result = text_curl($url,$data);
            if ($result['code'] == 200) {
                return Response::show(200,'删除成功');
            } else {
                return Response::show(400,'删除失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的消息');
        }
    }

    //畅安通消息通知
    public function cat_send_msg_index(){
        $this->display();
    }
    public function cat_send_msg_list(){
        $type = I('type');
        $this->assign('type',$type);
        if($type == 0){
            $this->display('cat_msg_untreated_list');
        }else{
            $this->display('cat_msg_treated_list');
        }
    }
    public function cat_send_msg_data(){
        $type = I('type');
        $pageIndex = $_REQUEST['pageIndex'];
        $limit = 10;
        $page = $pageIndex+1;
        $companyName = I('companyName');
        $typeName = I('typeName');

        $where = [
            'pageNum' => $page,
            'pageSize' => $limit,
            'companyName' => $companyName,
            'typeName' => $typeName,
            'remindStatus' => $type,
        ];
        $url = CAT_URL . "/remind/list";
        $result = text_curl($url,$where);
        $data = $result['data']['items'];
        $num = $result['data']['total'];

        return Response::mjson($data,$num);
    }
    public function cat_send_msg_deal(){
        $ids = I('ids');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'ids'=>$ids,
            );
            $url = CAT_URL . "/remind/confirm";
            $result = text_curl($url,$data);
            if ($result['code'] == 200) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的消息');
        }
    }
    public function cat_send_msg_del(){
        $ids = I('ids');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'ids'=>$ids,
            );
            $url = CAT_URL . "/remind/delete";
            $result = text_curl($url,$data);
            if ($result['code'] == 200) {
                return Response::show(200,'删除成功');
            } else {
                return Response::show(400,'删除失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的消息');
        }
    }
}