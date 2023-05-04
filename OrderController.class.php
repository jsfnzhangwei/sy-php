<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/4/10
 * Time: 18:44
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;

class OrderController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    /**
     *订单列表
     */
    public function order_lis(){
        $shop = M('mall_shops');
        $shops = $shop->field('id,name')->select();
        $this->assign('shops',$shops);
        $this->display('order_lis');
    }

    /**
     *  订单列表数据
     */
    public function order_info(){
        $pageIndex = I('pageIndex',0);
        $page = $pageIndex;
        $limit = 10;
        $ordernum = I('ordernum','');
        $shops = I('shops','');
        $start = I('start1','');
        $end = I('end','');
        $status = I('status','');
        if(!empty($ordernum)){
            $where[] = " a.ordernum = $ordernum ";
        }if(!empty($shops)){
            $where[] = " a.sid = $shops ";
        }
        if (!empty($start)) {
            $where[] = " a.time>='" . $start . " 00:00:00' ";
        }
        if (!empty($end)) {
            $where[] = " a.time<='" . $end . " 23:59:59' ";
        }
        if (!empty($status)) {
            $where[] = " a.status='" . $status . "' ";
        }
        $where[] = " a.order_type in (1,5)";
        if(!empty($where)){
            $where = 'where '.implode('and',$where);
        }
        $order[] = ' order by a.time desc ';
        $offset = $page*$limit;//

        $Model = new \Think\Model();
        $result = $Model->query("
            SELECT
                a.id,
                a.ordernum,
                a.status,
                b.user_name AS name,
                c.`name` AS sname,
                a.payprice AS price,
                a.time,
                c.shop_name,
                a.checkstatus,
                CASE a.order_type 
                WHEN 1 THEN '自营'
                WHEN 5 THEN '自营薪福广场'
                ELSE '' END  AS order_type
            FROM
                `dsy_mall_order` AS a
            LEFT JOIN t_user AS b ON a.uid = b.user_id
            LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id 
             $where   ORDER BY a.time DESC   
            LIMIT $offset,$limit
        ");

        $num = $Model->query(" SELECT COUNT(*) FROM `dsy_mall_order` as a LEFT JOIN t_user AS b ON a.uid = b.user_id LEFT JOIN dsy_mall_shops AS c ON a.sid = c.id $where");
        $num = $num[0]["count(*)"];

        return Response::mjson($result,$num);
    }

    /**
     * 订单详情页
     */
    public function order_detail(){
        $ordernum = I('ordernum','');
        $Model = new \Think\Model();
        if(!empty($ordernum)){
            $where = " a.ordernum = $ordernum";

            $result = $Model->query("
                SELECT
                    a.ordernum,
                    a.order_notpay_num,
                    a.payprice,
                    a.message,
                    a.freight,
                    a.name,
                    a.mobile,
                    a.paytime,
                    a.isneedinvoice,
                    a.isneedinvoice,
                    a.invoicecontent,
                    a.price,
                    a.pid,
                    a.time,
                    a.address,
                    a.etype,
                    a.enum,
                    CASE a.invoicetype
                WHEN 1 THEN
                    '企业'
                WHEN 2 THEN
                    '个人'
                ELSE
                    ''
                END AS invoicetype,
                 b.user_name AS bmobile
                FROM
                    `dsy_mall_order` AS a
                LEFT JOIN t_user AS b ON a.uid = b.user_id
                WHERE $where
            ");
            $info = M('mall_order', 'dsy_')->where(array('ordernum'=>$ordernum))->find();
            $pids = explode(',',$info['pid']);
            $order_type = $info['order_type'];
            $resu = M('mall_order_notpay', 'dsy_')->where(array('ordernum', $info['order_notpay_num']))->field('wz_orderid')->find();
            $wz_orderid = $resu['ordernum'];
            $enum = $info['enum'];//订单编号
            $etype = $info['etype'];//快递类型
            // $token = selAccess_token();
            // if($token==false && !in_array($order_type,[1,5])){
            //     return '获取token失败';
            // }
            $change_array = array();
            if($order_type == 1 || $order_type==5){
                $mobile = mb_substr($info['mobile'],7,11);
                $express = M('MallLogistics')->where(['id' => $etype])->find();
                $travel_info = logistics($enum,$express['num'],$mobile);
                //$travel_info = express($etype,$enum);
                if($travel_info['code'] == 200){
                    $change_array['num'] = $enum;
                    foreach($travel_info['data'] as $kk=>$vv){
                        $change_array['travel'][$kk]['msgTime'] = $vv['time'];
                        $change_array['travel'][$kk]['content'] = '';
                        $change_array['travel'][$kk]['operator'] = $vv['status'];
                    }
                }
            }
            foreach($pids as $key=>$value){
                $skuid = getSkuid($value);
                if($order_type == 2 || $order_type == 3 || $order_type==4){
                    $travelInfo = product_travel($token,$wz_orderid,$skuid);//物流信息
                    $data[] = $travelInfo;
                }else{
                    if($key == 0){
                        $travelInfo = $change_array;
                        $data[] = $travelInfo;
                    }
                }
            }

            $vo = $result[0];
            if(empty($vo['bname'])){
                $vo['bname'] = $vo['mobile'];
            }
            $vo['address'] = trim($vo['address']);
            $this->assign('vo',$vo);
            $this->assign('data',$data);
            $this->assign('ordernum',$ordernum);

            //售后信息
            $returnList = M('mall_order_return')->where(['ordernum' => $ordernum, 'num' => ['exp', 'is not null']])->select();
            if (!empty($returnList)) {
                $OrderReturnModel = new \Common\Model\OrderReturnModel;
                foreach ($returnList as $k => $v) {
                    $orderWhere = ['ordernum' => $ordernum, 'pid' => $v['pid']];
                    if (!empty($v['specv'])) {
                        $orderWhere['specv'] = $v['specv'];
                    }
                    $sInfo = M('MallOrderSpecifications')->where($orderWhere)->find();
                    $returnList[$k]['pro_name'] = $sInfo['pro_name'];
                    $returnList[$k]['pro_skuid'] = $sInfo['pro_skuid'];
                    $returnList[$k]['pro_num'] = $sInfo['num'];
                    $returnList[$k]['pro_price'] = $sInfo['price']*$sInfo['num'];
                    $returnList[$k]['specifications'] = $sInfo['specifications'];
                    $returnList[$k]['status'] = $OrderReturnModel::returnStatus($v['status']);
                    $returnList[$k]['type'] = $OrderReturnModel::type($v['type']);
                    if ($v['status'] != 2) {
                        $returnList[$k]['return_money'] = '--';
                        $returnList[$k]['return_wquota'] = '--';
                        $returnList[$k]['return_card'] = '--';
                    }
                }
            }
            $this->assign('returnList', $returnList);


            $this->display('order_detail');
        }else{
            echo '缺少参数';
        }
    }

    /**
     * 订单详情页列表数据
     */
    public function order_detail_info(){
        $ordernum = I('ordernum','');
        $goods = M('MallOrderSpecifications')->where(['ordernum' => $ordernum])->select();
        $result = [];
        foreach ($goods as $good) {
            $where_check = [];
            $where_check['ordernum'] = $ordernum;
            $where_check['pid'] = $good['pid'];
            $where_check['num'] = ['exp', 'is not null'];
            if (!empty($good['specv'])) {
                $where_check['specv'] = $good['specv'];
            }
            $check = M('mall_order_return')->where($where_check)->find();
            $type = 0;
            $status = '无';
            if (!empty($check)) {
                $type = $check['type'];
                switch ($check['status']) {
                    case 1:
                        $status = '申请中';
                        break;
                    case 2:
                        $status = '同意';
                        break;
                    case 3:
                        $status = '不同意';
                        break;
                    default:
                        $status = '无';
                        break;
                }
            }
            $result[] = [
                'aid' => $good['pid'],
                'skuid' => $good['pro_skuid'],
                'pname' => $good['pro_name'],
                'cprice' => $good['price'],
                'specifications' => $good['specifications'],
                'num' => $good['num'],
                'type' => $type,
                'status' => $status,
            ];
        }
        $num = count($result);
        return Response::mjson($result, $num);
        $order_notpay_num = I('order_notpay_num','');
        $where['ordernum'] = array('eq',$ordernum);
        $pids = M('mall_order')
            ->where($where)
            ->getField('pid');
        $where_p['a.id'] = array('in',$pids);

        $result = M('mall_product')
            ->join('as a left join dsy_mall_order_specifications as c on a.id = c.pid  and c.ordernum='.$ordernum)
            ->join(' left join dsy_mall_order_return as b on b.pid = a.id and b.spid = c.id and b.type=3 and b.ordernum='.$ordernum)
            ->where($where_p)
            ->field('a.cnum,a.name as pname,a.skuid,a.price,c.specifications,c.price as cprice,c.num,a.id as aid,c.id as cid,b.ordernum
            , b.type
            ,case b.status when 1 then \'申请中\'  when 2 then \'同意\'  when 3 then \'不同意\' else \'无\' end as status
            ')
            ->select();

//        $result = M('mall_product')
//            ->join('as a left join dsy_mall_order_specifications as c on a.id = c.pid  and c.ordernum='.$order_notpay_num)
//            ->join(' left join dsy_mall_order_return as b on b.pid = a.id and b.spid = c.id and b.type=3 and b.ordernum='.$ordernum)
//            ->where($where_p)
//            ->field('a.cnum,a.name as pname,a.price,c.specifications,c.num,a.id as aid,c.id as cid,b.ordernum
//            ,case b.status when 1 then \'������\'  when 2 then \'��ͬ��\'  when 3 then \'��ͬ��\' else \'\' end as status
//            ')
//            ->select();



        $num = count($result);
        return Response::mjson($result,$num);
    }

    /**
     * 购物车页面
     */
    public function cartPage()
    {
        $this->display();
    }

    /**
     * 获取购物车列表
     */
    public function getCartList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $source = I('source', -1);

        $where = [
            'is_del' => 1,
        ];
        if (!empty($startDate)) {
            $where['time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['time'][] = ['elt', $endDate . ' 23:59:59'];
        }
        switch ($source) {
            case 1:
                $where['said'] = 0;
                $where['is_act'] = 1;
                $where['act_type'] = 1;
                break;
            case 2:
                $where['said'] = 0;
                $where['is_act'] = 1;
                $where['act_type'] = 2;
                break;
            case 3:
                $where['said'] = ['gt', 0];
                break;
        }

        $all = (array)M('mall_shopcart')
            ->where($where)
            ->field('MAX(id) mid,pid,skuId,specifications,SUM(num) as add_num')
            ->group('skuId,specv')
            ->order('add_num DESC,mid DESC')
            ->select();
        $count = count($all);
        $list = array_slice($all, ($page - 1) * $limit, $limit);
        foreach ($list as $k => $v) {
            $list[$k]['goods'] = getProductsNames($v['pid']);
        }
        /*
        $model = M('mall_shopcart');
        $count = (int)$model->where($where)->count();
        $list = (array)$model
            ->where($where)
            ->page($page, $limit)
            ->order('`time` desc,`id` desc')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['user'] = getUserName($v['uid']);
            $goods = getpinfobyid($v['pid']);
            $list[$k]['goods'] = $goods['pname'];
            //是否参加活动
            $act_name = '--';
            if ($v['said'] > 0) {
                $act_name = '[专题活动]' . (M('activity')->where(['id' => $v['said']])->getField('title'));
            } else {
                if ($v['is_act'] != 2) {
                    switch ($v['act_type']) {
                        case 1:
                            $act_name = '[薪福来领]' . (M('company_activity')->where(['id' => $v['act_id']])->getField('name'));
                            break;
                        case 2:
                            $act_name = '[兑换天地]' . (M('company_exchange_activity')->where(['id' => $v['act_id']])->getField('name'));
                            break;
                        default:
                            break;
                    }
                }
            }
            $list[$k]['act_name'] = $act_name;

            if ($v['act_price'] > 0)
                $list[$k]['price'] = $v['act_price'];
            else
                $list[$k]['price'] = $goods['price'];
        }
        */

        return Response::mjson($list, $count);
    }

    /**
     * 商品浏览量统计页面
     */
    public function goodsPage()
    {
        $this->display();
    }

    /**
     * 获取商品浏览量统计
     */
    public function getGoodsList()
    {
        ini_set('memory_limit','256M');
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $etype = I('etype', -1);
        $source = I('source', -1);

        $where = [];
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

        $all = (array)M('mall_product_view')
            ->where($where)
            ->field('MAX(id) mid,pid,COUNT(1) as add_num')
            ->group('pid')
            ->order('add_num DESC,mid DESC')
            ->select();
        $count = count($all);
        $list = array_slice($all, ($page - 1) * $limit, $limit);
        foreach ($list as $k => $v) {
            $goods = getpinfobyid($v['pid']);
            $list[$k]['skuid'] = $goods['skuid'];
            $list[$k]['goods'] = $goods['pname'];
        }

        return Response::mjson($list, $count);
    }
    public function snacks_order(){
        $this->display();
    }
    public function snacks_order_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $ordernum = I('ordernum');
        $card = I('card');
        $start_time = I('start1');//开始时间
        $end_time = I('end');//结束时间

        if(!empty($ordernum)){
            $where['a.id_of_zsk'] = array('eq',$ordernum);
        }
        if(!empty($card)){
            $where['c.card_num'] = array('in',$card);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }

        $result = M('gift_card_of_zsk')
                    ->alias('a')
                    ->field('a.*')
                    ->join('left join dsy_gift_card_of_zsk_item as b on b.main_table_id=a.id')
                    ->join('left join dsy_gift_card_detail as c on b.sy_card_id = c.id')
                    ->where($where)
                    ->group('a.id')
                    ->order('a.create_time desc')
                    ->page($page,$limit)
                    ->select();
        $num = M('gift_card_of_zsk')
                    ->alias('a')
                    ->join('left join dsy_gift_card_of_zsk_item as b on a.id = b.main_table_id')
                    ->join('left join dsy_gift_card_detail as c on b.sy_card_id = c.id')
                    ->where($where)
                    ->group('a.id')
                    ->select();
        $num = count($num);
        foreach($result as $key => $val){
            $item = M('gift_card_of_zsk_item')
                        ->alias('a')
                        ->field('a.amount,b.card_num')
                        ->join('left join dsy_gift_card_detail as b on a.sy_card_id = b.id')
                        ->where(['a.main_table_id'=>$val['id']])
                        ->select();
            $str = '';
            foreach($item as $k => $v){
                $str .= $v['card_num'] . '(' . $v['amount'] . '),';
            }
            $result[$key]['card'] = $str;
        }
        return  Response::mjson($result,$num);
    }
    public function snacks_order_excel(){
        $ordernum = I('ordernum');
        $card = I('card');
        $start_time = I('start1');//开始时间
        $end_time = I('end');//结束时间

        if(!empty($ordernum)){
            $where['a.id_ of_zsk'] = array('eq',$ordernum);
        }
        if(!empty($card)){
            $where['c.card_num'] = array('in',$card);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }else{
            $day = date('Y-m');
            $where['a.create_time'] = array('like',"%$day%");
        }

        $result = M('gift_card_of_zsk')
                    ->alias('a')
                    ->field('a.*')
                    ->join('left join dsy_gift_card_of_zsk_item as b on b.main_table_id=a.id')
                    ->join('left join dsy_gift_card_detail as c on b.sy_card_id = c.id')
                    ->where($where)
                    ->group('a.id')
                    ->order('a.create_time desc')
                    ->page($page,$limit)
                    ->select();
        foreach($result as $key => $val){
            $result[$key]['item'] = M('gift_card_of_zsk_item')
                        ->alias('a')
                        ->field('a.amount,b.card_num')
                        ->join('left join dsy_gift_card_detail as b on a.sy_card_id = b.id')
                        ->where(['a.main_table_id'=>$val['id']])
                        ->select();
            $result[$key]['status'] = '支付成功';
        }
        $this->get_excel2($result,'座上客订单列表');
    }
    /**
     * 数据导出excel
     */
    public function get_excel2($data,$title){
        import('Org.Util.PHPExcel');
        $excel = new \PHPExcel();
        //Excel表格式,这里简略写了8列
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        //第一个sheet
        $excel->setactivesheetindex(0);
        //设置sheet标题
        $excel->getActiveSheet()->setTitle($title);
        //填充表格信息
        $excel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $excel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
        $excel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
        $excel->getActiveSheet()->setCellValue('A1','苏鹰订单编号');
        $excel->getActiveSheet()->setCellValue('B1','座上客订单状态');
        $excel->getActiveSheet()->setCellValue('C1','门店编号');
        $excel->getActiveSheet()->setCellValue('D1','门店名称');
        $excel->getActiveSheet()->setCellValue('E1','苏鹰卡卡号');
        $excel->getActiveSheet()->setCellValue('F1','苏鹰卡使用金额');
        $excel->getActiveSheet()->setCellValue('G1','苏鹰卡使用总金额');
        $excel->getActiveSheet()->setCellValue('H1','订单状态');
        $excel->getActiveSheet()->setCellValue('I1','下单时间');
        $excel->getActiveSheet()->getStyle('A1:I1')->getFont()->setBold(true);
        $key = 2;
        $count = count($data);
        for ($i = 0;$i < $count;$i++) {
            $excel->getActiveSheet()->setCellValue('A' . $key, $data[$i]['id']);
            $excel->getActiveSheet()->setCellValue('B' . $key, $data[$i]['id_of_zsk']);
            $excel->getActiveSheet()->setCellValue('C' . $key, $data[$i]['zsk_store_id']);
            $excel->getActiveSheet()->setCellValue('D' . $key, $data[$i]['zsk_store_name']);
            $excel->getActiveSheet()->setCellValue('G' . $key, $data[$i]['all_amount_sy']);
            $excel->getActiveSheet()->setCellValue('H' . $key, $data[$i]['status']);
            $excel->getActiveSheet()->setCellValue('I' . $key, $data[$i]['create_time']);
            if(count($data[$i]['item']) > 1){
                $iS = $key;
                foreach($data[$i]['item'] as $kk => $vl){
                    $excel->getActiveSheet()->setCellValue('E' . $key, $data[$i]['item'][$kk]['card_num']);
                    $excel->getActiveSheet()->setCellValue('F' . $key, $data[$i]['item'][$kk]['amount']);
                    $iE = $key++;
                }
                $excel->getActiveSheet()->mergeCells('A' . $iS . ':A' . $iE);
                $excel->getActiveSheet()->mergeCells('B' . $iS . ':B' . $iE);
                $excel->getActiveSheet()->mergeCells('C' . $iS . ':C' . $iE);
                $excel->getActiveSheet()->mergeCells('D' . $iS . ':D' . $iE);
                $excel->getActiveSheet()->mergeCells('G' . $iS . ':G' . $iE);
                $excel->getActiveSheet()->mergeCells('H' . $iS . ':H' . $iE);
                $excel->getActiveSheet()->mergeCells('I' . $iS . ':I' . $iE);
            }else{
                $excel->getActiveSheet()->setCellValue('E' . $key, $data[$i]['item'][0]['card_num']);
                $excel->getActiveSheet()->setCellValue('F' . $key, $data[$i]['item'][0]['amount']);
                $key++;
            }
        }
        $excel->getActiveSheet()->getStyle('A1:I' . $key)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $excel->getActiveSheet()->getStyle('A1:P' . $key)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $excel->getActiveSheet()->getStyle('I1:I' . $key)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
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