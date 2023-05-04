<?php
/**
 * Created by PhpStorm.
 * User: 86183
 * Date: 2018/6/1
 * Time: 15:56
 */

namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;
use Think\Exception;
use WechatPay\WechatAppPay;

class Export extends Controller
{
    public function _initialize()
    {
        is_logout();
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
     * 导出兑换记录
     **/
    public function output(){
        $aid = I('id','');
        if(empty($aid)){
            return Response::show('300','请选择一个公司导出');
        }
        $cinfo = M('company_activity')->find($aid);
        $model = M('company_exchange','dsy_');
        $where['aid'] = array('eq',$aid);
        $where['type'] = array('eq',$cinfo['get_type']);
        $all_info = $model
            ->where($where)
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
                if($jd_status == 1){
                    $data['jd_status'] = '';
                } elseif($jd_status == 2) {
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
                $data['user_tel'] = $name = getUserTel($uid);;
                if(!empty($pid)){
                    $pinfo = unserialize($value['setmeal']);
//                    $data['pname'] = getpackagename($pid);
                    $data['pname'] = $pinfo['name'];
//                    $all_products_name = explode(',',getPnamesByPid($pid));
                    $all_products_name = explode(',',getPnamesByPid($pinfo['pids']));
//                    $all_skuid = explode(',',getAllskuids($pid));
                    $all_skuid = explode(',',getAllskuids($pinfo['pids']));
                    foreach($all_skuid as $kk => $vv){
                        $word = $vv.'('.$all_products_name[$kk].')';
                        $one[] = $word;
                    }
                    $data['pinfo'] = implode("\r\n",$one);

                } else {
                    $data['pname'] = '';
                    $data['pinfo'] = '';
                }
                $data['id'] = $value['id'];
                if ($cinfo['get_type'] == 2) {
                    $use_money += $value['money'];
                    if($key==0){
                        $all_money = $cinfo['money'];
                    }else{
                        $all_money = $cinfo['money']-$use_money+$value['money'];
                    }
                    $data['money'] = $all_money;
                    $data['change_money'] = $value['money'];
                    $data['left_money'] = $all_money-$value['money'];
                }
                $all_data[] = $data;
            }
        }
        if($cinfo['get_type']==2){
            $xlsCell = array(
                array('aname', '活动名称'),
                array('time', '兑换时间'),
                array('user_name', '登陆号'),
                array('name', '姓名'),
                array('pname', '套餐名称'),
                array('pinfo', '商品信息'),
                array('status', '兑换状态'),
                array('jd_status', '京东状态'),
                array('money', '总兑换金额'),
                array('change_money', '兑换金额'),
                array('left_money', '剩余兑换金额'),
            );
        }elseif ($cinfo['get_type'] == 1){
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
        } elseif ($cinfo['get_type'] == 3) {
            $xlsCell = array(
                array('aname', '活动名称'),
                array('time', '兑换时间'),
                array('name', '姓名'),
                array('mobile', '手机号'),
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
     * 兑换详情列表数据
     **/
    public function exchange_detail(){
        $aid = I('aid','');
        $model = M('company_exchange','dsy_');
        //查询
        $cinfo = M('company_activity')->find($aid);
        $status = I('status','');
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        if(!empty($status)){
            if($status==1){
                $where['status'] = array('eq',$status);
            }else{
                $where['status'] = array('eq',0);
            }
        }
        $where['aid'] = array('eq',$aid);
        $where['type'] = array('eq',$cinfo['get_type']);

        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->select();
        $num = $model
            ->where($where)
            ->count();
        if(empty($info)){
            return Response::mjson($info,$num);
        }
        $use_money = 0;
        foreach($info as $key => $value){
            $name = getUserNmaeByEid($value['eid']);
            $info[$key]['name'] = $name;//姓名
            $info[$key]['mobile'] = getmobilebyeid($value['eid']);//手机号
            if($cinfo['get_type']==1){
                if($value['status']==1){
                    $info[$key]['status'] = '已经兑换';
                    $info[$key]['pids'] = getpackagename($value['pids']);
                    $info[$key]['time'] = date('Y-m-d',$value['time']);
                }else{
                    $info[$key]['status'] = '尚未兑换';
                    $info[$key]['pids'] = '尚未兑换任何套餐';
                    $info[$key]['time'] = '尚未兑换';
                }
            }else{
                $info[$key]['status'] = '已经兑换';
                $info[$key]['pids'] = getpackagename($value['pids']);
                $info[$key]['time'] = date('Y-m-d',$value['time']);
                $use_money += $value['money'];
                if($key==0){
                    $all_money = $cinfo['money'];
                }else{
                    $all_money = $cinfo['money']-$use_money+$value['money'];
                }
                $info[$key]['money'] = $all_money;//总金额
                $info[$key]['change_money'] = $value['money'];//已兑换金额
                $info[$key]['left_money'] = $all_money-$value['money'];//未兑换金额
            }

        }
        return Response::mjson($info,$num);
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
        $data1['dataIndex'] = 'name';
        $data2['dataIndex'] = 'mobile';
        $data3['dataIndex'] = 'time';
        $data1['width'] = '';
        $data2['width'] = '';
        $data3['width'] = '';
        $array[] = $data1;
        $array[] = $data2;
        $array[] = $data3;
        if ($info['get_type'] == 1 && $info['get_type'] == 2) {
            $data4['title'] = '兑换状态';
            $data5['title'] = '兑换套餐';
            $data4['dataIndex'] = 'status';
            $data5['dataIndex'] = 'pids';
            $data4['width'] = '';
            $data5['width'] = '';
            $array[] = $data4;
            $array[] = $data5;
        }
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
}