<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/15 0015
 * Time: 下午 2:20
 */
namespace Admin\Controller;
use Admin\Model\GiftCardModel;
use Org\Util\Response;
use Think\Controller;

class GiftCardController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 苏鹰礼品卡页面
     **/
    public function index(){
        $username = $_COOKIE['username'];
        $state = 0;//不显示导出按钮 
        if($username == 'admin'){
            $state = 1;
        }
        $this->assign('state',$state);
        $this->display('cardlist');
    }

    /**
     * 苏鹰礼品卡列表
     **/
    public function cardlistdata()
    {
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $name = trim(I('name',''));

        $model = M('gift_card','dsy_');
        if(!empty($name) || $name == 0){
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
        $cardModel = new \Common\Model\GiftCardDetailModel();
        $cardModel->setExpire();

        foreach($info as $key=>$value)
        {
            $statusCount = M('gift_card_detail')->where(['pid' => $value['id'], 'status' => 1])->count();
            if ($statusCount > 0) {
                $newStatus = 1;
            } else {
                $newStatus = 2;
            }
            if ($newStatus != $value['status']) {
                $model->where(['id' => $value['id']])->setField('status', $newStatus);
                $info[$key]['status'] = $newStatus;
            }
            if(!empty($value['start_time']) && !empty($value['end_time']))
            {
                $info[$key]['validity'] = $value['start_time'].'至'.$value['end_time'];
            }
            $info[$key]['createtime'] = date('Y-m-d H:i:s', $value['createtime']);
            $showBtn = 1;
            if ($value['start_time'] > YMD || $value['end_time'] < YMD) {
                $showBtn = 0;
            }
            if ($statusCount > 0) {
                $showBtn = 1;
            }
            $info[$key]['showBtn'] = $showBtn;
        }
        return Response::mjson($info,$num);

    }

    //苏鹰礼品卡详情
    public function giftcard_detail_index(){
        $id = I('id');
        $this->assign('id',$id);
        $this->display();
    }

    public function detail(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $pid = I('id');
        $num = trim(I('num',''));
        $where['pid'] = array('eq',$pid);
        if(!empty($num)){
            $where['card_num'] = array('like',"%$num%");
        }
        $info = M('gift_card_detail','dsy_')
            ->field('id,card_num,amount,balance,status,update_time,uid,start_date,end_date,expire_card')
            ->where($where)
            ->page($page,$limit)
            ->select();
        $count = M('gift_card_detail','dsy_')
            ->where($where)
            ->count();
        foreach ($info as $k => $v) {
            $info[$k]['expire_card'] = $v['expire_card'] > 0 ? $v['expire_card'] : '--';
            $showBtn = 1;
            if ($v['start_date'] > YMD || $v['end_date'] < YMD) {
                $showBtn = 0;
            }
            $info[$k]['showBtn'] = $showBtn;
        }

        $model = M('gift_card_detail');
        $total_money = round($model->where($where)->sum('`amount`'), 2);
        $rest_money = round($model->where($where)->sum('`balance`'), 2);
        $expire_money = round($model->where($where)->sum('expire_card'), 2);
        $extra_data = [
            'close' => (int)$model->where($where)->where(['status' => 2])->count(),
            'open' => (int)$model->where($where)->where(['status' => 1])->count(),
            'total_money' => $total_money,
            'rest_money' => $rest_money,
            'expire_money' => $expire_money,
            'use_money' => round($total_money - $rest_money - $expire_money, 2),
        ];
        return Response::mjson($info,$count, $extra_data);

    }




    /***
     * 启用礼品卡
     **/
    public function open_package()
    {
        $id = I('ids','');
        $id = $id[0];
        if(empty($id)){
            return Response::show(300,'缺少参数');
        }
        $data['status'] = 1;
        $where['id'] = array('eq',$id);
        //判断是否能启用
        $info = M('gift_card', 'dsy_')->where($where)->field('name,end_time')->find();
        $check = $info['end_time'];
        $now = date('Y-m-d');
        if($check<$now){
            return Response::show(400,'该活动已过期，无法再次启用');
        }
        $del = M('gift_card','dsy_')->where($where)->save($data);
        $detailWhere = [
            'balance'=>['neq',0],
            'pid'=>$id,
        ];
        //添加操作日志
        $admin_log = '启用礼品卡:' . $info['name'];
        $del2 = M('gift_card_detail','dsy_')->where($detailWhere)->save($data);
        if($del !== false && $del2 != false){
            admin_log($admin_log, 1, 'dsy_gift_card:' . $id);
            return Response::show(200,'启用成功');
        }else{
            admin_log($admin_log, 0, 'dsy_gift_card:' . $id);
            return Response::show(400,'启用失败');
        }
    }
    /**
     * 禁用礼品卡
     **/
    public function del_package()
    {
        $id = I('ids','');
        $id = $id[0];
        if(empty($id)){
            return Response::show(300,'缺少参数');
        }
        $model = M('gift_card','dsy_');
        $model->startTrans();
        $data['status'] = 2;
        $where['id'] = array('eq',$id);
        $info = $model->where($where)->field('name')->find();
        $del = $model->where($where)->save($data);
        $where1['pid'] = array('eq',$id);
        //添加操作日志
        $admin_log = '禁用礼品卡:' . $info['name'];
        $del2 = M('gift_card_detail','dsy_')->where($where1)->save($data);
        if($del !== false && $del2 !== false){
            $model->commit();
            admin_log($admin_log, 1, 'dsy_gift_card:' . $id);
            return Response::show(200,'禁用成功');
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_gift_card:' . $id);
            return Response::show(400,'禁用失败');
        }
    }

    /**
     * 新增礼品卡页面
     **/
    public function addcardpage()
    {
        $this->display('addcard');
    }
    /**
     * 新增礼品卡
     **/
    public function addcard()
    {
        $name = trim(I('name',''));
        $batch_num = trim(I('batch_num',''));
        $num = trim(I('num',''));
        $amount = trim(I('amount',''));
        $start_time = date('Y-m-d', time());
        $end_time = trim(I('end',''));
        if(empty($name)||empty($batch_num)||empty($num)||empty($amount)||empty($start_time)||empty($end_time)){
            return Response::show(300,'请填写完整');
        }
        $match = "/^[A-Z]{6}$/";
        $result = preg_match($match,$batch_num);
        if($result==false||is_numeric($num)==false||is_numeric($amount)==false||$amount<=0||$num<=0){
            return Response::show(400,'请检查卡批次格式、卡金额、卡数量输入是否正确');
        }
        $day = date('Y-m-d');
        if($end_time<$start_time || $start_time<$day){
            return Response::show(301,'过期时间小于当前时间');
        }

        $data['name'] = $name;
        $data['batch_num'] = $batch_num;
        $data['num'] = $num;
        $data['amount'] = $amount;
        $data['start_time'] = $start_time;
        $data['end_time'] = $end_time;
        $data['createtime'] = time();

        $model = new GiftCardModel();
        $info = $model->create_card($data);
        if($info === true ){
            return Response::show(200,'添加成功');
        }else{
            return Response::show(400,$info);
        }
    }

    public function output(){
        $id = I('id','');
        if(empty($id)){
            return false;
        }
        $where['pid'] = array('eq',$id);
        $data = M('gift_card_detail','dsy_')
            ->where($where)
            ->field('id,card_num,card_pwd')
            ->select();
        $xlsCell = array(
            array('card_num', '卡号'),
            array('card_pwd', '卡密'),
        );
        $xlsName = '礼品卡导出';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$data);


    }


    /***
     * 启用礼品卡下某卡号
     **/
    public function open_gift_card()
    {
        $id = I('ids', '');
        $pid = I('pid', '');
        $id = array_unique(array_filter($id));
        if (empty($id) || empty($pid)) {
            return Response::show(300, '缺少参数');
        }
        $id_str = implode(',', $id);

        $infos = M('gift_card_detail')->where(['status' => 2, 'id' => ['in', $id_str]])->getField('card_num', true);
        //添加操作日志
        $admin_log = '启用礼品卡单卡:' . implode(',', $infos);
        $data = [];
        $data['status'] = 1;
        $data['update_time'] = time();
        $res = M('gift_card_detail')->where(['status' => 2, 'id' => ['in', $id_str]])->save($data);
        if ($res !== false) {
            //子卡有一个启用则父卡启用
            if (intval(M('gift_card_detail')->where(['status' => 1, 'pid' => $pid])->count()) > 0) {
                M('gift_card')->where(['status' => 2, 'id' => $pid])->setField('status', 1);
            }
            admin_log($admin_log, 1, 'dsy_gift_card_detail:' . $id_str);
            return Response::show(200, '启用成功');
        } else {
            admin_log($admin_log, 0, 'dsy_gift_card_detail:' . $id_str);
            return Response::show(400, '启用失败');
        }
    }

    /**
     * 禁用礼品卡下某卡号
     **/
    public function del_gift_card()
    {
        $id = I('ids', '');
        $pid = I('pid', '');
        $id = array_unique(array_filter($id));
        if (empty($id) || empty($pid)) {
            return Response::show(300, '缺少参数');
        }
        $id_str = implode(',', $id);

        $infos = M('gift_card_detail')->where(['status' => 1, 'id' => ['in', $id_str]])->getField('card_num', true);
        //添加操作日志
        $admin_log = '禁用礼品卡单卡:' . implode(',', $infos);
        $data = [];
        $data['status'] = 2;
        $data['update_time'] = time();
        $res = M('gift_card_detail')->where(['status' => 1, 'id' => ['in', $id_str]])->save($data);
        if ($res !== false) {
            //子卡全部禁用则父卡禁用
            if (intval(M('gift_card_detail')->where(['status' => 1, 'pid' => $pid])->count()) == 0) {
                M('gift_card')->where(['status' => 1, 'id' => $pid])->setField('status', 2);
            }
            admin_log($admin_log, 1, 'dsy_gift_card_detail:' . $id_str);
            return Response::show(200, '禁用成功');
        } else {
            admin_log($admin_log, 0, 'dsy_gift_card_detail:' . $id_str);
            return Response::show(400, '禁用失败');
        }
    }

    /**
     * 查看苏鹰卡使用记录
     */
    public function cardUsePage()
    {
        $this->display('card_use_page');
    }

    /**
     * 获取苏鹰卡使用记录
     */
    public function cardUseList()
    {
        $pageIndex = I('pageIndex', '');
        $page = !empty($pageIndex) ? $pageIndex + 1 : 1;
        $limit = !empty($limit) ? $limit : 10;
        $mobile = trim(I('mobile', ''));
        $order_no = I('order_no', '');
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');

        $model = M('card_use_record');
        $userModel = M('user', 't_');

        $where = [];
        if (!empty($mobile)) {
            $userIds = $userModel->where(['user_name' => $mobile])->getField('user_id', true);
            if (empty($userIds)) {
                $userIds = [-1];
            }
            $where['uid'] = ['in', $userIds];
        }
        if (!empty($order_no)) {
            $_string = "`ordernum`='" . $order_no . "'";
            $orderNos = M('mall_order')->where(['ordernum' => $order_no])->getField('order_notpay_num', true);
            if (!empty($orderNos)) {
                $_string .= " OR `ordernum` IN('" . implode("','", $orderNos) . "')";
            }
            $where['_string'] = $_string;
        }
        if (!empty($startDate)) {
            $sTime = strtotime($startDate . ' 00:00:00');
            $where['create_time'][] = ['egt', $sTime];
        }
        if (!empty($endDate)) {
            $eTime = strtotime($endDate . ' 23:59:59');
            $where['create_time'][] = ['elt', $eTime];
        }
        $count = $model
            ->where($where)
            ->count();
        $list = [];
        if ($count > 0) {
            $list = $model
                ->where($where)
                ->page($page, $limit)
                ->order('id desc')
                ->select();
            foreach ($list as $k => $info) {
                $list[$k]['user_name'] = $userModel->where(['user_id' => $info['uid']])->getField('user_name');
                $list[$k]['create_time'] = date('Y-m-d H:i:s',$info['create_time']);
                $list[$k]['type'] = formatOrderType($info['utype']);
                $cardNum = M('gift_card_detail')
                            ->field('card_num')
                            ->where(['id' => $info['card_id']])
                            ->find();
                $list[$k]['card_num'] = $cardNum['card_num'];
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
                            $order_model = M('gift_card_of_zsk');
                            $o_where = ['id_of_zsk' => $info['ordernum']];
                            $field = '0 as order_status,0 as pay_status,`all_amount_sy` as total_amount,0.00 as receipt_amount';
                            break;
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
                                $list[$k]['coast'] = 0;
                            }
                            $order_info['total_amount'] = $pay_info['pay_money'];
                            $order_info['receipt_amount'] = $pay_info['pay_rest'];
                            break;
                        case 6|7:
                            if ($order_info['order_status'] == 2
                                || ($order_info['order_status'] == 0 && $order_info['pay_status'] != 1)) {
                                $list[$k]['coast'] = 0;
                            }
                            break;
                        default:
                            if ($order_info['order_status'] == 2
                                || ($order_info['order_status'] == 0 && $order_info['pay_status'] != 2)
                                || ($order_info['order_status'] == 1 && $order_info['refund_status'] != 0)) {
                                $list[$k]['coast'] = 0;
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
    public function cardUseDetailPage()
    {
        $id = I('id', '');
        $this->assign('id', $id);
        $this->display('card_use_detail_page');
    }

    public function cardUseDetail()
    {
        $id = I('id', '');

        $info = M('card_use_record')
            ->where(['id' => $id])
            ->find();

        if ($info['utype'] > 0) {
            $o_where = ['order_no' => $info['ordernum']];
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

    public function refundCardPage()
    {
        $this->display();
    }

    public function refundCardList()
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

        $logModel = M('log_card');
        $list = $logModel
            ->where($where)
            ->page($page, $limit)
            ->order('id desc')
            ->select();
        $count = $logModel
            ->where($where)
            ->count();

        $userModel = M('user', 't_');
        $useModel = M('card_use_record');
        $cardModel = M('gift_card_detail');
        foreach ($list as $k => $v) {
            $list[$k]['user_name'] = $userModel->where(['user_id' => $v['user_id']])->getField('user_name');
            $useInfo = $useModel->where(['id' => $v['data_id']])->field('orderNum,utype,card_id')->find();
            $list[$k]['typeName'] = formatOrderType($useInfo['utype']);
            $list[$k]['card_num'] = $cardModel->where(['id' => $useInfo['card_id']])->getField('card_num');
        }

        return Response::mjson($list, $count);
    }
    /**
     * 验证邮箱验证码查看苏鹰卡密码
     */
    public function checkCode(){
        $code = I('code');
        $id = I('id');
        $username = $_COOKIE['username'];
        $check_code = session($username . 'cardCode');
        if(empty($code)){
            return Response::show(300,'验证码不能为空');
        }
        if($code == $check_code){
            $result = M('gift_card_detail')->field('card_pwd')->where(['id'=>$id])->find();
            if($result){
                session($username . 'cardCode',null);
                return Response::show(200,$result);
            }else{
                return Response::show(300,'查询失败');
            }
        }else{
            return Response::show(300,'验证码不正确');
        }
    }
    /**
     * 获取邮箱验证码
     */
    public function email(){
        $username = $_COOKIE['username'];
        if(empty($username)){
            echo json_encode(['code'=>300, 'msg'=>'非法请求']);die();
        }
        $query_email = C('sy_email');
        $email= $query_email;//获取收件人邮箱
                 //return $email;
        $sendmail = 'hanbo159357@126.com'; //发件人邮箱
        $sendmailpswd = "XTWIILULNKUABMYO"; //客户端授权密码,而不是邮箱的登录密码，就是手机发送短信之后弹出来的一长串的密码
        $send_name = '苏鹰集团';// 设置发件人信息，如邮件格式说明中的发件人，
        $toemail = $email;//定义收件人的邮箱
        $to_name = 'sy';//设置收件人信息，如邮件格式说明中的收件人
        import('PHPMailer.PHPMailer');
        $mail = new \PHPMailer();
        $mail->isSMTP();// 使用SMTP服务
        $mail->CharSet = "utf8";// 编码格式为utf8，不设置编码的话，中文会出现乱码
        $mail->Host = "smtp.126.com";// 发送方的SMTP服务器地址
        $mail->SMTPAuth = true;// 是否使用身份验证
        $mail->Username = $sendmail;//// 发送方的
        $mail->Password = $sendmailpswd;//客户端授权密码,而不是邮箱的登录密码！
        $mail->SMTPSecure = "ssl";// 使用ssl协议方式
        $mail->Port = 465;//  qq端口465或587）
        $mail->setFrom($sendmail, $send_name);// 设置发件人信息，如邮件格式说明中的发件人，
        $mail->addAddress($toemail, $to_name);// 设置收件人信息，如邮件格式说明中的收件人，
        $mail->addReplyTo($sendmail, $send_name);// 设置回复人信息，指的是收件人收到邮件后，如果要回复，回复邮件将发送到的邮箱地址
        $mail->Subject = "登录验证";// 邮件标题

        $code=rand(100000,999999);
        session($username . 'cardCode',$code);
        //return $code."----".session("code");
        $mail->Body = "您好，本次查看苏鹰卡密码的验证码是：" . $code ."，仅供个人大管理后台查看苏鹰卡密码使用，请不要向外透露  此邮件由系统发出，请勿直接回复。本次操作人账户：" . $username;// 邮件正文
        //$mail->AltBody = "This is the plain text纯文本";// 这个是设置纯文本方式显示的正文内容，如果不支持Html方式，就会用到这个，基本无用
        if (!$mail->send()) { // 发送邮件
            echo json_encode(['code'=>300, 'msg'=>'发送失败']);
        } else {
            echo json_encode(['code'=>200, 'msg'=>'发送成功']);
        }
    }
}
