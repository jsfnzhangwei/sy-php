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
class SystemController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    //系统权限主界面
    public function index()
    {
        $this->display('System/index');
    }

    public function navigation()
    {
        $this->display('menu');
    }

    protected function getMenu($checked=array())
    {
        $arr = array();
        //获取菜单json
        $where_menu['status'] = array('eq', 1);
        $order = 'shunxu asc';
        $menu = M('menu')->where($where_menu)->order($order)->select();
        if (!empty($menu)) {

            foreach ($menu as $k => $v) {
                $data_id = $v['data_id'];
                $name = $v['name'];
                $pid = $v['pid'];
                if (in_array($data_id,$checked)) {
                    $check = true;
                }else{
                    $check = false;
                }
                $arr[] = array('id' => $data_id, 'pId' => $pid, 'name' => $name, 'open' => true,'checked'=>$check);
                $result_navigation_first = selNavigationFirst($data_id);
                if (!empty($result_navigation_first)) {
                    foreach ($result_navigation_first as $kk => $vv) {
                        if (in_array($vv['data_id'],$checked)) {
                            $check = true;
                        }else{
                            $check = false;
                        }
                        $arr[] = array('id' => $vv['data_id'], 'pId' => $v['data_id'], 'name' => $vv['name'],'checked'=>$check);
                        $result_navigation_second = selNavigationSecond($vv['data_id']);
                        foreach ($result_navigation_second as $kkk => $vvv) {
                            if (in_array($vvv['data_id'],$checked)) {
                                $check = true;
                            }else{
                                $check = false;
                            }
                            $arr[] = array('id' => $vvv['data_id'], 'pId' => $vv['data_id'], 'name' => $vvv['name'],'checked'=>$check);
                        }
                    }
                }

            }
        }
        return $arr;
    }

    public function add()
    {
        $arr = $this->getMenu();
        $this->assign('data',json_encode($arr))->display();
    }

    //新增
    public function adddo()
    {
        $data = $_REQUEST['data'];
        $username = I('username','');
        $pwd = I('pwd','');
        $email = I('email','');
        if(empty($email)){
            return Response::show(400,'邮箱不能为空');
        }
        $pattern = "/^[^_][\w]*@[\w.]+[\w]*[^_]$/";
        if(!preg_match($pattern, $email, $matches)){
            return Response::show(400,'邮箱格式不正确');;
        }
        //验证用户名不能重复
        $where_check['username'] = array('eq',$username);
        $result_check = M('admin')->where($where_check)->find();
        if (!empty($result_check)) {
            return Response::show(400,'存在相同的用户');
        }
        $info = array(
            'username'=>$username,
            'pwd'=>md5(md5($pwd)),
            'type'=>2,
            'time'=>NOW,
            'menu'=>$data,
            'email'=>$email
        );
        //添加操作日志
        $admin_log = '新增管理员，账户:' . $username;
        $result = M('admin')->add($info);
        if ($result) {
            admin_log($admin_log, 1, 'dsy_admin:' . $result);
            return Response::show(200,'true');
        } else {
            admin_log($admin_log, 0, 'dsy_admin');
            return Response::show(400,'操作失败请重试');
        }
    }

    public function permissions_info()
    {
        $pageIndex = I('pageIndex', 0);
        $page = $pageIndex + 1;
        $limit = 10;
        $where['type'] = array('in','2,99');
        $result = M('admin')->where($where)->page($page, $limit)->select();
        $where_menu['status'] = array('eq',1);
        foreach ($result as $kj=>$vj){
            $str = '';
            if ($vj['type'] == 99) {
                $str = 'ALL';
            }else{
                $menu_json = $vj['menu'];
                $menu_arr = json_decode($menu_json,true);
                foreach ($menu_arr as $k=>$v) {
                    $where_navigation['data_id'] = array('eq',$v);
                    $name1 = M('menu')->where($where_navigation)->getField('name');
                    if (!empty($name1)) {
                        $str.= '【'.$name1.'】';
                    }else{
                        $name2 = M('menu_navigation_first')->where($where_navigation)->getField('name');
                        if (!empty($name2)) {
                            $str .= '（'.$name2.'）';
                        }else{
                            $name3 = M('menu_navigation_second')->where($where_navigation)->getField('name');
                            $str.=''.$name3.'&nbsp;';
                        }

                    }
                }
            }
            $result[$kj]['menu'] = $str;
        }
        $num = M('admin')->where($where)->count();
        return Response::mjson($result,$num);
    }

    public function del()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $where['type'] = array('eq',2);
        $infos = M('admin')->where($where)->getField('username');
        //添加操作日志
        $admin_log = '删除管理员。账户:' . $infos;
        $result = M('admin')->where($where)->delete();
        if ($result !== false) {
            admin_log($admin_log, 1, 'dsy_admin:' . $id);
            return Response::show(200,'操作成功');
        } else {
            admin_log($admin_log, 0, 'dsy_admin:' . $id);
            return Response::show(400,'操作失败请重试');
        }
    }

    public function edit()
    {
        $id = I('id','');
        $where['id'] = array('eq',$id);
        $info = M('admin')->where($where)->field('')->find();
        $menu_json = $info['menu'];
        $menu = json_decode($menu_json,true);

        $arr = $this->getMenu($menu);

        $this->assign('data',json_encode($arr))->assign('info',$info)->display('System/edit');
    }

    public function editdo()
    {
        $id = I('id','');
        $data = $_REQUEST['data'];
        $username = I('username','');
        $email = I('email','');
        if(empty($email)){
            return Response::show(400,'邮箱不能为空');
        }
        $pattern = "/^[^_][\w]*@[\w.]+[\w]*[^_]$/";
        if(!preg_match($pattern, $email, $matches)){
            return Response::show(400,'邮箱格式不正确');;
        }
        //验证用户名不能重复
        $where_check['id'] = array('neq',$id);
        $where_check['username'] = array('eq',$username);
        $result_check = M('admin')->where($where_check)->find();
        if (!empty($result_check)) {
            return Response::show(400,'存在相同的用户');
        }
        $pwd = I('pwd','');
        $where['id'] = array('eq',$id);
        $datas['username'] = $username;
        if (!empty($pwd)) {
            $datas['pwd'] = md5(md5($pwd));
        }
        $datas['menu'] = $data;
        $datas['email'] = $email;
        $result = M('admin')->where($where)->save($datas);
        //添加操作日志
        $admin_log = '修改管理员信息。账户:' . $username;
        if ($result!== false) {
            admin_log($admin_log, 1, 'dsy_admin:' . $id);
            return Response::show(200,'操作成功');
        } else {
            admin_log($admin_log, 0, 'dsy_admin:' . $id);
            return Response::show(400,'操作失败');
        }

    }

    public function menu_index()
    {
        $this->display('menu');
    }

    public function menu()
    {
        $model = M('menu');
        $info = $model->order('shunxu asc')->select();
        $num = $model->count();
        return Response::mjson($info,$num);
    }

    public function menu_add_index()
    {
        $this->display();
    }

    public function menu_edit_index()
    {
        $data['id'] = I('id','');
        $data['data_id'] = I('data_id','');
        $data['name'] = I('name','');
        $data['status'] = I('status','');
        $data['shunxu'] = I('shunxu','');
        $this->assign('data',$data)->display();
    }

    public function menu_add_do()
    {
        $data_id = I('data_id','');
        $where['data_id'] = array('eq',$data_id);
        $check = M('menu')->where($where)->find();
        if (!empty($check)) {
            return Response::show(400,'ID重复，请修改后重新添加');
        }
        $name = I('name','');
        $shunxu = I('shunxu','');
        $data = array(
            'data_id'=>$data_id,
            'name'=>$name,
            'shunxu'=>$shunxu,
            'pid'=>0,
            'status'=>2,
        );
        $result = M('menu')->add($data);
        if ($result) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'添加失败');
        }
    }

    public function menu_edit_do()
    {
        $id = I('id','');
        $data_id = I('data_id','');
        $name = I('name','');
        $shunxu = I('shunxu','');
        $where['id'] = array('eq',$id);
        $data = array(
            'data_id'=>$data_id,
            'name'=>$name,
            'shunxu'=>$shunxu,
        );
        $result = M('menu')->where($where)->save($data);

        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'添加失败');
        }
    }

    public function menu_del_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $result = M('menu')->where($where)->delete();
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'删除失败');
        }
    }

    public function menu_start_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $data['status'] = 1;
        $result = M('menu')->where($where)->save($data);
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'操作失败');
        }
    }

    public function menu_stop_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $data['status'] = 2;
        $result = M('menu')->where($where)->save($data);
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'操作失败');
        }
    }

    public function navigation_first()
    {
        $id = I('id','');
        $data_id = I('data_id','');
        $this->assign('id',$id)->assign('data_id',$data_id)->display('navigation_first');
    }

    public function navigation_first_info()
    {
        $id = I('id','');
        $where2['id'] = array('eq',$id);
        $data_id = M('menu')->where($where2)->getField('data_id');
        $where['pid'] = array('eq',$data_id);
        $model = M('menu_navigation_first');
        $info = $model->order('shunxu asc')->where($where)->select();
        $num = $model->where($where)->count();
        return Response::mjson($info,$num);
    }

    public function navigation_first_add_index()
    {
        $pid = I('pid','');
        $this->assign('pid',$pid)->display();
    }

    public function navigation_first_add_do()
    {
        $data_id = I('data_id','');
        $where['data_id'] = array('eq',$data_id);
        $check = M('menu_navigation_first')->where($where)->find();
        if (!empty($check)) {
            return Response::show(400,'ID重复，请修改后重新添加');
        }
        $name = I('name','');
        $shunxu = I('shunxu','');
        $pid = I('pid','');
        $data = array(
            'data_id'=>$data_id,
            'name'=>$name,
            'shunxu'=>$shunxu,
            'pid'=>$pid,
            'status'=>2,
        );
        $result = M('menu_navigation_first')->add($data);
        if ($result) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'添加失败');
        }
    }

    public function navigation_first_del_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $result = M('menu_navigation_first')->where($where)->delete();
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'删除失败');
        }
    }

    public function navigation_first_start_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $data['status'] = 1;
        $result = M('menu_navigation_first')->where($where)->save($data);
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'操作失败');
        }
    }

    public function navigation_first_stop_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $data['status'] = 2;
        $result = M('menu_navigation_first')->where($where)->save($data);
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'操作失败');
        }
    }

    public function navigation_first_edit_index()
    {
        $data['id'] = I('id','');
        $data['pid'] = I('pid','');
        $data['data_id'] = I('data_id','');
        $data['name'] = I('name','');
        $data['status'] = I('status','');
        $data['shunxu'] = I('shunxu','');
        $this->assign('data',$data)->display();
    }

    public function navigation_first_edit_do()
    {
        $id = I('id','');
        $data_id = I('data_id','');
        $name = I('name','');
        $shunxu = I('shunxu','');
        $where['id'] = array('eq',$id);
        $data = array(
            'data_id'=>$data_id,
            'name'=>$name,
            'shunxu'=>$shunxu,
        );
        $result = M('menu_navigation_first')->where($where)->save($data);

        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'添加失败');
        }
    }

    public function navigation_second()
    {
        $id = I('id','');
        $data_id = I('data_id','');
        $this->assign('id',$id)->assign('data_id',$data_id)->display('navigation_second');
    }

    public function navigation_second_info()
    {
        $id = I('id','');
        $where2['id'] = array('eq',$id);
        $data_id = M('menu_navigation_first')->where($where2)->getField('data_id');
        $where['pid'] = array('eq',$data_id);
        $model = M('menu_navigation_second');
        $info = $model->order('shunxu asc')->where($where)->select();
        $num = $model->where($where)->count();
        return Response::mjson($info,$num);
    }

    public function navigation_second_add_index()
    {
        $pid = I('pid','');
        $this->assign('pid',$pid)->display();
    }

    public function navigation_second_add_do()
    {
        $data_id = I('data_id','');
        $where['data_id'] = array('eq',$data_id);
        $check = M('menu_navigation_second')->where($where)->find();
        if (!empty($check)) {
            return Response::show(400,'ID重复，请修改后重新添加');
        }
        $name = I('name','');
        $shunxu = I('shunxu','');
        $pid = I('pid','');
        $url = I('url','');
        $data = array(
            'data_id'=>$data_id,
            'name'=>$name,
            'shunxu'=>$shunxu,
            'pid'=>$pid,
            'status'=>2,
            'url'=>$url,
        );
        $result = M('menu_navigation_second')->add($data);
        if ($result) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'添加失败');
        }
    }

    public function navigation_second_del_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $result = M('menu_navigation_second')->where($where)->delete();
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'删除失败');
        }
    }

    public function navigation_second_start_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $data['status'] = 1;
        $result = M('menu_navigation_second')->where($where)->save($data);
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'操作失败');
        }
    }

    public function navigation_second_stop_do()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $data['status'] = 2;
        $result = M('menu_navigation_second')->where($where)->save($data);
        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'操作失败');
        }
    }

    public function navigation_second_edit_index()
    {
        $data['id'] = I('id','');
        $data['pid'] = I('pid','');
        $data['data_id'] = I('data_id','');
        $data['name'] = I('name','');
        $data['status'] = I('status','');
        $data['shunxu'] = I('shunxu','');
        $data['url'] = I('url','');
        $this->assign('data',$data)->display();
    }

    public function navigation_second_edit_do()
    {
        $id = I('id','');
        $data_id = I('data_id','');
        $name = I('name','');
        $shunxu = I('shunxu','');
        $url = I('url','');
        $where['id'] = array('eq',$id);
        $data = array(
            'data_id'=>$data_id,
            'name'=>$name,
            'shunxu'=>$shunxu,
            'url'=>$url,
        );
        $result = M('menu_navigation_second')->where($where)->save($data);

        if ($result!==false) {
            return Response::show(200,'');
        } else {
            return Response::show(400,'添加失败');
        }
    }
}
