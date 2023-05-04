<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Manager\Controller;
use Org\Util\Rbac;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Jpushsend;
class WdcrashController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    //提现主界面
    public function index()
    {
        $this->display('Wdcrash/index');
    }
    //提现界面数据
    public function indexlis()
    {
        if(!empty($_REQUEST['mobile'])) {
            $where['a.mobile'] = array('like','%'.$_REQUEST['mobile'] .'%');
        }
        if(!empty($_REQUEST['status'])) {
            $where['a.status'] = array('eq', $_REQUEST['status']);
        }
        if(!empty($_REQUEST['time'])) {
            $where['a.time'] = array('like', '%'.$_REQUEST['time'] .'%');
        }
        if(!empty($_REQUEST['incode'])) {
            $where['b.incode'] = array('eq', $_REQUEST['incode']);
        }
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;

        $order = 'time desc';
        $Wdcrash = M('wdcrash');

        $vo = $Wdcrash
            ->page($page,$limit)
            ->order($order)
            ->join('as a left join task_user as b on a.uid = b.id ')
            ->join('left join task_incode as c on b.incode = c.incode')
            ->field('a.id,a.mobile,b.incode,c.belong,
            case a.status when 1 then \'审核中\' when 2 then \'已打款\' else \'已驳回\' end as status ,
            a.price,a.time,b.alipaynum,b.realname')
            ->where($where)
            ->select();
        $rows = $Wdcrash
        ->join('as a left join task_user as b on a.uid = b.id ')
        ->join('left join task_incode as c on b.incode = c.incode')
        ->field('a.id,a.mobile,b.incode,c.belong,
            case a.status when 1 then \'审核中\' when 2 then \'已打款\' else \'已驳回\' end as status ,
            a.price,a.time,b.alipaynum,b.realname')
        ->where($where)
        ->select();
        $num = count($rows);
        $allrice =   $Wdcrash
            ->page($page,$limit)
            ->join('as a left join task_user as b on a.uid = b.id ')
            ->join('left join task_incode as c on b.incode = c.incode')
            ->field('sum(a.price) as allprice')
            ->where($where)
            ->select();
        if(!empty($vo))
        $vo[0]['allprice'] = $allrice[0]['allprice'];

        return Response::mjson($vo,$num);
    }
    //打款操作
    public  function dk(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $wheres['id'] = array('in',$id);
        $where['b.id'] = array('in',$id);
        $User = M('user');
        $Wd = M('wdcrash');
        $s = $Wd->where($wheres)->field('status')->select();
        if($s[0]['status'] != 1) {
            echo 3;
            return false;
        }
        $result = $User//通过提现表里的mobile 找到user表里的id
            ->join('as a left join task_wdcrash as b on a.mobile = b.mobile')
            ->where($where)
            ->field('a.id,b.price')
            ->select();
//        print_r($result);
        $uid = $result[0]['id'];
        $txprice = $result[0]['price'];
        $where3['id'] = array('in',$id);
        $Wd->status = 2;
        $Ms = D('message');
        $data['uid'] = $uid;
        $data['content'] = '您的提现金额'.$txprice.'元已通过审核，快去查看吧！';
        $data['time'] = date('Y-m-d H:i:s');
        //发送推送
        $message = $data['content'];
        $type = array(
            'type' => 2
        );
        if($Wd->where($where3)->save() &&
            $Ms->add($data) &&
            Jpushsend::sendNotifySpecial($message,$type,$uid) )
            echo 1;
        else echo 2;
    }
    //驳回操作
    public  function bh(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $wheres['id'] = array('in',$id);
        $where['b.id'] = array('in',$id);
        $User = M('user');
        $Wd = M('wdcrash');
        $s = $Wd->where($wheres)->field('status')->select();
        //获取提现当前状态  不是审核中不允许再次操作
        if($s[0]['status'] != 1) {
            echo 3;
            return false;
        }
        //获取提现金额  驳回审核要加上提现金额
        $result = $User//通过提现表里的mobile 找到user表里的id
        ->join('as a left join task_wdcrash as b on a.mobile = b.mobile')
            ->where('b.id = '.$id)
            ->field('a.id,a.balance,b.price,b.mobile as mobile')
            ->select();
        $uid = $result[0]['id'];//用户id
        $txprice = $result[0]['price'];//提现金额
        $oldbalance = $result[0]['balance'];//用户原始余额
        $User->balance = $oldbalance + $txprice; //驳回后的余额
        $where3['id'] = array('in',$id);
        $Wd->status = 3;
        //添加明细
        $Dt = M('detail');
        $dtdata['mobile'] = $result[0]['mobile'];
        $dtdata['price'] = $txprice;
        $dtdata['type'] = 3;
        $dtdata['time'] = date('Y-m-d H:i:s');

        //添加消息记录
        $Ms = M('message');
        $data['uid'] = $uid;
        $data['content'] = '您的提现金额'.$txprice.'元已被驳回，若有疑问请咨询在线客服！';
        $data['time'] = date('Y-m-d H:i:s');
        //发送推送
        $message = $data['content'];
        $type = array(
            'type' => 2
        );
        if($Wd->where($where3)->save() &&
            $Ms->add($data) &&
            $User->where('id = '.$uid)->save()!==false &&
            $Dt->add($dtdata) &&
            Jpushsend::sendNotifySpecial($message,$type,$uid)
        )
            echo 1;
        else echo 2;
    }
    //删除操作
    public function del(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $Wd = M('wdcrash');
        if($Wd->where($where)->delete())
            echo 1;
            else echo 2;
    }
    //




}
