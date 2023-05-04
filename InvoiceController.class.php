<?php
/**
 * Created by PhpStorm.
 * User: 苏鹰
 * Date: 2020/4/29
 * Time: 14:39
 */

namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;

class InvoiceController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }
    public function invoice_index(){
        $this->display();
    }
    public function invoice_list(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $start_time = I('StartTime');
        $end_time = I('EndTime');
        $invoice_subject = I('invoiceSubject');
        
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }

        if(!empty($invoice_subject)){
            $where['c.subject_title'] = array('like',"%$invoice_subject%"); 
        }
        $where['a.state'] = array('eq',0);
        $result = M('invoice','t_')
                        ->alias('a')
                        ->field('a.id,a.mseid,e.settlement_title as title,a.create_time,b.corporate_name,c.subject_title as invoice_subject,d.subject_title as invoice_title,a.invoice_price,a.state')
                        ->join('left join t_corporate as b on a.cid = b.corporate_id')
                        ->join('left join t_invoice_subject as c on a.invoice_subject = c.id')
                        ->join('left join t_invoice_subject as d on a.invoice_title = d.id')
                        ->join('left join dsy_settlement_apply as e on a.mseid = e.mseid')
                        ->order('a.create_time desc')
                        ->page($page,$limit)
                        ->where($where)
                        ->select();
        $num = M('invoice','t_')
                        ->alias('a')
                        ->join('left join t_corporate as b on a.cid = b.corporate_id')
                        ->join('left join t_invoice_subject as c on a.invoice_subject = c.id')
                        ->join('left join t_invoice_subject as d on a.invoice_title = d.id')
                        ->join('left join dsy_settlement_apply as e on a.mseid = e.mseid')
                        ->where($where)
                        ->count();
        return Response::mjson($result,$num);
    }
    public function invoice_detail(){
        $id = I('id');
        $where['a.id'] = array('eq',$id);
        $result = M('invoice','t_')
                        ->alias('a')
                        ->field('a.id,a.mseid,a.state,a.remarks,b.corporate_name,e.settlement_title,a.invoice_price,a.invoice_class,a.invoice_num,a.reason,a.enclosure,a.file_name,a.type,
                         c.subject_title as title1,c.duty_paragraph as duty1,c.account as acc1,c.bank as bank1,c.address as add1,c.phone as phone1,
                         d.subject_title as title2,d.duty_paragraph as duty2,d.account as acc2,d.bank as bank2,d.address as add2,d.phone as phone2,
                         f.addressee,f.phone,f.address')
                        ->join('left join t_corporate as b on a.cid = b.corporate_id')
                        ->join('left join t_invoice_subject as c on a.invoice_subject = c.id')
                        ->join('left join t_invoice_subject as d on a.invoice_title = d.id')
                        ->join('left join dsy_settlement_apply as e on a.mseid = e.mseid')
                        ->join('left join t_invoice_address as f on a.address_id = f.id')
                        ->where($where)
                        ->find();
        $result['invoice_class'] = json_decode($result['invoice_class'],true);
        $result['enclosure'] = empty($result['enclosure']) ? '' : WEB_URL . "/dashengyun/" . $result['enclosure'];
        $this->assign('result',$result);
        $this->display();
    }
    //同意开票
    public function invoice_open(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'请选择要同意开票的记录');
        }
        $check = $this->select_corporate_name($id);
        if($check['state'] == 0){
            return Response::show(300,'查询不到该开票记录');
        }
        $result = M('invoice','t_')->where(['id'=>$id,'state'=>0])->save(['state'=>1]);
        $admin_log = '同意开票审批标题为"' . $check['data']['title'] . '"的开票记录';
        if($result !== false){
            admin_log($admin_log, 1, 't_invoice:' . $id);
            return Response::show(200,'同意开票成功');
        }else{
            admin_log($admin_log, 0, 't_invoice:' . $id);
            return Response::show(200,'同意开票失败');
        }
    }
    //驳回开票
    public function invoice_stop(){
        $id = I('id');
        $reason = I('reason');
        if(empty($id)){
            return Response::show(300,'请选择要驳回的记录');
        }
        if(empty($reason)){
            return Response::show(300,'请填写驳回原因');
        }
        $check = $this->select_corporate_name($id);
        if($check['state'] == 0){
            return Response::show(300,'查询不到该开票记录');
        }
        $data = [
            'state' => 2,
            'reason' => $reason
        ];
        $result = M('invoice','t_')->where(['id'=>$id,'state'=>0])->save($data);
        $admin_log = '驳回审批标题为"' . $check['data']['title'] . '"的开票记录';
        if($result !== false){
            admin_log($admin_log, 1, 't_invoice:' . $id);
            return Response::show(200,'驳回成功');
        }else{
            admin_log($admin_log, 0, 't_invoice:' . $id);
            return Response::show(200,'驳回失败');
        }
    }
    public function look_approve(){
        $mseid = I('mseid');
        $where['a.id'] = array('eq',$mseid);
        $where['e.type_id'] = array('eq',10);
        $result = M('mysubeaa','t_')
                    ->alias('a')
                    ->field('a.id,d.settlement_title,e.type_name,a.time,d.apply_content,d.files_path,d.files_name,d.stamp_pic,c.name,null as pic')
                    ->join('left join t_employee as b on a.uid = b.employee_id')
                    ->join('left join t_personal as c on b.personal_id = c.personal_id')
                    ->join('left join dsy_settlement_apply as d on a.id = d.mseid')
                    ->join('left join dsy_annex_type as e on d.document = e.value')
                    ->where($where)
                    ->find();
        $mt_where['mseid'] = array('eq',$mseid);
        $myeaatd = M('myeaatd','t_')
                    ->field('needdo,status,content,time,type,user_name')
                    ->where($mt_where)
                    ->select();
        $result['stamp_pic'] = empty($result['stamp_pic']) ? '' : explode(',',$result['stamp_pic']);
        $result['files_path'] = empty($result['files_path']) ? '' : explode(',',$result['files_path']);
        $result['files_name'] = empty($result['files_name']) ? '' : explode(',',$result['files_name']);
        foreach($result['stamp_pic'] as $val){
            $result['pic'][] = format_img($val, IMG_VIEW);
        }
        foreach($result['files_path'] as $key=>$val){
            $result['file'][$key]['url'] = format_img($val, IMG_VIEW);
            $result['file'][$key]['name'] = $result['files_name'][$key];
        }
        $status = [1=>'审批中',2=>'待我审批',3=>'同意',4=>'不同意',5=>'已转交审批',6=>'驳回',7=>'撤销'];
        foreach($myeaatd as $key => $val){
            if($val['type'] == 1){
                $myeaatd[$key]['type_name'] = '提交审核';
            }else{//1审批中2待我审批3同意4不同意5已转交审批6驳回7撤销
                $myeaatd[$key]['type_name'] = $status[$val['status']];
            }
        }
        $this->assign('result',$result);
        $this->assign('myeaatd',$myeaatd);
        $this->display();
    }
    public function invoice_record_index(){
        $this->display();
    }
    public function invoice_record_list(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $start_time = I('StartTime');
        $end_time = I('EndTime');
        $invoice_subject = I('invoiceSubject');
        
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }
        if(!empty($invoice_subject)){
            $where['c.subject_title'] = array('like',"%$invoice_subject%"); 
        }
        $where['a.state'] = array('neq',0);
        $result = M('invoice','t_')
                        ->alias('a')
                        ->field('a.id,a.mseid,e.settlement_title as title,a.create_time,b.corporate_name,c.subject_title as invoice_subject,d.subject_title as invoice_title,a.invoice_price,a.invoice_num,a.state')
                        ->join('left join t_corporate as b on a.cid = b.corporate_id')
                        ->join('left join t_invoice_subject as c on a.invoice_subject = c.id')
                        ->join('left join t_invoice_subject as d on a.invoice_title = d.id')
                        ->join('left join dsy_settlement_apply as e on a.mseid = e.mseid')
                        ->order('a.create_time desc')
                        ->page($page,$limit)
                        ->where($where)
                        ->select();
        $num = M('invoice','t_')
                        ->alias('a')
                        ->join('left join t_corporate as b on a.cid = b.corporate_id')
                        ->join('left join t_invoice_subject as c on a.invoice_subject = c.id')
                        ->join('left join t_invoice_subject as d on a.invoice_title = d.id')
                        ->join('left join dsy_settlement_apply as e on a.mseid = e.mseid')
                        ->where($where)
                        ->count();
        return Response::mjson($result,$num);
    }
    //填写发票单号
    public function invoice_record_addnum(){
        $id = I('id');
        $num = I('num');
        if(empty($id)){
            return Response::show(300,'缺少参数');
        }
        if(empty($num)){
            return Response::show(300,'请填写开票单号');
        }
        $check = $this->select_corporate_name($id);
        if($check['state'] == 0){
            return Response::show(300,'查询不到该开票记录');
        }
        $where = ['id' => $id,'state' => 1];
        $data = ['invoice_num' => $num,'state' => 3];
        
        $result = M('invoice','t_')->where($where)->save($data);
        $admin_log = '填写发票单号：'. $num .',审批标题为"' . $check['data']['title'] . '"的开票记录';
        if($result !== false){
            admin_log($admin_log, 1, 't_invoice:' . $id);
            return Response::show(200,'填写开票单号成功');
        }else{
            admin_log($admin_log, 0, 't_invoice:' . $id);
            return Response::show(300,'填写开票单号失败');
        }
    }
    public function invoice_class_index(){
        $this->display();
    }
    public function invoice_class_list(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';

        $result = M('invoice_class','t_')
                ->order('create_time desc')
                ->page($page,$limit)
                ->select();
        $num = M('invoice_class','t_')->count();
        return Response::mjson($result,$num);
    }

    public function invoice_class_add(){
        $class_name = I('class_name');
        $taxrate = I('taxrate');
        if(empty($class_name)){
            return Response::show(300,'开票类目不能为空');
        }
        $data = [
            'class_name' => $class_name,
            'taxrate' => empty($taxrate) ? 0.00:$taxrate,
            'create_time' => date('Y-m-d H:i:s',time())
        ];
        $result = M('invoice_class','t_')->add($data);
        $admin_log = "新增开票类目：" . $class_name;
        if($result !== false){
            admin_log($admin_log, 1, 't_invoice_class:' . $result);
            return Response::show(200,'新增开票类目成功');
        }else{
            admin_log($admin_log, 0, 't_invoice_class:' . $result);
            return Response::show(300,'新增开票类目失败');
        }
    }

    public function invoice_class_edit(){
        $id = I('id');
        $class_name = I('class_name');
        $taxrate = I('taxrate');
        if(empty($id)){
            return Response::show(300,'请选择要编辑的类目');
        }
        if(empty($class_name)){
            return Response::show(300,'开票类目不能为空');
        }
        $log_result = M('invoice_class','t_')->field('class_name')->where(['id'=>$id])->find();
        $data = [
            'class_name' => $class_name,
            'taxrate' => $taxrate,
        ];
        $result = M('invoice_class','t_')->where(['id'=>$id])->save($data);
        if($log_result['class_name'] != $class_name){
            $admin_log = "编辑开票类目：类目名称" . $log_result['class_name'] . "修改为" . $class_name;
        }else{
            $admin_log = "编辑开票类目：类目名称" . $class_name;
        }
        if($result !== false){
            admin_log($admin_log, 1, 't_invoice_class:' . $id);
            return Response::show(200,'编辑成功');
        }else{
            admin_log($admin_log, 0, 't_invoice_class:' . $id);
            return Response::show(300,'编辑失败');
        }
    }
    public function invoice_class_del(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'请选择要删除的类目');
        }
        $log_result = M('invoice_class','t_')->field('class_name')->where(['id'=>$id])->find();

        $result = M('invoice_class','t_')->where(['id'=>$id])->delete();
        $admin_log = "删除了类目名称为" . $log_result['class_name'] . "开票类目";

        if($result !== false){
            admin_log($admin_log, 1, 't_invoice_class:' . $id);
            return Response::show(200,'删除成功');
        }else{
            admin_log($admin_log, 0, 't_invoice_class:' . $id);
            return Response::show(300,'删除失败');
        }
    }
    public function invoice_subject_index(){
        $this->display();
    }
    public function invoice_subject_list(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';

        $where['cid'] = array('eq',0); 
        $result = M('invoice_subject','t_')->where($where)->page($page,$limit)->select();

        $num = M('invoice_subject','t_')->count();

        return Response::mjson($result,$num);
    }
    public function invoice_subject_add(){
        $this->display();
    }
    public function invoice_subject_insert(){
        $subject_title = I('subject_title');
        $duty_paragraph = I('duty_paragraph');
        $account = I('account');
        $bank = I('bank');
        $address =I('address');
        $phone = I('phone');
        if(empty($subject_title)){
            return Response::show(300,'主体抬头为必填项，请输入');
        }
        if(empty($duty_paragraph)){
            return Response::show(300,'税务登记证号为必填项，请输入');
        }
        if(empty($account)){
            return Response::show(300,'基本开户账户为必填项，请输入');
        }
        if(empty($bank)){
            return Response::show(300,'开户银行为必填项，请输入');
        }
        if(empty($address)){
            return Response::show(300,'注册场地地址为必填项，请输入');
        }
        if(empty($phone)){
            return Response::show(300,'注册固定电话为必填项，请输入');
        }
        if (check_mobile($phone) == 0) {
            return Response::show(300, '手机号码格式不正确，请重新输入');
        }
        $data = [
            'cid' => 0,
            'subject_title' => $subject_title,
            'duty_paragraph' => $duty_paragraph,
            'account' => $account,
            'bank' => $bank,
            'address' => $address,
            'phone' => $phone,
            'create_time' => date('Y-m-d H:i:s',time())
        ];

        $result = M('invoice_subject','t_')->add($data);
        $admin_log = "新增开票主体。开票主体：" . $subject_title;
        if($result){
            admin_log($admin_log, 1, 't_invoice_subject:' . $result);
            return Response::show(200,'添加新开票主体成功');
        }else{
            admin_log($admin_log, 0, 't_invoice_subject:' . $result);
            return Response::show(300,'添加新开票主体失败');
        }
    }
    public function invoice_subject_edit(){
        $id = I('id');
        $result = M('invoice_subject','t_')->where(['id'=>$id])->find();
        $this->assign('result',$result);
        $this->display('invoice_subject_add');
    }
    public function invoice_subject_update(){
        $id = I('id');
        $subject_title = I('subject_title');
        $duty_paragraph = I('duty_paragraph');
        $account = I('account');
        $bank = I('bank');
        $address =I('address');
        $phone = I('phone');
        if(empty($id)){
            return Response::show(300,'请选择要修改的开票主体');
        }
        if(empty($subject_title)){
            return Response::show(300,'开票主体为必填项，请输入');
        }
        if(empty($duty_paragraph)){
            return Response::show(300,'税务登记账户为必填项，请输入');
        }
        if(empty($account)){
            return Response::show(300,'基本开户账户为必填项，请输入');
        }
        if(empty($bank)){
            return Response::show(300,'开户银行名称为必填项，请输入');
        }
        if(empty($address)){
            return Response::show(300,'注册场所地址为必填项，请输入');
        }
        if(empty($phone)){
            return Response::show(300,'注册固定电话为必填项，请输入');
        }
        $data = [
            'subject_title' => $subject_title,
            'duty_paragraph' => $duty_paragraph,
            'account' => $account,
            'bank' => $bank,
            'address' => $address,
            'phone' => $phone,
        ];

        $result = M('invoice_subject','t_')->where(['id'=>$id])->save($data);
        $admin_log = "编辑开票主体。开票主体：" . $subject_title;
        if($result){
            admin_log($admin_log, 1, 't_invoice_subject:' . $id);
            return Response::show(200,'修改开票主体成功');
        }else{
            admin_log($admin_log, 0, 't_invoice_subject:' . $id);
            return Response::show(300,'修改开票主体失败');
        }
    }
    public function invoice_subject_delete(){
        $id = I('id');

        if(empty($id)){
            return Response::show(300,'请选择要删除的开票主体');
        }
        $check = M('invoice','t_')->where(['invoice_subject'=>$id])->count();
        if($check > 0){
            return Response::show(300,'该主体已有申请开票记录，无法删除');
        }
        $log_result = M('invoice_subject','t_')->field('subject_title')->where(['id'=>$id])->find();
        $result = M('invoice_subject','t_')->where(['id'=>$id])->delete();
        $admin_log = "删除开票主体。开票主体：" . $log_result['subject_title'];
        if($result){
            admin_log($admin_log, 1, 't_invoice_subject:' . $result);
            return Response::show(200,'删除开票主体成功');
        }else{
            admin_log($admin_log, 0, 't_invoice_subject:' . $result);
            return Response::show(300,'删除开票主体失败');
        }
    }

    public function select_corporate_name($id){
        $where['a.id'] = array('eq',$id);

        $result = M('invoice','t_')
                    ->alias('a')
                    ->field('a.id,b.corporate_name,e.settlement_title as title')
                    ->join('left join t_corporate as b on a.cid = b.corporate_id')
                    ->join('left join dsy_settlement_apply as e on a.mseid = e.mseid')
                    ->where($where)
                    ->find();
        if($result){
            return ['state' => 1, 'data' => $result];
        }else{
            return ['state' => 0];
        }
    }

}