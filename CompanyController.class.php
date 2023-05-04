<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/1/16 0016
 * Time: 下午 4:10
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;
use Think\Exception;
use WechatPay\WechatAppPay;
use app\Controller\JdApiController;
use app\Controller\MallV1_2Controller;

class CompanyController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 商品列表页
    **/
    public function product_index(){
        //展示商城可用余额
        $JdApi = A('app/JdApi');
        $result = $JdApi->checkAccountBalanceOpen();
        $this->assign('money',$result['data']);

        $this->assign('first', getFLevel());
        $this->display('product_index');
    }

    public function second(){
        $id = I('id','');
        $where['flid'] = array('eq',$id);
        $sinfo = M('mall_slevel','dsy_')
            ->where($where)
            ->field('id,name')
            ->select();
        $data['id'] = '0';
        $data['name'] = '二级分类';
        array_unshift($sinfo,$data);
        if(!empty($sinfo)){
            $array = array();
            foreach($sinfo as $value){
                $a = '<option value="'.$value['id'].'">'.$value['name'].'</option>';
                $array[] = $a;
            }
            $string = implode('',$array);
        }
        $this->ajaxReturn($string);
    }

    public function third(){
        $id = I('id','');
        $where['slid'] = array('eq',$id);
        $sinfo = M('mall_tlevel','dsy_')
            ->where($where)
            ->field('id,name')
            ->select();
        $data['id'] = '0';
        $data['name'] = '三级分类';
        array_unshift($sinfo,$data);
        if(!empty($sinfo)){
            $array = array();
            foreach($sinfo as $value){
                $a = '<option value="'.$value['id'].'">'.$value['name'].'</option>';
                $array[] = $a;
            }
            $string = implode('',$array);
        }
        $this->ajaxReturn($string);
    }
    /**
     * 商品列表数据
    **/
    public function product_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex:'0';
        $limit = 10;
        $jd_num = trim(I('jd_num',''));
        $dsy_num = trim(I('dsy_num',''));
        $model = M('mall_product','dsy_');
        $order = I('order','');
        $pone = trim(I('pone',''));
        $ptwo = trim(I('ptwo',''));
        $first = I('first','');
        $second = I('second','');
        $third = I('third','');
        $pname = trim(I('pname',''));
        $recommend = I('recommend','');
        $upanddown = I('upanddown','');
//        $where = '';
        if($first>0){
            $where_s['flid'] = array('eq',$first);
            $where_2['b.flid'] = array('eq',$first);

        }
        if($second>0){
            $where_s['slid'] = array('eq',$second);
            $where_2['b.slid'] = array('eq',$second);
        }
        if($third>0){
            $where_s['tlid'] = array('eq',$third);
            $where_2['b.tlid'] = array('eq',$third);
        }


        if(!empty($jd_num)){
            $where_s['skuid'] = array('eq',$jd_num);
            $where_2['b.skuid'] = array('eq',$jd_num);
        }
        if(!empty($dsy_num)){
            $where_s['id'] = array('eq',$dsy_num);
            $where_2['b.id'] = array('eq',$dsy_num);
        }
        if(!empty($upanddown)){
            $where_s['upanddown'] = array('eq',$upanddown);;
            $where_2['b.upanddown'] = array('eq',$upanddown);
        }
        if(!empty($order)){
            if($order == 1){
                $order_string = 'difference_price asc';
                $order_string2 = 'b.difference_price asc';
            }else{
                $order_string = 'difference_price desc';
                $order_string2 = 'b.difference_price desc';
            }
        }else{
            $order_string = 'id asc';
            $order_string2 = 'b.id asc';
        }
        if(!empty($pone) && !empty($ptwo)){
            if(is_numeric($pone) && is_numeric($ptwo)){
                $jd_price[] = $pone;
                $jd_price[] = $ptwo;
            }
        }

        if (!empty($pname) || $pname == 0) {
            $where_2['b.name'] = array('like','%'.$pname.'%');
        }
        $where_2['b.type'] = array('eq',1);
