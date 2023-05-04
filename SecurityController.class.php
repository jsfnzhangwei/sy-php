<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/4/11
 * Time: 14:17
 */
namespace Admin\Controller;

use \Think\Controller;
use Org\Util\Response;

class SecurityController extends  Controller{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * banner主页
    */
    public function banner_index(){

        $this->display('index');
    }
    /**
     * banner主页信息列表数据
    */
    public function banner_info(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $banner = M('ss_banner');
        $where['status'] = array('neq',3);
        $result = $banner->where($where)->page($page,$limit)->select();
        foreach($result as $key => $value){
            if($value['type'] == 0){
                $url = Banner_IMG.$value['img'];
                $result[$key]['img_url'] = $url;
            }
        }
        $num = $banner->where($where)->page($page,$limit)->count();
        return Response::mjson($result,$num);
    }

    /**
     * 添加修改banner页
    */
    public function add_img(){
        $id = I('id','');
        if(!empty($id)){
            $banner = M('ss_banner')->where("id = $id")->find();
            $this->assign('banner',$banner);
            $this->assign('id',$id);
        }
        $this->display('add');
    }

    /**
     * 添加修改banner操作
    */
    public function do_add(){
        $id = I('pid','');
        $pic = $_FILES['pic'];
        $url = I('url','');
        $banner = M('ss_banner');
        $data['url'] = $url ;
        if(!empty($id)){
            //修改
            if(!empty($pic)){
                $data['img'] = banner_img($pic);
                $data['save_time'] = NOW;
                $data['url'] = $url;
                $result = $banner->where(" id = $id ")->save($data);
                if($result){
                    return Response::show(200,'Success');
                }
            }else{
                $data['save_time'] = NOW;
                $data['url'] = $url;
                $result = $banner->where(" id = $id ")->save($data);
                if($result){
                    return Response::show(200,'Success');
                }
            }
        }else{
            //添加
            if(!empty($pic)){
                $data['img'] = banner_img($pic);
            }else{
                return Response::show(404,'No Picture');
            }
            $data['url'] = $url;
            $data['status'] = 0;
            $data['time'] = NOW;
            $data['save_time'] = NOW;
            $result = $banner->add($data);
            if($result){
                return Response::show(200,'Success');
            }
        }
    }

    /**
     * banner操作
     * type : 1 启用 0 关闭 3 删除
    */
    public function change_status(){
        $ids = I('ids');
        $id = $ids[0];
        $type = I('type');
        $banner = M('ss_banner');
        if($type==0){
            $data['status'] = $type;
            $change = $banner->where(" id = $id ")->save($data);
            if($change){
                return Response::show(200,'Success');
            }
        }elseif($type==1){
            $data['status'] = $type;
            $change = $banner->where(" id = $id ")->save($data);
            if($change){
                return Response::show(200,'Success');
            }
        }elseif($type==3){
            $data['status'] = $type;
            $change = $banner->where(" id = $id ")->save($data);
            if($change){
                return Response::show(200,'Success');
            }
        }
    }

    /**
     * 社保订单页
    */
    public function order_index(){

        $this->display('order_lis');
    }

    /**
     * 社保订单页
    */
    public function order_info(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $ordernum = I('ordernum','');
        $name = I('name','');
        $start = I('start1','');
        $end = I('end','');
        $ssorder = M('ss_security_order');
        if(!empty($ordernum)){
            $where['a.order_num'] = $ordernum;
        }if(!empty($name)){
            $where['b.realname'] = array('like','%'.$name.'%');
        }if(!empty($start) && !empty($end)){
            $where['a.time'] = array('between',array($start,$end));
        }if(!empty($start) && empty($end)){
            $where['a.time'] = array('elt',$end);
        }if(empty($start) && !empty($end)){
            $where['a.time'] = array('egt',$start);
        }
        $where['a.status'] = array('in','2,3');
        $result = $ssorder
            ->join(" as a left join dsy_ss_user as b on a.do_uid = b.id ")
            ->field('a.spid,a.id,a.order_num as ordernum,a.time,a.order_money as price,a.status,b.realname as name,b.mobile')
            ->where($where)
            ->page($page,$limit)
            ->select();
//        $sql = $ssorder->getLastSql();
        $num = $ssorder
            ->join(" as a left join dsy_ss_user as b on a.do_uid = b.id ")
            ->where($where)
            ->count();
        return Response::mjson($result,$num);

    }

