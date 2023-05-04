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

class ShopOrderController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }
    /**
     * 商品订单首页
     */
    public function shop_order_index(){
        $start = date('Y-m-01', strtotime(date("Y-m-d")));
        $end = date('Y-m-d');
        $this->assign('start',$start);
        $this->assign('end',$end);
        $this->display();
    }
    /**
     * 商品订单数据
     */
    public function shop_order_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $order = I('order_id','');//苏鹰订单
        $sn_order = I('sn_order');//苏宁订单
        $name = I('name','');//收货人姓名
        $status = I('status','');//订单状态
        $start_time = I('start1');//开始时间
        $end_time = I('end');//结束时间

        $where['a.status'] = array('neq',8);
        if(!empty($order)){
            $where['a.order_num'] = array('eq',$order);
        }
        if(!empty($sn_order)){
            $where['a.sn_order_num'] = array('eq',$sn_order);
        }
        if(!empty($name)){
            $where['a.address_name'] = array('eq',$name);
        }
        if($status != ''){
            $where['a.status'] = array('in',$status);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }

        $result = M('sn_order')
                    ->alias('a')
                    ->field('a.id,a.order_num,a.sn_order_num,a.create_time,a.pay_type,a.status,a.total_freight,a.total_fee')
                    ->where($where)
                    ->order('a.create_time desc')
                    ->page($page,$limit)
                    ->select();
        $num = M('sn_order')
                    ->alias('a')
                    ->where($where)
                    ->count();
        foreach($result as $key=>$val){
            $deal_sn_data = $this->plan_sn_profit($val['id'],$val['status']);
            $result[$key]['profit_price'] = $deal_sn_data['profit_price'];
            $result[$key]['cost_price'] = $deal_sn_data['cost_price'];
            $result[$key]['total_freight'] = ((int)($result[$key]['total_freight'] * 100) - (int)($deal_sn_data['after_freight'] * 100)) /100;
            $result[$key]['total_fee'] = ((int)($result[$key]['total_fee']* 100) - (int)($deal_sn_data['after_price'] * 100)) / 100;
            $result[$key]['count_price'] = $result[$key]['total_freight'] + $result[$key]['total_fee'];
            $result[$key]['cname'] = '苏鹰自营';
            $result[$key]['isneedinvoice'] = '无需发票';
        }
        return Response::mjson($result,$num);
    }
    /**
     * 订单详情
     */
    public function shop_order_details(){
        $id = I('id','');
        $sy_order = I('sy_orderid','');
        $sn_order = I('sn_orderid','');
        if(empty($id) || empty($sy_order) || empty($sn_order)){
            return false;
        }else{
            $where['a.id'] = array('eq',$id);
            $where['a.order_num'] = array('eq',$sy_order);
            $where['a.sn_order_num'] = array('eq',$sn_order);
            $result = M('sn_order')
                        ->alias('a')
                        ->field('a.*,b.name,b.mobile,b.region,b.address as url,c.user_name')
                        ->join('LEFT JOIN dsy_mall_receipt as b on a.receiver_id=b.id')
                        ->join('LEFT JOIN t_user as c on a.user_id=c.user_id')
                        ->where($where)
                        ->find();
            $result['status_msg'] = $this->shop_order_state($result['status']);//获取订单状态
            $result['count_money'] = $result['total_freight'] + $result['total_fee'];
            $shop_list = M('sn_order_item')->where('order_id='.$result['id'])->select();
            $count_freight = 0;
            $count = count($shop_list) - 1;
            foreach($shop_list as $ky=>$vl){
                if($ky != $count){
                    $shop_list[$ky]['count_freight'] = round($vl['product_price'] * $vl['product_num'] * $result['total_freight'] / $result['total_fee'],2);
                    $count_freight +=  $shop_list[$ky]['count_freight'];
                }else{
                    $shop_list[$ky]['count_freight'] = $result['total_freight']  - $count_freight;
                }
                $shop_list[$ky]['count_price'] = $vl['product_price'] * $vl['product_num'];
                $shop_list[$ky]['product_price'] = $vl['product_price'] + 0;
            }
            $shop_log = M('sn_order_log')->where("sn_order_id=" . '"' . $result['sn_order_num'] .'"')->select();//订单日志
            $num = 1;
            //物流信息
            $msg = [];
            if($result['status'] == 1 || $result['status'] == 2 || $result['status'] == 3 || $result['status'] == 9){
                $shop_data = [];
                $curl = JAVA_API_URL_SN . "/order/findOrderItemLogistics?orderNum=".$result['order_num'];
                $shop_wl  = $this->shop_logistics_get($curl);
                if($shop_wl['code'] == 200){
                    if($shop_wl['data']['isPackage'] == 'Y'){
                        $shop_arr = $shop_wl['data']['logistics'];
                        $num2 = 1;
                        foreach($shop_arr as $key=>$val){
                            $shop_data[$key]['num'] = $num2++;
                            $shop_data[$key]['expressCompany'] = $val['expressCompany'];//物流公司名称
                            $shop_data[$key]['logisticsInfoList'] = $val['logisticsInfoList'];//物流状态
                            $shop_data[$key]['logisticNumber'] = $val['logisticNumber'];//物流单号
                            $shop_data[$key]['shippingTime'] = $val['shippingTime'];//发货时间
                        }
                        $msg = ['code'=>200,'msg'=>'获取物流信息成功'];
                    }else{
                        $msg = ['code'=>300,'msg'=>'暂无包裹信息'];
                    }
                }else{
                    $msg = ['code'=>$shop_wl['code'],'msg'=>$shop_wl['message']];
                }
            }else{
                $msg = ['code'=>400,'msg'=>'暂无物流信息'];
            }
            $count = count($shop_list);
            $this->assign('result',$result);
            $this->assign('shop_list',$shop_list);
            $this->assign('count',$count);
            $this->assign('count_price',$count_price);
            $this->assign('num',$num);
            $this->assign('shop_data',$shop_data);
            $this->assign('msg',$msg);//物流信息
            $this->assign('shop_log',$shop_log);//操作日志
            $this->assign('url',JAVA_API_URL_SN);
            $this->display('shop_order_details');
        }
    }
    /**
     * 取消订单
     */
    public function shop_order_cancel(){
        $id = I('id');
        $ordernum = I('orderNum');
        if(empty($id) && empty($ordernum)){
            return Response::show(300,'数据异常,非法提交');
        }
        $admin_log = '取消订单:订单号【' .$ordernum. '】';
        $result = text_curl_get(JAVA_API_URL_SN . '/order/closeNotPayOrder?orderNum=' . $ordernum);
        if($result['code'] == 200){
            admin_log($admin_log, 1, 'dsy_sn_order:' . $id);
            return Response::show(200,'取消订单成功');
        }else{
            admin_log($admin_log, 0, 'dsy_sn_order:' . $id);
            return Response::show(300,'取消订单失败');
        }
    }
    /**
     * 下单流水/利润
     */
    public function shop_order_runwater(){
        $start_time = I('stime','');
        $end_time = I('etime','');
        $order = I('ordernum','');
        $sn_order= I('wzordernum','');
        $status = I('status','');
        if(!empty($order)){
            $where['a.order_num'] = array('eq',$order);
        }
        if(!empty($sn_order)){
            $where['a.sn_order_num'] = array('eq',$sn_order);
        }
        if($status != ''){
            $where['a.status'] = array('in',$status);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }

        $result = M('sn_order')
                    ->alias('a')
                    ->field('a.id,a.order_num,a.sn_order_num,a.create_time,a.pay_type,a.status,a.pay_type,a.total_freight,a.total_fee')
                    ->join('LEFT JOIN dsy_mall_receipt as b on a.receiver_id=b.id')
                    ->where($where)
                    ->select();
        $profit_price = 0;//总利润
        $count_price = 0;//总实付价格
        foreach($result as $key=>$val){
                $profits = 0;//利润
                if(in_array($val['status'],[1,2,3,9])){//判断订单状态
                    $result_price = M('sn_order_item')
                            ->alias('a')
                            ->field('a.product_price,a.product_num,b.naked_price,a.item_status')
                            ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                            ->where('a.order_id=' . $val['id'])
                            ->select();
                    foreach($result_price as $vl){
                        if(in_array($vl['item_status'],[1,2,8,9])){
                            $profit = round(($vl['product_price']-$vl['naked_price']),2);//单件商品的利润
                            $profits += $profit * $vl['product_num'];//此商品的所有利润
                        }
                    }
                    $profit_price += $profits;
                    $count_price += $val['total_freight'] + $val['total_fee'];
                }
        }
        return $this->ajaxReturn(['money'=>$count_price,'profit'=>$profit_price]);
    }
    /**
     * 退款管理列表
     */
    public function shop_refund_index(){
        $start = date('Y-m-01', strtotime(date("Y-m-d")));
        $end = date('Y-m-d');
        $this->assign('url',JAVA_API_URL_SN);
        $this->assign('start',$start);
        $this->assign('end',$end);
        $this->display();
    }
    /**
     * 退款列表数据
     */
    public function shop_refund_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $type = I('type','');
        $sn_order = I('sn_order');
        $start_time = I('start1','');
        $end_time = I('end','');
        $status = I('status','');
        if($type == ''){
            $where['a.item_after_sale_type'] = array('in','0,1');
        }else{
            $where['a.item_after_sale_type'] = array('eq',$type);
        }
        if(!empty($sn_order)){
            $where['a.sn_order_id'] = array('eq',$sn_order);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }
        if($status != ''){
            $where['a.item_after_sale_status'] = array('in',$status);
        }

        $result = M('sn_item_after_sale')
                    ->alias('a')
                    ->field('a.sn_order_id,a.sn_order_item_id,c.user_name,a.refund_id,a.create_time,a.item_after_sale_type,a.after_sale_no,
                            a.item_after_sale_status,a.return_num,a.num,d.product_price,d.item_freight,e.total_freight,e.total_fee')
                    ->join('LEFT JOIN t_user as c on a.user_id=c.user_id')
                    ->join('LEFT JOIN dsy_sn_order_item as d on a.sn_order_item_id=d.sn_order_item_id')
                    ->join('LEFT JOIN dsy_sn_order as e on a.sn_order_id=e.sn_order_num')
                    ->where($where)
                    ->order('a.create_time desc')
                    ->page($page,$limit)
                    ->select();
        $num = M('sn_item_after_sale')
                    ->alias('a')
                    ->join('LEFT JOIN t_user as c on a.user_id=c.user_id')
                    ->join('LEFT JOIN dsy_sn_order_item as d on a.sn_order_item_id=d.sn_order_item_id')
                    ->where($where)
                    ->count();
        foreach($result as $key=>$val){
            if(in_array($val['item_after_sale_status'],[0,2,3,5,9,10,11])){
                $result[$key]['money'] = ($val['num'] * $val['product_price']) + (round($val['num'] * $val['product_price'] * $val['total_freight']/$val['total_fee'],2));//计算总退款金额
            }elseif($val['item_after_sale_status'] == 1){
                $where_refund['refund_id'] = array('in',$val['refund_id']);
                $sales = M('mall_refund')
                        ->field('sum(refund_money) as money')
                        ->where($where_refund)
                        ->find();
                $result[$key]['money'] = $sales['money'];
            }
        }
        return Response::mjson($result,$num);
    }
    /**
     * 退款详情
     */
    public function shop_refund_idea(){
        $id = I('id','');
        if(!empty($id)){
            $where['a.sn_order_item_id'] = array('eq',$id);
            $result = M('sn_item_after_sale')
                        ->alias('a')
                        ->field('a.id,a.sn_order_item_id,b.id,b.order_num,b.create_time as order_time,b.total_freight,b.total_fee,a.refund_id,a.create_time,a.item_after_sale_status,
                        a.after_sale_no,a.return_num,a.num,a.return_reason,a.return_reason_detail,d.product_name,d.product_price,d.item_freight,d.is_fac')
                        ->join('LEFT JOIN dsy_sn_order as b on a.sn_order_id=b.sn_order_num')
                        ->join('LEFT JOIN dsy_sn_order_item as d on a.sn_order_item_id=d.sn_order_item_id')
                        ->where($where)
                        ->find();
            $result['is_fac'] = ($result['is_fac'] == 1) ? 1 : 0;
            if(in_array($result['item_after_sale_status'],[0,2,3,9,10,11])){//都是没有退款完成的状态
                $result['money'] = ($result['num'] * $result['product_price']) + (round($result['num'] * $result['product_price'] * $result['total_freight'] / $result['total_fee'],2));//计算总退款金额
                $result['shop_num'] = $result['num'];
            }elseif($result['item_after_sale_status'] == 1){
                $where_refund['refund_id'] = array('in',$result['refund_id']);
                $sales = M('mall_refund')
                        ->field('sum(refund_money) as money')
                        ->where($where_refund)
                        ->find();
                $result['money'] = $sales['money'];
                $result['shop_num'] = $result['return_num'];
            }
            $count_price = $result['total_fee'] + $result['total_freight'];
            $count_freight = 0;//总运费
            $shop_list = M('sn_order_item')->where('order_id='.$result['id'])->select();
            $count = count($shop_list) - 1;
            foreach($shop_list as $ky=>$vl){
                if($ky != $count){
                    $shop_list[$ky]['count_freight'] = round($vl['product_price'] * $vl['product_num'] * $result['total_freight'] / $result['total_fee'],2);
                    $count_freight +=  $shop_list[$ky]['count_freight'];
                }else{
                    $shop_list[$ky]['count_freight'] = $result['total_freight']  - $count_freight;
                }
                $shop_list[$ky]['count_price'] = $vl['product_price'] * $vl['product_num'];
                $shop_list[$ky]['product_price'] = $vl['product_price'] + 0;
            }
            $num  = 1;
            $count = count($shop_list);
            $this->assign('result',$result);
            $this->assign('shop_list',$shop_list);
            $this->assign('count',$count);
            $this->assign('num',$num);
            $this->assign('count_price',$count_price);
            $this->assign('url',JAVA_API_URL_SN);
            $this->display();
        }else{
            echo 3;
        }
    }
    /**
     * 提前退款
     */
    public function shop_order_refund(){
        $snOrderItemId = I('snOrderItemId');
        $isFac = I('isFac');
        $after_sale_no = I('after_sale_no');
        $id = I('id');
        if(empty($snOrderItemId) && empty($isFac) && empty($after_sale_no)){
            return Response::show(300,'数据异常,非法提交');
        }
        $data = [
            'snOrderItemId' => $snOrderItemId,
            'isFac' => $isFac
        ];
        $admin_log = '同意退款:售后编号【' . $after_sale_no . '】,苏宁订单号【' . $snOrderItemId . "】";
        $result = text_curl(JAVA_API_URL_SN . '/before/afterSale/advanceReturnMoney',$data);
        if($result['code'] == 200){
            admin_log($admin_log, 1, 'dsy_sn_item_after_sale:' . $id);
            return Response::show(200,'退款成功');
        }else{
            admin_log($admin_log, 0, 'dsy_sn_item_after_sale:' . $id);
            return Response::show(300,'退款失败,请稍后重试');
        }
    }
    /**
     * 退货列表
     */
    public function shop_sales_index(){
        $start = date('Y-m-01', strtotime(date("Y-m-d")));
        $end = date('Y-m-d');
        $this->assign('start',$start);
        $this->assign('end',$end);
        $this->display();
    }
    /**
     * 退货列表数据
     */
    public function shop_sales_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $sn_order = I('sn_order','');
        $start_time = I('start1','');
        $end_time = I('end','');
        $status = I('status','');
        $name = I('name','');
        $where['a.item_after_sale_type'] = array('eq','1');
        if(!empty($sn_order)){
            $where['a.sn_order_id'] = array('eq',$sn_order);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }
        if($status != ''){
            $where['a.item_after_sale_status'] = array('in',$status);
        }
        if(!empty($name)){
            $where['_query'] = "phone=$name&address_name=$name&_logic=or";
        }
        $result = M('sn_item_after_sale')
                    ->alias('a')
                    ->field('a.sn_order_id,a.sn_order_item_id,c.user_name,a.refund_id,a.create_time,a.after_sale_no,a.sn_item_after_sale_num,
                    a.item_after_sale_status,e.create_time as order_time,f.is_fac')
                    ->join('LEFT JOIN t_user as c on a.user_id=c.user_id')
                    ->join('LEFT JOIN dsy_sn_order_item as d on a.sn_order_item_id=d.sn_order_item_id')
                    ->join('LEFT JOIN dsy_sn_order as e on a.sn_order_id=e.sn_order_num')
                    ->join('dsy_sn_product_extend as f on d.sku_id=f.sku_id')
                    ->where($where)
                    ->order('a.create_time desc')
                    ->page($page,$limit)
                    ->select();
        $num = M('sn_item_after_sale')
                    ->alias('a')
                    ->join('LEFT JOIN t_user as c on a.user_id=c.user_id')
                    ->join('LEFT JOIN dsy_sn_order_item as d on a.sn_order_item_id=d.sn_order_item_id')
                    ->join('LEFT JOIN dsy_sn_order as e on a.sn_order_id=e.sn_order_num')
                    ->join('dsy_sn_product_extend as f on d.sku_id=f.sku_id')
                    ->where($where)
                    ->count();
        return Response::mjson($result,$num);
    }
    /**
     * 退货详情
     */
    public function shop_sales_idea(){
        $id = I('id','');
        if(!empty($id)){
            $where['a.sn_order_item_id'] = array('eq',$id);
            $result = M('sn_item_after_sale')
                        ->alias('a')
                        ->field('b.id as order_id,b.order_num,c.user_name,a.*,b.total_freight,b.total_fee,
                        d.product_name,d.product_price,d.product_img,d.item_freight,f.value_list,b.address_name,b.phone,b.sn_order_num,f.is_fac')
                        ->join('LEFT JOIN dsy_sn_order as b on a.sn_order_id=b.sn_order_num')
                        ->join('LEFT JOIN t_user as c on a.user_id=c.user_id')
                        ->join('LEFT JOIN dsy_sn_order_item as d on a.sn_order_item_id=d.sn_order_item_id')
                        ->join('LEFT JOIN dsy_sn_product_extend as f on d.sku_id=f.sku_id')
                        ->where($where)
                        ->find();
            //根据退货状态计算退货金额
            if(in_array($result['item_after_sale_status'],[0,2,3,5,9,11])){
                $result['money'] = ($result['num'] * $result['product_price']) + (round($result['num'] * $result['product_price'] * $result['total_freight'] / $result['total_fee'],2));//计算总退款金额
                $result['count_product_price'] = $result['num'] * $result['product_price'];//退货商品小计
                $result['shop_num'] = $result['num'];
            }elseif($result['item_after_sale_status'] == 1){
                $result['money'] = ($result['num'] * $result['product_price']) + (round($result['num'] * $result['product_price'] * $result['total_freight'] / $result['total_fee'],2));
                $result['shop_num'] = $result['return_num'];//退款完成数量
                $result['count_product_price'] = $result['return_num'] * $result['product_price'];
            }
            $result['product_price'] = $result['product_price'] + 0;
            //查看商品规格
            $value_list = json_decode($result['value_list']);
            foreach($value_list as $key=>$val){
                if($key == "规格"){
                    foreach($val as $ky=>$vl){
                        $arr[] = [
                            'name' => $ky,
                            'content' => $vl
                        ];
                    }
                }
            }
            $result['count_price'] = $result['total_freight']+ $result['total_fee'];//总实付+总运费
            $this->assign('result',$result);
            $this->assign('value_list',$arr);
            $this->display();
        }else{
            echo 3;
        }
    }
    /**
     * 商城订单汇总(含苏宁,苏鹰,京东)
     */
    public function shop_order_allindex(){
        $start = date('Y-m-01', strtotime(date("Y-m-d")));
        $end = date('Y-m-d');
        $this->assign('start',$start);
        $this->assign('end',$end);
        $this->display();
    }
    /**
     * 汇总订单数据
     */
    public function shop_order_alldata(){
        $pageIndex = $_REQUEST['pageIndex'];
        $platform = I('platform','');
        $start_time = I('start1','');
        $end_time = I('end','');
        $status = I('status','');
        $result = [
            'data' => '',
            'num' => '',
        ];
        if(empty($platform)){
            $result = $this->order_jd_sn_sy($start_time,$end_time,$status,$pageIndex);
        }else{
            if($platform == 1){
                $result = $this->order_sn_data($start_time,$end_time,$status,$pageIndex);
            }elseif($platform == 2){
                $result = $this->order_jd_data($start_time,$end_time,$status,$pageIndex);
            }else{
                $result = $this->order_sy_data($start_time,$end_time,$status,$pageIndex);
            }
        }
        return Response::mjson($result['data'],$result['num']);
    }
    /**
     * 商城总订单
     */
    public function order_jd_sn_sy($start_time,$end_time,$status,$pageIndex=''){
        $page = $pageIndex * 10;
        $limit = 10;
        if($status != ''){
            $sn_status = [0=>'0',1=>'1',2=>'2',3=>'3',4=>'9',5=>'5',6=>'4,7'];
            $jd_status = [0=>'1',1=>'2',2=>'4',3=>'5',4=>'7',5=>'6,8',6=>'3'];
            $where_jd[] = 'a.status in('.$jd_status[$status].')';
            $where_sn[] = 'status in('.$sn_status[$status].')';
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where_jd[] = "a.time like'%$start_time%'";
                $where_sn[] = "create_time like'%$start_time%'";
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where_jd[] = "a.time between '$start_time' and '$end_time'";
                $where_sn[] = "create_time between '$start_time' and '$end_time'";
            }
        }else{
            $day = date('Y-m');
            $where_jd[] = "a.time like'%$day%'";
            $where_sn[] = "create_time like'%$day%'";
        }
        $where_jd = implode(' and ',$where_jd);
        $where_sn = implode(' and ',$where_sn);
        $Model = new \Think\Model();
        if($pageIndex != ''){
            $result = $Model
            ->table('dsy_sn_order')
            ->field('id,order_num AS ordernum,sn_order_num AS third_party_order,create_time AS time,pay_type,total_freight+total_fee AS count_price,
                    status,total_freight,total_fee,0 AS sid,0 AS type,0 AS pid,sy_card_id AS use_card,use_quota_id AS use_quota,0 AS card_amount,0 AS quota_amount,order_fee AS pay_price,0 AS pay_state')
            ->union("SELECT a.id,a.ordernum,d.wz_orderid AS third_party_order,a.time,a.paytype AS pay_type,a.price AS count_price,a.status,
                    a.freight AS total_freight,a.pprice AS total_fee,a.sid,c.shop_name AS type,d.pid,d.use_card,d.use_quota,d.card_amount,d.quota_amount,d.receipt_amount AS pay_price,d.status AS pay_state 
                    FROM dsy_mall_order AS a 
                    LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id 
                    LEFT JOIN dsy_mall_order_notpay AS d ON a.order_notpay_num = d.ordernum 
                    WHERE $where_jd 
                    ORDER BY time desc 
                    LIMIT $page,$limit")
            ->where($where_sn)
            ->select();
        }else{
            $result = $Model
            ->table('dsy_sn_order')
            ->field('id,order_num AS ordernum,sn_order_num AS third_party_order,create_time AS time,pay_type,total_freight+total_fee AS count_price,
                    status,total_freight,total_fee,0 AS sid,0 AS type,0 AS pid,sy_card_id AS use_card,use_quota_id AS use_quota,0 AS card_amount,0 AS quota_amount,order_fee AS pay_price,0 AS pay_state')
            ->union("SELECT a.id,a.ordernum,d.wz_orderid AS third_party_order,a.time,a.paytype AS pay_type,a.price AS count_price,a.status,
                    a.freight AS total_freight,a.pprice AS total_fee,a.sid,c.shop_name AS type,d.pid,d.use_card,d.use_quota,d.card_amount,d.quota_amount,d.receipt_amount AS pay_price,d.status AS pay_state 
                    FROM dsy_mall_order AS a 
                    LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id 
                    LEFT JOIN dsy_mall_order_notpay AS d ON a.order_notpay_num = d.ordernum 
                    WHERE $where_jd 
                    ORDER BY time desc")
            ->where($where_sn)
            ->select();            
        }
        $nums = $Model
        ->table('dsy_sn_order')
        ->field('id')
        ->union("SELECT a.id
                FROM dsy_mall_order AS a 
                LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id 
                LEFT JOIN dsy_mall_order_notpay AS d ON a.order_notpay_num = d.ordernum 
                WHERE $where_jd")
        ->where($where_sn)
        ->select();
        $nums = count($nums);
        $count_price = 0;//下单流水
        $count_freight = 0;//下单利润
        foreach($result as $key=>$value){
            if(empty($value['type'])){
                $result[$key]['type'] = '苏鹰商城-苏宁频道';
            }
            if($value['sid'] == 0){//苏宁订单
                if($value['pay_type'] != 0){
                    $deal_sn_pay = $this->pay_type($value['use_quota'],$value['use_card'],$value['pay_price'],$value['pay_type'],$value['quota_amount'],$value['card_amount'],1);
                    $result[$key]['pay_str'] = $deal_sn_pay['pay_str'];
                    $result[$key]['pay_detail'] = $deal_sn_pay['pay_detail'];
                }
                $deal_sn_data = $this->plan_sn_profit($value['id'],$value['status'],$value['third_party_order']);
                $result[$key]['after_sale'] = $deal_sn_data['after_sale'];//有无售后
                $result[$key]['profit_price'] = $deal_sn_data['profit_price'];//利润
                $result[$key]['cost_price'] = $deal_sn_data['cost_price'];//成本价
                $result[$key]['total_freight'] = ((int)($result[$key]['total_freight'] * 100) - (int)($deal_sn_data['after_freight'] * 100)) /100;
                $result[$key]['total_fee'] = ((int)($result[$key]['total_fee']* 100) - (int)($deal_sn_data['after_price'] * 100)) / 100;
                $result[$key]['count_price'] = $result[$key]['total_freight'] + $result[$key]['total_fee'];
                if(in_array($value['status'],[1,2,3,9])){
                    $count_price += $result[$key]['count_price'];
                }
            }else{//京东订单和自营订单
                if($value['pay_state'] == 2){
                    $deal_sn_pay = $this->pay_type($value['use_quota'],$value['use_card'],$value['pay_price'],$value['pay_type'],$value['quota_amount'],$value['card_amount'],2);
                    $result[$key]['pay_str'] = $deal_sn_pay['pay_str'];
                    $result[$key]['pay_detail'] = $deal_sn_pay['pay_detail'];
                }
                $deal_jd_data = $this->plan_jd_profit($value['pid'],$value['status'],$value['ordernum']);
                $result[$key]['after_sale'] = $deal_jd_data['after_sale'];//有无售后
                $result[$key]['cost_price'] = $deal_jd_data['cost_price'];
                $result[$key]['profit_price'] = $deal_jd_data['profit_price'];
                $result[$key]['count_price'] = ((int)($result[$key]['count_price']*100) - (int)($deal_jd_data['after_price']*100)) / 100;
                $result[$key]['total_fee'] = ((int)($result[$key]['total_fee']*100) - (int)($deal_jd_data['after_price']*100)) / 100;
                if(in_array($value['status'],[2,4,5,7])){
                    $count_price += $result[$key]['count_price'];
                }
            }
            $count_freight += $result[$key]['profit_price'];
        }
        $data = [
            'data' => $result,
            'num' => $nums,
            'count_price' => $count_price,
            'count_freight' => $count_freight
        ];
        return $data;
    }
    /**
     * 苏宁订单
     * @param start_time开始时间
     * @param end_time结束时间
     * @param status订单状态
     */
    public function order_sn_data($start_time,$end_time,$status,$pageIndex){
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        if($status != ''){
            $sn_status = [0=>'0',1=>'1',2=>'2',3=>'3',4=>'9',5=>'5',6=>'4,7'];
            $where['a.status'] = array('in',$sn_status[$status]);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }
        if($pageIndex != ''){
            $result = M('sn_order')
                        ->alias('a')
                        ->field('a.id,a.order_num as ordernum,a.sn_order_num as third_party_order,a.sy_card_id AS use_card,a.use_quota_id AS use_quota,a.order_fee AS pay_price,
                        a.create_time as time,a.pay_type,a.status,a.total_freight,a.total_fee,0 as sid')
                        ->where($where)
                        ->order('a.create_time desc')
                        ->page($page,$limit)
                        ->select();
        }else{
            $result = M('sn_order')
                        ->alias('a')
                        ->field('a.id,a.order_num as ordernum,a.sn_order_num as third_party_order,a.sy_card_id AS use_card,a.use_quota_id AS use_quota,a.order_fee AS pay_price,
                        a.create_time as time,a.pay_type,a.status,a.total_freight,a.total_fee,0 as sid')
                        ->where($where)
                        ->order('a.create_time desc')
                        ->select();
        }
        if(empty($result)){
            $data = [
                'data' => $result,
                'num' => 0,
                'count_price' => 0,
                'count_freight' => 0,
                'count_num' => 0
            ];
            return $data;
        }
        $num = M('sn_order')
                    ->alias('a')
                    ->where($where)
                    ->count();
        $count_price = 0;//下单流水
        $count_freight = 0;//下单利润
        $count_num = 0;//有限成单数量
        foreach($result as $key=>$val){
            if($val['pay_type'] != 0){
                $deal_sn_pay = $this->pay_type($val['use_quota'],$val['use_card'],$val['pay_price'],$val['pay_type'],0,0,1);
                $result[$key]['pay_str'] = $deal_sn_pay['pay_str'];
                $result[$key]['pay_detail'] = $deal_sn_pay['pay_detail'];
            }
            $deal_sn_data = $this->plan_sn_profit($val['id'],$val['status'],$val['third_party_order']);
            $result[$key]['after_sale'] = $deal_sn_data['after_sale'];
            $result[$key]['profit_price'] = $deal_sn_data['profit_price'];
            $result[$key]['cost_price'] = $deal_sn_data['cost_price'];
            $result[$key]['total_freight'] = ((int)($result[$key]['total_freight'] * 100) - (int)($deal_sn_data['after_freight'] * 100)) /100;
            $result[$key]['total_fee'] = ((int)($result[$key]['total_fee']* 100) - (int)($deal_sn_data['after_price'] * 100)) / 100;
            $result[$key]['count_price'] = $result[$key]['total_freight'] + $result[$key]['total_fee'];
            $result[$key]['type'] = '苏鹰商城-苏宁频道';
            if(in_array($val['status'],[1,2,3,9])){
                $count_num++;//有限成单数量
                $count_price += $result[$key]['count_price'];
            }
            $count_freight += $result[$key]['profit_price'];
        }
        $data = [
            'data' => $result,
            'num' => $num,
            'count_price' => $count_price,
            'count_freight' => $count_freight,
            'count_num' => $count_num
        ];
        return $data;
    }
    /**
     * 京东订单
     * @param start开始时间
     * @param end结束时间
     * @param status订单状态
     * @param pageIndex分页
     */
    public function order_jd_data($start_time,$end_time,$status,$pageIndex){
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $where['a.order_type'] = array('in','2,3,4');
        if($status != ''){
            $jd_status = [0=>'1',1=>'2',2=>'4',3=>'5',4=>'7',5=>'6,8',6=>'3'];
            $where['a.status'] = array('eq',$jd_status[$status]);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.time'] = array('like',"%$day%");
        }
        if($pageIndex != ''){
            $result = M('mall_order','dsy_')
            ->join('as a left join dsy_mall_order_notpay as b on a.order_notpay_num = b.ordernum')
            ->join('left join dsy_mall_shops as c on a.sid = c.id')
            ->where($where)
            ->order('a.id desc')
            ->page($page,$limit)
            ->field('a.id,a.ordernum,b.wz_orderid AS third_party_order,a.time,a.paytype AS pay_type,a.price AS count_price,a.status,
                    a.freight AS total_freight,a.pprice AS total_fee,a.sid,c.shop_name AS type,b.pid,b.use_card,b.use_quota,b.card_amount,b.quota_amount,b.receipt_amount AS pay_price,b.status AS pay_state')
            ->select();
        }else{
            $result = M('mall_order','dsy_')
            ->join('as a left join dsy_mall_order_notpay as b on a.order_notpay_num = b.ordernum')
            ->join('left join dsy_mall_shops as c on a.sid = c.id')
            ->where($where)
            ->order('a.id desc')
            ->field('a.id,a.ordernum,b.wz_orderid AS third_party_order,a.time,a.paytype AS pay_type,a.price AS count_price,a.status,
                    a.freight AS total_freight,a.pprice AS total_fee,a.sid,c.shop_name AS type,b.pid,b.use_card,b.use_quota,b.card_amount,b.quota_amount,b.receipt_amount AS pay_price,b.status AS pay_state')
            ->select();            
        }
        if(empty($result)){
            $data = [
                'data' => $result,
                'num' => 0,
                'count_price' => 0,
                'count_freight' => 0,
                'count_num' => 0
            ];
            return $data;
        }
        $num = M('mall_order','dsy_')
            ->join('as a left join dsy_mall_order_notpay as b on a.order_notpay_num = b.ordernum')
            ->join('left join dsy_mall_shops as c on a.sid = c.id')
            ->join('left join dsy_company_exchange_record as d on b.ordernum = d.ordernum')
            ->where($where)
            ->count();
        $count_price = 0;//下单流水
        $count_freight = 0;//下单利润
        $count_num = 0;//有限成单数量
        foreach($result as $key=>$value){
            if($value['pay_state'] == 2){
                $deal_sn_pay = $this->pay_type($value['use_quota'],$value['use_card'],$value['pay_price'],$value['pay_type'],$value['quota_amount'],$value['card_amount'],2);
                $result[$key]['pay_str'] = $deal_sn_pay['pay_str'];
                $result[$key]['pay_detail'] = $deal_sn_pay['pay_detail'];
            }
            $deal_jd_data = $this->plan_jd_profit($value['pid'],$value['status'],$value['ordernum']);
            $result[$key]['after_sale'] = $deal_jd_data['after_sale'];//有无售后
            $result[$key]['cost_price'] = $deal_jd_data['cost_price'];
            $result[$key]['profit_price'] = $deal_jd_data['profit_price'];
            $result[$key]['count_price'] = ((int)($result[$key]['count_price']*100) - (int)($deal_jd_data['after_price']*100)) / 100;
            $result[$key]['total_fee'] = ((int)($result[$key]['total_fee']*100) - (int)($deal_jd_data['after_price']*100)) / 100;
            if(in_array($value['status'],[2,4,5,7])){
                $count_num++;
                $count_price += $result[$key]['count_price'];
            }
            $count_freight += $result[$key]['profit_price'];
        }
        $data = [
            'data' => $result,
            'num' => $num,
            'count_price' => $count_price,
            'count_freight' => $count_freight,
            'count_num' => $count_num
        ];
        return $data;
    }
    /**
     * 苏鹰精选订单
     * @param start开始时间
     * @param end结束时间
     * @param status订单状态
     */
    public function order_sy_data($start_time,$end_time,$status,$pageIndex=''){
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        if($status != ''){
            $jd_status = [0=>'1',1=>'2',2=>'4',3=>'5',4=>'7',5=>'6,8',6=>'3'];
            $where['a.status'] = array('eq',$jd_status[$status]);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.time'] = array('like',"%$day%");
        }
        $where['a.order_type'] = array('in','1,5');
        if($pageIndex != ''){
            $result = M('mall_order')
                        ->alias('a')
                        ->field('a.id,a.ordernum,d.wz_orderid AS third_party_order,a.time,a.paytype AS pay_type,a.price AS count_price,a.status,
                        a.freight AS total_freight,a.pprice AS total_fee,a.sid,c.shop_name AS type,d.pid,d.use_card,d.use_quota,d.card_amount,d.quota_amount,d.receipt_amount AS pay_price,d.status AS pay_state')
                        ->join('LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id ')
                        ->join('LEFT JOIN dsy_mall_order_notpay as d on a.order_notpay_num = d.ordernum')
                        ->where($where)
                        ->order('a.time desc')
                        ->page($page,$limit)
                        ->select();
        }else{
            $result = M('mall_order')
                        ->alias('a')
                        ->field('a.id,a.ordernum,d.wz_orderid AS third_party_order,a.time,a.paytype AS pay_type,a.price AS count_price,a.status,
                        a.freight AS total_freight,a.pprice AS total_fee,a.sid,c.shop_name AS type,d.pid,d.use_card,d.use_quota,d.card_amount,d.quota_amount,d.receipt_amount AS pay_price,d.status AS pay_state')
                        ->join('LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id ')
                        ->join('LEFT JOIN dsy_mall_order_notpay as d on a.order_notpay_num = d.ordernum')
                        ->where($where)
                        ->order('a.time desc')
                        ->select();
        }
        if(empty($result)){
            $data = [
                'data' => $result,
                'num' => 0,
                'count_price' => 0,
                'count_freight' => 0,
                'count_num' => 0
            ];
            return $data;
        }
        $count_price = 0;
        $count_freight = 0;
        $count_num = 0;//有限成单数量
        foreach($result as $key=>$value){
            if($value['pay_state'] == 2){
                $deal_sn_pay = $this->pay_type($value['use_quota'],$value['use_card'],$value['pay_price'],$value['pay_type'],$value['quota_amount'],$value['card_amount'],2);
                $result[$key]['pay_str'] = $deal_sn_pay['pay_str'];
                $result[$key]['pay_detail'] = $deal_sn_pay['pay_detail'];
            }
            $deal_jd_data = $this->plan_jd_profit($value['pid'],$value['status'],$value['ordernum']);
            $result[$key]['after_sale'] = $deal_jd_data['after_sale'];//有无售后
            $result[$key]['cost_price'] = $deal_jd_data['cost_price'];
            $result[$key]['profit_price'] = $deal_jd_data['profit_price'];
            $result[$key]['count_price'] = ((int)($result[$key]['count_price']*100) - (int)($deal_jd_data['after_price']*100)) / 100;
            $result[$key]['total_fee'] = ((int)($result[$key]['total_fee']*100) - (int)($deal_jd_data['after_price']*100)) / 100;
            if(in_array($value['status'],[2,4,5,7])){
                $count_num++;//有限成单数量
                $count_price += $result[$key]['count_price'];
            }
            $count_freight += $result[$key]['profit_price'];            
        }
        $num = M('mall_order')
        ->alias('a')
        ->join('LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id ')
        ->join('LEFT JOIN dsy_mall_order_notpay as d on a.order_notpay_num = d.ordernum')
        ->where($where)
        ->count();

        $data = [
            'data' => $result,
            'num' => $num,
            'count_price' => $count_price,
            'count_freight' => $count_freight,
            'count_num' => $count_num
        ];
        return $data;
    }
    /**
     * 汇总订单的excel导出
     */
    public function shop_order_allexport(){
        $platform = I('platform','');
        $start_time = I('start1','');
        $end_time = I('end','');
        $status = I('status','');
        $result = [
            'data' => '',
            'num' => '',
        ];
        if(empty($platform)){
            $result = $this->order_jd_sn_sy($start_time,$end_time,$status);
        }else{
            if($platform == 1){
                $result = $this->order_sn_data($start_time,$end_time,$status);
            }elseif($platform == 2){
                $result = $this->order_jd_data($start_time,$end_time,$status);
            }else{
                $result = $this->order_sy_data($start_time,$end_time,$status);
            }
        }
        $title = [
            ['订单编号','平台','订单状态','第三方订单编号','有无售后','下单时间','订单金额','商品金额','运费金额','商品成本','运费成本','订单利润','付款类型','支付详情'],
        ];
        $sn_state = [0=>'待付款',1=>'待发货',2=>'待收货',3=>'交易成功',4=>'已取消',5=>'交易关闭',6=>'待处理',7=>'已取消',9=>'评价完成'];
        $jd_state = [1=>'待付款',2=>'待发货',3=>'已取消',4=>'待收货',5=>'交易成功',6=>'交易关闭',7=>'评价完成',8=>'交易关闭'];
        $arr = [];
        $num = 1;
        foreach($result['data'] as $key=>$val){
            $arr = [
                'order_num' => $val['ordernum'],
                'type'=>$val['type'],
                'order_status' => '',
                'third_party_order' => $val['third_party_order'],
                'after_sale' => ($val['after_sale'] == 0) ? '无':'有',
                'create_time' => $val['time'],
                'count_price' => $val['count_price'],
                'total_fee' => $val['total_fee'],
                'total_freight' => $val['total_freight'],
                'cost_price' => $val['cost_price'],
                'cost_freight' => $val['total_freight'],
                'profit_price' => $val['profit_price'],
                'pay_str' => $val['pay_str'],
                'pay_detail' => $val['pay_detail']
            ];
            if($val['sid'] == 0){
                $arr['order_status'] = $sn_state[$val['status']];
            }else{
                $arr['order_status'] = $jd_state[$val['status']];
            }
            $arr['order_num'] = "\t" . $arr['order_num'] . "\t";
            $arr['third_party_order'] = "\t" . $arr['third_party_order'] . "\t";
            $title[$num++] = array_values($arr);
        }
        $data = ['money'=>$result['count_price'],'profit'=>$result['count_freight']];
        $this->get_excel($title,'订单流水利润',$data); 
    }
    /**
     * 订单详情页
     */
    public function shop_order_alldetail(){
        $id = I('id');
        $sid = I('sid');
        $ordernum = I('ordernum');
        if($sid == '' || empty($ordernum) || empty($id)){
            return false;
        }else{
            if($sid == 0){//苏宁订单详情
                $where['a.order_num'] = array('eq',$ordernum);
                $result = M('sn_order')
                            ->alias('a')
                            ->field('a.*,b.name,b.mobile,b.region,b.address as url,c.user_name')
                            ->join('LEFT JOIN dsy_mall_receipt as b on a.receiver_id=b.id')
                            ->join('LEFT JOIN t_user as c on a.user_id=c.user_id')
                            ->where($where)
                            ->find();
                $result['status_msg'] = $this->shop_order_state($result['status']);//获取订单状态
                $result['count_money'] = $result['total_freight'] + $result['total_fee'];
                $shop_list = M('sn_order_item')->where('order_id='.$result['id'])->select();
                $count_freight = 0;
                $count = count($shop_list) - 1;
                foreach($shop_list as $ky=>$vl){
                    if($ky != $count){
                        $shop_list[$ky]['count_freight'] = round($vl['product_price'] * $vl['product_num'] * $result['total_freight'] / $result['total_fee'],2);
                        $count_freight +=  $shop_list[$ky]['count_freight'];
                    }else{
                        $shop_list[$ky]['count_freight'] = $result['total_freight']  - $count_freight;
                    }
                    $shop_list[$ky]['count_price'] = $vl['product_price'] * $vl['product_num'];
                    $shop_list[$ky]['product_price'] = $vl['product_price'] + 0;
                }
                $shop_log = M('sn_order_log')->where("sn_order_id=" . '"' . $result['sn_order_num'] .'"')->select();//订单日志
                $num = 1;
                //物流信息
                $msg = [];
                if($result['status'] == 1 || $result['status'] == 2 || $result['status'] == 3 || $result['status'] == 9){
                    $shop_data = [];
                    $curl = JAVA_API_URL_SN . "/order/findOrderItemLogistics?orderNum=".$result['order_num'];
                    $shop_wl  = $this->shop_logistics_get($curl);
                    if($shop_wl['code'] == 200){
                        if($shop_wl['data']['isPackage'] == 'Y'){
                            $shop_arr = $shop_wl['data']['logistics'];
                            $num2 = 1;
                            foreach($shop_arr as $key=>$val){
                                $shop_data[$key]['num'] = $num2++;
                                $shop_data[$key]['expressCompany'] = $val['expressCompany'];//物流公司名称
                                $shop_data[$key]['logisticsInfoList'] = $val['logisticsInfoList'];//物流状态
                                $shop_data[$key]['logisticNumber'] = $val['logisticNumber'];//物流单号
                                $shop_data[$key]['shippingTime'] = $val['shippingTime'];//发货时间
                            }
                            $msg = ['code'=>200,'msg'=>'获取物流信息成功'];
                        }else{
                            $msg = ['code'=>300,'msg'=>'暂无包裹信息'];
                        }
                    }else{
                        $msg = ['code'=>$shop_wl['code'],'msg'=>$shop_wl['message']];
                    }
                }else{
                    $msg = ['code'=>400,'msg'=>'暂无物流信息'];
                }
                $count = count($shop_list);
                $this->assign('result',$result);
                $this->assign('shop_list',$shop_list);
                $this->assign('count',$count);
                $this->assign('count_price',$count_price);
                $this->assign('num',$num);
                $this->assign('shop_data',$shop_data);
                $this->assign('msg',$msg);//物流信息
                $this->assign('shop_log',$shop_log);//操作日志
                $this->display('shop_order_details');
            }elseif($sid == 128){//京东订单详情
                $wz_orderid = I('third_party_order');
                $info = M('mall_order','dsy_')->alias('a')->field('a.*,b.user_name')->join('LEFT JOIN t_user as b on a.uid=b.user_id')->where(['a.id'=>$id])->find();
                $name = $info['name'];
                $mobile = $info['mobile'];
                $address = $info['address'];
                $order['price'] = $info['pprice'];
                $order['freight'] = $info['freight'];
                $order['count_price'] = $info['price'];
                $order['order_num'] = $info['ordernum'];
                $order['create_time'] = $info['time'];
                $order['status_msg'] = $this->shop_order_jdstate($info['status']);
                $order['pay_type'] = $info['paytype'];
                $order['trade_no'] = $info['trade_no'];
                $order['paytime'] = $info['paytime'];
                $order['user_name'] = $info['user_name'];
                $pids = explode(',',$info['pid']);

                //订单类型
                $order_type = $info['order_type'];
                $enum = $info['enum'];//订单编号
                $etype = $info['etype'];//快递类型
                //获取token
                $token = selAccess_token();
                if($token==false){
                    return '获取token失败';
                }
                $products = array();
                $change_array = array();
                if($order_type == 1 || $order_type==5){
                    $travel_info = express($etype,$enum);
                    if(!empty($travel_info)){
                        $change_array['num'] = $enum;
                        foreach($travel_info as $kk=>$vv){
                            $change_array['travel'][$kk]['msgTime'] = $vv['AcceptTime'];
                            $change_array['travel'][$kk]['content'] = '';
                            $change_array['travel'][$kk]['operator'] = $vv['AcceptStation'];
                        }
                    }
                }
                $number = 1;
                foreach($pids as $key=>$value){
                    $detail = getDetalinfo($ordernum,$value);
                    $skuid = $detail['skuid'];
                    if($order_type == 2 || $order_type == 3 || $order_type==4){
                        $travel_info = product_travel($token,$wz_orderid,$skuid);//物流信息
                    }else{
                        $travel_info = $change_array;
                    }
                    $pname = $detail['pname'];//商品名称
                    if(count($detail)==count($detail,1)){
                        $data['number'] = $number++;
                        $num = $detail['num'];//购买数量
                        $specifications = $detail['specifications'];//规格
                        $data['pid'] = $value;
                        $data['pname'] = $pname;
                        $data['price'] = $detail['price'];
                        $data['num'] = $num;
                        $data['count_price'] = $detail['price'] * $num;
                        $data['travel_info'] = $travel_info;
                        $data['specifications'] = $specifications;
                        $data['product_img'] = $detail['pro_pic'];
                        $data['sku_id'] = $detail['skuid'];
                        $where_check['ordernum'] = array('eq',$ordernum);
                        $where_check['pid'] = array('eq',$value);
                        $products[] = $data;
                    }else{
                        foreach($detail as $kk=>$vv){
                            $data['number'] = $number++;
                            $num = $vv['num'];//购买数量
                            $specifications = $vv['specifications'];//规格
                            $data['pid'] = $value;
                            $data['pname'] = $pname;
                            $data['price'] = $vv['price'];
                            $data['num'] = $num;
                            $data['count_price'] = $vv['price'] * $num;
                            $data['travel_info'] = $travel_info;
                            $data['specifications'] = $specifications;
                            $data['product_img'] = $detail['pro_pic'];
                            $data['sku_id'] = $detail['skuid'];
                            $where_check['ordernum'] = array('eq',$ordernum);
                            $where_check['pid'] = array('eq',$value);
                            $products[] = $data;
                        }
                    }
                }
                $count = count($products);
                $this->assign('count',$count);
                $this->assign('pinfo',$products);
                $this->assign('name',$name);
                $this->assign('mobile',$mobile);
                $this->assign('address',$address);
                $this->assign('result',$order);
                $this->display('shop_order_jddetails');
            }else{
                $info = M('mall_order','dsy_')->alias('a')->field('a.*,b.user_name')->join('LEFT JOIN t_user as b on a.uid=b.user_id')->where(['a.id'=>$id])->find();
                $name = $info['name'];
                $mobile = $info['mobile'];
                $address = $info['address'];
                $order['price'] = $info['pprice'];
                $order['freight'] = $info['freight'];
                $order['count_price'] = $info['price'];
                $order['order_num'] = $info['ordernum'];
                $order['create_time'] = $info['time'];
                $order['status_msg'] = $this->shop_order_jdstate($info['status']);
                $order['pay_type'] = $info['paytype'];
                $order['trade_no'] = $info['trade_no'];
                $order['paytime'] = $info['paytime'];
                $order['user_name'] = $info['user_name'];
                $pids = explode(',',$info['pid']);

                //订单类型
                $order_type = $info['order_type'];
                $enum = $info['enum'];//订单编号
                $etype = $info['etype'];//快递类型
                $products = array();
                $number = 1;
                foreach($pids as $key=>$value){
                    $detail = getDetalinfo($ordernum,$value);
                    $pname = $detail['pname'];//商品名称
                    if(count($detail)==count($detail,1)){
                        $data['number'] = $number++;
                        $num = $detail['num'];//购买数量
                        $specifications = $detail['specifications'];//规格
                        $data['pid'] = $value;
                        $data['pname'] = $pname;
                        $data['price'] = $detail['price'];
                        $data['num'] = $num;
                        $data['count_price'] = $detail['price'] * $num;
                        $data['travel_info'] = $travel_info;
                        $data['specifications'] = $specifications;
                        $data['product_img'] = format_img($detail['pro_pic'], IMG_VIEW);
                        $data['sku_id'] = $detail['skuid'];
                        $where_check['ordernum'] = array('eq',$ordernum);
                        $where_check['pid'] = array('eq',$value);
                        $products[] = $data;
                    }else{
                        foreach($detail as $kk=>$vv){
                            $data['number'] = $number++;
                            $num = $vv['num'];//购买数量
                            $specifications = $vv['specifications'];//规格
                            $data['pid'] = $value;
                            $data['pname'] = $pname;
                            $data['price'] = $vv['price'];
                            $data['num'] = $num;
                            $data['count_price'] = $vv['price'] * $num;
                            $data['travel_info'] = $travel_info;
                            $data['specifications'] = $specifications;
                            $data['product_img'] = format_img($detail['pro_pic'], IMG_VIEW);
                            $data['sku_id'] = $detail['skuid'];
                            $where_check['ordernum'] = array('eq',$ordernum);
                            $where_check['pid'] = array('eq',$value);
                            $products[] = $data;
                        }
                    }
                }
                $count = count($products);
                $this->assign('count',$count);
                $this->assign('pinfo',$products);
                $this->assign('name',$name);
                $this->assign('mobile',$mobile);
                $this->assign('address',$address);
                $this->assign('result',$order);
                $this->display('shop_order_zydetails');
            }
        }
    }
    /**
     * 计算苏宁订单利润和成本
     * @param id订单主键id
     * @param status订单状态
     * @param ordernum订单号
     */
    public function plan_sn_profit($id,$status,$ordernum = ''){
        $profit_price = 0;//总利润
        $cost_price = 0;//总成本成本
        $after_price = 0;//总退款商品金额
        $after_freight = 0;//总退款邮费
        if(in_array($status,[1,2,3,9])){//判断订单状态
            $result_price = M('sn_order_item')
                            ->alias('a')
                            ->field('a.product_price,a.product_num,b.naked_price,a.item_status,a.sn_order_item_id')
                            ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                            ->where('a.order_id=' . $id)
                            ->select();
            foreach($result_price as $vl){
                if(in_array($vl['item_status'],[1,2,8,9])){//判断订单商品状态
                    $result_after = M('sn_item_after_sale')->field('return_num,refund_id')->where(['sn_order_item_id'=>$vl['sn_order_item_id']])->find();
                    if(!empty($result_after)){
                        if($result_after['return_num'] != 0){
                            $refund_where['refund_id'] = array('in',$result_after['refund_id']);
                            $result_refund = M('mall_refund')->field('refund_money')->where($refund_where)->select();
                            $refunt_count_money = 0;//计算该商品退款总金额(包含商品和运费)
                            foreach($result_refund as $v){
                                $refunt_count_money += $v['refund_money'];
                            }
                            $vl['product_num'] = $vl['product_num'] - $result_after['return_num'];//该商品实际购买数量
                            $after_freight += ($refunt_count_money - $vl['product_price'] * $result_after['return_num']);
                            $after_price += $vl['product_price'] * $result_after['return_num'];
                        }
                    }
                    $cost_price += $vl['naked_price'] * $vl['product_num'];
                    $profit = round(($vl['product_price']-$vl['naked_price']),2);//单件商品的利润
                    $profit_price += $profit * $vl['product_num'];//此商品的所有利润
                }
            }
        }
        $after_sale_type = 0;
        if(!empty($ordernum)){
            $after_sale = M('sn_item_after_sale')->field('item_after_sale_type')->where(['sn_order_id'=>$ordernum])->select();
            if(empty($after_sale)){
                $after_sale_type = 0;
            }else{
                foreach($after_sale as $val){
                    if($val['item_after_sale_type'] !=3){
                        $after_sale_type = 1;
                    }
                }
            }
        }
        return ['profit_price'=>$profit_price,'cost_price'=>$cost_price,'after_sale'=>$after_sale_type,'after_price'=>$after_price,'after_freight'=>$after_freight];
    }
    /**
     * 京东订单利润
     * @param pid商品id
     * @param status订单状态
     * @param ordernum订单id
     * @param type 1为京东2为苏鹰自营
     */
    public function plan_jd_profit($pid,$status,$ordernum){
        $pid_array = explode(',',$pid);
        $ordernum = $ordernum;
        //判断该订单中的商品有没有被退货
        $cost_price = 0;
        $prilft_price = 0;
        $after_sale_type = 0;
        $after_price = 0;
        if($status>1&&$status<8&&$status != 3 &&$status != 6){
            foreach($pid_array as $val){
                //查询该订单是否申请售后
                $where_check['ordernum'] = array('eq',$ordernum);
                $where_check['pid'] = array('eq',$val);
                $check = M('mall_order_return','dsy_')->where($where_check)->find();
                if(!empty($check)){
                    $after_sale_type = 1;
                    if($check['status'] !=2){
                        $num_info = getDetalinfo($ordernum,$val);
                        if(count($num_info)==count($num_info,1)){
                            $num = $num_info['num'];
                            $one_cost_price = $num_info['prime_cost'];//成本
                            $one_prilft_price = round($num_info['price'] - $one_cost_price, 2);//利润
                            $cost_price += $one_cost_price * $num;
                            $prilft_price += $one_prilft_price * $num;
                        }else{
                            foreach($num_info as $kkk=>$vvv){
                                $num = $vvv['num'];
                                $one_cost_price = $vvv['prime_cost'];//成本
                                $one_prilft_price = round($vvv['price'] - $one_cost_price, 2);//利润
                                $cost_price += $one_cost_price * $num;
                                $prilft_price += $one_prilft_price * $num;
                            }
                        }
                    }else{
                        $num_info = getDetalinfo($ordernum,$val);
                        if(count($num_info)==count($num_info,1)){
                            $num = $num_info['num'];
                            $price = $num_info['price'];
                            $after_price += $num * $price;
                        }else{
                            foreach($num_info as $kkk=>$vvv){
                                $num = $vvv['num'];
                                $price = $vvv['price'];
                                $after_price += $num * $price;
                            }
                        }
                    }
                }else{
                    $after_sale_type = 0;
                    $num_info = getDetalinfo($ordernum,$val);
                    if(count($num_info)==count($num_info,1)){
                        $num = $num_info['num'];
                        $one_cost_price = $num_info['prime_cost'];//成本
                        $one_prilft_price = round($num_info['price'] - $one_cost_price, 2);//利润
                        $cost_price += $one_cost_price * $num;
                        $prilft_price += $one_prilft_price * $num;
                    }else{
                        foreach($num_info as $kkk=>$vvv){
                            $num = $vvv['num'];
                            $one_cost_price = $vvv['prime_cost'];//成本
                            $one_prilft_price = round($vvv['price'] - $one_cost_price, 2);//利润
                            $cost_price += $one_cost_price * $num;
                            $prilft_price += $one_prilft_price * $num;
                        }
                    }
                }
            }
        }
        return ['profit_price'=>$prilft_price,'cost_price'=>$cost_price,'after_sale'=>$after_sale_type,'after_price'=>$after_price];
    }
    public function shop_order_allrunwater(){
        $platform = I('platform','');
        $start_time = I('stime','');
        $end_time = I('etime','');
        $status = I('status','');
        $result = [
            'data' => '',
            'num' => '',
        ];
        if(empty($platform)){
            $result = $this->order_jd_sn_sy($start_time,$end_time,$status);
        }else{
            if($platform == 1){
                $result = $this->order_sn_data($start_time,$end_time,$status);
            }elseif($platform == 2){
                $result = $this->order_jd_data($start_time,$end_time,$status);
            }else{
                $result = $this->order_sy_data($start_time,$end_time,$status);
            }
        }
        return $this->ajaxReturn(['money'=>$result['count_price']+0,'profit'=>$result['count_freight']+0,'num'=>$result['num']]);
    }
    /**
     * 公共的判断支付方式
     * @param quota福利豆
     * @param card苏鹰卡
     * @param pay现金支付
     * @param pay_type现金支付类型
     * @param use_quota福利豆抵扣金额
     * @param use_card苏鹰卡抵扣金额
     * @param type订单类型1为苏宁2为京东和自营
     */
    public function pay_type($quota,$card,$pay,$pay_type,$use_quota,$use_card,$type = 2){
        $pay_str = '';
        $pay_detail = '';
        if(!empty($quota)){
            $pay_str[] = '福利豆';
            if($type == 1){
                $result_quota = M('mall_wquota_detail_use')->field('sum(quota) as sum_quota')->where("id in($quota)")->find();
                $use_quota = $result_quota['sum_quota'];
                if($use_quota != 0){
                    $pay_detail[] = '福利豆' . $use_quota;
                }
            }else{
                $pay_detail[] = '福利豆' . $use_quota;
            }
        }
        if(!empty($card)){
            $pay_str[] = '苏鹰卡';
            if($type == 1){
                $result_card = explode(',',$card);
                $count = count($result_card);
                if($count == 1){
                    $card_price = explode(':',$card);
                    $use_card = $card_price[1];
                }else{
                    foreach($result_card as $val){
                        $card_price = explode(':',$val);
                        $use_card += $card_price[1];
                    }
                }
                $pay_detail[] = '苏鹰卡' . $use_card;
            }else{
                $pay_detail[] = '苏鹰卡' . $use_card;
            }
        }
        if(!empty($pay)){
            $pay_the_way = '';
            if($type == 1){
                if($pay_type == 1){
                    $pay_the_way = '微信';
                }elseif($pay_type == 2){
                    $pay_the_way = '支付宝';
                }
            }else{
                if($pay_type == 1){
                    $pay_the_way = '支付宝';
                }elseif($pay_type == 2){
                    $pay_the_way = '微信';
                }
            }
            if($pay_the_way != ''){
                $pay_str[] = $pay_the_way;
                $pay_detail[] = $pay_the_way . $pay;
            }
        }
        $pay_str = implode('+',$pay_str);
        $pay_detail = implode('+',$pay_detail);
        return ['pay_str'=>$pay_str,'pay_detail'=>$pay_detail];
    }
    /**
     * 苏宁订单状态列表及信息
     * @param key状态码
     */
    public function shop_order_state($key){
        $state = [
            0=>'待付款',
            1=>'待发货',
            2=>'待收货',
            3=>'交易成功',
            4=>'已取消',
            5=>'交易关闭',
            7=>'已取消',
            9=>'评价完成'
        ];
        $msg = [
            0=>'商品已拍下，等待买家付款',
            1=>'交易关闭或订单中所有商品退货退款完成',
            2=>'买家已付款,等待卖家发货',
            3=>'卖家已发货,等待买家收货',
            4=>'买家确认收货,交易成功',
            5=>'用户已取消订单或超出支付时间',
            6=>'交易成功,用户已评价完成'
        ];
        if($key == 0){
            $return_msg = $msg[0];
        }elseif($key == 5){
            $return_msg = $msg[1];
        }elseif($key == 1){
            $return_msg = $msg[2];
        }elseif($key == 2){
            $return_msg = $msg[3];
        }elseif($key == 3){
            $return_msg = $msg[4];
        }elseif($key == 4 || $key == 7){
            $return_msg = $msg[5];
        }elseif($key == 9){
            $return_msg = $msg[6];
        }else{
            $return_msg = '';
        }
        return [$state[$key],$return_msg];
    }
    /**
     * 京东和自营订单状态列表及信息
     * @param key状态码
     */
    public function shop_order_jdstate($key){
        $state = [
            1=>'待付款',
            2=>'待发货',
            3=>'已取消',
            4=>'待收货',
            5=>'交易成功',
            6=>'交易关闭',
            7=>'评价完成',
            8=>'交易关闭'
        ];
        $msg = [
            0=>'商品已拍下，等待买家付款',
            1=>'交易关闭或订单中所有商品退货退款完成',
            2=>'买家已付款,等待卖家发货',
            3=>'卖家已发货,等待买家收货',
            4=>'买家确认收货,交易成功',
            5=>'用户已取消订单或超出支付时间',
            6=>'交易成功,用户已评价完成'
        ];
        if($key == 1){
            $return_msg = $msg[0];
        }elseif($key == 6 || $key==8){
            $return_msg = $msg[1];
        }elseif($key == 2){
            $return_msg = $msg[2];
        }elseif($key == 4){
            $return_msg = $msg[3];
        }elseif($key == 5){
            $return_msg = $msg[4];
        }elseif($key == 3){
            $return_msg = $msg[5];
        }elseif($key == 7){
            $return_msg = $msg[6];
        }else{
            $return_msg = '';
        }
        return [$state[$key],$return_msg];
    }
    /**
     * 查询物流信息
     */
    public function shop_logistics_get($url){
        $ch = curl_init();
        //设置选项，包括URL
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        //执行并获取HTML文档内容
        $output = curl_exec($ch);
        //释放curl句柄
        curl_close($ch);
        return json_decode($output,true);
    }
    /**
     * 导出订单信息
     */
    public function shop_order_export(){
        $data = [
            'order_num' => I('ordernum',''),
            'sn_order_num' => I('snordernum',''),
            'start_time' => I('start1',''),
            'end_time' => I('end'),
            'status' => I('status','addrname',''),
            'name' => I('addrname',''),
        ];
        $where['a.status'] = array('neq',8);
        if(!empty($data['order_num'])){
            $where['a.order_num'] = array('eq',$data['order_num']);
        }
        if(!empty($data['sn_order_num'])){
            $where['a.sn_order_num'] = array('eq',$data['sn_order_num']);
        }
        if(!empty($data['name'])){
            $where['b.name'] = array('eq',$data['name']);
        }
        if($data['status'] != ''){
            $where['a.status'] = array('in',$data['status']);
        }
        if(!empty($data['start_time'])&&!empty($data['end_time'])){
            if($data['start_time']==$data['end_time']){
                $where['a.create_time'] = array('like',"%".$data['start_time']."%");
            }else{
                $start_time = $data['start_time'].' 00:00:00';
                $end_time = $data['end_time'].' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }
        $result = M('sn_order')
                    ->alias('a')
                    ->field('a.id,a.order_num,a.sn_order_num,a.create_time,a.pay_type,a.status,a.pay_type,a.total_freight,a.total_fee,a.address_name,a.address,a.phone')
                    ->join('LEFT JOIN dsy_mall_receipt as b on a.receiver_id=b.id')
                    ->order('a.create_time desc')
                    ->where($where)
                    ->select();

        foreach($result as $key=>$val){
            $profit_price = 0;//总利润
            $cost_price = 0;//总成本成本
            $count_price = 0;//总实付价格
            $result_price = M('sn_order_item')
                            ->alias('a')
                            ->field('a.product_price,a.product_num,b.naked_price,a.item_status,a.product_name,a.product_num,a.spec')
                            ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                            ->where('a.order_id=' . $val['id'])
                            ->select();
            $result_price = M('sn_order_item')
                                ->alias('a')
                                ->field('a.product_price,a.product_num,b.naked_price,a.item_status,a.product_name,a.product_num,a.spec')
                                ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                                ->where('a.order_id=' . $val['id'])
                                ->select();
            foreach($result_price as $vl){
                if(in_array($val['status'],[1,2,3,9])){//判断订单状态
                    $count_price += $vl['product_price'] * $vl['product_num'];
                    if(in_array($vl['item_status'],[1,2,8,9])){//判断订单商品状态
                        $cost_price += $vl['naked_price'] * $vl['product_num'];
                        $profit = round(($vl['product_price']-$vl['naked_price']),2);//单件商品的利润
                        $profit_price += $profit * $vl['product_num'];//此商品的所有利润
                    }
                }
                $result[$key]['product'][] = [
                    'num' => $vl['product_num'],
                    'name' => $vl['product_name'],
                    'spec' => $vl['spec']
                ];
            }
            $result[$key]['profit_price'] = $profit_price;
            $result[$key]['cost_price'] = $cost_price;
            $result[$key]['count_price'] = $val['total_freight'] + $val['total_fee'];
            $result[$key]['cname'] = '苏鹰自营';
            $result[$key]['isneedinvoice'] = '无需发票';
        }
        $title = [];
        $state = [0=>'待付款',1=>'待发货',2=>'待收货',3=>'交易成功',4=>'已取消',5=>'交易关闭',6=>'待处理',7=>'已取消',9=>'评价完成'];
        $num = 0;
        foreach($result as $key=>$val){
            $arr = [
                'order_num' => $val['order_num'],
                'order_status' => $state[$val['status']],
                'order_company' => $val['cname'],
                'sn_order_num' => $val['sn_order_num'],
                'sn_order_status' => ($val['status'] == 2)?'成功':($val['status'] == 3) ?'成功':($val['status'] == 9)?'成功':'',
                'create_time' => $val['create_time'],
                'count_price' => $val['count_price'],
                'cost_price' => $val['cost_price'],
                'profit_price' => $val['profit_price'],
                'pay_type' => '',
                'order_type' => '苏宁商城',
                'order_fp' => $val['isneedinvoice'],
                'address_name' => $val['address_name'],
                'phone' => $val['phone'],
                'address' => $val['address'],
                'product' => $val['product']
            ];
            if($val['pay_type'] == 1){
                $arr['pay_type'] = '微信';
            }elseif($val['pay_type'] == 2){
                $arr['pay_type'] = '支付宝';
            }elseif($val['pay_type'] == 3){
                $arr['pay_type'] = '完全抵扣';
            }else{
                $arr['pay_type'] = '未支付';
            }
            $arr['order_num'] = "\t" . $arr['order_num'] . "\t";
            $arr['sn_order_num'] = "\t" . $arr['sn_order_num'] . "\t";
            $arr['phone'] = "\t" . $arr['phone'] . "\t";
            $title[$num++] = $arr;
        }
        $this->get_excel2($title,'订单列表'); 
    }
    /**
     * 数据导出excel
     */
    public function get_excel($data,$title,$profit=''){
        import('Org.Util.PHPExcel');
        $excel = new \PHPExcel();
        //Excel表格式,这里简略写了8列
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        //第一个sheet
        $excel->setactivesheetindex(0);
        //设置sheet标题
        $excel->getActiveSheet()->setTitle($title);
        //填充表格信息
        $excel->getActiveSheet()->setCellValue("A1",$title."统计报表");
        if(!empty($profit)){
            $excel->getActiveSheet()->setCellValue("B1","下单流水:" . $profit['money']);
            $excel->getActiveSheet()->setCellValue("C1","下单利润:" . $profit['profit']);
        }
        $excel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('F')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('M')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('N')->setWidth(25);
        for ($i = 2;$i <= count($data) + 1;$i++) {
        $j = 0;
        foreach ($data[$i-2] as $key=>$value) {
        
        if(!strpos($value,'Public/Uploads')){
        //文字生成
        $excel->getActiveSheet()->setCellValue("$letter[$j]$i","$value");
        }else{
        // 图片生成
        $objDrawing[$key] = new \PHPExcel_Worksheet_Drawing();
        $objDrawing[$key]->setPath($value);
        // 设置宽度高度
        $objDrawing[$key]->setHeight(100);//照片高度
        //$objDrawing[$k]->setWidth(80); //照片宽度
        /*设置图片要插入的单元格*/
        $objDrawing[$key]->setCoordinates("$letter[$j]$i");
        // 图片偏移距离
        $objDrawing[$key]->setOffsetX(50);
        $objDrawing[$key]->setOffsetY(10);
        $objDrawing[$key]->setWorksheet($excel->getActiveSheet());
        }
        $j++;
        }
        }
        $title = $title . time();
        $write = new \PHPExcel_Writer_Excel5($excel);
        ob_end_clean();
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");;
        header('Content-Disposition:attachment;filename="'.$title.'.xls"');
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
    }
    /**
     * 导出订单信息
     */
    public function shop_order_export2(){
        $data = [
            'order_num' => I('ordernum',''),
            'sn_order_num' => I('snordernum',''),
            'start_time' => I('start1',''),
            'end_time' => I('end'),
            'status' => I('status','addrname',''),
            'name' => I('addrname',''),
        ];
        $where['a.status'] = array('neq',8);
        if(!empty($data['order_num'])){
            $where['a.order_num'] = array('eq',$data['order_num']);
        }
        if(!empty($data['sn_order_num'])){
            $where['a.sn_order_num'] = array('eq',$data['sn_order_num']);
        }
        if(!empty($data['name'])){
            $where['b.name'] = array('eq',$data['name']);
        }
        if($data['status'] != ''){
            $where['a.status'] = array('in',$data['status']);
        }
        if(!empty($data['start_time'])&&!empty($data['end_time'])){
            if($data['start_time']==$data['end_time']){
                $where['a.create_time'] = array('like',"%".$data['start_time']."%");
            }else{
                $start_time = $data['start_time'].' 00:00:00';
                $end_time = $data['end_time'].' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }
        $result = M('sn_order')
                    ->alias('a')
                    ->field('a.id,a.order_num,a.sn_order_num,a.create_time,a.pay_type,a.status,a.pay_type,a.total_freight,a.total_fee,a.address_name,a.address,a.phone')
                    ->join('LEFT JOIN dsy_mall_receipt as b on a.receiver_id=b.id')
                    ->order('a.create_time desc')
                    ->where($where)
                    ->select();

        foreach($result as $key=>$val){
            $profit_price = 0;//总利润
            $cost_price = 0;//总成本成本
            $count_price = 0;//总实付价格
            $result_price = M('sn_order_item')
                            ->alias('a')
                            ->field('a.product_price,a.product_num,b.naked_price,a.item_status,a.product_name,a.product_num,a.spec')
                            ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                            ->where('a.order_id=' . $val['id'])
                            ->select();
            $result_price = M('sn_order_item')
                                ->alias('a')
                                ->field('a.product_price,a.product_num,b.naked_price,a.item_status,a.product_name,a.product_num,a.spec')
                                ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                                ->where('a.order_id=' . $val['id'])
                                ->select();
            foreach($result_price as $vl){
                if(in_array($val['status'],[1,2,3,9])){//判断订单状态
                    $count_price += $vl['product_price'] * $vl['product_num'];
                    if(in_array($vl['item_status'],[1,2,8,9])){//判断订单商品状态
                        $cost_price += $vl['naked_price'] * $vl['product_num'];
                        $profit = round(($vl['product_price']-$vl['naked_price']),2);//单件商品的利润
                        $profit_price += $profit * $vl['product_num'];//此商品的所有利润
                    }
                }
                $result[$key]['product'][] = [
                    'num' => $vl['product_num'],
                    'name' => $vl['product_name'],
                    'spec' => $vl['spec']
                ];
            }
            $result[$key]['profit_price'] = $profit_price;
            $result[$key]['cost_price'] = $cost_price;
            $result[$key]['count_price'] = $val['total_freight'] + $val['total_fee'];
            $result[$key]['cname'] = '苏鹰自营';
            $result[$key]['isneedinvoice'] = '无需发票';
        }
        $title = [];
        $state = [0=>'待付款',1=>'待发货',2=>'待收货',3=>'交易成功',4=>'已取消',5=>'交易关闭',6=>'待处理',7=>'已取消',9=>'评价完成'];
        $num = 0;
        foreach($result as $key=>$val){
            $arr = [
                'order_num' => $val['order_num'],
                'order_status' => $state[$val['status']],
                'order_company' => $val['cname'],
                'sn_order_num' => $val['sn_order_num'],
                'sn_order_status' => ($val['status'] == 2)?'成功':($val['status'] == 3) ?'成功':($val['status'] == 9)?'成功':'',
                'create_time' => $val['create_time'],
                'count_price' => $val['count_price'],
                'cost_price' => $val['cost_price'],
                'profit_price' => $val['profit_price'],
                'pay_type' => '',
                'order_type' => '苏宁商城',
                'order_fp' => $val['isneedinvoice'],
                'address_name' => $val['address_name'],
                'phone' => $val['phone'],
                'address' => $val['address'],
                'product' => $val['product']
            ];
            if($val['pay_type'] == 1){
                $arr['pay_type'] = '微信';
            }elseif($val['pay_type'] == 2){
                $arr['pay_type'] = '支付宝';
            }elseif($val['pay_type'] == 3){
                $arr['pay_type'] = '完全抵扣';
            }else{
                $arr['pay_type'] = '未支付';
            }
            $arr['order_num'] = "\t" . $arr['order_num'] . "\t";
            $arr['sn_order_num'] = "\t" . $arr['sn_order_num'] . "\t";
            $arr['phone'] = "\t" . $arr['phone'] . "\t";
            $title[$num++] = $arr;
        }
        $this->get_excel2($title,'订单列表'); 
    }
    /**
     * 数据导出excel
     */
    public function get_excel2($data,$title,$profit=''){
        import('Org.Util.PHPExcel');
        $excel = new \PHPExcel();
        //Excel表格式,这里简略写了8列
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        //第一个sheet
        $excel->setactivesheetindex(0);
        //设置sheet标题
        $excel->getActiveSheet()->setTitle($title);
        //填充表格信息
        $excel->getActiveSheet()->setCellValue("A1",$title."统计报表");
        if(!empty($profit)){
            $excel->getActiveSheet()->setCellValue("B1","下单流水:" . $profit['money']);
            $excel->getActiveSheet()->setCellValue("C1","下单利润:" . $profit['profit']);
        }
        $excel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('F')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('M')->setWidth(10);
        $excel->getActiveSheet()->getColumnDimension('N')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('O')->setWidth(50);
        $excel->getActiveSheet()->getColumnDimension('P')->setWidth(10);
        $excel->getActiveSheet()->getColumnDimension('Q')->setWidth(60);
        $excel->getActiveSheet()->getColumnDimension('R')->setWidth(15);
        $excel->getActiveSheet()->setCellValue('A2','苏鹰订单编号');
        $excel->getActiveSheet()->setCellValue('B2','苏鹰订单状态');
        $excel->getActiveSheet()->setCellValue('C2','商家名称');
        $excel->getActiveSheet()->setCellValue('D2','苏宁订单编号');
        $excel->getActiveSheet()->setCellValue('E2','苏宁订单状态');
        $excel->getActiveSheet()->setCellValue('F2','下单时间');
        $excel->getActiveSheet()->setCellValue('G2','订单金额');
        $excel->getActiveSheet()->setCellValue('H2','订单成本');
        $excel->getActiveSheet()->setCellValue('I2','订单利润');
        $excel->getActiveSheet()->setCellValue('J2','付款类型');
        $excel->getActiveSheet()->setCellValue('K2','订单类型');
        $excel->getActiveSheet()->setCellValue('L2','是否开具发票');
        $excel->getActiveSheet()->setCellValue('M2','收货人姓名');
        $excel->getActiveSheet()->setCellValue('N2','收货手机号');
        $excel->getActiveSheet()->setCellValue('O2','收货地址');
        $excel->getActiveSheet()->setCellValue('P2','购买数量');
        $excel->getActiveSheet()->setCellValue('Q2','商品名称');
        $excel->getActiveSheet()->setCellValue('R2','商品规格');
        $excel->getActiveSheet()->getStyle('A2:R2')->getFont()->setBold(true);
        $key = 3;
        $count = count($data);
        for ($i = 0;$i < $count;$i++) {
            $excel->getActiveSheet()->setCellValue('A' . $key, $data[$i]['order_num']);
            $excel->getActiveSheet()->setCellValue('B' . $key, $data[$i]['order_status']);
            $excel->getActiveSheet()->setCellValue('C' . $key, $data[$i]['order_company']);
            $excel->getActiveSheet()->setCellValue('D' . $key, $data[$i]['sn_order_num']);
            $excel->getActiveSheet()->setCellValue('E' . $key, $data[$i]['sn_order_status']);
            $excel->getActiveSheet()->setCellValue('F' . $key, $data[$i]['create_time']);
            $excel->getActiveSheet()->setCellValue('G' . $key, $data[$i]['count_price']);
            $excel->getActiveSheet()->setCellValue('H' . $key, $data[$i]['cost_price']);
            $excel->getActiveSheet()->setCellValue('I' . $key, $data[$i]['profit_price']);
            $excel->getActiveSheet()->setCellValue('J' . $key, $data[$i]['pay_type']);
            $excel->getActiveSheet()->setCellValue('K' . $key, $data[$i]['order_type']);
            $excel->getActiveSheet()->setCellValue('L' . $key, $data[$i]['order_fp']);
            $excel->getActiveSheet()->setCellValue('M' . $key, $data[$i]['address_name']);
            $excel->getActiveSheet()->setCellValue('N' . $key, $data[$i]['phone']);
            $excel->getActiveSheet()->setCellValue('O' . $key, $data[$i]['address']);
            if(count($data[$i]['product']) > 1){
                $iS = $key;
                foreach($data[$i]['product'] as $kk => $vl){
                    $excel->getActiveSheet()->setCellValue('P' . $key, $data[$i]['product'][$kk]['num']);
                    $excel->getActiveSheet()->setCellValue('Q' . $key, $data[$i]['product'][$kk]['name']);
                    $excel->getActiveSheet()->setCellValue('R' . $key, $data[$i]['product'][$kk]['spec']);
                    $iE = $key++;
                }
                $excel->getActiveSheet()->mergeCells('A' . $iS . ':A' . $iE);
                $excel->getActiveSheet()->mergeCells('B' . $iS . ':B' . $iE);
                $excel->getActiveSheet()->mergeCells('C' . $iS . ':C' . $iE);
                $excel->getActiveSheet()->mergeCells('D' . $iS . ':D' . $iE);
                $excel->getActiveSheet()->mergeCells('E' . $iS . ':E' . $iE);
                $excel->getActiveSheet()->mergeCells('F' . $iS . ':F' . $iE);
                $excel->getActiveSheet()->mergeCells('G' . $iS . ':G' . $iE);
                $excel->getActiveSheet()->mergeCells('H' . $iS . ':H' . $iE);
                $excel->getActiveSheet()->mergeCells('I' . $iS . ':I' . $iE);
                $excel->getActiveSheet()->mergeCells('J' . $iS . ':J' . $iE);
                $excel->getActiveSheet()->mergeCells('K' . $iS . ':K' . $iE);
                $excel->getActiveSheet()->mergeCells('L' . $iS . ':L' . $iE);
                $excel->getActiveSheet()->mergeCells('M' . $iS . ':M' . $iE);
                $excel->getActiveSheet()->mergeCells('N' . $iS . ':N' . $iE);
                $excel->getActiveSheet()->mergeCells('O' . $iS . ':O' . $iE);
            }else{
                $excel->getActiveSheet()->setCellValue('P' . $key, $data[$i]['product'][0]['num']);
                $excel->getActiveSheet()->setCellValue('Q' . $key, $data[$i]['product'][0]['name']);
                $excel->getActiveSheet()->setCellValue('R' . $key, $data[$i]['product'][0]['spec']);
                $key++;
            }
        }
        $excel->getActiveSheet()->getStyle('A1:R' . $key)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $excel->getActiveSheet()->getStyle('A1:P' . $key)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $excel->getActiveSheet()->getStyle('I1:I' . $key)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        $title = $title . time();
        $write = new \PHPExcel_Writer_Excel5($excel);
        ob_end_clean();
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");;
        header('Content-Disposition:attachment;filename="'.$title.'.xls"');
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
    }
    public function logis(){
        $str = 20200915;
        $ccc = date('Y-m',strtotime($str));
        echo $ccc;
    }
    public function shop_recharge_list(){
       $this->display();
    }
    public function shop_recharge_content(){
        $type = I('type',1);
        $start = I('start');
        $end = I('end');
        $where['a.type'] = array('eq',$type);
        if(!empty($start)&&!empty($end)){
            if($start==$end){
                $where['a.time'] = array('eq',$start);
            }else{
                $where['a.time'] = array('between',array($start,$end));
            }
        }else{
            if(empty($start)&&!empty($end)){
                $where['a.time'] = array('elt',$end);
            }elseif(!empty($start)&&empty($end)){
                $where['a.time'] = array('egt',$start);
            }
        }
        $num = M('company_recharge_log')
                        ->alias('a')
                        ->join('left join sys_action_log as b on a.log_id=b.id')
                        ->where($where)
                        ->count();
        $this->assign('type',$type);
        $this->assign('start',$start);
        $this->assign('end',$end);
        $this->assign('num',$num);
        $this->display();
    }
    public function projectData(){
        $page = I('page',1);
        $type = I('type',1);
        $start = I('start');
        $end = I('end');
        $pages = (I('pages',1) - 1) * 10;
        $where['a.type'] = array('eq',$type);
        if(!empty($start)&&!empty($end)){
            if($start==$end){
                $where['a.time'] = array('eq',$start);
            }else{
                $where['a.time'] = array('between',array($start,$end));
            }
        }else{
            if(empty($start)&&!empty($end)){
                $where['a.time'] = array('elt',$end);
            }elseif(!empty($start)&&empty($end)){
                $where['a.time'] = array('egt',$start);
            }
        }
        $result['list'] = M('company_recharge_log')
                        ->alias('a')
                        ->field("a.*,if(a.type = 1,'微知',if(a.type = 2,'苏宁','千米')) as type_name,b.user_id")
                        ->join('left join sys_action_log as b on a.log_id=b.id')
                        ->where($where)
                        ->limit($pages,10)
                        ->order('a.time desc')
                        ->select();
        $result['num'] = M('company_recharge_log')
                        ->alias('a')
                        ->join('left join sys_action_log as b on a.log_id=b.id')
                        ->where($where)
                        ->count();
        foreach($result['list'] as $key=>$val){
            $result['list'][$key]['certificate_img'] = WEB_URL . '/dashengyun' . $val['certificate_img'];
            $result['list'][$key]['recharge_img'] = WEB_URL .'/dashengyun'. $val['recharge_img'];
            // $result['list'][$key]['certificate_img'] = "http://localhost/dashengyun" . $val['certificate_img'];
            // $result['list'][$key]['recharge_img'] = "http://localhost/dashengyun" . $val['recharge_img'];
        }
        return Response::json(200,'请求成功',$result);
    }
    public function shop_recharge_add(){
        $this->display();
    }
    public function shop_recharge_insert(){
        $type = I('type');//1微知 2苏宁 3千米
        $time = I('time');//充值时间
        $certificate_img = I('certificate_img');//打款凭证
        $recharge_img = I('recharge_img');//到账充值
        $remark = I('remark');//备注
        $data = [
            'type' => $type,
            'update_time' => date('Y-m-d H:i:s',time()),
            'time' => $time,
            'certificate_img' => $certificate_img,
            'recharge_img' => $recharge_img,
            'remark' => $remark
        ];
        if(empty($type)){
            return Response::json(300,$data);
        }
        if(empty($time)){
            return Response::json(300,'充值时间不能为空');
        }
        if(empty($certificate_img)){
            return Response::json(300,'打款凭证截图不能为空');
        }
        if(empty($recharge_img)){
            return Response::json(300,'充值到账截图不能为空');
        }
        $date = strtotime($time);
        if($date > time()){
            return Response::json(300,'充值时间不能选择未来时间');
        }
        $result = M('company_recharge_log')->add($data);
        $admin_log = '添加第三方充值记录编号id:'. $result;
        if($result){
            $log_id = admin_log($admin_log, 1, 'dsy_sn_category:' . $result);
            $add_log_id = M('company_recharge_log')->where(['id'=>$result])->save(['log_id'=>$log_id]);
            return Response::json(200,'添加记录成功');
        }else{
            $log_id = admin_log($admin_log, 0, 'dsy_sn_category:');
            return Response::json(300,'添加记录成功');
        }
    }
    public function shop_recharge_export(){
        $type = I('type',1);
        $start = I('start');
        $end = I('end');
        $where['a.type'] = array('eq',$type);
        if(!empty($start)&&!empty($end)){
            if($start==$end){
                $where['a.time'] = array('eq',$start);
            }else{
                $where['a.time'] = array('between',array($start,$end));
            }
        }else{
            if(empty($start)&&!empty($end)){
                $where['a.time'] = array('elt',$end);
            }elseif(!empty($start)&&empty($end)){
                $where['a.time'] = array('egt',$start);
            }
        }
        $result = M('company_recharge_log')
                        ->alias('a')
                        ->field("a.*,if(a.type = 1,'微知',if(a.type = 2,'苏宁','千米')) as type_name,b.user_id")
                        ->join('left join sys_action_log as b on a.log_id=b.id')
                        ->where($where)
                        ->select();
        $data[] = ['第三方名称','操作时间','充值时间','操作人','公司打款凭证','充值到账截图','备注'];
        $num = 1;
        foreach($result as $key=>$val){
            $arr = [
                'type_name' => $val['type_name'],
                'update_time' => $val['update_time'],
                'time' => $val['time'],
                'user_id' => $val['user_id'],
                'certificate_img' => $val['certificate_img'],
                'recharge_img' => $val['recharge_img'],
                'remark' => $val['remark']
            ];
            $data[$num++] = array_values($arr);
        }
        if($type == 1){
            $title = '微知';
        }elseif($type == 2){
            $title = '苏宁';
        }else{
            $title = '千米';
        }
        import('Org.Util.PHPExcel');
        $excel = new \PHPExcel();
        //Excel表格式,这里简略写了8列
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        //第一个sheet
        $excel->setactivesheetindex(0);
        //设置sheet标题
        $excel->getActiveSheet()->setTitle($title);
        //填充表格信息
        $excel->getActiveSheet()->setCellValue("A1",$title."第三方充值统计报表");
        $excel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('E')->setWidth(30);
        $excel->getActiveSheet()->getColumnDimension('F')->setWidth(30);
        $excel->getActiveSheet()->getColumnDimension('G')->setWidth(25);
        $excel->getActiveSheet()->getDefaultRowDimension()->setRowHeight(100);
        $excel->getActiveSheet()->getStyle('A1:G2')->getFont()->setBold(true);
        for ($i = 2;$i <= count($data) + 1;$i++) {
        $j = 0;
        foreach ($data[$i-2] as $key=>$value) {
        
        if(!strpos($value,'Public/Uploads')){
            //文字生成
            $excel->getActiveSheet()->setCellValue("$letter[$j]$i","$value");
        }else{
        // 图片生成
        $objDrawing[$key] = new \PHPExcel_Worksheet_Drawing();
        $objDrawing[$key]->setPath("." . $value);
        // 设置宽度高度
        $objDrawing[$key]->setHeight(140);//照片高度
        $objDrawing[$key]->setWidth(210); //照片宽度
        /*设置图片要插入的单元格*/
        $objDrawing[$key]->setCoordinates("$letter[$j]$i");
        // 图片偏移距离
        // $objDrawing[$key]->setOffsetX(50);
        // $objDrawing[$key]->setOffsetY(10);
        $objDrawing[$key]->setWorksheet($excel->getActiveSheet());
        }
        $j++;
        }
        }
        $excel->getActiveSheet()->getStyle('A1:G' . $key)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $title = $title . time();
        $write = new \PHPExcel_Writer_Excel5($excel);
        ob_end_clean();
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");;
        header('Content-Disposition:attachment;filename="'.$title.'.xls"');
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
    }
    /**
     * 上传图片
     */
    public function uploadPic()
    {
        $pic = $_FILES['file'];
        if($pic['size'] > 2097152){
            return Response::show(300,'照片大小超过2M,请重新上传');
        }

        if (empty($pic))
            return Response::show(300, '请选择图片！');
        $upload = new \Think\Upload();// 实例化上传类
        $upload->maxSize   =     3145728 ;// 设置附件上传大小
        $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
        $upload->rootPath  =     './'; // 设置附件上传根目录
        $upload->savePath  =     'Public/Uploads/img/'; // 设置附件上传（子）目录
        $upload->subName   =     array('date','Ymd');
        $info   =   $upload->uploadOne($pic);
        if ($info === false) {
            return Response::show(300, $upload->getError());
        }

        $path = '/' . $info['savepath'] . $info['savename'];
        $data = [
            'fixPath' => $path,
            'path' => WEB_URL . '/dashengyun' . $path,
        ];
        return Response::json(200, '上传成功！', $data);
    }
    /***获取ip地址 */
    public function getIp(){
        $ip = get_client_ip();
        $php = PHP_VERSION;
        echo $php . '-------' . $ip;die();
    }
}