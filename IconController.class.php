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

class IconController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    public function index(){
        $this->display();
    }
    public function icon_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $icon_title = I('icon_title');

        if(!empty($icon_title)){
            $where['icon_title'] = array('like',"%$icon_title%");
        }
        $result = M('index_icon')
                    ->where($where)
                    ->page($page,$limit)
                    ->select();
        $num = M('index_icon')
                    ->where($where)
                    ->count();
        foreach($result as $key => $val){
            $result[$key]['icon_class'] = icon_class($val['icon_class']);
        }

        return Response::mjson($result,$num);
    }

    public function icon_open_stop(){
        $ids = I('ids');
        $state = I('state');//0是启用  1是禁用
        if(empty($ids) || $state == ''){
            return Response::show(300,'缺少参数');
        }

        $model = M('index_icon');
        $where['id'] = array('in',$ids); 
        $icon_result = $model->field('icon_title')->where($where)->select();
        $icon_name = '';
        foreach($icon_result as $key => $val){
            $icon_name .= $val['icon_title'] . ',';
        }
        $msg = '';
        $admin_log = '';
        if($state == 0){
            $msg = '启用';
            $admin_log = '启用应用。应用名称：' . $icon_name;
        }else{
            $msg = '禁用';
            $admin_log = '禁用应用。应用名称：' . $icon_name;
        }
        $result = $model->where($where)->setField(['icon_state'=>$state]);
        if($result){
            admin_log($admin_log, 1, 'dsy_index_icon:' . implode(',',$ids));
            return Response::show(200,$msg . '成功');
        }else{
            admin_log($admin_log, 0, 'dsy_index_icon:' . implode(',',$ids));
            return Response::show(300,$msg . '失败');
        }
    }

    public function icon_update_fixed(){
        $id = I('id');
        $status = I('status');//0是取消  1是设为
        if(empty($id) || $status == ''){
            return Response::show(300,'缺少参数');
        }

        $model = M('index_icon');
        $where['id'] = array('eq',$id); 
        $icon_title = $model->field('icon_title')->where($where)->find();
        $msg = '';
        $admin_log = '';
        if($status == 0){
            $msg = '取消';
            $admin_log = '取消应用为固定应用。应用名称：' . $icon_title['icon_title'];
        }else{
            $fixed_num = $model->where(['icon_fixed'=>1])->count();
            if($fixed_num >= 4){
                return Response::show(300,'固定应用最多设置四个,请先取消至少一个固定应用再启用');
            }
            $msg = '启用';
            $admin_log = '启用应用为固定应用。应用名称：' . $icon_title['icon_title'];
        }
        $result = $model->where($where)->setField(['icon_fixed'=>$status]);
        if($result){
            admin_log($admin_log, 1, 'dsy_index_icon:' . $id);
            return Response::show(200,$msg . '成功');
        }else{
            admin_log($admin_log, 0, 'dsy_index_icon:' . $id);
            return Response::show(300,$msg . '失败');
        }
    }

    public function icon_index_add(){
        $class = icon_class();
        $this->assign('class',$class);
        $this->display();
    }

    public function icon_insert(){
        $icon_class = I('icon_class');
        $icon_title = I('icon_title');
        $icon_url = I('icon_url');
        $icon_fixed = I('icon_fixed');
        $icon_state = I('icon_state');
        $icon_sort = I('icon_sort',99);

        if(empty($icon_class)){
            return Response::show(300,'应用分类不能为空');
        }
        if(empty($icon_title)){
            return Response::show(300,'应用标题不能为空');
        }
        if(empty($icon_url)){
            return Response::show(300,'图标不能为空');
        }
        if($icon_fixed == 1){
            $fixed_num = M('index_icon')->where(['icon_fixed'=>1])->count();
            if($fixed_num >= 4){
                return Response::show(300,'固定应用最多设置四个,请先取消至少一个固定应用再启用');
            }
        }
        $data = [
            'icon_class' => $icon_class,
            'icon_title' => $icon_title,
            'icon_url' => $icon_url,
            'icon_fixed' => $icon_fixed,
            'icon_state' => $icon_state,
            'icon_sort' => empty($icon_sort) ? 99 : $icon_sort,
        ];

        $result = M('index_icon')->add($data);
        if($result){
            return Response::show(200,'添加成功');
        }else{
            return Response::show(200,'添加失败');
        }
    }

    public function icon_index_edit(){
        $id = I('id');
        $class = icon_class();
        $result = M('index_icon')->where(['id'=>$id])->find();
        $this->assign('result',$result);
        $this->assign('class',$class);
        $this->display('icon_index_add');
    }
    public function icon_update(){
        $id = I('id');
        $icon_class = I('icon_class');
        $icon_title = I('icon_title');
        $icon_url = I('icon_url');
        $icon_fixed = I('icon_fixed');
        $icon_state = I('icon_state');
        $icon_sort = I('icon_sort',99);

        if(empty($id)){
            return Response::show(300,'请至少选择一条应用');
        }
        if(empty($icon_class)){
            return Response::show(300,'应用分类不能为空');
        }
        if(empty($icon_title)){
            return Response::show(300,'应用标题不能为空');
        }
        if(empty($icon_url)){
            return Response::show(300,'图标不能为空');
        }
        if($icon_fixed == 1){
            $fixed_num = M('index_icon')->where(['icon_fixed'=>array('eq',1),'id'=>array('neq',$id)])->count();
            if($fixed_num >= 4){
                return Response::show(300,'固定应用最多设置四个,请先取消至少一个固定应用再启用');
            }
        }
        $data = [
            'icon_class' => $icon_class,
            'icon_title' => $icon_title,
            'icon_url' => $icon_url,
            'icon_fixed' => $icon_fixed,
            'icon_state' => $icon_state,
            'icon_sort' => empty($icon_sort) ? 99 : $icon_sort,
            'update_time' => date('Y-m-d H:i:s',time())
        ];

        $result = M('index_icon')->where(['id'=>$id])->save($data);
        if($result){
            return Response::show(200,'修改成功');
        }else{
            return Response::show(200,'修改失败');
        }
    }
}