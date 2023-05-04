<?php
/**
 * Created by PhpStorm.
 * User: 苏鹰
 * Date: 2020/4/29
 * Time: 14:39
 */

namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;

class ShopController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }
    /**
     * 一级分类页面
     */
    public function index(){
        $this->display();
    }
    /**
     * 一级分类数据
     */
    public function shop_class_first(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        $status = I('status','');
        $sort = I('sort','');

        $where['category_level'] = array('eq',1);//一级分类
        if(!empty($name)){
            $where['category_name'] = array('like',"%$name%");
        }
        if($status != ''){
            $where['status'] = array('eq',$status);
        }
        if($sort != ''){
            if($sort == 0){
                $order = 'sort asc,create_time desc';
            }elseif($sort == 1){
                $order = 'sort desc';
            }
        }else{
            $order = 'category_id desc';
        }
        $result = M('sn_category')
                ->field('category_id,category_name,create_time,sort,
                case status when 1 then \'启用中\'when 0 then \'停用中\' end as status')
                ->where($where)
                ->order($order)
                ->page($page,$limit)
                ->select();
        $num = M('sn_category')->where($where)->count();
        return Response::mjson($result,$num);
    }
    /**
     * 编辑一级分类
     */
    public function shop_first_edit(){
        $id = I('id','');
        if(!empty($id)){
            $result = M('sn_category')
                    ->field('category_id,category_name,sort,status')
                    ->where('category_id=' . $id)
                    ->find();
        }else{
            $result = [];
        }
        $this->assign('result',$result);
        $this->display();
    }
    /**
     * 保存分类编辑
     */
    public function shop_first_save(){
        $id = I('id');
        $data = [
            'category_name' => str_replace(' ','', I('category_name')),
            'sort' => I('sort') == 0 ? 1000 : I('sort'),
            'status' => I('status',1),
            'update_time' => date('Y-m-d H:i:s',time())
        ];
        if(preg_match("/^[\\/]+$/u", $data['category_name'])){
            return Response::show(300,'分类名称不能由纯特殊字符组成');
        }
        if(empty($data['category_name'])){
            return Response::show(300,'请填写分类名称');
        }
        if(empty($id)){
            $check_name = M('sn_category')->where(['category_name'=>$data['category_name'],'category_level'=>1])->find();
            if($check_name){
                return Response::show(300,'一级分类名称不能重复');
            }
            $data['category_level'] = 1;
            $data['category_code'] = mt_rand(9999,99999);
            $result = M('sn_category')->add($data);
        }else{
            $check_name = $this->shop_check_name($id,1,$data['category_name']);
            if($check_name == 1){
                return Response::show(300,'一级分类名称不能重复');
            }
            if($data['status'] == 0){
                $admin_log = '停用一级分类:【'. $data['category_name'].'】及该分类下所有分类和商品';
                $update = $this->shop_stop_allClass(1,$id,0);
            }
            $result = M('sn_category')->where('category_id='.$id)->save($data);
            if($data['status'] == 0){
                if($result !== false){
                    admin_log($admin_log, 1, 'dsy_sn_category:' . $id);
                }else{
                    admin_log($admin_log, 0, 'dsy_sn_category:' . $id);
                }
            }
        }
        if($result !== false){
            return Response::show(200,'编辑成功');
        }else{
            return Response::show(300,'编辑失败');
        }
    }
    /**
     * 判断名字是否重复
     */
    public function shop_check_name($id,$level,$name){
        $check = 0;
        $check_name = M('sn_category')
                        ->field('category_name')
                        ->where(['category_id'=>$id])
                        ->find();
        if($name != $check_name['category_name']){
            $check_name = M('sn_category')->where(['category_name'=>$name,'category_level'=>$level])->find();
            if($check_name){
                $check = 1;
            }
        }
        return $check;
    }
    /**
     * 启用一级分类
     */
    public function shop_first_open(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>1,
            );
            $where['category_id'] = array('in',$ids);
            $infos = M('sn_category')->field('category_name')->where($where)->select();
            foreach($infos as $val){
                $infos_str[] = "【" . $val['category_name'] . "】";
            }
            $admin_log = '启用一级分类:'. implode(',', $infos_str);
            $result = M('sn_category')->where($where)->save($data);
            if ($result !== false) {
                admin_log($admin_log, 1, 'dsy_sn_category:' . $ids);
                return Response::show(200,'操作成功');
            } else {
                admin_log($admin_log, 0, 'dsy_sn_category:' . $ids);
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 停用一级分类
     */
    public function shop_first_stop(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>0,
            );
            $where['category_id'] = array('in',$ids);
            $infos = M('sn_category')->field('category_name')->where($where)->select();
            foreach($infos as $val){
                $infos_str[] = "【" . $val['category_name'] . "】";
            }
            $admin_log = '停用一级分类:'. implode(',', $infos_str);
            $update = $this->shop_stop_allClass(1,$ids,0);
            if($update){
                $result = M('sn_category')->where($where)->save($data);
                if ($result !== false) {
                    admin_log($admin_log, 1, 'dsy_sn_category:' . $ids);
                    return Response::show(200,'操作成功');
                } else {
                    admin_log($admin_log, 0, 'dsy_sn_category:' . $ids);
                    return Response::show(301,'操作失败');
                }
            }else{
                return Response::show(302,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 二级分类首页
     */
    public function shop_second_index(){
        $this->display();
    }
    /**
     * 二级分类数据
     */
    public function shop_class_second(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        $status = I('status','');
        $sort = I('sort','');

        $where['a.category_level'] = array('eq',2);//一级分类
        if(!empty($name)){
            $where['a.category_name'] = array('like',"%$name%");
        }
        if($status != ''){
            $where['a.status'] = array('eq',$status);
        }
        if($sort != ''){
            if($sort == 0){
                $order = 'a.sort asc,a.create_time desc';
            }elseif($sort == 1){
                $order = 'a.sort desc';
            }
        }else{
            $order = 'a.category_id desc';
        }
        $result = M('sn_category')
                ->alias('a')
                ->field('a.category_id,a.category_name,a.create_time,a.sort,b.category_name as first_name,
                case a.status when 1 then \'启用中\'when 0 then \'停用中\' end as status')
                ->join('left join dsy_sn_category as b on b.category_id=a.pid')
                ->where($where)
                ->order($order)
                ->page($page,$limit)
                ->select();
        $num = M('sn_category')
                ->alias('a')
                ->join('left join dsy_sn_category as b on a.pid=b.category_id')
                ->where($where)
                ->count();
        return Response::mjson($result,$num);
    }
    /**
     * 编辑二级分类
     */
    public function shop_second_edit(){
        $id = I('id');
        if(!empty($id)){
            $result = M('sn_category')
                        ->field('category_id,category_name,sort,pid,status')
                        ->where('category_id='.$id)
                        ->find();
        }else{
            $result = [];
        }
        $level = $this->shop_class();
        $this->assign('result',$result);
        $this->assign('level',$level);
        $this->display();
    }
    /**
     * 保存二级分类编辑
     */
    public function shop_second_save(){
        $id = I('id');
        $data = [
            'category_name' => str_replace(' ','', I('category_name')),
            'pid' => I('first_class'),
            'sort' => I('sort') == 0 ? 1000 : I('sort'),
            'status' => I('status',1),
            'update_time' => date('Y-m-d H:i:s',time())
        ];
        if(empty($data['pid'])){
            return Response::show(300,'请选择上级分类');
        }
        if(preg_match("/^[\\/]+$/u", $data['category_name'])){
            return Response::show(300,'分类名称不能由纯特殊字符组成');
        }
        if(empty($data['category_name'])){
            return Response::show(300,'请填写分类名称');
        }
        if(empty($id)){
            $check_name = M('sn_category')->where(['category_name'=>$data['category_name'],'category_level'=>2])->find();
            if($check_name){
                return Response::show(300,'二级分类名称不能重复');
            }
            $data['category_level'] = 2;
            $data['category_code'] = mt_rand(9999,99999);
            $result = M('sn_category')->add($data);
        }else{
            $check_name = $this->shop_check_name($id,2,$data['category_name']);
            if($check_name == 1){
                return Response::show(300,'二级分类名称不能重复');
            }
            if($data['status'] == 0){
                $admin_log = '停用二级分类:【'. $data['category_name'].'】';
                $update = $this->shop_stop_allClass(2,$id,0);
            }
            $result = M('sn_category')->where('category_id='.$id)->save($data);
            if($data['status'] == 0){
                if($result !== false){
                    admin_log($admin_log, 1, 'dsy_sn_category:' . $id);
                }else{
                    admin_log($admin_log, 0, 'dsy_sn_category:' . $id);
                }
            }
        }
        if($result !== false){
            return Response::show(200,'编辑成功');
        }else{
            return Response::show(300,'编辑失败');
        }
    }
    /**
     * 启用二级分类
     */
    public function shop_second_open(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>1,
            );
            $where['category_id'] = array('in',$ids);
            $infos = M('sn_category')->field('category_name')->where($where)->select();
            foreach($infos as $val){
                $infos_str[] = "【" . $val['category_name'] . "】";
            }
            $admin_log = '启用二级分类:'. implode(',', $infos_str);
                $result = M('sn_category')->where($where)->save($data);
                if ($result !== false) {
                    admin_log($admin_log, 1, 'dsy_sn_category:' . $ids);
                    return Response::show(200,'操作成功');
                } else {
                    admin_log($admin_log, 0, 'dsy_sn_category:' . $ids);
                    return Response::show(400,'操作失败');
                }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 停用二级分类
     */
    public function shop_second_stop(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>0,
            );
            $where['category_id'] = array('in',$ids);
            $infos = M('sn_category')->field('category_name')->where($where)->select();
            foreach($infos as $val){
                $infos_str[] = "【" . $val['category_name'] . "】";
            }
            $admin_log = '停用二级分类:'. implode(',', $infos_str);
            $update = $this->shop_stop_allClass(2,$ids,0);
            if($update){
                $result = M('sn_category')->where($where)->save($data);
                if ($result !== false) {
                    admin_log($admin_log, 1, 'dsy_sn_category:' . $ids);
                    return Response::show(200,'操作成功');
                } else {
                    admin_log($admin_log, 0, 'dsy_sn_category:' . $ids);
                    return Response::show(400,'操作失败');
                }
            }else{
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 三级分类首页
     */
    public function shop_third_index(){
        $this->display();
    }
    /**
     * 三级分类数据
     */
    public function shop_class_third(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        $status = I('status','');
        $sort = I('sort','');

        $where['a.category_level'] = array('eq',3);//一级分类
        if(!empty($name)){
            $where['a.category_name'] = array('like',"%$name%");
        }
        if($status != ''){
            $where['a.status'] = array('eq',$status);
        }
        if($sort != ''){
            if($sort == 0){
                $order = 'a.sort asc,a.create_time desc';
            }elseif($sort == 1){
                $order = 'a.sort desc';
            }
        }else{
            $order = 'a.category_id desc';
        }
        $result = M('sn_category')
                ->alias('a')
                ->field('a.category_id,a.category_name,a.img,a.create_time,a.sort,b.category_name as second_name,c.category_name as first_name,
                case a.status when 1 then \'启用中\'when 0 then \'停用中\' end as status')
                ->join('left join dsy_sn_category as b on a.pid=b.category_id')
                ->join('left join dsy_sn_category as c on b.pid=c.category_id')
                ->where($where)
                ->order($order)
                ->page($page,$limit)
                ->select();
        $num = M('sn_category')
                ->alias('a')
                ->join('left join dsy_sn_category as b on a.pid=b.category_id')
                ->join('left join dsy_sn_category as c on b.pid=c.category_id')
                ->where($where)
                ->count();
        return Response::mjson($result,$num);
    }
    /**
     * 编辑三级分类
     */
    public function shop_third_edit(){
        $id = I('id');
        if(!empty($id)){
            $result = M('sn_category')
                        ->alias('a')
                        ->field('a.category_id,a.category_name,a.sort,a.pid,a.status,a.img,b.category_id as second,c.category_id as first')
                        ->join('left join dsy_sn_category as b on a.pid=b.category_id')
                        ->join('left join dsy_sn_category as c on b.pid=c.category_id')
                        ->where('a.category_id='.$id)
                        ->find();
        }else{
            echo 3;
        }
        $level_first = $this->shop_class();
        if(!empty($result['first'])){
            $level_second = $this->shop_class(2,$result['first']);
        }else{
            $level_second = $this->shop_class(2,$level_first[0]['category_id']);
        }
        $this->assign('result',$result);
        $this->assign('level_first',$level_first);
        $this->assign('level_second',$level_second);
        $this->assign('show_pic', format_img($result['img'], IMG_VIEW));
        $this->display();
    }
    /**
     * 保存三级分类编辑
     */
    public function shop_third_save(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'数据异常，请重新编辑提交');
        }
        $data = [
            'category_name' => str_replace(' ','', I('category_name')),
            'pid' => I('level_second'),
            'sort' => I('sort') == 0 ? 1000 : I('sort'),
            'status' => I('status',1),
            'update_time' => date('Y-m-d H:i:s',time())
        ];
        if(empty($data['pid'])){
            return Response::show(300,'请选择上级分类');
        }
        if(preg_match("/^[\\/]+$/u", $data['category_name'])){
            return Response::show(300,'分类名称不能由纯特殊字符组成');
        }
        if(empty($data['category_name'])){
            return Response::show(300,'请填写分类名称');
        }
        $check_name = $this->shop_check_name($id,3,$data['category_name']);
        if($check_name == 1){
            return Response::show(300,'三级分类名称不能重复');
        }
        if(!empty($_FILES['img'])){
            $upload = uploadfile($_FILES['img']);
            if(!empty($upload)){
                $data['img'] = $upload;
            } else {
                return Response::show(300,'图片上传失败');
            }
        } 
        if($data['status'] == 0){
            $admin_log = '停用三级分类:【'. $data['category_name'].'】';
            $update = $this->shop_stop_allClass(3,$id,0);
        }
        $result = M('sn_category')->where('category_id='.$id)->save($data);
        if($result !== false){
            if($data['status'] == 0){
                admin_log($admin_log, 1, 'dsy_sn_category:' . $id);
            }
            return Response::show(200,'编辑成功');
        }else{
            if($data['status'] == 0){
                admin_log($admin_log, 0, 'dsy_sn_category:' . $id);
            }
            return Response::show(300,'编辑失败');
        }
    }
    /**
     * 启用三级分类
     */
    public function shop_third_open(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>1,
            );
            $where['category_id'] = array('in',$ids);
            $infos = M('sn_category')->field('category_name')->where($where)->select();
            foreach($infos as $val){
                $infos_str[] = "【" . $val['category_name'] . "】";
            }
            $admin_log = '启用三级分类:'. implode(',', $infos_str);
            $result = M('sn_category')->where($where)->save($data);
            if ($result !== false) {
                admin_log($admin_log, 1, 'dsy_sn_category:' . $ids);
                return Response::show(200,'操作成功');
            } else {
                admin_log($admin_log, 0, 'dsy_sn_category:' . $ids);
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 停用三级分类
     */
    public function shop_third_stop(){
        $ids = I('ids','');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>0,
            );
            $where['category_id'] = array('in',$ids);
            $infos = M('sn_category')->field('category_name')->where($where)->select();
            foreach($infos as $val){
                $infos_str[] = "【" . $val['category_name'] . "】";
            }
            $admin_log = '停用三级分类:'. implode(',', $infos_str);
            $update = $this->shop_stop_allClass(3,$ids,0);
            if($update){
                $result = M('sn_category')->where($where)->save($data);
                if ($result !== false) {
                    admin_log($admin_log, 1, 'dsy_sn_category:' . $ids);
                    return Response::show(200,'操作成功');
                } else {
                    admin_log($admin_log, 0, 'dsy_sn_category:' . $ids);
                    return Response::show(400,'操作失败');
                }
            }else{
                return Response::show(400,'操作失败');
            }
        } else {
            return Response::show(300,'请选择需要操作的分类');
        }
    }
    /**
     * 停用/启用分类停用/启用下面所有分类
     * @param type分类等级
     * @param id分类的category_id
     * @param status分类的启用停用状态
     */
    public function shop_stop_allClass($type,$id,$status){
        $model = M('sn_category');
        $model->startTrans();
        if($type == 1){
            $where['pid'] = array('in',$id);
            $second = $model
                        ->field('category_id')
                        ->where($where)
                        ->select();
            if(empty($second)){//如果没有二级分类则提交
                $model->commit();
                return true;
            }
            $result1 = $model
                        ->where($where)
                        ->setField('status',$status);
            $str_id = '';
            foreach($second as $val){
                $str_id .= $val['category_id'] . ',';
            }
            $second_where['pid'] = array('in',rtrim($str_id,','));
            $thrid = $model
                        ->field('category_code')
                        ->where($second_where)
                        ->select();
            if(empty($thrid)){//如果没有二级分类则提交
                $model->commit();
                return true;
            }
            $result2 = $model
                        ->where($second_where)
                        ->setField('status',$status);
            $str_code = '';
            foreach($thrid as $val){
                $str_code .= $val['category_code'] . ',';
            }
            $code_where['category_code'] = array('in',rtrim($str_code,','));
            $update_product = M('sn_product')->where($code_where)->save(['my_status'=>$status]);
            if($update_product !== false){
                if(!empty($str_code)){
                    $update_es = $this->shop_update_es($code_where);
                    if($update_es['code'] == 200){
                        $model->commit();
                        return true;
                    }else{
                        $model->rollback();
                        return false;
                    }
                }else{
                    $model->commit();
                    return true;
                }
            }else{
                $model->rollback();
                return false;
            }
        }elseif($type == 2){//二级分类停用/启用
            $where['pid'] = array('in',$id);
            $third = $model
                        ->field('category_code')
                        ->where($where)
                        ->select();
            if(empty($thrid)){//如果没有二级分类则提交
                $model->commit();
                return true;
            }
            $result1 = $model
                        ->where($where)
                        ->setField('status',$status);
            $str_code = '';
            foreach($third as $val){
                $str_code .= $val['category_code'] . ',';
            }
            $code_where['category_code'] = array('in',rtrim($str_code,','));
            $update_product = M('sn_product')->where($code_where)->save(['my_status'=>$status]);
            if($update_product !== false){
                if(!empty($str_code)){
                    $update_es = $this->shop_update_es($code_where);
                    if($update_es['code'] == 200){
                        $model->commit();
                        return true;
                    }else{
                        $model->rollback();
                        return false;
                    }
                }else{
                    $model->commit();
                    return true;
                }
            }else{
                $model->rollback();
                return false;
            }
        }elseif($type == 3){
            $where['category_id'] = array('in',$id);
            $third = $model
                        ->field('category_code')
                        ->where($where)
                        ->select();
            $str_code = '';
            foreach($third as $val){
                $str_code .= $val['category_code'] . ',';
            }
            $code_where['category_code'] = array('in',rtrim($str_code,','));
            $update_product = M('sn_product')->where($code_where)->save(['my_status'=>$status]);
            if($update_product !== false){
                if(!empty($str_code)){
                    $update_es = $this->shop_update_es($code_where);
                    if($update_es['code'] == 200){
                        $model->commit();
                        return true;
                    }else{
                        $model->rollback();
                        return false;
                    }
                }else{
                    $model->commit();
                    return true;
                }
            }else{
                $model->rollback();
                return false;
            }
        }
    }
    /**
     * 批量更行商品到es
     */
    public function shop_update_es($code_where){
        $sku_ids = M('sn_product')->field('sku_id')->where($code_where)->select();
        $sku_id = '';
        foreach($sku_ids as $key=>$val){
            $sku_id[] = $val['sku_id'];
        }
        $update_es = $this->text_curl(JAVA_API_URL_SN . '/before/search/deleteAllBySkuIds',json_encode($sku_id));
        return $update_es;
    }
    /**
        * curl请求
    **/
    public function text_curl($url,$post_data){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json; charset=utf-8','Content-Length:' . strlen($post_data)));
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // post数据
        curl_setopt($ch, CURLOPT_POST, 1);
        // post的变量
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $output = curl_exec($ch);
        curl_close($ch);
        //打印获得的数据
        return json_decode($output,true);
    }
    /**
     * 分类联动
     */
    public function shop_class_linkage(){
        $id = I('id');
        $result = $this->shop_class(2,$id);
        return Response::show(200,$result);
    }
    /**
     * 首页商品菜单管理
     */
    public function shop_menu_index(){
        $this->display();
    }
    /**
     * 菜单管理数据
     */
    public function shop_menu_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $location = I('location','');
        $status = I('status','');

        $where = [];
        if(!empty($location)){
            $where['location'] = array('eq',$location);
        }
        if($status != ''){
            $where['status'] = array('eq',$status);
        }
        
        $result = M('sn_menu')
                ->field('menu_id,menu_name,menu_img,data_id,views,sort,create_time,update_time,location,data_type,
                case status when 1 then \'启用中\'when 0 then \'停用中\' end as status')
                ->where($where)
                ->order('sort asc')
                ->page($page,$limit)
                ->select();
        foreach($result as $key=>$val){
            $result[$key]['menu_img'] = format_img($val['menu_img'], IMG_VIEW);
        }
        $num = M('sn_menu')->where($where)->count();
        return Response::mjson($result,$num);
    }
    /**
     * 商品菜单启用/停用
     */
    public function shop_menu_open(){
        $ids = I('ids','');
        $status = I('status');
        if(!empty($ids)){
            $ids = implode(',',$ids);
            $data = array(
                'status'=>$status,
            );
            $where['menu_id'] = array('in',$ids);
            $result = M('sn_menu')->where($where)->save($data);
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
     * 商品菜单修改
     */
    public function shop_menu_edit(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'提交数据异常,请稍后尝试');
        }
        $result = M('sn_menu')
                    ->field('menu_id,location,menu_name,menu_img,sort,data_id,data_type,location')
                    ->where('menu_id=' . $id)
                    ->find();
        if(!empty($result['menu_img'])){
            $img = format_img($result['menu_img'], IMG_VIEW);
        }
        $this->assign('url',JAVA_API_URL_SN);
        $this->assign('result',$result);
        $this->assign('img',$img);
        $this->display();
    }
    /**
     * 商品菜单修改保存
     */
    public function shop_menu_save(){
        $id = I('id');
        if(empty($id)){
            Response::show(300,'提交数据异常,请稍后尝试');
        }
        $type = M('sn_menu')->field('location')->where('menu_id=' . $id)->find();//查询菜单类型
        $data = [
            'location' => I('location',''),
            'menu_name' => I('menu_name'),
            'sort' => I('sort',20),
            'data_id' => I('data_id'),
            'data_type' => I('data_type'),
        ];
        $img = I('img','');
        if(empty($data['location'])){
            return Response::show(300,'菜单所属区块不能为空');
        }
        if(empty($data['menu_name'])){
            return Response::show(300,'标题不能为空');
        }elseif((strlen($data['menu_name'])) > 18){
            return Response::show(300,'标题不能超过6个字');
        }elseif(!preg_match("/^[\x{4e00}-\x{9fa5}A-Za-z0-9]+$/u", $data['menu_name'])){
            return Response::show(300,'标题不能有特殊字符和空格');
        }
        if(!empty($img)){
            $data['menu_img'] = $img;
        }
        $result = M('sn_menu')->where('menu_id=' . $id)->save($data);
        if($result !== false){
            if($type['location'] == 2 && $data['location'] == 2){//企业集采菜单不变
                return $this->ajaxReturn(['code'=>200,'message'=>'编辑成功','data'=>$id,'type'=>2]);
            }elseif($type['location'] == 1 && $data['location'] == 2){//苏宁首页边企业集采
                return $this->ajaxReturn(['code'=>200,'message'=>'编辑成功','data'=>$id,'type'=>2]);
            }elseif($type['location'] == 2 && $data['location'] == 1){//企业集采变苏宁
                return $this->ajaxReturn(['code'=>200,'message'=>'编辑成功','data'=>$id,'type'=>1]);
            }else{
                return $this->ajaxReturn(['code'=>200,'message'=>'编辑成功','data'=>$id,'type'=>3]);
            }
        }else{
            return Response::show(300,'编辑失败');
        }
    }
    /**
     * 商品菜单添加页面
     */
    public function shop_menu_add(){
        $this->assign('url',JAVA_API_URL_SN);
        $this->display();
    }
    /**
     * 商品菜单添加
     */
    public function shop_menu_insert(){
        $data = [
            'location' => I('location'),
            'menu_name' => I('menu_name'),
            'sort' => I('sort',20),
            'data_type' => I('data_type'),
            'data_id' => I('data_id'),
            'status' => 0,
        ];
        if(empty($data['location'])){
            return Response::show(300,'请选择所属区块');
        }
        if(empty($data['menu_name'])){
            return Response::show(300,'标题不能为空');
        }elseif((strlen($data['menu_name'])) > 18){
            return Response::show(300,'标题不能超过6个字');
        }elseif(!preg_match("/^[\x{4e00}-\x{9fa5}A-Za-z0-9]+$/u", $data['menu_name'])){
            return Response::show(300,'标题不能有特殊字符和空格');
        }
        if(empty($data['sort'])){
            return Response::show(300,'排序值不能为空');
        }elseif(!is_numeric($data['sort'])){
            return Response::show(300,'排序值请输入正确的数字格式');
        }elseif($data['sort'] > 20){
            return Response::show(300,'排序值请输入1-20的数字');
        }
        if(empty($data['data_type'])){
            return Response::show(300,'请选择链接类型');
        }
        if(empty($data['data_id'])){
            return Response::show(300,'链接地址不能为空');
        }
        $img = I('img');
        if(empty($img)){
            return Response::show(300,'请选择一个文件');
        }else{
            $data['menu_img'] = $img;
        }
        $result = M('sn_menu')->add($data);
        if($result){
            if($data['location'] == 2){
                return $this->ajaxReturn(['code'=>200,'message'=>'添加商品菜单成功','data'=>$result,'type'=>2]);
            }else{
                return $this->ajaxReturn(['code'=>200,'message'=>'添加商品菜单成功','data'=>$result,'type'=>1]);
            }
        }else{
            return Response::show(300,'添加商品菜单失败');
        }
    }
    /**
     * $type分类级别$id下级分类的父id
     * 公共获取分类的方法
     */
    public function shop_class($type = 1,$id = ''){
        $where['category_level'] = array('eq',$type);
        $where['status'] = array('eq',1);
        if(!empty($id)){
            $where['pid'] = array('eq',$id);
        }
        $result = M('sn_category')
                    ->field('category_id,category_name')
                    ->where($where)
                    ->select();
        return $result;
    }
    /**
     * 获取二级分类
     */
    public function shop_get_class(){
        $id = I('id','');
        $type = I('type');
        $result = $this->shop_class($type,$id);
        $str = '';
        foreach($result as $val){
            $str .= "<option value='".$val['category_id']."'>".$val['category_name']."</option>";
        }
        $this->ajaxReturn($str);
    }
    public function checkcard(){
        $data = [1,2,3,4,5];
        file_put_contents('Runtime/wz_log/wx' . date('Y-m-d') . '.log', '请求时间：' . date('Y-m-d H:i:s') . '接收数据：' . json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    }
}