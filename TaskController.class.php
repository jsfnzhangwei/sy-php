<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Manager\Controller;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Response;
use Org\Util\Jpushsend;
use Think\Exception;

class TaskController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    //任务列表界面
    public function taskindex()
    {
        $this->display('Task/index');
    }
    //任务列表数据
    public function tasklis()
    {
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        if(!empty($_REQUEST['title']))
            $where['title'] = array('like','%'.$_REQUEST['title'].'%');
        if(!empty($_REQUEST['isshow']))
            $where['isshow'] = array('eq',$_REQUEST['isshow']);
        if(!empty($_REQUEST['isindex']))
            $where['isindex'] = array('eq',$_REQUEST['isindex']);
        if(!empty($_REQUEST['istop']))
            $where['istop'] = array('eq',$_REQUEST['istop']);
        if(!empty($_REQUEST['type']))
            $where['type'] = array('eq',$_REQUEST['type']);
        if(!empty($_REQUEST['time']))
            $where['time'] = array('like',$_REQUEST['time'].'%');
        if(!empty($_REQUEST['jztime']))
            $where['jztime'] = array('like',$_REQUEST['jztime'].'%');
        $Task = M('task');
        $num= count($Task->field('id')->where($where)->select());//总记录数
        $vo = $Task
            ->where($where)
            ->order('isindex asc,istop asc,time desc')
            ->page($page,$limit)
            ->field('id,title,time,jztime,price,num,
            case isshow when 1 then \'已启用\' else \'已停用\' end as isshow,
            case istop when 1 then \'已置顶\' else \'未置顶\' end as istop,
            case isindex when 1 then \'已推荐\' else \'未推荐\' end as isindex
            ')
            ->select();
        return Response::mjson($vo,$num);

    }
    //添加任务界面
    public function addindex(){
        $this->display('add');
    }
    //添加任务
    public function add(){

        if(!empty($_REQUEST['title']) &&
            !empty($_REQUEST['type']) &&
            !empty($_REQUEST['num']) &&
            !empty($_REQUEST['limitnum']) &&
            !empty($_REQUEST['price']) &&
            !empty($_REQUEST['isshow']) &&
            !empty($_REQUEST['istop']) &&
            !empty($_REQUEST['isindex']) &&
            !empty($_REQUEST['jztime']) &&
            !empty($_REQUEST['instruction']) &&
            !empty($_FILES['pic'])
        ) {
            $data['title'] = $_REQUEST['title'];
            $data['type'] = $_REQUEST['type'];
            $data['num'] = $_REQUEST['num'];
            $data['limitnum'] = $_REQUEST['limitnum'];
            if(!empty($_REQUEST['url']) ) {
                $data['url'] = $_REQUEST['url'];
            }
            $data['price'] = $_REQUEST['price'];
            $data['isshow'] = $_REQUEST['isshow'];
            $data['istop'] = $_REQUEST['istop'];
            $data['isindex'] = $_REQUEST['isindex'];
            $data['jztime'] = $_REQUEST['jztime'];
            $data['instruction'] = $_REQUEST['instruction'];
            $data['time'] = date('Y-m-d H:i:s');
            $data['pic'] = Upload::uploadfile($_FILES['pic']);
            $Task = M('task');
            if ($Task->add($data)){
                echo 1;
            } else {
                echo 2;
            }

        }else echo 2;

    }
    //编辑页面
    public  function taskeditindex(){
        $id = $_REQUEST['id'];
        $where['id'] = array('eq',$id);
        $wheretid['tid'] = array('eq',$id);
        $Task = M('task');
        $Taskstep = M('taskstep');
        $vo = $Task->where($where)->select();
        $step = $Taskstep->where($wheretid)->order('step asc')->select();
        $pic = '';
        for ($i=0; $i<count($step);$i++) {
            $piclis = $step[$i]['spic'];
            $picarr = explode(',',$piclis);
//            print_r($picarr);
            for($j=0;$j<count($picarr);$j++){
                $step[$i]['sspic'].= '<img src ='. $picarr[$j].' width="50px" height="50px" id = "'. $i.$j.'" onclick = fd("'.$i.$j.'") />&nbsp:';

            }
        }

//        print_r($step);exit;
        $this->assign('vo',$vo);
        $this->assign('step',$step);
        $this->display('Task:edit');
    }
    //编辑
    public  function edit(){

        if(!empty($_REQUEST['title']) &&
            !empty($_REQUEST['id'])&&
            !empty($_REQUEST['type']) &&
            !empty($_REQUEST['num']) &&
            !empty($_REQUEST['limitnum']) &&
            !empty($_REQUEST['price']) &&
            !empty($_REQUEST['isshow']) &&
            !empty($_REQUEST['istop']) &&
            !empty($_REQUEST['isindex']) &&
            !empty($_REQUEST['jztime']) &&
            !empty($_REQUEST['instruction'])
        ) {
            $Task = M('task');
            if(!empty($_FILES['pic']))
                $Task->pic = Upload::uploadfile($_FILES['pic']);
            $id = $_REQUEST['id'];
            $where['id'] = array('eq',$id);
            $Task->title = $_REQUEST['title'];
            $Task->type = $_REQUEST['type'];
            $Task->num = $_REQUEST['num'];
            $Task->limitnum = $_REQUEST['limitnum'];
            if(!empty($_REQUEST['url'])){
                $Task->url = $_REQUEST['url'];
            }else $Task->url = '';
            $Task->price = $_REQUEST['price'];
            $Task->isshow = $_REQUEST['isshow'];
            $Task->istop = $_REQUEST['istop'];
            $Task->isindex = $_REQUEST['isindex'];
            $Task->jztime = $_REQUEST['jztime'];
            $Task->instruction = $_REQUEST['instruction'];
            if($Task->where($where)->save() !== false )
                echo 1;
                else echo 2;
        }else echo 3;
    }
    //停用
    public  function ty(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Task = M('task');
        $where['id'] = array('in',$id);
        $Task->isshow = 2;
        if($Task->where($where)->save()!==false)
            echo 1;
        else echo 2;
    }
    //启用
    public  function qy(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Task = M('task');
        $where['id'] = array('in',$id);
        $Task->isshow = 1;
        if($Task->where($where)->save()!==false)
            echo 1;
        else echo 2;
    }
    //置顶
    public  function zd(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Task = M('task');
        $where['id'] = array('in',$id);
        $Task->istop = 1;
        if($Task->where($where)->save()!==false)
            echo 1;
        else echo 2;
    }
    //取消置顶
    public  function qxzd(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Task = M('task');
        $where['id'] = array('in',$id);
        $Task->istop = 2;
        if($Task->where($where)->save()!==false)
            echo 1;
        else echo 2;
    }
    //推荐首页
    public  function tj(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Task = M('task');
        $where['id'] = array('in',$id);
        $Task->isindex = 1;
        if($Task->where($where)->save()!==false)
            echo 1;
        else echo 2;
    }
    //取消推荐
    public  function qxtj(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Task = M('task');
        $where['id'] = array('in',$id);
        $Task->isindex = 2;
        if($Task->where($where)->save()!==false)
            echo 1;
        else echo 2;
    }
    //任务步骤列表界面
    public function stepindex()
    {
        $this->display('step');
    }
    //任务步骤列表数据
    public function step()
    {
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        if(!empty($_REQUEST['tid']))
            $where['tid'] = array('eq',$_REQUEST['tid']);
        else $where = '';
        $Taskstep = M('taskstep');
        $num= count($Taskstep->where($where)->field('id')->select());//总记录数
        $vo = $Taskstep
            ->join('as a left join task_task as b on a.tid = b.id')
            ->field('a.id,a.step,a.content,b.title,a.tid')
            ->where($where)
            ->page($page,$limit)
            ->select();
//        $rows = $Taskstep
//            ->join('as a left join task_task as b on a.tid = b.id')
//            ->field('a.id,a.step,a.content,b.title,a.tid')
//            ->where($where)
//            ->select();
//        $num = count($rows);
        return Response::mjson($vo,$num);
    }
    //添加任务步骤界面
    public function addstepindex(){

        $this->display('addstep');
}
    //添加任务步骤操作
    public function addsteptool(){
        $T = D('taskstep');
        if(!empty($_REQUEST['tid']) &&
            !empty($_REQUEST['content']) &&
            !empty($_REQUEST['step'])
        ) {
            $data['tid'] = $_REQUEST['tid'];
            $data['content'] = $_REQUEST['content'];
            $data['step'] = $_REQUEST['step'];
            if(!empty($_REQUEST['purl'])) {
                $picurl = $_REQUEST['purl'];//获取到纯<img>字符串
//                echo $picurl;exit;
//                echo $picurl;exit;
                $srcnum = substr_count($picurl, '<img src=');//获取<img>个数
                $piclist = array();
                for ($i = 0; $i < $srcnum; $i++) {
                    $weizhi1 = strpos($picurl, "<");
                    $weizhi2 = strpos($picurl, '>');
                    $img = substr($picurl, $weizhi1, $weizhi2);//找到<img>位置
                    $picweizhi1 = strpos($img, 'http://');//找到pic路径
                    $picweizhi2 = strpos($img, '.jpg');
                    $pic = substr($img, $picweizhi1, $picweizhi2-6);
                    $piclist[$i] = $pic;
                    $picurl = substr($picurl, $weizhi2+1);//移除上一个<img>
                }
                $data['spic'] = implode(',', $piclist);
//                echo $data['spic'];exit;
            }
//            print_r($data);exit;
            $r = $T->add($data);
//            var_dump($r) ;exit;
//            echo $srcnum;exit;

            if ($r)
                echo 1;
             else echo 2;


        }else echo 2;

    }
    //删除任务步骤
    public function delstep(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $Taskstep = M('taskstep');
        if($Taskstep->where($where)->delete())
            echo 1;
        else echo 2;
    }
    //编辑任务步骤页面
    public function editstepindex(){

        $id = $_REQUEST['id'];
        $where['id'] = array('eq',$id);
        $Taskstep = M('taskstep');
        $vo = $Taskstep->where($where)->select();
        $pic = $vo[0]['spic'];
       // echo $pic;exit;
        $picarray = explode(',',$pic);
        //print_r($picarray);exit;
        $piclist = '';
        foreach ($picarray as $k=>$v){
            $piclist .=  '<img src = "'.$v.'" />';
        }
        $this->assign('piclist',$piclist);
        $this->assign('vo',$vo);
        $this->display('editstep');
    }
    //编辑任务步骤操作
    public function editstep(){

        $Taskstep = M('taskstep');
        if(!empty($_REQUEST['tid']) &&
            !empty($_REQUEST['id']) &&
            !empty($_REQUEST['content']) &&
            !empty($_REQUEST['step'])
        ) {
            $where['id'] = array('eq',$_REQUEST['id']);
            $Taskstep->tid = $_REQUEST['tid'];
            $Taskstep->content = $_REQUEST['content'];
            $Taskstep->step = $_REQUEST['step'];
            if(!empty($_REQUEST['picurl'])) {
                $picurl = $_REQUEST['picurl'];//获取到纯<img>字符串
                $srcnum = substr_count($picurl, '<img src=');//获取<img>个数
                $piclist = array();
                for ($i = 0; $i < $srcnum; $i++) {
                    $weizhi1 = strpos($picurl, "<");
                    $weizhi2 = strpos($picurl, '>');
                    $img = substr($picurl, $weizhi1, $weizhi2);//找到<img>位置
                    $picweizhi1 = strpos($img, 'http://');//找到pic路径
                    $picweizhi2 = strpos($img, '.jpg');
                    $pic = substr($img, $picweizhi1, $picweizhi2 - 6);
                    $piclist[$i] = $pic;
                    $picurl = substr($picurl, $weizhi2 +1);//移除上一个<img>
                }
                $Taskstep->spic = implode(',', $piclist);
            }else $Taskstep->spic = '';
//            echo $srcnum;exit;

            if ($Taskstep->where($where)->save() !==false){
                echo 1;
            } else {
                echo 2;
            }

        }else echo 2;

    }
    //任务审核界面
    public function tasksubindex(){
        $this->display('tasksub');
    }
    //任务审核列表数据
    public function tasksublis(){
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
       if(!empty($_REQUEST['time'])) {
           $where['a.time'] = array('like', $_REQUEST['time'] . '%');
           $where2['time'] = array('like', $_REQUEST['time'] . '%');
       }
       if (!empty($_REQUEST['tid'])) {
           $where['a.tid'] = array('eq', $_REQUEST['tid']);
           $where2['tid'] = array('eq', $_REQUEST['tid']);
       }
        $Tasksub = M('subtask');
        $num = $Tasksub->where($where2)->count('id');
        $data = $Tasksub
            ->join('as a left join task_task as b on a.tid = b.id')
            ->join ('left join task_user as c on a.uid = c.id')
            ->where($where)
            ->field('a.id,a.tid,
            a.name,a.time,a.mobile as mobile,a.else,a.pic,b.title,c.mobile as umobile,
            case a.STATUS when 1 then \'已通过\' when 2 then \'已驳回\' when 3 then \'审核中\' else \'其他\' end as status')
            ->order('time desc')
            ->page($page,$limit)
            ->select();

        return Response::mjson($data,$num);
    }
    //子任务审核列表数据
    public function tasksublis_2(){
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $username = $_SESSION['admin'];
        //echo $username;exit;
        $where['fpst'] = array('eq',$username);
        $where['a.status'] = array('eq',3);
        if(!empty($_REQUEST['time']))
            $where['a.time'] = array('like',$_REQUEST['time'].'%');
        $Tasksub = M('subtask');
        $data = $Tasksub
            ->join('as a left join task_task as b on a.tid = b.id')
            ->join ('left join task_user as c on a.uid = c.id')
            ->where($where)
            ->field('a.id,a.tid,
            a. fpst,
            a.name,a.time,a.mobile as mobile,a.else,a.pic,b.title,c.mobile as umobile,
            case a.STATUS when 1 then \'已通过\' when 2 then \'已驳回\' when 3 then \'审核中\' else \'其他\' end as status')
            ->order('time desc')
            ->page($page,$limit)
            ->select();
        $num = $Tasksub->where($where)->count('id');
        return Response::mjson($data,$num);
    }
    //任务审核详情
    public function tasksubselone()
    {
        $id = $_REQUEST['id'];
        echo $id;exit;
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $Tasksub = M('subtask');
        $data = $Tasksub->order('time desc')->page($page, $limit)->select();
        $num = $Tasksub->field("count('id') as num")->select();
        return Response::mjson($data, $num['0']['num']);
    }
    //查看任务审核图片
        public function selpic(){
            $id = $_REQUEST['id'];
//            echo $id;
            $where['id'] = array('eq',$id);
            $Tasksub = M('subtask');
            $pic = $Tasksub->where($where)->field('pic')->select();
//            print_r($pic);exit;
            $picarr = explode(',',$pic[0]['pic']);
//          print_r($picarr);exit;
//            $piclist = '';
//            foreach ($picarr as $k=>$v){
//                $piclist .= '<img src = "'.$v.'" />  ';
//            }
//            echo  $piclist;
            $this->assign('picarr',$picarr);
            $this->display('selpic');
    }
    //删除任务审核记录
    public function tasksubdel(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $Tasksub = M('subtask');
        if($Tasksub->where($where)->delete())
            echo 1;
            else echo 2;

//        print_r($ids);
    }
    //通过任务审核
    public function tasksubtg(){
//        $message = '您提交的任务金额：￥3.2 已通过审核，快去提现吧！';
//        $type = array(
//            'type'=> 2,
//        );
//        $uid = '1490';
//        $push = Jpushsend::sendNotifySpecial($message,$type,$uid);
//        echo $push;exit;

        $ids = $_REQUEST['ids'];

        $id = implode(',',$ids);
//       // echo $id;exit;
        $where['a.id'] = array('eq',$id);
        //查询任务审核相关数据
        $Tasksub = M('subtask');
        $result = $Tasksub
            ->join('as a left join task_task as b on a.tid = b.id')
            ->where($where)
            ->field('a.status,a.tid,a.uid as uid,b.price as price,a.pic as pic')
            ->select();
        $status = $result[0]['status'];
        //如果任务状态为已通过，则提示不能再次审核
        if($status == 1){
            echo 3;
            return false;
        }
        //添加消息记录
        $Msg = M('message');
        $data['uid'] = $result[0]['uid'];
        $data['content'] = '您提交的任务金额：￥'.$result[0]['price'].' 已通过审核，快去提现吧！';
        $data['time'] = date('Y-m-d H:i:s');
        //添加余额
        $User = M('user');
        $uid = $result[0]['uid'];
        $whereuser['id'] = array('eq',$uid);
        $oldbal = $User->where($whereuser)->field('balance,mobile')->select();
        $User->balance = $oldbal[0]['balance'] +$result[0]['price'];
        //添加明细记录
        $dtdata['pic'] = $result[0]['pic'];
        $dtdata['mobile'] = $oldbal[0]['mobile'];
        $dtdata['price'] = $result[0]['price'];
        $dtdata['type'] = 2;
        $dtdata['time'] = date('Y-m-d H:i:s');
        $Dt = M('detail');
        //改变审核状态
        $Tasksub->status = 1;
        $wheretub['id'] = array('in',$id);
        //改变我的任务状态
        $Mytask = M('mytask');
        $Mytask->status = 3;
        $wheremt['uid'] = array('eq',$result[0]['uid']);
//        echo $result[0]['uid'];exit;
//        echo $id;exit;
        $wheremt['tid'] = array('in',$result[0]['tid']);
        $wheremt['status'] = array('eq',2);
        //发送推送通知
        $message = $data['content'];
        $type = array(
           'type'=> 2,
            );
       // $title = '任务审核信息';
       // $push = Jpushsend::sendNotifySpecial($message,$type,$uid);
        //echo $push;exit;
        if( $Msg->add($data) &&
            $Dt->add($dtdata) &&
            $Tasksub->where($wheretub)->save() !== false &&
            $User->where($whereuser)->save() !==false &&
            $Mytask->where($wheremt)->save() !== false

        ) {

            try{
                Jpushsend::sendNotifySpecial($message,$type,$uid);
                echo 1;
            }catch (Exception $e){echo 1;}

        }
        else echo 2;

    }
    //驳回审核任务
    public function tasksubntg(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $where['a.id'] = array('in',$id);
        $Tasksub = M('subtask');
        $result = $Tasksub
            ->join('as a left join task_task as b on a.tid = b.id')
            ->where($where)
            ->field('a.status,a.uid as uid,b.price,a.tid')
            ->select();
        $status = $result[0]['status'];
        if($status != 3){
            echo 3;
            return false;
        }
        //添加消息记录
        $Msg = M('message');
        $uid = $result[0]['uid'];
        $data['uid'] = $uid;
      //  echo $data['uid '];
        $data['content'] = '您提交的任务金额：￥'.$result[0]['price'].' 已被驳回，若有疑问请咨询在线客服!';
        $data['time'] = date('Y-m-d H:i:s');
        //改变审核状态
        $Tasksub->status = 2;
        $wheretub['id'] = array('in',$id);
        //改变我的任务状态
        $Mytask = M('mytask');
        $Mytask->status = 4;
        $wheremt['uid'] = array('eq',$result[0]['uid']);
        $wheremt['tid'] = array('eq',$result[0]['tid']);
        $wheremt['status'] = array('eq',2);
      //  print_r($wheremt);exit;
        //发送推送通知
        $message = $data['content'];
        $type = array(
            'type' => 2
        );
        if( $Msg->add($data) &&
            $Mytask->where($wheremt)->save() !== false &&
            $Tasksub->where($wheretub)->save() !== false

        ) {
            try{
                Jpushsend::sendNotifySpecial($message,$type,$uid);
                echo 1;
            }catch (Exception $e){echo 1;}
        }else echo 2;
    }
    //分配任务界面
    public function fenpeiindex(){
        $tids = $_REQUEST['ids'];
        $Ad = M('admin');
        $where['id'] = array('neq',1);
        $where['username'] = array('neq','username');
        $vo = $Ad->where($where)->select();
        $this->assign('vo',$vo);
        $this->assign('tids',$tids);
        $this->display('fenpei');
    }
    //分配任务操作
    public function fenpei(){
        $tids = $_REQUEST['tids'];
        $aid = $_REQUEST['aid'];
        $Fp = M('fenpei');
        $data['tids'] = $tids;
        $data['aid'] = $aid;
        $data['time'] = date('Y-m-d H:i:s');
        if($Fp->add($data)){
            $Ad = M('admin');
            $where['id'] = array('eq',$aid);
            $result = $Ad->where($where)->field('username')->select();
            $username = $result[0]['username'];
            $Tasksub = M('subtask');
            $Tasksub->fpst = $username;
            $where['id'] = array('in',$tids);
            if($Tasksub->where($where)->save()!==false)
                echo 1;
            else echo 2;
        }

    }

}
