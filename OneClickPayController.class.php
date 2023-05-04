<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/21 0021
 * Time: 01:24
 */

namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;

class OneClickPayController extends Controller
{
  public function _initialize()
  {
    is_logout();
  }
  public function company_index(){
    $this->display();
  }
  public function company_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $company_name = I('companyName');
    $contacts_phone = I('contactsPhone');

    $url = YJFX_URL . "/bank/getaccountlist";
    $where = [
      'pageNum' => $pageIndex + 1,
      'pageSize' => $limit,
      'companyName' => $company_name,
      'contactsPhone' => $contacts_phone,
    ];
    $result = text_curl($url,$where);
    $data = [];
    $num = 0;
    if($result['code'] == 200){
      $data = $result['data']['list'];
      $num = $result['data']['total'];
    }

    return Response::mjson($data,$num);
  }

  public function company_detail(){
    $id = I('id');
    $where['a.id'] = array('eq',$id);
    $data = M('account','s_','db_salary')
              ->alias('a')
              ->field('c.company_name,c.contacts_phone,c.company_legal_person,a.account_status,a.account')
              ->join('left join s_company as c on a.cid = c.id')
              ->where($where)
              ->find();
    $this->assign('data',$data);
    $this->display();
  }

  public function company_add(){
    $company = M('company','t_','db_hxf')->field('id,company_name')->where(['status'=>0,'is_del'=>0])->select();
    $this->assign('company',$company);
    $this->display();
  }
  public function open_account(){
    $company = I('company');
    $channel = I('channel');

    if(empty($company) || empty($channel)){
      return Response::show(300,'缺少参数');
    }
    $company_result = M('company','t_','db_hxf')->field('id,company_name')->where(['id'=>$company])->find();
    $company_name = $company_result['company_name'];
    $count = strlen($company_name);
    if($count > 42){
      $company_name = substr($company_name,0,42);
    }
    $subAccountSeq = mt_rand(100000,999999);//获取六位随机序列号
    $data = [
      'cId' => $company_result['id'],
      'type' => $channel,
      'subAccountName' => $company_name,
      'subAccountSeq' => $subAccountSeq,
      'opFlag' => 'A'
    ];
    $company_type = ($channel == 1) ? '慧薪福' : '人事邦';
    $url = YJFX_URL . "/bank/subaccountmaintenance";
    $result = text_curl($url,$data);
    $admin_log = '开通企业账户一键发薪' . $company_type . '渠道。企业名称：' . $company_result['company_name'];
    if($result['code'] == 200){
      $admin_status = 1;
    }else{
      $admin_status = 0;
    }
    admin_log($admin_log, $admin_status,$company_result['id']);
    return Response::show($result['code'],$result['msg']);
  }

  public function company_switch(){
    $cid = I('cid');
    $state = I('state');
    $channel = I('channel');
    $account = I('account');
    $id = I('id');
    if(empty($cid)){
      return Response::show(300,'缺少参数');
    }
    $company_result = M('company','t_','db_hxf')->field('id,company_name')->where(['id'=>$cid])->find();
    $company_name = $company_result['company_name'];
    $count = strlen($company_name);
    if($count > 42){
      $company_name = substr($company_name,0,42);
    }
    $opFlag = 'R';
    $admin_state = '';
    if($state == 'false'){
      $admin_state = '关闭';
      $opFlag = 'D';
    }else{
      $admin_state = '开启';
      $opFlag = 'R';
    }
    $data = [
      'cId' => $company_result['id'],
      'type' => $channel,
      'subAccountName' => $company_name,
      'opFlag' => $opFlag,
      'subAccount' => $account
    ];
    $company_type = ($channel == 1) ? '慧薪福' : '人事邦';
    $url = YJFX_URL . "/bank/subaccountmaintenance";
    $result = text_curl($url,$data);
    $admin_log = $admin_state . '企业账户一键发薪' . $company_type . '渠道。企业名称：' . $company_result['company_name'];
    if($result['code'] == 200){
      $admin_status = 1;
    }else{
      $admin_status = 0;
    }
    admin_log($admin_log, $admin_status,$id);
    return Response::show($result['code'],$result['msg']);
  }
  public function bill_index(){
    $start = date('Y-m-01', strtotime(date("Y-m-d")));
    $end = date('Y-m-d');
    $this->assign('start',$start);
    $this->assign('end',$end);
    $this->display();
  }
  public function bill_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $receiveName = I('receiveName');
    $start_time = I('StartTime');
    $end_time = I('EndTime');
    $status = I('status');

    if(!empty($companyName)){
      $where['c.company_name'] = array('like',"%$companyName%");
    }
    if(!empty($paymentSerialNumber)){
      $where['b.payment_serial_number'] = array('eq',$paymentSerialNumber);
    }
    if($status != ''){
      $where['b.status'] = array('eq',$status);
    }
    if(!empty($receiveName)){
      $where['b.receive_name'] = array('like',"%$receiveName%");
    }
    if(!empty($start_time)&&!empty($end_time)){
      if($start_time==$end_time){
          $where['b.create_date'] = array('like',"%$start_time%");
      }else{
          $start_time = $start_time.' 00:00:00';
          $end_time = $end_time.' 23:59:59';
          $where['b.create_date'] = array('between',array($start_time,$end_time));
      }
    }

    $result = M('bill','s_','db_salary')
                ->alias('a')
                ->field('b.id,b.create_date as createDate,b.update_date as updateDate,a.bill_code as billCode,b.payment_serial_number as paymentSerialNumber,
                c.company_name as companyName,d.account,b.money,b.receive_name as receiveName,b.receive_bank as receiveBank,b.receipt_account as receiptAccount,b.mobile,b.remark,b.status')
                ->join('left join s_bill_detail as b on a.id=b.bid')
                ->join('left join s_company as c on a.cid=c.id')
                ->join('left join s_account as d on a.cid=d.cid')
                ->where($where)
                ->order('b.create_date desc,b.id desc')
                ->page($page,$limit)
                ->select();
    $num = M('bill','s_','db_salary')
                ->alias('a')
                ->join('left join s_bill_detail as b on a.id=b.bid')
                ->join('left join s_company as c on a.cid=c.id')
                ->join('left join s_account as d on a.cid=d.cid')
                ->where($where)
                ->count();

    return Response::mjson($result,$num);
  }
  public function count_price(){
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $receiveName = I('receiveName');
    $start_time = I('stime');
    $end_time = I('etime');

    if(!empty($companyName)){
      $where['c.company_name'] = array('like',"%$companyName%");
    }
    if(!empty($paymentSerialNumber)){
      $where['b.payment_serial_number'] = array('eq',$paymentSerialNumber);
    }
    if(!empty($receiveName)){
      $where['b.receive_name'] = array('like',"%$receiveName%");
    }
    if(!empty($start_time)&&!empty($end_time)){
      if($start_time==$end_time){
          $where['b.create_date'] = array('like',"%$start_time%");
      }else{
          $start_time = $start_time.' 00:00:00';
          $end_time = $end_time.' 23:59:59';
          $where['b.create_date'] = array('between',array($start_time,$end_time));
      }
    }
    $where['b.status'] = array('eq',2);
    $result = M('bill','s_','db_salary')
                ->alias('a')
                ->field('sum(b.money) as money')
                ->join('left join s_bill_detail as b on a.id=b.bid')
                ->join('left join s_company as c on a.cid=c.id')
                ->join('left join s_account as d on a.cid=d.cid')
                ->where($where)
                ->order('b.create_date desc,b.id desc')
                ->page($page,$limit)
                ->select();
    $price = empty($result[0]['money']) ? 0.00 : $result[0]['money'];
    return Response::show(200,$price);
  }
  public function company_bill_index(){
    $this->display();
  }
  public function company_bill_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $companyName = I('companyName');
    $billNum = I('billNum');
    $start_time = I('StartTime');
    $end_time = I('EndTime');

    if(!empty($start_time)&&!empty($end_time)){
      if($start_time==$end_time){
          $where['a.create_date'] = array('like',"%$start_time%");
      }else{
          $start_time = $start_time.' 00:00:00';
          $end_time = $end_time.' 23:59:59';
          $where['a.create_date'] = array('between',array($start_time,$end_time));
      }
    }
    if(!empty($companyName)){
      $where['c.company_name'] = array('like',"%$companyName%");
    }
    if(!empty($billNum)){
      $where['a.bill_code'] = array('eq',$billNum);
    }
    $where['a.deleted'] = array('eq',0);
    $result = M('bill','s_','db_salary')
              ->alias('a')
              ->field('a.id,a.create_date,a.bill_code,a.total_money,count(b.id) as pnum,(select count(id) from s_bill_detail where status=2 and bid=a.id) as snum,c.company_name')
              ->join('left join s_bill_detail as b on a.id=b.bid')
              ->join('left join s_company as c on a.cid=c.id')
              ->where($where)
              ->group('b.bid')
              ->order('a.create_date desc')
              ->page($page,$limit)
              ->select();
    
    $num = M('bill','s_','db_salary')
              ->alias('a')
              ->where($where)
              ->count();
    
    return Response::mjson($result,$num);
  }
  public function company_bill_detail(){
    $id = I('id');
    $this->assign('id',$id);
    $this->display();
  }
  public function company_bill_detail_data(){
    $id = I('id');
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $receiveName = I('receiveName');
    $start_time = I('stime');
    $end_time = I('etime');
    $status = I('status');

    if(!empty($paymentSerialNumber)){
      $where['b.payment_serial_number'] = array('eq',$paymentSerialNumber);
    }
    if($status != ''){
      $where['b.status'] = array('eq',$status);
    }
    if(!empty($receiveName)){
      $where['b.receive_name'] = array('like',"%$receiveName%");
    }

    $where['a.id'] = array('eq',$id);
    $result = M('bill','s_','db_salary')
                ->alias('a')
                ->field('b.id,b.create_date as createDate,b.update_date as updateDate,a.bill_code as billCode,b.payment_serial_number as paymentSerialNumber,
                c.company_name as companyName,d.account,b.money,b.receive_name as receiveName,b.receive_bank as receiveBank,b.receipt_account as receiptAccount,b.mobile,b.remark,b.status')
                ->join('left join s_bill_detail as b on a.id=b.bid')
                ->join('left join s_company as c on a.cid=c.id')
                ->join('left join s_account as d on a.cid=d.cid')
                ->where($where)
                ->order('id desc')
                ->page($page,$limit)
                ->select();
    $num = M('bill','s_','db_salary')
                ->alias('a')
                ->join('left join s_bill_detail as b on a.id=b.bid')
                ->join('left join s_company as c on a.cid=c.id')
                ->join('left join s_account as d on a.cid=d.cid')
                ->where($where)
                ->count();

    return Response::mjson($result,$num);
  }

  public function deal_price(){
    $id = I('id');

    $where['bid'] = array('eq',$id);
    $where['status'] = array('eq',2);
    $price = M('bill_detail','s_','db_salary')->field('sum(money) as money')->where($where)->find();
    $money = empty($price['money']) ? 0.00 : $price['money'];
    return Response::show(200,$money);
  }

  public function recharge_index(){
    $this->display();
  }
  public function recharge_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $start_time = I('StartTime');
    $end_time = I('EndTime');
    $status = I('status');

    if(!empty($start_time)&&!empty($end_time)){
      if($start_time==$end_time){
          $where['a.create_date'] = array('like',"%$start_time%");
      }else{
          $start_time = $start_time.' 00:00:00';
          $end_time = $end_time.' 23:59:59';
          $where['a.create_date'] = array('between',array($start_time,$end_time));
      }
    }
    if(!empty($paymentSerialNumber)){
      $where['a.payment_serial_number'] = array('eq',$paymentSerialNumber);
    }
    if($status != ''){
      $where['a.status'] = array('eq',$status);
    }
    if(!empty($companyName)){
      $where['c.company_name'] = array('like',"%$companyName%");
    }
    $result = M('recharge_log','s_','db_salary')
                ->alias('a')
                ->field('a.id,a.create_date,a.payment_serial_number,c.company_name,a.transaction_number,
                a.money,a.service_charge,a.account,a.status,a.receive_user_bank_no,a.receive_user_bank_name,a.receive_bank_name,a.remark')
                ->join('left join s_account as b on a.aid = b.id')
                ->join('left join s_company as c on b.cid = c.id')
                ->where($where)
                ->order('a.create_date desc')
                ->page($page,$limit)
                ->select();
    $num = M('recharge_log','s_','db_salary')
                ->alias('a')
                ->join('left join s_account as b on a.aid = b.id')
                ->join('left join s_company as c on b.cid = c.id')
                ->count();
    
    return Response::mjson($result,$num);
  }
  public function deal_recharge_price(){
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $start_time = I('stime');
    $end_time = I('etime');
    $status = I('status');

    if(!empty($start_time)&&!empty($end_time)){
      if($start_time==$end_time){
          $where['a.create_date'] = array('like',"%$start_time%");
      }else{
          $start_time = $start_time.' 00:00:00';
          $end_time = $end_time.' 23:59:59';
          $where['a.create_date'] = array('between',array($start_time,$end_time));
      }
    }
    if(!empty($paymentSerialNumber)){
      $where['a.payment_serial_number'] = array('eq',$paymentSerialNumber);
    }
    if($status != ''){
      $where['a.status'] = array('eq',$status);
    }
    if(!empty($companyName)){
      $where['c.company_name'] = array('like',"%$companyName%");
    }
    $result = M('recharge_log','s_','db_salary')
                ->alias('a')
                ->field('sum(a.money) as money')
                ->join('left join s_account as b on a.aid = b.id')
                ->join('left join s_company as c on b.cid = c.id')
                ->where($where)
                ->select();
    $price = empty($result[0]['money']) ? 0.00 : $result[0]['money'];
    return Response::show(200,$price);
  }

  public function transfer_accounts_index(){
    $this->display();
  }
  public function transfer_accounts_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $start_time = I('StartTime');
    $end_time = I('EndTime');

    if(!empty($start_time)&&!empty($end_time)){
      if($start_time==$end_time){
          $where['a.create_date'] = array('like',"%$start_time%");
      }else{
          $start_time = $start_time.' 00:00:00';
          $end_time = $end_time.' 23:59:59';
          $where['a.create_date'] = array('between',array($start_time,$end_time));
      }
    }
    if(!empty($paymentSerialNumber)){
      $where['a.payment_serial_number'] = array('eq',$paymentSerialNumber);
    }
    if(!empty($companyName)){
      $where['c.company_name'] = array('like',"%$companyName%");
    }

    $result = M('transfer_out','s_','db_salary')
                ->alias('a')
                ->field('a.id,a.create_date,c.company_name,a.item_number,a.payment_serial_number,a.money,a.real_money,a.status')
                ->join('left join s_account as b on a.aid = b.id')
                ->join('left join s_company as c on b.cid = c.id')
                ->where($where)
                ->order('a.create_date desc')
                ->page($page,$limit)
                ->select();
    $num = M('transfer_out','s_','db_salary')
                ->alias('a')
                ->join('left join s_account as b on a.aid = b.id')
                ->join('left join s_company as c on b.cid = c.id')
                ->where($where)
                ->count();
    return Response::mjson($result,$num);
  }

  public function confirm_transfer_accounts(){
    $id = I('id');
    $num = I('num');

    if(empty($id)){
      return Response::show(300,'请选择要填写的转账记录');
    }
    $data = [
      'payment_serial_number' => $num,
      'status' => 1
    ];
    $result = M('transfer_out','s_','db_salary')->where(['id'=>$id])->save($data);
    if($result !== false){
      return Response::show(200,'确认转账成功');
    }else{
      return Response::show(300,'确认转账失败');
    }
  }
  public function edit_transfer_accounts(){
    $id = I('id');
    $num = I('num');

    if(empty($id)){
      return Response::show(300,'请选择要填写的转账记录');
    }
    $data = [
      'payment_serial_number' => $num,
      'status' => 1
    ];
    $result = M('transfer_out','s_','db_salary')->where(['id'=>$id])->save($data);
    if($result !== false){
      return Response::show(200,'修改成功');
    }else{
      return Response::show(300,'修改失败');
    }
  }
  //畅安通
  public function cat_company_index(){
    $this->display();
  }
  public function cat_company_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $company_name = I('companyName');
    $contacts_phone = I('contactsPhone');

    $url = CAT_YJFX_URL . "/bank/getaccountlist";
    $where = [
      'pageNum' => $pageIndex + 1,
      'pageSize' => $limit,
      'companyName' => $company_name,
      'contactsPhone' => $contacts_phone,
    ];
    $result = text_curl($url,$where);
    $data = [];
    $num = 0;
    if($result['code'] == 200){
      $data = $result['data']['list'];
      $num = $result['data']['total'];
    }

    return Response::mjson($data,$num);
  }
  public function cat_company_detail(){
    $id = I('id');
    $url = CAT_YJFX_URL . '/bank/getaccount';

    $where = [
      'id' => $id
    ];
    $result = text_curl($url,$where);
    $data = $result['data'];
    $this->assign('data',$data);
    $this->display();
  }

  public function cat_company_add(){
    $url = CAT_URL . '/getAllCompanyList';
    $result = text_curl($url);
    $company = $result['data'];
    $this->assign('company',$company);
    $this->display();
  }
  public function cat_open_account(){
    $company = I('company');
    $channel = I('channel');

    if(empty($company) || empty($channel)){
      return Response::show(300,'缺少参数');
    }
    $url = CAT_URL . '/getAllCompanyList';
    $where = [
      'cid' => $company
    ];
    $result = text_curl($url,$where);
    $company_result = $result['data'][0];
    $company_name = $company_result['companyName'];
    $count = strlen($company_name);
    if($count > 42){
      $company_name = substr($company_name,0,42);
    }
    $subAccountSeq = mt_rand(100000,999999);//获取六位随机序列号
    $data = [
      'cId' => $company_result['id'],
      'type' => 1,
      'subAccountName' => $company_name,
      'subAccountSeq' => $subAccountSeq,
      'opFlag' => 'A'
    ];
    $company_type = '畅安通';
    $url = CAT_YJFX_URL . "/bank/subaccountmaintenance";
    $result = text_curl($url,$data);
    $admin_log = '开通畅安通企业账户一键发薪' . $company_type . '渠道。企业名称：' . $company_result['companyName'];
    if($result['code'] == 200){
      $admin_status = 1;
    }else{
      $admin_status = 0;
    }
    admin_log($admin_log, $admin_status,$company_result['id']);
    return Response::show($result['code'],$result['msg']);
  }

  public function cat_company_switch(){
    $cid = I('cid');
    $state = I('state');
    $channel = I('channel');
    $account = I('account');
    $id = I('id');
    if(empty($cid)){
      return Response::show(300,'缺少参数');
    }
    $company_name = I('company');
    $count = strlen($company_name);
    if($count > 42){
      $company_name = substr($company_name,0,42);
    }
    $opFlag = 'R';
    $admin_state = '';
    if($state == 'false'){
      $admin_state = '关闭';
      $opFlag = 'D';
    }else{
      $admin_state = '开启';
      $opFlag = 'R';
    }
    $data = [
      'cId' => $cid,
      'type' => $channel,
      'subAccountName' => $company_name,
      'opFlag' => $opFlag,
      'subAccount' => $account
    ];
    $company_type = '畅安通';
    $url = CAT_YJFX_URL . "/bank/subaccountmaintenance";
    $result = text_curl($url,$data);
    $admin_log = $admin_state . '企业账户一键发薪' . $company_type . '渠道。企业名称：' . $company_name;
    if($result['code'] == 200){
      $admin_status = 1;
    }else{
      $admin_status = 0;
    }
    admin_log($admin_log, $admin_status,$id);
    return Response::show($result['code'],$result['msg']);
  }
  public function cat_bill_index(){
    $start = date('Y-m-01', strtotime(date("Y-m-d")));
    $end = date('Y-m-d');
    $this->assign('start',$start);
    $this->assign('end',$end);
    $this->display();
  }
  public function cat_bill_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $receiveName = I('receiveName');
    $start_time = I('StartTime');
    $end_time = I('EndTime');
    $status = I('status');
    
    $url = CAT_YJFX_URL . "/bank/getbilldetaillist";
    $where = [
      'pageNum' => $pageIndex + 1,
      'pageSize' => $limit,
      'companyName' => $company_name,
      'paymentSerialNumber' => $paymentSerialNumber,
      'receiveName' => $receiveName,
      'status' => $status,
      'time1' => $start_time,
      'time2' => $end_time,
    ];
    $result = text_curl($url,$where);
    $data = [];
    $num = 0;
    if($result['code'] == 200){
      $data = $result['data']['list'];
      $num = $result['data']['total'];
    }
    return Response::mjson($data,$num);
  }
  public function cat_count_price(){
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $receiveName = I('receiveName');
    $start_time = I('stime');
    $end_time = I('etime');

    $url = CAT_YJFX_URL . "/bank/getbilldetaillist";
    $where = [
      'companyName' => $company_name,
      'paymentSerialNumber' => $paymentSerialNumber,
      'receiveName' => $receiveName,
      'time1' => $start_time,
      'time2' => $end_time,
    ];
    $result = text_curl($url,$where);
    $price = empty($result['msg']) ? 0.00 : $result['msg'];
    return Response::show(200,$price);
  }
  public function cat_recharge_index(){
    $excelurl = CAT_YJFX_URL . '/bank/rechargeRecordExport';
    $this->assign('excelurl',$excelurl);
    $this->display();
  }
  public function cat_recharge_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $start_time = I('StartTime');
    $end_time = I('EndTime');

    $url = CAT_YJFX_URL . "/bank/getRechargeLogList";
    $where = [
      'pageNum' => $pageIndex + 1,
      'pageSize' => $limit,
      'companyName' => $companyName,
      'paymentSerialNumber' => $paymentSerialNumber,
      'startCreateDate' => $start_time,
      'endCreateDate' => $end_time,
    ];
    
    $result = text_curl($url,$where);
    $data = [];
    $num = 0;
    if($result['code'] == 200){
      $data = $result['data']['list'];
      $num = $result['data']['total'];
    }
    return Response::mjson($data,$num);
  }
  public function cat_deal_recharge_price(){
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $start_time = I('stime');
    $end_time = I('etime');

    $url = CAT_YJFX_URL . "/bank/getRechargeLogList";
    $where = [
      'companyName' => $companyName,
      'paymentSerialNumber' => $paymentSerialNumber,
      'startCreateDate' => $start_time,
      'endCreateDate' => $end_time,
    ];
    $result = text_curl($url,$where);
    $price = empty($result['msg']) ? 0.00 : $result['msg'];
    return Response::show(200,$price);
  }
  public function cat_transfer_accounts_index(){
    $this->display();
  }
  public function cat_transfer_accounts_data(){
    $pageIndex = I('pageIndex','');
    $limit = 10;
    $page = $pageIndex+1;
    $companyName = I('companyName');
    $paymentSerialNumber = I('paymentSerialNumber');
    $createDate1 = I('StartTime');
    $createDate2 = I('EndTime');

    $url = CAT_YJFX_URL . "/bank/getTransferOutList";
    $where = [
      'pageNum' => $pageIndex + 1,
      'pageSize' => $limit,
      'companyName' => $companyName,
      'paymentSerialNumber' => $paymentSerialNumber,
      'createDate1' => $createDate1,
      'createDate2' => $createDate2,
    ];
    $result = text_curl($url,$where);
    $data = [];
    $num = 0;
    if($result['code'] == 200){
      $data = $result['data']['list'];
      $num = $result['data']['total'];
    }

    return Response::mjson($data,$num);
  }
  public function cat_confirm_transfer_accounts(){
    $id = I('id');
    $num = I('num');

    if(empty($id)){
      return Response::show(300,'请选择要填写的转账记录');
    }
    $data = [
      'status' => 1,
      'paymentSerialNumber' => $num,
      'id' => $id
    ];
    $url = CAT_YJFX_URL . "/bank/updatetransferout";
    $result = text_curl($url,$data);
    if($result['code'] == 200){
      return Response::show(200,'确认转账成功');
    }else{
      return Response::show(300,'确认转账失败');
    }
  }
  public function cat_edit_transfer_accounts(){
    $id = I('id');
    $num = I('num');

    if(empty($id)){
      return Response::show(300,'请选择要填写的转账记录');
    }
    $data = [
      'status' => 1,
      'paymentSerialNumber' => $num,
      'id' => $id
    ];
    $url = CAT_YJFX_URL . "/bank/updatetransferout";
    $result = text_curl($url,$data);
    if($result['code'] == 200){
      return Response::show(200,'修改成功');
    }else{
      return Response::show(300,'修改失败');
    }
  }
}