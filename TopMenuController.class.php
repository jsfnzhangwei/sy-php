<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/2
 * Time: 17:15
 */

namespace Admin\Controller;


use Common\Model\ConfigModel;
use Think\Controller;

class TopMenuController extends CommonController{
    public function index(){
        $map = [];
        $title = I('get.');
        if($title)
            $map['name'] = array('like',"%{$title}%");
        $list = $this->lists('MallMenu',$map);
        $this->assign('list',$list);
        $this->meta_title = '菜单列表';
        $this->display();
    }

    /**
     * 二级菜单
     */
    public function secondEdit(){
        $pid = I('get.pid');
        if(IS_AJAX){
            $Menu = D('MallMenu');
            $data = I('request.');
            if(8<count($data['slid'])){
                $this->error('对不起分类不可以超过8个');
            }
            $data['slid'] = implode('|',$data['slid']);
            $data = $Menu->create($data);
            if($data){
                if(false === $Menu->save()){
                    $this->error('保存失败');
                }else{
                    $this->success('保存成功', U('TopMenu/index'));
                }
            } else {
                $this->error($Menu->getError());
            }
        } else {
            $info = M('MallMenu')->where(['id'=>$pid])->find();
            $info['slid'] = explode('|',$info['slid']);
            $list = M('MallSlevel')->where(['flid'=>$info['flid']])->select();
            foreach ($list as $key=>$item){
                $list[$key]['list'] = M('MallTlevel')->where(['slid'=>$item['id']])->select();
            }
            $this->assign('slevel', $list);
            $this->assign('info',$info);
            $this->display('secondEdit');
        }
    }

    /**
     * 新增菜单
     */
    public function add(){
        if(IS_POST){
            $Menu = D('MallMenu');
            $data = $Menu->create();
            if($data){
                $id = $Menu->add();
                if($id){
                    $this->success('新增成功',U('topMenu/index'));
                } else {
                    $this->error('新增失败');
                }
            } else {
                $this->error($Menu->getError());
            }
        } else {
            $list = M('MallFlevel')->select();
            $this->assign('flevel', $list);
            $this->display('edit');
        }
    }

    public function setPosition(){
        if(IS_POST){
            $Menu = D('MallMenu');
            $data = $Menu->create();
            if($data){
                if(false === $Menu->save()){
                    $this->error('保存失败');
                }else{
                    $this->success('保存成功');
                }
            }else{
                $this->error($Menu->getError());
            }
        }else{
            $info = M('MallMenu')->find(I('get.id'));
            $list = M('BannerPosition')->where(['status=1'])->select();
            if(empty($list)){
                $this->error('广告位目前为空，请去添加!');
            }
            $this->assign('info',$info);
            $this->assign('list',$list);
            $this->display();
        }
    }

    public function edit($id = 0){
        if(IS_POST){
            $Menu = D('MallMenu');
            $data = $Menu->create();
            if($data){
                if($Menu->save()!== false){
                    $this->success('更新成功', U('TopMenu/index'));
                } else {
                    $this->error('更新失败');
                }
            } else {
                $this->error($Menu->getError());
            }
        } else {
            $info = array();
            /* 获取数据 */
            $list = M('MallFlevel')->select();
            $this->assign('flevel', $list);
            $info = M('MallMenu')->field(true)->find($id);
            $this->assign('info', $info);
            $this->meta_title = '编辑后台菜单';
            $this->display();
        }
    }

    public function del(){
        $id = array_unique((array)I('id',0));

        if ( empty($id) ) {
            $this->error('请选择要操作的数据!');
        }

        $map = array('id' => array('in', $id) );
        if(M('MallMenu')->where($map)->delete()){
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }

    public function toogleHide($id,$value = 1){
        $this->editRow('MallMenu', array('hide'=>$value), array('id'=>$id));
    }

    public function sort(){
        if(IS_GET){
            $ids = I('get.ids');
            $pid = I('get.pid');

            //获取排序的数据
            $map = array('status'=>array('gt',-1));
            if(!empty($ids)){
                $map['id'] = array('in',$ids);
            }else{
                if($pid !== ''){
                    $map['pid'] = $pid;
                }
            }
            $list = M('MallMenu')->where($map)->field('id,title')->order('sort asc,id asc')->select();

            $this->assign('list', $list);
            $this->meta_title = '菜单排序';
            $this->display();
        }elseif (IS_POST){
            $ids = I('post.ids');
            $ids = explode(',', $ids);
            foreach ($ids as $key=>$value){
                $res = M('MallMenu')->where(array('id'=>$value))->setField('sort', $key+1);
            }
            if($res !== false){
                $this->success('排序成功！');
            }else{
                $this->error('排序失败！');
            }
        }else{
            $this->error('非法请求！');
        }
    }
}