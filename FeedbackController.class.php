<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/21 0021
 * Time: 01:24
 */

namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Jpushsend;

class FeedbackController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    //主界面
    public function index(){
        $this->display('feedback/index');
    }
    //主界面数据
    public function data_list(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $Fb = M('suggestion','t_');
        $result = $Fb
            ->join('as a left join t_user as b on a.user_id = b.user_id')
            ->field('a.id,a.content,a.time,b.user_name,a.img,a.contact,a.type,a.time')
            ->page($page,$limit)
            ->order('id desc')
            ->select();

        foreach($result as $key=>$value){
            $imgs = explode(',',$value['img']);
            $img = array();
            foreach($imgs as $v){
                if(!empty($v)){
                    $url = format_img($v, IMG_VIEW);
                    $option = '<a target="_blank" href="'.$url.'">查看</a>';
                    $img[] = $option;
                }
            }
            $im = implode('&nbsp;',$img);
            $result[$key]['img'] =$im;
        }
        $num = $Fb->count();
        return Response::mjson($result,$num);

    }
    //删除
    public function del(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Fb = M('suggestion','t_');
        $where['id'] = array('in',$id);
        if($Fb->where($where)->delete())
            echo 1;
        else echo 2;

    }


/**********************************************************/
    //版本更新主界面
    public function version()
    {
        $this->display('feedback/version');
    }

    //版本更新界面数据
    public function version_lis()
    {
        $version = M('version','dsy_');
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $result = $version
            ->page($page,$limit)
            ->order('id desc')
            ->where(['app_type'=>1])
            ->select();
        $num = $version->count();
        return Response::mjson($result,$num);
    }

    public function version_add(){
        if($_POST){
            $id = I('post.id','');
            $version = I('post.version','');
            $version_code = I('post.version_code','');
            $address = I('post.address','');
            $type = I('post.type','');
            $phone_type = I('post.phone_type','');
            $video_code = I('video_code');
//            if(empty($version)||empty($version_code)||empty($address)||empty($phone_type)){
//                return Response::show('300','缺少参数');
//            }
            if (empty($version)) {
                return Response::show('300', '请填写版本号');
            }
            if (empty($version_code)) {
                return Response::show('300', '请填写版本识别号');
            }
            if (empty($address)) {
                return Response::show('300', '请填写下载地址');
            }
            if (empty($phone_type)) {
                return Response::show('300', '请选择设备类型');
            }
            if(empty($video_code)){
                $result_video = M('version')->field('video_code')->where(['type'=>$phone_type])->order('id desc')->find();
                $video_code = $result_video['video_code'];
            }
            $data['version'] = $version;
            $data['code'] = $version_code;
            $data['type'] = $phone_type;
            $data['url'] = $address;
            $data['video_code'] = $video_code;
            $data['app_type'] = 1;
            if($type == 1){
                $data['is_force'] = 1;
            }else{
                $data['is_force'] = 0;
            }
            $data['time'] = NOW;
            $admin_log = (($phone_type == 1) ? 'Android' : 'IOS') . '包，' . $version . ' || ' . $version_code . ' || ' . $address . ' || ' . (($type == 1) ? '强更' : '不强更') . '||' . $video_code;
            if(empty($id)){
                $add = M('version','dsy_')->add($data);
                if($add){
                    admin_log('新增' . $admin_log, 1, 'version:' . $add);
                    return Response::show('200','success');
                }else{
                    return Response::show('400','fail');
                }
            }else{
                $where['id'] = array('eq',$id);
                $save = M('version','dsy_')->where($where)->save($data);
                if($save){
                    admin_log('编辑' . $admin_log, 1, 'version:' . $id);
                    return Response::show('200','success');
                }else{
                    return Response::show('400','fail');
                }
            }

        }else{
            if($_GET){
                $id = I('get.id','');
                $where['id'] = array('eq',$id);
                $info = M('version','dsy_')
                    ->where($where)
                    ->find();
                $this->assign('info',$info);
                $this->assign('id',$id);
            }
        }

        $this->display('feedback/version_add');
    }

