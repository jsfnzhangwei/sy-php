<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
class WquotaController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    //福利豆消费记录界面
    public function wquota_index(){
        $Model = new \Think\Model();
        $vo = $Model->query("
            SELECT
                corporate_id AS cid,
                corporate_name AS cname
            FROM
                t_corporate
         ");
        $this->assign('vo',$vo);
        $this->display('wquota_index');
    }

    //福利豆消费记录列表数据
    public function wquota_user_lis(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $username = I('username','');
        $cid = I('cname','');
        $start = I('start1','');
        $end = I('end','');
        $where = '';
        if(!empty($username)){
            $username = trim($username);
            $where[] = " c.name like ".'"%'.$username.'%"';
        }
        if(!empty($cid)){
            $where[] = " b.corporate_id = $cid ";
        }

        $where_time = array();
        $where_time2 = array();
        $where_time3 = array();
        //接收时间
        //当月
        $moth = date('Y-m');
        //上个月
        $last_month = getOneMonthBefore();
        if(!empty($start) && !empty($end)){
            if($start == $end){
                $where_time[] = " time like '$start%' ";
                //接收时间的上个月
                $stime = substr($start,0,7);//接收之间截取到月
                $time_stamp = strtotime($stime);//转换时间戳
                $last_time = $time_stamp -1;//上个月的时间戳
                $ftime = date('Y-m',$last_time );//接收时间的上个月
                $where_time2['time'] = array('like',"$ftime%");
                $where_time3['time'] = array('like',"$stime%");
            }else{
                $where_time[] = " time  between '$start' and  '$end' ";
                //接收时间的上个月
                $sstime = substr($start,0,7);
                $stime_stamp = strtotime($sstime);
                $slast_time = $stime_stamp -1;
                $fstime = date('Y-m',$slast_time );

                $setime = substr($end,0,7);
                $etime_stamp = strtotime($setime);
                $elast_time = $etime_stamp -1;
                $etime = date('Y-m',$elast_time );

                if($sstime == $setime){
                    $where_time2['time'] = array('like',"$fstime%") ;
                    $where_time3['time'] = array('like',"$sstime%");
                }else{
                    $where_time2['time'] = array('between',array($fstime,$etime));
                    $where_time3['time'] = array('between',array("'$start'","'$end'"));
                }

            }
        }else{
            //显示当月数据（上个月的总额度、这个月的使用记录)
            $where_time[] = " time like '$moth%' ";
            $where_time2['time'] = array('like',"$last_month%");
            $where_time3['time'] = array('like',"$moth%");
        }
        $wquota = M('mall_wquota','dsy_');
        $wquota_use = M('mall_wquota_use','dsy_');
        $employee = M('employee','t_');
        $wuid = $wquota_use
            ->group('uid')
            ->where($where_time)
            ->page($page,$limit)
            ->select();
        $info = array();
        foreach($wuid as $key=>$value){
            $where['a.user_id'] = $value['uid'];
            $pinfo = $employee
                ->join('as a left join t_corporate as b on a.corporate_id = b.corporate_id')
                ->join('left join t_personal as c on a.personal_id = c.personal_id')
                ->field('a.user_id as id,
                c.name as realname,
                c.mobile,
                b.corporate_name as cname')
                ->where($where)
                ->find();
            if($pinfo){

                //总额度
                $where_time2['uid'] = $value['uid'];
                $all = $wquota->where($where_time2)->sum('allquota');
                $pinfo['allquota'] = $all;
//                $sql1 = $wquota->getLastSql();
//                dump($sql1);

                //使用额度
                $where_time3['uid'] = $value['uid'];
                $use = $wquota_use->where($where_time3)->sum('usequota');
                $pinfo['usequota'] = $use;
//                $sql2 = $wquota_use->getLastSql();
//                dump($sql2);exit();

                //剩余额度
                $pinfo['leave'] = $all-$use;
                $info[] = $pinfo;
            }

        }

        $num = count($info);

        return Response::mjson($info,$num);
    }


//    福利豆使用明细界面
    public function wquota_detail_index(){
        $uid = I('id','');
        $this->assign('uid',$uid)->display();
    }

    //福利豆使用明细s数据
    public function wquota_detail_info(){
        $uid = I('id','');
        $pageIndex = I('pageIndex','');
        $page = $pageIndex+1;
        if(!empty($page)){
            $limit = 10;
        }else{
            $limit = '';
        }
        $wquota_use = M('mall_wquota_use');
        $start = I('start1','');
        $end = I('end','');
        $where = '';
        if(!empty($uid)){
            $where['uid'] = array('eq',$uid);
        }
        if(!empty($start)){
            if(!empty($end)){
                $where['time'] = array('between',"$start,$end");
            }else{
                $where['time'] = array('EGT',"$start");
            }
        }else{

        }

        $result = $wquota_use
            ->where($where)
            ->page($page,$limit)
            ->field('id,usequota,ordernum,time ')
            ->select();

        $num =  $wquota_use
            ->where($where)
            ->count();
        return Response::mjson($result,$num);
    }


    /*==============================================薪资=================================================*/


    //员工薪资记录界面
    public function salary_index(){
        $Model = new \Think\Model();
        $vo = $Model->query(" select corporate_id as cid,corporate_name as cname from t_corporate ");
        $this->assign('vo',$vo);
        $this->display();
    }

    /**
     * 处理薪资时间专用
    */
    private function time($time){
        $one = substr($time,0,4);
        $two = substr($time,5,2);
        $time = $one.$two;
        return $time;
    }

    //员工薪资记录
    public function salary_lis_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit =10;
        $cid = I('cname','');
        $username = I('username','');
        $start = I('start1','');
        $end = I('end','');
        $where = '';
        if(!empty($cid)){
            $where[] = " d.corporate_id = $cid ";
        }
        if(!empty($username)){
            $where[] = " a.employee_name like %$username% ";
        }

        if(!empty($start) && !empty($end)){
            $start = $this->time($start);
            $end = $this->time($end);
            if($start == $end){
                $where[] = " a.salary_year_month = $start";
            }else{
                $where[] = " a.salary_year_month between $start and $end ";
            }
        }
//        dump($where);exit;
//        dump($page);
//        dump($limit);
//        exit;
        $salary = M('salary_employee_payment','t_');
        $result = $salary
            ->join('as a left join t_employee as b on a.employee_id = b.employee_id')
            ->join('left join t_personal as c on b.personal_id = c.personal_id')
            ->join('left join t_corporate as d on b.corporate_id = d.corporate_id')
            ->where($where)
            ->field('a.employee_name as name,c.mobile,d.corporate_name as cname,a.salary_year_month as paytime,a.total_pay_amount as spay,a.final_pay_amount as apay,a.updatetime as time')
            ->page($page,$limit)
            ->select();
//        $sql = $salary->getLastSql();
//        echo $sql;
//        exit;
        $num =  $salary
            ->join('as a left join t_employee as b on a.employee_id = b.employee_id')
            ->join('left join t_personal as c on b.personal_id = c.personal_id')
            ->join('left join t_corporate as d on b.corporate_id = d.corporate_id')
            ->where($where)
            ->count();


        return Response::mjson($result,$num);
    }
    /*****************************未用到*/
    //公司列表
    public function company_lis_info(){
        $company = M('company');
        $cname = I('cname','');
        $pageIndex = I('pageIndex','');
        $page = $pageIndex+1;
        if(!empty($page)){
            $limit = 10;
        }else{
            $limit = '';
        }
        if(!empty($cname)){
            $where['name'] = array('like','%'.$cname.'%');
        }else{
            $where = '';
        }
        $result = $company
            ->where($where)
            ->page($page,$limit)
            ->order('time desc')
            ->field('id,cname ')
            ->select();
        $num = $company->where($where)->count();
        $user = M('user');
        for($i=0;$i<count($result);$i++){
            $where_user['cid'] = array('eq',$result[$i]['id']);
            $result[$i]['num'] = $user->where($where_user)->count('id');
        }
        return Response::mjson($result,$num);
    }

    //公司员工列表
    public function company_user_lis(){
        $cid = I('id','');
        $pageIndex = I('pageIndex','');
        $page = $pageIndex+1;
        if(!empty($page)){
            $limit = 10;
        }else{
            $limit = '';
        }
        if(!empty($cid)){
            $where['cid'] = array('eq',$cid);
            $result = M('user')->where($where)->page($page,$limit)->field('name,')->select();
            $num = M('user')->where($where)->count();
        }
    }









}
