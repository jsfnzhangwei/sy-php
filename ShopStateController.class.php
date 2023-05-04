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

class ShopStateController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }
    /**
     * 商品列表
     */
    public function shop_list_index(){
        $this->assign('url',JAVA_API_URL_SN);
        $this->assign('first',shopClass(1));
        $this->display();
    }
    public function shop_list_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        $sku = I('sku','');
        $status = I('status','');
        $my_status = I('my_status','');
        $third = I('third','');
        $second = I('second','');
        $first = I('first','');
        $min = I('min','');
        $max = I('max','');
        $sort = I('sort','');
        $where = [];
        if(!empty($name)){
            $where['a.name'] = array('like',"%$name%");
        }
        if(!empty($sku)){
            $where['a.sku_id'] = array('eq',$sku);
        }
        if($status != ''){
            $where['b.sn_status'] = array('eq',$status);
        }
        if($my_status != ''){
            $where['a.my_status'] = array('eq',$my_status);
        }
        if(!empty($third)){
            $where['c.category_id'] = array('eq',$third);
        }elseif(!empty($second)){
            $where['d.category_id'] = array('eq',$second);
        }elseif(!empty($first)){
            $where['e.category_id'] = array('eq',$first);
        }
        if(!empty($min)){
            if(!empty($max)){
                if($min < $max){
                    $where['a.price'] = array('between',[$min,$max]);
                }else{
                    $where['a.price'] = array('egt',$min);
                }
            }else{
                $where['a.price'] = array('egt',$min);
            }
        }else{
            if(!empty($max)){
                $where['a.price'] = array('elt',$max);
            }
        }
        if($sort != ''){
            if($sort == 0){
                $order = 'a.sort asc,a.create_time desc';
            }elseif($sort == 1){
                $order = 'a.sort desc';
            }
        }else{
            $order = 'a.create_time desc';
        }
        $result = M('sn_product')
                ->alias('a')
                ->field('a.product_id,c.category_name,a.sku_id,a.name,b.sn_status,a.sort,a.price,a.company_price,a.sn_price,
                a.naked_price,a.my_status,d.category_name as second,e.category_name as first,a.sale')
                ->join('LEFT JOIN dsy_sn_product_recommend as b on a.sku_id=b.sku_id')
                ->join('LEFT JOIN dsy_sn_category as c on a.category_code=c.category_code')
                ->join('LEFT JOIN dsy_sn_category as d on c.pid=d.category_id')
                ->join('LEFT JOIN dsy_sn_category as e on d.pid=e.category_id')
                ->order($order)
                ->page($page,$limit)
                ->where($where)
                ->select();
        $num = M('sn_product')
                ->alias('a')
                ->join('LEFT JOIN dsy_sn_product_recommend as b on a.sku_id=b.sku_id')
                ->join('LEFT JOIN dsy_sn_category as c on a.category_code=c.category_code')
                ->join('LEFT JOIN dsy_sn_category as d on c.pid=d.category_id')
                ->join('LEFT JOIN dsy_sn_category as e on d.pid=e.category_id')
                ->where($where)
                ->count();
        return Response::mjson($result,$num);
    }
    /**
     * 商品编辑
     */
    public function shop_one_edit(){
        $id = I('id');
        $type = I('type');
        $result = M('sn_product')
                    ->alias('a')
                    ->field('a.product_id,a.sku_id,a.category_code,a.name,a.price,a.company_price,a.sn_price,a.naked_price,a.sort,c.category_id as third,d.category_id as second,e.category_id as first')
                    ->join('LEFT JOIN dsy_sn_category as c on a.category_code=c.category_code')
                    ->join('LEFT JOIN dsy_sn_category as d on c.pid=d.category_id')
                    ->join('LEFT JOIN dsy_sn_category as e on d.pid=e.category_id')
                    ->where('product_id=' . $id)
                    ->find();
        if($type == 1){
            $url = 'sp_list_1';
        }else{
            $url = 'sp_company_2';
        }
        $this->assign('url',$url);
        $this->assign('first',shopClass(1));
        $this->assign('second',shopClass(2,$result['first']));
        $this->assign('third',shopClass(3,$result['second']));
        $this->assign('result',$result);
        $this->display();
    }
    /**
     * 商品编辑保存
     */
    public function shop_one_save(){
        $id = I('id');
        $sku_id = I('sku_id');
        if(empty($id)){
            return Response::show(300,'数据异常,请重新提交');
        }
        $data = [
            'category_code' => I('third',''),
            'name' => I('name',''),
            'price' => I('price',''),
            'company_price' => I('company_price',''),
            'sort' => I('sort',''),
        ];
        $naked_price = I('naked_price');
        if(!empty($data['category_code'])){
            $third = M('sn_category')->field('category_code')->where('category_id=' . $data['category_code'])->find();
            $data['category_code'] = $third['category_code'];
        }else{
            return Response::show(300,'商品分类不能为空');
        }
        if(empty($data['name'])){
            return Response::show(300,'商品名称不能为空');
        }
        if(empty($data['price'])){
            return Response::show(300,'正常售价不能为空');
        }
        if(empty($data['company_price'])){
            return Response::show(300,'企业会员价不能为空');
        }
        if($data['company_price'] < $naked_price){
            return Response::show(300,'企业会员价不能小于成本价');
        }
        if(!is_numeric($data['price'])){
            return Response::show(300,'正常售价只能填写数字');
        }
        if(!is_numeric($data['company_price'])){
            return Response::show(300,'企业会员价只能填写数字');
        }
        if(empty($data['sort'])){
            return Response::show(300,'排序值不能为空');
        }
        $result = M('sn_product')->where('product_id='.$id)->save($data);//更新商品排序
        $result_sort = M('sn_product_recommend')->where('sku_id='.$sku_id)->setField('sort',$data['sort']);//更新商品推荐排序
        if($result !== false){
            return Response::show(200,'编辑成功');
        }else{
            return Response::show(300,'编辑失败');
        }
    }
    /**
     * 商品下架
     */
    public function shop_one_stop(){
        $ids = I('ids','');
        if(empty($ids)){
            return Response::show(300,'请选择要下架的商品');
        }
        $where['sku_id'] = array('eq',$ids);
        $infos = M('sn_product')->field('sku_id,name')->where($where)->select();
        foreach($infos as $v){
            $infos_str[] = '【' . $v['sku_id'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            $str_sku = $v['sku_id'] . ',';
        }
        $admin_log = '下架商品:' . implode(',', $infos_str);
        $data = [
            'my_status' => 0
        ];
        $result = M('sn_product')->where($where)->save($data);
        if($result !== false){
            admin_log($admin_log, 1, 'dsy_sn_product:' . $id_str);
            return Response::show(200,'下架成功');
        }else{
            admin_log($admin_log, 0, 'dsy_sn_product:' . $id_str);
            return Response::show(300,'下架失败');
        }
    }
    /**
     * 商品上架
     */
    public function shop_one_start(){
        $ids = I('ids','');
        if(empty($ids)){
            return Response::show(300,'请选择要上架的商品');
        }
        $data = [
            'my_status' => 1
        ];
        $where['sku_id'] = array('eq',$ids);
        $infos = M('sn_product')->field('sku_id,name')->where($where)->select();
        foreach($infos as $v){
            $infos_str[] = '【' . $v['sku_id'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            $str_sku = $v['sku_id'] . ',';
        }
        $admin_log = '上架商品:' . implode(',', $infos_str);
        $result = M('sn_product')->where($where)->save($data);
        if($result !== false){
            admin_log($admin_log, 1, 'dsy_sn_product:' . $ids);
            return Response::show(200,'上架成功');
        }else{
            admin_log($admin_log, 0, 'dsy_sn_product:' . $ids);
            return Response::show(300,'上架失败');
        }
    }
    /**
     * 查看规格
     */
    public function shop_spec_look(){
        $id = I('id','');
        $info = M('sn_product_extend')
                    ->alias('a')
                    ->field('a.value_list,b.name,b.price,b.sn_price,b.company_price,b.img,b.sku_id,c.category_name,a.images_paths')
                    ->join('LEFT JOIN dsy_sn_product as b on a.sku_id=b.sku_id')
                    ->join('LEFT JOIN dsy_sn_category as c on b.category_code=c.category_code')
                    ->where('a.sku_id=' . $id)
                    ->find();
        $images_paths = explode(',',$info['images_paths']);
        $value_list = json_decode($info['value_list']);
        $arr = [];
        $num = 0;
        foreach($value_list as $key=>$val){
            $arr[$num]['title'] = $key;
            foreach($val as $ky=>$vl){
                $arr[$num]['data'][] = [
                    'name' => $ky,
                    'content' => $vl
                ];
            }
            $num++;
        }
        $this->assign('info',$info);
        $this->assign('value_list',$arr);
        $this->assign('images_paths',$images_paths);
        $this->display('shop_spec_look');
    }
    /**
     * 查看商品库存
     */
    public function shop_kucun(){
        $id = I('id');
        $arr = ['address_id'=>0,'name'=>'省'];
        $first = M('sn_address')
                    ->field('address_id,name')
                    ->where('level=1')
                    ->order('address_id asc')
                    ->select();
        array_unshift($first,$arr);
        $this->assign('url',JAVA_API_URL_SN);
        $this->assign('first',$first);
        $this->assign('id',$id);
        $this->display();
    }
    //查找二级城市
    public function shop_two(){
        $id = I('id');
        $second = M('sn_address')
                    ->field('address_id,name')
                    ->where('level=2 and pid=' . $id)
                    ->order('address_id asc')
                    ->select();
        $str = "<option value='0'>市</option>";
        foreach($second as $val){
            $str .= "<option value='".$val['address_id']."'>".$val['name']."</option>";
        }
        $this->ajaxReturn($str);
    }
    /**
     * 查找3级城市
     */
    public function shop_third(){
        $id = I('id');
        $second = M('sn_address')
                    ->field('address_id,name')
                    ->where('level=3 and pid=' . $id)
                    ->order('address_id asc')
                    ->select();
        if(!empty($second)){
            $str = "<option value='0'>区</option>";
            foreach($second as $val){
                $str .= "<option value='".$val['address_id']."'>".$val['name']."</option>";
            }
        }else{
            $str = '';
        }
        $this->ajaxReturn($str);
    }
    /**
     * 设为推荐
     * @param type 1为商品列表2为企业集采
     */
    public function shop_open_recommend(){
        $id = I('ids','');
        $type = I('type','');
        if(!empty($id)){
            $sku_id = $this->shop_deal_data($id);
            $where['sku_id'] = array('in',$sku_id);
            $result_product = M('sn_product_recommend')->field('product_id')->where($where)->select();
            $ids = [];
            foreach($result_product as $vl){
                $ids[] = $vl['product_id'];
            }
            $str = '';
            $add = [];
            foreach($id as $val){
                if(in_array($val[0],$ids)){
                    $str .= $val[0] . ',';
                }else{
                    if($type == 1){
                        $add[] = [
                            'sku_id'=>$val[1],
                            'product_id'=>$val[0],
                            'sn_status'=>1,
                            'create_time'=>date('Y-m-d H:i:s',time()),
                        ];
                    }else{
                        $add[] = [
                            'sku_id'=>$val[1],
                            'product_id'=>$val[0],
                            'company_status'=>1,
                            'create_time'=>date('Y-m-d H:i:s',time()),
                        ];
                    }
                }
            }
            if($type == 1){
                $save = [
                    'sn_status'=>1,
                    'update_time'=>date('Y-m-d H:i:s',time())
                ];
            }else{
                $save = [
                    'company_status'=>1,
                    'update_time'=>date('Y-m-d H:i:s',time())
                ];
            }
            $where_str['product_id'] = array('in',rtrim($str,','));
            $num = 0;
            if(!empty($str)){
                $result_save = M('sn_product_recommend')->where($where_str)->save($save);
                if($result_save)$num = 1;
            }
            if(!empty($add)){
                $result_add = M('sn_product_recommend')->addAll($add);
                if($result_add)$num = 1;
            }
            $infos = M('sn_product')->field('sku_id,name')->where($where)->select();
            foreach($infos as $v){
                $infos_str[] = '【' . $v['sku_id'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            }
            if($type == 1){
                $admin_log = "推荐苏宁商品:" . implode(',',$infos_str);
            }else{
                $admin_log = "推荐企业商品:" . implode(',',$infos_str);
            }
            if($num == 1){
                admin_log($admin_log, 1, 'sn_product_recommend:' . $sku_id);
                return Response::show(200,'设置成功');
            }else{
                admin_log($admin_log, 0, 'sn_product_recommend:' . $sku_id);
                return Response::show(300,'设置失败');
            }
        }else{
            return Response::show(300,'请选择要设为推荐的商品');
        }
    }
    /**
     * 取消推荐
     */
    public function shop_stop_recommend(){
        $id = I('ids','');
        $type = I('type','');
        if(!empty($id)){
            $sku_id = implode(',',$id);
            $where['sku_id'] = array('in',$sku_id);
            if($type == 1){
                $data = [
                    'sn_status'=>0,
                    'update_time'=>date('Y-m-d H:i:s',time())
                ];
            }else{
                $data = [
                    'company_status'=>0,
                    'update_time'=>date('Y-m-d H:i:s',time())
                ];
            }
            $infos = M('sn_product')->field('sku_id,name')->where($where)->select();
            foreach($infos as $v){
                $infos_str[] = '【' . $v['sku_id'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            }
            if($type == 1){
                $admin_log = "取消推荐苏宁商品:" . implode(',',$infos_str);
            }else{
                $admin_log = "取消推荐企业商品:" . implode(',',$infos_str);
            }
            $result = M('sn_product_recommend')->where($where)->save($data);
            if($result != false){
                admin_log($admin_log, 1, 'sn_product_recommend:' . $sku_id);
                return Response::show(200,'取消成功');
            }else{
                admin_log($admin_log, 0, 'sn_product_recommend:' . $sku_id);
                return Response::show(300,'取消失败');
            }
        }else{
            return Response::show(300,'请选择要取消推荐的商品');
        }
    }
    /**
     * 商品导出
     */
    public function shop_export_data(){
        $id = I('id',0);
        $type = I('type');
        $where['a.product_id'] = array('in',$id);
        $data = [
            ['商品ID','商品分类','sku编号','商品信息','是否推荐','排序','商品价格','企业会员价','苏宁销售价','成本价','商品状态'],
        ];
        if($type == 1){
            $field = 'a.product_id,c.category_name,a.sku_id,a.name,b.sn_status as status,a.sort,a.price,a.company_price,a.sn_price,a.naked_price,a.my_status,d.category_name as second,e.category_name as first';
        }else{
            $field = 'a.product_id,c.category_name,a.sku_id,a.name,b.company_status as status,a.sort,a.price,a.company_price,a.sn_price,a.naked_price,a.my_status,d.category_name as second,e.category_name as first';
        }
        $result = M('sn_product')
                ->alias('a')
                ->field($field)
                ->join('LEFT JOIN dsy_sn_product_recommend as b on a.sku_id=b.sku_id')
                ->join('LEFT JOIN dsy_sn_category as c on a.category_code=c.category_code')
                ->join('LEFT JOIN dsy_sn_category as d on c.pid=d.category_id')
                ->join('LEFT JOIN dsy_sn_category as e on d.pid=e.category_id')
                ->where($where)
                ->select();
        $num = 1;
        foreach($result as $key=>$val){
            if(!empty($val['status'])){
                $status = ($val['status'] == 1) ? '是':'否';
            }else{
                $status = '否';
            }
            $arr = [
                'product_id' => $val['product_id'],
                'category_name' => $val['first'] . '>' . $val['second'] . '>' . $val['category_name'],
                'sku_id' => '',
                'name' => $val['name'],
                'status' => $status,
                'sort' => $val['sort'],
                'price' => $val['price'],
                'company_price' => $val['company_price'],
                'sn_price' => $val['sn_price'],
                'naked_price' => $val['naked_price'],
                'my_status' => $val['my_status'] == 0 ? '已下架' : '已上架',
            ];
            $arr['sku_id'] = "\t" . $val['sku_id'] . "\t";
            $data[$num++] = array_values($arr);
        }
        $this->get_excel($data,'商品列表');
    }
    /**
     * 企业商品
     */
    public function shop_company_list(){
        $this->assign('url',JAVA_API_URL_SN);
        $this->assign('first',shopClass(1));
        $this->display();
    }
    /**
     * 企业商品列表数据
     */
    public function shop_company_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        $sku = I('sku','');
        $status = I('status','');
        $my_status = I('my_status','');
        $third = I('third','');
        $second = I('second','');
        $first = I('first','');
        $min = I('min','');
        $max = I('max','');
        $sort = I('sort','');
        $str = '';
        if(!empty($name)){
            $where['a.name'] = array('like',"%$name%");
        }
        if(!empty($sku)){
            $where['a.sku_id'] = array('eq',$sku);
        }
        if($status != ''){
            $where['b.company_status'] = array('eq',$status);
        }
        if($my_status != ''){
            $where['a.my_status'] = array('eq',$my_status);
        }
        if(!empty($third)){
            $where['c.category_id'] = array('eq',$third);
        }elseif(!empty($second)){
            $where['d.category_id'] = array('eq',$second);
        }elseif(!empty($first)){
            $where['e.category_id'] = array('eq',$first);
        }
        if(!empty($min)){
            if(!empty($max)){
                if($min < $max){
                    $where['a.price'] = array('between',[$min,$max]);
                }else{
                    $where['a.price'] = array('egt',$min);
                }
            }else{
                $where['a.price'] = array('egt',$min);
            }
        }else{
            if(!empty($max)){
                $where['a.price'] = array('elt',$max);
            }
        }
        if($sort != ''){
            if($sort == 0){
                $order = 'a.sort asc,a.create_time desc';
            }elseif($sort == 1){
                $order = 'a.sort desc';
            }
        }else{
            $order = 'a.product_id desc';
        }
        $result = M('sn_product')
                ->alias('a')
                ->field('a.product_id,c.category_name,a.sku_id,a.name,b.company_status,a.sort,a.price,a.company_price,a.sale,
                a.sn_price,a.naked_price,a.my_status,d.category_name as second,e.category_name as first')
                ->join('LEFT JOIN dsy_sn_product_recommend as b on a.sku_id=b.sku_id')
                ->join('LEFT JOIN dsy_sn_category as c on a.category_code=c.category_code')
                ->join('LEFT JOIN dsy_sn_category as d on c.pid=d.category_id')
                ->join('LEFT JOIN dsy_sn_category as e on d.pid=e.category_id')
                ->join('LEFT JOIN dsy_sn_menu as f on c.category_id=f.data_id or d.category_id=f.data_id or e.category_id=f.data_id')
                ->group('a.sku_id')
                ->order($order)
                ->page($page,$limit)
                ->where($where)
                ->where('f.data_type in(3,4,5) and f.location = 2')
                ->select();
        $num = M('sn_product')
                ->alias('a')
                ->field('a.product_id')
                ->join('LEFT JOIN dsy_sn_product_recommend as b on a.sku_id=b.sku_id')
                ->join('LEFT JOIN dsy_sn_category as c on a.category_code=c.category_code')
                ->join('LEFT JOIN dsy_sn_category as d on c.pid=d.category_id')
                ->join('LEFT JOIN dsy_sn_category as e on d.pid=e.category_id')
                ->join('LEFT JOIN dsy_sn_menu as f on c.category_id=f.data_id or d.category_id=f.data_id or e.category_id=f.data_id')
                ->group('a.sku_id')
                ->where($where)
                ->where('f.data_type in(3,4,5) and f.location = 2')
                ->select();
        $count = count($num);
        return Response::mjson($result,$count);
    }
    /**
     * 商品导入
     */
    public function shop_import_data(){
        import("Vendor.PHPExcel.PHPExcel");
        import("Vendor.PHPExcel.PHPExcel.Writer.Excel5");
        import("Vendor.PHPExcel.PHPExcel.IOFactory.php");
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;
        $cacheSettings = array( 'memoryCacheSize ' => '8MB');
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
        // 实例化exel对象
        //文件路径
        $files = $_FILES['excel'];
        $upload = new \Think\Upload();// 实例化上传类
	    $upload->maxSize   =     3145728 ;// 设置附件上传大小
	    $upload->exts      =     array('xlsx', 'xls');// 设置附件上传类型
	    $upload->rootPath  =     './'; // 设置附件上传根目录
        $upload->savePath  =     'Public/Uploads/temp/'; // 设置附件上传（子）目录
        $upload->subName   =     array('date','Ymd');
	    $info   =   $upload->uploadOne($files);
	    if(!$info) {
            // 上传错误提示错误信息
            return Response::show(300,$upload->getError()); 
		}else{
			// 上传成功 获取上传文件信息
            $infopath = $upload->rootPath.$info['savepath'].$info['savename'];
		}
        $file_path = $infopath;

        //文件的扩展名
        $ext = strtolower(pathinfo($file_path,PATHINFO_EXTENSION));
        if ($ext == 'xlsx'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objPHPExcel = $objReader->load($file_path);
        }elseif($ext == 'xls'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel5');
            $objPHPExcel = $objReader->load($file_path);
        }
        $objReader->setReadDataOnly(true);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();//获取总行数
        $highestColumn = $sheet->getHighestColumn();//获取总列数
        $record = array();//申明每条记录数组
        $num = 0;
        $error = [];//记录错误数据
        for ($i = 3;$i<=$highestRow;$i++){
            $col = 0;
            for ($j = 'A';$j<=$highestColumn;$j++){
                $record[$num][$col] = $objPHPExcel->getActiveSheet()->getCell("$j$i")->getValue();//读取单元格
                if($col == 6){
                    if(!is_numeric($record[$num][$col])){
                        $error[] = '第' . $i . '行,第' . $j . '列格式错误';
                    }
                    if($record[$num][$col] <= 0){
                        $error[] = '第' . $i . '行,第' . $j . '列商品价格不能为0';
                    }   
                }
                if($col == 7){
                    if(!is_numeric($record[$num][$col])){
                        $error[] = '第' . $i . '行,第' . $j . '列格式错误';
                    }
                }
                if($col == 10){
                    $switch = 0;
                    if($record[$num][$col] == "已上架"){
                        $switch = 1;
                    }
                    if($record[$num][$col] == "已下架"){
                        $switch = 1;
                    }
                    if($switch != 1){
                        $error[] = '第' . $i . '行,第' . $j . '列格式错误';
                    }
                }
                $col++;
            }
            header("Content-type:text/html;charset=utf-8");
            $num++;
        }
        if(empty($error)){
            foreach($record as $val){
                $data = [
                    'price' => $val[6],
                    'company_price' => $val[7],
                    'my_status' => $val[10] == '已下架' ? 0 : 1,
                ];
                $result = M('sn_product')->where('product_id=' . $val[0])->save($data);
            }
            return Response::show(200,'商品导入更新成功');
        }else{
            $str = '错误提示:';
            foreach($error as $val){
                $str.="<p>" . $val . "</p>";
            }
            $str .= '请重新填写后再次导入'; 
            return Response::show(300,$str);
        }
    }
    /**
     * 处理数据
     */
    public function shop_deal_data($data){
        $str = '';
        foreach($data as $val){
            $str .= $val[1] . ',';
        }
        $str = rtrim($str,',');
        return $str;
    }
    /**
     * 商品数据导出excel
     */
    public function get_excel($data,$title){
        import('Org.Util.PHPExcel');
        $excel = new \PHPExcel();
        //Excel表格式,这里简略写了8列
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        //第一个sheet
        $excel->setactivesheetindex(0);
        //设置sheet标题
        $excel->getActiveSheet()->setTitle($title);
        //填充表格信息
        $excel->getActiveSheet()->setCellValue("A1",$title."统计报表。注:商品价格,企业会员价格,商品状态(添已上架或已下架)可进行修改。");
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(60);
        $excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        for ($i = 2;$i <= count($data) + 1;$i++) {
        $j = 0;
        foreach ($data[$i-2] as $key=>$value) {
        
        if(!strpos($value,'Public/Uploads')){
        //文字生成
        $excel->getActiveSheet()->setCellValue("$letter[$j]$i","$value");
        }else{
        // 图片生成
        $objDrawing[$key] = new \PHPExcel_Worksheet_Drawing();
        $objDrawing[$key]->setPath($value);
        // 设置宽度高度
        $objDrawing[$key]->setHeight(100);//照片高度
        //$objDrawing[$k]->setWidth(80); //照片宽度
        /*设置图片要插入的单元格*/
        $objDrawing[$key]->setCoordinates("$letter[$j]$i");
        // 图片偏移距离
        $objDrawing[$key]->setOffsetX(50);
        $objDrawing[$key]->setOffsetY(10);
        $objDrawing[$key]->setWorksheet($excel->getActiveSheet());
        }
        $j++;
        }
        }
        $title = $title . time();
        $write = new \PHPExcel_Writer_Excel5($excel);
        ob_end_clean();
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");;
        header('Content-Disposition:attachment;filename="'.$title.'.xls"');
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
    }
}