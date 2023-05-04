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

class ProductController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 商品分类
     *
     *
     */
    public function product_level()
    {
        echo level();
    }

    /**
     * 商品列表
     *
     *
     */
    public function product_index()
    {
        $us = I('us');
        $username = $_COOKIE['username'];
        $this->assign('us', $us);
        $this->assign('username', $username);
        $this->assign('first', getFLevel());
        $this->display('product_index');
    }

    /**
     * 商品列表数据
     *
     *
     * */
    public function product_info()
    {
        $pageIndex = I('pageIndex', '');
        $page = !empty($pageIndex) ? $pageIndex + 1 : 1;
        $limit = 30;
        $pname = I('pname', '');
        $upanddown = I('upanddown', '');
        $product = M('mall_product');
        $id_str = sphinx('name', $pname, array('type' => 2, 'upanddown' => $upanddown), 'time desc', $page - 1, $limit);
        if (!empty($id_str['id_str'])) {
            $product_where['id'] = array('in', $id_str['id_str']);
            $vo = $product
                ->where($product_where)
                ->order('time desc')
                ->field('id,name,price,pnum,cnum
            ,case upanddown when 1 then \'已上架\' when 2 then \'已下架\' end as upanddown
            ,case isrecommend when 1 then \'推荐\' when 2 then \'不推荐\' end as rec
            ')
                ->select();
        } else {
            $vo = array();
        }

        $num = $id_str['total_found'];
        return Response::mjson($vo, $num);
    }

    /**
     * 商品评价界面
     *
     *
     * */
    public function product_evaluate_index()
    {
        $us = I('us');
        $username = I('username');
        $this->assign('us', $us);
        $this->assign('username', $username);
        $this->display('product_index');
    }

    /**
     * 商品评价数据
     *
     *
     * */
    public function product_evaluate_info()
    {
//        $us = I('us','');
        $username = I('username', '');
//        $result_check = id_check($us);
        $page = I('page', '');
        $limit = !empty($page) ? 10 : '';
//        if($result_check !=1){
//            return Response::mjson(array(),-1);
//        }
        $where_shop['username'] = array('eq', $username);
        $sid = M('mall_shops')->where($where_shop)->getField('id');
        $pname = I('pname', '');
        $upanddown = I('upanddown', '');
        $product = M('mall_product');
        $where['sid'] = array('eq', $sid);
        if (!empty($pname)) {
            $where['name'] = array('like', '%' . $pname . '%');
        }
        if (!empty($upanddown)) {
            $where['upanddown'] = array('eq', $upanddown);
        }
        $num = $product->where($where)->count('id');
        $vo = $product
            ->where($where)
            ->page($page, $limit)
            ->field('id,name,price,pnum,cnum
            ,case upanddown when 1 then \'已上架\' when 2 then \'已下架\' end as upanddown
            ')
            ->select();
        return Response::mjson($vo, $num);
    }

    /**
     * 新建界面
     *
     */
    public function add_index()
    {
        $this->assign('first', getFLevel());

        $us = I('us');
        $username = I('username');
        $this->assign('us', $us);
        $this->assign('username', $username);
        $this->display('product_add');
    }

    /**
     * 新建操作
     *
     */
    public function add_do()
    {
        $us = I('us', '');
        $username = I('username', '');
        $result_check = id_check($us);
//        if($result_check !=1) {
//            return Response::show(444, '登陆超时');
//        }
        $where['username'] = array('eq', 'admin');
        $sid = M('mall_shops')->where($where)->getField('id');
        if (!empty($sid)) {
            $name = I('name', '');
            $name = htmlspecialchars_decode($name);
            if (empty($name)) {
                return Response::show(300, '请输入商品名称');
            } else {
                $data['name'] = $name;
            }

            $price = I('price', '');
            if (empty($price)) {
                return Response::show(300, '请输入商品价格');
            } else {
                $data['price'] = $price;
            }
            $jd_price = I('jd_price', '');
            if (empty($jd_price)) {
                return Response::show(300, '请输入商品原价即划线价');
            } else {
                $data['jd_price'] = $jd_price;
            }
            $cost_price = I('cost_price','');
            if(empty($cost_price) || $cost_price == '0.00'){
                return Response::show(300, '请输入商品成本价');
            }else{
                $data['cost_price'] = $cost_price;
            }
            $freight = I('freight', '');
            if (empty($freight)) {
                $data['freight'] = 0;
            } else {
                $data['freight'] = $freight;
            }

            if (!empty($_FILES['pic'])) {
                $pic = $_FILES['pic'];
                $pic_upload = uploadfile($pic);
                $data['pic'] = $pic_upload;
            } else {
                return Response::show(300, '请选择列表图片');
            }
//            $purl = I('purl','');
//            if(empty($_REQUEST) || $purl == '\r\n' || $purl == '' || $purl == null){
//
//            }else{
            if (!empty($_REQUEST['purl'])) {
                $picurl = $_REQUEST['purl'];//获取到纯<img>字符串
                if (SDE == 0)
                    $zhengze = '/<img[^>]*src=[\'"]http:\/\/[^\/]*([^\'"]*)[\'"][^>]*>/is';
                else
                    $zhengze = '/<img[^>]*src=[\'"]http:\/\/[^\/]*\/[^\/]*([^\'"]*)[\'"][^>]*>/is';
                preg_match_all($zhengze, $picurl, $result);
                $url = $result[1];
                foreach ($url as $pk => $pv) {
                    if (substr($pv, 0, 5) != '/M00/')
                        $url[$pk] = str_replace('serverimages/', '', $pv);
                    $url[$pk] = str_replace('//', '/', $pv);
                }
                $data['pics'] = implode(',', $url);
//                    $srcnum = substr_count($picurl, 'title');//获取<title>个数
//                    $piclist = array();
//                    for ($i = 0; $i < $srcnum; $i++) {
//                        $weizhi1 = strpos($picurl, "title");
//                        $weizhi2 = strpos($picurl, '>');
//                        $img = substr($picurl, $weizhi1, $weizhi2);//找到<img>位置
//                        $picweizhi1 = strpos($img, 'http://');//找到pic路径
//                        $picweizhi2 = strpos($img, '.jpg');
//                        $pic = substr($img, $picweizhi1, $picweizhi2-6);
//                        $piclist[$i] = $pic;
//                        $picurl = substr($picurl, $weizhi2+1);//移除上一个<img>
//                    }
//                    $data['pics'] = implode(',', $piclist);
//                echo $data['spic'];exit;
            } else {
                return Response::show(300, '请上传商品图片');
            }
//            }
//            $detail = I('detail','');
            if (empty($_REQUEST['detail'])) {
                return Response::show(300, '请输入商品图文详情');
            } else {
                $data['detail'] = $_REQUEST['detail'];
            }

//            $specifications = I('specifications','');
            if (empty($_REQUEST['specifications'])) {
                return Response::show(300, '请输入商品规格参数说明');
            } else {
                $data['specifications'] = $_REQUEST['specifications'];
            }

//            $pas = I('pas','');
            if (empty($_REQUEST['pas'])) {
                return Response::show(300, '请输入商品包装售后说明');
            } else {
                $data['pas'] = $_REQUEST['pas'];
            }

            $flid = I('fl', '');
            $slid = I('sl', '');
            $tlid = I('tl', '');
            if (!empty($flid) && !empty($slid) && !empty($tlid)) {
                $data['flid'] = $flid;
                $data['slid'] = $slid;
                $data['tlid'] = $tlid;
            } else {
                return Response::show(300, '请选择商品分类');
            }
            $data['status'] = 1;
            $data['upanddown'] = 1;
            $data['type'] = I('type', '2');
            $data['sid'] = $sid;
            $data['cnum'] = $sid . time();
            $data['time'] = NOW;
            //添加操作日志
            $admin_log = '新增商品:' . $name;
            $result = M('mall_product')->add($data);
            if ($result) {
                $skuid = 'dsy_' . $result;
                $detail_where['id'] = array('eq', $result);
                M('mall_product')->where($detail_where)->save(array(
                    'skuid' => $skuid
                ));

                //同步到es start
                $es_info = M('mall_product')
                    ->field('`id`,`skuid`,`name`,`price`,`flid`,`slid`,`tlid`,`sid`,`pnum`,`evaluate`,`isrecommend`,`type`,`status`,`upanddown`')
                    ->where(['id' => $result])
                    ->find();
                if (!empty($es_info)) {
                    addEs($es_info);
                    updateAllEs($es_info['skuid']);
                }
                //同步到es end

                admin_log($admin_log, 1, 'dsy_mall_product:' . $result);
                return Response::show(200, '添加成功');
            } else {
                admin_log($admin_log, 0, 'dsy_mall_product');
                return Response::show(400, '添加失败');
            }

        } else {
            return Response::show(400, '未找到该商家');
        }
    }

    /**
     * 详情界面
     *
     */
    public function product_detail()
    {
        $us = I('us');
        $username = I('username');
        $id = I('id', '');
        if (!empty($id)) {
            $where['a.id'] = array('eq', $id);
        } else {
            return Response::show(300, '请选择商品');
        }
        $vo = M('mall_product')
            ->join('as a left join dsy_mall_flevel as b on a.flid = b.id')
            ->join('left join dsy_mall_slevel as c on a.slid = c.id')
            ->join('left join dsy_mall_tlevel as d on a.tlid = d.id')
            ->where($where)
            ->field('
            a.*
            ,b.name as flname
            ,c.name as slname
            ,d.name as tlname
            ')
            ->select();

        //规格参数
        $where_specifications['pid'] = array('eq', $id);
        $specification = M('mall_product_specifications')
//            ->join('as a left join dsy_mall_product_specification_value as b on a.id = b.sid')
//            ->where($where)
            ->where($where_specifications)
            ->field('id as sid,name as sname')
            ->select();

        //参数值
        for ($i = 0; $i < count($specification); $i++) {
            $where_values['sid'] = array('eq', $specification[$i]['sid']);
            $values = M('mall_product_specification_value')->where($where_values)->field('name as vname')->select();
            $specification[$i]['values'] = $values;
        }
        $pics = $vo[0]['pics'];
        $pics2 = explode(',', $pics);

        if ($vo[0]['type'] == 2) {
            $vo[0]['pic'] = format_img($vo[0]['pic'], IMG_VIEW);
            foreach ($pics2 as $k => $v) {
                $pics2[$k] = format_img($v, IMG_VIEW);
            }
        }

        $this->assign('pics2', $pics2);
        $this->assign('us', $us);
        $this->assign('vo', $vo);
        $this->assign('username', $username);
        $this->assign('specification', $specification);
        $this->display('product_detail');
    }

    /**
     * 编辑界面
     *
     */
    public function edit_index()
    {
        $us = I('us');
        $username = I('username');
        $id = I('id', '');
        if (!empty($id)) {
            $where['id'] = array('eq', $id);
        } else {
            return Response::show(300, '请选择商品');
        }
        $vo = M('mall_product')
            ->where($where)
            ->select();

        $this->assign('first', getFLevel());
        $this->assign('second', getSLevel($vo[0]['flid']));
        $this->assign('third', getTLevel($vo[0]['slid']));

        //规格参数
        $where_specifications['pid'] = array('eq', $id);
        $specification = M('mall_product_specifications')
//            ->join('as a left join dsy_mall_product_specification_value as b on a.id = b.sid')
//            ->where($where)
            ->where($where_specifications)
            ->field('id as sid,name as sname')
            ->select();

        //参数值
        for ($i = 0; $i < count($specification); $i++) {
            $where_values['sid'] = array('eq', $specification[$i]['sid']);
            $values = M('mall_product_specification_value')->where($where_values)->field('name as vname')->select();
            $specification[$i]['values'] = $values;
        }
        $pics = $vo[0]['pics'];
        $pics2 = explode(',', $pics);
        $imgs = '';
        for ($j = 0; $j < count($pics2); $j++) {
            if ($vo[0]['type'] == 1) {
                $src = $pics2[$j];
            } else {
                $src = format_img($pics2[$j], IMG_VIEW);
            }
            $imgs .= '<img src =' . $src . ' width="240px" height="160px">&nbsp;';
        }
        $vo[0]['pic'] = format_img($vo[0]['pic'], IMG_VIEW);

        $this->assign('imgs', $imgs);
        $this->assign('us', $us);
        $this->assign('vo', $vo);
        $this->assign('username', $username);
        $this->assign('specification', $specification);
        $this->display('product_edit');
    }

    /**
     * 编辑操作
     * */
    public function edit_do()
    {
        $us = I('us', '');
        $username = I('username', '');


        $result_check = id_check($us);
//        if($result_check !=1) {
//            return Response::show(444, '登陆超时');
//        }
//        $where['username'] = array('eq',$username);
//        $sid = M('mall_shops')->where($where)->getField('id');
//        if(!empty($sid)){

        $id = I('id', '');
        if (empty($id)) {
            return Response::show(300, '请选择商品');

        }

        $name = I('name', '');
        $name = htmlspecialchars_decode($name);
        if (empty($name)) {
            return Response::show(300, '请输入商品名称');
        } else {
            $data['name'] = $name;
        }

        $num = I('num', '');
        if (!is_numeric($num) || $num < 0) {
            return Response::show(300, '请输入商品销量');
        } else {
            $data['pnum'] = $num;
        }

        $price = I('price', '');
        if (empty($price)) {
            return Response::show(300, '请输入商品价格');
        } else {
            $data['price'] = $price;
        }
        $jd_price = I('jd_price', '');
        if (empty($jd_price)) {
            return Response::show(300, '请输入商品原价即划线价');
        } else {
            $data['jd_price'] = $jd_price;
        }
        $cost_price = I('cost_price','');
        if(empty($cost_price) || $cost_price == '0.00'){
            return Response::show(300, '请输入商品成本价');
        }else{
            $data['cost_price'] = $cost_price;
        }
        $freight = I('freight', '');
        if (empty($freight)) {
            $data['freight'] = 0;
        } else {
            $data['freight'] = $freight;
        }

        if (!empty($_FILES['pic'])) {
            $pic = $_FILES['pic'];
            $pic_upload = uploadfile($pic);
            $data['pic'] = $pic_upload;
        }

//            $purl = I('purl','');
//            if(empty($_REQUEST) || $purl == '\r\n' || $purl == '' || $purl == null){
//
//            }else{
        if (!empty($_REQUEST['purl'])) {
            $picurl = $_REQUEST['purl'];//获取到纯<img>字符串
            if (SDE == 0)
                $zhengze = '/<img[^>]*src=[\'"]http:\/\/[^\/]*([^\'"]*)[\'"][^>]*>/is';
            else
                $zhengze = '/<img[^>]*src=[\'"]http:\/\/[^\/]*\/[^\/]*([^\'"]*)[\'"][^>]*>/is';
            preg_match_all($zhengze, $picurl, $result);
            $url = $result[1];
            foreach ($url as $pk => $pv) {
                if (substr($pv, 0, 5) != '/M00/')
                    $url[$pk] = str_replace('serverimages/', '', $pv);
                $url[$pk] = str_replace('//', '/', $pv);
            }

            $data['pics'] = implode(',', $url);
        } else {
            return Response::show(300, '请上传商品图片');
        }
//            }
//            $detail = I('detail','');
        if (empty($_REQUEST['detail'])) {
            return Response::show(300, '请输入商品图文详情');
        } else {
            $data['detail'] = $_REQUEST['detail'];
        }

//            $specifications = I('specifications','');
        if (empty($_REQUEST['specifications'])) {
            return Response::show(300, '请输入商品规格参数说明');
        } else {
            $data['specifications'] = $_REQUEST['specifications'];
        }

//            $pas = I('pas','');
        if (empty($_REQUEST['pas'])) {
            return Response::show(300, '请输入商品包装售后说明');
        } else {
            $data['pas'] = $_REQUEST['pas'];
        }

        $flid = I('fl', '');
        $slid = I('sl', '');
        $tlid = I('tl', '');
        if (!empty($flid) && !empty($slid) && !empty($tlid)) {
            $data['flid'] = $flid;
            $data['slid'] = $slid;
            $data['tlid'] = $tlid;
        } else {
            return Response::show(300, '请选择商品分类');
        }
        $data['etime'] = NOW;
        $where = array(
            'id' => array('eq', $id),
        );
        //添加操作日志
        $admin_log = '编辑商品:' . $name;
        $result = M('mall_product')->where($where)->save($data);
        if ($result !== false) {
            S('productdsy_' . $id, null);
            S('proImgdsy_' . $id, null);
            unlink($_SERVER['DOCUMENT_ROOT'] . '/dashengyun/Runtime/htmlProduct/' . $id . '.html');
            //同步到es start
            $es_data = [];
            $es_data['name'] = $name;
            $es_data['price'] = $price;
            $es_data['flid'] = $flid;
            $es_data['slid'] = $slid;
            $es_data['tlid'] = $tlid;
            $es_data['pnum'] = $num;
            editEs($es_data, $id);
            //同步到es end
            $sku = M('mall_product')->where($where)->getField('skuid');
            updateAllEs($sku);
            admin_log($admin_log, 1, 'dsy_mall_product:' . $id);
            return Response::show(200, '编辑成功');
        } else {
            admin_log($admin_log, 0, 'dsy_mall_product:' . $id);
            return Response::show(400, '编辑失败');
        }

//        } else {
//            return Response::show(400,'未找到该商家');
//        }


    }

    /**
     * 上架操作
     *
     */
    public function open()
    {
        $us = I('us', '');
        $result_check = id_check($us);
        if ($result_check != 1) {
            return Response::show(444, '登陆超时');
        }
        $id = I('ids', '');

        if (empty($id)) {
            return Response::show('300', '请选择商品');
        }
        $where['id'] = array('in', $id);
        $product = M('mall_product');
        $product->upanddown = 1;
        $product->uptime = NOW;
        $id_str = implode(',', $id);
        $infos_str = [];
        $infos = $product->where(['id' => ['in', $id_str]])->field('skuid,name')->select();
        $str_sku = '';
        foreach ($infos as $v) {
            S('product' . $v['skuid'], null);
            S('proImg' . $v['skuid'], null);
            $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            $str_sku .= $v['skuid'] . ',';
        }
        //添加操作日志
        $admin_log = '上架商品:' . implode(',', $infos_str);
        $result = $product->where($where)->save();
        if ($result !== false) {
            admin_log($admin_log, 1, 'dsy_mall_product:' . $id_str);
            foreach ($id as $pro_id) {
                //同步到es start
                $es_data = [];
                $es_data['upanddown'] = 1;
                editEs($es_data, $pro_id);
                //同步到es end
            }
            updateAllEs($str_sku);
            return Response::show('200', '操作成功');
        } else {
            admin_log($admin_log, 0, 'dsy_mall_product:' . $id_str);
            return Response::show('400', '操作失败');
        }
    }

    /**
     * 下架操作
     *
     */
    public function stop()
    {
        $us = I('us', '');
        $type = I('type');
        $result_check = id_check($us);
        if ($result_check != 1) {
            return Response::show(444, '登陆超时');
        }
        $id = I('ids', '');
        if (empty($id)) {
            return Response::show('300', '请选择商品');
        }
        $where['id'] = array('in', $id);
        $product = M('mall_product');
        $product->upanddown = 2;
        $product->downtime = NOW;
        $id_str = implode(',', $id);
        $infos_str = [];
        $infos = $product->where(['id' => ['in', $id_str]])->field('skuid,name')->select();
        $str_sku = '';
        foreach ($infos as $v) {
            S('product' . $v['skuid'], null);
            S('proImg' . $v['skuid'], null);
            $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
            $str_sku .= $v['skuid'] . ',';
        }
        //添加操作日志
        $admin_log = '下架商品:' . implode(',', $infos_str);
        $result = $product->where($where)->save();
        if ($result !== false) {
            admin_log($admin_log, 1, 'dsy_mall_product:' . $id_str);
            foreach ($id as $pro_id) {
                //同步到es start
                $es_data = [];
                $es_data['upanddown'] = 2;
                editEs($es_data, $pro_id);
                //同步到es end
            }
            deleteAllEs($str_sku,$type);
            return Response::show('200', '操作成功');
        } else {
            admin_log($admin_log, 0, 'dsy_mall_product:' . $id_str);
            return Response::show('400', '操作失败');
        }
    }

    /**
     * 推荐操作
     */
    public function rec()
    {
        $id = I('ids', '');
        if (empty($id)) {
            return Response::show('300', '请选择商品');
        }
        $product = M('mall_product');
        $whereone['id'] = array('in', $id);
        $isrec = $product->where($whereone)->field('isrecommend')->select();
        foreach ($isrec as $key => $value) {
            $array[] = $value['isrecommend'];
        }
        if (in_array('1', $array)) {
            return Response::show('300', '有商品已经处于推荐状态');
        }
        $product->startTrans();//开启事物
        $where['id'] = array('in', $id);
        $product->isrecommend = 1;
        $resultone = $product->where($where)->save();
        //查询商品所属店铺id
        if (count($id) > 1) {
            $ids = implode(',', $id);
            $where[] = "id in ($ids)";
            $sid = $product->where($where)->field('sid')->order("field(id,$ids)")->select();
        } else {
            $where['id'] = $id[0];
            $ids = $id[0];
            $sid = $product->where($where)->field('sid')->order("field(id,$ids)")->select();
        }

        foreach ($sid as $key => $value) {
            $sid[$key]['pid'] = $id[$key];
        }
        $infos_str = [];
        $infos = $product->where(['id' => ['in', $ids]])->field('skuid,name')->select();
        foreach ($infos as $v) {
            $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
        }
        //添加操作日志
        $admin_log = '推荐商品:' . implode(',', $infos_str);
        $resulttwo = M('mall_product_recommend')->addAll($sid);
        if (!empty($resultone) && !empty($resulttwo)) {
            $product->commit();
            admin_log($admin_log, 1, 'dsy_mall_product:' . $ids);
            foreach ($id as $pro_id) {
                //同步到es start
                $es_data = [];
                $es_data['isrecommend'] = 1;
                editEs($es_data, $pro_id);
                //同步到es end
            }
            return Response::show('200', '操作成功');
        } else {
            $product->rollback();
            admin_log($admin_log, 0, 'dsy_mall_product:' . $ids);
            return Response::show('400', '操作失败');
        }

    }

    /**
     * 取消推荐操作
     */
    public function cancle_rec()
    {
        $id = I('ids', '');
        if (empty($id)) {
            return Response::show('300', '请选择商品');
        }
        $product = M('mall_product');
        $productrec = M('mall_product_recommend');
        $product->startTrans();//开启事物
        $where['id'] = array('in', $id);
        $product->isrecommend = 2;
        $resultone = $product->where($where)->save();
        if (count($id) > 1) {
            //批量取消推荐
            $ids = implode(',', $id);
            $where[] = "id in ($ids)";
            $sid = $product->where($where)->field('sid')->order("field(id,$ids)")->select();
            foreach ($sid as $key => $value) {
                $sid[$key]['pid'] = $id[$key];
            }
            $recid = array();
            foreach ($sid as $value) {
                $prid = $productrec->where($value)->field('id')->select();
                $recid[] = $prid[0]['id'];
            }
            $recid = 'id in(' . implode(',', $recid) . ')';
            $result = $productrec->where($recid)->delete();

        } else {
            //单个取消
            $ids = $id[0];
            $sid = $product->where("id = $id[0]")->field('sid')->find();
            $sid = $sid['sid'];
            $pid = $id[0];
            $where1['pid'] = $pid;
            $where1['sid'] = $sid;
            $result = $productrec->where($where1)->delete();

        }
        $infos_str = [];
        $infos = $product->where(['id' => ['in', $ids]])->field('skuid,name')->select();
        foreach ($infos as $v) {
            $infos_str[] = '【' . $v['skuid'] . '】' . mb_substr($v['name'], 0, 8, 'utf8') . '...';
        }
        //添加操作日志
        $admin_log = '取消推荐商品:' . implode(',', $infos_str);
        if (!empty($resultone) && !empty($result)) {
            $product->commit();
            admin_log($admin_log, 1, 'dsy_mall_product:' . $ids);
            foreach ($id as $pro_id) {
                //同步到es start
                $es_data = [];
                $es_data['isrecommend'] = 2;
                editEs($es_data, $pro_id);
                //同步到es end
            }
            return Response::show('200', '操作成功');
        } else {
            $product->rollback();
            admin_log($admin_log, 0, 'dsy_mall_product:' . $ids);
            return Response::show('400', '操作失败');
        }

    }

    /**
     * 评价界面
     *
     */
    public function evaluate_index()
    {
        $us = I('us', '');
        $result_check = id_check($us);
//        if($result_check !=1) {
//            return Response::show(444, '登陆超时');
//        }
        $id = I('id', '');
        if (empty($id)) {
            return Response::show('300', '请选择商品');
        }
        $this->assign('id', $id)->display();
    }

    /**
     * 评价列表数据
     *
     */
    public function evaluate_info()
    {
        $us = I('us', '');
        $result_check = id_check($us);
//        if($result_check !=1) {
//            return Response::show(444, '登陆超时');
//        }
        $id = I('id', '');
        if (empty($id)) {
            return Response::show('300', '请选择商品');
        }
        $star = I('star', '');
        $time = I('time', '');
        if (!empty($star)) {
            switch ($star) {
                case 1:
                    $where['a.star'] = array('in', '4,5');
                    break;
                case 2:
                    $where['a.star'] = array('in', '2,3');
                    break;
                case 3:
                    $where['a.star'] = array('in', '1');
                    break;
            }
        }
        if (!empty($time)) {
            $where['a.time'] = array('like', $time . '%');
        }
        $where['a.pid'] = array('eq', $id);
        $result = M('mall_product_evaluate', 'dsy_')
            ->join('as a left join dsy_mall_order_specifications as b on a.spid = b.id')
            ->join('left join t_personal as c on a.uid = c.user_id')
            ->where($where)
            ->field('a.id,b.specifications,c.name,c.mobile,a.star,a.content,a.time')
            ->select();
        $num = count($result);
        return Response::mjson($result, $num);
    }

    /**
     * 查看评价图片
     *
     */
    public function evaluate_imgs()
    {
        $us = I('us', '');
        $result_check = id_check($us);
//        if($result_check !=1) {
//            return Response::show(444, '登陆超时');
//        }
        $id = I('id', '');
        if (empty($id)) {
            return Response::show('300', '请选择商品');
        }
        $where['id'] = array('eq', $id);
        $result = M('mall_product_evaluate')
            ->where($where)
            ->getField('pics');
        if (!empty($result)) {
            $pics = explode(',', $result);
            $urls = '';
            for ($i = 0; $i < count($pics); $i++) {
                $urls .= '<img src ="' . format_img($pics[$i], IMG_VIEW) . '" width="320" >';
            }
            echo $urls;
        } else {
            echo '暂无图片';
        }


    }

    /**
     * 规格参数界面
     */
    public function specifications_index()
    {
        $id = I('id', '');

        $v_model = M('mall_product_specification_value');

        $result = M('mall_product_specifications')->where(['pid' => $id])->field('id,name,pic')->select();
        foreach ($result as $k => $v) {
            $result[$k]['values'] = $v_model->where(['sid' => $v['id']])->field('id,name')->select();
        }
        $this->assign('list', $result);

        $config_list = M('mall_product_specification_config')->where(['pid' => $id])->select();
        foreach ($config_list as $k => $v) {
            $config_list[$k]['img_url'] = format_img($v['img_url'], IMG_VIEW);
        }
        $this->assign('config_list', $config_list);

        $this->assign('id', $id);
        $this->display();
    }

    /**
     * 规格参数列表数据
     */
    public function specifications_config()
    {
        $id = I('id', '');
        if (empty($id)) {
            return Response::show(300, '请选择商品');
        }
        $goodInfo = M('mall_product')->where(['id' => $id])->field('price,jd_price,cost_price')->find();
        if (empty($goodInfo)) {
            return Response::show(300, '请选择商品');
        }

        $picArr = I('picarr', []);
        $vArr = I('varr', []);
        //获取数组所有组合
        $arr = combination_arr($vArr);

        //上传图的规格值组合
        $picList = [];
        //组合列表
        $list = [];
        foreach ($arr as $v) {
            //需要上传图的规格
            $pitem = [];
            foreach ($picArr as $pk => $pv) {
                if ($pv == 1) {
                    $pitem[] = $v[$pk];
                }
            }
            $pitem = implode('p', $pitem);
            $pic = 0;
            if (!empty($pitem) && !in_array($pitem, $picList)) {
                $picList[] = $pitem;
                $pic = 1;
            }

            $list[] = [
                'vname' => $v,
                'price' => $goodInfo['price'],
                'jd_price' => $goodInfo['jd_price'],
                'cost_price' => $goodInfo['cost_price'],
//                'stock' => $goodInfo['stock'],//暂时商品没有库存，以后用时别忘了在查询时添加字段stock
                'pic' => $pic
            ];
        }

        return Response::json(200, 'success', $list);
    }

    /**
     * 规格参数配置保存
     */
    public function specifications_config_save()
    {
        $id = $_POST['id'];
        $sarr = $_POST['sarr'];
        $varr = $_POST['varr'];
        $picarr = $_POST['picarr'];
        $vlname = $_POST['vlname'];
        $vlpic = $_POST['vlpic'];
        $vlcostprice = $_POST['vlcostprice'];
        $vljdprice = $_POST['vljdprice'];
        $vlprice = $_POST['vlprice'];
//        $vlstock = $_POST['vlstock'];
        $vlimg = $_FILES['vlimg'];
        $vldefault = $_POST['vldefault'];

        if (empty($id)) {
            return Response::show(300, '请选择商品');
        }
        $goodInfo = M('mall_product')->where(['id' => $id])->field('skuid,name')->find();
        if (empty($goodInfo)) {
            return Response::show(300, '请选择商品');
        }

        //需要上传图片的数量
        $picNum = 0;
        foreach ($vlpic as $k => $v) {
            if (empty($vlcostprice[$k]) || empty($vljdprice[$k]) || empty($vlprice[$k])) {// || empty($vlstock[$k])
                return Response::show(300, '请完善规格组合【' . $vlname[$k] . '】的所有信息');
            }
            if ($v == 1) {
                $picNum++;
            }
        }
        if ($picNum != count($vlimg['tmp_name'])) {
            return Response::show(300, '请选择图片');
        }
        //上传图片
        $vlpicArr = [];
        if ($picNum > 0) {
            if (SDE == 0) {
                $fastDfs = A('app/FastDfs');
                for ($v = 0; $v < $picNum; $v++) {
                    $fastDfsDes = $fastDfs->uploadImg1(['error' => $vlimg['error'][$v], 'name' => $vlimg['name'][$v], 'size' => $vlimg['size'][$v], 'tmp_name' => $vlimg['tmp_name'][$v], 'type' => $vlimg['type'][$v]]);
                    if ($fastDfsDes['code'] != 1) {
                        return Response::show(300, '第' . ($v + 1) . '张图片上传失败：' . $fastDfsDes['msg']);
                    }
                    $vlpicArr[] = $fastDfsDes['data'];
                }
            } else {
                $config = array(
                    'maxSize' => 6145728,// 设置附件上传大小
                    'rootPath' => './../../../../../htdocs/images/',// 设置附件上传根目录
                    'exts' => array('jpg', 'gif', 'png', 'jpeg'),// 设置附件上传类型
                    'subName' => array('date', 'Ymd'),
                );
                $upload = new \Think\Upload($config);// 实例化上传类
                $uploadRes = $upload->upload();
                if ($uploadRes == false) {
                    return Response::show(300, $upload->getError());
                }
                foreach ($uploadRes as $v) {
                    $vlpicArr[] = '/' . $v['savepath'] . $v['savename'];
                }
            }
        }

        $s_model = M('mall_product_specifications');
        $v_model = M('mall_product_specification_value');
        $c_model = M('mall_product_specification_config');
        $s_model->startTrans();//开启事务

        //删除原记录
        $sidArr = $s_model->where(['pid' => $id])->getField('id', true);
        if (!empty($sidArr)) {
            $sres = $s_model->where(['id' => ['in', $sidArr]])->delete();
            $vres = $v_model->where(['sid' => ['in', $sidArr]])->delete();
            $cres = $c_model->where(['pid' => $id])->delete();
            if ($sres === false || $vres === false || $cres === false) {
                $s_model->rollback();//事务回滚
                return Response::show(300, '配置失败');
            }
        }

        //规格名插入数据
        $sAddArr = [];
        foreach ($sarr as $k => $v) {
            $sAddArr[] = [
                'pid' => $id,
                'name' => $v,
                'pic' => ($picarr[$k] == 1) ? 1 : 0,
                'time' => NOW,
            ];
        }
        $sres = $s_model->addAll($sAddArr);
        if ($sres === false) {
            $s_model->rollback();//事务回滚
            return Response::show(300, '配置失败~');
        }
        $sidArr = $s_model->where(['pid' => $id])->getField('id', true);
        //规格值插入数据
        $vAddArr = [];
        foreach ($varr as $k => $v) {
            foreach ($v as $vv) {
                $vAddArr[] = [
                    'pid' => $id,
                    'sid' => $sidArr[$k],
                    'name' => $vv,
                    'time' => NOW,
                ];
            }
        }
        $vres = $v_model->addAll($vAddArr);
        if ($vres === false) {
            $s_model->rollback();//事务回滚
            return Response::show(300, '配置失败~~');
        }

        //组合列表插入数据
        $img_num = 0;
        $cAddArrList = [];
        $picList = [];//上传图片的属性值搭配
        $product_data = [];
        foreach ($vlname as $k => $v) {
            $vnameArr = explode(',', $v);
            $vidArr = [];
            $pitem = [];
            $vnameArrAll = [];
            foreach ($vnameArr as $ck => $cv) {
                $vid = intval($v_model->where(['sid' => $sidArr[$ck], 'name' => $cv])->getField('id'));
                $vidArr[] = $vid;
                if ($picarr[$ck] == 1) {
                    $pitem[] = $vid;
                }
                $vnameArrAll[] = $sarr[$ck] . ':' . $cv;
            }
            $pitem = 'p' . implode('p', $pitem);
            if ($vlpic[$k] == 1) {
                $img_url = $vlpicArr[$img_num];
                $img_num++;
                $picList[$pitem] = $img_url;
            } else {
                $img_url = $picNum > 0 ? $picList[$pitem] : '';
            }
            $cAddArr = [
                'pid' => $id,
                'vkey' => 'c' . implode('c', $vidArr),
                'vid' => implode(',', $vidArr),
                'vname' => implode(' ', $vnameArrAll),
//                'stock' => $vlstock[$k],
                'price' => $vlprice[$k],
                'jd_price' => $vljdprice[$k],
                'cost_price' => $vlcostprice[$k],
                'img_url' => $img_url
            ];
            if ($vldefault == $v) {
                $cAddArr['is_default'] = 1;
                $product_data = [
                    'price' => $vlprice[$k],
                    'jd_price' => $vljdprice[$k],
                    'cost_price' => $vlcostprice[$k],
                    'etime' => date('Y-m-d H:i:s')
                ];
            } else {
                $cAddArr['is_default'] = 0;
            }
            $cAddArrList[] = $cAddArr;
        }
        $cres = $c_model->addAll($cAddArrList);
        if ($cres === false) {
            $s_model->rollback();//事务回滚
            return Response::show(300, '配置失败~~~');
        }
        if (!empty($product_data)) {
            $product_data['has_spec'] = 1;
            $edit_res = M('mall_product')->where(['id' => $id])->save($product_data);
            if ($edit_res !== false) {
                //同步到es start
                $es_data = [];
                $es_data['price'] = $product_data['price'];
                editEs($es_data, $id);
            }
        }
        S('productdsy_' . $id, null);
        S('proImgdsy_' . $id, null);

        //添加操作日志
        $admin_log = '配置商品【' . $goodInfo['skuid'] . '】' . mb_substr($goodInfo['name'], 0, 8, 'utf8') . '...规格参数';

        $s_model->commit();//事务提交
        admin_log($admin_log, 1, 'mall_product_specification_config');
        return Response::show(200, '配置成功');
    }


    /**
     * 规格参数配置保存，仅改变价格
     */
    public function specifications_config_save_y()
    {
        $id = $_POST['id'];
        $vlid = $_POST['vlid'];
        $vlname = $_POST['vlname'];
        $vlcostprice = $_POST['vlcostprice'];
        $vljdprice = $_POST['vljdprice'];
        $vlprice = $_POST['vlprice'];
//        $vlstock = $_POST['vlstock'];
        $vlimg = $_FILES['vlimg'];
        $vldefault = $_POST['vldefault'];

        if (empty($id)) {
            return Response::show(300, '请选择商品');
        }
        $goodInfo = M('mall_product')->where(['id' => $id])->field('skuid,name')->find();
        if (empty($goodInfo)) {
            return Response::show(300, '请选择商品');
        }

        //上传图片
        if (count($vlimg['tmp_name']) > 0) {
            if (SDE == 0) {
                $fastDfs = A('app/FastDfs');
            } else {
                $config = array(
                    'maxSize' => 6145728,// 设置附件上传大小
                    'rootPath' => './../../../../../htdocs/images/',// 设置附件上传根目录
                    'exts' => array('jpg', 'gif', 'png', 'jpeg'),// 设置附件上传类型
                    'subName' => array('date', 'Ymd'),
                );
                $upload = new \Think\Upload($config);// 实例化上传类
            }
        }

        $c_model = M('mall_product_specification_config');
        $c_model->startTrans();//开启事务

        $product_data = [];
        //规格组合修改
        foreach ($vlid as $v) {
            if (empty($vlcostprice[$v]) || empty($vljdprice[$v]) || empty($vlprice[$v])) {// || empty($vlstock[$v])
                return Response::show(300, '请完善规格组合【' . $vlname[$v] . '】的所有信息');
            }
            $cAddArr = [
//                'stock' => $vlstock[$v],
                'price' => $vlprice[$v],
                'jd_price' => $vljdprice[$v],
                'cost_price' => $vlcostprice[$v]
            ];
            if ($vldefault == $v) {
                $cAddArr['is_default'] = 1;
                $product_data = [
                    'price' => $vlprice[$v],
                    'jd_price' => $vljdprice[$v],
                    'cost_price' => $vlcostprice[$v],
                    'etime' => date('Y-m-d H:i:s')
                ];
            } else {
                $cAddArr['is_default'] = 0;
            }
            if (!empty($vlimg['tmp_name'][$v])) {
                if (SDE == 0) {
                    $fastDfsDes = $fastDfs->uploadImg1(['error' => $vlimg['error'][$v], 'name' => $vlimg['name'][$v], 'size' => $vlimg['size'][$v], 'tmp_name' => $vlimg['tmp_name'][$v], 'type' => $vlimg['type'][$v]]);
                    if ($fastDfsDes['code'] != 1) {
                        $c_model->rollback();//事务回滚
                        return Response::show(300, '规格组合【' . $vlname[$v] . '】图片上传失败：' . $fastDfsDes['msg']);
                    }
                    $cAddArr['img_url'] = $fastDfsDes['data'];
                } else {
                    $uploadRes = $upload->uploadOne(['error' => $vlimg['error'][$v], 'name' => $vlimg['name'][$v], 'size' => $vlimg['size'][$v], 'tmp_name' => $vlimg['tmp_name'][$v], 'type' => $vlimg['type'][$v]]);
                    if ($uploadRes == false) {
                        $c_model->rollback();//事务回滚
                        return Response::show(300, '规格组合【' . $vlname[$v] . '】图片上传失败：' . ($upload->getError()));
                    }
                    $cAddArr['img_url'] = '/' . $uploadRes['savepath'] . $uploadRes['savename'];
                }
            }
            $cres = $c_model->where(['vkey' => $v, 'pid' => $id])->save($cAddArr);
            if ($cres === false) {
                $c_model->rollback();//事务回滚
                return Response::show(300, '配置失败~~~');
            }
        }
        if (!empty($product_data)) {
            $product_data['has_spec'] = 1;
            $edit_res = M('mall_product')->where(['id' => $id])->save($product_data);
            if ($edit_res !== false) {
                //同步到es start
                $es_data = [];
                $es_data['price'] = $product_data['price'];
                editEs($es_data, $id);
            }
        }
        S('productdsy_' . $id, null);
        S('proImgdsy_' . $id, null);

        //添加操作日志
        $admin_log = '更改商品【' . $goodInfo['skuid'] . '】' . mb_substr($goodInfo['name'], 0, 8, 'utf8') . '...规格组合配置';

        $c_model->commit();//事务提交
        admin_log($admin_log, 1, 'mall_product_specification_config');
        return Response::show(200, '配置成功');
    }

    /**
     * 取消配置规格
     */
    public function specifications_del()
    {
        $pid = $_POST['pid'];
        if (!is_numeric($pid)
            || $pid == 0
        ) {
            return response::show(300, '缺少参数');
        }

        M('mall_product_specifications')->where(['pid' => $pid])->delete();
        M('mall_product_specification_value')->where(['pid' => $pid])->delete();
        M('mall_product_specification_config')->where(['pid' => $pid])->delete();
        M('mall_product')->where(['id' => $pid])->setField('has_spec', 0);

        $goodInfo = M('mall_product')->where(['id' => $pid])->field('skuid,name')->find();
        //添加操作日志
        $admin_log = '清空商品【' . $goodInfo['skuid'] . '】' . mb_substr($goodInfo['name'], 0, 8, 'utf8') . '...的规格';

        admin_log($admin_log, 1, 'mall_product_specification_config');
        return Response::show(200, '成功清空商品规格');
    }

    /**
     * 查看弹框/商城banner/人事邦banner点击量
     */
    public function viewPage()
    {
        $rid = (int)I('rid', 0);
        $this->assign('id', $rid);
        $this->display();
    }

    /**
     * 浏览量列表
     */
    public function getViewList()
    {
        $rid = (int)I('rid', 0);
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $etype = I('etype', -1);
        $source = I('source', -1);

        $where = [
            'pid' => $rid,
        ];
        if (!empty($startDate)) {
            $where['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($etype, [0, 1, 2]))
            $where['etype'] = $etype;
        if (in_array($source, [0, 1, 2, 3, 4, 5, 6]))
            $where['source'] = $source;

        $model = M('mall_product_view');
        $count = (int)$model->where($where)->count();
        $list = (array)$model
            ->where($where)
            ->page($page, $limit)
            ->order('`create_date` desc,`id` desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['user'] = ($v['uid'] == 0) ? '游客' : getUserName($v['uid']);
            switch ($v['etype']) {
                case 1:
                    $etype = 'Ios';
                    break;
                case 2:
                    $etype = 'Android';
                    break;
                default:
                    $etype = '小程序';
                    break;
            }
            $list[$k]['etype'] = $etype;
            switch ($v['source']) {
                case 0:
                    $source = '商品列表';
                    break;
                case 1:
                    $source = '首页弹框';
                    break;
                case 2:
                    $source = '商城banner';
                    break;
                case 3:
                    $source = '人事邦banner';
                    break;
                case 5:
                    $title = M('activity')->where(['id' => $v['data_id']])->getField('title');
                    $source = '专题活动';
                    if (!empty($title))
                        $source .= ' : ' . $title;
                    break;
                default:
                    $source = '其他';
                    break;
            }
            $list[$k]['source'] = $source;
        }

        return Response::mjson($list, $count);
    }

    public function delivery()
    {
        $pid = I('pid', 0);

        $provinces = M('mall_product_delivery')->where(['pid' => $pid])->getField('jd_province', true);
        $data = [];
        $data[] = [
            'id' => '0',
            'name' => '全国',
            'checked' => false,
        ];
        $token = selAccess_token();
        $info = getFirst($token);
        foreach ($info as $key => $value) {
            $data[] = [
                'id' => $value,
                'name' => $key,
                'checked' => in_array($value, $provinces),
            ];
        }
        if (empty($provinces)) {
            $data[0]['checked'] = true;
        }

        $this->assign('list', $data);
        $this->assign('pid', $pid);
        $this->display();
    }

    public function updateDelivery()
    {
        $pid = I('pid', 0);
        $provinces = I('provinces', []);

        if (empty($provinces)) {
            return Response::show(300, '请选择配送地区');
        }

        M('mall_product_delivery')->where(['pid' => $pid])->delete();
        $list = [];
        foreach ($provinces as $v) {
            if ($v['id'] > 0) {
                $list[] = [
                    'pid' => $pid,
                    'jd_province' => $v['id'],
                    'province' => $v['name'],
                ];
            }
        }
        M('mall_product_delivery')->addAll($list);
        return Response::show(200, '设置成功');
    }

}
