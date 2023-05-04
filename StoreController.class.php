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

class StoreController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    //商家界面
    public function index(){
        $this->display('index');
    }
    //商家列表数据
    public function store_lis()
    {
        $name = I('name','');
        $status = I('status','');
        if(!empty($name)){
            $where['name'] = array('like','%'.$name.'%');
        }
        if(!empty($status)){
            $where['status'] = array('eq',$status);
        }
        $pageIndex = I('pageIndex');
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $store = M('mall_shops');
        $num= $store->where($where)->count('id');//总记录数
        $where['id'] = array('neq','128');
        $vo = $store
            ->page($page,$limit)
            ->order('id desc')
            ->where($where)
            ->select();
        if(!empty($vo)){
            foreach($vo as $key=>$value){
                if($value['status'] == 1){
                    $vo[$key]['status'] = '启用中';
                }else{
                    $vo[$key]['status'] = '停用中';
                }
                if(!empty($value['logo_url'])){
                    $img = format_img($value['logo_url'], WEB_URL_IMG_UP);
                    $vo[$key]['logo_url'] =  '<a href="'.$img.'" target="view_window">点击查看</a>';
                }
            }
        }

        return Response::mjson($vo,$num);
    }
    //添加商家界面
    public function add_index()
    {
        $this->display('add');
    }
    //添加商家操作
    public function add()
    {
        $name = I('name','');
        $username = I('username','');
        $pwd = I('pwd','');
        $mobile = I('mobile','');
        $coast = floatval(I('server_coast'));
        $pic = $_FILES['pic'];
//        $store_type = I('store_type','');

        if ($coast < 0 || $coast > 100) {
            return Response::show(300,'服务费不能超过100%或低于0');
        }
        $server_coast = $coast*0.01;
        if(empty($pic)){
            return Response::show(300,'请添加图片');
        }elseif(empty($name)){
            return Response::show(300,'请输入商家名称');
        }elseif (empty($username)){
            return Response::show(300,'请输入商家账号');
        }elseif (empty($pwd)){
            return Response::show(300,'请输入商家密码');
        }elseif (empty($mobile)) {
            return Response::show(300, '请输入联系方式');
        }elseif (empty($coast)) {
            return Response::show(300, '请输入商家服务费');
        }else {
            $pic = uploadfile($pic);
            if(empty($pic)){
                return Response::show(400,'图片上传失败');
            }
            $where['username'] = array('eq', $username);
            $dd = M('mall_shops', 'dsy_')
                ->where($where)
                ->select();
            if (!empty($dd)) {
                return Response::show(300,'商家名称已存在');
            }
            $de_where['name'] = array('eq', $name);
            $ddd = M('mall_shops', 'dsy_')
                ->where($de_where)
                ->select();
            if (!empty($ddd)) {
                return Response::show(300,'用户名已存在');
            }
            $data = array(
                'pic'=>$pic,
                'name'=>$name,
                'username'=>$username,
                'pwd'=>md5($pwd),
                'mobile'=>$mobile,
//                'type' => $store_type,
                'server_coast'=>$server_coast,
                'time'=>NOW,
            );
            //添加操作日志
            $admin_log = '新增商家:' . $name;
            $store = M('mall_shops');
            $result = $store->add($data);
            $arr['username'] = $username;
            $arr['name'] = $name;
            if($result){
                admin_log($admin_log, 1, 'dsy_mall_shops:' . $result);
                return Response::show(200,$arr);
            } else {
                admin_log($admin_log, 0, 'dsy_mall_shops');
                return Response::show(400,'添加失败');
            }
        }

    }
    //停用商家
    public function stop()
    {

        $ids = I('ids');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        $store = M('mall_shops');
        $info = $store->where($where)->getField('allow_pass',true);
        if(in_array('2',$info)){
            return Response::show(400,'您选择的商家中有未通过审核的商家');
        }
        $store->startTrans();
        $store->status = 2;
        $result_stop = $store->where($where)->save();

        //商品停用 、停用对应对应推荐商品
        $p = 1;
        $r = 2;
        for($i=0;$i<count($ids);$i++){
            $where_p['sid'] = array('eq',$ids[$i]);
            $data['status'] = 2;
            $result_p = M('mall_product')->where($where_p)->save($data);
            if($result_p !== false){
                $p = 1;
            } else {
                $p = 2;
                break;
            }

            $stop_recommend = M('mall_product_recommend')->where($where_p)->save($data);
            if($stop_recommend !== false){
                $r = 1;
            }else{
                $r = 2;
                break;
            }
        }
        $infos = M('mall_shops')->where(['id' => ['in', $id]])->getField('name', true);
        //添加操作日志
        $admin_log = '停用商家【' . implode(',', $infos) . '】';
        if($result_stop !==false && $p == 1 && $r == 1){
            $store->commit();
            admin_log($admin_log, 1, 'dsy_mall_shops:' . $id);
            return Response::show(200,'success');
        }else{
            $store->rollback();
            admin_log($admin_log, 0, 'dsy_mall_shops:' . $id);
            return Response::show(400,'停用失败');
        }

    }
    //启用商家
    public function open()
    {
        $ids = I('ids');
        $id = implode(',',$ids);
        $where['id'] = array('in',$id);
        //推荐表修改字段
        $rec['sid'] = array('in',$id);
        $store = M('mall_shops');
        $info = $store->where($where)->getField('allow_pass',true);
        if(in_array('2',$info)){
            return Response::show(400,'您选择的商家中有未通过审核的商家');
        }
        $recprod = M('mall_product_recommend');
        $store->startTrans();
        $store->status = 1;
        $result_stop = $store->where($where)->save();
        //对应的推荐表状态修改
        $result_rec =  $recprod->where($rec)->save();
        //商品启用
        for($i=0;$i<count($ids);$i++){
            $where_p['sid'] = array('eq',$ids[$i]);
            $data['status'] = 1;
            $result_p = M('mall_product')->where($where_p)->save($data);
            if($result_p !== false){
                $p = 1;
            } else {
                $p = 2;
            }
        }
        $infos = M('mall_shops')->where(['id' => ['in', $id]])->getField('name', true);
        //添加操作日志
        $admin_log = '启用商家【' . implode(',', $infos) . '】';
        if($result_stop !==false && $p == 1) {
            $store->commit();
            admin_log($admin_log, 1, 'dsy_mall_shops:' . $id);
            return Response::show(200,'success');
        } else{
            $store->rollback();
            admin_log($admin_log, 0, 'dsy_mall_shops:' . $id);
            return Response::show(400,'启用失败');
        }

    }

    //审核通过操作
    public function allow_pass(){
        $ids = I('ids','');
        $id = $ids[0];
        $where['id'] = array('eq',$id);
        $data['allow_pass'] = 1;
        $infos = M('mall_shops')->where(['id' => $id])->getField('name');
        //添加操作日志
        $admin_log = '商家【' . $infos . '】注册审核通过';
        $save = M('mall_shops','dsy_')->where($where)->save($data);
        if($save != false){
            S('shop' . $id, null);
            admin_log($admin_log, 1, 'dsy_mall_shops:' . $id);
            return Response::show(200,'审核通过');
        }else{
            admin_log($admin_log, 0, 'dsy_mall_shops:' . $id);
            return Response::show(400,'审核失败');
        }


    }


    //编辑服务费界面
    public function edit_index()
    {
        $id = I('id');
        $store = M('mall_shops');
        $vo = $store->find($id);
        if(!empty($vo)){
            $type_id = $vo['shop_type'];
            $where['id'] = array('eq',$type_id);
            $type_name = M('shoptype','dsy_')->where($where)->getField('name');
            $vo['type_name'] = $type_name;
            $iid = $vo['industry_category'];
            $where_i['id'] = array('eq',$iid);
            $iname = M('industry_category','dsy_')->where($where_i)->getField('name');
            $vo['iname'] = $iname;
            $where_pname['id'] = array('eq',$vo['pid']);
            $pname = M('provincial','dsy_')->where($where_pname)->getField('name');
            $where_cname['id'] = array('eq',$vo['city']);
            $cname = M('provincial','dsy_')->where($where_cname)->getField('name');
            $where_aname['id'] = array('eq',$vo['area']);
            $aname = M('provincial','dsy_')->where($where_aname)->getField('name');
            $vo['address'] = $pname.$cname.$aname.$vo['address'];

            $vo['logo_url'] = format_img($vo['logo_url'], WEB_URL_IMG_UP);
            $vo['idcard1'] = format_img($vo['idcard1'], WEB_URL_IMG_UP);
            $vo['idcard2'] = format_img($vo['idcard2'], WEB_URL_IMG_UP);
            $vo['business_license'] = format_img($vo['business_license'], WEB_URL_IMG_UP);
            $vo['spltxkz'] = format_img($vo['spltxkz'], WEB_URL_IMG_UP);
            $vo['jlxkz'] = format_img($vo['jlxkz'], WEB_URL_IMG_UP);
        }
        $this->assign('vo',$vo);
        $this->display('edit');
    }
    //驳回操作
    public function stop_replay(){
        $ids = I('ids');
        $message = I('message');
        $id = $ids[0];
        if(empty($message)){
            return Response::show(300,'驳回原因不能为空');
        }
        if(empty($_COOKIE['username'])){
            return Response::show(300,'请刷新页面');
        }
        $where_admin['username'] = array('eq',$_COOKIE['username']);
        $admin_id = M('admin','dsy_')->where($where_admin)->getField('id');
        $model = M('mall_shops','dsy_');
        $model->startTrans();
        $where['id'] = array('eq',$id);
        $data['allow_pass'] = 2;
        $infos = M('mall_shops')->where(['id' => $id])->getField('name');
        //添加操作日志
        $admin_log = '商家【' . $infos . '】注册审核驳回，驳回原因：' . $message;
        $one = $model->where($where)->save($data);

        $add_data['uid'] = $admin_id;
        $add_data['sid'] = $id;
        $add_data['remarks'] = $message;
        $add_data['time'] = time();
        $two = M('mall_shops_reject','dsy_')->add($add_data);
        if($one != false && $two != false){
            $model->commit();
            admin_log($admin_log, 1, 'dsy_mall_shops:' . $id);
            return Response::show(200,'操作成功');
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_mall_shops:' . $id);
            return Response::show(400,'操作失败');
        }
    }
    //商家入住申请列表界面
    public function shops_enter_index(){
        $this->display();
    }
    //商家入住申请列表数据
    public function shops_enter_info(){
        $pageIndex = I('pageIndex');
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $where = '';
        $status = I('status','');
        if(!empty($status)){
            $where['status'] = array('eq',$status);
        }
        $cname = I('cname','');
        if(!empty($cname)){
            $where['name'] = array('like','%'.$cname.'%');
        }
        $start = I('start1','');
        $end = I('end','');
        if(!empty($start)){
            if(!empty($end)){
                $where['applytime'] = array('between',"$start,$end");
            }else{
                $where['applytime'] = array('EGT',"$start");
            }
        }

        $order = 'status asc,applytime desc';
        $result = M('mall_shops_apply')
            ->where($where)
            ->page($page,$limit)
            ->select();

        $num = M('mall_shops_apply')->where($where)->order($order)->count();
        return Response::mjson($result,$num);

    }

    //商家入住申请--通过
    public function shops_enter_tg()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $apply = M('mall_shops_apply');
        $apply->startTrans();
        //修改审核表中记录状态
        $where['id'] = array('in',$id);
        $where['status'] = array('eq',1);
        $result_shops = $apply->where($where)->field('username,name,mobile')->select();

        $data = array(
            'status'=>2,
            'adopttime'=>NOW,
        );
        $result = $apply->where($where)->save($data);
        if($result == 0){
            return Response::show(400,'重复操作');
        }

        //添加商家表记录