//        $where_s['type'] = array('eq',1);
        if(!empty($recommend)){
            $info = M('mall_product_recommend')
                ->join('as a left join dsy_mall_product as  b on a.pid = b.id')
                ->where($where_2)
                ->order($order_string2)
                ->page($page+1,$limit)
//                ->field('b.id,b.skuid,b.price,b.jd_price,b.wz_price,b.cost_price,b.name,b.upanddown,b.difference_price as left_price,b.isrecommend ')
                ->field('b.id,b.skuid,b.etime,b.price,b.jd_price,b.wz_price,b.cost_price,b.name,b.upanddown,b.difference_price,b.isrecommend ')
                ->select();
            if(!empty($info)){
                foreach($info as $key=>$value){
                    $info[$key]['isrecommend'] = 1;
                }
            }
        }else{
//            $id_string = sphinx('name',$pname,$where_s,$order_string,$page,$limit,$difference_price);
            if($where_s==null){
                $where_s['upanddown'] = ['eq',1] ;
                $where_s['status'] = ['eq',1] ;
//                $where_s['type'] = ['eq',2];
            }
            $id_string = sphinx('name',$pname,$where_s,$order_string,$page,$limit,$jd_price);

            $id_string_id = $id_string['id_str'];
            $num = $id_string['total_found'];

            $where_in['id'] = array('in',$id_string_id);
            if(!empty($id_string_id)){
                $info = $model
                    ->field('id,skuid,price,jd_price,wz_price,cost_price,name,upanddown,difference_price,isrecommend,etime ')
                    ->where($where_in)
                    ->order($order_string)
                    ->select();
            }else{
                $info = array();
            }
        }

        return Response::mjson($info,$num);
    }

    /**
     * 更新商品数据
    **/
    public function update_products(){
        $ids = I('ids','');
        if(empty($ids)){
            return Response::show(400,'尚未选择商品');
        }
        $ids = implode(',',$ids);
        $info = check_is_sell_new($ids);
        $this->ajaxReturn($info);
    }

    /**
     * 推荐商品
    **/
    public function recommend(){
        $ids = I('ids');
        $model = M('mall_product','dsy_');
        $recommend = M('mall_product_recommend');
        $model->startTrans();
        $data['isrecommend'] = 1;
        $check = true;
        foreach($ids  as $value){
            $where['id'] = array('eq',$value);
            $save = $model->where($where)->save($data);
            //同步到es start
            $es_data = [];
            $es_data['isrecommend'] = 1;
            editEs($es_data, $value);
            //同步到es end
            //增加推荐表数据
            $data_recommend['pid'] = $value;
            $data_recommend['time'] = NOW;
            $data_recommend['status'] = 1;
            $where_c['pid'] = array('eq',$value);
            $info = $recommend->where($where_c)->find();
            $result = true;
            if (!empty($info)) {
            }else{
                $result = $recommend->add($data_recommend);
            }

            if($save !== false && $result){

            }else{
                $check = false;
                break;
            }
        }

        $ids_str = implode(',', $ids);
        $infos_str = [];
        $infos = $model->where(['id' => ['in', $ids_str]])->field('skuid,name')->select();
        foreach ($infos as $v) {
            $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
        }
        //添加操作日志
        $admin_log = '推荐京东商品:' . implode(',', $infos_str);
        if($check==true){
            $model->commit();
            admin_log($admin_log, 1, 'dsy_mall_product:' . $ids_str);
            $this->ajaxReturn(1);
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_mall_product:' . $ids_str);
            $this->ajaxReturn(0);
        }

    }

    /**
     * 取消推荐
    **/
    public function cancle_recommend(){
        $ids = I('ids');
        $model = M('mall_product','dsy_');
        $recommend = M('mall_product_recommend');
        $model->startTrans();
        $data['isrecommend'] = 2;
        $check = true;
        foreach($ids  as $value){
            $where['id'] = array('eq',$value);
            $save = $model->where($where)->save($data);
            //同步到es start
            $es_data = [];
            $es_data['isrecommend'] = 2;
            editEs($es_data, $value);
            //同步到es end
            //删除推荐表数据
            $where_recommend['pid'] = array('eq',$value);
            $result = $recommend->where($where_recommend)->delete();
            if($save !== false && $result!==false){

            }else{
                $check = false;
                break;
            }
        }
        $ids_str = implode(',', $ids);
        $infos_str = [];
        $infos = $model->where(['id' => ['in', $ids_str]])->field('skuid,name')->select();
        foreach ($infos as $v) {
            $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
        }
        //添加操作日志
        $admin_log = '取消推荐京东商品:' . implode(',', $infos_str);
        if($check==true){
            $model->commit();
            admin_log($admin_log, 1, 'dsy_mall_product:' . $ids_str);
            $this->ajaxReturn(1);
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_mall_product:' . $ids_str);
            $this->ajaxReturn(0);
        }
    }

    /**
     * 商品详情详情界面
     */
    public function product_detail(){
        $pid = I('id','');
        if(empty($pid)){
            return '请选择商品';
        }
        $pinfo = M('mall_product','dsy_')->where(['id'=>$pid])->getField('skuId,price,wz_price,jd_price,cost_price',true);
        if(!empty($pinfo)){
            foreach($pinfo as $key => $val){
                $skuid = $val['skuid'];
            }
            $info = product_info($token,$skuid);
            $price = product_price($token,$skuid,$pinfo);
            $self_price = $pinfo[$skuid]['price'];
            $price['self_price'] = $self_price;
            $catid = str_ireplace(';',',',$info['category']);
            $category_name = product_level($token,$catid);
            $plevel_name = '';
            foreach($category_name as $key=>$val){
                if($val['categoryLevel'] == 2){
                    $plevel_name = $val['categoryName'];
                }
            }
            if(!empty($info)){
                $this->assign('info',$info);
            }
            if(!empty($price)){
                $this->assign('price',$price);
            }
            if(!empty($plevel_name)){
                $this->assign('level_name',$plevel_name);
            }
        }

        $this->display('product_detail');
    }

    /**
     *订单列表
    **/
    public function order_index(){
        $model = M('mall_order','dsy_');
        $where['a.order_type'] = array('in','2,3,4');
        $start = date('Y-m-01', strtotime(date("Y-m-d")));
        $end = date('Y-m-d');
        $start_time = $start.' 00:00:00';
        $end_time = $end.' 23:59:59';

        $where['a.time'] = array('between',array($start_time,$end_time));
        $token = '';
        $info = $model
            ->join('as a left join dsy_mall_order_notpay as b on a.order_notpay_num = b.ordernum')
            ->where($where)
            ->field('a.id as id,b.wz_orderid,a.pid,a.price,a.ordernum,a.time,b.wz_status,a.status')
            ->select();

        $all_money = 0;
        $all_profit = 0;
        foreach($info as $key=>$value){
            $pids = explode(',',$value['pid']);
            $ordernum = $value['ordernum'];
            $profit_all = 0;
            $wz_orderid  = $value['wz_orderid'];
            if($value['status']>1&&$value['status']<8&&$value['status'] != 3 &&$value['status'] != 6) {
                foreach ($pids as $kk => $vv) {
                    //查询该订单是否申请售后
                    $where_check['ordernum'] = array('eq',$ordernum);
                    $where_check['pid'] = array('eq',$vv);
                    $check = M('mall_order_return','dsy_')->where($where_check)->find();
                    if(!empty($check)){
                        if($check['status'] !=2){
                            $wz_info = product_status($token,$wz_orderid,$check['skuid']);
                            if($wz_info['service_step'] != 20 || $wz_info['service_step'] != 60){
                                $num_info = getDetalinfo($ordernum, $vv);
                                if(count($num_info)==count($num_info,1)){
                                    $profit = round($num_info['price'] - $num_info['prime_cost'], 2) * $num_info['num'];//利润
                                    $profit_all += $profit;
                                }else{
                                    foreach($num_info as $kkk=>$vvv){
                                        $profit = round($vvv['price'] - $vvv['prime_cost'], 2) * $vvv['num'];//利润
                                        $profit_all += $profit;
                                    }
                                }
                            }
                        }
                    }else{
                        $num_info = getDetalinfo($ordernum, $vv);
                        if(count($num_info)==count($num_info,1)){
                            $profit = round($num_info['price'] - $num_info['prime_cost'], 2) * $num_info['num'];//利润
                            $profit_all += $profit;
                        }else{
                            foreach($num_info as $kkk=>$vvv){
                                $profit = round($vvv['price'] - $vvv['prime_cost'], 2) * $vvv['num'];//利润
                                $profit_all += $profit;
                            }
                        }
                    }

                }
                $all_money += (float)$value['price'];
            }
            $info[$key]['price'] = (float)$value['price'];
            $info[$key]['profit'] = $profit_all;
            $all_profit += $profit_all;
        }

        $start = date('Y-m-01', strtotime(date("Y-m-d")));
        $end = date('Y-m-d');
        $this->assign('start',$start);
        $this->assign('end',$end);
        $this->assign('money',$all_money);
        $this->assign('profit',$all_profit);
        $this->display('order_index');
    }

    /**
     * 订单列表数据
    **/
    public function order_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $ordernum = I('ordernum','');//订单编号
        $wzordernum = I('wzordernum','');//微知订单号
        $addrname = I('addrname','');//收获人姓名
        $start_time = I('start1','');
        $end_time = I('end','');
        $status = I('status','');
        $exchange_num = I('exchange_num','');
        $model = M('mall_order','dsy_');
        $where['a.order_type'] = array('in','2,3,4');
        if(!empty($exchange_num)){
            $where['d.num'] = array('eq',$exchange_num);
        }

        if(!empty($ordernum)){
            $where['a.ordernum'] = array('eq',$ordernum);
        }

        if(!empty($addrname)){
            $where['a.name'] = array('eq',$addrname);
        }

        if(!empty($wzordernum)){
            $where['b.wz_orderid'] = array('eq',$wzordernum);
        }
        if(!empty($status)){
            $where['a.status'] = array('eq',$status);
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
        $info = $model
            ->join('as a left join dsy_mall_order_notpay as b on a.order_notpay_num = b.ordernum')
            ->join('left join dsy_mall_shops as c on a.sid = c.id')
            ->join('left join dsy_company_exchange_record as d on b.ordernum = d.ordernum')
            ->where($where)
            ->order('a.id desc')
            ->page($page,$limit)
            ->field('a.id as id,b.wz_orderid,a.pid,a.price,a.ordernum,a.time,b.wz_status,a.status,a.isneedinvoice,a.paytype,a.order_type,c.name as cname')
            ->select();

        $count = $model
            ->join('as a left join dsy_mall_order_notpay as b on a.order_notpay_num = b.ordernum')
            ->join('left join dsy_mall_shops as c on a.sid = c.id')
            ->join('left join dsy_company_exchange_record as d on b.ordernum = d.ordernum')
            ->where($where)
            ->count();

        if(empty($info)){
            return Response::mjson($info,$count);
        }
        foreach($info as $key=>$value){
            $pid_array = explode(',',$value['pid']);
            $ordernum = $value['ordernum'];
            //判断该订单中的商品有没有被退货
            $cost_price = 0;
            $prilft_price = 0;
            if($value['status']>1&&$value['status']<8&&$value['status'] != 3 &&$value['status'] != 6){

                foreach($pid_array as $val){
                    //查询该订单是否申请售后
                    $where_check['ordernum'] = array('eq',$ordernum);
                    $where_check['pid'] = array('eq',$val);
                    $check = M('mall_order_return','dsy_')->where($where_check)->find();
                    if(!empty($check)){
                        if($check['status'] !=2){
                            $num_info = getDetalinfo($value['ordernum'],$val);
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
                    }else{
                        $num_info = getDetalinfo($value['ordernum'],$val);
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

            $info[$key]['cost_price'] = $cost_price;
            $info[$key]['profit'] = $prilft_price;

        }

        return Response::mjson($info,$count);
    }

    /**
     * 订单详情
    **/
    public function order_detail(){
        $id = I('id','');
        $wz_orderid = I('wz_orderid','');
        $ordernum = I('ordernum','');
        if(empty($id)||empty($wz_orderid)||empty($ordernum)){
            return false;
        }
        $info = M('mall_order','dsy_')->find($id);
        $name = $info['name'];
        $mobile = $info['mobile'];
        $address = $info['address'];
        $pids = explode(',',$info['pid']);

        //订单类型
        $order_type = $info['order_type'];
        $enum = $info['enum'];//订单编号
        $etype = $info['etype'];//快递类型

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

        foreach($pids as $key=>$value){
            $detail = getDetalinfo($ordernum,$value);
            $skuid = $detail['skuid'];
            if($order_type == 2 || $order_type == 3 || $order_type==4){
                $travel_info = product_travel(['wzOrderId'=>$wz_orderid],$skuid);//物流信息
            }else{
                $travel_info = $change_array;
            }
            $pname = $detail['pname'];//商品名称
            if(count($detail)==count($detail,1)){
                $num = $detail['num'];//购买数量
                $specifications = $detail['specifications'];//规格
                $data['pid'] = $value;
                $data['pname'] = $pname;
                $data['price'] = $detail['price'];
                $data['num'] = $num;
                $data['travel_info'] = $travel_info;
                $data['specifications'] = $specifications;
                $where_check['ordernum'] = array('eq',$ordernum);
                $where_check['pid'] = array('eq',$value);
                $check = M('mall_order_return','dsy_')->where($where_check)->find();
                if(!empty($check)){
                    if($check['status']==1){
                        $data['return'] = '申请售后中';
                    }elseif($check['status']==2){
                        $data['return'] = '同意退款';
                    }else{
                        $data['return'] = '驳回';
                    }
                }else{
                    $data['return'] = '';
                }
                $products[] = $data;
            }else{
                foreach($detail as $kk=>$vv){
                    $num = $vv['num'];//购买数量
                    $specifications = $vv['specifications'];//规格
                    $data['pid'] = $value;
                    $data['pname'] = $pname;
                    $data['price'] = $vv['price'];
                    $data['num'] = $num;
                    $data['travel_info'] = $travel_info;
                    $data['specifications'] = $specifications;
                    $where_check['ordernum'] = array('eq',$ordernum);
                    $where_check['pid'] = array('eq',$value);
                    $check = M('mall_order_return','dsy_')->where($where_check)->find();
                    if(!empty($check)){
                        if($check['status']==1){
                            $data['return'] = '申请售后中';
                        }elseif($check['status']==2){
                            $data['return'] = '同意退款';
                        }else{
                            $data['return'] = '驳回';
                        }
                    }else{
                        $data['return'] = '';
                    }
                    $products[] = $data;
                }
            }
        }
        $this->assign('pinfo',$products);
        $this->assign('name',$name);
        $this->assign('mobile',$mobile);
        $this->assign('address',$address);

        //售后信息
        $returnList = M('mall_order_return')->where(['ordernum' => $ordernum, 'num' => ['exp', 'is not null']])->select();
        if (!empty($returnList)) {
            $OrderReturnModel = new \Common\Model\OrderReturnModel;
            foreach ($returnList as $k => $v) {
                $orderWhere = ['ordernum' => $ordernum, 'pid' => $v['pid']];
                if (!empty($v['specv'])) {
                    $orderWhere['specv'] = $v['specv'];
                }
                $sInfo = M('MallOrderSpecifications')->where($orderWhere)->find();
                $returnList[$k]['pro_name'] = $sInfo['pro_name'];
                $returnList[$k]['pro_skuid'] = $sInfo['pro_skuid'];
                $returnList[$k]['pro_num'] = $sInfo['num'];
                $returnList[$k]['pro_price'] = $sInfo['price']*$sInfo['num'];
                $returnList[$k]['specifications'] = $sInfo['specifications'];
                $returnList[$k]['status'] = $OrderReturnModel::returnStatus($v['status']);
                $returnList[$k]['type'] = $OrderReturnModel::type($v['type']);
                if ($v['status'] != 2) {
                    $returnList[$k]['return_money'] = '--';
                    $returnList[$k]['return_wquota'] = '--';
                    $returnList[$k]['return_card'] = '--';
                }
            }
        }
        $this->assign('returnList', $returnList);

        $this->display('order_detail');
    }

    /**
     * 提交时间计算总流水 利润
    **/
    public function ajax_p(){
        $stime = $_POST['stime'];
        $etime = $_POST['etime'];
        $ordernum = $_POST['ordernum'];
        $wzordernum = $_POST['wzordernum'];
        $model = M('mall_order','dsy_');
        $where['a.order_type'] = array('in','1,2,3');
        if(!empty($stime)&&!empty($etime)){
            if($stime==$etime){
                $where['a.time'] = array('like',"%$etime%");
            }else{
                $start_time = $stime.' 00:00:00';
                $end_time = $etime.' 23:59:59';
                $where['a.time'] = array('between',array($start_time,$end_time));
            }
        }
        if(!empty($ordernum)){
            $where['a.ordernum'] = array('eq',$ordernum);
        }
        if(!empty($wzordernum)){
            $where['b.wz_orderid'] = array('eq',$wzordernum);
        }
        $info = $model
            ->join('as a left join dsy_mall_order_notpay as b on a.order_notpay_num = b.ordernum')
            ->where($where)
            ->field('a.id,a.ordernum,a.order_notpay_num,a.time,a.freight,a.price,a.pid,a.status')
            ->select();
        $token = selAccess_token();
        foreach($info as $key=>$value){
            $pids = explode(',',$value['pid']);
            $ordernum = $value['ordernum'];
            $wz_orderid  = $value['wz_orderid'];
            $profit_all = 0;
            if($value['status']>1&&$value['status']<8&&$value['status'] != 3&&$value['status'] != 6){
                foreach($pids as $kk=>$vv ){
                    //查询该订单是否申请售后
                    $where_check['ordernum'] = array('eq',$ordernum);
                    $where_check['pid'] = array('eq',$vv);
                    $check = M('mall_order_return','dsy_')->where($where_check)->find();
                    if(!empty($check)){
                        if($check['status'] !=2){
                            $wz_info = product_status($token,$wz_orderid,$check['skuid']);
                            if($wz_info['service_step'] != 20 || $wz_info['service_step'] != 60){
                                $num_info = getDetalinfo($ordernum,$vv);
                                if(count($num_info)==count($num_info,1)){
                                    $profit = round($num_info['price'] - $num_info['prime_cost'], 2) * $num_info['num'];//利润
                                    $profit_all += $profit;
                                }else{
                                    foreach($num_info as $kkk=>$vvv){
                                        $profit = round($vvv['price'] - $vvv['prime_cost'], 2) * $vvv['num'];//利润
                                        $profit_all += $profit;
                                    }
                                }
                            }
                        }
                    }else{
                        $num_info = getDetalinfo($ordernum,$vv);
                        if(count($num_info)==count($num_info,1)){
                            $profit = round($num_info['price'] - $num_info['prime_cost'], 2) * $num_info['num'];//利润
                            $profit_all += $profit;
                        }else{
                            foreach($num_info as $kkk=>$vvv){
                                $profit = round($vvv['price'] - $vvv['prime_cost'], 2) * $vvv['num'];//利润
                                $profit_all += $profit;
                            }
                        }
                    }

                }
                $info[$key]['price'] = (float)$value['price'];
            }
            $info[$key]['profit'] = $profit_all;
        }

        $all_money = 0;
        $all_profit = 0;
        foreach($info as $value){
            $all_money += $value['price'];
            $all_profit += $value['profit'];
        }
        $data['money'] = $all_money;
        $data['profit'] = $all_profit;
        $this->ajaxReturn($data);
    }

    /**
     * 套餐列表
    **/
    public function set_meal(){

        $this->display('setmeal_list');
    }

    /**
     *  套餐列表数据
    **/
    public function setmeal_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $model = M('company_package','dsy_');
        $name = trim(I('name',''));
        if(!empty($name) || $name=='0'){
            $where['name'] = array('like',"%$name%");
        }
        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->order('id desc')
            ->select();

        $num = $model
            ->where($where)
            ->count();
        foreach($info as $key=>$value){
            if (empty($value['goods'])) {
                $pids = $value['pids'];
                if($value['type'] == 1 || $value['type'] == 2){
                    $where1['id'] = array('in',$pids);
                    $names = M('mall_product','dsy_')->where($where1)->getField('name',true);
                }elseif($value['type'] == 3){
                    $where1['product_id'] = array('in',$pids);
                    $names = M('sn_product','dsy_')->where($where1)->getField('name',true);
                }
            } else {
                $goods = json_decode($value['goods'], true);
                $names = [];
                if($value['type'] == 1 || $value['type'] == 2){
                    foreach ($goods as $v) {
                        $pName = M('mall_product')->where(['id' => $v['id']])->getField('name');
                        if (!empty($pName)) {
                            $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                        }
                    }
                }elseif($value['type'] == 3){
                    foreach ($goods as $v) {
                        $pName = M('sn_product')->where(['product_id' => $v['id']])->getField('name');
                        if (!empty($pName)) {
                            $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                        }
                    }
                }
            }
            $info[$key]['pids'] = implode(',', $names);
            if(!empty($value['pic'])){
                $img = format_img($value['pic'], IMG_VIEW);
                $info[$key]['pic'] =  '<a href="'.$img.'" target="view_window">点击查看</a>';
            }
            //查询所属活动
            $where_aname['id'] = array('eq',$value['aid']);
            $aname = M('company_activity','dsy_')->where($where_aname)->getField('name');
            $info[$key]['aid'] = $aname;
        }
        return Response::mjson($info,$num);
    }

    /**
     * 添加套餐页面
    **/
    public function setmeal_add(){
        $this->display('setmeal_add');
    }

    /**
     * 选择套餐商品页面
     **/
    public function choose_products()
    {
        $info = I('info', '');
        $this->assign('info', $info);

        $products_type = I('products_type', '');
        if (empty($products_type)) {
            $products_type = 4;
        }
        $this->assign('products_type', $products_type);

//        $where = [];
//        $where['status'] = 1;
//        $where['upanddown'] = 1;
//        if (in_array($products_type, [1, 2])) {
//            $where['type'] = $products_type;
//        }
//        $count = M('mall_product')
//            ->where($where)
//            ->count();
        $filter = [];
        $filter[]['term']['status'] = 1;
        $filter[]['term']['upanddown'] = 1;
        if (in_array($products_type, [1, 2])) {
            $filter[]['term']['type'] = $products_type;
        }
        //运用elasticsearch查询商品名称匹配的商品id
        $url = ES_URL . '/' . ES_INDEX . '/_search';
        $data = [
            "query" => [
                "bool" => [
                    "filter" => $filter
                ]
            ],
            "_source" => ["skuid"]
        ];
        $data = json_encode($data);
        $re = es_curl($url, 'post', $data);
        $total = 0;
        if ($re['timed_out'] == false) {
            $total = $re['hits']['total'];
        }
        $this->assign('count', $total);

        $this->display('choice_products');
    }

    /**
     * 套餐选择商品返回商品页面数据
    **/
    public function products_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex:'0';
        $pages = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $pname = trim(I('pname',''));
        $model = M('mall_product','dsy_');
        $info1 = I('info',0);
        $products_type = I('products_type','');
        $pid = I('pid','');
        if(empty($products_type)){
            return false;
        }
        $where['upanddown'] = array('eq',1);
        $where['status'] = array('eq',1);
        if(!empty($pid)){
            $where['skuid'] = array('eq',$pid);
        }

        if($products_type==2){
            if(!empty($pname)){
                $where['name'] = array('like',"$pname%");
            }
            $where['type'] = array('eq',2);
            $info = $model
                ->field('id,cnum,skuid,price,jd_price,cost_price,name')
                ->where($where)
                ->page($pages,$limit)
                ->select();
            $num = $model
                ->where($where)
                ->count();

        }elseif($products_type==1){
            $where['type'] = array('eq',1);
            $id_string = sphinx('name',$pname,$where,'',$page,$limit,'');
            $num = $id_string['total_found'];
            if($num==0){
                $info = array();
            }else{
                $id_string_id = $id_string['id_str'];
                $where_s['id'] = array('in',$id_string_id);
                $info = $model
                    ->field('id,cnum,skuid,price,jd_price,cost_price,name')
                    ->where($where_s)
                    ->select();
            }
        }else{

            $id_string = sphinx('name',$pname,$where,'',$page,$limit,'');
            $num = $id_string['total_found'];
            if($num==0){
                $info = array();
            }else{
                $id_string_id = $id_string['id_str'];
                $where_s['id'] = array('in',$id_string_id);
                $info = $model
                    ->field('id,cnum,skuid,price,jd_price,cost_price,name')
                    ->where($where_s)
                    ->select();
            }
        }

        if($info1 !== 0){
            $array = explode(',',$info1);
            foreach($info as $key=>$value){
                if(in_array($value['id'],$array)){
                    $info[$key]['LAY_CHECKED'] = true;
                }
            }
        }
        if($info1 != 0){
            $parray = explode(',',$info1);
        }else{
            $parray = array();
        }
        $data['code'] = 200;
        $data['msg'] = '';
        $data['count'] = $num;
        $data['data'] = $info;
        $data['is_data'] = $parray;
        $this->ajaxReturn($data);
    }

    /**
     * 选择商品提交
    **/
    public function add_product(){
        $ids = I('ids','');
        $this->ajaxReturn($ids);
    }

    /**
     * 根据商品id，转换成商品名称
     **/
    public function getGoodsName()
    {
        $goods = I('goods', []);
        $type = I('type','');
        if (empty($goods) || empty($type)) {
            $this->ajaxReturn('');
        }
        $names = [];
        if($type == 1 || $type == 2){
            foreach ($goods as $v) {
                $pName = M('mall_product')->where(['id' => $v['id']])->getField('name');
                if (!empty($pName)) {
                    $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                }
            }
        }elseif($type == 3){
            foreach ($goods as $v) {
                $pName = M('sn_product')->where(['product_id' => $v['id']])->getField('name');
                if (!empty($pName)) {
                    $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                }
            }
        }
        $names = implode(',', $names);
        $this->ajaxReturn($names);
    }
    /**
     * 根据商品id，转换成商品名称
    **/
    public function get_products_names(){
        $ids = I('ids','');
        if(!empty($ids)){
            $names = getProductsNames($ids);
            $this->ajaxReturn($names);
        }
    }

    /**
     * 预览套餐页面
     **/
    public function view_setmeal(){
        $pids = I('pids','');
        $name = I('name','');
        $type = I('products_type','');
        if(!empty($pids)){
            if($type == 1 || $type == 2){
                $model = M('mall_product','dsy_');
                $where['id'] = array('in',$pids);
                $info = $model
                    ->where($where)
                    ->field('name,pic')
                    ->select();
                if($type==2){
                    foreach($info as $key=>$value){
                        $info[$key]['pic'] = format_img($value['pic'], IMG_VIEW);
                    }
                }
            }elseif($type == 3){
                $model = M('sn_product');
                $where['product_id'] = array('in',$pids);
                $info = $model
                    ->where($where)
                    ->field('name,img as pic')
                    ->select();
            }
            $this->assign('info',$info);
        }
        $count = strlen($name);
        if($count > 9){
            $name = mb_substr($name,0,9,'UTF-8') . '...';
        }
        $this->assign('name',$name);
        $this->display('view');
    }

    /**
     * 添加套餐操作
    **/
    public function add_setmeal(){
        $name = trim(I('name',''));
        $price = I('price','');
        $products = I('products','');
        $pic = $_FILES['pic'];
        $remarks = I('remarks','');
        $goods = I('goods','');
        $products_type = I('products_type','');
        if(!empty($name) || $name=='0'){

        }else{
            return Response::show(300,'请填写完整后提交');
        }
        if(empty($price)||empty($products)||empty($pic)||empty($products_type)){

            return Response::show(300,'请填写完整后提交');
        }
//        $products_num = explode(',',$products);
//        if(count($products_num)>20){
//            return Response::show(400,'提交商品数量不得超过20个');
//        }
        $errorGoods = $this->checkProduct($products);
        if(!empty($errorGoods)){
            $message = implode(',',$errorGoods) . '商品下架或无货，请更换商品';
            return Response::show(400,$message);
        }
        if($price<0){
            return Response::show(400,'价格不能为负数');
        }
        if(!empty($pic)){
            $pic_string = uploadfile($pic);
            if(empty($pic_string)){
                return Response::show(400,'图片上传失败');
            }
            $data['pic'] = $pic_string;
        }
        $data['name'] = $name;
        $data['pids'] = $products;
        $data['goods'] = empty($goods) ? '' : json_encode($goods);
        $data['price'] = $price;
        $data['type'] = $products_type;
        if(!empty($remarks)){
            $data['remarks'] = $remarks;
        }
        $data['time'] = time();
        //添加操作日志
        $admin_log = '新增套餐:' . $name;
        $add = M('company_package','dsy_')->add($data);
        if($add !== false){
            admin_log($admin_log, 1, 'dsy_company_package:' . $add);
            return Response::show(200,'新增成功');
        }else{
            admin_log($admin_log, 0, 'dsy_company_package');
            return Response::show(400,'新增失败');
        }
    }
    /**
     * 获取套餐商品所有价格价格
     */
    public function get_product_price(){
        $goods = I('goods');
        $products_type = I('products_type');
        $price = I('price');
        $count_price = 0;
        foreach($goods as $key=>$value){
            if($products_type == 1 || $products_type == 2){
                $pinfo = getpinfobyid($value['id']);
            }elseif($products_type == 3){
                $pinfo = getsnpinfobyid($value['id']);
            }
            $count_price += $pinfo['price'] * $value['num'];
        }
        $msg = "当前套餐价格：<font style='color:red;'>" .$price. "</font>元&nbsp;&nbsp;&nbsp;商品套餐金额：<font style='color:red;'>" .$count_price. "</font>元<br /><font style='margin-left:100px;'>请您再次确认是否保存？</font>";
        return Response::show(200,$msg);
    }
    /**
     * 修改页面
     **/
    public function edit_index(){
        $id = I('id',0);
        $info = M('company_package','dsy_')
            ->find($id);
        if(!empty($info)){
            if (empty($info['goods'])) {
                $pids = $info['pids'];
                if($info['type'] == 1 || $info['type'] == 2){
                    $where1['id'] = array('in',$pids);
                    $names = M('mall_product','dsy_')->where($where1)->getField('name',true);
                }else{
                    $where1['product_id'] = array('in',$pids);
                    $names = M('sn_product','dsy_')->where($where1)->getField('name',true);
                }
            } else {
                $goods = json_decode($info['goods'], true);
                $names = [];
                if($info['type'] == 1 || $info['type'] == 2){
                    foreach ($goods as $v) {
                        $pName = M('mall_product')->where(['id' => $v['id']])->getField('name');
                        if (!empty($pName)) {
                            $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                        }
                    }
                }elseif($info['type'] == 3){
                    foreach ($goods as $v) {
                        $pName = M('sn_product')->where(['product_id' => $v['id']])->getField('name');
                        if (!empty($pName)) {
                            $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                        }
                    }
                }
            }
            $info['pnames'] = implode(',', $names);
            $info['pic'] = format_img($info['pic'], IMG_VIEW);
        }
        $this->assign('info',$info);
        $this->assign('goods', empty($info['goods']) ? [] : $info['goods']);
        $this->assign('id',$id);
        $this->display('setmeal_edit');
    }

    /**
     * 修改套餐
    **/
    public function edit_setmeal(){
        $id = I('id','');
        $name = trim(I('name',''));
        $price = I('price','');
        $products = I('products','');
        $pic = $_FILES['pic'];
        $remarks = I('remarks','');
        $goods = I('goods','');
        $products_type = I('products_type','');
        if(!empty($name) || $name == 0){
            $data['name'] = $name;
        }
        if(!empty($price)){
            $data['price'] = $price;
        }
        if(!empty($products)){
            $data['pids'] = $products;
            $data['goods'] = empty($goods) ? '' : json_encode($goods);
        }
        $errorGoods = $this->checkProduct($products);
        if(!empty($errorGoods)){
            $message = implode(',',$errorGoods) . '商品下架或无货，请更换商品';
            return Response::show(400,$message);
        }
        if(!empty($pic)){
            $pic_string = uploadfile($pic);
            if(empty($pic_string)){
                return Response::show(400,'图片上传失败');
            }
            $data['pic'] = $pic_string;
        }
        if(!empty($remarks)){
            $data['remarks'] = $remarks;
        }
        $where['id'] = array('eq',$id);
        //添加操作日志
        $admin_log = '编辑套餐:' . $name;
        $model = M('company_package','dsy_');
        $model->startTrans();
        if(!empty($data)){
            $save = $model->where($where)->save($data);
        }
        if($save !== false){
            //将套餐对应的活动修改
            $product_arr = array_unique(array_filter(explode(',', $products)));
            if (!empty($products)) {
                S('package' . $id, NULL);
            }
            if (!empty($product_arr)) {
                $time = time();//当前时间戳
                $where = [];
                $where['is_del'] = 0;
                $where['start_time'] = ['ELT', $time];
                $where['end_time'] = ['EGT', $time];
                $where['get_type'] = 3;
                $where['FIND_IN_SET(' . $id . ',packages)'] = array('gt', 0);
                $ainfo = M('company_activity', 'dsy_')->where($where)->getField('`id`,`packages`,`packages_info`', true);
                $c_model = M('mall_product_specification_config');
                foreach ($ainfo as $k => $v) {
                    $goods = unserialize($v['packages_info']);
                    $packages_arr = explode(',', $v['packages']);
                    $goods_new = [];
                    $i = 0;
                    foreach ($goods as $gk => $gv) {
                        if (in_array($gv['package_id'], $packages_arr)) {
                            if ($gv['package_id'] == $id) {
                                if ($i == 0) {
                                    $i = 1;
                                    foreach ($product_arr as $pak => $pav) {
                                        if($products_type == 1 || $products_type == 2){
                                            $one_product = getpinfobyid($pav);
                                        }elseif($products_type == 3){
                                            $one_product = getpinfobyid($pav);
                                        }
                                        $config_list = $c_model
                                            ->where(['pid' => $pav])
                                            ->field('`vkey`,`price` as save_price')
                                            ->select();
                                        $data2 = [];
                                        $data2['package_id'] = $id;
                                        $data2['package_name'] = $name;
                                        $data2['pid'] = $pav;
                                        $data2['pname'] = $one_product['pname'];
                                        if (empty($config_list)) {
                                            $data2['price'] = $one_product['price'];
                                            $data2['wz_price'] = $one_product['wz_price'];
                                            $data2['cost_price'] = $one_product['cost_price'];
                                            $data2['save_price'] = $one_product['price'];
                                            $data2['spec'] = 0;
                                        } else {
                                            $config_default = $c_model
                                                ->where(['pid' => $pav])
                                                ->field('`price`,`jd_price`,`cost_price`')
                                                ->order('is_default desc')
                                                ->limit(1)
                                                ->find();
                                            $data2['price'] = $config_default['price'];
                                            $data2['wz_price'] = $config_default['jd_price'];
                                            $data2['cost_price'] = $config_default['cost_price'];
                                            $data2['save_price'] = $config_default['price'];
                                            $data2['spec'] = $config_list;
                                        }
                                        $goods_new[] = $data2;
                                    }
                                }
                            } else {
                                $goods_new[] = $gv;
                            }
                        }
                    }
                    M('company_activity', 'dsy_')->where(['id' => $k])->setField('packages_info', serialize($goods_new));
                }
            }

            $model->commit();
            admin_log($admin_log, 1, 'dsy_company_package:' . $id);
            return Response::show(200,'修改成功');
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_company_package:' . $id);
            return Response::show(400,'修改失败');
        }
    }
    /**
     * 验证商品是否有货无货
     */
    public function checkProduct($pids){
        $pro_sku = M('mall_product')->field('skuid')->where(['id'=>array('in',$pids)])->select();
        $pro_sku = array_column($pro_sku,'skuid');
        $stock = [];
        foreach($pro_sku as $val){
            $stock[] = array(
                'skuId'=> $val, //京东商品编号
                'num'=> 1 //商品数量
            );
        }
        $pro_sku = implode(',',$pro_sku);
        $jd_address = array(
            'province'=> 12, //京东省ID
            'city'=> 904, //京东市ID
            'county'=> 3379, //京东县/镇ID
            'town'=> 62187 //京东 区
        );
        $MallV1_2 = new MallV1_2Controller;
        $errorGoods = [];
        //判断商品是否可售
        $saleJudge = $MallV1_2->productSaleJudge($pro_sku);
        if ($saleJudge['status'] == 2) {
            $errorGoods = array_merge($errorGoods, array_column($saleJudge['sale'],'sku'));
        }
        //判断商品是否上架
        $stateJudge = $MallV1_2->productStateJudge($pro_sku);
        if ($stateJudge['status'] == 2) {
            $errorGoods = array_merge($errorGoods, array_column($stateJudge['state'],'sku'));
        }

        //判断商品库存是否足够
        $stockJudge = $MallV1_2->productStockJudge($jd_address,$stock,$pro_sku);
        if($stockJudge['status'] == 2) {
            $errorGoods = array_merge($errorGoods, array_column($stockJudge['stock'],'sku'));
        }
        //判断收货地址是否能购买
        $area_return = $MallV1_2->checkAreaJudge($pro_sku,$jd_address);
        if ($area_return['status'] == 2) {
            $errorGoods = array_merge($errorGoods, array_column($area_return['area'],'sku'));
        }
        $errorGoods = array_unique($errorGoods);
        return $errorGoods;
    }
    /**
     * 删除套餐
    **/
    public function del_package(){
        $id = I('ids','');
        $id = $id[0];
        if(empty($id)){
           return Response::show(300,'缺少参数');
        }
        $data['del'] = 1;
        $where['id'] = array('eq',$id);
        $infos = M('company_package', 'dsy_')->where($where)->getField('name');
        //添加操作日志
        $admin_log = '禁用套餐:' . $infos;
        $del = M('company_package','dsy_')->where($where)->save($data);
        if($del !== false){
            admin_log($admin_log, 1, 'dsy_company_package:' . $id);
            return Response::show(200, '删除成功');
        }else{
            admin_log($admin_log, 0, 'dsy_company_package:' . $id);
            return Response::show(400, '删除失败');
        }
    }

    /***
     * 启用套餐
    **/
    public function open_package(){
        $id = I('ids','');
        $id = $id[0];
        if(empty($id)){
            return Response::show(300,'缺少参数');
        }
        $data['del'] = 0;
        $where['id'] = array('eq',$id);
        $infos = M('company_package', 'dsy_')->where($where)->getField('name');
        //添加操作日志
        $admin_log = '启用套餐:' . $infos;
        $del = M('company_package','dsy_')->where($where)->save($data);
        if($del !== false){
            admin_log($admin_log, 1, 'dsy_company_package:' . $id);
            return Response::show(200,'启用成功');
        }else{
            admin_log($admin_log, 0, 'dsy_company_package:' . $id);
            return Response::show(400,'启用失败');
        }
    }

    /**
    * 活动列表
   **/
    public function activity_list(){
        $this->display('activity_index');
    }

    /**
     * 活动列表数据
    **/
    public function activity_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $cname = trim(I('cname',''));
        $aname = trim(I('aname',''));
        if(!empty($cname) || $cname == 0){
            $where['b.corporate_name'] = array('like',"%$cname%");
        }
        if(!empty($aname) || $aname=='0'){
            $where['a.name'] = array('like',"%$aname%");
        }
        $info = M('company_activity','dsy_')
            ->where($where)
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->page($page,$limit)
            ->order('id desc')
            ->field('a.id,a.name,a.cid,a.persons,a.start_time,a.end_time,a.type,a.pic,a.type,a.is_del,a.get_type')
            ->select();
        $num = M('company_activity','dsy_')
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                $cname = getcnamebycid($value['cid']);
                $info[$key]['cid'] = $cname;
                $person_array = explode(',',$value['persons']);
                $person_num = count($person_array);
                $info[$key]['persons'] = $person_num;
                if(!empty($value['pic'])){
                    $img = format_img($value['pic'], IMG_VIEW);
                    $info[$key]['pic'] =  '<a href="'.$img.'" target="view_window">点击查看</a>';
                }
                $info[$key]['start_time'] = date('Y-m-d',$value['start_time']);
                $info[$key]['end_time'] = date('Y-m-d',$value['end_time']);
                $now = date('Y-m-d');
                $now = strtotime($now);
                if($now<$value['start_time']){
                    $info[$key]['status'] = '未开始';
                }elseif($value['end_time']<$now){
                    $info[$key]['status'] = '已结束';
                }else{
                    $info[$key]['status'] = '活动中';
                }

            }
        }
        return Response::mjson($info,$num);
    }

    /**
     * 禁用活动
    **/
    public function stop_activity(){
        $id = I('ids','');
        $id = $id[0];
        $where['id'] = array('eq',$id);
        $data['is_del'] = 1;
        $infos = M('company_activity', 'dsy_')->where($where)->getField('name');
        //添加操作日志
        $admin_log = '禁用活动:' . $infos;
        $save = M('company_activity','dsy_')->where($where)->save($data);
        if($save==false){
            admin_log($admin_log, 1, 'dsy_company_activity:' . $id);
            return Response::show('400','已经禁用');
        }else{
            admin_log($admin_log, 0, 'dsy_company_activity:' . $id);
            return Response::show('200','禁用成功');
        }



    }

    /**
     * 启用活动
    **/
    public function use_activity(){
        $id = I('ids','');
        $id = $id[0];
        $where['id'] = array('eq',$id);
        $data['is_del'] = 0;
        $infos = M('company_activity', 'dsy_')->where($where)->getField('name');
        //添加操作日志
        $admin_log = '启用活动:' . $infos;
        $save = M('company_activity','dsy_')->where($where)->save($data);
        if($save==false){
            admin_log($admin_log, 1, 'dsy_company_activity:' . $id);
            return Response::show('400','已经启用');
        }else{
            admin_log($admin_log, 0, 'dsy_company_activity:' . $id);
            return Response::show('200','禁用成功');
        }



    }

    /**
     * 活动详情页面
    **/
    public function activity_detail(){
        $id = I('id','');
        $where['id'] = array('eq',$id);
        $info = M('company_activity','dsy_')->find($id);
        $eids = $info['persons'];

        $where1['a.employee_id'] = array('in',$eids);
        $names = M('employee','t_')
            ->join('as a left join t_personal as b on a.personal_id = b.personal_id')
            ->where($where1)
            ->field('b.name')
            ->select();
        if(!empty($names)){
            foreach($names as $value){
                $name[] = $value['name'];
            }
        }
        $allow_times = $info['allow_times'];
        $packages_ids = $info['packages'];
        $where_pids['id'] = array('in',$packages_ids);
        $pinfo = M('company_package','dsy_')
            ->where($where_pids)
            ->field('id,name,pids,price,goods,type')
            ->select();
        foreach($pinfo as $key=>$value){
            if (empty($value['goods'])) {
                if($value['type'] == 1 || $value['type'] == 2){
                    $where_pname['id'] = array('in',$value['pids']);
                    $names = M('mall_product','dsy_')->where($where_pname)->getField('name',true);
                }elseif($value['type'] == 3){
                    $where_pname['product_id'] = array('in',$value['pids']);
                    $names = M('sn_product','dsy_')->where($where_pname)->getField('name',true);
                }
            } else {
                $goods = json_decode($value['goods'], true);
                $names = [];
                if($value['type'] == 1 || $value['type'] == 2){
                    foreach ($goods as $v) {
                        $pName = M('mall_product')->where(['id' => $v['id']])->getField('name');
                        if (!empty($pName)) {
                            $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                        }
                    }
                }elseif($value['type'] == 3){
                    foreach ($goods as $v) {
                        $pName = M('sn_product')->where(['id' => $v['id']])->getField('name');
                        if (!empty($pName)) {
                            $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                        }
                    }
                }
            }
            $pinfo[$key]['pids'] = implode(',',$names);
        }
        $this->assign('setmeal',$pinfo);//套餐信息
        $this->assign('name',$name);//人员信息
        $this->assign('allow_times',$allow_times);//允许领取次数
        $this->display('activity_detail');
    }

    /**
     * 添加活动
    **/
    public function add_activity_index(){
        //返回所有公司
        $info = getCorporateList();
        $this->assign('companys',$info);
        $this->display('activity_add');
    }

    /**
     * 显示选择套餐页面
    **/
    public function choose_setmeals(){
        $setmeals = I('setmeals','');
        $model = M('company_package','dsy_');
        $where['del'] = array('neq',1);
//        $where['aid'] = array('exp','is null');
        $num = $model->where($where)->count();
        $this->assign('setmeals',$setmeals);
        $this->assign('count',$num);
        $this->display('choice_setmeals');
    }

    /**
     * 活动添加页面返回所有套餐数据
    **/
    public function all_packages(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $where['del'] = array('neq',1);
//        $where['aid'] = array('exp','is null');
        $model = M('company_package','dsy_');
        $setmeals = I('setmeals',0);
        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->order('id desc')
            ->select();
        $num = $model->where($where)->count();
        foreach($info as $key=>$value){
            if (empty($value['goods'])) {
                $pids = $value['pids'];
                if($value['type'] == 1 || $value['type'] == 2){
                    $where['id'] = array('in',$pids);
                    $names = M('mall_product','dsy_')->where($where)->getField('name',true);
                }elseif($value['type'] == 3){
                    $where['product_id'] = array('in',$pids);
                    $names = M('sn_product','dsy_')->where($where)->getField('name',true);
                }
            } else {
                $goods = json_decode($value['goods'], true);
                $names = [];
                if($value['type'] == 1 || $value['type'] == 2){
                    foreach ($goods as $v) {
                        $pName = M('mall_product')->where(['id' => $v['id']])->getField('name');
                        if (!empty($pName)) {
                            $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                        }
                    }
                }elseif($value['type'] == 3){
                    foreach ($goods as $v) {
                        $pName = M('sn_product')->where(['id' => $v['id']])->getField('name');
                        if (!empty($pName)) {
                            $names[] = ($v['num'] > 1 ? ('（' . $v['num'] . '份）') : '') . $pName;
                        }
                    }
                }
            }
            $info[$key]['pids'] = implode(',',$names);
            if(!empty($value['pic'])){
                $img = format_img($value['pic'], IMG_VIEW);
                $info[$key]['pic'] =  '<a href="'.$img.'" target="view_window">点击查看</a>';
            }
        }
        if($setmeals !== 0){
            $array = explode(',',$setmeals);
            foreach($info as $key=>$value){
                if(in_array($value['id'],$array)){
                    $info[$key]['LAY_CHECKED'] = true;
                }
            }
        }
        if($setmeals != 0){
            $parray = explode(',',$setmeals);
        }else{
            $parray = array();
        }
        $data['code'] = 200;
        $data['msg'] = '';
        $data['count'] = $num;
        $data['data'] = $info;
        $data['is_data'] = $parray;
        $this->ajaxReturn($data);
    }

    /**
     * 提交选中套餐返回ids
    **/
    public function add_setmeals(){
        $ids = I('ids','');
        $this->ajaxReturn($ids);
    }

    /**
     * 根据套餐ids得到套餐名称
    **/
    public function get_setmeals_names(){
        $ids = I('ids','');
        $names = getSetmealsNames($ids);
        $this->ajaxReturn($names);
    }

    /**
     * 选择人员页面
    **/
    public function choose_persons(){
        $persons = I('persons','');
        $cid = I('cid','');
        $where['a.corporate_id'] = array('eq',$cid);
        $where['a.status'] = array('neq',5);
        $where['a.del_status'] = array('neq',1);
        $num =  M('employee','t_')
            ->join('as a left join t_personal as b on a.personal_id = b.personal_id')
            ->where($where)
            ->count();
        $this->assign('persons',$persons);
        $this->assign('count',$num);
        $this->assign('cid',$cid);
        $this->display('choice_persons');
    }

    /***
     * 返回所有该公司的人员
    **/
    public function all_persons(){
        $cid = I('cid');
        $persons = I('persons',0);
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $where['a.corporate_id'] = array('eq',$cid);
        $where['a.status'] = array('neq',5);
        $where['a.del_status'] = array('neq',1);
        $info = M('employee','t_')
            ->join('as a left join t_personal as b on a.personal_id = b.personal_id')
            ->where($where)
            ->field('a.employee_id as id,b.name')
            ->page($page,$limit)
            ->select();
        if($persons !== 0){
            $array = explode(',',$persons);
            foreach($info as $key=>$value){
                if(in_array($value['id'],$array)){
                    $info[$key]['LAY_CHECKED'] = true;
                }
            }
        }
        $num =  M('employee','t_')
            ->join('as a left join t_personal as b on a.personal_id = b.personal_id')
            ->where($where)
            ->count();
        if($persons != 0){
            $parray = explode(',',$persons);
        }else{
            $parray = array();
        }
        $data['code'] = 200;
        $data['msg'] = '';
        $data['count'] = $num;
        $data['data'] = $info;
        $data['is_data'] = $parray;
        $this->ajaxReturn($data);
    }

    /**
     * 返回所选eids
    **/
    public function persons(){
        $ids = I('ids');
        $this->ajaxReturn($ids);
    }

    /**
     * 根据eids返回姓名
    **/
    public function names(){
        $eids = I('ids');
        foreach($eids as $key=>$value){
            $eid_array[] = $value;
//            foreach($value as $val){
//                $eid_array[] = $val;
//            }
        }
        $names = getNamesbyEids($eid_array);
        $this->ajaxReturn($names);
    }

    /**
     * 上传活动人员模板
     **/
    public function view(){
        if(!empty($_FILES['file'])){
            $config=array(
                'exts'=>array('xlsx','xls'),
                'rootPath'=>"./Public/",
                'savePath'=>'Uploads/temp/',
                'subName' => array('date','Ymd'),
            );
            $upload = new \Think\Upload($config);
            if (!$info=$upload->uploadOne($_FILES['file'])) {
                $error = $upload->getError();
            }
            if(!empty($error)){
                return Response::show(400,$error);
            }
            $file_name=$upload->rootPath.$info['savepath'].$info['savename'];
            $this->ajaxReturn($file_name);
        }
    }

    /**
     * 添加操作
    **/
    public function add_activity(){
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $company = $_POST['company'];
        $packages = $_POST['packages'];
        $start_time = $_POST['start1'];
        $end_time = $_POST['end'];
        $file_name = $_FILES['file'];//上传的人员
        $pic = $_FILES['pic'];
        $background_pic = $_FILES['background_pic'];
        $get_type = $_POST['get_type'];
        $money = $_POST['money'];
        if(!empty($name) || $name=='0'){

        }else{
            return Response::show(300,'请填写完整后提交');
        }
        if(empty($type)||empty($company)||empty($packages)||empty($pic)||empty($start_time)||empty($end_time)||empty($file_name)||empty($get_type)){
            return Response::show(300,'请填写完整后提交');
        }
        if($get_type!=1){
            if(empty($money)|| $money<1){
                return Response::show(400,'请填写正确的金额');
            }
        }
        $now = date('Y-m-d');
        if($start_time<$now || $end_time<$now){
            return Response::show(400,'不能选择过去时间');
        }
        if($start_time>$end_time){
            return Response::show(400,'开始时间不能大于结束时间');
        }
        $pic_string = uploadfile($pic);
        if(empty($pic_string)){
            return Response::show(400,'图片上传失败');
        }
        $background_pic_string = uploadfile($background_pic);
        if(empty($background_pic_string)){
            return Response::show(400,'背景图片上传失败');
        }
        $start_time = substr($start_time,0,10).' 00:00:00';
        $end_time = substr($end_time,0,10).' 23:59:59';

        //根据账号查询eid
        $model = M('company_activity','dsy_');
        $model->startTrans();
        //处理上传模板中的人
        $config=array(
            'exts'=>array('xlsx','xls'),
            'rootPath'=>"./Public/",
            'savePath'=>'Uploads/temp/',
            'subName' => array('date','Ymd'),
        );
        $upload = new \Think\Upload($config);
        if (!$info=$upload->uploadOne($file_name)) {
            $error = $upload->getError();
        }
        if(!empty($error)){
            return Response::show(400,$error);
        }
        $file_name=$upload->rootPath.$info['savepath'].$info['savename'];
        $person_data = excel_data($file_name);

        if(!empty($person_data)){
            if($person_data[1]['A'] != '添加活动人员' && $person_data[2]['A'] != '姓名' && $person_data[2]['B'] != '手机号码'){
                return Response::show(400,'请使用模板文件');
            }
            unset($person_data[1]);
            unset($person_data[2]);
            $new_data = array();
            foreach($person_data as $key=>$value) {
                if (!empty($value['A']) && !empty($value['B'])) {
                    $array1['name'] = $value['A'];
                    $array1['mobile'] = str_replace(' ', '', $value['B']);
                    $new_data[] = $array1;
                }else{
                    return Response::show(400,'文件内容不完整，请填写完整再提交');
                }
            }
        }

        if(empty($new_data)){
            return Response::show('400','请上传参与活动人员');
        }
        $error = array();
        $person_array = array();
        foreach($new_data as $key=>$value){
            //判断添加的人是否在该公司
            $employeeInfo = getEmployeeByMobile($value['mobile'], $company);
            if(empty($employeeInfo)){
                $error[] = $value['name'];
            }else{
                $person_array[] = $employeeInfo['employee_id'];
            }
        }
        if(empty($person_array)){
            return Response::show('400','请上传正确的参与活动人员');
        }
        if(!empty($error)){
            $names = implode(',',$error);
            $message = $names.'不在所选公司中';
            return Response::show('400',$message);
        }
        if($get_type==3){
            $where['id'] = array('in',$packages);
            $pinfo = M('company_package','dsy_')->where($where)->field('id,pids,name,type')->select();
            $new_array = array();
            $c_model = M('mall_product_specification_config');
            foreach($pinfo as $key=>$value){
                $pid = explode(',',$value['pids']);
                foreach($pid as $kk=>$vv){
                    if($value['type'] == 1 || $value['type'] == 2){
                        $one_product = getpinfobyid($vv);
                    }elseif($value['type'] == 3){
                        $one_product = getsnpinfobyid($vv);
                    }
                    $config_list = $c_model
                        ->where(['pid' => $vv])
                        ->field('`vkey`,`price` as save_price')
                        ->select();
                    $data2 = [];
                    $data2['package_id'] = $value['id'];
                    $data2['package_name'] = $value['name'];
                    $data2['pid'] = $vv;
                    $data2['pname'] = $one_product['pname'];
                    if (empty($config_list)) {
                        $data2['price'] = $one_product['price'];
                        $data2['wz_price'] = $one_product['wz_price'];
                        $data2['cost_price'] = $one_product['cost_price'];
                        $data2['save_price'] = $one_product['price'];
                        $data2['spec'] = 0;
                    } else {
                        $config_default = $c_model
                            ->where(['pid' => $vv])
                            ->field('`price`,`jd_price`,`cost_price`')
                            ->order('is_default desc')
                            ->limit(1)
                            ->find();
                        $data2['price'] = $config_default['price'];
                        $data2['wz_price'] = $config_default['jd_price'];
                        $data2['cost_price'] = $config_default['cost_price'];
                        $data2['save_price'] = $config_default['price'];
                        $data2['spec'] = $config_list;
                    }
                    $new_array[] = $data2;
                }
            }
            $data['packages_info'] = serialize($new_array);
        }
        //给人员去重
        array_unique($person_array);
        $num = count($person_array);
        $persons = implode(',',$person_array);
        $data['persons'] = $persons;
        $data['name'] = $name;
        $data['pic'] = $pic_string;
        $data['background_pic'] = $background_pic_string;
        $data['cid'] = $company;
        $data['allow_times'] = $num;
        $data['start_time'] = strtotime($start_time);
        $data['end_time'] = strtotime($end_time);
        $data['packages'] = $packages;
        $data['type'] = $type;
        $data['time'] = time();
        $data['get_type'] = $get_type;
        if(!empty($money) && $money>=1){
            $data['money'] = $money;
        }
        $data['show_money'] = ($_POST['show_money'] == 0) ? 0 : 1;
        $add = $model->add($data);
        //给套餐加数据
        $data_packages['aid'] = $add;
        $error_check = 1;
        $packages_array = explode(',',$packages);
        foreach($packages_array as $key=>$value){
            $where_package['id'] = array('eq',$value);
            $save_package = M('company_package','dsy_')->where($where_package)->save($data_packages);
            if($save_package==false){
                $error_check = 0;
            }
        }
        //添加操作日志
        $admin_log = '新增活动:' . $name;
        //添加到兑换表
        if($get_type==1){
            $datalist = array();
            foreach($person_array as $value){
                $data1['eid'] = $value;
                $data1['aid'] = $add;
                $data1['time'] = time();
                $data1['type'] = 1;
                $data1['pids'] = 0;
                $datalist[] = $data1;
            }
            $add1 = M('company_exchange','dsy_')->addAll($datalist);
            if($add&&$add1&&($error_check==1)){
                $model->commit();
                unlink($file_name);//添加成功后删除临时文件
                admin_log($admin_log, 1, 'dsy_company_activity:' . $add);
                return Response::show(200,'添加成功');
            }else{
                $model->rollback();
                admin_log($admin_log, 0, 'dsy_company_activity');
                return Response::show(400,'添加失败');
            }
        }else{
            if($add&&($error_check==1)){
                $model->commit();
                unlink($file_name);//添加成功后删除临时文件
                admin_log($admin_log, 1, 'dsy_company_activity:' . $add);
                return Response::show(200,'添加成功');
            }else{
                $model->rollback();
                admin_log($admin_log, 0, 'dsy_company_activity');
                return Response::show(400,'添加失败');
            }
        }

    }

    /**
     * 兑换列表
    **/
    public function exchange_index(){
//        $id = I('id','');
//        if(empty($id)){
//            return Response::show(400,'参数传递错误');
//        }
//        $info = M('company_activity')->find($id);
//        $this->assign('money',$info['get_type']);
        $this->display('exchange_index');
    }

    /**
     * 兑换列表数据
    **/
    public function exchange_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $cname = trim(I('cname',''));
        $aname = trim(I('aname',''));
        if(!empty($cname) || $cname == 0){
            $where['b.corporate_name'] = array('like',"%$cname%");
        }
        if(!empty($aname) || $aname == 0){
            $where['a.name'] = array('like',"%$aname%");
        }
        $info = M('company_activity','dsy_')
            ->where($where)
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->page($page,$limit)
            ->order('id desc')
            ->field('a.id,a.name,a.cid,a.persons,a.start_time,a.end_time,a.packages,a.allow_times,a.type,get_type')
            ->select();
        $num = M('company_activity','dsy_')
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->where($where)
            ->count();
        if(empty($info)){
            return Response::mjson($info,$num);
        }

        foreach($info as $key=>$value){
            $person = explode(',',$value['persons']);
            $info[$key]['persons'] = count($person);
            $info[$key]['start_time'] = date('Y-m-d',$value['start_time']);
            $info[$key]['end_time'] = date('Y-m-d',$value['end_time']);
            $info[$key]['cname'] = getcnamebycid($value['cid']);
            if($value['get_type'] == 2){
                $info[$key]['already_num'] = getalreadyperson($value['id'],2);//已经兑换人数
                $info[$key]['left_num'] = $value['allow_times'] - getalreadyperson($value['id'],2);//剩余
            }elseif($value['get_type'] == 1){

                $info[$key]['already_num'] = getalreadyperson($value['id'],1);//已经兑换人数
                $info[$key]['left_num'] = $value['allow_times'] - getalreadyperson($value['id'],1);//剩余
            }elseif($value['get_type']==3){

                $info[$key]['already_num'] = getalreadyperson($value['id'],2);//已经兑换人数
                $info[$key]['left_num'] = $value['allow_times'] - getalreadyperson($value['id'],2);//剩余
            }
            $now = date('Y-m-d');
            $now = strtotime($now);
            if($now<$value['start_time']){
                $info[$key]['status'] = '未开始';
            }elseif($value['end_time']<$now){
                $info[$key]['status'] = '已结束';
            }else{
                $info[$key]['status'] = '活动中';
            }
            if($value['type'] == 1){
                $info[$key]['type'] = '节日';
            }
            if($value['type'] == 2){
                $info[$key]['type'] = '高温';
            }
            if($value['type'] == 3){
                $info[$key]['type'] = '其他';
            }


        }
        return Response::mjson($info,$num);
    }

    /**
     * 兑换详情
    **/
    public function exchange_detail_index(){
        $id = I('id');
        $this->assign('aid',$id);//活动id
        $this->display('exchange_detail');
    }

    /**
     * 获取表头
    **/
    public function get_columns(){
        $id = I('ids');
        $info = M('company_activity')->find($id);
        $data1['title'] = '姓名';
        $data2['title'] = '手机号';
        $data3['title'] = '兑换时间';
        $data4['title'] = '兑换状态';
        $data5['title'] = '兑换套餐';
        $data1['dataIndex'] = 'name';
        $data2['dataIndex'] = 'mobile';
        $data3['dataIndex'] = 'time';
        $data4['dataIndex'] = 'status';
        $data5['dataIndex'] = 'pids';
        $data1['width'] = '';
        $data2['width'] = '';
        $data3['width'] = '';
        $data4['width'] = '';
        $data5['width'] = '';
        $array[] = $data1;
        $array[] = $data2;
        $array[] = $data3;
        $array[] = $data4;
        $array[] = $data5;
        if($info['money']>=1){
            $data1['title'] = '总兑换金额';
            $data1['dataIndex'] = 'money';
            $array[] = $data1;
            $data1['title'] = '已兑换金额';
            $data1['dataIndex'] = 'change_money';
            $array[] = $data1;
            $data1['title'] = '剩余兑换金额';
            $data1['dataIndex'] = 'left_money';
            $array[] = $data1;
        }
        $this->ajaxReturn($array);
    }

    /**
     * 兑换详情列表数据
    **/
    public function exchange_detail(){
        $aid = I('aid','');
        //查询
        $cinfo = M('company_activity')->find($aid);
        $status = I('status','');
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $model = M('company_exchange');

        $list_qb = array_unique(array_filter(explode(',', $cinfo['persons'])));//全部人员
        if (!empty($status)) {
            $list_yd = (array)$model->where(['status' => 1, 'aid' => $aid])->getField('eid', true);//已领取
            $list_yd = array_unique(array_filter($list_yd));
            if ($status == 1) {
                $data = $list_yd;
            } else {
                if (empty($list_yd))
                    $data = $list_qb;
                else
                    $data = array_values(array_diff($list_qb, $list_yd));
            }
        } else {
            $data = $list_qb;
        }
        if (empty($data)) {
            return Response::mjson([], 0);
        }
        $list = array_slice($data, ($page - 1) * $limit, $limit);

        $info = [];
        $use_money_arr = [];
        foreach ($list as $v) {
            $personal = getAllEmployeeByEid($v);
            $item = [
                'name' => $personal['name'],
                'mobile' => $personal['mobile'],
                'money' => $cinfo['money'],
            ];

            $exchange = $model->where(['eid' => $v, 'status' => 1, 'aid' => $aid])->find();
            $use_money = 0;
            if (empty($exchange)) {
                $item['status'] = '尚未兑换';
                $item['pids'] = '尚未兑换任何套餐';
                $item['time'] = '尚未兑换';
            } else {
                $item['status'] = '已经兑换';
                $item['pids'] = getpackagename($exchange['pids']);
                $item['time'] = date('Y-m-d', $exchange['time']);
                if ($cinfo['get_type'] != 1) {
                    if (isset($use_money_arr[$v])) {
                        $use_money_arr[$v] += $exchange['money'];
                    } else {
                        $use_money_arr[$v] = $exchange['money'];
                    }
                    $use_money = $use_money_arr[$v];
                }
            }
            $item['change_money'] = $use_money;//已兑换金额
            $item['left_money'] = round($cinfo['money'] - $use_money, 2);//未兑换金额
            $info[] = $item;
        }
        return Response::mjson($info, count($data));


        if(!empty($status)){
            if($status==1){
                $where['status'] = array('eq',$status);
            }else{
                $where['status'] = array('eq',0);
            }
        }
        $where['aid'] = array('eq',$aid);
//        $where['type'] = array('eq',$cinfo['get_type']);

        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->order('eid desc')
            ->select();

        $num = $model
            ->where($where)
            ->count();
        if(empty($info)){
            return Response::mjson($info,$num);
        }
        $use_money = 0;
        foreach($info as $key=>$value){
            $name = getUserNmaeByEid($value['eid']);
            $info[$key]['name'] = $name;//姓名
            $info[$key]['mobile'] = getmobilebyeid($value['eid']);//手机号
            if($value['status']==1){
                $info[$key]['status'] = '已经兑换';
                $info[$key]['pids'] = getpackagename($value['pids']);
                $info[$key]['time'] = date('Y-m-d',$value['time']);
            }else{
                $info[$key]['status'] = '尚未兑换';
                $info[$key]['pids'] = '尚未兑换任何套餐';
                $info[$key]['time'] = '尚未兑换';
            }
            if($cinfo['get_type']!=1){
                $info[$key]['money'] = $cinfo['money'];
                if($value['status']==1){
                    if($value['eid'] != $info[($key-1)]['eid']){
                        $use_money = $value['money'];
                    }else{
                        $use_money += $value['money'];
                    }
                }
                $info[$key]['change_money'] = $use_money;//已兑换金额
                $info[$key]['left_money'] = round($cinfo['money']-$use_money, 2);//未兑换金额
            }

        }

        return Response::mjson($info,$num);
    }

    /**
     * 导出兑换记录
    **/
