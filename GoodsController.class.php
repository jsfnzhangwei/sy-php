<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/6
 * Time: 11:40
 */

namespace Admin\Controller;


class GoodsController extends CommonController
{
    public function index(){
        $pid  = I('get.pid',0);
        //获取分类
        $categoryString = M('MallMenu')->where(['id'=>$pid])->getField('slid');
        $categoryArray = explode('|',$categoryString);
        $name       =   I('name');
        $map = [
            'status'=>1,
            'upanddown'=>1,
            'tlid'=>['in',$categoryArray]
        ];
        if(is_numeric($name)){
            $map['skuid'] = $name;
        }else{
            if(!empty($name)){
                //搜索引擎
                $map['name'] = ['like','%'.$name.'%'];
            }
        }
        $list   = $this->lists('MallProduct', $map,'isrecommend asc','id,name,skuid,isrecommend,price,etime');
        $this->assign('_list', $list);
        $this->meta_title = '商品列表';
        $this->display();
    }

    public function changeStatus($method=null){
        $id = array_unique((array)I('id',0));
        $id = is_array($id) ? implode(',',$id) : $id;
        if ( empty($id) ) {
            $this->error('请选择要操作的数据!');
        }
        $map['uid'] =   array('in',$id);
        switch ( strtolower($method) ){
            case 'recommendgoods':
                $this->recommend('MallProduct', $map );
                break;
            case 'unrecommendgoods':
                $this->unRecommend('MallProduct', $map );
                break;
            default:
                $this->error('参数非法');
        }
    }
}