//        $pwd = rand(100000,999999);
        $pwd = substr($result_shops['mobile'],-6);
        $mobile = array();
        for($i=0;$i<count($result_shops);$i++){
            $data_shops = array(
                'username'=>$result_shops[$i]['username'],
                'pwd'=>md5(md5($pwd)),
                'name'=>$result_shops[$i]['name'],
                'mobile'=>$result_shops[$i]['mobile'],
            );
            $result_shops_add = M('mall_shops')->add($data_shops);
            if(!$result_shops_add){
                $apply->rollback();
                return Response::show(400,'操作失败，请稍后再试');
            }
            $mobile[$i] = $result_shops[$i]['username'];
        }
        if($result !==false){
            $apply->commit();
            //发送短信通知
            $to = implode(',',$mobile);
            $datas = array(
                0=>'此手机号',
                1=>$pwd,
            );
            $tempId = '379191';
            $send = SendTemplateSMS($to,$datas,$tempId);
            if ($send === 0)
                return Response::show(200, '操作成功');
            else
                return Response::show(400, '操作成功，短信通知失败: '.$send);
        }else{
            $apply->rollback();
            return Response::show(400,'操作失败');
        }
    }

    //商家入住申请--驳回
    public function shops_enter_bh()
    {
        $ids = I('ids','');
        $id = implode(',',$ids);
        $apply = M('mall_shops_apply');
        $apply->startTrans();
        //修改审核表中记录状态
        $where['id'] = array('in',$id);
        $where['status'] = array('eq',1);
        $result_shops = $apply->where($where)->field('username,name,mobile')->select();

        $data = array(
            'status'=>3,
            'rejecttime'=>NOW,
        );
        $result = $apply->where($where)->save($data);
        if($result == 0){
            return Response::show(400,'重复操作');
        }

        $mobile = array();
        for($i=0;$i<count($result_shops);$i++){
            $mobile[$i] = $result_shops[$i]['username'];
        }

        if($result !==false){
            $apply->commit();
            //发送短信通知
            $to = implode(',',$mobile);
            $datas = array();
            $tempId = '379193';
            $send = SendTemplateSMS($to,$datas,$tempId);
            if ($send === 0)
                return Response::show(200, '操作成功');
            else
                return Response::show(400, '操作成功，短信通知失败: '.$send);
        }else{
            $apply->rollback();
            return Response::show(400,'操作失败');
        }
    }

    //商家入住申请--详情
    public function shops_enter_detail()
    {
        $id = I('id', '');
        $where['id'] = array('eq',$id);
        $result = M('mall_shops_apply')->where($where)->select();

        $result[0]['licenseimg'] = format_img($result[0]['licenseimg'], IMG_VIEW);

        $this->assign('result',$result)->display();

    }


    //编辑商家服务费
    public function edit_service_index(){
        $id = I('id','');
        $where['id'] = array('eq',$id);
        $cost = M('mall_shops','dsy_')->where($where)->getField('server_coast');
        $cost = $cost*100;
        $this->assign('cost',$cost);
        $this->assign('id',$id);
        $this->display('service');
    }

    //编辑服务费操作
    public function edit_service(){
        $id = I('id');
        $server_coast = I('server_coast');
        if(is_numeric($server_coast)==false){
            return Response::show(401,'请输入正确的数值');
        }
        $server_coast = $server_coast/100;
        $where['id'] = array('eq',$id);
        $cost = M('mall_shops','dsy_')->where($where)->getField('server_coast');
        if($cost==$server_coast){
            return Response::show(400,'请不要设置重复的服务费');
        }
        $data['server_coast'] = $server_coast;
        $infos = M('mall_shops')->where(['id' => $id])->getField('name');
        //添加操作日志
        $admin_log = '编辑商家【' . $infos . '】服务费:' . ($server_coast * 100) . '%';
        $save = M('mall_shops','dsy_')->where($where)->save($data);
        if($save != false){
            admin_log($admin_log, 1, 'dsy_mall_shops:' . $id);
            return Response::show(200,'设置成功');
        }else{
            admin_log($admin_log, 0, 'dsy_mall_shops:' . $id);
            return Response::show(400,'设置失败');
        }

    }


}
