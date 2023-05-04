<?php
/**
 * Created by PhpStorm.
 * User: HanHan
 * Date: 2018/8/6
 * Time: 19:45
 */

namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;

class EvaluateController extends Controller{
    /**
     * 苏宁商城评价首页
     */
    public function sn_index(){
        $this->assign('first',shopClass(1));
        $this->display();
    }
    /**
     * 苏宁商城评价数据
     */
    public function sn_evaluate_list(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $shop_name = I('shop_name','');
        $third = I('third','');
        $second = I('second','');
        $first = I('first','');
        $name = I('name');
        $where = [];
        if(!empty($shop_name)){
            $where['b.name'] = array('like',"%$shop_name%");
        }
        if(!empty($third)){
            $where['c.category_id'] = array('eq',$third);
        }elseif(!empty($second)){
            $where['d.category_id'] = array('eq',$second);
        }elseif(!empty($first)){
            $where['e.category_id'] = array('eq',$first);
        }
        if(!empty($name)){
            $where['f.user_name'] = array('eq',$name);
        }
        $result = M('sn_evaluation')
                        ->alias('a')
                        ->field('a.*,f.user_name,b.name')
                        ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                        ->join('LEFT JOIN dsy_sn_category as c on b.category_code=c.category_code')
                        ->join('LEFT JOIN dsy_sn_category as d on c.pid=d.category_id')
                        ->join('LEFT JOIN dsy_sn_category as e on d.pid=e.category_id')
                        ->join('LEFT JOIN t_user as f on a.user_id=f.user_id')
                        ->where($where)
                        ->order('a.create_time desc')
                        ->page($page,$limit)
                        ->select();
        $num = M('sn_evaluation')
            ->alias('a')
            ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
            ->join('LEFT JOIN dsy_sn_category as c on b.category_code=c.category_code')
            ->join('LEFT JOIN dsy_sn_category as d on c.pid=d.category_id')
            ->join('LEFT JOIN dsy_sn_category as e on d.pid=e.category_id')
            ->join('LEFT JOIN t_user as f on a.user_id=f.user_id')
            ->where($where)
            ->count();
        return Response::mjson($result,$num);
    }
    /**
     * 苏宁商城是否显示
     */
    public function shop_evaluate_dispay(){
        $id = I('id','');
        if(empty($id)){
            return Response::show(300,'请选择要隐藏或显示的评论');
        }
        $type = I('type','');
        $result = M('sn_evaluation')
                    ->where('id=' . $id)
                    ->setField('status',$type);
        if($result != false){
            if($type == 0){
                return Response::show(200,'该评论显示成功');
            }else{
                return Response::show(200,'该评论隐藏成功');
            }
        }else{
            if($type == 0){
                return Response::show(300,'该评论显示失败');
            }else{
                return Response::show(300,'该评论隐藏失败');
            }
        }
    }
    /**
     * 批量显示全部
     */
    public function shop_evaluate_alldisplay(){
        $ids = I('ids');
        $type = I('type');
        if(empty($ids)){
            return Response::show(300,'请选择要删除的评论');
        }
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $result = M('sn_evaluation')
                    ->where($where)
                    ->setField('status',$type);
        if($result != false){
            if($type == 0){
                return Response::show(200,'该评论显示成功');
            }else{
                return Response::show(200,'该评论隐藏成功');
            }
        }else{
            if($type == 0){
                return Response::show(300,'该评论显示失败');
            }else{
                return Response::show(300,'该评论隐藏失败');
            }
        }
    }
    /**
     * 删除评论
     */
    public function shop_evaluate_delete(){
        $ids = I('ids');
        if(empty($ids)){
            return Response::show(300,'请选择要删除的评论');
        }
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $result = M('sn_evaluation')
                    ->where($where)
                    ->delete();
        if($result){
            return Response::show(200,'该评论删除成功');
        }else{
            return Response::show(200,'该评论删除成功');
        }
    }
    /**
     * 评论详情
     */
    public function shop_evaluate_idea(){
        $id = I('id','');
        if(empty($id)){
            return Response::show(300,'请选择要查看的评论');
        }
        $result = M('sn_evaluation')
                        ->alias('a')
                        ->field('a.*,f.user_name,b.name')
                        ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                        ->join('LEFT JOIN t_user as f on a.user_id=f.user_id')
                        ->where('a.id=' . $id)
                        ->find();
        $str = '';
        if($result['score'] == 0){
            $str = '暂无打星';
        }else{
            for($i = 0; $i < $result['score'];$i++){
                $str .= "<i class='layui-icon' style='margin:0 2px 0 2px;color:red;'>&#xe600;</i>";
            }
        }
        $result['str'] = $str;
        $result['img'] = explode(',',$result['img']);
        $this->assign('result',$result);
        $this->display();
    }
    /**
     * 自营商品评价
     */
    public function zy_index(){
        $this->assign('type',2);
        $this->assign('first', getFLevel());
        $this->display();
    }
    /**
     * 京东商品评价
     */
    public function jd_index(){
        $this->assign('type',1);
        $this->assign('first', getFLevel());
        $this->display('zy_index');
    }
    /**
     * 自营京东商品评价数据
     */
    public function shop_evaluate_sydata(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $shop_name = I('shop_name','');
        $third = I('third','');
        $second = I('second','');
        $first = I('first','');
        $name = I('name');
        $type = I('type');

        $where['b.type'] = array('eq',$type);
        if(!empty($shop_name)){
            $where['b.name'] = array('like',"%$shop_name%");
        }
        if(!empty($third)){
            $where['b.tlid'] = array('eq',$third);
        }elseif(!empty($second)){
            $where['b.slid'] = array('eq',$second);
        }elseif(!empty($first)){
            $where['b.flid'] = array('eq',$first);
        }
        if(!empty($name)){
            $where['c.user_name'] = array('eq',$name);
        }
        $result = M('mall_product_evaluate')
                    ->alias('a')
                    ->field('a.*,b.name,c.user_name')
                    ->join('LEFT JOIN dsy_mall_product as b on a.pid=b.id')
                    ->join('LEFT JOIN t_user as c on a.uid=c.user_id')
                    ->where($where)
                    ->order('a.time desc')
                    ->page($page,$limit)
                    ->select();
        $num = M('mall_product_evaluate')
                    ->alias('a')
                    ->join('LEFT JOIN dsy_mall_product as b on a.pid=b.id')
                    ->join('LEFT JOIN t_user as c on a.uid=c.user_id')
                    ->where($where)
                    ->count();
        return Response::mjson($result,$num);
    }
    /**
     * 查看自营商品详情
     */
    public function shop_evaluate_syidea(){
        $id = I('id','');
        if(empty($id)){
            return Response::show(300,'请选择要查看的评论');
        }
        $result = M('mall_product_evaluate')
                        ->alias('a')
                        ->field('a.id,a.uid,a.content,a.pics as img,a.star as score,a.time as create_time,f.user_name,b.name')
                        ->join('LEFT JOIN dsy_mall_product as b on a.pid=b.id')
                        ->join('LEFT JOIN t_user as f on a.uid=f.user_id')
                        ->where('a.id=' . $id)
                        ->find();
        $str = '';
        if($result['score'] == 0){
            $str = '暂无打星';
        }else{
            for($i = 0; $i < $result['score'];$i++){
                $str .= "<i class='layui-icon' style='margin:0 2px 0 2px;color:red;'>&#xe600;</i>";
            }
        }
        $result['str'] = $str;
        $result['img'] = explode(',',$result['img']);
        if(!empty($result['img'])){
            foreach($result['img'] as $key => $val){
                $result['img'][$key] = format_img($val, IMG_VIEW);
            }
        }
        $this->assign('result',$result);
        $this->display('shop_evaluate_idea');
    }
    public function shop_evaluate_sydispay(){
        $id = I('id','');
        if(empty($id)){
            return Response::show(300,'请选择要隐藏或显示的评论');
        }
        $type = I('type','');
        $result = M('mall_product_evaluate')
                    ->where('id=' . $id)
                    ->setField('status',$type);
        if($result != false){
            if($type == 0){
                return Response::show(200,'该评论显示成功');
            }else{
                return Response::show(200,'该评论隐藏成功');
            }
        }else{
            if($type == 0){
                return Response::show(300,'该评论显示失败');
            }else{
                return Response::show(300,'该评论隐藏失败');
            }
        }
    }
    public function shop_evaluate_syalldispay(){
        $ids = I('ids','');
        if(empty($ids)){
            return Response::show(300,'请选择要隐藏或显示的评论');
        }
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $type = I('type','');
        $result = M('mall_product_evaluate')
                    ->where($where)
                    ->setField('status',$type);
        if($result != false){
            if($type == 0){
                return Response::show(200,'该评论显示成功');
            }else{
                return Response::show(200,'该评论隐藏成功');
            }
        }else{
            if($type == 0){
                return Response::show(300,'该评论显示失败');
            }else{
                return Response::show(300,'该评论隐藏失败');
            }
        }
    }
    public function shop_evaluate_sydelete(){
        $ids = I('ids','');
        if(empty($ids)){
            return Response::show(300,'请选择要隐藏或显示的评论');
        }
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $result = M('mall_product_evaluate')
                    ->where($where)
                    ->delete();
        if($result){
            return Response::show(200,'该评论删除成功');
        }else{
            return Response::show(200,'该评论删除成功');
        }
    }
} 