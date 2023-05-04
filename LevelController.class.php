<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Admin\Controller;
use Think\Controller;
use Org\Util\Response;
class LevelController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 一级分类列表界面
     *
     * */
    public function first_level(){
        $this->display('first_level_lis');
    }
    /**
     * 一级分类列表数据
     *
     * */
    public function first_level_info(){
        $fl = M('mall_flevel');//一级分类表
        $pageIndex = I('pageIndex','');
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        $status = I('status','');

        $where = '';
        if(!empty($name)){
            $where['name'] = array('like','%'.$name.'%');
        }
        if(!empty($status)){
            $where['status'] = array('eq',$status);
        }

        $num = $fl->where($where)->count('id');
        $result = $fl
            ->where($where)
            ->page($page,$limit)
            ->order('time desc')
            ->field('id,name,time,jd_catid
            ,case status when 1 then \'启用中\'when 2 then \'停用中\' end as status
            ')
            ->select();
        return Response::mjson($result,$num);
    }
    /**
     * 一级分类新增
     *
     * */
    public function first_level_add(){
        $this->display('first_level_add');
    }
    /**
     * 一级分类新增操作
     *
     * */
    public function first_level_add_do(){
        $name = I('name','');
        if(!empty($name)){
            $data = array(
                'name'=>$name,
                'status'=>2,
                'time'=>NOW,
            );
            $result = M('mall_flevel')->add($data);
            if($result){
                return Response::show(200,'新增成功');
            } else {
                return Response::show(400,'新增失败');
            }
        }else{
            return Response::show(300,'请输入分类名称');
        }
    }
    /**
     * 一级分类编辑
     *
     * */
    public function first_level_edit(){
        $id = I('id','');
        if(!empty($id)){
            $vo = M('mall_flevel')->where('id='.$id)->field('id,name,status')->find();
        }else{
            echo 3;
        }
        $this->assign('vo',$vo);
        $this->display('first_level_edit');

    }
    /**
     * 一级分类编辑操作
     *
     * */
    public function first_level_edit_do(){
        $id = I('id','');
        $name = I('name','');
        $status = I('status','');
        if(!empty($name) && !empty($status)){
            $where['id']=array('eq',$id);
            $data = array(
                'name'=>$name,
                'status'=>$status,
            );
            $result = M('mall_flevel')->where($where)->save($data);
            if($result !== false){
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请输入分类名称');
        }
    }
    /**
     * 一级分类停用操作
     *
     * */
    public function first_level_stop(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>2,
            );
            $where['id'] = array('in',$ids);
            $result = M('mall_flevel')->where($where)->save($data);
            if ($result !== false) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 一级分类启用操作
     *
     * */
    public function first_level_start(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>1,
            );
            $where['id'] = array('in',$ids);
            $result = M('mall_flevel')->where($where)->save($data);
            if ($result !== false) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 二级分类
     *
     * */
    public function second_level(){
        $this->display('second_level_lis');
    }
    /**
     * 二级分类列表数据
     *
     * */
    public function second_level_info(){
        $sl = M('mall_slevel');//二级分类表
        $pageIndex = I('pageIndex','');
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        $status = I('status','');
        $where = '';
        $where_num = '';
        if(!empty($name)){
            $where['a.name'] = array('like','%'.$name.'%');
            $where_num['name'] = array('like','%'.$name.'%');
        }
        if(!empty($status)){
            $where['a.status'] = array('eq',$status);
            $where_num['status'] = array('eq',$status);
        }



        $num = $sl->where($where_num)->count('id');
        $result = $sl
            ->join('as a left join dsy_mall_flevel as b on a.flid = b.id')
            ->where($where)
            ->page($page,$limit)
            ->order('a.time desc')
            ->field('a.id,a.name,a.time,a.jd_catid,b.name as sname
            ,case a.status when 1 then \'启用中\'when 2 then \'停用中\' end as status')
            ->select();
        return Response::mjson($result,$num);
    }
    /**
     * 二级分类新增
     *
     * */
    public function second_level_add(){
        $this->assign('first', getFLevel());
        $this->display('second_level_add');
    }
    /**
     * 二级分类新增操作
     *
     * */
    public function second_level_add_do(){
        $flid = I('fl','');
        $name = I('name','');
        if(empty($flid)){
            return Response::show(300,'请选择一级分类');
        } elseif (empty($name)) {
            return Response::show(300,'请输入分类名称');
        } else{
            $data = array(
                'flid'=>$flid,
                'name'=>$name,
                'status'=>2,
                'time'=>NOW,
            );
//            $slevel = M('mall_slevel');
//            $slevel->startTrans();
            //添加二级分类
            $result = M('mall_slevel')->add($data);
            //组合二级分类slid
//            $slid = $flid.$result;
//            $data_slid['slid'] = $slid;
//            $where_slid['id'] = array('eq',$result);
//            $result_slid = $slevel->where($where_slid)->save($data_slid);

            if($result){
//                $slevel->commit();
                return Response::show(200,'新增成功');
            } else {
//                $slevel->rollback();
                return Response::show(400,'新增失败');
            }
        }
    }
    /**
     * 二级分类编辑
     *
     * */
    public function second_level_edit(){
        $this->assign('first', getFLevel());
        $id = I('id','');
        if(!empty($id)){
            $vo = M('mall_slevel')->where('id='.$id)->field('id,name,status,flid')->find();
        }else{
            return Response::show(400,'数据异常，请刷新重试');
        }
        $this->assign('vo',$vo);
        $this->display('second_level_edit');
    }
    /**
     * 二级分类编辑操作
     *
     * */
    public function second_level_edit_do(){
        $flid = I('fl',''); //一级分类id
        $slid = I('id',''); //二级分类id
        $name = I('name','');
        $status = I('status','');
        if(empty($flid)){
            return Response::show(300,'请选择一级分类');
        }
        if(empty($name)){
            return Response::show(300,'请输入分类姓名');
        }
        if(!empty($name) && !empty($status) && !empty($flid) && !empty($slid)){
            $where['id']=array('eq',$slid);
            $data = array(
                'flid'=>$flid,
                'name'=>$name,
                'status'=>$status,
            );
            $result = M('mall_slevel')->where($where)->save($data);
            if($result !== false){
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        }
    }
    /**
     * 二级分类停用操作
     *
     * */
    public function second_level_stop(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>2,
            );
            $where['id'] = array('in',$ids);
            $result = M('mall_slevel')->where($where)->save($data);
            if ($result !== false) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 二级分类启用操作
     *
     * */
    public function second_level_start(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>1,
            );
            $where['id'] = array('in',$ids);
            $result = M('mall_slevel')->where($where)->save($data);
            if ($result !== false) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 三级分类
     *
     * */
    public function third_level(){
        $this->display('third_level_lis');
    }
    /**
     * 三级分类列表数据
     *
     * */
    public function third_level_info(){
        $tl = M('mall_tlevel');//三级分类表
        $page1 = I('pageIndex',0);
        $page = $page1+1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        $status = I('status','');
        $where = '';
        $where_num = '';
        if(!empty($name)){
            $where['a.name'] = array('like','%'.$name.'%');
            $where_num['name'] = array('like','%'.$name.'%');
        }if(!empty($status)){
            $where['a.status'] = array('eq',$status);
            $where_num['status'] = array('eq',$status);
        }

//        $where['d.status'] = array('neq',0);
        $num = $tl->where($where_num)->count('id');
        $result = $tl
            ->join('as a left join dsy_mall_slevel as b on a.slid = b.id')
            ->join('left join dsy_mall_flevel as c on b.flid = c.id')
            ->join('left join dsy_mall_rcmd_tlevel as d on a.id = d.tlid')
            ->where($where)
            ->page($page,$limit)
            ->order('a.time desc')
            ->field('d.tlid as rec,a.id,a.name,a.time,a.pic,b.name as sname,c.name as fname
            ,case a.status when 1 then \'启用中\'when 2 then \'停用中\' end as status
            ')
            ->select();
//        dump($result);exit;
        return Response::mjson($result,$num);
    }
    /**
     * 三级分类新增
     *
     * */
    public function third_level_add(){
        $this->assign('first', getFLevel());
        $this->display('third_level_add');
    }

    public function getSecondLevel()
    {
        $id = I('id', '');
        $result = getSLevel($id);
        $array = [];
        foreach ($result as $value) {
            $array[] = '<option value="' . $value['id'] . '">' . $value['name'] . '</option>';
        }
        $string = implode('', $array);
        $this->ajaxReturn($string);
    }

    public function getThirdLevel()
    {
        $id = I('id', '');
        $result = getTLevel($id);
        $array = [];
        foreach ($result as $value) {
            $array[] = '<option value="' . $value['id'] . '">' . $value['name'] . '</option>';
        }
        $string = implode('', $array);
        $this->ajaxReturn($string);
    }
    /**
     * 三级分类新增操作
     *
     * */
    public function third_level_add_do(){
        $slid = I('sl','');
        $name = I('name','');
        if(!empty($_FILES['pic'])){
            $pic = $_FILES['pic'];
        }else{
            return Response::show(300,'请上传分类图片');
        }
        if(!empty($_FILES['toppic'])){
            $toppic = $_FILES['toppic'];
        }else{
            return Response::show(300,'请上传首页图片');
        }
        if(empty($slid)){
            return Response::show(300,'请选择二级分类');
        }
        if (empty($name)) {
            return Response::show(300,'请输入分类名称');
        }
        $upload = uploadfile($pic);
        if(empty($upload)){
            return Response::show(300,'分类图上传失败');
        }
        $toppicupload = uploadfile($toppic);
        if(empty($toppicupload)){
            return Response::show(300,'推荐图上传失败');
        }
        $data = array(
            'slid'=>$slid,
            'name'=>$name,
            'status'=>2,
            'pic'=>$upload,
            'recpic'=>$toppicupload,
            'time'=>NOW,
        );
        //添加三级分类
//        $tlevel = M('mall_tlevel');
//        $tlevel->startTrans();
        $result = M('mall_tlevel')->add($data);

        //组合三级分类id
//        $tlid = $slid.$result;

        //添加三级分类id
//        $data_tlid['tlid'] = $tlid;
//        $where_tlid['id'] = array('eq',$result);
//        $result_tlid = $tlevel->where($where_tlid)->save($data_tlid);

        if($result){
//            $tlevel ->commit();
            return Response::show(200,'新增成功');
        } else {
//            $tlevel->rollback();
            return Response::show(400,'新增失败');
        }

    }
    /**
     * 三级分类编辑
     *修改点b.slid
     * */
    public function third_level_edit(){
        $id = I('id','');
        $where['a.id'] = array('eq',$id);
        $vo = M('mall_tlevel')
            ->join('as a left join dsy_mall_slevel as b on a.slid = b.id')
            ->where($where)
            ->field('a.id,a.pic,a.recpic,a.name,a.slid,a.jd_catid,a.status,b.flid')
            ->find();
        $vo['jd_catid'] = empty($vo['jd_catid']) ? '' : $vo['jd_catid'];

        $this->assign('first', getFLevel());
        $this->assign('second', getSLevel($vo['flid']));

        $this->assign('show_pic', format_img($vo['pic'], IMG_VIEW));
        $this->assign('show_recpic', format_img($vo['recpic'], IMG_VIEW));

        $this->assign('vo',$vo);
        $this->display('third_level_edit');
    }
    /**
     * 三级分类编辑操作
     *
     * */
    public function third_level_edit_do(){
        $flid = I('fl',''); //一级分类id
        $slid = I('sl',''); //二级分类slid
        $tlid = I('id',''); //三级分类id
        $name = I('name','');
        $status = I('status','');

        if(empty($flid)){
            return Response::show(300,'请选择一级分类');
        }
        if(empty($slid)){
            return Response::show(300,'请选择二级分类');
        }
        if(empty($name)){
            return Response::show(300,'请输入分类姓名');
        }
        if(!empty($name) && !empty($status) && !empty($flid) && !empty($slid)){
            $where['id']=array('eq',$tlid);
            $data = array(
                'slid'=>$slid,
                'name'=>$name,
                'status'=>$status,
            );
            if(!empty($_FILES['pic'])){
                $upload = uploadfile($_FILES['pic']);
                if(!empty($upload)){
                    $data['pic'] = $upload;
                } else {
                    return Response::show(300,'图片上传失败');
                }
            }
            if(!empty($_FILES['toppic'])){
                $topupload = uploadfile($_FILES['toppic']);
                if(!empty($topupload)){
                    $data['recpic'] = $topupload;
                } else {
                    return Response::show(300,'图片上传失败');
                }
            }

            $result = M('mall_tlevel')->where($where)->save($data);
            if($result !== false){
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        }
    }
    /**
     * 三级分类停用操作
     *
     * */
    public function third_level_stop(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>2,
            );
            $where['id'] = array('in',$ids);
            $result = M('mall_tlevel')->where($where)->save($data);
            if ($result !== false) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 三级分类启用操作
     *
     * */
    public function third_level_start(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>1,
            );
            $where['id'] = array('in',$ids);
            $result = M('mall_tlevel')->where($where)->save($data);
            if ($result !== false) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }

    /**
     * 三级分类推荐操作
     *
     * */
    public function third_level_rec(){
        $ids = I('ids','');
        if(!empty($ids)){
            $data = array();
            foreach($ids as $value){
                $data[]['tlid'] = $value;
            }
            $result = M('mall_rcmd_tlevel')->addAll($data);
            if ($result !== false) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }

    /**
     * 三级分类取消推荐操作
     *
     * */
    public function third_level_delrec(){
        $ids = I('ids','');
        if(!empty($ids)){
            if(count($ids)>1){
                $where = 'tlid in('.implode(',',$ids).')';
            }else{
                $where = 'tlid='.$ids[0];
            }
            $model = M('mall_rcmd_tlevel');
            $result=$model->where($where)->delete();
            if ($result !== false) {
                return Response::show(200,'操作成功');
            } else {
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }




}
