<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Manager\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;
use Think\Db;

class AncController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    //公告主界面
    public function index()
    {
        $this->display('Anc/index');
    }
    //公告主界面数据
    public function lis()
    {
        if(!empty($_REQUEST['title'])){
            $where['title'] = array('like','%'. $_REQUEST['title'].'%');
        }else {$where = '';}
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $Anc = M('announcement');//实例化公告表
        $vo = $Anc
            ->order('istop desc,time desc')
            ->field('id,title,time,
            case istop when 1 then \'展示\' when 2 then \'不展示\' end as istop
            ')
            ->page($page,$limit)
            ->where($where)
            ->select();
        $num = $Anc->where($where)->count('id');
        return Response::mjson($vo,$num);
    }
    //删除公告
    public  function del(){
        //删除
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Anc = M('announcement');
        $where['id'] = array('in',$id);
        if($Anc->where($where)->delete())
            echo 1;
        else echo 2;
    }
    //添加公告界面
    public function addindex(){
        $this->display('add');
    }
    //添加公告操作
    public function add(){
        if(!empty($_REQUEST['title'])&&
            !empty($_FILES['pic'])&&
            !empty($_REQUEST['istop'])&&
            !empty($_REQUEST['content'])
        ) {
            $data['title'] = $_REQUEST['title'];
            $data['pic'] = Upload::uploadfile($_FILES['pic']);
            $data['istop'] = $_REQUEST['istop'];
            $data['content'] = $_REQUEST['content'];
            $data['time'] = date('Y-m-d H:i:s');
            $Anc = D('announcement');
            $result = $Anc->add($data);
            $message = $_REQUEST['title'];
            $type = array(
                'type' => 1,//公告消息 2系统消息
                'id' => $result,
            );
            if ($result !=='') echo 1;
            else echo 2;
            Jpushsend::sendNotifyAll($message,$type);
        }else echo 3;
    }
    //编辑公告界面
    public function update(){
        $id = $_REQUEST['id'];
        $where['id'] = array('eq',$id);
        $Anc = M('announcement');
        $vo = $Anc
            ->where($where)
            ->select();
        $this->assign( "vo", $vo );
        $this->display('Anc:edit');
    }
    //编辑公告操作
    public function editanc(){
        if(!empty($_REQUEST['id'])&&
            !empty($_REQUEST['content'])&&
            !empty($_REQUEST['title'])&&
            !empty($_REQUEST['istop'])
            )
         {
            $id = $_REQUEST['id'];
            $where['id'] = array('eq', $id);
            $Anc = M('announcement');
            $Anc->content = $_REQUEST['content'];
            $Anc->title = $_REQUEST['title'];
            $Anc->istop = $_REQUEST['istop'];
//             echo $_REQUEST['content'];exit;
             if(!empty($_FILES['pic']))
                 $Anc->pic = Upload::uploadfile($_FILES['pic']);
            if ($Anc->where($where)->save()!==false)
                echo 1;
            else echo 2;

        }else echo 3;
    }
}
