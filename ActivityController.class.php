<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/8 0008
 * Time: 上午 10:05
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;
use Think\Exception;

class ActivityController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 福利券列表页
    **/
    public function welfare_index(){

        $this->display('exchange_index');
    }

    /**
     * 福利券充值列表数据
    **/
    public function welfare_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;

        $info = M('mall_card','dsy_')
            ->page($page,$limit)
            ->order('id desc')
            ->select();

        $num =  M('mall_card','dsy_')->count();
        $model = M('mall_card_change','dsy_');
        if(!empty($info)){
            foreach($info as $key=>$value){
                //查询兑换券使用情况
                //已经兑换的人数
                $where['cid'] = array('eq',$value['id']);
                $where['is_use'] = array('eq',2);
                $al_num = $model->where($where)->count();
                $info[$key]['al_num'] = $al_num;
                //剩余人数
                $no_num = $value['num'] - $al_num;
                $info[$key]['no_num'] = $no_num;
            }
        }
        return Response::mjson($info,$num);
    }


    /**
     * 福利券添加页面
    **/
    public function welfare_add_index(){
        $this->display('activity_add');
    }

    /**
     * 添加福利券预览页面
    **/
    public function view_index(){
        $num = I('num',0);
        $start_time = I('start_time');
        $end_time = I('end_time');
        $money = I('money');
        $this->assign('start_time',$start_time);
        $this->assign('end_time',$end_time);
        $this->assign('money',$money);
        $this->assign('count',$num);
        $this->display('choice_setmeals');
    }

    /**
     * 预览页数据
    **/
    public function view_list(){
        $num = I('num',0);
        $start_time = I('start_time');
        $end_time = I('end_time');
        $money = I('money');

        $array['time'] = $start_time.'~'.$end_time;
        $array['money'] = $money;
        $card = array();
        $check_code = array();
        for($i=0;$i<$num;$i++){
            for(;;){
                $code = roundCodewelfare();
                if(in_array($code,$check_code) == false){
                    $check_code[] = $code;
                    $array['code'] = $code;
                    $card[] = $array;
                    break;
                }
            }
        }

        $data['code'] = 200;
        $data['msg'] = '';
        $data['count'] = '';
        $data['data'] =$card;
        $data['check_code'] = $check_code;
        $this->ajaxReturn($data);
    }



    /**
     * 添加福利券操作
    **/
    public function add_welfare(){
        $num = I('num','');
        $start_time = I('start1');
        $end_time = I('end');
        $money = I('money');
        $code = I('code','');
        $now = date('Y-m-d');

        if(empty($start_time)||empty($end_time)){
            return Response::show(400,'请选择完整的时间');
        }
        if($start_time<$now || $end_time<$now){
            return Response::show(400,'不能选择过去时间');
        }
        if($start_time>$end_time){
            return Response::show(400,'开始时间不能大于结束时间');
        }
        if(is_numeric($num)==false){
            return Response::show(400,'请填写正确的福利券数量');
        }
        if(empty($num)|| $num<1 || $num>2000){
            return Response::show(400,'福利券数量1~2000');
        }
        if(empty($code)){
            $check_code = array();
            for($i=0;$i<$num;$i++){
                for(;;){
                    $code = roundCodewelfare();
                    if(in_array($code,$check_code) == false){
                        $check_code[] = $code;
                        break;
                    }
                }
            }
            $code_array = $check_code;
        }else{
            $code = explode(',',$code);
            $code_array = $code;
        }


        if(count($code_array) != $num){
            return Response::show(400,'兑换券数量错误');
        }
        $model = M('mall_card');
        $model->startTrans();
        $data['num'] = $num;
        $data['start_time'] = $start_time;
        $data['end_time'] = $end_time;
        $data['time'] = NOW;
        $data['money'] = $money;
        $add_one = $model->add($data);
        //添加到详情表
        $datalist = array();
        $data1['start_time'] = $start_time;
        $data1['end_time'] = $end_time;
        $data1['cid'] = $add_one;
        $data1['money'] = $money;
        $data1['time'] = NOW;
        foreach($code_array as $key=>$value){
            $data1['code'] = $value;
            $datalist[] = $data1;
        }
        $add_two = M('mall_card_change')->addAll($datalist);
        //添加操作日志
        $admin_log = '新增福利券，数量:' . $num . '，金额:' . $money;
        if($add_one != false && $add_two != false){
            $model->commit();
            admin_log($admin_log, 1, 'dsy_mall_card:' . $add_one);
            return Response::show(200,'添加成功');
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_mall_card');
            return Response::show(400,'添加失败');
        }
    }


    /**
     * 福利券兑换明细页面
    **/
    public function exchange_detail_index(){
        $id = I('id');
        $this->assign('id',$id);
        $this->display('exchange_detail');
    }

    /**
     * 福利券兑换明细数据
    **/
    public function exchange_detail_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $id = I('id');
        $where['cid'] = array('eq',$id);
        $where['id_del'] = array('eq',1);
        $info = M('mall_card_change')
            ->where($where)
            ->page($page,$limit)
            ->select();
        $count = M('mall_card_change')
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                $info[$key]['time'] = $value['start_time'].'~'.$value['end_time'];
            }
        }
        return Response::mjson($info,$count);
    }


    /**
     * 禁用福利券
    **/
    public function abdon(){
        $ids = I('ids');
        $id = $ids[0];
        $model = M('mall_card_change');
        $check = $model->find($id);
        if($check['is_use']==2){
            return Response::show(400,'已经兑换的不能禁用');
        }
        $where['id'] = array('eq',$id);
        $data['is_abdon'] = 2;
        $save = $model->where($where)->save($data);
        //添加操作日志
        $admin_log = '禁用福利券:' . M('mall_card_change')->where($where)->getField('code');
        if($save != false){
            admin_log($admin_log, 1, 'dsy_mall_card_change:' . $id);
            return Response::show(200,'禁用成功');
        }else{
            admin_log($admin_log, 0, 'dsy_mall_card_change:' . $id);
            return Response::show(400,'禁用失败');
        }

    }



    /**
     * 删除福利券
    **/
    public function del(){
        $ids = I('ids');
        $id = $ids[0];
        $model = M('mall_card_change');
        $check = $model->find($id);
        if($check['is_use']==2){
            return Response::show(400,'已经兑换的不能删除');
        }
        $where['id'] = array('eq',$id);
        $data['id_del'] = 2;
        $save = $model->where($where)->save($data);
        //添加操作日志
        $admin_log = '删除福利券:' . M('mall_card_change')->where($where)->getField('code');
        if($save != false){
            admin_log($admin_log, 1, 'dsy_mall_card_change:' . $id);
            return Response::show(200,'删除成功');
        }else{
            admin_log($admin_log, 0, 'dsy_mall_card_change:' . $id);
            return Response::show(400,'删除失败');
        }
    }


    /**
     * 启用福利券
    **/
    public function open(){
        $ids = I('ids');
        $id = $ids[0];
        $model = M('mall_card_change');
        $where['id'] = array('eq',$id);
        $check = $model->find($id);
        if($check['is_use']==2){
            return Response::show(400,'已经兑换的不能启用');
        }
        $data['is_abdon'] = 1;
        $save = $model->where($where)->save($data);
        //添加操作日志
        $admin_log = '启用福利券:' . M('mall_card_change')->where($where)->getField('code');
        if($save != false){
            admin_log($admin_log, 1, 'dsy_mall_card_change:' . $id);
            return Response::show(200,'启用成功');
        }else{
            admin_log($admin_log, 0, 'dsy_mall_card_change:' . $id);
            return Response::show(400,'启用失败');
        }
    }


    /**
     * 导出本次充值记录
    **/
    public function output(){
        $id = I('id');
        $where['cid'] = array('eq',$id);
        $where['id_del'] = array('eq',1);
        $info = M('mall_card_change')
            ->where($where)
            ->select();

        if(!empty($info)){
            foreach($info as $key=>$value){
                if($value['is_abdon']==1){
                    $info[$key]['is_abdon'] = '未被禁用';
                }else{
                    $info[$key]['is_abdon'] = '已禁用';
                }
                if($value['is_use']==1){
                    $info[$key]['is_use'] = '未兑换';
                }else{
                    $info[$key]['is_use'] = '已兑换';
                }
                $info[$key]['time'] = $value['start_time'].'~'.$value['end_time'];
            }
        }
        $xlsCell = array(
            array('id', '序号'),
            array('code', '福利券号'),
            array('money', '福利额度'),
            array('time', '兑换时间'),
            array('is_use', '兑换状态'),
            array('is_abdon', '是否被禁用'),
        );

        $xlsName = '兑换结果导出';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$info);
    }



    /**
     * 福利券兑换列表（都是已经兑换的）
    **/
    public function already_exchange_index(){
        $this->display();
    }

    /**
     * 福利券兑换列表数据
    **/
    public function already_exchange_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $card = I('card','');

        $model = M('mall_card_change');
        if(!empty($card)){
            $where['code'] = array('eq',$card);
        }
        $where['id_del'] = array('eq',1);
        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->order('is_use desc')
            ->select();
        $count = $model
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                if(!empty($value['uid'])){
                    $where_u['user_id'] = array('eq',$value['uid']);
                    $user_name = M('user','t_')->where($where_u)->find();
                    $info[$key]['user_name'] = $user_name['user_name'];
                    $where_p['user_id']  = array('eq',$value['uid']);
                    $mobile = M('personal','t_')->where($where_p)->find();
                    $info[$key]['mobile'] = $mobile['mobile'];
                }else{
                    $info[$key]['user_name'] = '';
                    $info[$key]['mobile'] = '';
                }
            }
        }
        return Response::mjson($info,$count);
    }



    /**
     * 导出所有兑换记录
    **/
    public function output_all(){
        $model = M('mall_card_change');
        $where['id_del'] = array('eq',1);
        $info = $model
            ->where($where)
            ->select();
        if(!empty($info)){
            foreach($info as $key=>$value){
                if(!empty($value['uid'])){
                    $where_u['user_id'] = array('eq',$value['uid']);
                    $user_name = M('user','t_')->where($where_u)->find();
                    $info[$key]['user_name'] = $user_name['user_name'];
                    $where_p['user_id']  = array('eq',$value['uid']);
                    $mobile = M('personal','t_')->where($where_p)->find();
                    $info[$key]['mobile'] = $mobile['mobile'];
                }else{
                    $info[$key]['user_name'] = '';
                    $info[$key]['mobile'] = '';
                }
                $info[$key]['time'] = $value['start_time'].'~'.$value['end_time'];
                if($value['is_use']==1){
                    $info[$key]['is_use'] = '未兑换';
                }else{
                    $info[$key]['is_use'] = '已兑换';
                }
            }
        }
        $xlsCell = array(
            array('id', '序号'),
            array('code', '福利券号'),
            array('money', '福利额度'),
            array('time', '兑换时间'),
            array('is_use', '兑换状态'),
            array('user_name', '兑换人账号'),
            array('mobile', '兑换人手机号'),
        );

        $xlsName = '兑换结果导出';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$info);

    }


}