//    public function output(){
//        $aid = I('id','');
//        if(empty($aid)){
//            return Response::show('300','请选择一个公司导出');
//        }
//        $cinfo = M('company_activity')->find($aid);
//
//        $model = M('company_exchange','dsy_');
//        $where['aid'] = array('eq',$aid);
////        $where['type'] = array('eq',$cinfo['get_type']);
//        $all_info = $model
//            ->where($where)
//            ->order('eid desc')
//            ->select();
//        $all_data = array();
//        $use_money = 0;
//        foreach($all_info as $key=>$value){
//            $aid = $value['aid'];
//            $eid = $value['eid'];
//            $pid = $value['pids'];//套餐id
//            $ordernum = $value['ordernum'];
//            $status = $value['status'];
//            $time = $value['time'];
//            $ainfo = activity_info($aid);
//            $aname = $ainfo['name'];//活动名称
//            if($status != 1){
//                $data['jd_status'] = '';
//                $data['time'] = '';
//                $data['status'] = '未兑换';
//            }else{
//                $jd_status = getNotpayNum($ordernum);
//                if($jd_status==1){
//                    $data['jd_status'] = '';
//                }else{
//                    $data['jd_status'] = '成功';
//                }
//                $data['time'] = date('Y-m-d H:i:s',$time);
//                $data['status'] = '已兑换';
//            }
//
//            $uid = getUidByEid($eid);
//            if(!empty($uid)){
//                $name = getUserNmae($uid);
//                $user_info = M('user','t_')->find($uid);
//                $user_name = $user_info['user_name'];
//                $data['aname'] = $aname;
//                $data['name'] = $name;
//                $data['user_name'] = (string)$user_name;
//                if(!empty($pid)){
//                    $pinfo = unserialize($value['setmeal']);
//                    $data['pname'] = $pinfo['name'];
//                    $all_products_name = explode(',',getPnamesByPid($pinfo['pids']));
//                    $all_skuid = explode(',',getAllskuids($pinfo['pids']));
//                    foreach($all_skuid as $kk=>$vv){
//                        $word = $vv.'('.$all_products_name[$kk].')';
//                        $one[] = $word;
//                    }
//                    $data['pinfo'] = implode("\r\n",$one);
//
//                }else{
//                    $data['pname'] = '';
//                    $data['pinfo'] = '';
//                }
//                $data['id'] = $value['id'];
//                if($cinfo['get_type']==2 || $cinfo['get_type']==3){
//                    if($value['eid'] != $all_info[($key-1)]['eid']){
//                        $use_money = $value['money'];
//                        $data['change_money'] = $use_money;//已兑换金额
//                        $data['left_money'] = $cinfo['money']-$use_money;//未兑换金额
//                    }else{
//                        $use_money += $value['money'];
//                        $data['change_money'] += $use_money;//已兑换金额
//                        $data['left_money'] = $cinfo['money']-$use_money;//未兑换金额
//                    }
//                    $data['money'] = $cinfo['money'];
//                }
//                $all_data[] = $data;
//            }
//        }
//        if($cinfo['get_type']==2){
//            $xlsCell = array(
//                array('aname', '活动名称'),
//                array('time', '兑换时间'),
//                array('user_name', '登陆号'),
//                array('name', '姓名'),
//                array('pname', '套餐名称'),
//                array('pinfo', '商品信息'),
//                array('status', '兑换状态'),
//                array('jd_status', '京东状态'),
//                array('money', '总兑换金额'),
//                array('change_money', '兑换金额'),
//                array('left_money', '剩余兑换金额'),
//            );
//        }else{
//            $xlsCell = array(
//                array('aname', '活动名称'),
//                array('time', '兑换时间'),
//                array('user_name', '登陆号'),
//                array('name', '姓名'),
//                array('pname', '套餐名称'),
//                array('pinfo', '商品信息'),
//                array('status', '兑换状态'),
//                array('jd_status', '京东状态'),
//            );
//        }
//
//        $xlsName = '兑换结果导出';
//        $field = null;
//        foreach ($xlsCell as $key => $value) {
//            if($key == 0){
//                $field = $value[0];
//            }else{
//                $field .= "," . $value[0];
//            }
//        }
//        $one = exportExcel($xlsName,$xlsCell,$all_data);
//    }

    public function output(){
        $aid = I('id','');
        if(empty($aid)){
            return Response::show('300','请选择一个公司导出');
        }
        $cinfo = M('company_activity')->find($aid);
        $model = M('company_exchange','dsy_');
        $where['aid'] = array('eq',$aid);

        $all_info = $model
            ->where($where)
            ->order('eid desc')
            ->select();

        $all_data = array();
        $use_money = 0;
        foreach($all_info as $key=>$value){
            $aid = $value['aid'];
            $eid = $value['eid'];
            $pid = $value['pids'];//套餐id
            $ordernum = $value['ordernum'];
            $status = $value['status'];
            $time = $value['time'];
            $ainfo = activity_info($aid);
            $aname = $ainfo['name'];//活动名称
            if($status != 1){
                $data['jd_status'] = '';
                $data['time'] = '';
                $data['status'] = '未兑换';
            }else{
                $jd_status = getNotpayNum($ordernum);
                if($jd_status==1){
                    $data['jd_status'] = '';
                }else{
                    $data['jd_status'] = '成功';
                }
                $data['time'] = date('Y-m-d H:i:s',$time);
                $data['status'] = '已兑换';
            }

            $uid = getUidByEid($eid);
            if(!empty($uid)){
                $name = getUserNmae($uid);
                $user_info = M('user','t_')->find($uid);
                $user_name = $user_info['user_name'];
                $data['aname'] = $aname;
                $data['name'] = $name;
                $data['user_name'] = (string)$user_name;
                if($cinfo['get_type']==1 || $cinfo['get_type']==2){
//                    if(!empty($pid)){
//                        $pinfo = unserialize($value['setmeal']);
//                        $data['pname'] = $pinfo['name'];
//                        $all_products_name = explode(',',getPnamesByPid($pinfo['pids']));
//                        $all_skuid = explode(',',getAllskuids($pinfo['pids']));
//                        foreach($all_skuid as $kk=>$vv){
//                            $word = $vv.'('.$all_products_name[$kk].')';
//                            $one[] = $word;
//                        }
//                        $data['pinfo'] = implode("\r\n",$one);
//
//                    }else{
//                        $data['pname'] = '';
//                        $data['pinfo'] = '';
//                    }
                }else{
                    $goods = $value['goods_ids'];
                    $all_products_name = explode(',',getPnamesByPid($goods));
                    $all_skuid = explode(',',getAllskuids($goods));
                    foreach($all_skuid as $kk=>$vv){
                        $word = $vv.'('.$all_products_name[$kk].')';
                        $one[] = $word;
                    }
                    $data['pinfo'] = implode("\r\n",$one);

                }

                $data['id'] = $value['id'];
                if($cinfo['get_type']==2 || $cinfo['get_type']==3){
                    if($value['eid'] != $all_info[($key-1)]['eid']){
                        $use_money = $value['money'];
                        $data['change_money'] = $use_money;//已兑换金额
                        $data['left_money'] = round($cinfo['money']-$use_money, 2);//未兑换金额
                    }else{
                        $use_money += $value['money'];
                        $data['change_money'] = $use_money;//已兑换金额
                        $data['left_money'] = round($cinfo['money']-$use_money, 2);//未兑换金额
                    }
                    $data['money'] = $cinfo['money'];

                }
                $all_data[] = $data;
            }
        }

        if($cinfo['get_type']==2 ){

            $xlsCell = array(
                array('aname', '活动名称'),
                array('time', '兑换时间'),
                array('user_name', '登陆号'),
                array('name', '姓名'),
//                array('pname', '套餐名称'),
//                array('pinfo', '商品信息'),
                array('status', '兑换状态'),
                array('jd_status', '京东状态'),
                array('money', '总兑换金额'),
                array('change_money', '兑换金额'),
                array('left_money', '剩余兑换金额'),
            );
        }elseif($cinfo['get_type'] ==1){

            $xlsCell = array(
                array('aname', '活动名称'),
                array('time', '兑换时间'),
                array('user_name', '登陆号'),
                array('name', '姓名'),
                array('pname', '套餐名称'),
                array('pinfo', '商品信息'),
                array('status', '兑换状态'),
                array('jd_status', '京东状态'),
            );
        }elseif($cinfo['get_type'] ==3){

            $xlsCell = array(
                array('aname', '活动名称'),
                array('time', '兑换时间'),
                array('user_name', '登陆号'),
                array('name', '姓名'),
                array('pinfo', '商品信息'),
                array('status', '兑换状态'),
                array('jd_status', '京东状态'),
                array('money', '总兑换金额'),
                array('change_money', '兑换金额'),
                array('left_money', '剩余兑换金额'),
            );
        }

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
     * 售后主页
    **/
    public function retrun_index(){
        $this->display('return_index');
    }

    /**
     * 售后列表数据
    **/
    public function return_list(){
        $return_num = I('ordernum','');//售后单号
        $time = I('start1','');//申请时间
        $status = I('status','');//申请时间
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $model = M('mall_order_return','dsy_');
        if(!empty($return_num)){
            $where['csnum'] = array('eq',$return_num);
        }
        if(!empty($time)){
            $where['time'] = array('like',"%$time%");
        }
        if(!empty($status)){
            $where['status'] = array('eq',$status);
        }
        $where['order_type'] = array('eq',2);
        $where['type'] = array('eq',1);
        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->order('id desc')
            ->field('id,wz_orderid,csnum,ordernum,pid,skuid,status')
            ->select();
        $num =$model
            ->where($where)
            ->count();
        if(empty($info)){
            return Response::mjson($info,$num);
        }
        //获取token
        $token = selAccess_token();
        foreach($info as $key=>$value){
            $wz_id = $value['wz_orderid'];
            $skuid = $value['skuid'];
            $return_info = product_status($token,$wz_id,$skuid);//服务代码  退货(10)、换货(20)、维修(30)
            $info[$key]['service_code'] = $return_info['service_code'];//服务代码  退货(10)、换货(20)、维修(30)
            $info[$key]['service_step_name'] = $return_info['service_step_name'];
            $info[$key]['service_num'] = $return_info['service_num'];//服务单号
            $detail = getDetalinfo($value['ordernum'], $value['pid']);
            $info[$key]['pname'] = $detail['pname'];
            $result = getOrderInfo($token,$wz_id,$skuid);
            $info[$key]['state'] = $result['state'];
        }
//        foreach($info as $key=>$value){
//            $skuid = $value['skuid'];
//            $wz_id = $value['wz_orderid'];
//            $result = getOrderInfo($token,$wz_id,$skuid);
//            $status = $result['state'];
//            $info[$key]['state'] = $status;
//        }
        return Response::mjson($info,$num);
    }

    /**
     * 同意退款操作
    **/
    public function allow_return(){
        $id = I('ids', '');
        $id = $id[0];
        //售后同意
        $model = new \Common\Model\OrderReturnModel();
        $res = $model->returnOrder($id);
        if ($res['code'] == 200) {
            return Response::show(200, '操作成功');
        } else {
            return Response::show($res['code'], $res['msg']);
        }
        $info = M('mall_order_return','dsy_')->find($id);
        $uid = $info['uid'];
        $pid = $info['pid'];
        $ordernum = $info['ordernum'];
        $where_paytype['ordernum'] = array('eq',$ordernum);
        $mall_order = M('mall_order')->where($where_paytype)->find();
        $where1['ordernum'] = array('eq',$ordernum);
        $where1['pid'] = array('eq',$pid);
        $pinfo = M('mall_order_specifications','dsy_')->where($where1)->order('id desc')->find();
//        $pinfo = getpinfobyid($pid);
        $price = $pinfo['price'];//商品金额
        $order_price = $mall_order['price'];//订单金额
        $paytype = $mall_order['paytype'];//1支付宝 2微信

        if($price>$order_price){
            return Response::show('401','退款金额错误');
        }

        //添加操作日志
        $admin_log = '订单【' . $ordernum . '】售后退款同意';
        if($paytype == 1){
            //支付宝退款
            $return_money = $this->order_refund_alipay($id);
            if($return_money == 10000){
                admin_log($admin_log, 1, 'dsy_mall_order_return:' . $id);
                return Response::show('200','操作成功');
            }else{
                admin_log($admin_log, 0, 'dsy_mall_order_return:' . $id);
                return Response::show('401','操作失败');
            }
        }elseif ($paytype == 2){
            //微信退款
            $return_money = $this->order_refund_wechat($id);
            if($return_money == 200){
                admin_log($admin_log, 1, 'dsy_mall_order_return:' . $id);
                return Response::show('200','操作成功');
            }else{
                admin_log($admin_log, 0, 'dsy_mall_order_return:' . $id);
                return Response::show('402',$return_money);
            }
        }

    }

    /**
     * 支付宝退款
     * @param $ids 退款订单ID
     *
     */
    private function order_refund_alipay($ids){
        $where['id'] = array('eq',$ids);
        $where['type'] = array('in','1,3');
        $return = M('mall_order_return');
        $result = $return->where($where)->field('csnum,ordernum,spid,type,skuid,pid,num')->select();

        $csnum = $result[0]['csnum'];//退款单号
        $ordernum = $result[0]['ordernum'];//已支付订单号
        $spid = $result[0]['spid'];//规格id
        $skuid = $result[0]['skuid'];//区别唯一
        $where_order['ordernum'] = array('eq',$ordernum);
        $result_order = M('mall_order')->where($where_order)->field('order_notpay_num,price,freight,pid,trade_no')->select();
        $order_notpay_num= $result_order[0]['order_notpay_num'];//未付款订单号
        $order_pids = $result_order[0]['pid'];//订单商品集
        $trade_no = $result_order[0]['trade_no'];//交易号

        $where_order_specifications['id'] = array('eq',$spid);
        $pprice = M('mall_order_specifications')->where($where_order_specifications)->getField('price');//商品金额
        if($pprice==null){
            $pprice = M('mall_order_specifications')->where(['pid'=>$result[0]['pid'],'ordernum'=>$result[0]['ordernum']])->getField('price');//商品金额
            $pprice = $pprice * $result[0]['num'];
        }
        $where_ninfo['ordernum'] = array('eq',$order_notpay_num);
        $ninfo = M('mall_order_notpay','dsy_')->where($where_ninfo)->find();
        $uid = $ninfo['uid'];

        $return->startTrans();
        if($ninfo['quota_type']==1){
            $bee = $ninfo['quota_left'];//可使用退款的剩余豆子
            $left = $bee - $pprice;
            $nid = $ninfo['id'];
            if($left<0){
                //剩余福利豆不够扣
                $pprice = $pprice-$bee;
                $data_save['quota_left'] = 0 ;
                $where_save['id'] = array('eq',$nid);
                $save_one = M('mall_order_notpay','dsy_')->where($where_save)->save($data_save);
                $add_model = new \Common\Model\MallWquotaDetailModel();
                $add_return = $add_model->addReturnRecord($uid,$bee,$order_notpay_num, '', 0, 0, $ninfo['use_quota']);
//                $data_add['type'] = 2;
//                $data_add['uid'] = $uid;
//                $data_add['eid'] = $eid;
//                $data_add['num'] = $bee;
//                $data_add['cid'] = $cid;
//                $data_add['time'] = NOW;
//                $add_return = M('mall_wquota_detail','dsy_')->add($data_add);
            }else{
                $result_status_check = 1;
                $pids = explode(',',$order_pids);
                $count = count($pids);//购买商品的id数量
                if($count > 1){
                    $where_is_return_all = array(
                        'ordernum' => array('eq',$ordernum),
                        'type' => array('in','1,3'),
                    );
                    $result_return_num = M('mall_order_return')->where($where_is_return_all)->count('id');
                    if($count == $result_return_num){
                        $refund_fee = (string)($pprice );//退款金额
                        //更改已支付订单状态为全部退款
                        $data_status['status'] = 6;
                        $where_order['ordernum'] = array('eq',$ordernum);
                        $result_status = M('mall_order')->where($where_order)->save($data_status);
                        if($result_status !== false){
                            $result_status_check = 1;
                        }else{
                            $result_status_check = 2;
                            $return->rollback();
                            die();
                        }
                    }else{
                        $refund_fee = (string)$pprice;//退款金额
                    }
                }else{
                    $refund_fee = (string)($pprice);//退款金额25.8
                    //更改已支付订单状态为全部退款
                    $data_status['status'] = 6;
                    $where_order['ordernum'] = array('eq',$ordernum);
                    $result_status = M('mall_order')->where($where_order)->save($data_status);
                    if($result_status !== false){
                        $result_status_check = 1;
                    }else{
                        $result_status_check = 2;
                        $return->rollback();
                        die();
                    }
                }
                $data_save['quota_left'] = $left ;
                $where_save['id'] = array('eq',$nid);
                $save_one = M('mall_order_notpay','dsy_')->where($where_save)->save($data_save);//修改可用退款豆子数额
                $add_model = new \Common\Model\MallWquotaDetailModel();
                $add_return = $add_model->addReturnRecord($uid,$pprice,$order_notpay_num, '', 0, 0, $ninfo['use_quota']);
//                $data_add['type'] = 2;
//                $data_add['uid'] = $uid;
//                $data_add['eid'] = $eid;
//                $data_add['num'] = $pprice;
//                $data_add['cid'] = $cid;
//                $data_add['time'] = NOW;
//                $add_return = M('mall_wquota_detail','dsy_')->add($data_add);
                $data_return_money['return_money'] = 0;
                $data_return_money['status'] = 2;
                $where_is_return_money['csnum'] = $csnum;
                $result_return_money = M('mall_order_return','dsy_')->where($where_is_return_money)->save($data_return_money);
                if($add_return !== false && $save_one !== false && $result_return_money !==false){
                    $return->commit();
                    return 10000;
                }else{
                    $return->rollback();
                    return 401;
                }
            }
        }

        //判断商品是否全部退款
        $result_status_check = 1;
        $pids = explode(',',$order_pids);
        $count = count($pids);//购买商品的id数量
        if($count > 1){
            $where_is_return_all = array(
                'ordernum' => array('eq',$ordernum),
                'type' => array('in','1,3'),
            );
            $result_return_num = M('mall_order_return')->where($where_is_return_all)->count('id');
            if($count == $result_return_num){
                $refund_fee = (string)($pprice );//退款金额
                //更改已支付订单状态为全部退款
                $data_status['status'] = 6;
                $where_order['ordernum'] = array('eq',$ordernum);
                $result_status = M('mall_order')->where($where_order)->save($data_status);
                if($result_status !== false){
                    $result_status_check = 1;
                }else{
                    $result_status_check = 2;
                    $return->rollback();
                    die();
                }
            }else{
                $refund_fee = (string)$pprice;//退款金额
            }
        }else{
            $refund_fee = (string)($pprice);//退款金额25.8
            //更改已支付订单状态为全部退款
            $data_status['status'] = 6;
            $where_order['ordernum'] = array('eq',$ordernum);
            $result_status = M('mall_order')->where($where_order)->save($data_status);
            if($result_status !== false){
                $result_status_check = 1;
            }else{
                $result_status_check = 2;
                $return->rollback();
                die();
            }
        }

        //支付宝必要参数   $trade_no(交易号)|$refund_fee(退款金额)
        import('Alipay.aop.AopClient');
        $aop = new \AopClient;
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = '2018020702158270';
        $aop->rsaPrivateKey = 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCpp2pkqfhQrl/mkoOo9NDLOeXJDmKQ+knIeJXkWb49fpNyfURuyfjwtijMtNcjiW3S7wgsrGRQNnZozAtNW2WozR4BhYf0iu2r8burGl0VQNd4E1jdeY8KKyN+N0k+pq3PgjjDwQDIMmcWcQWM+C2sJZRHTEAUp6+Knv58KaZsk/6w0lvhfo4QKDYcjOHAsjU0sqakDqZtIUhb/lux1wQM9UNEcaIraJ9osfux82DQESt+ewetNxxrnLgrRdZ/WYP+4diOs10jg92GKE+nslN2NHd0t8sC66NzatR2vY0wzorQbGEg+J2Gw0XB9+Q1ABmfvUzrH8q+WVQjZgKFelztAgMBAAECggEAdmKg14XUBVjpCtiKj0fmuym3s0kadthwzDd5QVNucTL2aRoIutatpCs45T/8MIwh2uN57NKPXXnjvtVDvcNdeSFJIv6vFHItz6JrtsY61i3dLakyhbnhmtLnfZfOwK1G9FqGifMQPKMPVJWhrfEwzcObaPVIciFhWXYBV1spCvJH9nuEDmFXI6qdMpH6+G3QtDJX3YSzGWnyKPXGD0YSBXY+5mC66ywuaevdZ9rgJeB86u9BqV7uxYhqUmiMdJBxhu6d2inHJcyIEbVy0qcwe4a+5yMIisMYNaQdpc+aLNnB2EMZ4IgXYxdGv9w13fZVfhQmYaIQOmftB7gHe5SGgQKBgQDW6MeN3ePy7RtXx+QLdgrWJUq1o47oG10dhtQHDJQgXb7bpDHRlMa7UhSaySzcECPGt3XKPZoFqRxb975qlUN9/j1NEeuukpwa9kATdABWP+bd0wuK2HN9h/DkxALCdu6g9assKEtYKQ4HbW9Ewr2C7Tzwzi8tYrmbDMZnPcZXaQKBgQDKF4MN6IZmiLgt/g3ymmhEKC0Yv1CPu85Ep+9ssB7PSd4uUrXPUAme/9R8xAHgwF8gPaYS0mDguhY54cja+BkUUGZcGl9JRSKG2rVFAEvBUpC3OOpX9TPQyK2qDOR8EBTmDx9q6gWa7YjxcXgWn4jEIIAzVBZVaXX9HX3eB+NM5QKBgQDJrlSvxz+Hl4pke5uAQfvzcbXF9kNDgYKGFiTepKhSI4fcWh/CnktOOb5KcGcf8imQ8FSjQeJMU6LgkAPrxD49fB2NOTcjckT7bIM/fkpepsODAu6/E1h2wt+H4IbydmiFN2e3He1vQ7/9qm6UaekteHQLTIOrpQ2n+3oqIygCmQKBgBf7PEkNO821Ea4bhMoyOodEAT37jfLQhYSuLQJH0BAnIt96XyrPw6SDlVKM6/Agw+kOh8OaBXcFfhe2TGB3qno3pD3vvzjxpEw+bd5XT6YMRzIG5gA0D8cJ2Vnhl8eFHQXD66WDSdD9uQ91uJtuqQslFDGDG/dcwWyc8E/FvdplAoGAfl+c2yYiv5DZ6W5ccc5nnCf2pZzSF1lJPBlvGQEKeHc1kTWpcvC8r9nYqehRs5J4ZS0d4hvEoOMqni89H//uKsziicV5wh80hH/OSMGtYPQ2fTTzsgUgG0FRwMJukAxqhrSIo9wOlK5fjxKARk5AybJeJa5XExvD8V4SNFWHCOg=';
        $aop->alipayrsaPublicKey='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAgJltygvmb9ZqqSXZ7WJjnezDR9qzI8xbjhbWwTPboJd0Z9OzjSazob7XrcUAcnH/ieRukMrhej4oeFXrv2xoVSdf3uFvsjHGJiAnO9opDY+FwlEI8ns0tSCYR+jtq0rOCv8KGiVhXETCMX1suRmtbeUv5m4PsT6T/d8ea7MGu1LrpSWCvy9xbXYeOV7V/KCQ0QZzY68FlwrOCdTOUstwXkaRng7gDOeX99ZSysRROJRzNVqSaqah/Q6tkU45x0gSCl6ARaQnrHlrasn//3mPrBt1naLnXP3/RzsKl1HN7JebyaAp129Gl/nPK5bhRH0qeKKaLiRk5P64yAUc0/YP4QIDAQAB';
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='UTF-8';
        $aop->format='json';
        import('Alipay.aop.request.AlipayTradeRefundRequest');
        $request = new \AlipayTradeRefundRequest;
        $request->setBizContent("{" .
            "    \"trade_no\":\"".$trade_no."\"," .
            "    \"out_request_no\":\"".$skuid."\"," .
            "    \"refund_amount\":\"".$refund_fee."\"" .
            "  }");

        $result = $aop->execute ( $request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if(!empty($resultCode)&&$resultCode == 10000){
            //退款成功后，修改售后订单的退款金额
            $data_return_money['return_money'] = $refund_fee;
            $data_return_money['status'] = 2;
            $where_is_return_money['csnum'] = $csnum;
            $result_return_money = M('mall_order_return')->where($where_is_return_money)->save($data_return_money);
            if($result_return_money !== false && $result_status_check==1 && $save_one !==false && $add_return !==false){
                $return->commit();
                return 10000;
            }else{
                $return->rollback();
                return '401';
            }
        } else {
            $return->rollback();
            return $resultCode;
        }

    }

    /**
     * 微信退款
     * param 售后no
     * ps:退款：如果只剩一件商品 ,退运费,否则只退当前商品金额；  退货：只退当前商品金额
     */
    private function order_refund_wechat($ids){

        $where['id'] = array('eq',$ids);
        $where['type'] = array('in','1,3');
        $return = M('mall_order_return');
        $return->startTrans();
        $result = $return->where($where)->field('csnum,ordernum,spid,type,skuid')->select();
        $type = $result[0]['type']; //1退货 3退款
        $csnum = $result[0]['csnum'];//退款单号
        $ordernum = $result[0]['ordernum'];//已支付订单号
        $spid = $result[0]['spid'];//规格id
        $skuid = $result[0]['skuid'];//区分
        $where_order['ordernum'] = array('eq',$ordernum);
        $result_order = M('mall_order')->where($where_order)->field('id,order_notpay_num,price,freight,pid,payprice')->select();
        $order_notpay_num = $result_order[0]['order_notpay_num'];//未支付订单编号
        $order_pids = $result_order[0]['pid'];//订单商品集

        $where_order_specifications['id'] = array('eq',$spid);
        $pprice = M('mall_order_specifications')->where($where_order_specifications)->getField('price');//商品金额

        $where_ninfo['ordernum'] = array('eq',$order_notpay_num);
        $ninfo = M('mall_order_notpay','dsy_')->where($where_ninfo)->find();
        $price = $ninfo['receipt_amount'] * 100; //订单金额
        $uid = $ninfo['uid'];
        if($ninfo['quota_type']==1){

            $bee = $ninfo['quota_left'];//可使用退款的剩余豆子
            $left = $bee - $pprice;
            $nid = $ninfo['id'];
            if($left<0){
                $pprice = $pprice-$bee;
                $data_save['quota_left'] = 0 ;
                $where_save['id'] = array('eq',$nid);
                $save_one = M('mall_order_notpay','dsy_')->where($where_save)->save($data_save);
                $add_model = new \Common\Model\MallWquotaDetailModel();
                $add_return = $add_model->addReturnRecord($uid,$bee,$order_notpay_num, '', 0, 0, $ninfo['use_quota']);
//                $data_add['type'] = 2;
//                $data_add['uid'] = $uid;
//                $data_add['eid'] = $eid;
//                $data_add['num'] = $bee;
//                $data_add['cid'] = $cid;
//                $data_add['time'] = NOW;
//                $add_return = M('mall_wquota_detail','dsy_')->add($data_add);
            }else{
                $data_save['quota_left'] = $left ;
                $where_save['id'] = array('eq',$nid);
                $save_one = M('mall_order_notpay','dsy_')->where($where_save)->save($data_save);
                $add_model = new \Common\Model\MallWquotaDetailModel();
                $add_return = $add_model->addReturnRecord($uid,$pprice,$order_notpay_num, '', 0, 0, $ninfo['use_quota']);
//                $data_add['type'] = 2;
//                $data_add['uid'] = $uid;
//                $data_add['eid'] = $eid;
//                $data_add['num'] = $pprice;
//                $data_add['cid'] = $cid;
//                $data_add['time'] = NOW;
//                $add_return = M('mall_wquota_detail','dsy_')->add($data_add);
                $data_return_money['return_money'] = $pprice;
                $data_return_money['status'] = 2;
                $where_is_return_money['csnum'] = $csnum;
                $result_return_money = M('mall_order_return','dsy_')->where($where_is_return_money)->save($data_return_money);
                if($add_return !== false && $save_one !== false && $result_return_money !==false){
                    $return->commit();
                    return 200;
                }else{
                    $return->rollback();
                    return 401;
                }
            }
        }

        //判断商品是否全部退款
        $result_status_check = 1;
        $pids = explode(',',$order_pids);
        $count = count($pids);//购买商品的id数量
        if($count > 1){
            $where_is_return_all = array(
                'ordernum' => array('eq',$ordernum),
                'type' => array('in','1,3'),
            );
            $result_return_num = M('mall_order_return')->where($where_is_return_all)->count('id');
            if($count == $result_return_num){
                $refund_fee = ($pprice)*100;//退款金额
                //更改已支付订单状态为全部退款
                $data_status['status'] = 6;
                $where_order['ordernum'] = array('eq',$ordernum);
                $result_status = M('mall_order')->where($where_order)->save($data_status);
                if($result_status !== false){
                    $result_status_check = 1;
                }else{
                    $result_status_check = 2;
                    $return->rollback();
                    die();
                }
            }else{
                $refund_fee = $pprice*100;//退款金额
            }
        }else{
            $refund_fee = ($pprice)*100;//退款金额
            //更改已支付订单状态为全部退款
            $data_status['status'] = 6;
            $where_order['ordernum'] = array('eq',$ordernum);
            $result_status = M('mall_order')->where($where_order)->save($data_status);
            if($result_status !== false){
                $result_status_check = 1;
            }else{
                $result_status_check = 2;
                $return->rollback();
                die();
            }
        }


        $wxpay_config = C('wxpay_config');
        $wxappid = $wxpay_config['wxappid'];//应用ID 字符串
        $mch_id = $wxpay_config['mch_id'];//商户号 字符串
        $notify_url = $wxpay_config['notify_url'];//接收微信支付异步通知回调地址 字符串
        $wxkey = $wxpay_config['wxkey'];//这个是在商户中心设置的那个值用来生成签名时保证安全的 字符串
        $wechatAppPay = new  WechatAppPay($wxappid, $mch_id, $notify_url, $wxkey);
        $str = $wechatAppPay ->genRandomString();
        $ref= array(
            'appid'=>$wxappid,
            'mch_id'=>$mch_id,//商户号
            'nonce_str'=>$str,//随机字符串
            'op_user_id'=>$mch_id,
            'out_refund_no'=>$csnum,//退款单号
            'out_trade_no'=>$order_notpay_num, //订单编号
            'refund_fee'=>$refund_fee,//退款金额 todo 测试
            'total_fee'=>$price,//订单金额 todo 测试
        );
        $sign = $wechatAppPay->MakeSign($ref);
        $refund=array(
            'appid'=>$wxappid,
            'mch_id'=>$mch_id,//商户号
            'nonce_str'=>$str,//随机字符串
            'op_user_id'=>$mch_id,
            'out_refund_no'=>$csnum,//退款单号
            'out_trade_no'=>$order_notpay_num, //订单编号
            'refund_fee'=>$refund_fee,//退款金额  todo 测试
            'total_fee'=>$price,//订单金额  todo 测试
            'sign'=>$sign
        );

        $url="https://api.mch.weixin.qq.com/secapi/pay/refund";//微信退款地址，post请求
        $xml=$wechatAppPay->data_to_xml($refund);

        $ch=curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_HEADER,0);//隐藏请求头信息
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,1);//证书检查
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'pem');
        curl_setopt($ch,CURLOPT_SSLCERT,dirname(__FILE__).'/cert/wechat/apiclient_cert.pem');
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'pem');
        curl_setopt($ch,CURLOPT_SSLKEY,dirname(__FILE__).'/cert/wechat/apiclient_key.pem');
        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'pem');
        curl_setopt($ch,CURLOPT_CAINFO,dirname(__FILE__).'/cert/wechat/rootca.pem');
        curl_setopt($ch,CURLOPT_POST,1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$xml);
        $data=curl_exec($ch);

        if($data){ //返回来的是xml格式需要转换成数组再提取值，用来做更新
            curl_close($ch);
            $result_data = $wechatAppPay->xml_to_data($data);
            //退款成功后，修改售后订单的退款金额
            if($result_data['result_code'] == 'FAIL'){
                return $result_data['err_code_des'];
            }else{
                $data_return_money['return_money'] = $refund_fee/100;
                $data_return_money['status'] = 2;
                $where_is_return_money['csnum'] = $csnum;
                $result_return_money = M('mall_order_return')->where($where_is_return_money)->save($data_return_money);
                if($result_return_money !== false && $result_status_check==1 &&$save_one!==false && $add_return !==false){
                    $return->commit();
                    return 200;
                }else{
                    $return->rollback();
                    return 400;
                }
            }
        }else{
            $return->rollback();
            $error=curl_errno($ch);
            curl_close($ch);
            echo  $error;
        }

    }

    /**
     * 查询库存页面
    **/
    public function kucun(){
        $id = I('id');
        $info = getFirst($token);
        foreach($info as $key=>$value){
            $one['id'] = $value['areaId'];
            $one['name'] = $value['areaName'];
            $data[] = $one;
        }
        $data1['id'] = '0';
        $data1['name'] = '省';
        array_unshift($data,$data1);
        $this->assign('first',$data);
        $this->assign('id',$id);
        $this->display('kucun');

    }

    /**
     * 查询库存
    **/
    public function search_num(){
        $pid = I('pid');
        $first = I('first');
        $second = I('second');
        $third = I('third');
        $four = I('four');
        $area = [
            'province' => $first,
            'city' => $second,
            'county' => $third,
            'town' => $four,
        ];
        $skuid = getSkuid($pid);
        $array = [['skuId'=>$skuid,'skuNumber'=>1]];
        $info = getLeft($token,$array,$area);
//        $left_num = $info['stockStateDesc'];
        $left_num = $info['stockStateType'] == 33 ? '有货' : '无货';
        $this->ajaxReturn($left_num);
    }

    /**
     * 二级地址
    **/
    public function area_two(){
        $id = I('id');
        $info = getSecond($token,$id);
        foreach($info as $key=>$value){
            $one['id'] = $value['areaId'];
            $one['name'] = $value['areaName'];
            $data[] = $one;
        }
        $data1['id'] = '0';
        $data1['name'] = '市';
        array_unshift($data,$data1);
        if(!empty($data)){
            $array = array();
            foreach($data as $value){
                $a = '<option value="'.$value['id'].'">'.$value['name'].'</option>';
                $array[] = $a;
            }
            $string = implode('',$array);
        }
        $this->ajaxReturn($string);
    }

    /**
     *三级地址
     **/
    public function area_three(){
        $id = I('id');
        $info = getThird($token,$id);
        foreach($info as $key=>$value){
            $one['id'] = $value['areaId'];
            $one['name'] = $value['areaName'];
            $data[] = $one;
        }
        $data1['id'] = '0';
        $data1['name'] = '区';
        array_unshift($data,$data1);
        if(!empty($data)){
            $array = array();
            foreach($data as $value){
                $a = '<option value="'.$value['id'].'">'.$value['name'].'</option>';
                $array[] = $a;
            }
            $string = implode('',$array);
        }
        $this->ajaxReturn($string);
    }

    /**
     *四级地址
     **/
    public function area_four(){
        $id = I('id');
        $info = getFour($token,$id);
        foreach($info as $key=>$value){
            $one['id'] = $value['areaId'];
            $one['name'] = $value['areaName'];
            $data[] = $one;
        }
        $data1['id'] = '0';
        $data1['name'] = '镇';
        array_unshift($data,$data1);
        if(!empty($data)){
            $array = array();
            foreach($data as $value){
                $a = '<option value="'.$value['id'].'">'.$value['name'].'</option>';
                $array[] = $a;
            }
            $string = implode('',$array);
        }
        $this->ajaxReturn($string);
    }

     /**
     * 特价商品主页
    **/
    public function activity_product(){


        $this->display('activity_product_index');
    }

    /**
     *列表页数据
    **/
    public function activity_products_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $aname = trim(I('aname',''));
        if(!empty($aname) || $aname=='0'){
            $where['name'] = array('like',"%$aname%");
        }
        $where['is_del'] = array('eq',1);
        $where['type'] = array('eq',1);
        $info = M('mall_activity','dsy_')
            ->where($where)
            ->page($page,$limit)
            ->order('id desc')
            ->select();
        $count = M('mall_activity','dsy_')
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                if(!empty($value['face_img'])){
                    $img = format_img($value['face_img'], IMG_VIEW);
                    $info[$key]['face_img'] =  '<a href="'.$img.'" target="view_window">点击查看</a>';
                }
                if(!empty($value['act_img'])){
                    $aimg = format_img($value['act_img'], IMG_VIEW);
                    $info[$key]['act_img'] = '<a href="'.$aimg.'" target="view_window">点击查看</a>';
                }
                $pinfo = getpinfobyid($value['pid']);
                $info[$key]['pid'] = $pinfo['pname'];
            }
        }
        return Response::mjson($info,$count);
    }

    /**
     * 删除活动
    **/
    public function del_product_activity(){
        $id = I('ids');
        $where['id'] = array('eq',$id[0]);
        $data['is_del'] = 0;
        $del = M('mall_activity','dsy_')->where($where)->save($data);
        if($del != false){
            return Response::show('200','删除成功');
        }else{
            return Response::show('400','删除失败');
        }
    }

    /**
     * 添加活动商品页面
    **/
    public function activity_product_add(){

        $this->display('activity_product_add');
    }

    /**
     * 添加活动商品操作
    **/
    public function add_activity_product(){
        $name = trim(I('name',''));
        $product = I('products','');
        $price = trim(I('price',''));
        $face_pic = $_FILES['face_pic'];
        $pic = $_FILES['pic'];
        $start = I('start1','');
        $hours = trim(I('hours',''));
        if(!empty($name)|| $name=='0'){

        }else{
            return Response::show('300','请填写完整后提交');
        }
        if(empty($product)||empty($price)||empty($face_pic)||empty($start)||empty($pic)){
            return Response::show('300','请填写完整后提交');
        }
        if(is_numeric($price)==false || is_numeric($hours)==false){
            return Response::show('301','请输入正确的数字');
        }
        if($price<0 || $hours<0){
            return Response::show('301','不能小于0');
        }

        //判断商品是否可售,是否上架
        $is_sell['status'] = array('eq',1);
        $is_sell['upanddown'] = array('eq',1);
        $is_sell['id'] = array('eq',$product);
        $check_sell = M('mall_product','dsy_')->where($is_sell)->find();
        if(empty($check_sell)){
            return Response::show('400','添加商品不可售');
        }

        $start = substr($start,0,16).':00';
        $time_stamp = strtotime($start);
        $seconds = $hours*3600;
        $end = date('Y-m-d H:i:s',($time_stamp+$seconds));
        //判断该商品有没有在其他活动中
        $model = M('mall_activity');
        $where['pid'] = array('eq',$product);
        $where['is_del'] = array('eq',1);
        $check = $model->where($where)->select();
        $start_time = $start;
        $end_time = $end;
        if(!empty($check)){
            if(!empty($check)){
                foreach($check as $key=>$value){
                    if( ($start_time>=$value['start_time'] && $end_time<=$value['end_time']) || ($start_time>=$value['start_time'] && $start_time<=$value['end_time']) || ($end_time>=$value['start_time'] && $end_time<=$value['end_time']) || ($start_time<=$value['start_time'] && $end_time>=$value['end_time'] )){
                        return Response::show(400,'您选择的商品已经参加了其他活动，同一件商品不允许参加多个活动');
                    }
                }
            }
        }

        $data['name'] = $name;
        $data['price'] = $price;
        $data['pid'] = $product;
        $data['start_time'] = $start;
        $data['end_time'] = $end;
        $data['hours'] = $hours;
        if(!empty($pic)){
            $pic_string = uploadfile($pic);
            if(empty($pic_string)){
                return Response::show(400,'活动图片上传失败');
            }
            $data['act_img'] = $pic_string;
        }
        if(!empty($face_pic)){
            $face_pic_string = uploadfile($face_pic);
            if(empty($face_pic_string)){
                return Response::show(400,'封面图片上传失败');
            }
            $data['face_img'] = $face_pic_string;
        }
        $add = M('mall_activity','dsy_')->add($data);
        if($add != false){
            return Response::show('200','添加成功');
        }else{
            return Response::show('400','添加失败');
        }

    }

    /**
     * 精选特价主页页面
    **/
    public function special_index(){


        $this->display('special_product_index');
    }

    /**
     * 精选特价主页页面数据
     **/
    public function special_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $type = I('type','');
        if(empty($type)){
            $where['type'] = array('gt',1);
        }else{
            $where['type'] = array('eq',$type);
        }
        $where['is_del'] =  array('eq',1);
        $info = M('mall_activity')
            ->where($where)
            ->page($page,$limit)
            ->field('id,pid,price,start_time,end_time,stock,time,type')
            ->order('id desc')
            ->select();
        $count = M('mall_activity')
            ->where($where)
            ->count();
        $now = date('Y-m-d H:i:s');
        $token = selAccess_token();
        if(!empty($info)){
            foreach($info as $key=>$value){
                $pinfo = M('mall_product')->find($value['pid']);
                $info[$key]['pname'] = $pinfo['name'];
                $info[$key]['dprice'] = $pinfo['price'];//苏鹰价格
                $skuid = $pinfo['skuid'];
                $wzprice = product_price($token,$skuid);
                $info[$key]['jdprice'] = $wzprice['JDPrice'];//jd价格
                $info[$key]['cprice'] = $wzprice['WzPrice'];//成本价
                //剩余库存
                $where_all['pid'] = array('eq',$value['pid']);
                $where_all['is_activity'] = array('eq',2);
                $where_all['act_type'] = 1;
                $where_all['rid'] = array('eq',$value['id']);
                $all_num = M('mall_order_specifications')
                    ->where($where_all)
                    ->sum('num');
                $info[$key]['left_num'] = $value['stock']-$all_num;//剩余库存
                if($now<$value['start_time']){
                    $info[$key]['status'] = '未开始';
                }elseif($value['end_time']<$now){
                    $info[$key]['status'] = '已结束';
                }else{
                    $info[$key]['status'] = '活动中';
                }
            }
        }
        return Response::mjson($info,$count);
    }

    /**
     * 添加特价活动页面
    **/
    public function sepcial_add_index(){

        $this->display('special_product_add');
    }

    /**
     * 添加特价活动
     **/
    public function add_sepcial(){
        $product = I('products','');
        $type = I('type','');
        $price = I('price','');
        $num = I('num','');
        $start_time = I('start1','');
        $end_time = I('end','');
        if($num<1||$price<1){
            return Response::show(400,'数量和价格不能小于1');
        }
        if(empty($product)||empty($type)||empty($price)||empty($num)||empty($start_time)||empty($end_time)){
            return Response::show(300,'请填写完整后提交');
        }
        $now = date('Y-m-d');
        if($start_time<$now || $end_time<$now){
            return Response::show(400,'不能选择过去时间');
        }
        if($start_time>$end_time){
            return Response::show(400,'开始时间不能大于结束时间');
        }

        //判断商品是否可售,是否上架
        $is_sell['status'] = array('eq',1);
        $is_sell['upanddown'] = array('eq',1);
        $is_sell['id'] = array('eq',$product);
        $check_sell = M('mall_product','dsy_')->where($is_sell)->find();
        if(empty($check_sell)){
            return Response::show('400','添加商品不可售'.M()->getLastSql());
        }

        //判断该商品有没有在其他活动中
        $model = M('mall_activity');
        $where['pid'] = array('eq',$product);
        $where['is_del'] = array('eq',1);
        $check = $model->where($where)->select();
        $start_time = $start_time.' 00:00:00';
        $end_time = $end_time.' 23:59:59';
        if(!empty($check)){
            foreach($check as $key=>$value){
                if( ($start_time>=$value['start_time'] && $end_time<=$value['end_time']) || ($start_time>=$value['start_time'] && $start_time<=$value['end_time']) || ($end_time>=$value['start_time'] && $end_time<=$value['end_time']) || ($start_time<=$value['start_time'] && $end_time>=$value['end_time'] )){
                    return Response::show(400,'您选择的商品已经参加了其他活动，同一件商品不允许参加多个活动');
                }
            }

        }

        $data['type'] = $type;
        $data['pid'] = $product;
        $data['price'] = $price;
        $data['stock'] = $num;
        $data['start_time'] = $start_time;
        $data['end_time'] = $end_time;
        $data['time'] = $now;
        //添加操作日志
        $admin_log = '新增特价活动，活动商品:' . $check_sell['name'];
        $add = $model->add($data);
        if($add != false){
            admin_log($admin_log, 1, 'dsy_mall_activity:' . $add);
            return Response::show(200,'添加成功');
        }else{
            admin_log($admin_log, 0, 'dsy_mall_activity');
            return Response::show(400,'添加失败');
        }
    }

    /**
     * 添加特价活动预览
    **/
    public function view_price(){
        $ids = I('ids','');
        $token = selAccess_token();
        $pinfo = M('mall_product')->find($ids[0]);
        $skuid = $pinfo['skuid'];
        $price = product_price($token,$skuid);
        $data['dprice'] = $pinfo['price'];
        $data['jdprice'] = $price['JDPrice'];
        $data['cprice'] = $price['WzPrice'];
        $this->ajaxReturn($data);
    }

    /**
     * 删除特价活动
    **/
    public function del_activity(){
        $ids = I('ids');
        $id = $ids[0];

        $model = M('mall_activity');
        $where['id'] = array('eq',$id);
        $data['is_del'] = 0;
        $pid = $model->where($where)->getField('pid');
        $infos = M('mall_product')->where(['id' => $pid])->getField('name');
        //添加操作日志
        $admin_log = '删除特价活动，活动商品:' . $infos;
        $del = $model->where($where)->save($data);
        if($del != false){
            admin_log($admin_log, 1, 'dsy_mall_activity:' . $id);
            return Response::show(200,'删除成功');
        }else{
            admin_log($admin_log, 0, 'dsy_mall_activity:' . $id);
            return Response::show(400,'删除失败');
        }

    }

    /**
     * 修改新服广场的活动商品价格
    **/
    public function edit_products(){
        $id = I('id','');
        $this->assign('id',$id);
        $this->display();
    }

    /**
     * 列表数据
     **/
    public function edit_list()
    {
        $id = I('id');//活动id
        if (empty($id)) {
            return Response::show(300, '缺少参数');
        }
        //获取活动内套餐
        $ainfo = M('company_activity', 'dsy_')->find($id);
        if (!empty($ainfo)) {
            $specs = [];
            if (!empty($ainfo['packages_info'])) {
                $new_array = unserialize($ainfo['packages_info']);
                foreach ($new_array as $k => $v) {
                    if ($v['spec'] != 0) {
                        $key = 't' . $v['package_id'] . 'p' . $v['pid'];
                        foreach ($v['spec'] as $kk => $vv) {
                            $specs[$key . $vv['vkey']] = $vv['save_price'];
                        }
                    }
                }
            } else {
                $packages = $ainfo['packages'];
                $where['id'] = array('in', $packages);
                $pinfo = M('company_package', 'dsy_')->where($where)->field('id,pids,name')->select();
                $new_array = array();
                foreach ($pinfo as $key => $value) {
                    $pid = explode(',', $value['pids']);
                    foreach ($pid as $kk => $vv) {
                        $one_product = getpinfobyid($vv);
                        $data = [];
                        $data['package_id'] = $value['id'];
                        $data['package_name'] = $value['name'];
                        $data['pid'] = $vv;
                        $data['pname'] = $one_product['pname'];
                        $data['price'] = $one_product['price'];
                        $data['wz_price'] = $one_product['wz_price'];
                        $data['cost_price'] = $one_product['cost_price'];
                        $data['save_price'] = $one_product['price'];
                        $new_array[] = $data;
                    }
                }
            }

            $c_model = M('mall_product_specification_config');
            foreach ($new_array as $k => $v) {
                $config_list = $c_model
                    ->where(['pid' => $v['pid']])
                    ->field('`vkey`,`vname`,`price`,`jd_price`,`cost_price`,`price` as save_price,`is_default`')
                    ->select();
                if (empty($config_list)) {
                    $new_array[$k]['spec'] = 0;
                } else {
                    $key = 't' . $v['package_id'] . 'p' . $v['pid'];
                    foreach ($config_list as $kk => $vv) {
                        if (isset($specs[$key . $vv['vkey']])) {
                            $config_list[$kk]['save_price'] = $specs[$key . $vv['vkey']];
                        }
                    }
                    $new_array[$k]['spec'] = $config_list;
                }
            }

        }


        return Response::mjson($new_array, 0);

    }

    /**
     * 修改操作
     **/
    public function edit_do()
    {
        $info = $_REQUEST['info'];
        $specs = $_REQUEST['specs'];
        $id = $_REQUEST['aid'];

        if (empty($info) || empty($specs) || empty($id)) {
            return Response::show(300, '缺少参数');
        }

        foreach ($info as $k => $v) {
            $key = 't' . $v['package_id'] . 'p' . $v['pid'];
            if ($v['spec'] == 0) {
                $save_price = $specs[$key];
                if (is_numeric($save_price) == false) {
                    return Response::show(400, '价格非金额格式');
                }
                if ($save_price < 0) {
                    return Response::show(400, '价格非金额格式');
                }
                $info[$k]['save_price'] = $save_price;
            } else {
                $config_list_new = [];
                $config_default = $v['save_price'];
                foreach ($v['spec'] as $kk => $vv) {
                    $save_price = $specs[$key . $vv['vkey']];
                    if (is_numeric($save_price) == false) {
                        return Response::show(400, '价格非金额格式');
                    }
                    if ($save_price < 0) {
                        return Response::show(400, '价格非金额格式');
                    }
                    if ($vv['is_default'] == 1) {
                        $config_default = $save_price;
                    }
                    $config_list_new[] = [
                        'vkey' => $vv['vkey'],
                        'save_price' => $save_price,
                    ];
                }
                $info[$k]['save_price'] = $config_default;
                $info[$k]['spec'] = $config_list_new;
            }
        }
        $info = serialize($info);
        $save = M('company_activity')->where(['id' => $id])->setField('packages_info', $info);
        $infos = M('company_activity')->where(['id' => $id])->getField('name');
        //添加操作日志
        $admin_log = '编辑活动【' . $infos . '】套餐商品价格';
        admin_log($admin_log, 1, 'dsy_company_activity:' . $id);
        return Response::show(200, '修改价格成功');

    }

    /**
     * es搜索引擎检索商品
     **/
    public function getProductList()
    {
        header("Access-Control-Allow-Origin: *");
        $param = $_REQUEST;
        $start = isset($param['start']) ? intval($param['start']) : 0;
        $start = max($start, 0);
        $pageSize = 10;
        $condition = $param['pname'];
        $upanddown = (int)$param['upanddown'];
        $jd_num = $param['jd_num'];
        $recommend = (int)$param['recommend'];
        $pone = (float)$param['pone'];
        $ptwo = (float)$param['ptwo'];
        $first = (int)$param['first'];
        $second = (int)$param['second'];
        $third = (int)$param['third'];
        $type = $param['products_type'];
        $type = $type == 2 ? 2 : 1;

        //获取列表
        $result = [];
        $count = 0;
        //查询字段
        $field = 'id,skuid,price,jd_price,wz_price,cost_price,name,upanddown,difference_price,isrecommend,etime,pnum,views,flid,slid,tlid';

        //先查编号是否存在
        if (empty($jd_num)) {
            //先查找分类
            if (!empty($condition)) {
                $third = M('mall_tlevel')
                    ->where(['name' => $condition, 'status' => 1])
                    ->order('`id` desc')
                    ->getField('id');
            }
            //条件
            $must = [];
            if (!empty($condition)) {
                $must['multi_match'] = [
                    "query" => $condition,
                    "fields" => "name",
                    "minimum_should_match" => "75%",
                    "operator"=>"and"
                ];
            }
            $filter = [];
            $filter[]['term']['status'] = 1;
            if (in_array($type, [1, 2])) {
                $filter[]['term']['type'] = $type;
            }
            if ($recommend == 1) {
                $filter[]['term']['isrecommend'] = 1;
            }
            if (in_array($upanddown, [1, 2])) {
                $filter[]['term']['upanddown'] = $upanddown;
            }
            if ($first > 0) {
                $filter[]['term']['flid'] = $first;
            }
            if ($second > 0) {
                $filter[]['term']['slid'] = $second;
            }
            if ($third > 0) {
                $filter[]['term']['tlid'] = $third;
            }
            $range = [];
            if (!empty($pone)) {
                $range['price']['gte'] = $pone;
            }
            if (!empty($ptwo)) {
                $range['price']['lte'] = $ptwo;
            }
            if (!empty($range)) {
                $filter[]['range'] = $range;
            }

            //排序
            $order = [];
            $order['id'] = 'desc';

            //运用elasticsearch查询商品名称匹配的商品id
            $url = ES_URL . '/' . ES_INDEX . '/_search';
            $data = [
                "query" => [
                    "bool" => [
                        "must" => $must,
                        "filter" => $filter
                    ]
                ],
                "sort" => $order,
                "_source" => ["skuid"],
                "from" => $start,
                "size" => $pageSize
            ];
            $data = json_encode($data);
            $re = es_curl($url, 'post', $data);

            $pro_skuids = [];
            if ($re['timed_out'] == false) {
                if ($re['hits']['total'] > 0) {
                    $count = $re['hits']['total'];
                    $list = (array)array_column($re['hits']['hits'], '_source');
                    $pro_skuids = (array)array_column($list, 'skuid');
                }
            }
            if (!empty($pro_skuids)) {
                //获取列表
                $result = M('mall_product')
                    ->where(['skuid' => ['in', $pro_skuids]])
                    ->field($field)
                    ->order('field(skuid,\'' . implode("','", $pro_skuids) . '\')')
                    ->select();
                if (count($pro_skuids) > count($result)) {
                    $count -= ($pageSize - count($result));
                }
            }
        } else {
            $where_jd = [];
            $where_jd['status'] = 1;
            $where_jd['skuid'] = $jd_num;
            if (in_array($type, [1, 2])) {
                $where_jd['type'] = $type;
            }
            if ($recommend == 1) {
                $where_jd['isrecommend'] = 1;
            }
            if (in_array($upanddown, [1, 2])) {
                $where_jd['upanddown'] = $upanddown;
            }
            if ($first > 0) {
                $where_jd['flid'] = $first;
            }
            if ($second > 0) {
                $where_jd['slid'] = $second;
            }
            if ($third > 0) {
                $where_jd['tlid'] = $third;
            }
            if (!empty($pone)) {
                $where_jd['price'][] = ['egt', $pone];
            }
            if (!empty($ptwo)) {
                $where_jd['price'][] = ['elt', $ptwo];
            }

            //获取列表
            $result = M('mall_product')
                ->where($where_jd)
                ->field($field)
                ->select();
            print_r($result);die();
            $count = count($result);
        }
        if (empty($result)) {
            $result = [];
        }
        foreach ($result as $k => $v) {
            if ($type == 2) {
                $provinces = M('mall_product_delivery')->where(['pid' => $v['id']])->getField('province', true);
                $result[$k]['delivery'] = empty($provinces) ? '全国' : implode(',', $provinces);
            } else {
                $result[$k]['delivery'] = '';
            }
            $result[$k]['first'] = M('mall_flevel')->where(['id' => $v['flid']])->getField('name');
            $result[$k]['second'] = M('mall_slevel')->where(['id' => $v['slid']])->getField('name');
            $result[$k]['third'] = M('mall_tlevel')->where(['id' => $v['tlid']])->getField('name');
        }

        return Response::mjson($result, $count);
    }

    /**
     * es搜索引擎检索商品（活动）
     **/
    public function getActProductList()
    {
        header("Access-Control-Allow-Origin: *");
        $param = $_REQUEST;
        $page = isset($param['pageIndex']) ? intval($param['pageIndex']) : 0;
        $pageSize = 10;
        $start = $page * $pageSize;
        $condition = $param['pname'];
        $type = $param['products_type'];
        $jd_num = $param['pid'];
        if($type == 1 || $type == 2 || $type == 4){
            //获取列表
            $result = [];
            $count = 0;
            //查询字段
            $field = 'id,cnum,skuid,price,jd_price,cost_price,name';

            //先查编号是否存在
            if (empty($jd_num)) {
                $third = 0;
                //先查找分类
                if (!empty($condition)) {
                    $third = M('mall_tlevel')
                        ->where(['name' => $condition, 'status' => 1])
                        ->order('`id` desc')
                        ->getField('id');
                }
                //条件
                $must = [];
                if (!empty($condition)) {
                    $must['multi_match'] = [
                        "query" => $condition,
                        "fields" => "name",
                        "minimum_should_match" => "75%"
                    ];
                }
                $filter = [];
                $filter[]['term']['status'] = 1;
                $filter[]['term']['upanddown'] = 1;
                if (in_array($type, [1, 2])) {
                    $filter[]['term']['type'] = $type;
                }
                if ($third > 0) {
                    $filter[]['term']['tlid'] = $third;
                }

                //运用elasticsearch查询商品名称匹配的商品id
                $url = ES_URL . '/' . ES_INDEX . '/_search';
                $data = [
                    "query" => [
                        "bool" => [
                            "must" => $must,
                            "filter" => $filter
                        ]
                    ],
                    "_source" => ["skuid"],
                    "from" => $start,
                    "size" => $pageSize
                ];
                $data = json_encode($data);
                $re = es_curl($url, 'post', $data);

                $pro_skuids = [];
                if ($re['timed_out'] == false) {
                    if ($re['hits']['total'] > 0) {
                        $count = $re['hits']['total'];
                        $list = (array)array_column($re['hits']['hits'], '_source');
                        $pro_skuids = (array)array_column($list, 'skuid');
                    }
                }
                if (!empty($pro_skuids)) {
                    //获取列表
                    $result = M('mall_product')
                        ->where(['skuid' => ['in', $pro_skuids]])
                        ->field($field)
                        ->order('field(skuid,\'' . implode("','", $pro_skuids) . '\')')
                        ->select();
                    if (count($pro_skuids) > count($result)) {
                        $count -= ($pageSize - count($result));
                    }
                }
            } else {
                $where_jd = [];
                $where_jd['status'] = 1;
                $where_jd['upanddown'] = 1;
                if (in_array($type, [1, 2])) {
                    $where_jd['type'] = $type;
                }
                $where_jd['skuid'] = $jd_num;

                //获取列表
                $result = M('mall_product')
                    ->where($where_jd)
                    ->field($field)
                    ->select();
                $count = count($result);
            }
            if (empty($result)) {
                $result = [];
            }
        }elseif($type == 3){
            $page = isset($param['pageIndex']) ? intval($param['pageIndex']) : 0;
            $page += 1;
            $field = 'product_id as id,sku_id as skuid,price,sn_price as jd_price,naked_price as cost_price,name';
            $where_sn['status'] = array('eq',1);
            $where_sn['my_status'] = array('eq',1);
            if(!empty($condition)){
                $where_sn['name'] = array('like','%' . $condition . '%');
            }
            if(!empty($jd_num)){
                $where_sn['sku_id'] = array('eq',$jd_num);
            }
            $result = M('sn_product')->field($field)->where($where_sn)->page($param['pageIndex'] + 1,$pageSize)->select();
            $count = M('sn_product')->field($field)->where($where_sn)->count();
        }
        $goodsArr = I('info', []);
            if (empty($goodsArr)) { 
                $check_ids = [];
                $goodsArr = [];
            } else {
                $check_ids = array_column($goodsArr, 'id');
            }
            foreach ($result as $k => $v) {
                if (in_array($v['id'], $check_ids)) {
                    $result[$k]['LAY_CHECKED'] = true;
                }
                $result[$k]['num'] = 1;
                foreach ($goodsArr as $gk => $gv) {
                    if ($gv['id'] == $v['id']) {
                        $result[$k]['num'] = $gv['num'];
                    }
                }
            }
        $data = [];
        $data['code'] = 200;
        $data['msg'] = '';
        $data['count'] = $count;
        $data['data'] = $result;
        $data['is_data'] = $goodsArr;
        $this->ajaxReturn($data);
    }
        /**
     * es搜索引擎检索商品（活动）
     **/
    public function getActProductListExchang()
    {
        header("Access-Control-Allow-Origin: *");
        $param = $_REQUEST;
        $page = isset($param['pageIndex']) ? intval($param['pageIndex']) : 0;
        $pageSize = 10;
        $start = $page * $pageSize;
        $condition = $param['pname'];
        $type = $param['products_type'];
        $jd_num = $param['pid'];
        if($type == 1 || $type == 2){
            //获取列表
            $result = [];
            $count = 0;
            //查询字段
            $field = 'id,cnum,skuid,price,jd_price,cost_price,name';

            //先查编号是否存在
            if (empty($jd_num)) {
                $third = 0;
                //先查找分类
                if (!empty($condition)) {
                    $third = M('mall_tlevel')
                        ->where(['name' => $condition, 'status' => 1])
                        ->order('`id` desc')
                        ->getField('id');
                }
                //条件
                $must = [];
                if (!empty($condition)) {
                    $must['multi_match'] = [
                        "query" => $condition,
                        "fields" => "name",
                        "minimum_should_match" => "75%"
                    ];
                }
                $filter = [];
                $filter[]['term']['status'] = 1;
                $filter[]['term']['upanddown'] = 1;
                if (in_array($type, [1, 2])) {
                    $filter[]['term']['type'] = $type;
                }
                if ($third > 0) {
                    $filter[]['term']['tlid'] = $third;
                }

                //运用elasticsearch查询商品名称匹配的商品id
                $url = ES_URL . '/' . ES_INDEX . '/_search';
                $data = [
                    "query" => [
                        "bool" => [
                            "must" => $must,
                            "filter" => $filter
                        ]
                    ],
                    "_source" => ["skuid"],
                    "from" => $start,
                    "size" => $pageSize
                ];
                $data = json_encode($data);
                $re = es_curl($url, 'post', $data);

                $pro_skuids = [];
                if ($re['timed_out'] == false) {
                    if ($re['hits']['total'] > 0) {
                        $count = $re['hits']['total'];
                        $list = (array)array_column($re['hits']['hits'], '_source');
                        $pro_skuids = (array)array_column($list, 'skuid');
                    }
                }
                if (!empty($pro_skuids)) {
                    //获取列表
                    $result = M('mall_product')
                        ->where(['skuid' => ['in', $pro_skuids]])
                        ->field($field)
                        ->order('field(skuid,\'' . implode("','", $pro_skuids) . '\')')
                        ->select();
                    if (count($pro_skuids) > count($result)) {
                        $count -= ($pageSize - count($result));
                    }
                }
            } else {
                $where_jd = [];
                $where_jd['status'] = 1;
                $where_jd['upanddown'] = 1;
                if (in_array($type, [1, 2])) {
                    $where_jd['type'] = $type;
                }
                $where_jd['skuid'] = $jd_num;

                //获取列表
                $result = M('mall_product')
                    ->where($where_jd)
                    ->field($field)
                    ->select();
                $count = count($result);
            }
            if (empty($result)) {
                $result = [];
            }
        }elseif($type == 3){
            $page = isset($param['pageIndex']) ? intval($param['pageIndex']) : 0;
            $page += 1;
            $field = 'product_id as id,sku_id as skuid,price,sn_price as jd_price,naked_price as cost_price,name';
            $where_sn['status'] = array('eq',1);
            $where_sn['my_status'] = array('eq',1);
            if(!empty($condition)){
                $where_sn['name'] = array('like','%' . $condition . '%');
            }
            if(!empty($jd_num)){
                $where_sn['sku_id'] = array('eq',$jd_num);
            }
            $result = M('sn_product')->field($field)->where($where_sn)->page($param['pageIndex'] + 1,$pageSize)->select();
            $count = M('sn_product')->where($where_sn)->count();
        }
        $goodsArr = I('info', []);
            if (empty($goodsArr)) {
                $goodsArr = [];
            }else{
                $goodsArr = explode(',',rtrim($goodsArr,','));
            }
            foreach ($result as $k => $v) {
                if (in_array($v['id'], $goodsArr)) {
                    $result[$k]['LAY_CHECKED'] = true;
                }
            }
        $data = [];
        $data['code'] = 200;
        $data['msg'] = '';
        $data['count'] = $count;
        $data['data'] = $result;
        $data['is_data'] = $goodsArr;
        $this->ajaxReturn($data);
    }
    /**
     * 设置苏鹰优选商品
     */
    public function setRecPro()
    {
        $model = M('config', 'sys_');
        $where = ['name' => 'SET_REC_PRO'];
        if (IS_POST) {
            $pros = $_POST['value'];
            $pros = str_replace(' ', '', $pros);
            $pros = array_unique(array_filter(explode(',', $pros)));
            if (empty($pros)) {
                return Response::show(300, '请填写商品sku');
            }

            $goods = M('mall_product')
                ->where(['skuid' => ['in', $pros], 'status' => 1, 'upanddown' => 1])
                ->order('field(skuid,\'' . implode("','", $pros) . '\')')
                ->field('skuid,name')
                ->select();
            $skuids = array_column($goods, 'skuid');

            //获取数组中不同的元素
            $diff_skuids = empty($skuids) ? $pros : array_diff($pros, $skuids);
            if (!empty($diff_skuids)) {
                return Response::show(300, '商品sku“' . implode('，', $diff_skuids) . '”找不到或已下架、不可售');
            }

            $infos_str = [];
            foreach ($goods as $v) {
                $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            }
            //添加操作日志
            $admin_log = '编辑苏鹰精选商品:' . implode(',', $infos_str);

            $res = $model
                ->where($where)
                ->setField('value', implode(',', $skuids));

            if ($res == false) {
                admin_log($admin_log, 0, 'sys_config:SET_REC_PRO');
                $error = $model->getError();
                $error = empty($error) ? '编辑失败!' : $error;
                return Response::show(300, $error);
            }

            admin_log($admin_log, 1, 'sys_config:SET_REC_PRO');
            return Response::show(200, '编辑成功');
        } else {
            $info = $model
                ->where($where)
                ->getField('value');

            $this->assign('info', $info);
            $this->display('set_rec_pro');
        }
    }

    /**
     * 薪福来领兑换导出
     */
    public function exchangeExcel()
    {
        $aid = I('id', '');
        $status = I('status', 0);
        if (empty($aid)) {
            return Response::show('300', '请选择一个公司导出');
        }
        $cinfo = M('company_activity')->field('`name`,`persons`')->find($aid);

        $c_p_model = M('company_package');
        $p_model = M('personal', 't_');
        $m_o_s_model = M('mall_order_specifications');

        if ($status == 1) {
            import('Org.Util.PHPExcel');
            $objPHPExcel = new \PHPExcel();
            $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
            $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
            $i = 1;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '会员');
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '姓名');
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':B' . $i)->getFont()->setBold(true);
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':B' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

            $list_qb = explode(',', $cinfo['persons']);//全部人员
            $list_yd = M('company_exchange')->where(['status' => 1, 'aid' => $aid])->getField('eid', true);//已领取
            if (empty($list_yd))
                $list = $list_qb;
            else
                $list = array_unique(array_filter(array_values(array_diff($list_qb, $list_yd))));
            if (empty($list)) {
                $i++;
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '暂无记录');
                $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':B' . $i);
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':B' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            } else {
                foreach ($list as $v) {
                    $i++;
                    $personal = getAllEmployeeByEid($v);

                    $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $personal['mobile']);
                    $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $personal['name']);
                }
            }

            $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
            $outputFileName = $cinfo['name'] . date('YmdHis') . '未兑人员' . '.xlsx';
            ob_end_clean();
            header('pragma:public');
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Content-Type:application/force-download");
            header("Content-Type:application/vnd.ms-execl");
            header("Content-Type:application/octet-stream");
            header("Content-Type:application/download");
            header('Content-Disposition:attachment;filename=' . $outputFileName);
            header("Content-Transfer-Encoding:binary");
            $objWriter->save('php://output');
            die;
        }

        $list = M('mall_order_specifications')
            ->join('AS a LEFT JOIN dsy_mall_order AS b ON a.ordernum=b.ordernum')
            ->where(['a.rid' => $aid, 'status' => ['not in', '1,3']])
            ->field('b.`name`,b.`mobile`,b.`address`,b.`uid`,b.`ordernum`,b.`time`,a.tid')
            ->group('a.ordernum')
            ->select();

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(60);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(30);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '订单编号');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '买家账户');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '下单时间');
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, '收货人姓名');
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, '收货手机号');
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, '收货地址');
        $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, '套餐名称');
        $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, '套餐金额');
        $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, '购买数量');
        $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, '商品名称');
        $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, '商品规格');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':K' . $i)->getFont()->setBold(true);

        if (empty($list)) {
            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '暂无记录');
            $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':K' . $i);
        } else {
            foreach ($list as $k => $v) {
                $i++;
                $package = $c_p_model->where(['id' => intval($v['tid'])])->field('name,price')->find();
                $personal = $p_model->where(['user_id' => intval($v['uid'])])->field('`name`,`mobile`')->find();

                $products = $m_o_s_model->where(['ordernum' => $v['ordernum']])->field('`num`,`pro_name`,`specifications`')->select();
                $proNum = count($products);

                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, 'sn:' . $v['ordernum']);
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $personal['mobile']);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['time']);
                $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, $v['name']);
                $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, $v['mobile']);
                $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, $v['address']);
                $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, $package['name']);
                $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, $package['price']);
                $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, $products[0]['num']);
                $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, $products[0]['pro_name']);
                $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, $products[0]['specifications']);

                if ($proNum > 1) {
                    $iS = $i;
                    for ($pi = 2; $pi <= $proNum; $pi++) {
                        $i++;
                        $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, $products[$pi - 1]['num']);
                        $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, $products[$pi - 1]['pro_name']);
                        $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, $products[$pi - 1]['specifications']);
                    }
                    $iE = $i;
                    $objPHPExcel->getActiveSheet()->mergeCells('A' . $iS . ':A' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('B' . $iS . ':B' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('C' . $iS . ':C' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('D' . $iS . ':D' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('E' . $iS . ':E' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('F' . $iS . ':F' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('G' . $iS . ':G' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('H' . $iS . ':H' . $iE);
                }
            }
        }
        $objPHPExcel->getActiveSheet()->getStyle('A1:K' . $i)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A1:E' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('H1:I' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = $cinfo['name'] . date('YmdHis') . '订单' . '.xlsx';
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName);
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 订单导出导出
     */
    public function orderExcel()
    {
        $otype = I('otype', 1);

        $ordernum = I('ordernum', '');//订单编号
        $start_time = I('start1', '');
        $end_time = I('end', '');
        $status = I('status', '');

        $where = [];
        if (!empty($ordernum)) {
            $where['ordernum'] = $ordernum;
        }
        if (!empty($start_time)) {
            $where['time'][] = ['egt', $start_time . ' 00:00:00'];
        }
        if (!empty($end_time)) {
            $where['time'][] = ['elt', $end_time . ' 23:59:59'];
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }

        if ($otype == 1) {
            $title = '微知';
            $where['order_type'] = ['in', '2,3,4'];
            $wzordernum = I('wzordernum', '');//微知订单号
            $addrname = I('addrname', '');//收获人姓名
            $exchange_num = I('exchange_num', '');
            if (!empty($wzordernum)) {
                $order_notpay_num = M('mall_order_notpay')->where(['wz_orderid' => $wzordernum])->getField('ordernum');
                if (empty($order_notpay_num)) {
                    $order_notpay_num = 'nono';
                }
                $where['order_notpay_num'] = $order_notpay_num;
            }
            if (!empty($addrname)) {
                $where['name'] = $addrname;
            }
            if (!empty($exchange_num)) {
                $ordernum = M('company_exchange_record')->where(['num' => $exchange_num])->getField('ordernum');
                if (empty($ordernum)) {
                    $ordernum = 'nono';
                }
                $where['ordernum'] = $ordernum;
            }
        } else {
            $title = '自营';
            $where['order_type'] = ['in', '1,5'];
            $shops = I('shops', '');
            if (!empty($shops)) {
                $where['sid'] = $shops;
            }
        }

        $m_o_s_model = M('mall_order_specifications');
        $c_a_model = M('company_activity');
        $c_p_model = M('company_package');
        $m_l_model = M('mall_logistics');
        $p_model = M('personal', 't_');
        $list = M('mall_order')
            ->where($where)
            ->field('`ordernum`,`status`,`payprice`,`paytime`,`time`,`name`,`mobile`,`address`,`uid`,`etype`,`enum`,`sid`,`pid`')
            ->order('`time` desc')
            ->select();
        $Shoporder = A('ShopOrder');
        foreach($list as $key=>$value){
            $deal_jd_data = $Shoporder->plan_jd_profit($value['pid'],$value['status'],$value['ordernum']);
            $list[$key]['profit_price'] = $deal_jd_data['profit_price'];
            $list[$key]['cost_price'] = $deal_jd_data['cost_price'];
        }
        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(60);
        $objPHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(30);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '订单编号');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '下单时间');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '支付时间');
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, '订单金额');
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, '订单成本');
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, '订单利润');
        $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, '状态');
        $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, '买家账户');
        $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, '收货人姓名');
        $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, '收货手机号');
        $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, '收货地址');
        $objPHPExcel->getActiveSheet()->setCellValue('L' . $i, '物流公司');
        $objPHPExcel->getActiveSheet()->setCellValue('M' . $i, '物流单号');
        $objPHPExcel->getActiveSheet()->setCellValue('N' . $i, '购买数量');
        $objPHPExcel->getActiveSheet()->setCellValue('O' . $i, '商品名称');
        $objPHPExcel->getActiveSheet()->setCellValue('P' . $i, '商品规格');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':P' . $i)->getFont()->setBold(true);

        if (empty($list)) {
            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '暂无记录');
            $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':N' . $i);
        } else {
            foreach ($list as $k => $v) {
                if (empty($v['pid'])) {
                    continue;
                }
                $i++;
                $express = $m_l_model->where(['id' => $v['etype']])->field('name')->find();
                $personal = $p_model->where(['user_id' => intval($v['uid'])])->field('`name`,`mobile`')->find();

                $products = $m_o_s_model->where(['ordernum' => $v['ordernum'], 'pid' => ['in', $v['pid']]])->field('`num`,`pro_name`,`specifications`')->select();
                $proNum = count($products);

                // $mos = $m_o_s_model->where(['ordernum' => $v['ordernum'], 'is_activity' => 2, 'act_type' => 1])->field('`rid`,`tid`,`act_type`')->find();
                // if (!empty($mos)) {
                //     $activity = $c_a_model->where(['id' => $mos['rid']])->field('`get_type`')->find();
                //     if (!empty($activity)) {
                //         if ($activity['get_type'] != 3) {
                //             $package = $c_p_model->where(['id' => $mos['tid']])->field('`price`')->find();
                //             if (!empty($package)) {
                //                 $v['payprice'] = $package['price'];
                //             }
                //         }
                //     }
                // }

                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, 'sn:' . $v['ordernum']);
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['time']);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['paytime']);
                $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, $v['payprice']);
                $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, $v['cost_price']);
                $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, $v['profit_price']);
                $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, getOrderStatus($v['status']));
                $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, $personal['mobile']);
                $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, $v['name']);
                $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, $v['mobile']);
                $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, $v['address']);
                $objPHPExcel->getActiveSheet()->setCellValue('L' . $i, $express['name']);
                $objPHPExcel->getActiveSheet()->setCellValue('M' . $i, 'sn:' . $v['enum']);
                $objPHPExcel->getActiveSheet()->setCellValue('N' . $i, $products[0]['num']);
                $objPHPExcel->getActiveSheet()->setCellValue('O' . $i, $products[0]['pro_name']);
                $objPHPExcel->getActiveSheet()->setCellValue('P' . $i, $products[0]['specifications']);

                if ($proNum > 1) {
                    $iS = $i;
                    for ($pi = 2; $pi <= $proNum; $pi++) {
                        $i++;
                        $objPHPExcel->getActiveSheet()->setCellValue('N' . $i, $products[$pi - 1]['num']);
                        $objPHPExcel->getActiveSheet()->setCellValue('O' . $i, $products[$pi - 1]['pro_name']);
                        $objPHPExcel->getActiveSheet()->setCellValue('P' . $i, $products[$pi - 1]['specifications']);
                    }
                    $iE = $i;
                    $objPHPExcel->getActiveSheet()->mergeCells('A' . $iS . ':A' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('B' . $iS . ':B' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('C' . $iS . ':C' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('D' . $iS . ':D' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('E' . $iS . ':E' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('F' . $iS . ':F' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('G' . $iS . ':G' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('H' . $iS . ':H' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('I' . $iS . ':I' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('J' . $iS . ':J' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('K' . $iS . ':K' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('L' . $iS . ':L' . $iE);
                    $objPHPExcel->getActiveSheet()->mergeCells('M' . $iS . ':M' . $iE);
                }
            }
        }
        $objPHPExcel->getActiveSheet()->getStyle('A1:P' . $i)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A1:N' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('K1:K' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = $title . '订单' . date('YmdHis') . '.xlsx';
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName);
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 设置苏鹰办公用品
     */
    public function setJobPro()
    {
        $model = M('config', 'sys_');
        $where = ['name' => 'SET_JOB_PRO'];
        if (IS_POST) {
            $pros = $_POST['value'];
            $pros = str_replace(' ', '', $pros);
            $pros = array_unique(array_filter(explode(',', $pros)));
            if (empty($pros)) {
                return Response::show(300, '请填写商品sku');
            }

            $goods = M('mall_product')
                ->where(['skuid' => ['in', $pros], 'status' => 1, 'upanddown' => 1])
                ->order('field(skuid,\'' . implode("','", $pros) . '\')')
                ->field('skuid,name')
                ->select();
            $skuids = array_column($goods, 'skuid');

            //获取数组中不同的元素
            $diff_skuids = empty($skuids) ? $pros : array_diff($pros, $skuids);
            if (!empty($diff_skuids)) {
                return Response::show(300, '商品sku“' . implode('，', $diff_skuids) . '”找不到或已下架、不可售');
            }

            $infos_str = [];
            foreach ($goods as $v) {
                $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            }
            //添加操作日志
            $admin_log = '编辑苏鹰办公用品:' . implode(',', $infos_str);

            $res = $model
                ->where($where)
                ->setField('value', implode(',', $skuids));

            if ($res == false) {
                admin_log($admin_log, 0, 'sys_config:SET_JOB_PRO');
                $error = $model->getError();
                $error = empty($error) ? '编辑失败!' : $error;
                return Response::show(300, $error);
            }

            admin_log($admin_log, 1, 'sys_config:SET_JOB_PRO');
            return Response::show(200, '编辑成功');
        } else {
            $info = $model
                ->where($where)
                ->getField('value');

            $this->assign('info', $info);
            $this->display('set_job_pro');
        }
    }

    /**
     * 设置苏鹰劳保
     */
    public function setWorkPro()
    {
        $model = M('config', 'sys_');
        $where = ['name' => 'SET_WORK_PRO'];
        if (IS_POST) {
            $pros = $_POST['value'];
            $pros = str_replace(' ', '', $pros);
            $pros = array_unique(array_filter(explode(',', $pros)));
            if (empty($pros)) {
                return Response::show(300, '请填写商品sku');
            }

            $goods = M('mall_product')
                ->where(['skuid' => ['in', $pros], 'status' => 1, 'upanddown' => 1])
                ->order('field(skuid,\'' . implode("','", $pros) . '\')')
                ->field('skuid,name')
                ->select();
            $skuids = array_column($goods, 'skuid');

            //获取数组中不同的元素
            $diff_skuids = empty($skuids) ? $pros : array_diff($pros, $skuids);
            if (!empty($diff_skuids)) {
                return Response::show(300, '商品sku“' . implode('，', $diff_skuids) . '”找不到或已下架、不可售');
            }

            $infos_str = [];
            foreach ($goods as $v) {
                $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            }
            //添加操作日志
            $admin_log = '编辑苏鹰精选商品:' . implode(',', $infos_str);

            $res = $model
                ->where($where)
                ->setField('value', implode(',', $skuids));

            if ($res == false) {
                admin_log($admin_log, 0, 'sys_config:SET_WORK_PRO');
                $error = $model->getError();
                $error = empty($error) ? '编辑失败!' : $error;
                return Response::show(300, $error);
            }

            admin_log($admin_log, 1, 'sys_config:SET_WORK_PRO');
            return Response::show(200, '编辑成功');
        } else {
            $info = $model
                ->where($where)
                ->getField('value');

            $this->assign('info', $info);
            $this->display('set_work_pro');
        }
    }
    /**设置优惠活动 */
    public function setActivityPro()
    {
        $model = M('config', 'sys_');
        $where = ['name' => 'SET_ACTIVITY_PRO'];
        if (IS_POST) {
            $pros = $_POST['value'];
            $pros = str_replace(' ', '', $pros);
            $pros = array_unique(array_filter(explode(',', $pros)));
            if (empty($pros)) {
                return Response::show(300, '请填写商品sku');
            }

            $goods = M('mall_product')
                ->where(['skuid' => ['in', $pros], 'status' => 1, 'upanddown' => 1])
                ->order('field(skuid,\'' . implode("','", $pros) . '\')')
                ->field('skuid,name')
                ->select();
            $skuids = array_column($goods, 'skuid');

            //获取数组中不同的元素
            $diff_skuids = empty($skuids) ? $pros : array_diff($pros, $skuids);
            if (!empty($diff_skuids)) {
                return Response::show(300, '商品sku“' . implode('，', $diff_skuids) . '”找不到或已下架、不可售');
            }

            $infos_str = [];
            foreach ($goods as $v) {
                $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            }
            //添加操作日志
            $admin_log = '编辑苏鹰精选商品:' . implode(',', $infos_str);

            $res = $model
                ->where($where)
                ->setField('value', implode(',', $skuids));

            if ($res == false) {
                admin_log($admin_log, 0, 'sys_config:SET_WORK_PRO');
                $error = $model->getError();
                $error = empty($error) ? '编辑失败!' : $error;
                return Response::show(300, $error);
            }

            admin_log($admin_log, 1, 'sys_config:SET_WORK_PRO');
            return Response::show(200, '编辑成功');
        } else {
            $info = $model
                ->where($where)
                ->getField('value');

            $this->assign('info', $info);
            $this->display('set_activity_pro');
        }
    }
    public function productCheck()
    {
        $this->display();
    }

    public function productCheckDo()
    {
        $skuId = I('skuId', '');
        if (empty($skuId)) {
            return Response::show(300, '请输入商品sku编号');
        }
        $product = M('mall_product')->where(['skuid' => $skuId])->field('id,status,upanddown')->find();

        $data = [
            'sy_status' => '无',
            'sy_upanddown' => '无',
            'wz_status' => '无',
            'wz_upanddown' => '无',
        ];
        if (empty($product)) {
            $pid = 0;
        } else {
            $pid = $product['id'];
            $data['sy_status'] = ($product['status'] == 1) ? '正常' : '不售';
            $data['sy_upanddown'] = ($product['upanddown'] == 1) ? '上架' : '下架';
        }
        $JdApi = new JdApiController();
        $resSale = $JdApi->checkSkuSaleGoods([$skuId]);
        if ($resSale['code'] == 200 && !empty($resSale['data'])) {
            $data['wz_status'] = ($resSale['data'][0]['saleState'] == 1) ? '正常' : '不售';
        }
        $resState = $JdApi->getSkuStateGoods([$skuId]);
        if ($resState['code'] == 200 && !empty($resState['data'])) {
            $data['wz_upanddown'] = ($resState['data'][0]['skuState'] == 1) ? '上架' : '下架';
        }
        $data['pid'] = $pid;

        return Response::json(200, 'success', $data);
    }

    public function activitySmsPage()
    {
        $rid = I('rid', 0);
        $this->assign('rid', $rid);

        $info = M('company_activity')
            ->where(['id' => $rid])
            ->field('name,allow_times,start_time,end_time,is_del')
            ->find();

        $count = M('company_exchange')->where(['status' => 1, 'aid' => $rid])->field('id')->group('eid')->select();
        $count = count($count);
        $left = intval($info['allow_times']) - $count;
        $left = max(0, $left);
        $info['leftNum'] = $left;

        $showBtn = 0;
        if ($left > 0) {
            if ($info['is_del'] != 1) {
                $now = time();
                if ($now >= $info['start_time'] && $info['end_time'] > $now) {
                    $showBtn = 1;
                }
            }
        }
        $this->assign('showBtn', $showBtn);
        $this->assign('info', $info);

        $this->display();
    }

    public function activitySmsList()
    {
        $rid = I('rid', 0);

        $list = M('company_sendrecord')
            ->where(['aid' => $rid])
            ->field('create_date,msg,success_num,fail_num')
            ->select();
        echo json_encode($list);
        die;
    }


    public function activitySendSms()
    {
        $aid = I('aid', 0);
        $msg = I('msg', '');

        if (empty($aid)) {
            return Response::show(300, '参数错误');
        }
        if (empty($msg)) {
            return Response::show(300, '请填写短信内容');
        }
        $time = time();//当前时间戳
        $now = date('Y-m-d', $time) . ' 00:00:00';
        $date = strtotime($now);

        $activity_where = [];
        $activity_where['start_time'] = array('elt', $time);//开始时间
        $activity_where['end_time'] = array('gt', $time);//结束时间
        $activity_where['id'] = $aid;
        $activity_where['is_del'] = ['neq', 1];
        //活动列表
        $data = M('company_activity')
            ->where($activity_where)
            ->field('id,persons')
            ->find();
        if (empty($data)) {
            return Response::show(300, '该活动已禁用或已结束，不可以推送短信。');
        }

        $sendrecord_where = [];
        $sendrecord_where['aid'] = $aid;//活动ID
        $sendrecord_where['time'][] = ['egt', $date];
        $sendrecord_where['time'][] = ['lt', $date + 86400];
        $result = M('company_sendrecord')
            ->where($sendrecord_where)
            ->field('id')
            ->find();
        //判断是否已经发送
        if (!empty($result)) {
            return Response::show(300, '今日该活动短信已发');
        }

        //查找活动会员
        $employee_where = [];
        $employee_where['a.employee_id'] = array('in', $data['persons']);
        $employee_result = M('employee', 't_')
            ->alias('a')
            ->join('left join t_personal as b on a.personal_id=b.personal_id')
            ->where($employee_where)
            ->field('a.employee_id,b.mobile,b.name')
            ->select();
        if (empty($employee_result)) {
            return Response::show(300, '没有可发送短信的员工');
        }
//        die(json_encode($employee_result));
        $successEid = [];
        $failEid = [];
        $totalNum = 0;
        $successNum = 0;
        $failNum = 0;
        $model = M('company_exchange');
        foreach ($employee_result as $vv) {
            if (empty($vv['mobile'])) {
                continue;
            }
            //领取的不发送短信
            $count = intval($model->where(['status' => 1, 'aid' => $aid, 'eid' => $vv['employee_id']])->count());
            if ($count > 0) {
                continue;
            }
            $totalNum++;
            $return = SendTemplateSMS($vv['mobile'], [$vv['name'], $msg], 432557, 2);
            if ($return === 0) {
                $successEid[] = $vv['employee_id'];
                $successNum++;
            } else {
                $failEid[] = $vv['employee_id'];
                $failNum++;
            }
        }
        //添加短信发送记录
        $info = [
            'aid' => $aid, //活动ID
            'time' => time(), //发送时间
            'msg' => $msg,
            'success_num' => $successNum,
            'fail_num' => $failNum,
            'success_eid' => implode(',', $successEid),
            'fail_eid' => implode(',', $failEid),
        ];
        M('company_sendrecord')->add($info);
        return Response::show(200, '短信发送成功，共' . $totalNum . '条，成功' . $successNum . '条，失败' . $failNum . '条');
    }
    public function company_template_list(){
        $this->display();
    }
    public function company_template_data(){
        $pageIndex = I('pageIndex','');
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $company_name = I('company_name');
        $where = [];
        if(!empty($company_name)){
            $where['b.corporate_name'] = array('like',"%$company_name%");
        }
        $result = M('cor_use_template')
                    ->alias('a')
                    ->field('a.id,b.corporate_name,c.name,a.create_time')
                    ->join('LEFT JOIN t_corporate as b on a.cid=b.corporate_id')
                    ->join('LEFT JOIN dsy_hr_template as c on a.template_id=c.id')
                    ->where($where)
                    ->order('id desc')
                    ->page($page,$limit)
                    ->select();
        $num = M('cor_use_template')
                    ->alias('a')
                    ->join('LEFT JOIN t_corporate as b on a.cid=b.corporate_id')
                    ->join('LEFT JOIN dsy_hr_template as c on a.template_id=c.id')
                    ->where($where)
                    ->count();
        return Response::mjson($result,$num);
    }
    public function company_template_detail(){
        $id = I('id');
        $template = M('hr_template')->field('id,name')->select();
        $company = M('cor_use_template a')
                    ->field('a.id,b.corporate_name,a.template_id')
                    ->join('LEFT JOIN t_corporate as b on a.cid=b.corporate_id')
                    ->where(['id'=>$id])
                    ->find();
        $this->assign('template',$template);
        $this->assign('company',$company);
        $this->display();
    }
    public function company_template_save(){
        $id = I('id');
        $type = I('type','');
        if($type == ''){
            return Response::show(300,'请选择要展示的模板');
        }
        $result = M('cor_use_template')->where(['id'=>$id])->setField('template_id',$type);
        if($result !== false){
            return Response::show(200,'修改成功');
        }else{
            return Response::show(300,'修改失败');
        }
    }
    public function special_plus_index(){
        $this->display();
    }
    public function special_plus_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $company = I('company','');
        $name = I('name');
        $start = I('start1');
        $end = I('end');
        $month = I('month');
        if(!empty($company)){
            $where['d.corporate_name'] = array('like',"%$company%");
        }
        if(!empty($name)){
            $where['_string'] = "c.name like'$name%' OR c.id_no='$name'";
        }
        if(!empty($start)&&!empty($end)){
            if($start==$end){
                $where['a.time'] = array('like',"%".$start."%");
            }else{
                $start_time = $start .' 00:00:00';
                $end_time = $end.' 23:59:59';
                $where['a.time'] = array('between',array($start_time,$end_time));
            }
        }else{
            if(empty($month)){
                $day = date('Y-m');
                $where['a.time'] = array('like',"%$day%");
            }
        }
        if(!empty($month)){
            $where['a.deduct_year_month'] = array('eq',date('Ym',strtotime($month . '-01')));
        }
        $result = M('salary_sixdeduct')
                    ->alias('a')
                    ->field('a.*,c.name,c.id_no,d.corporate_name,e.operator')
                    ->join('left join t_employee as b on a.employee_id = b.employee_id')
                    ->join('left join t_personal as c on b.personal_id = c.personal_id')
                    ->join('left join t_corporate as d on b.corporate_id = d.corporate_id')
                    ->join('left join dsy_salary_sixdeduct_log as e on(a.time = e.time and d.corporate_id = e.corporate_id)')
                    ->where($where)
                    ->order('a.time desc,a.id desc')
                    ->page($page,$limit)
                    ->select();
        $num = M('salary_sixdeduct')
                    ->alias('a')
                    ->join('left join t_employee as b on a.employee_id = b.employee_id')
                    ->join('left join t_personal as c on b.personal_id = c.personal_id')
                    ->join('left join t_corporate as d on b.corporate_id = d.corporate_id')
                    ->join('left join dsy_salary_sixdeduct_log as e on(a.time = e.time and d.corporate_id = e.corporate_id)')
                    ->where($where)
                    ->count();
        foreach($result as $key=>$val){
            $result[$key]['deduct_year_month'] = date('Y-m',strtotime($val['deduct_year_month'] . 15));
        }
        return Response::mjson($result,$num);
    }
    /**
     * 专项附加扣除导入
     */
    public function shop_import_data(){
        set_time_limit(0);
        import("Vendor.PHPExcel.PHPExcel");
        import("Vendor.PHPExcel.PHPExcel.Writer.Excel5");
        import("Vendor.PHPExcel.PHPExcel.IOFactory.php");
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;
        $cacheSettings = array( 'memoryCacheSize ' => '20MB');
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
        // 实例化exel对象
        //文件路径
        $files = $_FILES['excel'];
        $upload = new \Think\Upload();// 实例化上传类
	    $upload->maxSize   =     3145728 ;// 设置附件上传大小
	    $upload->exts      =     array('xlsx', 'xls');// 设置附件上传类型
	    $upload->rootPath  =     './'; // 设置附件上传根目录
        $upload->savePath  =     'Public/Uploads/temp/'; // 设置附件上传（子）目录
        $upload->subName   =     array('date','Ymd');
	    $info   =   $upload->uploadOne($files);
	    if(!$info) {
            // 上传错误提示错误信息
            return Response::show(300,$upload->getError()); 
		}else{
			// 上传成功 获取上传文件信息
            $infopath = $upload->rootPath.$info['savepath'].$info['savename'];
		}
        $file_path = $infopath;
        //$file_path = "C:/XAMPP/htdocs/dashengyun/Public/Uploads/temp/20201211/ccc.xlsx";
        //文件的扩展名
        $ext = strtolower(pathinfo($file_path,PATHINFO_EXTENSION));
        if ($ext == 'xlsx'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objPHPExcel = $objReader->load($file_path);
        }elseif($ext == 'xls'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel5');
            $objPHPExcel = $objReader->load($file_path);
        }
        $objReader->setReadDataOnly(true);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();//获取总行数
        if($highestRow == 1){
            return Response::show(300,'<p>请添加要导入的数据</p>');
        }
        $highestColumn = $sheet->getHighestColumn();//获取总列数
        $record = array();//申明每条记录数组
        $num = 0;
        $error = [];//记录错误数据
        $corporate = [];
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC');
        for ($i = 2;$i<=$highestRow;$i++){
            $col = 0;
            for ($j = 0;$j<26;$j++){
                $record[$num][$col] = $objPHPExcel->getActiveSheet()->getCell($letter[$j] . "$i")->getValue();//读取单元格+;
                $col++;
            }
            header("Content-type:text/html;charset=utf-8");
            $num++;
        }
        //return Response::mjson(200,'success',$record);die();
        $success_time = date('Y-m-d H:i:s',time());
        $sixdeduct_data = [];//要新增的专项附加扣除人员
        $salary_sixdeduct = M('salary_sixdeduct');
        $salary_sixdeduct->startTrans();
        foreach($record as $key=>$val){
            $row = $key + 2;
            if(empty($val[3])){
                $error[] = '员工' .  $val[1] . ",第" . $row . '行第' . $letter[3] . '列,身份证号缺失';
                continue;
            }
            if(!empty($val[14]) && !empty($val[15])){
                $error[] = '员工' .  $val[1] . ',累计住房贷款和累计住房租金不能同时享受';
            }
            if(empty($val[4]) && empty($val[5])){
                $error[] = '员工' .  $val[1] . '所得期间起止时间不能为空';
            }
            $where['a.id_no'] = array('eq',$val[3]);
            $where['_string'] = "b.leave_time > '" . $val[4] . " 00:00:00' OR ISNULL(b.leave_time) is TRUE";
            $where['b.employee_id'] = array('neq','');
            $personal = M('personal','t_')
                            ->alias('a')
                            ->field('a.personal_id,b.employee_id,b.corporate_id')
                            ->join('left join t_employee as b on a.personal_id=b.personal_id')
                            ->where($where)
                            ->select();
            if(!$personal){
                $error[] = '员工' .  $val[1] . ',在人事邦系统中没有找到该员工';
                continue;
            }
            //print_r($personal);die();
            $deduct_year_month = date('Ym',strtotime($val[4]));
            $data = [];
            $data = [
                'deduct_year_month' =>      $deduct_year_month,//专项扣除月份
                'children_education' =>     empty($val[12])? 0.00 : $val[12],//累计子女教育
                'continuing_education' =>   empty($val[13])? 0.00 : $val[13],//累计继续教育
                'house_loans' =>            empty($val[14])? 0.00 : $val[14],//累计住房贷款利息
                'house_rent' =>             empty($val[15])? 0.00 : $val[15],//累计住房租金
                'support_elderly' =>        empty($val[16])? 0.00 : $val[16],//累计赡养老人
                'enterprise_annuity' =>     empty($val[17])? 0.00 : $val[17],//累计企业年金
                'health_insurance' =>       empty($val[18])? 0.00 : $val[18],//累计商业健康保险
                'deferred_yl_insurance' =>  empty($val[19])? 0.00 : $val[19],//累计税延养老保险
                'other_deduct' =>           $val[20] + $val[21],//累计其他
                'total_deduct' =>           $val[12] + $val[13] + $val[14] + $val[15] + $val[16] + $val[17] + $val[18] + $val[19] + $val[20] + $val[21],//累计扣除合计
            ];
            $count = count($personal);
            if($count == 1){
                $where2['employee_id'] = array('eq',$personal[0]['employee_id']);
                $where2['deduct_year_month'] = array('eq',$deduct_year_month);
                $result = $salary_sixdeduct
                                ->field('id')
                                ->where($where2)
                                ->find();
                if(empty($result['id'])){
                    $data['employee_id'] = $personal[0]['employee_id'];
                    $data['time'] = $success_time;
                    $sixdeduct_data[] = $data;
                }else{
                    $data['time'] = $success_time;
                    $save_sixdeduct = $salary_sixdeduct->where('id=' . $result['id'])->save($data);
                }
                if(!in_array($personal[0]['corporate_id'],$corporate)){
                    $corporate[] = $personal[0]['corporate_id'];
                }
            }else{
                $where2['deduct_year_month'] = array('eq',$deduct_year_month);
                foreach($personal as $ky=>$vl){
                    $where2['employee_id'] = array('eq',$vl['employee_id']);
                    $result = $salary_sixdeduct
                                ->field('id')
                                ->where($where2)
                                ->find();
                    if(empty($result['id'])){
                        $data['employee_id'] = $vl['employee_id'];
                        $data['time'] = $success_time;
                        $sixdeduct_data[] = $data;
                    }else{
                        $data['employee_id'] = $vl['employee_id'];
                        $data['time'] = $success_time;
                        $save_sixdeduct = $salary_sixdeduct->where('id=' . $result['id'])->save($data);
                    }
                    if(!in_array($vl['corporate_id'],$corporate)){
                        $corporate[] = $vl['corporate_id'];
                    }
                }
            }
        }
        $error_count = count($error);
        $record_count = count($record);
        $failure_count = $record_count - $error_count;
        $str = '详情:导入' . $record_count . ',成功' . $failure_count . '人,失败' . $error_count . '人';
        foreach($error as $val){
            $str.="<p>" . $val . "</p>";
        }
        if(!empty($sixdeduct_data)){
            $result_sixdeduct_data = $salary_sixdeduct->addAll($sixdeduct_data);
            if($result_sixdeduct_data){
                $salary_sixdeduct->commit();
                foreach($corporate as $k=>$v){
                    $corporate_data[] = [
                        'corporate_id' => $v,
                        'time' => $success_time,
                        'operator' => $_COOKIE['username']
                    ];
                }
                //print_r($corporate_data);die();
                //添加导入专项扣除公司记录
                $result_log = M('salary_sixdeduct_log')->addAll($corporate_data);
                if($error_count == 0){
                    return Response::show(200,'专项附加扣除导入成功');
                }else{
                    return Response::show(300,$str);
                }
            }else{
                $salary_sixdeduct->rollback();
                return Response::show(300,$str);
            }
        }else{
            $salary_sixdeduct->commit();
            if($error_count == 0){
                return Response::show(200,'专项附加扣除导入成功');
            }else{
                return Response::show(300,$str);
            }
        }
    }
    public function shop_export_data(){
        $company = I('company','');
        $name = I('name');
        $start = I('start1');
        $end = I('end');
        $month = I('month');
        if(!empty($company)){
            $where['d.corporate_name'] = array('like',"%$company%");
        }
        if(!empty($name)){
            $where['_string'] = "c.name like'$name%' OR c.id_no='$name'";
        }
        if(!empty($start)&&!empty($end)){
            if($start==$end){
                $where['a.time'] = array('like',"%".$start."%");
            }else{
                $start_time = $start .' 00:00:00';
                $end_time = $end.' 23:59:59';
                $where['a.time'] = array('between',array($start_time,$end_time));
            }
        }else{
            if(empty($month)){
                $day = date('Y-m');
                $where['a.time'] = array('like',"%$day%");
            }
        }      
        if(!empty($month)){
            $where['a.deduct_year_month'] = array('eq',date('Ym',strtotime($month . '-01')));
        }
        $data = [
            ['公司名称','申报月份','姓名','身份证号','数据更新时间','累计子女教育','累计继续教育','累计住房贷款利息','累计住房租金','累计赡养老人','累计企业年金','累计商业健康保险','累计税延养老保险','累计其他','累计扣除合计','操作人'],
        ];
        $result = M('salary_sixdeduct')
                    ->alias('a')
                    ->field('a.*,c.name,c.id_no,d.corporate_name,e.operator')
                    ->join('left join t_employee as b on a.employee_id = b.employee_id')
                    ->join('left join t_personal as c on b.personal_id = c.personal_id')
                    ->join('left join t_corporate as d on b.corporate_id = d.corporate_id')
                    ->join('left join dsy_salary_sixdeduct_log as e on(a.time = e.time and d.corporate_id = e.corporate_id)')
                    ->where($where)
                    ->order('a.time desc,a.id desc')
                    ->select();
        $num = 1;
        foreach($result as $key=>$val){
            $arr = [
                'corporate_name' =>         $val['corporate_name'],
                'deduct_year_month'=>       date('Y-m',strtotime($val['deduct_year_month'] . 15)),
                'name' =>                   $val['name'],
                'id_no'=>                   $val['id_no'],
                'time' =>                   $val['time'],
                'children_education' =>     $val['children_education'],
                'continuing_education' =>   $val['continuing_education'],
                'house_loans' =>            $val['house_loans'],
                'house_rent' =>             $val['house_rent'],
                'support_elderly' =>        $val['support_elderly'],
                'enterprise_annuity' =>     $val['enterprise_annuity'],
                'health_insurance' =>       $val['health_insurance'],
                'deferred_yl_insurance' =>  $val['deferred_yl_insurance'],
                'other_deduct' =>           $val['other_deduct'],
                'total_deduct' =>           $val['total_deduct'],
                'wuman'=>                   $val['operator']
            ];
            $arr['id_no'] = "\t" . $val['id_no'] . "\t";
            $data[$num++] = array_values($arr);
        }
        $title = '专项附加扣除';
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
        $excel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $excel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $excel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('K')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('L')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('N')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('O')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('P')->setWidth(15);
        $excel->getActiveSheet()->getStyle('A2:P2')->getFont()->setBold(true);
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
        $num += 2;
        $excel->getActiveSheet()->getStyle('A1:P' . $num)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
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

    public function card_index(){
        $this->display();
    }
    public function card_data(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        $start_time = I('StartTime');
        $end_time = I('EndTime');

        if(!empty($name)){
            $where['name'] = array('like',"%$name%");
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['create_time'] = array('between',array($start_time,$end_time));
            }
        }
        $where['bank_name'] = array('like',"%二类卡%");
        $result = M('project_user_bank','t_')
                    ->field('project_user_bank_id as id,create_time,bank_name,name,identity_code,bank_mobile,bank_code')
                    ->where($where)
                    ->page($page,$limit)
                    ->select();
        $num = M('t_project_user_bank')
                    ->where($where)
                    ->count();
        return Response::mjson($result,$num);
    }
}
