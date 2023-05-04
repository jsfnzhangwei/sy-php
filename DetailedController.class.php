<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Admin\Controller;
use Admin\Model\OrderModel;
use Org\Util\Response;
use Think\Controller;
use Think\Model;

class DetailedController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 收款明细界面
     *
     */
    public function detailed_receivables_index(){
        $shops = M('mall_shops')->field('id,name as sname')->select();
        $this->assign('shops',$shops);
        $this->display('detailed_receivables_index');
    }

    /**
     * 收款明细数据
     *
     */
    public function detailed_receivables_info(){
        $pageIndex = I('pageIndex',0);
        $page = $pageIndex;
        $limit = 10;
        $ordernum = I('ordernum','');
        $cordernum = I('cordernum','');
        $shops = I('shops','');
        $start = I('start1','');
        $end = I('end','');
        if(!empty($ordernum)){
//            $where['a.ordernum'] = array('eq',$ordernum);
            $where[] = " a.ordernum = $ordernum ";
        }if(!empty($shops)){
//            $where['a.sid'] = array('eq',$shops);
            $where[] = " a.sid = $shops ";
        }if(!empty($start) && !empty($end)){
//            $where['a.time'] = array('between',"$start,$end");
            if($start == $end){
                $where[] = " a.time like '%$start%' ";
            }else{
                $where[] = " a.time between '$start' and '$end' ";
            }

        }
        $where[] = " a.status != 1 ";
        if(!empty($cordernum)){
            $where[] = " a.order_notpay_num = $cordernum ";
        }
        if(!empty($where)){
            $where = 'where '.implode('and',$where);
        }

        $offset = $page*$limit;//偏移量

        $Model = new \Think\Model();
        $sql = "
        SELECT
            a.id,
            a.ordernum,
            b. NAME AS NAME,
            c. NAME AS sname,
            a.payprice AS price,
            a.paytype,
            a.time,
            a.checkstatus,
            a.order_notpay_num,
            a.status
        FROM
            `dsy_mall_order` AS a
        LEFT JOIN t_personal AS b ON a.uid = b.user_id
        LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id $where
        LIMIT $offset,$limit
        ";
        $result = $Model->query($sql);

        $num = $Model->query(" SELECT COUNT(*) FROM `dsy_mall_order` as a LEFT JOIN t_personal AS b ON a.uid = b.user_id LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id $where");
        $num = $num[0]["count(*)"];
//        var_dump($num);exit;
//        $order = M('mall_order');
//        $result = $order
//            ->join('as a left join dsy_user as b  on a.uid = b.id')
//            ->join('left join dsy_mall_shops as c  on a.sid = c.id')
//            ->page($page,$limit)
//            ->where($where)
//            ->field('a.id,a.ordernum,b.realname as name,c.name as sname,a.payprice as price,a.time,a.checkstatus')
//            ->select();
//        $num = $order
//            ->join('as a left join dsy_user as b  on a.uid = b.id')
//            ->join('left join dsy_mall_shops as c  on a.sid = c.id')
//            ->where($where)
//            ->count() ;
        return Response::mjson($result,$num);

    }

    /**
     * 退款明细界面
     *
     */
    public function detailed_refund_index(){
        $shops = M('mall_shops')->field('id,name as sname')->select();
        $this->assign('shops',$shops);
        $this->display('detailed_refund_index');
    }

    /**
     * 退款明细数据
     *
     */
    public function detailed_refund_info(){
//        $pageIndex = I('pageIndex',0);
//        $page = $pageIndex;
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = 10;
        $csnum = I('csnum','');
        $cordernum = I('cordernum','');
        $shops = I('shops','');
        $start = I('start1','');
        $end = I('end','');
        if(!empty($csnum)){
//            $where[] = " a.csnum = $csnum ";
            $where['a.csnum'] = array('eq',$csnum);
        }if(!empty($shops)){
//            $where[] = " a.sid = $shops ";
            $where['a.sid'] = array('eq',$shops);
        }if(!empty($start) && !empty($end)){
//            $where[] = " a.returntime between '$start' and '$end' ";
            if($start == $end){
//                $where[] = " a.returntime like '%$start%' ";
                $where['a.returntime'] = array('like',"%$start%");
            }else{
//                $where[] = " a.returntime between '$start' and '$end' ";
                $where['a.returntime'] = array('between',array($start,$end));
            }

        }

        if(!empty($cordernum)){
//            $where[] = " d.order_notpay_num = $cordernum ";
            $where['d.order_notpay_num'] = array('eq',$cordernum);
        }
//        $order = M('mall_order_return');
//        $where[] = " a.type in('1,3') ";
//        $where[] = " a.type = 3 or a.type=1 ";
        $where['a.type'] = array('in','1,3');
//        $where[] = " a.status = 2 ";
        $where['a.status'] = array('eq',2);


//        $where = implode('and',$where);

//        $offset = $page*$limit;//偏移量
//        $Model = new \Think\Model();
//        $result = $Model->query("
//        SELECT
//            a.id,
//            a.ordernum,
//            a.csnum,
//            a.returntime,
//            a.replytime,
//            CASE a. STATUS
//        WHEN 1 THEN
//            '申请中'
//        WHEN 2 THEN
//            '同意'
//        WHEN 3 THEN
//            '不同意'
//        END AS return_money_status,
//         a.return_money,
//         b.name AS NAME,
//         c. NAME AS sname,
//         d.paytype,
//         d.order_notpay_num
//        FROM
//            `dsy_mall_order_return` AS a
//        LEFT JOIN t_personal AS b ON a.uid = b.user_id
//        LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id
//        LEFT JOIN dsy_mall_order AS d ON a.ordernum = d.ordernum
//        WHERE $where
//        LIMIT $offset,$limit
//        ");
//
//        $num = $Model->query("
//            SELECT
//                COUNT(*)
//            FROM
//                `dsy_mall_order_return` AS a
//            LEFT JOIN t_personal AS b ON a.uid = b.user_id
//            LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id
//            LEFT JOIN dsy_mall_order AS d ON a.ordernum = d.ordernum
//            WHERE $where
//        ");
//
//        $num = $num[0]["count(*)"];


        $result = M('mall_order_return','dsy_')
            ->join('as a left join t_personal as b  on a.uid = b.user_id')
            ->join('left join dsy_mall_shops as c  on a.sid = c.id')
            ->join('left join dsy_mall_order as d  on a.ordernum = d.ordernum')
            ->page($page,$limit)
            ->where($where)
            ->field('a.id,a.ordernum,a.csnum,a.returntime,a.replytime,a.status,a.return_money,b.name as name,c.name as sname,d.paytype,d.order_notpay_num')
            ->order('a.id desc')
            ->select();
        if(!empty($result)){
            foreach ( $result as $key => $value) {
                if($value['status'] == 1){
                    $result[$key]['return_money_status'] = '申请中';
                }
                if($value['status'] == 2){
                    $result[$key]['return_money_status'] = '同意';
                }
                if($value['status'] == 3){
                    $result[$key]['return_money_status'] = '不同意';
                }
            }
        }

        $num =M('mall_order_return','dsy_')
            ->join('as a left join t_personal as b  on a.uid = b.user_id')
            ->join('left join dsy_mall_shops as c  on a.sid = c.id')
            ->join('left join dsy_mall_order as d  on a.ordernum = d.ordernum')
            ->where($where)
            ->count();
        return Response::mjson($result,$num);

    }








}
