<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/3 0003
 * Time: 下午 3:37
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;
use Think\Exception;

class ExchangeController extends Controller{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 兑换券套餐列表
     **/
    public function set_meal(){

        $this->display('setmeal_list');
    }


    /**
     * 兑换券套餐列表数据
    **/
    public function set_meal_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $name = trim(I('name',''));

        $model = M('company_exchange_package','dsy_');
        if(!empty($name) || $name == 0){
            $where['name'] = array('like',"%$name%");
        }
        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->order('id desc')
            ->select();

        $num = $model
            ->where($where)
            ->count();
        foreach($info as $key=>$value){
            $pids = $value['pids'];
            if($value['type'] != 3){
                $names = getProductsNames($pids);
            }else{
                $names = getSnProductsNames($pids);
            }
            $info[$key]['pids'] = $names;
            if(!empty($value['pic'])){
                $img = format_img($value['pic'], IMG_VIEW);
                $info[$key]['pic'] =  '<a href="'.$img.'" target="view_window">点击查看</a>';
            }
            if(!empty($value['aid'])){
                $where_a['id'] = array('eq',$value['aid']);
                $aname = M('company_exchange_activity','dsy_')->where($where_a)->getField('name');
                $info[$key]['aid'] = $aname;
            }else{
                $info[$key]['aid'] = '';
            }


        }
        return Response::mjson($info,$num);

    }



    /**
     * 添加套餐兑换券页面
    **/
    public function setmeal_add_index(){
        $this->display('setmeal_add');
    }


    /**
     * 选择套餐商品页面
     **/
    public function choose_products(){
        $info = I('info','');
        $this->assign('info',$info);

        $products_type = I('products_type','');
        if(empty($products_type)){
            $products_type = 4;
        }
        $this->assign('products_type',$products_type);

//        $where = [];
//        $where['status'] = 1;
//        $where['upanddown'] = 1;
//        if (in_array($products_type, [1, 2])) {
//            $where['type'] = $products_type;
//        }
//        $count = M('mall_product')
//            ->where($where)
//            ->count();
        $filter = [];
        $filter[]['term']['status'] = 1;
        $filter[]['term']['upanddown'] = 1;
        if (in_array($products_type, [1, 2])) {
            $filter[]['term']['type'] = $products_type;
        }
        //运用elasticsearch查询商品名称匹配的商品id
        $url = ES_URL . '/' . ES_INDEX . '/_search';
        $data = [
            "query" => [
                "bool" => [
                    "filter" => $filter
                ]
            ],
            "_source" => ["skuid"]
        ];
        $data = json_encode($data);
        $re = es_curl($url, 'post', $data);
        $total = 0;
        if ($re['timed_out'] == false) {
            $total = $re['hits']['total'];
        }
        $this->assign('count', $total);

        $this->display('choice_products');
    }

    /**
     * 根据商品id，转换成商品名称
     **/
    public function get_products_names(){
        $ids = I('ids','');
        $type = I('type','');
        if(!empty($ids)){
            if($type == 1 || $type == 2){
                $names = getProductsNames($ids);
                $this->ajaxReturn($names);
            }elseif($type == 3){
                $names = getSnProductsNames($ids);
                $this->ajaxReturn($names);
            }
        }
    }

    /**
     * 套餐选择商品返回商品页面数据
     **/
    public function products_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex:'0';
        $limit = !empty($limit)?$limit:10;
        $pname = trim(I('pname',''));
        $model = M('mall_product','dsy_');
        $info1 = I('info',0);
        $products_type = I('products_type','');
        $pid = I('pid','');
        if(empty($products_type)){
            return false;
        }
        $where['upanddown'] = array('eq',1);
        $where['status'] = array('eq',1);
        if($products_type==1){
            $where['type'] = array('eq',1);
        }else{
            $where['type'] = array('eq',2);
        }
        if(!empty($pid)){
            $where['skuid'] = array('eq',$pid);
        }
        $id_string = sphinx('name',$pname,$where,'',$page,$limit,'');
        $id_string_id = $id_string['id_str'];
        $num = $id_string['total_found'];

        $where_s['id'] = array('in',$id_string_id);
        $info = $model
            ->field('id,cnum,skuid,price,jd_price,cost_price,name')
            ->where($where_s)
            ->select();

        if($info1 !== 0){
            $array = explode(',',$info1);
            foreach($info as $key=>$value){
                if(in_array($value['id'],$array)){
                    $info[$key]['LAY_CHECKED'] = true;
                }
            }
        }
        if($info1 != 0){
            $parray = explode(',',$info1);
        }else{
            $parray = array();
        }
        $data['code'] = 200;
        $data['msg'] = '';
        $data['count'] = $num;
        $data['data'] = $info;
        $data['is_data'] = $parray;
        $this->ajaxReturn($data);
    }


    /**
     * 预览套餐页面
    **/
    public function view(){
        $pids = I('pids','');
        $name = I('name','');
        $type = I('products_type','');
        if(!empty($pids)){
            if($type == 1 || $type  == 2){
                $model = M('mall_product','dsy_');
                $where['id'] = array('in',$pids);
                $info = $model
                    ->where($where)
                    ->field('name,pic')
                    ->select();
                if($type==2){
                    foreach($info as $key=>$value){
                        $info[$key]['pic'] = format_img($value['pic'], IMG_VIEW);
                    }
                }
            }elseif($type == 3){
                $model = M('sn_product');
                $where['product_id'] = array('in',$pids);
                $info = $model
                    ->where($where)
                    ->field('name,img as pic')
                    ->select();
            }
            $this->assign('info',$info);
        }
        $count = strlen($name);
        if($count > 9){
            $name = mb_substr($name,0,9,'UTF-8') . '...';
        }
        $this->assign('name',$name);
        $this->display('view');
    }


    /**
     * 添加套餐操作
    **/
    public function add_setmeal(){
        $name = trim(I('name',''));
        $price = I('price','');
        $products = I('products','');
        $pic = $_FILES['pic'];
        $get_type = I('get_type','');
        $products_type = I('products_type','');
        $id = I('id','');
        if(empty($id)){
            if(!empty($name) || $name=='0'){

            }else{
                return Response::show(300,'请填写完整后提交');
            }
            if(empty($price)||empty($products)||empty($pic)||empty($products_type)||empty($get_type)){
                return Response::show(300,'请填写完整后提交');
            }
        }else{
            if(!empty($name) || $name=='0'){

            }else{
                return Response::show(300,'请填写完整后提交');
            }
            if(empty($price)||empty($products)){
                return Response::show(300,'请填写完整后提交');
            }
        }

        if(mb_strlen($name,'utf8')>18){
            return Response::show(400,'名称不得超过18个中英文数字字符');
        }
        if(is_numeric($price) == false){
            return Response::show(400,'请填写正确的数字格式');
        }
        if($price<1){
            return Response::show(400,'价格不得低于1');
        }
        $remarks = I('remarks','');

        if(!empty($pic)){
            $pic_string = uploadfile($pic);
            if(empty($pic_string)){
                return Response::show(400,'图片上传失败');
            }
            $data['pic'] = $pic_string;
        }

        $data['name'] = $name;
        $data['pids'] = $products;
        $data['price'] = $price;
        $data['type'] = $products_type;
        $data['get_type'] = $get_type;
        if(!empty($remarks)){
            $data['remarks'] = $remarks;
        }
        $save_data = array();
        $model = M('company_exchange_package','dsy_');
        if(empty($id)){
            $data['time'] = NOW;
            //添加
            $add = $model->add($data);
            if($get_type == 2){
                $products_array = explode(',',$products);
                foreach($products_array as $key=>$value){
                    if($products_type == 1 || $products_type == 2){
                        $pinfo = getpinfobyid($value);
                    }elseif($products_type == 3){
                        $pinfo = getsnpinfobyid($value);
                    }
                    $array['pid'] = $value;
                    $array['pname'] = $pinfo['pname'];
                    $array['price'] = $pinfo['price'];
                    $array['wz_price'] = $pinfo['wz_price'];
                    $array['cost_price'] = $pinfo['cost_price'];
                    $array['save_price'] = $pinfo['price'];
                    $array['time'] = NOW;
                    $array['tid'] = $add;
                    $array['type'] = 1;
                    $array['good_type'] = $pinfo['type'];
                    $array['skuid'] = $pinfo['skuid'];
                    $save_data[] = $array;
                }
                $add_two = M('company_exchange_record_products','dsy_')->addAll($save_data);
            }
            //添加操作日志
            $admin_log = '新增兑换券套餐:' . $name;
            if($add !== false && $add_two !== false){
                $model->commit();
                admin_log($admin_log, 1, 'dsy_company_exchange_package:' . $add);
                return Response::show(200,'新增成功');
            }else{
                $model->rollback();
                admin_log($admin_log, 0, 'dsy_company_exchange_package');
                return Response::show(400,'新增失败');
            }
        }else{
            //修改
            if($get_type==2){
                //删除数据
                $where_check['tid'] = array('eq',$id);
                $all_check = M('company_exchange_record_products','dsy_')->where($where_check)->getField('pid',true);
                $products_array = explode(',',$products);
                $is_change = 0;
                if(count($all_check) == count($products_array)){
                    foreach($products_array as $u=>$i){
                        if(in_array($i,$all_check)==false){
                            $is_change = 1;
                        }
                    }
                }else{
                    $is_change = 1;
                }
                if($is_change==1){
                    $where_tsave['tid'] = array('eq',$id);
                    $del_data['del'] = 2;
                    $del = M('company_exchange_record_products','dsy_')->where($where_tsave)->save($del_data);
                    //新增数据
                    foreach($products_array as $key=>$value){
                        if($products_type == 1 || $products_type == 2){
                            $pinfo = getpinfobyid($value);
                        }elseif($products_type == 3){
                            $pinfo = getsnpinfobyid($value);
                        }
                        $array['pid'] = $value;
                        $array['pname'] = $pinfo['pname'];
                        $array['price'] = $pinfo['price'];
                        $array['wz_price'] = $pinfo['wz_price'];
                        $array['cost_price'] = $pinfo['cost_price'];
                        $array['save_price'] = $pinfo['price'];
                        $array['time'] = NOW;
                        $array['tid'] = $id;
                        $array['type'] = 1;
                        $array['good_type'] = $pinfo['type'];
                        $array['skuid'] = $pinfo['skuid'];
                        $save_data[] = $array;
                    }
                    $add_two = M('company_exchange_record_products','dsy_')->addAll($save_data);
                }

            }
            $where_save['id'] = array('eq',$id);
            $save = M('company_exchange_package','dsy_')->where($where_save)->save($data);
            //添加操作日志
            $admin_log = '编辑兑换券套餐:' . $name;
            if($get_type==2){
                if($is_change==1){
                    if($save !== false && $add_two !== false){
                        $model->commit();
                        admin_log($admin_log, 1, 'dsy_company_exchange_package:' . $id);
                        return Response::show(200,'修改成功');
                    }else{
                        $model->rollback();
                        admin_log($admin_log, 0, 'dsy_company_exchange_package:' . $id);
                        return Response::show(400,'修改失败');
                    }
                }else{
                    if($save !== false){
                        $model->commit();
                        admin_log($admin_log, 1);
                        return Response::show(200,'修改成功');
                    }else{
                        $model->rollback();
                        admin_log($admin_log, 0);
                        return Response::show(400,'修改失败');
                    }
                }
            }else{
                if($save !== false){
                    $model->commit();
                    admin_log($admin_log, 1);
                    return Response::show(200,'修改成功');
                }else{
                    $model->rollback();
                    admin_log($admin_log, 0);
                    return Response::show(400,'修改失败');
                }
            }
        }
    }
    /**
     * 获取套餐商品所有价格价格
     */
    public function get_product_price(){
        $products = I('products');
        $products_type = I('products_type');
        $price = I('price','');
        $products_array = explode(',',$products);
        $count_price = 0;
        foreach($products_array as $key=>$value){
            if($products_type == 1 || $products_type == 2){
                $pinfo = getpinfobyid($value);
            }elseif($products_type == 3){
                $pinfo = getsnpinfobyid($value);
            }
            $count_price += $pinfo['price'];
        }
        $msg = "当前套餐价格：<font style='color:red;'>" .$price. "</font>元&nbsp;&nbsp;&nbsp;商品套餐金额：<font style='color:red;'>" .$count_price. "</font>元<br /><font style='margin-left:100px;'>请您再次确认是否保存？</font>";
        return Response::show(200,$msg);
    }

    /**
     * 编辑兑换券套餐
    **/
    public function edit_setmeal_index(){
        $id = I('id');
        $info = M('company_exchange_package','dsy_')->find($id);
        if($info['type'] != 3){
            $names = getProductsNames($info['pids']);
        }else{
            $names = getSnProductsNames($info['pids']);
        }
        $this->assign('names',$names);

        $info['pic'] = format_img($info['pic'], IMG_VIEW);
        $this->assign('info',$info);
        $this->assign('id',$id);
        $this->assign('goods',$info['pids']);
        $this->display('setmeal_add');
    }


    /**
     * 禁用套餐
     **/
    public function del_package(){
        $id = I('ids','');
        $id = $id[0];
        if(empty($id)){
            return Response::show(300,'缺少参数');
        }
        $data['del'] = 1;
        $where['id'] = array('eq',$id);
        //添加操作日志
        $admin_log = '禁用兑换券套餐:' . M('company_exchange_package', 'dsy_')->where($where)->getField('name');
        $del = M('company_exchange_package','dsy_')->where($where)->save($data);
        if($del !== false){
            admin_log($admin_log, 1, 'dsy_company_exchange_package:' . $id);
            return Response::show(200,'删除成功');
        }else{
            admin_log($admin_log, 0, 'dsy_company_exchange_package:' . $id);
            return Response::show(400,'删除失败');
        }
    }

    /***
     * 启用套餐
     **/
    public function open_package(){
        $id = I('ids','');
        $id = $id[0];
        if(empty($id)){
            return Response::show(300,'缺少参数');
        }
        $data['del'] = 0;
        $where['id'] = array('eq',$id);
        //添加操作日志
        $admin_log = '启用兑换券套餐:' . M('company_exchange_package', 'dsy_')->where($where)->getField('name');
        $del = M('company_exchange_package','dsy_')->where($where)->save($data);
        if($del !== false){
            admin_log($admin_log, 1, 'dsy_company_exchange_package:' . $id);
            return Response::show(200,'启用成功');
        }else{
            admin_log($admin_log, 0, 'dsy_company_exchange_package:' . $id);
            return Response::show(400,'启用失败');
        }
    }




    /**
     * 兑换券活动
    **/
    public function setmeal_activity_index(){

        $this->display('activity_index');
    }



    /**
     * 兑换券活动列表数据
    **/
    public function activity_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $aname = trim(I('aname',''));

        if(!empty($aname) || $aname == 0){
            $where['a.name'] = array('like',"%$aname%");
        }
        $info = M('company_exchange_activity','dsy_')
            ->where($where)
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->page($page,$limit)
            ->order('id desc')
            ->field('a.id,a.name,a.start_time,a.end_time,a.type,a.pic,a.type,a.is_del,b.corporate_name,a.num,a.money')
            ->select();
        $num = M('company_exchange_activity','dsy_')
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->where($where)
            ->count();
        if(!empty($info)){
            foreach($info as $key=>$value){
                if(!empty($value['pic'])){
                    $img = format_img($value['pic'], IMG_VIEW);
                    $info[$key]['pic'] =  '<a href="'.$img.'" target="view_window">点击查看</a>';
                }
                $now = date('Y-m-d');
                if($now<$value['start_time']){
                    $info[$key]['status'] = '未开始';
                }elseif($value['end_time']<$now){
                    $info[$key]['status'] = '已结束';
                }else{
                    $info[$key]['status'] = '活动中';
                }
            }
        }
        return Response::mjson($info,$num);
    }


    /**
     * 添加活动页面
    **/
    public function add_activity(){
        $info = getCorporateList();
        $this->assign('companys',$info);
        $this->display('activity_add');
    }



    /**
     * 活动添加页面返回所有套餐数据
     **/
    public function all_packages(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $where['del'] = array('neq',1);
//        $where['aid'] = array('exp','is null');

        $model = M('company_exchange_package','dsy_');
        $setmeals = I('setmeals',0);
        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->order('id desc')
            ->select();
        $num = $model->where($where)->count();
        foreach($info as $key=>$value){
            $pids = $value['pids'];
            if($value['type'] != 3){
                $names = getProductsNames($pids);
            }else{
                $names = getSnProductsNames($pids);
            }
            $info[$key]['pids'] = $names;
            if(!empty($value['pic'])){
                $img = format_img($value['pic'], IMG_VIEW);
                $info[$key]['pic'] =  '<a href="'.$img.'" target="view_window">点击查看</a>';
            }
        }
        if($setmeals !== 0){
            $array = explode(',',$setmeals);
            foreach($info as $key=>$value){
                if(in_array($value['id'],$array)){
                    $info[$key]['LAY_CHECKED'] = true;
                }
            }
        }
        if($setmeals != 0){
            $parray = explode(',',$setmeals);
        }else{
            $parray = array();
        }
        $data['code'] = 200;
        $data['msg'] = '';
        $data['count'] = $num;
        $data['data'] = $info;
        $data['is_data'] = $parray;
        $this->ajaxReturn($data);
    }


    /**
     * 显示选择套餐页面
     **/
    public function choose_setmeals(){
        $setmeals = I('setmeals','');
        $model = M('company_exchange_package','dsy_');
        $where['del'] = array('neq',1);
//        $where['aid'] = array('exp','is null');
        $num = $model->where($where)->count();
        $this->assign('setmeals',$setmeals);
        $this->assign('count',$num);
        $this->display('choice_setmeals');
    }


    /**
     * 根据套餐ids得到套餐名称
     **/
    public function get_setmeals_names(){
        $ids = I('ids','');
        $model = M('company_exchange_package','dsy_');
        $where['id'] = array('in',$ids);
        $names = $model->where($where)->getField('name',true);
        $name = implode(',',$names);
        $this->ajaxReturn($name);
    }


    /**
     * 添加活动操作
    **/
    public function add_activity_do(){
        $name = trim($_POST['name']);
        $type = $_POST['type'];
        $company = $_POST['company'];
        $packages = $_POST['packages'];
        $start_time = $_POST['start1'];
        $end_time = $_POST['end'];
        $num = $_POST['num'];
        $pic = $_FILES['pic'];
        $money = I('money','');
        if(!empty($name) || $name=='0'){

        }else{
            return Response::show(300,'请填写完整后提交');
        }
        if(empty($type)||empty($company)||empty($packages)||empty($pic)||empty($start_time)||empty($end_time)||empty($num)||empty($money)){
            return Response::show(300,'请填写完整后提交');
        }
        if(is_numeric($num) == false){
            return Response::show(400,'请填写正确的数字');
        }
        if($num>5000||$num<1){
            return Response::show(400,'数量必须在1~5000以内');
        }
        $now = date('Y-m-d');
        if($start_time<$now || $end_time<$now){
            return Response::show(400,'不能选择过去时间');
        }
        if($start_time>$end_time){
            return Response::show(400,'开始时间不能大于结束时间');
        }
        $pic_string = uploadfile($pic);
        if(empty($pic_string)){
            return Response::show(400,'图片上传失败');
        }

        //判断套餐是否被其他活动占用
//        $check = checkIsuse($packages);
//        if($check == true){
//            return Response::show(400,'您选择的套餐中有被其他活动占用的套餐，请重新选择');
//        }
        $model = M('company_exchange_activity','dsy_');
        $model->startTrans();
        $data['name'] = $name;
        $data['cid'] = $company;
        $data['num'] = $num;
        $data['start_time'] = $start_time;
        $data['end_time'] = $end_time;
        $data['package'] = $packages;
        $data['type'] = $type;
        $data['pic'] = $pic_string;
        $data['time'] = NOW;
        $data['money'] = $money;
        $add_one = $model->add($data);

        //套餐对应的活动加上
        $parray = explode(',',$packages);
        $error = true;
        foreach($parray as $key=>$value){
            $where_p['id'] = array('eq',$value);
            $data_p['aid'] = $add_one;
            $save = M('company_exchange_package','dsy_')->where($where_p)->save($data_p);
            if($save==false){
                $error = false;
            }
        }

        //生成兑换券码
        $getCode = new \Org\Util\getCode();
        $code_list = $this->codeDiff($getCode, $num);
        $data_list = [];
        foreach ($code_list as $v) {
            $data_list[] = [
                'num' => $v,
                'aid' => $add_one,
                'end_time' => $end_time,
            ];
        }
        /*
        $code_array = array();
        for($i=0;$i<$num;$i++){
            for(;;){
                $code = roundCode();
                if(in_array($code,$code_array)==false){
                    $code_array[] = $code;
                    break;
                }
            }
            $data1['num'] = $code;
            $data1['aid'] = $add_one;
            $data_list[] = $data1;
        }
        */
        //添加操作日志
        $admin_log = '新增兑换券活动:' . $name;
        $add_two = M('company_exchange_record','dsy_')->addAll($data_list);
        if($add_one != false && $add_two != false && $error != false){
            $model->commit();
            admin_log($admin_log, 1, 'dsy_company_exchange_activity:' . $add_one);
            return Response::show(200,'添加成功');
        }else{
            $model->rollback();
            admin_log($admin_log, 0, 'dsy_company_exchange_activity');
            return Response::show(400,'添加失败');
        }

    }

    //生成验证码并判断有没有重复的，排除数据库
    private function codeDiff($getCode, $num)
    {
        //查询数据库有没有重复的
        $getCode->getCodeArr($num, 6);
        $code_arr = $getCode->code_arr;

        //查询数据库有没有重复的
        $code_cf = M('company_exchange_record', 'dsy_')->where(['num' => ['in', $code_arr]])->getField('num', true);
        $code_count = count($code_cf);
        if ($code_count > 0) {
            $getCode->code_arr = array_diff($code_arr, $code_cf);
            $this->codeDiff($getCode, $code_count);
        }

        return $getCode->code_arr;
    }

    /**
     * 活动详情页面
    **/
    public function activity_detail(){
        $id = I('id','');
        $info = M('company_exchange_activity')->find($id);
        $package = $info['package'];
        //查询套餐情况
        $where['id'] = array('in',$package);
        $pinfo = M('company_exchange_package')
            ->where($where)
            ->field('name,pids,price,type')
            ->select();
        foreach($pinfo as $key=>$value){
            if($value['type'] == 1 || $value['type'] == 2){
                $pnames = getProductsNames($value['pids']);
            }elseif($value['type'] == 3){
                $pnames = getSnProductsNames($value['pids']);
            }
            $pinfo[$key]['pids'] = $pnames;
        }

        $this->assign('setmeal',$pinfo);
        $this->display('activity_detail');
    }


    /**
     * 删除活动
     **/
    public function del_product_activity(){
        $id = I('ids');
        $id = $id[0];
        $where['id'] = array('eq',$id);
        $data['is_del'] = 1;
        //添加操作日志
        $admin_log = '禁用兑换券活动:' . M('company_exchange_activity', 'dsy_')->where($where)->getField('name');
        $del = M('company_exchange_activity','dsy_')->where($where)->save($data);
        if($del != false){
            admin_log($admin_log, 1, 'dsy_company_exchange_activity:' . $id);
            return Response::show('200','禁用成功');
        }else{
            admin_log($admin_log, 0, 'dsy_company_exchange_activity:' . $id);
            return Response::show('400','禁用失败');
        }
    }

    /**
     * 启用活动
     **/
    public function open_product_activity(){
        $id = I('ids');
        $id = $id[0];
        $where['id'] = array('eq',$id);
        $data['is_del'] = 0;
        //添加操作日志
        $admin_log = '启用兑换券活动:' . M('company_exchange_activity', 'dsy_')->where($where)->getField('name');
        $del = M('company_exchange_activity','dsy_')->where($where)->save($data);
        if($del != false){
            admin_log($admin_log, 1, 'dsy_company_exchange_activity:' . $id);
            return Response::show('200','启用成功');
        }else{
            admin_log($admin_log, 0, 'dsy_company_exchange_activity:' . $id);
            return Response::show('400','启用失败');
        }
    }

    /**
     *兑换券记录
    **/
    public function exchange_index(){


        $this->display('exchange_index');
    }

    /**
     * 兑换券记录列表数据
    **/
    public function exchange_list(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;

        $aname = trim(I('aname',''));
        if(!empty($aname) || $aname == 0){
            $where['a.name'] = array('like',"%$aname%");
        }
        $info = M('company_exchange_activity','dsy_')
            ->where($where)
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->page($page,$limit)
            ->order('id desc')
            ->field('a.id,a.name,a.start_time,a.end_time,a.type,a.pic,a.type,a.is_del,b.corporate_name,a.num,a.package')
            ->select();

        $num = M('company_exchange_activity','dsy_')
            ->join('as a left join t_corporate as b on a.cid = b.corporate_id')
            ->where($where)
            ->count();

        if(!empty($info)){
            foreach($info as $key=>$value){
                //包含套餐名称
                $where_names['id'] = array('in',$value['package']);
                $names = M('company_exchange_package')->where($where_names)->getField('name',true);
                $info[$key]['pnames'] = implode(',',$names);
                //已经兑换人数
                $where_al['aid'] = array('eq',$value['id']);
                $where_al['status'] = array('eq',1);
                $al_num = M('company_exchange_record')->where($where_al)->count();
                $info[$key]['al_num'] = $al_num;
                //未兑换的人数
                $info[$key]['no_num'] = $value['num']-$al_num;
                //是否过期
                if($value['end_time']<date('Y-m-d')){
                    $info[$key]['over'] = '已过期';
                }else{
                    $info[$key]['over'] = '未过期';
                }

            }
        }
        return Response::mjson($info,$num);

    }


    /**
     * 兑换券兑换记录详情页面
    **/
    public function exchange_detail_index(){
        $id = I('id');
        $this->assign('id',$id);
        $this->display('exchange_detail');
    }

    /**
     * 兑换券兑换记录详情页面数据
    **/
    public function exchange_detail_list(){
        $id = I('id','');
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;

        $where['a.aid'] = array('eq',$id);
        $info = M('company_exchange_record')
            ->alias('a')
            ->field('a.*,b.type as act_type')
            ->join('left join dsy_company_exchange_activity as b on a.aid=b.id')
            ->where($where)
            ->page($page,$limit)
            ->order('a.status desc,a.time desc')
            ->select();

        $num = M('company_exchange_record')
            ->alias('a')
            ->join('left join dsy_company_exchange_activity as b on a.aid=b.id')
            ->where($where)
            ->count();

        if(!empty($info)){
            foreach($info as $key=>$value){
                //查找用户名
                if(!empty($value['uid'])){
                    $uinfo = M('user','t_')
                    ->alias('a')
                    ->field('a.*,d.name,f.corporate_name')
                    ->join('left join t_personal as d on a.user_id=d.user_id')
                    ->join('left join t_employee as e on d.personal_id=e.personal_id')
                    ->join('left join t_corporate as f on e.corporate_id=f.corporate_id')
                    ->where(['a.user_id'=>$value['uid']])
                    ->find();
                    $info[$key]['uname'] = $uinfo['user_name'];
                    $info[$key]['name'] = $uinfo['name'];
                    $info[$key]['corporate_name'] = $uinfo['corporate_name'] ? $uinfo['corporate_name']:'非系统内在职员工/游客';
                    //查询兑换的套餐名称
                    if($value['type'] == 1){
                        $setmeal_info = unserialize($value['setmeal']);
                        $sname = $setmeal_info['name'];
                        $info[$key]['setmeal'] = $sname;
                    }else{
                        $pids = $value['goods_ids'];
                        $products = M('mall_order')
                            ->alias('mo')
                            ->join('LEFT JOIN `dsy_mall_order_specifications` mos ON mos.`ordernum`=mo.`ordernum`')
                            ->where("mo.`order_notpay_num`='" . $value['ordernum'] . "' AND mos.`pid` IN('" . $pids . "')")
                            ->field('mos.`pro_name` as pname')
                            ->select();
                        $pnames = array_column($products, 'pname');
                        $info[$key]['setmeal'] = implode(',',$pnames);
                    }
                }else{
                    $info[$key]['uname'] = '';
                    $info[$key]['time'] = '';
                    $info[$key]['setmeal'] = '尚未兑换任何套餐';

                }
            }
        }
        return Response::mjson($info,$num);

    }
    /**
     * 修改生日兑换卷结束时间
     */
    public function exchange_detail_edittime(){
        $id = I('id');
        $end_time = I('end_time');
        if(empty($id)){
            return Response::show(300,'修改异常,请稍后尝试');
        }
        if(empty($end_time)){
            return Response::show(300,'结束时间不能为空');
        }
        $where['id'] = array('eq',$id);
        $result = M('company_exchange_record')
                    ->where($where)
                    ->setField('end_time',$end_time);
        if($result){
            return Response::show(200,'修改结束时间成功');
        }else{
            return Response::show(300,'修改结束时间失败');
        }
    }

    /**
     * 已经兑换的套餐订单详情
    **/
    public function order_detail(){
        $id = I('id','');
        if(empty($id)){
            return false;
        }
        $exinfo = M('company_exchange_record')->find($id);
        if(strlen($exinfo['ordernum']) > 16){
            $notpay = $exinfo['ordernum'];
            $where_not['ordernum'] = array('eq',$notpay);
            $notnuminfo = M('mall_order_notpay')->where($where_not)->find();
            $wz_orderid = $notnuminfo['wz_orderid'];
    
    
            $where_mall['order_notpay_num'] = array('eq',$notpay);
            $mall_info = M('mall_order')->where($where_mall)->select();
    
            //获取token
            $token = selAccess_token();
            if($token==false){
                return '获取token失败';
            }
            if(empty($mall_info)){
                return false;
            }
            $products = array();
            foreach($mall_info as $kk=>$vv){
                if($vv['order_type'] == 1 || $vv['order_type'] == 5){
                    $ordernum = $vv['ordernum'];
                    $name = $vv['name'];
                    $mobile = $vv['mobile'];
                    $address = $vv['address'];
                    $pids = explode(',',$vv['pid']);
                    $enum = $vv['enum'];//订单编号
                    $etype = $vv['etype'];//快递类型
                    $travel_info = express($etype,$enum);
                    if(!empty($travel_info)){
                        foreach($travel_info as $kkk=>$vvv){
                            $travel['msgTime'] = $vvv['AcceptTime'];
                            $travel['content'] = '';
                            $travel['operator'] = $vvv['AcceptStation'];
                            $stravel[] = $travel;
                        }
                    }
                    $data['travel_info'] = $stravel;
                    foreach($pids as $key=>$value){
                        $detail = getDetalinfo($ordernum,$value);
                        if(count($detail)==count($detail,1)){
                            $num = $detail['num'];//购买数量
                            $specifications = $detail['specifications'];//规格
                            $data['pid'] = $value;
                            $data['pname'] = $detail['pname'];
                            $data['price'] = $detail['price'];
                            $data['num'] = $num;
                            $data['travel_info'] = $travel_info;
                            $data['specifications'] = $specifications;
                            $where_check['ordernum'] = array('eq',$ordernum);
                            $where_check['pid'] = array('eq',$value);
                            $check = M('mall_order_return','dsy_')->where($where_check)->find();
                            if(!empty($check)){
                                if($check['status']==1){
                                    $data['return'] = '申请售后中';
                                }elseif($check['status']==2){
                                    $data['return'] = '同意退款';
                                }else{
                                    $data['return'] = '驳回';
                                }
                            }else{
                                $data['return'] = '尚无售后信息';
                            }
                            $products[] = $data;
                        }else{
                            foreach($detail as $k=>$v){
                                $num = $v['num'];//购买数量
                                $specifications = $v['specifications'];//规格
                                $data['pid'] = $value;
                                $data['pname'] = $v['pname'];
                                $data['price'] = $v['price'];
                                $data['num'] = $num;
                                $data['travel_info'] = $travel_info;
                                $data['specifications'] = $specifications;
                                $where_check['ordernum'] = array('eq',$ordernum);
                                $where_check['pid'] = array('eq',$value);
                                $check = M('mall_order_return','dsy_')->where($where_check)->find();
                                if(!empty($check)){
                                    if($check['status']==1){
                                        $data['return'] = '申请售后中';
                                    }elseif($check['status']==2){
                                        $data['return'] = '同意退款';
                                    }else{
                                        $data['return'] = '驳回';
                                    }
                                }else{
                                    $data['return'] = '尚无售后信息';
                                }
                                $products[] = $data;
                            }
                        }
                    }
                }elseif($vv['order_type'] == 2 ||$vv['order_type'] ==3){
                    $ordernum = $vv['ordernum'];
                    $name = $vv['name'];
                    $mobile = $vv['mobile'];
                    $address = $vv['address'];
                    $pids = explode(',',$vv['pid']);
                    foreach($pids as $key=>$value){
                        $skuid = getSkuid($value);
                        $travel_info = product_travel($token,$wz_orderid,$skuid);//物流信息
                        $pinfo = getpinfobyid($value);
                        $pname = $pinfo['pname'];//商品名称
                        $detail = getDetalinfo($ordernum,$value);
                        if(count($detail)==count($detail,1)){
                            $num = $detail['num'];//购买数量
                            $specifications = $detail['specifications'];//规格
                            $data['pid'] = $value;
                            $data['pname'] = $pname;
                            $data['price'] = $detail['price'];
                            $data['num'] = $num;
                            $data['travel_info'] = $travel_info;
                            $data['specifications'] = $specifications;
                            $where_check['ordernum'] = array('eq',$ordernum);
                            $where_check['pid'] = array('eq',$value);
                            $check = M('mall_order_return','dsy_')->where($where_check)->find();
                            if(!empty($check)){
                                if($check['status']==1){
                                    $data['return'] = '申请售后中';
                                }elseif($check['status']==2){
                                    $data['return'] = '同意退款';
                                }else{
                                    $data['return'] = '驳回';
                                }
                            }else{
                                $data['return'] = '尚无售后信息';
                            }
                            $products[] = $data;
                        }else{
                            foreach($detail as $k=>$v){
                                $num = $v['num'];//购买数量
                                $specifications = $v['specifications'];//规格
                                $data['pid'] = $value;
                                $data['pname'] = $pname;
                                $data['price'] = $v['price'];
                                $data['num'] = $num;
                                $data['travel_info'] = $travel_info;
                                $data['specifications'] = $specifications;
                                $where_check['ordernum'] = array('eq',$ordernum);
                                $where_check['pid'] = array('eq',$value);
                                $check = M('mall_order_return','dsy_')->where($where_check)->find();
                                if(!empty($check)){
                                    if($check['status']==1){
                                        $data['return'] = '申请售后中';
                                    }elseif($check['status']==2){
                                        $data['return'] = '同意退款';
                                    }else{
                                        $data['return'] = '驳回';
                                    }
                                }else{
                                    $data['return'] = '尚无售后信息';
                                }
                                $products[] = $data;
                            }
                        }
                    }
                }
            }
        }else{
            $notpay = $exinfo['ordernum'];
            $where_not['order_num'] = array('eq',$notpay);
            $result = M('sn_order')->where($where_not)->find();
            $name = $result['address_name'];
            $mobile = $result['phone'];
            $address = $result['address'];
            $shop_wl  = shop_logistics_get($result['order_num']);
            $products_data = M('sn_order_item')->field('sku_id,product_name,spec,product_num,product_price')->where(['order_id'=>$result['id']])->select();
            foreach($products_data as $key=>$val){
                $data['pid'] = $val['skuid'];
                $data['pname'] = $val['product_name'];
                $data['price'] = $val['product_price'] * 1;
                $data['num'] = $val['product_num'];
                if(!empty($shop_wl)){
                    foreach($shop_wl as $ke=>$vl){
                        if(in_array($val['skuid'],$vl['skuId'])){
                            $data['travel_info'] = $vl['logisticsInfoList'];
                        }
                    }
                }else{
                    $data['travel_info'] = [];
                }
                $data['specifications'] = $val['spec'];
                $data['return'] = '尚无售后信息';
                $products[] = $data;
            }
        }

//        $ordernum = $mall_info['ordernum'];
//        $info = M('mall_order','dsy_')->find($mall_info['id']);
//        $name = $info['name'];
//        $mobile = $info['mobile'];
//        $address = $info['address'];
//        $pids = explode(',',$info['pid']);
//        foreach($pids as $key=>$value){
//            $skuid = getSkuid($value);
//            $travel_info = product_travel($token,$wz_orderid,$skuid);//物流信息
//            $pinfo = getpinfobyid($value);
//            $pname = $pinfo['pname'];//商品名称
//            $price = $pinfo['price'];//商品价格
//            $detail = getDetalinfo($ordernum,$value);
//            $num = $detail['num'];//购买数量
//            $specifications = $detail['specifications'];//规格
//            $data['pid'] = $value;
//            $data['pname'] = $pname;
//            $data['price'] = $price;
//            $data['num'] = $num;
//            $data['travel_info'] = $travel_info;
//            $data['specifications'] = $specifications;
//            $where_check['ordernum'] = array('eq',$ordernum);
//            $where_check['pid'] = array('eq',$value);
//            $check = M('mall_order_return','dsy_')->where($where_check)->find();
//            if(!empty($check)){
//                if($check['status']==1){
//                    $data['return'] = '申请售后中';
//                }elseif($check['status']==2){
//                    $data['return'] = '同意退款';
//                }else{
//                    $data['return'] = '驳回';
//                }
//            }else{
//                $data['return'] = '尚无售后信息';
//            }
//            $products[] = $data;
//
//        }

        $this->assign('pinfo',$products);
        $this->assign('name',$name);
        $this->assign('mobile',$mobile);
        $this->assign('address',$address);
        $this->display('order_detail');
    }


    /**
     * 导出兑换记录
     **/
    public function output(){
        $id = I('id','');
        $model = M('company_exchange_record','dsy_');
        $where['aid'] = array('eq',$id);
        $info = $model
            ->where($where)
            ->order('status desc')
            ->select();
        //处理里面的所有商品信息
        $all_data = array();
        if(!empty($info)){
            foreach($info as $key=>$value){
                if($value['status']==1){
                    //已经兑换
                    $uinfo = M('user','t_')->find($value['uid']);
                    $user_name = $uinfo['user_name'];
                    $not_pay_num = $value['ordernum'];
                    if(!empty($value['setmeal'])){
                        $pinfo = unserialize($value['setmeal']);
                        $data['pid'] = $pinfo['name'];
                        $pids = $pinfo['pids'];
                    }else{
                        $pids = $value['goods_ids'];
                    }

                    $where_not['ordernum'] = array('eq',$not_pay_num);
                    $not_info = M('mall_order_notpay')
                        ->where($where_not)
                        ->find();
                    $data['id'] = $value['id'];
                    $data['code'] = $value['num'];
                    $data['user_name'] = $user_name;
                    $data['status'] = '已兑换';

                    $data['address'] = $not_info['address'];
                    $data['name'] = $not_info['name'];
                    $data['mobile'] = (string)$not_info['mobile'];


                    $pids = explode(',',$pids);
                    $pname_array = array();
                    foreach($pids as $kk=>$val){
                        $pinfo = getpinfobyid($val);
                        $pname = $pinfo['pname'];//商品名称
                        $pname_array[] = $pname;
                    }

                    $data['pname'] = implode(',',$pname_array);
                    $all_data[] = $data;
                }else{
                    $data['id'] = $value['id'];
                    $data['code'] = $value['num'];
                    $data['user_name'] = '';
                    $data['status'] = '未兑换';
                    $data['pid'] = '';
                    $data['address'] = '';
                    $data['name'] = '';
                    $data['mobile'] = '';
                    $data['pname'] = '';
                    $all_data[] = $data;
                }
            }
        }

        $xlsCell = array(
            array('id', '序号'),
            array('code', '兑换券号'),
            array('user_name', '领取账号'),
            array('status', '兑换状态'),
            array('pid', '兑换套餐'),
            array('address', '收货地址'),
            array('name', '收货人'),
            array('mobile', '联系号码'),
            array('pname', '商品'),
//            array('trians', '物流状态'),
        );

        $xlsName = '兑换结果导出';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$all_data);
    }




    /**
     * 修改套餐商品
    **/
    public function edit_products(){
        $id = I('id','');
        $this->assign('id',$id);
        $this->display();
    }


    /**
     * 列表数据
     **/
    public function edit_list(){
        $id = I('id');//活动id
        if(empty($id)){
            return Response::show(300,'缺少参数');
        }
        $where['tid'] = array('eq',$id);
        $where['del'] = array('eq',1);
        $info = M('company_exchange_record_products','dsy_')->where($where)->select();
        return Response::mjson($info,0);
    }


    /**
     * 修改操作
     **/
    public function edit_do(){
        $info = $_POST['info'];
        $array = json_decode($info,true);
        $error = 0;
        foreach($array as $key=>$value){
            if(is_numeric($value['save_price'])==false){
                $error=1;
            }
            if($value['save_price']<0){
                $error=1;
            }
            $where['id'] = array('eq',$value['id']);
            $data['save_price'] = $value['save_price'];
            $data['time'] = NOW;
            $save = M('company_exchange_record_products','dsy_')->where($where)->save($data);
            if($save === false){
                $error =1;
            }

        }
        $ids_str = implode(',', array_column($array, 'id'));
        $tid = M('company_exchange_record_products', 'dsy_')->where(['id' => $array[0]['id']])->getField('tid');
        $tname = M('company_exchange_package', 'dsy_')->where(['id' => $tid])->getField('name');
        //添加操作日志
        $admin_log = '编辑兑换券套餐【' . $tname . '】商品价格';
        if($error==1){
            admin_log($admin_log, 0, 'dsy_company_exchange_record_products:' . $ids_str);
            return Response::show(400,'修改价格失败');
        }else{
            admin_log($admin_log, 1, 'dsy_company_exchange_record_products:' . $ids_str);
            return Response::show(200,'修改价格成功');
        }

    }

    /**
     * 兑换券重置功能
     **/
    public function code_reset_index(){

        $this->display();
    }


    /**
     * 重置列表页数据
     **/
    public function reset_info(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:1;
        $limit = !empty($limit)?$limit:10;
        $code = I('code','');
        $where['status'] = array('eq',1);
        if(!empty($code)){
            $where['num'] = array('eq',$code);
        }
        $model = M('company_exchange_record','dsy_');
        $info = $model
            ->where($where)
            ->page($page,$limit)
            ->select();
        if(!empty($info)){
            foreach($info as $key=>$value){
                $ordernum = $value['ordernum'];
                $where_c['order_notpay_num'] = array('eq',$ordernum);
                $cordernum = M('mall_order','dsy_')->where($where_c)->getField('ordernum',true);
                $info[$key]['cordernum'] = implode('|',$cordernum);
            }
        }
        $num = $model
            ->where($where)
            ->count();
        return Response::mjson($info,$num);
    }


    /**
     * 重置操作
    **/
    public function reset_do(){
        $ids = I('ids','');
        $id = $ids[0];
        $model = M('company_exchange_record','dsy_');
        $info = $model->find($id);
        $code = $info['num'];
        $ordernum = $info['ordernum'];
        $data['setmeal'] = '';
        $data['ordernum'] = '';
        $data['money'] = '';
        $data['type'] = '';
        $data['status'] = 0;
        $data['goods_ids'] = '';
        $data['uid'] = '';
        $where['id'] = array('eq',$id);
        $save = $model->where($where)->save($data);
        $add_data['code'] = $code;
        $add_data['ordernum'] = $ordernum;
        $add_data['time'] = time();
        $add = M('company_exchange_reset','dsy_')->add($add_data);
        if($save != false && $add != false){
            $model->commit();
            return Response::show(200,'重置成功');
        }else{
            $model->rollback();
            return Response::show(400,'重置失败');
        }



    }


    /**
     * 修改活动时间页面
    **/
    public function save_time(){
        $id = I('id');
        $this->assign('id',$id);
        $this->display();
    }


    /**
     * 修改时间操作
    **/
    public function save_do(){
        $id = I('id','');
        $end = I('end','');
        if(empty($id)||empty($end)){
            return Response::show(400,'参数为空');
        }
        $check = M('company_exchange_activity','dsy_')->find($id);
        $start_time = $check['start_time'];
        if($end< $start_time){
            return Response::show(400,'结束时间不得小于开始时间');
        }
        $data['end_time'] = $end;
        $where['id'] = array('eq',$id);
        //添加操作日志
        $admin_log = '编辑兑换券活动【' . $check['name'] . '】结束时间';
        $save = M('company_exchange_activity','dsy_')->where($where)->save($data);
        if($save != false){
            admin_log($admin_log, 1, 'dsy_company_exchange_activity:' . $id);
            return Response::show(200,'修改成功');
        }else{
            admin_log($admin_log, 0, 'dsy_company_exchange_activity:' . $id);
            return Response::show(400,'修改失败');
        }
    }





    /**
     * 导出水果订单数据
     */
    public function output_order(){
        $where['a.status'] = array('eq',2);
        $where['a.order_type'] = array('eq',5);
        $starttime = '2018-06-12 00:00:00';
        $endtime = date('Y-m-d').' 17:20:00';
        $where['a.time'] = array('between',array($starttime,$endtime));
        $where['c.status'] = array('eq',1);
        $where['c.aid'] = array('eq',23);
        $info = M('mall_order','dsy_')
            ->join('as a left join dsy_mall_order_notpay as b on a.order_notpay_num = b.ordernum')
            ->join('left join dsy_company_exchange_record as c on b.ordernum =c.ordernum ')
            ->where($where)
            ->field('a.id,a.uid,a.name,a.mobile,a.address,a.pid,a.ordernum,c.num')
            ->select();

        if(!empty($info)){
            $id = 0;
            foreach($info as $key=>$value){
                $id+=1;
                $all_pinfo = array();
                $where1['pid'] = array('in',$value['pid']);
                $where1['ordernum'] = array('eq',$value['ordernum']);
                $pinfo = M('mall_order_specifications','dsy_')->where($where1)->select();
                foreach($pinfo as $kk=>$vv){
                    $proinfo = getpinfobyid($vv['pid']);
                    $pname = $proinfo['pname'];
                    $num = $vv['num'];
                    $connect = $pname.'【数量'.$num.'】';
                    $all_pinfo[] = $connect;
                }
                $products = implode("\r\n",$all_pinfo);
                $info[$key]['products'] = $products;
                $info[$key]['id'] = $id;
            }
        }

        $xlsCell = array(
            array('id', '序号'),
            array('num', '兑换码'),
            array('name', '收货人'),
            array('address', '收货地址'),
            array('mobile', '联系电话'),
            array('products', '发货商品'),
        );
        $xlsName = '发货清单';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$info);

    }



    /**
     *下单信息导出
     */
    public function test(){

        $starttime = '2018-05-01 00:00:00';
        $enttime = '2018-07-15 23:59:59';
        $where['wz_orderid'] = array('exp','is not null');
        $where['time'] = array('between',array($starttime,$enttime));
        $where['wz_status'] = array('eq',2);
        $info = M('mall_order_notpay','dsy_')->where($where)->getField('ordernum',true);
        $ids = implode(',',$info);
        $where1['order_type'] = array('in','2,3');
        $where1['order_notpay_num'] = array('in',$ids);
        $list = M('mall_order','dsy_')->where($where1)->select();
        $array = array();
        foreach($list as $key=>$value){
            $where2['ordernum'] = array('eq',$value['order_notpay_num']);
            $wzid = M('mall_order_notpay','dsy_')->where($where2)->getField('wz_orderid');
            $pro_price = $value['price'] - $value['freight'];
            $freight = $value['freight'];
            $price =$value['price'];
            $data['wzid'] = $wzid;
            $data['ordernum'] = $value['ordernum'];
            $data['pro_price'] = $pro_price;
            $data['freight'] = $freight;
            $data['price'] = $price;
            $array[] = $data;
        }

        $xlsCell = array(
            array('wzid', '微知单号'),
            array('ordernum', '平台单号'),
            array('pro_price', '商品售价'),
            array('freight', '订单运费'),
            array('price', '订单总额(带运费)'),
        );
        $xlsName = '下单信息';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$array);

    }



    /**
     *售后信息导出
     */
    public function test1(){
        $starttime = '2018-05-01 00:00:00';
        $enttime = '2018-07-15 23:59:59';
        $where['wz_orderid'] = array('exp','is not null');
        $where['time'] = array('between',array($starttime,$enttime));
        $where['status'] = array('eq',2);
        $info = M('mall_order_return','dsy_')->where($where)->select();
        $array = array();
        foreach($info as $key=>$value){
            $where1['ordernum'] = array('eq',$value['ordernum']);
            $order = M('mall_order','dsy_')->where($where1)->find();
            $pinfo = getpinfobyid($value['pid']);
            $data['wzid'] = $value['wz_orderid'];
            $data['pname'] = $pinfo['pname'];
            $data['num'] = $value['num'];
            $data['time'] = $order['time'];
            $data['wz_price'] = $pinfo['wz_price'];
            $data['freight'] = $order['freight'];
            $data['price'] = $order['price'];
            $array[] = $data;
        }


        $xlsCell = array(
            array('wzid', '微知单号'),
            array('pname', '商品名称'),
            array('num', '商品数量'),
            array('time', '下单时间'),
            array('wz_price', '微知销售价'),
            array('freight', '运费'),
            array('price', '订单金额'),
        );
        $xlsName = '下单信息';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$array);

    }


    /**
     *人社厅活动订单
     */
    public function test3(){
        $where['aid'] = array('eq',23);
        $where['status'] = array('eq',1);
        $info = M('company_exchange_record','dsy_')->where($where)->getField('ordernum',true);
        $array = array();
        foreach($info as $key=>$value){
            $where1['ordernum'] = array('eq',$value);
            $oinfo = M('mall_order_notpay','dsy_')->where($where1)->find();
            $data['wzid'] = $oinfo['wz_orderid'];
            $data['ordernum'] = $value;
            $data['pro_price'] = $oinfo['total_amount']-$oinfo['freight'];
            $data['freight'] = $oinfo['freight'];
            $data['price'] = $oinfo['total_amount'];
            $array[] = $data;
        }
        $xlsCell = array(
            array('wzid', '微知单号'),
            array('ordernum', '平台总单号'),
            array('pro_price', '商品售价'),
            array('freight', '订单运费'),
            array('price', '订单总额(带运费)'),
        );
        $xlsName = '人社厅下单信息';
        $field = null;
        foreach ($xlsCell as $key => $value) {
            if($key == 0){
                $field = $value[0];
            }else{
                $field .= "," . $value[0];
            }
        }
        $one = exportExcel($xlsName,$xlsCell,$array);

    }



}

