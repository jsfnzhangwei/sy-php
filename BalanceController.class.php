<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/15 0015
 * Time: 上午 11:53
 * 商家结算平台端
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;
use Think\Exception;

class BalanceController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 申请页面
    **/
    public function index(){
        $this->display();
    }



    /**
     * 申请结算页数据
    **/
    public function index_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;

        $cname = I('sname','');
        if(!empty($cname)){
            $where['b.name'] = array('like',"$cname%");
        }
        $where['a.status'] = array('eq',1);
        $model = M('mall_balance','dsy_');
        $info = $model
            ->join('as a left join dsy_mall_shops as b on a.sid = b.id')
            ->where($where)
            ->field('a.id,a.btime,a.money,b.name,a.time,b.server_coast,a.order_money')
            ->page($page,$limit)
            ->order('a.id desc')
            ->select();
        $count = $model
            ->join('as a left join dsy_mall_shops as b on a.sid = b.id')
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                $info[$key]['time'] = date('Y-m-d H:i:s',$value['time']);
                $info[$key]['server_coast'] = ($value['server_coast']*100).'%';
            }
        }
        return Response::mjson($info,$count);

    }


    /**
     * 结算申请订单详情页面
    **/
    public function add_apply_index(){
        $id = I('id');
        $this->assign('id',$id);
        $this->display();
    }


    /**
     * 结算申请订单详情
    **/
    public function order_detail(){
        $id = I('id');
        $order_info = M('mall_balance','dsy_')->find($id);
        $orderids = $order_info['mall_ids'];
        $where['id'] = array('in',$orderids);
        $info = M('mall_order','dsy_')->where($where)->order('id desc')->select();
        $this->ajaxReturn($info);
    }


    /**
     * 导出结算订单信息
    **/
    public function output(){
        $id = I('id');
        $mall_info = M('mall_balance','dsy_')->find($id);
        $sid = $mall_info['sid'];
        $where_s['id'] = array('eq',$sid);
        $sinfo = M('mall_shops','dsy_')->where($where_s)->find();
        $sname = $sinfo['name'];
        $mall_ids = $mall_info['mall_ids'];
        $where_ids['id'] = array('in',$mall_ids);
        $info = M('mall_order','dsy_')
            ->where($where_ids)
            ->field('id,ordernum,price,time,uptime')
            ->order('id desc')
            ->select();
        if(!empty($info)){
            foreach($info as $key=>$value){
                $info[$key]['uptime'] = date('Y-m-d H:i:s',$value['uptime']);
            }
        }
        $money['money'] = $mall_info['money'];
        $money['server_coast'] = $sinfo['server_coast']*100;
        if(!empty($info)){
            $xlsCell = array(
                array('id', '订单序号'),
                array('ordernum', '订单编号'),
                array('price', '订单金额'),
                array('time', '下单时间'),
                array('uptime', '收货时间'),
            );

            $xlsName = $sname.'截止'.$mall_info['btime'].'月份结算申请';
            $field = null;
            foreach ($xlsCell as $key => $value) {
                if($key == 0){
                    $field = $value[0];
                }else{
                    $field .= "," . $value[0];
                }
            }
            $one = exportExcel($xlsName,$xlsCell,$info,$money);
        }
    }


    /**
     * 同意结算
    **/
    public function allow(){
        $ids = I('ids');
        $id = $ids[0];
        $model = M('mall_balance','dsy_');
        $model->startTrans();
        $where['id'] = array('eq',$id);
        $data['status'] = 2;
        $save = $model->where($where)->save($data);

        $mall_ids = $model->find($id);
        $where_ids['id'] = array('in',$mall_ids['mall_ids']);
        $save_data['is_balance'] = 2;
        //添加操作日志
        $admin_log = '结算申请同意，结算月份:' . $mall_ids['btime'] . '，结算金额:' . $mall_ids['money'];
        $save2 = M('mall_order','dsy_')->where($where_ids)->save($save_data);
        if($save != false && $save2 != false){
            $model->commit();
            admin_log($admin_log, 1, 'dsy_mall_balance:' . $id);
            return Response::show(200,'操作成功');
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_mall_balance:' . $id);
            return Response::show(400,'操作失败');
        }
    }





    /**
     * 已经结算的申请
    **/
    public function already_index(){
        $this->display();
    }


    /**
     * 已结算订单列表页数据
    **/
    public function already_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $cname = I('sname','');

        if(!empty($cname)){
            $where['b.name'] = array('like',"$cname%");
        }
        $where['a.status'] = array('eq',2);
        $model = M('mall_balance','dsy_');
        $info = $model
            ->join('as a left join dsy_mall_shops as b on a.sid = b.id')
            ->where($where)
            ->field('a.id,a.btime,a.money,b.name,a.time,a.sid,b.server_coast,a.order_money')
            ->page($page,$limit)
            ->order('a.id desc')
            ->select();
        $count = $model
            ->join('as a left join dsy_mall_shops as b on a.sid = b.id')
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                $info[$key]['time'] = date('Y-m-d H:i:s',$value['time']);
                $info[$key]['server_coast'] = ($value['server_coast']*100).'%';
            }
        }
        return Response::mjson($info,$count);




    }


    //会员购买订单量统计页
    public function userOrder()
    {
        $this->display('user_order_index');
    }

    //会员购买订单量统计列表
    public function userOrderList()
    {
        //获取参数
        $pageIndex = I('pageIndex', 0);
        $pageIndex = max($pageIndex, 0);
        $limit = !empty($limit) ? $limit : 10;
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');

        //条件
        $m_o_where = [];
        $m_o_where['mo.`order_type`'] = ['in', '1,2,3,4,5'];
        $m_o_where['mo.`status`'] = ['in', '2,4,5,7'];
        if (!empty($startDate)) {
            $m_o_where['mo.`time`'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $m_o_where['mo.`time`'][] = ['elt', $endDate . ' 23:59:59'];
        }
        //获取列表数据
        $arr = M('user', 't_')
            ->alias('u')
            ->join('LEFT JOIN `dsy_mall_order` AS mo ON u.`user_id`=mo.`uid`')
            ->where($m_o_where)
            ->group('mo.`uid`')
            ->field('MAX(mo.id) mid,u.`user_id`,u.`user_name`,COUNT(1) as num')
            ->order('num desc,mid DESC')
            ->select();
        $count = count($arr);
        $list = [];
        if ($count > 0) {
            $list = array_slice($arr, $pageIndex * $limit, $limit);
            foreach ($list as $k => $v) {
                $name = M('personal', 't_')->where('`user_id`=' . intval($v['user_id']))->getField('name');
                $list[$k]['name'] = empty($name) ? '游客' : $name;
            }
        }
        return Response::mjson($list, $count);
    }

    //会员购买商品量统计页
    public function userGoods()
    {
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $uid = I('uid', 0);
        $this->assign('start', $startDate);
        $this->assign('end', $endDate);
        $this->assign('uid', $uid);
        $this->assign('user_name', I('p1', ''));
        $this->assign('name', I('p2', ''));
        $this->assign('num', I('p3', ''));
        $this->display('user_goods_index');
    }

    //会员购买商品量统计列表
    public function userGoodsList()
    {
        //获取参数
        $pageIndex = I('pageIndex', 0);
        $pageIndex = max($pageIndex, 0);
        $limit = !empty($limit) ? $limit : 10;
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $uid = I('uid', 0);

        //条件
        $m_o_where = [];
        $m_o_where['mo.`order_type`'] = ['in', '1,2,3,4,5'];
        $m_o_where['mo.`status`'] = ['in', '2,4,5,7'];
        if (!empty($startDate)) {
            $m_o_where['mo.`time`'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $m_o_where['mo.`time`'][] = ['elt', $endDate . ' 23:59:59'];
        }
        $m_o_where['mo.`uid`'] = $uid;
        //获取列表数据
        $arr = M('mall_order_specifications')
            ->alias('mos')
            ->join('LEFT JOIN `dsy_mall_order` AS mo ON mos.`ordernum`=mo.`ordernum`')
            ->where($m_o_where)
            ->group('mos.`specv`,mos.`pid`')
            ->field('mos.`ordernum`,mos.`specv`,mos.`specifications` as spec,mos.`pid`,mos.`pro_skuid` skuid,mos.`pro_name` name,sum(mos.`num`) as num')
            ->order('num desc')
            ->select();
        $count = count($arr);
        $list = [];
        if ($count > 0) {
            $list = array_slice($arr, $pageIndex * $limit, $limit);
        }
        return Response::mjson($list, $count);
    }

}