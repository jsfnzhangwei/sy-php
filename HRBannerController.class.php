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
use Org\Util\Upload;
use Common\Model\ErrorModel;
class HRBannerController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    //广告主界面
    public function banner_lis()
    {
        $this->display('HRBanner/banner_lis');
    }
    //广告主界面数据
    public function banner_lis_info()
    {
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $where['is_use'] = array('eq',1);
        $Banner = M('rzbanner','dsy_');//实例化公告表

        $totalNum = getViewsNum();
        $vo = $Banner
            ->where($where)
            ->page($page,$limit)
            ->order('time desc')
            ->select();
        foreach ($vo as $k => $v) {
            $vo[$k]['view_rate'] = round(($v['views'] / $totalNum) * 100, 2) . '%';
        }
        $num =  $Banner
            ->where($where)
            ->count();
        return Response::mjson($vo,$num);

    }
    //删除广告
    public  function banner_del(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Anc = M('rzbanner','dsy_');
        $where['id'] = array('in',$id);
        if($Anc->where($where)->delete())
            return Response::show(200,'操作成功');
        else return Response::show(200,'操作失败');
    }
    //添加广告界面
    public function banner_add(){
        $this->display('HRBanner/add');
    }
    //添加操作
    public function banner_add_do(){
        $url = I('url','');
        $pic = $_FILES['pic'];
        $start_date = I('start_date', '');
        $end_date = I('end_date', '');

        if (empty($start_date)) {
            ErrorModel::startDateLose();
        }
        if (empty($end_date)) {
            ErrorModel::endDateLose();
        }
        if ($end_date < date('Y-m-d')) {
            ErrorModel::endDateError();
        }
        if ($start_date > $end_date) {
            ErrorModel::dateCompare();
        }

        if(!empty($pic)){
            $Banner = M('rzbanner','dsy_');
            $data['url'] = $url;
            $data['time'] = NOW;
            $data['is_use'] = 1;
            $data['pic'] = uploadfile($pic);
            if(empty($data['pic'])){
                return Response::show(400,'图片上传失败');
            }
            $data['link_type'] = (int)$_POST['link_type'];
            $data['start_date'] = $start_date;
            $data['end_date'] = $end_date;
            if($Banner->add($data))
                return Response::show(200,'新增成功');
            else return Response::show(400,'新增失败');
        }else return Response::show(400,'请选择图片');



    }
    //编辑广告界面
    public function banner_edit(){
        $id = I('id');
        $Banner = M('rzbanner','dsy_');
        $where['id'] = array('eq',$id);
        $vo = $Banner->where($where)->select();
        $vo[0]['pic'] = format_img($vo[0]['pic'], IMG_VIEW);
        $this->assign('vo',$vo);
        $this->display('HRBanner/update');
    }
    //编辑操作保存
    public function banner_edit_do(){
        $Banner = M('rzbanner','dsy_');
        $id = I('id');
        $start_date = I('start_date', '');
        $end_date = I('end_date', '');

        if (empty($start_date)) {
            ErrorModel::startDateLose();
        }
        if (empty($end_date)) {
            ErrorModel::endDateLose();
        }
        if ($end_date < date('Y-m-d')) {
            ErrorModel::endDateError();
        }
        if ($start_date > $end_date) {
            ErrorModel::dateCompare();
        }

        $where['id'] = array('eq',$id);
        $Banner->url = $_REQUEST['url'];
        if(!empty($_FILES['pic'])) {
            $newpic = uploadfile($_FILES['pic']);
            if(empty($newpic)){
                return Response::show(400,'图片上传失败');
            }
            $Banner->pic = $newpic;
        }
        $Banner->link_type = (int)$_POST['link_type'];
        $Banner->start_date = $start_date;
        $Banner->end_date = $end_date;
        if($Banner->where($where)->save()!==false)
            return Response::show(200,'操作成功');
        else return Response::show(400,'操作失败');
    }
    public function card_banner_index(){
        $this->display();
    }

    public function card_banner_list(){
        $limit = $_REQUEST['limit'];
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;

        $result = M('card_banner')
                    ->order('create_time desc')
                    ->page($page,$limit)
                    ->select();
        foreach($result as $key => $val){
            $result[$key]['pic'] = format_img($val['pic'], IMG_VIEW);
        }
        $num = M('card_banner')->count();

        return Response::mjson($result,$num);
    }
    public function card_banner_add(){
        $this->display();
    }
    public function card_banner_edit(){
        $id = I('id');
        $where['id'] = array('eq',$id);
        $result = M('card_banner')->where($where)->find();
        $result['pic'] = format_img($result['pic'], IMG_VIEW);
        $this->assign('result',$result);
        $this->display();
    }
    public function card_banner_insert(){
        $url = I('url','');
        $pic = $_FILES['pic'];
        $state = I('state');
        $type = I('type');
        if(empty($url)){
            return Response::show(300,'外链地址不能为空');
        }

        if(!empty($pic)){
            $Banner = M('card_banner','dsy_');
            $data = [
                'pic' => uploadfile($pic),
                'url' => $url,
                'create_time' => NOW,
                'type' => $type,
                'state' => $state
            ];
            if(empty($data['pic'])){
                return Response::show(400,'图片上传失败');
            }
            if($Banner->add($data))
                return Response::show(200,'新增成功');
            else return Response::show(400,'新增失败');
        }else return Response::show(400,'请选择图片');
    }
    public function card_banner_update(){
        $id = I('id');
        $url = I('url','');
        $state = I('state');
        $type = I('type');
        if(empty($id)){
            return Response::show(300,'请至少选择一个要修改的广告');
        }
        if(empty($url)){
            return Response::show(300,'外链地址不能为空');
        }

        $Banner = M('card_banner','dsy_');
        $data = [
            'url' => $url,
            'create_time' => NOW,
            'type' => $type,
            'state' => $state
        ];
        if(!empty($_FILES['pic'])) {
            $newpic = uploadfile($_FILES['pic']);
            if(empty($newpic)){
                return Response::show(400,'图片上传失败');
            }
            $data['pic'] = $newpic;
        }
        $where['id'] = array('eq',$id);
        $result = $Banner->where($where)->save($data);
        if($result !== false)
            return Response::show(200,'新增成功');
        else return Response::show(400,'新增失败');
    }
    public function card_banner_delete(){
        $ids = $_REQUEST['ids'];
        $id = implode(',',$ids);
        $Anc = M('card_banner');
        $where['id'] = array('in',$id);
        if($Anc->where($where)->delete())
            return Response::show(200,'操作成功');
        else return Response::show(200,'操作失败');
    }
    public function card_banner_state(){
        $ids = I('ids');
        $id = implode(',',$ids);
        $state = I('state');
        $where['id'] = array('in',$id);
        $result = M('card_banner')->where($where)->save(['state'=>$state]);
        if($result !== false){
            return Response::show(200,'操作成功');
        }else{
            return Response::show(200,'操作失败');
        }
    }
}
