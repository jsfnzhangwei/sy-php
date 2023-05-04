<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Admin\Controller;
use Think\Cache\Driver\Memcachesae;
use Think\Controller;
use Org\Util\Response;
class UserController extends Controller {
    public function _initialize()
    {
        is_logout();
    }


    //平台用户界面
    public function userindex(){
        $this->display('index');
    }
    //平台用户列表数据
    public function userlis()
    {
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $User = M('user');
//        $num= count($User->field('id')->select());

        $Wdcrash = M('wdcrash');
        $myTask = M('mytask');
        $Task = M('task');
        $order = 'zctime desc';
        if(!empty($_REQUEST['mobile']))
            $where['mobile'] = array('like','%'.$_REQUEST['mobile'].'%');
        else if(!empty($_REQUEST['incode']))
            $where['incode'] = array('eq',$_REQUEST['incode']);
        else if(!empty($_REQUEST['time']))
            $where['zctime'] = array('like',$_REQUEST['zctime'].'%');
        else $where = '';
        $num = $User->where($where)->count('id');
        $vo = $User
            ->where($where)
            ->page($page,$limit)
            ->order($order)
            ->field('id,mobile,alipaynum,incode,zctime')
            ->select();
        $shprice = 0;
        //可用余额和审核中余额
        for($i = 0 ;$i<count($vo) ;$i++){
            $wd = $Wdcrash->where('status = 2 and mobile = '.$vo[$i]['mobile'])->field('sum(price) as allprice')->select();
            if($wd[$i]['allprice'] !='' && $wd[$i]['allprice']!=null)
                $vo[$i]['allprice'] = $wd[$i]['allprice'];
            else $vo[$i]['allprice'] = 0;
            $tid = $myTask->where('status = 2 and uid = '.$vo[$i]['id'])->field('tid')->select();
            if(!empty($tid)){
                for ($j = 0; $j < count($tid); $j++) {
                    $price = $Task->where('id = ' . $tid[$j]['tid'])->field('price')->select();
                    $shprice += $price[0]['price'];
                }
                if ($shprice != 0) $vo[$i]['shprice'] = $shprice;
                else $vo[$i]['shprice'] = 0;
            }else {
                $vo[$i]['shprice'] = 0;
            }
        }
        return Response::mjson($vo,$num);



    }
    //修改管理员密码界面
    public function uppwd()
    {
        $this->display('User/uppwd');
    }

    //修改管理员密码操作
    public function updatepwd()
    {
        $oldpwd = $_REQUEST['oldpwd'];
        $newpwd = $_REQUEST['newpwd'];
        if (empty($newpwd)) {
            return Response::show(400, '请输入新密码');
        }

        $Admin = M('admin');
        $token = $_COOKIE['token'];
        $admin_info = $Admin->where(['token' => $token, 'pwd' => md5($oldpwd)])->field('id')->find();

        if (!empty($admin_info)) {
            $res = $Admin->where(['id' => $admin_info['id']])->setField('pwd', md5($newpwd));
            if ($res !== false) return Response::show(200, '操作成功');
            else return Response::show(400, '操作失败');
        } else  return Response::show(400, '原密码错误');
    }
    //检测原始密码
    public function checkpwd()
    {
        $oldpwd = $_REQUEST['oldpwd'];
        $where['pwd'] = array('eq',md5(md5($oldpwd)));
        $Admin = M('admin');
        $check = $Admin->where($where)->select();
        if(!empty($check)) {
        }else echo '原密码不正确';//原始密码错误
    }
    //子账号界面
//    public function zuserindex(){
//        $this->display('zuserindex');
//    }
    //子账号数据
    public function zuserindexlis(){
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        if(!empty($_REQUEST['username']))
            $where['username'] = array('eq',$_REQUEST['username']);
        $where['id'] = array('neq',1);
       // $where['username'] = array('neq','admin');
        $Ad = M('admin');
        $num = $Ad->where($where)->count('id');
        $result = $Ad->where($where)->page($page,$limit)->select();
        return Response::mjson($result,$num);
    }
    //添加子账号
    public function adduserindex(){
        $this->display('adduser');
    }
    //添加子账号操作
    public function adduser(){
        $username = $_REQUEST['username'];
        $pwd = $_REQUEST['pwd'];
        $pwdtwo = $_REQUEST['pwdtwo'];
        if($pwd != $pwdtwo)return 2;
        $Ad = M('admin');
        $data['username'] = $username;
        $data['pwd'] = md5($pwd);
        $data['time'] = date('Y-m-d H:i:s');
        if($Ad->add($data))
            echo 1;
            else echo 2;

    }
    //删除子账号操作
    public function deluser(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $Ad = M('admin');
        if($Ad->where($where)->delete())
            echo 1;
        else echo 2;

    }
    //编辑子账号界面
    public function zuseredit(){
        $id = $_REQUEST['id'];
        $Ad = M('admin');
        $where['id'] = array('eq',$id);
        $result = $Ad->where($where)->select();
        $this->assign('result',$result);
        $this->display('zuseredit');
    }
    //编辑子账号操作
    public function zedit(){
        if(!empty($_REQUEST['username'])
            && !empty($_REQUEST['pwd'])
        && !empty($_REQUEST['id'])) {
            $id = $_REQUEST['id'];
//        echo $id;exit;
            $Ad = M('admin');
            $Ad->username = $_REQUEST['username'];
            $Ad->pwd = md5($_REQUEST['pwd']);
            $where['id'] = array('eq', $id);
            $result = $Ad->where($where)->save();
            if ($result !== false)
                echo 1;
            else echo 2;
        }else echo 2;
    }







}
