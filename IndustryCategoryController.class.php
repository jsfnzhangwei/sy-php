<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/15
 * Time: 15:06
 */

namespace Admin\Controller;


use Admin\Controller\CommonController;

class IndustryCategoryController extends CommonController
{
    public function index(){
        /* 获取商户类型列表 */
        $list = M('IndustryCategory')->where(['status'=>1])->select();

        $this->assign('list', $list);
        $this->meta_title = '商户类型管理';
        $this->display();
    }

    public function add(){
        if(IS_POST){
            $IndustryCategory = D('IndustryCategory');
            $data = $IndustryCategory->create();
            if($data){
                $id = $IndustryCategory->add();
                if($id){
                    $this->success('新增成功', U('index'));
                } else {
                    $this->error('新增失败');
                }
            } else {
                $this->error($IndustryCategory->getError());
            }
        } else {
            $this->assign('info',null);
            $this->meta_title = '新增行业类别';
            $this->display('edit');
        }
    }

    public function edit($id = 0){
        if(IS_POST){
            $Shoptype = D('IndustryCategory');
            $data = $Shoptype->create();
            if($data){
                if($Shoptype->save()){
                    $this->success('编辑成功', U('index'));
                } else {
                    $this->error('编辑失败');
                }

            } else {
                $this->error($Shoptype->getError());
            }
        } else {
            $info = array();
            /* 获取数据 */
            $info = M('IndustryCategory')->find($id);

            if(false === $info){
                $this->error('获取配置信息错误');
            }

            $this->assign('info', $info);
            $this->meta_title = '编辑';
            $this->display();
        }
    }

    public function del(){
        $id = array_unique((array)I('id',0));

        if ( empty($id) ) {
            $this->error('请选择要操作的数据!');
        }

        $map = array('id' => array('in', $id) );
        if(M('IndustryCategory')->where($map)->delete()){
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }
}