/*************************************************************/
    //版本更新主题界面
    public function version_introduction()
    {
        $this->display('feedback/version_introduction');
    }

    //版本主题界面数据
    public function version_introduction_lis()
    {
        $version = M('version_introduction','dsy_');
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $result = $version
            ->page($page,$limit)
            ->order('id desc')
            ->select();
        $num = $version->count();
        return Response::mjson($result,$num);
    }

    public function version_introduction_add(){
        if($_POST){
            if(empty($_REQUEST['detail'])){
                return Response::show(300,'请填写版本介绍');
            }else{
                $data['content'] = $_REQUEST['detail'];
                $data['text'] = $_REQUEST['text'];
            }
            if(!empty($_FILES['pic'])){
                $pic = $_FILES['pic'];
                $pic_upload = uploadfile($pic);
                $data['url'] = $pic_upload;
            }
            $name = I('name','');
            $id = I('id','');
            if(empty($name)){
                return Response::show(300,'请输入版本名称');
            }else{
                $data['title'] = $name;
            }
            $data['time'] = NOW;
            $version = M('version_introduction','dsy_');
            if(empty($id)){
                $add = $version->add($data);
                if($add){
                    //修改员工表字段
                    $employee = M('employee','t_');
                    $employee->startTrans();
                    $eids = $employee->field('employee_id')->select();
                    foreach($eids as $key=>$value){
                        $eid = $value['employee_id'];
                        $employee->is_show_version = 1;
                        $where1['employee_id'] = $eid;
                        $save = $employee->where($where1)->save();
                        if($save==true){
                            $employee->commit();
                        }else{
                            $employee->rollback();
                        }

                    }
                    //发送推送
                    $message = '有新版本发布啦，请点击查看';
                    $type = array(
                        'type'=> 11,
                        'rid' => $add,//记录id
                        'title'=>$name,
                        'text'=> $data['text']
                    );
                    $push = Jpushsend::sendNotifyAll($message,$type);
                    return Response::show('200','success');
                }else{
                    return Response::show('400','fail');
                }
            }else{
                $where['id'] = array('eq',$id);
                $save = $version->where($where)->save($data);
                if($save){
                    return Response::show('200','success');
                }else{
                    return Response::show('400','fail');
                }
            }

        }else{
            if($_GET){
                $id = I('get.id','');
                $where['id'] = array('eq',$id);
                $info = M('version_introduction','dsy_')
                    ->where($where)
                    ->find();
                $info['url'] = format_img($info['url'], IMG_VIEW);
                $this->assign('info',$info);
                $this->assign('id',$id);
            }
        }
        $version_all = M('version','dsy_');
        $all = $version_all->field('id,version')->select();
        $this->assign('list',$all);
        $this->display('feedback/version_introduction_add');
    }


    public function del_record(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $version = M('version_introduction','dsy_');
        $where['id'] = array('in',$id);
        if($version->where($where)->delete())
            echo 1;
        else echo 2;
    }
    /**********************************************************/
    //版本更新主界面
    public function shop_version()
    {
        $this->display('feedback/shop_version');
    }

    //版本更新界面数据
    public function shop_version_lis()
    {
        $version = M('version','dsy_');
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $result = $version
            ->page($page,$limit)
            ->order('id desc')
            ->where(['app_type'=>2])
            ->select();
        $num = $version->count();
        return Response::mjson($result,$num);
    }

    public function shop_version_add(){
        if($_POST){
            $id = I('post.id','');
            $version = I('post.version','');
            $version_code = I('post.version_code','');
            $address = I('post.address','');
            $type = I('post.type','');
            $phone_type = I('post.phone_type','');
            $video_code = I('video_code');
//            if(empty($version)||empty($version_code)||empty($address)||empty($phone_type)){
//                return Response::show('300','缺少参数');
//            }
            if (empty($version)) {
                return Response::show('300', '请填写版本号');
            }
            if (empty($version_code)) {
                return Response::show('300', '请填写版本识别号');
            }
            if (empty($address)) {
                return Response::show('300', '请填写下载地址');
            }
            if (empty($phone_type)) {
                return Response::show('300', '请选择设备类型');
            }
            if(empty($video_code)){
                $result_video = M('version')->field('video_code')->where(['type'=>$phone_type])->order('id desc')->find();
                $video_code = $result_video['video_code'];
            }
            $data['version'] = $version;
            $data['code'] = $version_code;
            $data['type'] = $phone_type;
            $data['url'] = $address;
            $data['video_code'] = $video_code;
            $data['app_type'] = 2;
            if($type == 1){
                $data['is_force'] = 1;
            }else{
                $data['is_force'] = 0;
            }
            $data['time'] = NOW;
            $admin_log = (($phone_type == 1) ? 'Android' : 'IOS') . '包，' . $version . ' || ' . $version_code . ' || ' . $address . ' || ' . (($type == 1) ? '强更' : '不强更') . '||' . $video_code;
            if(empty($id)){
                $add = M('version','dsy_')->add($data);
                if($add){
                    admin_log('新增商城' . $admin_log, 1, 'version:' . $add);
                    return Response::show('200','success');
                }else{
                    return Response::show('400','fail');
                }
            }else{
                $where['id'] = array('eq',$id);
                $save = M('version','dsy_')->where($where)->save($data);
                if($save){
                    admin_log('编辑商城' . $admin_log, 1, 'version:' . $id);
                    return Response::show('200','success');
                }else{
                    return Response::show('400','fail');
                }
            }

        }else{
            if($_GET){
                $id = I('get.id','');
                $where['id'] = array('eq',$id);
                $info = M('version','dsy_')
                    ->where($where)
                    ->find();
                $this->assign('info',$info);
                $this->assign('id',$id);
            }
        }

        $this->display('feedback/shop_version_add');
    }


}