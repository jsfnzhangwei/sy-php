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

class CompanyShopController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }
    //集采菜单
    public function product_category_index(){
        $this->display();
    }
    public function product_category_data(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        $status = I('status');
        $start_time = I('StartTime');
        $end_time = I('EndTime');

        if(!empty($name)){
            $where['name'] = array('like',"%$name%");
        }
        if($status !== ''){
            $where['status'] = array('eq',$status);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['b.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['b.create_time'] = array('between',array($start_time,$end_time));
            }
        }
        $result = M('jc_category')
                    ->field('id,name,create_time,sort,pic as category_image,
                            case status when 0 then \'启用中\'when 1 then \'停用中\' end as status')
                    ->where($where)
                    ->order('create_time desc')
                    ->page($page,$limit)
                    ->select();
        foreach($result as $key=>$val){
            $result[$key]['category_image'] = format_img($val['category_image'], IMG_VIEW);
        }
        $num = M('jc_category')->where($where)->count();

        return Response::mjson($result,$num);
    }
    public function product_category_add(){
        $this->display();
    }
    public function product_category_edit(){
        $id = I('id');
        $result = M('jc_category')->field('id,name,create_time,sort,pic as category_image,status')->where(['id'=>$id])->find();
        $result['img'] = format_img($result['category_image'], IMG_VIEW);
        $this->assign('result',$result);
        $this->display();
    }
    public function product_category_save(){
        $id = I('id');
        $status = I('status');
        $name = I('category_name');
        $sort = I('sort');
        $img = I('img');

        if(empty($name)){
            return Response::show(300,'分类名称不能为空');
        }
        if(mb_strlen($name,'UTF-8') > 6){
            return Response::show(300,'分类名称不得大于6个字符，请重新输入');
        }
        if(empty($img)){
            return Response::show(300,'分类图标不能为空');
        }
        $data = [
            'name' => $name,
            'status' => $status,
            'sort' => $sort,
            'pic' => $img
        ];

        if(empty($id)){
            $result = M('jc_category')->add($data);
        }else{
            $result = M('jc_category')->where(['id'=>$id])->save($data);
        }
        if($result !== false){
            return Response::show(200,'操作成功');
        }else{
            return Response::show(300,'操作失败');
        }
    }
    public function product_category_status(){
        $id = I('ids');
        $status = I('status');
        
        if(empty($id)){
            return Response::show(300,'请选择要操作的分类');
        }
        $result = M('jc_category')->where(['id'=>array('in',$id)])->save(['status'=>$status]);
        if($result !== false){
            return Response::show(200,'操作成功');
        }else{
            return Response::show(300,'操作失败');
        }
    }   
    //集采菜单
    public function product_menu_index(){
        $menu = M('config', 'sys_')->field('value')->where(['name'=>'SET_JC_MENU'])->find();
        $where['id'] = array('in',$menu['value']);
        $where['status'] = array('eq',0);
        $result = M('jc_category')
                        ->field('id,pic as image,name')
                        ->where($where)
                        ->order("field(id," .  $menu['value'] . ")")
                        ->select();
        foreach($result as $key=>$val){
            $result[$key]['name'] = substr($val['name'],0,6);
            $result[$key]['image'] = format_img($val['image'], IMG_VIEW);
        }
        $this->assign('info',$menu['value']);
        $this->assign('result',$result);
        $this->display();
    }
    public function product_menu_add(){
        $pros = $_POST['value'];
        $model = M('config', 'sys_');
        $where = ['name' => 'SET_JC_MENU'];
        $pros = str_replace(' ', '', $pros);
        $pros = array_unique(array_filter(explode(',', $pros)));
        if (empty($pros)) {
            return Response::show(300, '请填写分类编号id');
        }
        $result = M('jc_category')
                    ->where(['id'=>['in',$pros],'status'=>0])
                    ->order('field(id,\'' . implode("','", $pros) . '\')')
                    ->field('id,name')
                    ->select();
        $category_id = array_column($result,'id');
        //获取数组中不同的元素
        $diff_categoryids = empty($category_id) ? $pros : array_diff($pros, $category_id);
        if (!empty($diff_categoryids)) {
            return Response::show(300, '分类编号"' . implode('，', $diff_categoryids) . '”找不到或已停用');
        }
        if(count($category_id) > 8){
            return Response::show(300,'集采菜单最多添加8个分类');
        }
        $infos_str = [];
        foreach ($result as $v) {
            $infos_str[] = '【' . $v['id'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
        }
        //添加操作日志
        $admin_log = '编辑苏鹰企业集采菜单:' . implode(',', $infos_str);
        $res = $model
            ->where($where)
            ->setField('value', implode(',', $category_id));
        if ($res == false) {
            admin_log($admin_log, 0, 'sys_config:SET_JC_MENU');
            $error = $model->getError();
            $error = empty($error) ? '编辑失败!' : $error;
            return Response::show(300, $error);
        }
        admin_log($admin_log, 1, 'sys_config:SET_JC_MENU');
        return Response::show(200, '编辑成功');
    }

    //集采精选
    public function product_selected_index(){
        $selected = M('config', 'sys_')->field('value')->where(['name'=>'SET_JC_PRO'])->find();
        $where['a.sku_id'] = array('in',$selected['value']);
        $where['b.type'] = array('eq',0);
        $result = M('jc_product')
                        ->alias('a')
                        ->field('a.id,a.sku_id,a.name,a.original_price,a.promote_price,a.base_buy_num,b.image')
                        ->join('left join dsy_jc_product_image as b on a.id=b.pid')
                        ->where($where)
                        ->select();
        $this->assign('info',$selected['value']);
        $this->assign('result',$result);
        $this->display();
    }
    public function product_selected_add(){
        $pros = $_POST['value'];

        $model = M('config', 'sys_');
        $where = ['name' => 'SET_JC_PRO'];
        $pros = str_replace(' ', '', $pros);
        $pros = array_unique(array_filter(explode(',', $pros)));
        if (empty($pros)) {
            return Response::show(300, '请填写商品sku');
        }
        $goods = M('jc_product')
            ->where(['sku_id' => ['in', $pros], 'my_status' => 1, 'deleted' => 0])
            ->order('field(sku_id,\'' . implode("','", $pros) . '\')')
            ->field('sku_id,name')
            ->select();
        $skuids = array_column($goods, 'sku_id');
        //获取数组中不同的元素
        $diff_skuids = empty($skuids) ? $pros : array_diff($pros, $skuids);
        if (!empty($diff_skuids)) {
            return Response::show(300, '商品sku“' . implode('，', $diff_skuids) . '”找不到或已下架、不可售');
        }
        $infos_str = [];
        foreach ($goods as $v) {
            $infos_str[] = '【' . $v['sku_id'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
        }
        //添加操作日志
        $admin_log = '编辑苏鹰企业集采商品:' . implode(',', $infos_str);
        $res = $model
            ->where($where)
            ->setField('value', implode(',', $skuids));
        if ($res == false) {
            admin_log($admin_log, 0, 'sys_config:SET_JC_PRO');
            $error = $model->getError();
            $error = empty($error) ? '编辑失败!' : $error;
            return Response::show(300, $error);
        }
        admin_log($admin_log, 1, 'sys_config:SET_JC_PRO');
        return Response::show(200, '编辑成功');
    }


    //集采商品管理
    public function company_product_index(){
        $username = $_COOKIE['username'];
        $category = M('jc_category')->field('id,name')->where(['status'=>0])->select();
        $this->assign('us', $us);
        $this->assign('username', $username);
        $this->assign('category',$category);
        $this->display();
    }
    public function company_product_data(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        $status = I('status');
        $category = I('category');
        $sku = I('sku');
        
        if(!empty($sku)){
            $where['a.sku_id'] = array('eq',$sku);
        }
        if(!empty($name)){
            $where['a.name'] = array('like',"%$name%");
        }
        if(!empty($status)){ 
            $where['a.my_status'] = array('eq', $status);
        }
        if(!empty($category)){
            $where['a.cid'] = array('eq',$category);
        }
        $result = M('jc_product')
                        ->alias('a')
                        ->field('a.id,b.name as category_name,a.sku_id,a.name,a.original_price,a.promote_price,a.base_buy_num,a.my_status,c.name as shops_name')
                        ->join('left join dsy_jc_category as b on a.cid=b.id')
                        ->join('left join dsy_jc_shops as c on a.sid=c.id')
                        ->where($where)
                        ->page($page,$limit)
                        ->select();
        $num = M('jc_product')
                        ->alias('a')
                        ->join('left join dsy_jc_category as b on a.cid=b.id')
                        ->join('left join dsy_jc_shops as c on a.sid=c.id')
                        ->where($where)
                        ->count();
        return Response::mjson($result,$num);
    }
    public function company_product_edit(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'请选择要编辑的商品');
        }

        $result = M('jc_product')
                        ->alias('a')
                        ->field('a.id,a.sku_id,a.name,a.promote_price,a.original_price,a.base_buy_num,b.name as category_name,
                                0 as freight,a.sale,a.good_evaluate,a.detail,a.specifications,a.pas,c.image')
                        ->join('left join dsy_jc_category as b on a.cid=b.id')
                        ->join('left join dsy_jc_product_image as c on a.id=c.pid')
                        ->where(['a.id'=>$id,'c.type'=>0])
                        ->find();
        $this->assign('result',$result);
        $this->display();
    }
    public function company_product_save(){
        $id = I('id');
        $price = I('price');
        if(empty($id)){
            return Response::show(300,"请选择要修改的商品");
        }
        if(empty($price)){
            return Response::show(300,"商品价格不能为空，请重新输入");
        }

        $result = M('jc_product')->where(['id'=>$id])->setField(['promote_price'=>$price]);
        if($result !== false){
            return Response::show(200,"保存成功");
        }else{
            return Response::show(300,"保存失败");
        }
    }
    public function company_product_status(){
        $id = I('ids');
        $status = I('status');
        
        if(empty($id)){
            return Response::show(300,'请选择要操作的商品');
        }
        $result = M('jc_product')->where(['id'=>array('in',$id)])->save(['my_status'=>$status]);
        if($result !== false){
            return Response::show(200,'操作成功');
        }else{
            return Response::show(300,'操作失败');
        }
    }
    public function company_product_detail(){
        $id = I('id');
        $result = M('jc_product')
                    ->alias('a')
                    ->field('a.id,a.sku_id,a.name,a.promote_price,b.name as category_name,
                        0 as freight,a.sale,a.good_evaluate,a.detail,a.specifications,a.pas,c.image')
                    ->join('left join dsy_jc_category as b on a.cid=b.id')
                    ->join('left join dsy_jc_product_image as c on a.id=c.pid')
                    ->where(['a.id'=>$id,'c.type'=>0])
                    ->find();
        $image = M('jc_product_image')->field()->where(['pid'=>$result['id']])->select();
        $this->assign('result',$result);
        $this->display();
    }
    

    //集采订单
    public function product_order_index(){
        $this->display();
    }
    public function product_order_data(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $order_num = I('order_num');
        $user_name = I('user_name');
        $status = I('status');
        $start_time = I('start1');
        $end_time = I('end');

        if(!empty($order_num)){
            $where['a.logistics_order_num'] = array('eq',$order_num);
        }
        if(!empty($user_name)){
            $where['a.address_name'] = array('like',"%$user_name");
        }
        if(!empty($status)){
            $where['a.status'] = array('eq',$status);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }

        $result = M('jc_order_logistics')
                    ->alias('a')
                    ->field('a.id,a.order_id,a.sid,a.logistics_order_num as order_num,a.create_time,b.user_name,a.status,a.price as order_price,0 as cost_price,
                            0 as profit_price')
                    ->join('left join t_user as b on a.user_id=b.user_id')
                    ->where($where)
                    ->order('a.create_time desc')
                    ->page($page,$limit)
                    ->select();
        $num = M('jc_order_logistics')
                    ->alias('a')
                    ->join('left join t_user as b on a.user_id=b.user_id')
                    ->where($where)
                    ->count();
        foreach($result as $key => $val){
            $order_item = M('jc_order_item')
                            ->alias('a')
                            ->field('a.id,a.original_price,a.order_item_price,a.number,b.name')
                            ->join('left join dsy_jc_shops as b on a.sid = b.id')
                            ->where(['a.order_id'=>$val['order_id'] ,'a.sid'=>$val['sid']])
                            ->select();
            $cost_price = 0;
            $shops_name = [];
            foreach($order_item as $k => $v){
                $cost_price += $v['original_price'] * $v['number'];
                if(!in_array($v['name'],$shops_name)){
                    $shops_name[] = $v['name'];
                }
            }
            $result[$key]['cost_price'] = number_format($cost_price,2,'.','');
            $result[$key]['profit_price'] = ($val['order_price'] * 1000 - $cost_price * 1000) / 1000;
            $result[$key]['shops_name'] = implode(',',$shops_name);
        }

        return Response::mjson($result,$num);
                    
    }
    public function product_order_detail(){
        $id = I('id');
        $where['a.order_id'] = array('eq',$id);
        //获取基本信息
        $info = M('jc_order')
                    ->alias('a')
                    ->field('a.order_id,a.order_num,a.create_time,b.user_name,a.status,d.content,a.user_message,
                            a.order_price,a.address_name,a.address,a.phone,a.total_price,a.order_price,c.price,c.sid')
                    ->join('left join t_user as b on a.user_id=b.user_id')
                    ->join('left join dsy_jc_order_item as c on a.order_id=c.order_id')
                    ->join('left join dsy_jc_order_liv_message as d on a.order_id=d.order_id')
                    ->where($where)
                    ->find();
        $return = M('jc_order_after_sale')
                    ->alias('a')
                    ->field('a.id,a.after_sale_num,e.name,a.create_time,d.order_item_price,a.return_num,a.status,a.type')
                    ->join('left join dsy_jc_order_item as d on a.id=d.after_sale_id')
                    ->join('left join dsy_jc_product as e on d.product_id=e.id')
                    ->where(['a.order_id'=>$id])
                    ->select();
        //0-退货完成/退款中  1-退款完成 2-退款失败 3-退货中  5-退货失败  6-换货中  7-换货成功  8-换货失败 9-待处理 10-已取消 11-已经申请第三方退款
        $status = [0=>'退款中',1=>'退款完成',3=>'退货中',5=>'退货失败',6=>'换货中',7=>'换货成功',8=>'换货失败',9=>'待处理',10=>'已取消'];
        foreach($return as $key => $val){
            $return[$key]['return_money'] = $val['order_item_price'] * $val['return_num'];
            $return[$key]['step'] = $status[$val['status']];
        }
        $this->assign('return',$return);
        $this->assign('info',$info);
        $this->assign('ordernum',$info['order_num']);
        $this->display();
    }
    public function order_product_detail(){
        $id = I('order_id');
        $sid = I('sid');
        if(!empty($id)){
            $where['a.order_id'] = array('eq',$id);
            $where['a.sid'] = array('eq',$sid);
            $product = M('jc_order_item')
                        ->alias('a')
                        ->field('b.id,b.sku_id,b.name,a.number,a.order_item_price,b.specifications,0 as step,c.name as shops_name,a.after_sale_id')
                        ->join('left join dsy_jc_product as b on a.product_id=b.id')
                        ->join('left join dsy_jc_shops as c on a.sid=c.id')
                        ->where($where)
                        ->select();
            $num = M('jc_order_item')
                        ->alias('a')
                        ->join('left join dsy_jc_product as b on a.product_id=b.id')
                        ->where($where)
                        ->count();
            $status = [0=>'退款中',1=>'退款完成',3=>'退货中',5=>'退货失败',6=>'换货中',7=>'换货成功',8=>'换货失败',9=>'待处理',10=>'已取消'];
            foreach($product as $key => $val){
                if(!empty($val['after_sale_id'])){
                    $order_return = M('jc_order_after_sale')->field('type,status')->where(['id'=>$val['after_sale_id']])->find();
                    $product[$key]['status'] = $status[$order_return['status']];
                    $product[$key]['type'] = $order_return['type'];
                }else{
                    $product[$key]['status'] = '无';
                    $product[$key]['type'] = 4;
                }
            }
            return Response::mjson($product,$num);
        }
    }

    //售后订单
    public function product_after_order(){
        $this->display();
    }
    public function product_after_order_data(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $order_num = I('order_num');
        $user_name = I('user_name');
        if(!empty($order_num)){
            $where['b.order_num'] = array('eq',$order_num);
        }
        if(!empty($user_name)){
            $where['c.user_name'] = array('eq',$user_name);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }
        $result = M('jc_order_after_sale')
                    ->alias('a')
                    ->field('a.order_id,a.after_sale_num,b.order_num,c.user_name,e.name as pro_name,a.return_num,d.order_item_price,a.return_reason,a.return_reason_detail,a.status,s.name as shop_name')
                    ->join('left join dsy_jc_order as b on a.order_id=b.order_id')
                    ->join('left join t_user as c on a.user_id=c.user_id')
                    ->join('left join dsy_jc_order_item as d on a.id=d.after_sale_id')
                    ->join('left join dsy_jc_product as e on d.product_id=e.id')
                    ->join('left join dsy_jc_shops as s on d.sid=s.id')
                    ->where($where)
                    ->order('a.create_time asc')
                    ->page($page,$limit)
                    ->select();
        $num = M('jc_order_after_sale')
                    ->alias('a')
                    ->join('left join dsy_jc_order as b on a.order_id=b.order_id')
                    ->join('left join t_user as c on a.user_id=c.user_id')
                    ->join('left join dsy_jc_order_item as d on a.id=d.after_sale_id')
                    ->join('left join dsy_jc_product as e on d.product_id=e.id')
                    ->join('left join dsy_jc_shops as s on d.sid=s.id')
                    ->where($where)
                    ->count();
        foreach($result as $key => $val){
            $result[$key]['return_price'] = $val['order_item_price'] * $val['return_num'];
        }
        return Response::mjson($result,$num);
    }
    //供应商列表
    public function company_shops_index(){
        $this->display();
    }
    public function company_shops_data(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        $status = I('status');

        if(!empty($name)){
            $where['name'] = array('like',"%$name%");
        }
        if(!empty($status)){
            $where['status'] = array('eq',$status);
        }

        $result = M('jc_shops')
                    ->field('id,username,name,mobile,time,status')
                    ->where($where)
                    ->order('time desc')
                    ->page($page,$limit)
                    ->select();

        $num = M('jc_shops')->where($where)->count();

        return Response::mjson($result,$num);
    }

    public function company_shops_detail(){
        $id = I('id');
        $store = M('jc_shops');
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

            $vo['logo_url'] = format_img($vo['logo_url'], WEB_URL_IMG_UP);
            $vo['idcard1'] = format_img($vo['idcard1'], WEB_URL_IMG_UP);
            $vo['idcard2'] = format_img($vo['idcard2'], WEB_URL_IMG_UP);
            $vo['business_license'] = format_img($vo['business_license'], WEB_URL_IMG_UP);
            $vo['spltxkz'] = format_img($vo['spltxkz'], WEB_URL_IMG_UP);
            $vo['jlxkz'] = format_img($vo['jlxkz'], WEB_URL_IMG_UP);
        }
        $this->assign('vo',$vo);
        $this->display();
    }
    public function company_shops_add(){
        $this->display();
    }
    public function company_shops_save(){
        $name = I('name','');
        $username = I('username','');
        $pwd = I('pwd','');
        $mobile = I('mobile','');

        if(empty($name)){
            return Response::show(300,'请输入商家名称');
        }elseif (empty($username)){
            return Response::show(300,'请输入商家账号');
        }elseif (empty($pwd)){
            return Response::show(300,'请输入商家密码');
        }elseif (empty($mobile)) {
            return Response::show(300, '请输入联系方式');
        }else{
            $where['username'] = array('eq', $username);
            $dd = M('jc_shops', 'dsy_')
                ->where($where)
                ->select();
            if (!empty($dd)) {
                return Response::show(300,'商家账号已存在');
            }
            if (check_mobile($username) == 0) {
                return Response::show(300, '商家账号格式不正确，请输入真实有效的手机号');
            }
            if (preg_match("/[\x7f-\xff]/",$pwd)) {
                return Response::show(300,'商家登陆密码中不能有汉字，请重新输入');
            }
            if(strlen($pwd) < 6){
                return Response::show(300,'登陆密码不得小于6字符，请重新输入');
            }
            $de_where['name'] = array('eq', $name);
            $ddd = M('jc_shops', 'dsy_')
                ->where($de_where)
                ->select();
            if (!empty($ddd)) {
                return Response::show(300,'商家名称已存在');
            }
            $data = array(
                'name'=>$name,
                'username'=>$username,
                'pwd'=>md5($pwd),
                'mobile'=>$mobile,
                'time'=>NOW,
                'status'=>1
            );
            //添加操作日志
            $admin_log = '新增供应商:' . $name;
            $store = M('jc_shops');
            $result = $store->add($data);
            $arr['username'] = $username;
            $arr['name'] = $name;
            if($result){
                admin_log($admin_log, 1, 'dsy_jc_shops:' . $result);
                return Response::show(200,$arr);
            } else {
                admin_log($admin_log, 0, 'dsy_jc_shops');
                return Response::show(400,'添加失败');
            }
        }
    }

    //企业用户
    public function company_vip_index(){
        $this->display();
    }
    public function company_vip_data(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        if(!empty($name)){
            $where['user_name'] = array('eq',$name);
        }
        $where['company_vip_status'] = array('eq',1);
        $result = M('user','t_')
                    ->field('user_id,user_name,user_type,update_time,company_vip_status')
                    ->where($where)
                    ->order('update_time desc')
                    ->select();
        $num = M('user','t_')
                    ->where($where)
                    ->count();
                    
        return Response::mjson($result,$num);
    }
    public function company_vip_status(){
        $uid = I('uid');
        $user_name = I('user_name');
        $status = I('status');
        if(empty($uid)){
            return Response::show(300,'请选择要操作的集采用户');
        }
        if($status == '' || $status == NULL){
            return Response::show(300,'缺少参数');
        }
        $result = M('user','t_')->where(['user_id'=>$uid,'user_name'=>$user_name])->save(['company_vip_status'=>$status]);
        if($result !== false){
            return Response::show(200,'操作成功');
        }else{
            return Response::show(300,'操作失败');
        }
    }
    public function company_vip_add(){
        $user_name = I('user_name');
        if(empty($user_name)){
            return Response::show(300,'集采用户手机号不能为空');
        }
        if (check_mobile($user_name) == 0) {
            return Response::show(300, '手机号码格式不正确，请重新输入');
        }
        $check_user = M('user','t_')->field('user_id,user_name,company_vip_status')->where(['user_name'=>$user_name,'user_status'=>1])->find();
        if(empty($check_user)){
            return Response::show(300,'你要开通集采身份的用户不存在或账户已冻结');
        }
        if($check_user['company_vip_status'] == 1){
            return Response::show(300,'该用户已开通集采身份');
        }
        $data = [
            'company_vip_status' => 1,
            'update_time' => date('Y-m-d H:i:s',time())
        ];
        $result = M('user','t_')->where(['user_name'=>$user_name,'user_status'=>1])->save($data);
        if($result !== false){
            return Response::show(200,'开通成功');
        }else{
            return Response::show(300,'开通失败');
        }
    }
}