    /**
     * 社保订单详情
    */
    public function order_detail(){
        //订单id
        $order_id = I('orderid');
        //参保人id
        $spid = I('spid');
        $order = M('ss_security_order');
        $where['a.id'] = $order_id;
        $where['b.id'] = $spid;
        $result = $order
            ->join('as a left join dsy_ss_security_person as b on a.spid = b.id ')
            ->field('b.name,b.mobile,b.id_num,b.id_city,b.id_property,b.card_positive,b.card_opposite,
            a.is_first,a.security_money,a.fund_money,a.service_money,a.start_time,a.during_time,a.pay_city')
            ->where($where)
            ->find();
        $result['card_positive'] = CARD_IMG.$result['card_positive'];
        $result['card_opposite'] = CARD_IMG.$result['card_opposite'];
//        dump($result);exit();
        $this->assign('result',$result);
        $this->display('order_detail');
    }

    /**
     * 社保订单操作
    */
    public function order_do(){
        $ids = I('ids');
        $id = $ids[0];
        $order = M('ss_security_order');
        $data['status'] = 3;
        $where['id'] = $id;
        $result = $order->where($where)->save($data);
        if($result){
            return Response::show(200,'Success');
        }else{
            return Response::show(400,'Fail');
        }
    }

    /**
     * 社保补缴订单
    */
    public function after_payment(){

        $this->display('after_payment');
    }

    /**
     * 社保补缴订单信息
    */
    public function after_payment_info(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $ordernum = I('ordernum','');
        $name = I('name','');
        $start = I('start1','');
        $end = I('end','');
        $after_payment = M('ss_after_payment');
        if(!empty($ordernum)){
            $where['a.order_num'] = $ordernum;
        }if(!empty($name)){
            $where['a.name'] = array('like','%'.$name.'%');
        }if(!empty($start) && !empty($end)){
            $where['a.time'] = array('between',array($start,$end));
        }if(!empty($start) && empty($end)){
            $where['a.time'] = array('elt',$end);
        }if(empty($start) && !empty($end)){
            $where['a.time'] = array('egt',$start);
        }
        $where['status'] = array('neq',0);
        $result = $after_payment
            ->join('as a left join dsy_ss_service_money as c on a.type = c.servicename ')
            ->where($where)
            ->field('a.id,a.name,a.order_num,a.order_money,a.time,a.status,c.money,a.type')
            ->page($page,$limit)
            ->select();
        $num =  $after_payment
            ->join('as a left join dsy_ss_service_money as c on a.type = c.servicename ')
            ->where($where)
            ->count();
        return Response::mjson($result,$num);
    }

    /**
     * 社保补缴订单详情
    */
    public function after_payment_detail(){
        $after_order_id = I('orderid');
        $after_payment = M('ss_after_payment');
        $where['a.id'] = $after_order_id;
        $result = $after_payment
            ->join('as a left join dsy_ss_user as b on a.do_uid = b.id ')
            ->where($where)
            ->field('b.mobile,b.id_number,b.reg_resid_city as id_city,b.reg_resid_type as id_type,b.code_positive as id_positive,b.code_side as id_side
             ,a.name,a.city,a.start_time,a.end_time,a.during_time,a.order_money,a.message
             ')
            ->find();
        $result['id_positive'] = CARD_IMG.$result['id_positive'];
        $result['id_side'] = CARD_IMG.$result['id_side'];
        $this->assign('result',$result);
        $this->display('after_payment_detail');
    }

    /**
     * 社保补缴操作
     * type:1:通过 2:取消 3:办理
    */
    public function sdo(){
        $ids = I('ids');
        $id = $ids[0];
        $type = I('type');
        $after_payment = M('ss_after_payment');
        if($type == 1){
            $after_payment->status = 2;
        }elseif($type == 2){
            $after_payment->status = 6;
        }elseif($type == 3){
            $after_payment->status = 4;
        }

        $after_payment->where("id = $id")->save();
        if($after_payment){
            return Response::show(200,'Success');
        }
    }


    /**
     * 社保计算器页
     */
    public function counter_list(){

        $this->display('counter_list');
    }

    /**
     * 社保计算器页列表信息
     */
    public function counter_info(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $city_name = I('city_name','');
        if(!empty($city_name)){
            $where['city_name'] = array('like','%'.$city_name.'%');
        }
        $city = M('ss_city_base');
        $where['status'] = 1;
        $result = $city
            ->where($where)
            ->field('id,city_name,time,is_auth')
            ->page($page,$limit)
            ->select();
        $num = $city
            ->where($where)
            ->count();

        return Response::mjson($result,$num);
    }


    /**
     * 社保计算器创建/修改页
     */
    public function counter(){
        $id = I('counter_id','');
        if(!empty($id)){
            $city = M('ss_city_base');
            $result = $city->find($id);
            $this->assign('result',$result);
            $this->assign('id',$id);
        }
        $this->display('counter');
    }

    /**
     * 社保计算器删除
     */
    public function counter_del(){
        $ids = I('ids');
        $id = $ids[0];
        $city = M('ss_city_base');
        $where['id'] = $id;
        $city->status = 0;
        $result = $city->where($where)->save();
        if($result){
            return Response::show(200,'Success');
        }

    }

    /**
     * 社保计算器设置操作
    */
    public function counter_do(){
        $id = I('id','');
        //省市区ID
        $data['prov_num'] = I('province_id');
        $data['city_num'] = I('city_id');
        //省市区名字
        $data['prov_name'] = I('province_name');
        $data['city_name'] = I('city_name');
        $data['is_auth'] = I('auth');
        $data['security_base_min'] = I('sbase');
        $data['fund_base_min'] = I('fbase');
        $data['security_base_max'] = I('sbasemax');
        $data['fund_base_max'] = I('fbasemax');
        $data['servicem'] = I('smoney');
        $data['penson_roportion_p'] = I('pyanglao');
        $data['medical_roportion_p'] = I('pyiliao');
        $data['injury_roportion_p'] = I('pgongshang');
        $data['birth_roportion_p'] = I('pshengyu');
        $data['unem_roportion_p'] = I('pshiye');
        $data['fund_roportion_p'] = I('pfund');
        $data['penson_roportion_c'] = I('cyanglao');
        $data['medical_roportion_c'] = I('cyiliao');
        $data['injury_roportion_c'] = I('cgongshang');
        $data['birth_roportion_c'] = I('cshengyu');
        $data['unem_roportion_c'] = I('cshiye');
        $data['fund_roportion_c'] = I('cfund');
        $data['time'] = NOW;
        $data['save_time'] = NOW;
        foreach($data as $value){
            if(empty($value)){
                return Response::show(400,'请填写完整再提交!');
            }
        }
        $model = M('ss_city_base');
        if(!empty($id)){
            //修改
            $result = $model->where("id = $id")->save($data);
        }else{
            //创建
            $result = $model->add($data);
        }
        if($result){
            return Response::show(200,'Success');
        }

    }



    /**
     * 服务费设置列表
    */
    public function service_money_index(){

        $this->display('service_money');
    }

    /**
     * 服务费设置列表信息
     */
    public function service_money_info(){
        $service_money = M('ss_service_money');
        $result = $service_money->select();
        $num = 11;
        return Response::mjson($result,$num);

    }

    /**
     * 服务费设置页
    */
    public function service_money_set(){
        $smoney = I('smoney');//服务费id
        $service_money = M('ss_service_money');
        $result = $service_money->where("id = $smoney")->find();
        $money = $result['money'];
        $this->assign('money',$money);
        $this->assign('id',$smoney);
        $this->display('service_money_set');
    }

    /**
     * 服务费设置操作
     */
    public function service_money_set_do(){
        $id = I('id');//服务费id
        $money = I('money');//用户输入服务费
        $service_money = M('ss_service_money');
        $service_money->money = $money;
        $result = $service_money->where("id = $id")->save();
        if($result){
            return Response::show(200,'Success');
        }
    }

    /**
     * 客服电话设置
    */
    public function mobile(){
        $mobile = M('ss_mobile');
        $result = $mobile->select();
        $this->assign('result',$result);
        $this->display('mobile');

    }
    /**
     * 客服电话设置操作
    */
    public function mobile_do(){
        $mobile = M('ss_mobile');
        $mob = I('mobile','');
        $id = I('id','');
        if(empty($id)){
            //新建数据
            $data['mobile'] = $mob;
            $result = $mobile->add($data);
            if($result){
                return Response::show(200,'Success');
            }
        }else{
            //修改数据
            $mobile->mobile = $mob;
            $result = $mobile->where("id = $id")->save();
            if($result){
                return Response::show(200,'Success');
            }
        }
    }


    /**
     * 用户协议页
    */
    public function agreement(){

        $this->display('agreement_index');
    }

    /**
     * 用户协议页列表
    */
    public function agreement_info(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $agreement = M('ss_agreement');
        $where['is_use'] = array('neq',0);
        $result = $agreement
                ->field('id,title,is_use,time,save_time')
                ->where($where)
                ->page($page,$limit)
                ->select();
        $num = $agreement ->where($where)->count();

        return Response::mjson($result,$num);
    }

    /**
     * 用户协议页编辑
     */
    public function agreement_edit(){
        $id = I('id','');
        if(empty($id)){
            $this->display('agreement_edit');
        }else{
            $agreement = M('ss_agreement');
            $result = $agreement->find($id);
            $this->assign('result',$result);
            $this->display('agreement_edit');
        }
    }

    /**
     * 用户协议页编辑/新建 操作
     */
    public function agreement_edit_do(){
        $content = $_REQUEST['content'];
        $title = I('title','');
        $id = I('id','');
        if(empty($id)){
            //新建操作
            $data['title'] = $title;
            $data['content'] = $content;
            $data['time'] = NOW;
            $data['save_time'] = NOW;
            $result = M('ss_agreement')->add($data);
            if($result){
                return Response::show(200,'Success');
            }
        }else{
            //更新操作
            $data['title'] = $title;
            $data['content'] = $content;
            $data['save_time'] = NOW;
            $result = M('ss_agreement')->where("id = $id")->save($data);
            if($result){
                return Response::show(200,'Success');
            }
        }

    }

    /**
     * 用户协议状态操作
     * 0 删除 1 启用 2 不启用
    */
    public function agreement_change(){
        $type = I('type');
        $ids = I('ids');
        $id  = $ids[0];
        $agreement = M('ss_agreement');
        if($type == 1){
            $agreement->is_use = 1;
        }elseif($type == 2){
            $agreement->is_use = 2;
        }elseif($type == 0){
            $agreement->is_use = 0;
        }
        $result = $agreement->where("id  = $id")->save();
        if($result){
            return Response::show(200,'Success');
        }

    }


    /**
     * 企业人事需求管理
    */
    public function company_hr(){

        $this->display('more_service');
    }

    /**
     * 企业人事需求列表
     */
    public function company_hr_info(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $ordernum = I('ordernum','');
        $name = I('name','');
        $start = I('start1','');
        $end = I('end','');
        $hr = M('ss_hr');
        if(!empty($ordernum)){
            $where['a.order_num'] = $ordernum;
        }if(!empty($name)){
            $where['b.name'] = array('like','%'.$name.'%');
        }if(!empty($start) && !empty($end)){
            $where['a.time'] = array('between',array($start,$end));
        }if(!empty($start) && empty($end)){
            $where['a.time'] = array('elt',$end);
        }if(empty($start) && !empty($end)){
            $where['a.time'] = array('egt',$start);
        }
        $result = $hr
            ->where($where)
            ->join('as a left join dsy_ss_user as b on a.do_uid = b.id')
            ->field('a.id,a.order_num,b.realname,a.name,a.mobile,a.time')
            ->page($page,$limit)
            ->select();
        $num =  $hr
            ->where($where)
            ->join('as a left join dsy_ss_user as b on a.do_uid = b.id')
            ->count();
        return Response::mjson($result,$num);

    }

    /**
     * 企业人事需求详情
     */
    public function company_hr_detail(){
        $id = I('orderid','');
        $hr = M('ss_hr');
        $result = $hr
            ->where("id = $id")
            ->find();
//        dump($result);exit;
        $this->assign('result',$result);
        $this->display('more_service_detail');
    }

}