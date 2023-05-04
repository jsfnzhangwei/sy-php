<?php
/**
 * 快递公司模块
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/10 0010
 * Time: 下午 3:24
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;
use Think\Exception;

class LogisticsController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    public function index(){

        $this->display();
    }


    public function info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;

        $model = M('mall_logistics','dsy_');
        $info = $model
            ->page($page,$limit)
            ->select();

        $count = $model->count();
        return Response::mjson($info,$count);
    }



    public function add_index(){
        $id = I('id','');
        if(!empty($id)){
            $model = M('mall_logistics','dsy_');
            $info = $model->find($id);
            $this->assign('id',$id);
            $this->assign('info',$info);
        }
        $this->display('add');
    }


    public function add_do(){
        $id = I('id','');
        $name = trim(I('name',''));
        $num = trim(I('num',''));
        $model = M('mall_logistics','dsy_');
        if(empty($id)){
            if(empty($name)||empty($num)){
                return Response::show(300,'请填写完整');
            }
            $data['name'] = $name;
            $data['num'] = $num;
            $data['time'] = time();
            $add = $model->add($data);
        }else{
            $where['id'] = array('eq',$id);
            $check = $model->find($id);
            if($check['name']==$name && $check['num'] == $num){
                return Response::show(300,'请修改后不要和原来一致');
            }
            if(!empty($name)){
                $data['name'] = $name;
            }
            if(!empty($num)){
                $data['num'] = $num;
            }
            $save = $model->where($where)->save($data);
        }
        if($add != false || $save != false){
            return Response::show(200,'操作成功');
        }else{
            return Response::show(400,'操作失败');
        }

    }





}