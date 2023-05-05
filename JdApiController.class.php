<?php
namespace app\Controller;
use Org\Util\Response;
use Think\Controller;

class JdApiController extends Controller{
    private $appKey = '';

    private $appSecret = '';

    private $url = '';

    private $picUrlN0 = 'https://img13.360buyimg.com/n0/';
    private $picUrlN1 = 'https://img13.360buyimg.com/n1/';
    private $picUrlN2 = 'https://img13.360buyimg.com/n2/';

    public function _initialize(){
        $config = C('JD_CONFIG');
        $this->appKey = $config['app_key'];
        $this->appSecret = $config['app_secret'];
        $this->url = $config['url'];
    }
    //获取鉴权码
    public function selAccessToken(){
        $config = C('JD_CONFIG');
        $fopen_private = fopen($config['private_key_path'],"r");
        $private_key = fread($fopen_private,filesize($config['private_key_path']));//获取密钥
        $redirect_uri = urlencode($config['redirect_uri']);//鉴权码回调地址，需要urlencode编码
        $username = urlencode($config['username']);//登录用户名，需要urlencode编码
        $password = $this->privateEncrypt($config['password'],$private_key);//加密密码
        $url = $config['url'] . '/authorizeForVOP?app_key=' . $config['app_key'] . '&response_type=code&redirect_uri=' . $redirect_uri . '&username=' . $username . '&password=' . $password . '&scope=snsapi_base';
        text_curl_get($url);
    }

    //鉴权码回调地址
    public function callbackToken(){
        $state = I('state');
        $code  = I('code');//获取的鉴权码

        $config = C('JD_CONFIG');
        $url = $config['url'] . '/access_token?app_key=' . $config['app_key'] . '&app_secret=' . $config['app_secret'] . '&grant_type=authorization_code&code=' . $code;
        $return['data'] = text_curl_get($url);
        $return['time'] = time();
        if($return['data']['code'] == 0){
            file_put_contents('ThinkPHP/Library/Vendor/JdApi/access_token.txt', json_encode($return, JSON_UNESCAPED_UNICODE) . PHP_EOL);
        }else{
            file_put_contents('Runtime/jd_log/jd' . date('Y-m-d') . '.log','请求地址：' . $url . '接收数据：' . json_encode($return, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        }
    }

    //刷新access_token地址
    public function refreshAccessToken(){
        $data = file_get_contents('ThinkPHP/Library/Vendor/JdApi/access_token.txt');
        $data = json_decode($data,true);
        $url = $this->url . '/refresh_token?app_key=' . $this->appKey . '&app_secret=' . $this->appSecret . '&grant_type=refresh_token&refresh_token=' . $data['data']['refresh_token'];
        $return['data'] = text_curl_get($url);
        $return['time'] = time();
        if($return['data']['code'] == 0){
            file_put_contents('ThinkPHP/Library/Vendor/JdApi/access_token.txt', json_encode($return, JSON_UNESCAPED_UNICODE) . PHP_EOL);
        }else{
            file_put_contents('Runtime/jd_log/jd' . date('Y-m-d') . '.log','请求地址：' . $url . '接收数据：' . json_encode($return, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        }
        return $return['data']['access_token'];
    }

    //获取access_token
    private function getAccessToken(){
        $data = file_get_contents('ThinkPHP/Library/Vendor/JdApi/access_token.txt');
        $data = json_decode($data,true);
        $now = time();
        $time = $data['time'] + 43200;
        if($now < $time){
            $access_token = $data['data']['access_token'];
        }else{
            $access_token = $this->refreshAccessToken();
        }
        return $access_token;
    }
    /**
     * 私钥加密
     * @param $password密码
     * @param $private_key私钥
     * @return string
     * @throws \Exception
     */
    public function privateEncrypt($password,$private_key)
    {
        $password = md5($password);
        $pi_key =  openssl_pkey_get_private($private_key);
        openssl_private_encrypt($password,$encrypted,$pi_key);
        if ($encrypted === false) {
            throw new \Exception('Could not encrypt the data.');
        }
        return urlencode(base64_encode($encrypted));
    }

    //VOP--四级地址查询API
    /**
     * 查询四级地址ID列表
     */
    public function addressQueryJd($areaLevel,$JdAreaId){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopAddressQueryJdAreaIdListRequest');
        $req = new \VopAddressQueryJdAreaIdListRequest();
        $req->setAreaLevel($areaLevel);
        $req->setJdAreaId($JdAreaId);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_address_queryJdAreaIdList_responce']['openRpcResult'];
        if($result['resultCode'] === '0000'){
            $data = $result['result']['areaInfoList'];
            return ['code' => 200,'data' =>$data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 验证四级地址ID有效性
     */
    public function addressVerifyAreaFourId(){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopAddressVerifyAreaFourIdOpenReqRequest');
        $req = new \VopAddressVerifyAreaFourIdOpenReqRequest();
        $req->setProvinceId(8);
        $req->setCityId(609);
        $req->setTownId(61440);
        $req->setCountyId(20183);


        $resp = $vop->execute($req, $vop->accessToken);
        print_r($resp);
    }

    //VOP--商品类京东API
    /**
     * 搜索商品
     */
    public function searchSkuGoodsReq(){
        $word = I('word');
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopGoodsSearchSkuRequest');
        $req = new \VopGoodsSearchSkuRequest();
        // $req->setBrandId( );
        // $req->setBrandCollect( );
        // $req->setExtAttrCollect( );
        $req->setKeyword($word);
        // $req->setPageSize( );
        // $req->setPriceCollect( );
        // $req->setUseCacheStore( );
        // $req->setMaxPrice( );
        // $req->setPageIndex( );
        // $req->setCategoryId3( );
        // $req->setCategoryId1( );
        // $req->setCategoryId2( );
        $req->setNeedMergeSku(2);
        // $req->setSortType( );
        // $req->setMinPrice( );
        // $req->setAreaIds( );
        $resp = $vop->execute($req, $vop->accessToken);
        if($resp->jingdong_vop_goods_searchSku_responce->code == 0){
            $data = $resp->jingdong_vop_goods_searchSku_responce->openRpcResult->result->skuHitResultPaging->items;
            print_r($data[0]->brandName);die();
            return ['code'=>200,'message'=>'获取成功','data'=>$resp];
        }else{
            return ['code'=>300,'message'=>'系统开小差了~'];
        }
    }

    /**
     * 查询商品上下架
     */
    public function getSkuStateGoods($skuArr = null){
        $skuArr = implode(',',$skuArr);
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopGoodsGetSkuStateListRequest');
        $req = new \VopGoodsGetSkuStateListRequest();
        $req->setSkuId($skuArr);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_goods_getSkuStateList_responce']['openRpcResult'];
        if($result['resultCode'] === '0000'){
            $data = $result['result'];
            return ['code' => 200,'data' =>$data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 查询商品可售性
     */
    public function checkSkuSaleGoods($skuArr = null){
        $skuArr = implode(',',$skuArr);
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopGoodsCheckSkuSaleListRequest');
        $req = new \VopGoodsCheckSkuSaleListRequest();
        $req->setSkuId($skuArr);
        $resp = $vop->execute($req, $vop->accessToken);
        $result = $resp['jingdong_vop_goods_checkSkuSaleList_responce']['openRpcResult'];
        if($result['resultCode'] === '0000'){
            $data = $result['result'];
            foreach($data as $key => $val){
                if(in_array($val['noReasonToReturn'],[0,3])){
                    $data[$key]['is7ToReturn'] = 0;
                }else{
                    $data[$key]['is7ToReturn'] = 1;
                }
            }
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    /**
     * 查询商品详情
     */
    public function getSkuDetailGoods($sku = 2297112){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopGoodsGetSkuDetailInfoRequest');
        $req = new \VopGoodsGetSkuDetailInfoRequest();
        $req->setSkuId($sku);
        $req->setQueryExtSet(1);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_goods_getSkuDetailInfo_responce']['openRpcResult'];
        if($result['resultCode'] === '0000' && !empty($result['result'])){
            $data = $result['result'];
            $data['imagePath'] = $this->picUrlN0 . $data['imagePath'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    /**
     * 根据分类id查询分类信息
     */
    public function getCategoryInfoGoods($cid = ''){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopGoodsGetCategoryInfoListRequest');
        $req = new \VopGoodsGetCategoryInfoListRequest();
        $req->setCategoryId($cid);
        $resp = $vop->execute($req, $vop->accessToken);
        $result = $resp['jingdong_vop_goods_getCategoryInfoList_responce']['openRpcResult'];
        if($result['resultCode'] === '0000'){
            $data = $result['result'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 查询查询商品区域购买限制
     */
    public function checkAreaLimitGoods($skuArr = null,$address = null){
        $skuArr = implode(',',$skuArr);
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopGoodsCheckAreaLimitListRequest');
        $req = new \VopGoodsCheckAreaLimitListRequest();
        $req->setSkuId($skuArr);
        $req->setProvinceId($address['province']);
        $req->setCityId($address['city']);
        $req->setCountyId($address['county']);
        $req->setTownId($address['town']);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_goods_checkAreaLimitList_responce']['openRpcResult'];
        if($result['resultCode'] === '0000'){
            $data = $result['result'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 查询商品售卖价
     */
    public function getSellPriceGoods($skuArr = null,$product = null){
        $skuArr = implode(',',$skuArr);
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopGoodsGetSellPriceRequest');
        $req = new \VopGoodsGetSellPriceRequest();
        $req->setSkuId($skuArr);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_goods_getSellPrice_responce']['openRpcResult'];
        if($result['resultCode'] === '0000' && !empty($result['result'])){
            $data = $result['result'];
            foreach($data as $key => $val){
                $sku = $val['skuId'];
                $data[$key]['skuId'] = (string)$val['skuId'];
                $data[$key]['wzSellPrice'] = empty($product["$sku"]['wz_price']) ? $val['jdPrice'] : $product["$sku"]['wz_price'];//售卖价
                $data[$key]['JDPrice'] = $val['jdPrice'];//京东售卖价
                $data[$key]['WzPrice'] = $val['salePrice'];//京东成本价
                if($val['salePrice'] > $product["$sku"]['price']){//京东成本价如果大于了苏鹰售卖价
                    $data[$key]['wzSellPrice'] = $val['jdPrice'];
                }
            }
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 查询同类商品
     */
    public function getSimilarSkuGoods($skuArr = null){
        $skuArr = implode(',',$skuArr);
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopGoodsGetSimilarSkuListRequest');
        $req = new \VopGoodsGetSimilarSkuListRequest();
        $req->setSkuId($skuArr);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_goods_getSimilarSkuList_responce']['openRpcResult'];
        if($result['resultCode'] === '0000' && !empty($result['result'])){
            $data = $result['result'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    /**
     * 查询商品图片
    */
    public function getSkuImageGoods($skuArr = null){
        $skuArr = implode(',',$skuArr);
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopGoodsGetSkuImageListRequest');
        $req = new \VopGoodsGetSkuImageListRequest();
        $req->setSkuId($skuArr);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_goods_getSkuImageList_responce']['openRpcResult'];
        if($result['resultCode'] === '0000' && !empty($result['result'])){
            $data = $result['result'];
            $pics = [];
            $sort = [];
            foreach ($data as $val) {
                $sku = (string)$val['skuId'];
                foreach($val['skuImageList'] as $v){
                    $pics[$sku][$v['orderSort']] = $this->picUrlN0 . $v['shortPath'];
                }
                $pics[$sku] = implode(',',$pics[$sku]);
            }
            return ['code' => 200,'data' => $pics,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 查询商品运费
    */
    public function freightQueryOpen($freigtArr = null){
        $freightQueryOpenReq = [
            'paymentType' => 4,
            'skuInfoList' => $freigtArr['sku'],
            'areaInfo'      => [
                'provinceId'=> $freigtArr['province'],
                'cityId'    => $freigtArr['city'],
                'countyId'  => $freigtArr['county'],
                'townId'    => $freigtArr['town'],
            ]
        ];
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken($freightQueryOpenReq);

        import('JdVop.jd.request.VopOrderQuerySkuFreightRequest');
        $req = new \VopOrderQuerySkuFreightRequest();
        $req->setFreightQueryOpenReq($freightQueryOpenReq);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_order_querySkuFreight_responce']['openRpcResult'];
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }


    //VOP--订单API
    /**
     * 提交订单（预占库存）
     */
    public function submitOrderOpen($orderArr){
        $productList = json_decode($orderArr['sku'],true);
        $skuInfoList = [];
        foreach($productList as  $val){
            $skuInfoList[] = [
                'skuNeedGift'=>$val['bNeedGift'],
                'skuNum'=> (int)$val['num'],
                'skuId'=> $val['skuId'],
                'skuUnitPrice'=>$val['price']
            ];
        }
        $submitOrderOpenReq = [
            'remark' => $orderArr['remark'],
            'submitStateType' => 0,//是否预占库存，0是预占库存，1是不预占库存，直接进入生产
            'thirdOrderId'    => $orderArr['thirdOrder'],//第三方订单号，必须在100字符以内
            'skuInfoList'     => $skuInfoList,//商品信息
            'paymentInfo' => [//支付信息
                'paymentType' => 4,//4预存款
            ],
            'consigneeInfo' => [//订单收货地址信息
                'consigneeName'       => $orderArr['name'],
                'consigneeProvinceId' => $orderArr['province'],
                'consigneeCityId'     => $orderArr['city'],
                'consigneeCountyId'   => $orderArr['county'],
                'consigneeTownId'     => $orderArr['town'],
                'consigneeAddress'    => $orderArr['address'],
                'consigneeZip'        => '100000',
                'consigneeMobile'     => $orderArr['mobile'],

            ],
            'invoiceInfo' => [
                'invoiceCompanyName'   => '江苏苏鹰电子商务有限公司',//发票抬头
                'invoiceType'          => '2',//发票类型（2增值税专用发票；3 电子票） 当发票类型为2时，开票方式只支持2集中开票
                'invoiceSelectedTitle' => '5',//	发票类型：4：个人，5：单位
                'invoiceContentType'   => '1',//1:明细，100：大类 备注:若增值税专用发票则只能选1 明细
                'invoiceName'          => '张玉磊',//收票人姓名
                'invoicePhone'         => '15996395211',//收票人手机号
                'invoiceProvinceId'    => '12',
                'invoiceCityId'        => '904',
                'invoiceCountyId'      => '3379',
                'invoiceTownId'        => '62183',
                'invoiceAddress'       => '新华汇A2栋3楼',
                'invoiceRegCompanyName'=> '江苏苏鹰电子商务有限公司',
                'invoiceRegCode'       => '91320118MA1X6LKB65',
                'invoiceRegAddress'    => '江苏省南京市高淳区漆桥镇河滨路1号',
                'invoiceRegPhone'      => '025-89669712',
                'invoiceRegBank'       => '招商银行股份有限公司南京鼓楼支行',
                'invoiceRegBankAccount'=> '125909516810301',
                'invoicePutType'       => '2',//
            ],
        ];
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();    

        import('JdVop.jd.request.VopOrderSubmitOrderRequest');
        $req = new \VopOrderSubmitOrderRequest();
        $req->setSubmitOrderOpenReq($submitOrderOpenReq);
        $resp = $vop->execute($req, $vop->accessToken);
        file_put_contents('Runtime/jd_log/jd' . date('Y-m-d') . '.log','请求地址：submitOrderOpen ,请求数据：' . json_encode($submitOrderOpenReq) . ',接收数据：' . json_encode($resp, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

        $result = $resp['jingdong_vop_order_submitOrder_responce']['vopOrderRpcResult'];
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    /**
     * 确认订单
     */
    public function confirmOrderOpen($orderId){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopOrderConfirmOrderRequest');
        $req = new \VopOrderConfirmOrderRequest();
        $req->setThirdOrderId($orderId['ordernum']);
        $req->setJdOrderId($orderId['wz_orderid']);
        $resp = $vop->execute($req, $vop->accessToken);

        file_put_contents('Runtime/jd_log/jd' . date('Y-m-d') . '.log','请求地址：confirmOrderOpen,接收数据：' . json_encode($resp, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);

        $result = $resp['jingdong_vop_order_confirmOrder_responce']['openRpcResult'];
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            return ['result' => 0,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['result' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    /**
     * 批量校验订单是否可取消
     */
    public function CheckCancelOrderOpen($JdOrder = null){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopOrderBatchCheckCancelOrderRequest');
        $req = new \VopOrderBatchCheckCancelOrderRequest();
        $req->setJdOrderId($JdOrder);
        $resp = $vop->execute($req, $vop->accessToken);
        
        $result = $resp['jingdong_vop_order_batchCheckCancelOrder_responce']['openRpcResult'];
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            return ['result' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['result' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    /**
     * 取消订单
     */
    public function cancelOrderOpen($orderId = null){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopOrderCancelOrderRequest');
        $req = new \VopOrderCancelOrderRequest();
        // $req->setThirdOrderId($orderId['thirdOrder']);
        // $req->setJdOrderId($orderId['jdOrder']);
        $req->setThirdOrderId($orderId['thirdOrder']);
        $req->setJdOrderId($orderId['jdOrder']);
        $req->setCancelReason("取消订单");
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_order_cancelOrder_responce']['openRpcResult'];
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            return ['result' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['result' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    /**
     * 订单确认收货
     */
    public function confirmReceiveOrderOpen($orderId = null){
        $orderDetail = $this->queryOrderOpen($orderId);
        if($orderDetail['code'] == 300){
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
        $orderDetail = $orderDetail['data'];
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopOrderConfirmReceiveByOrderRequest');
        $req = new \VopOrderConfirmReceiveByOrderRequest();

        if(empty($orderDetail[$orderId['wzOrderId']]['childJdOrderIdList'])){
            $req->setJdOrderId($orderId['wzOrderId']);//京东订单号
            $resp = $vop->execute($req, $vop->accessToken);
            $result = $resp['jingdong_vop_order_confirmReceiveByOrder_responce']['openRpcResult'];
        }else{
            foreach($orderDetail[$orderId['wzOrderId']]['childJdOrderIdList'] as $val){
                $req->setJdOrderId($val);//京东订单号
                $resp = $vop->execute($req, $vop->accessToken);
                $result = $resp['jingdong_vop_order_confirmReceiveByOrder_responce']['openRpcResult'];
            }
        }
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 查询订单详情
     */
    public function queryOrderOpen($orderId = null){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopOrderQueryOrderDetailRequest');
        $req = new \VopOrderQueryOrderDetailRequest();
        $req->setJdOrderId($orderId['wzOrderId']);//京东订单号
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_order_queryOrderDetail_responce']['openRpcResult'];
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            $orderDetail = [];
            foreach($data as $key => $val){
                $jdOrderId = (string)$val['jdOrderId'];
                $orderDetail[$jdOrderId] = $val;
            }
            return ['code' => 200,'data' => $orderDetail,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 查询配送信息
     */
    public function deliveryInfoQueryOpen($orderId = null){
        $orderDetail = $this->queryOrderOpen($orderId);
        if($orderDetail['code'] == 300){
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
        $orderDetail = $orderDetail['data'];
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopOrderQueryDeliveryInfoRequest');
        $req = new \VopOrderQueryDeliveryInfoRequest();
        $deliveryDate = [];
        if(empty($orderDetail[$orderId['wzOrderId']]['childJdOrderIdList'])){
            $req->setJdOrderId($orderId['wzOrderId']);//京东订单号
            $resp = $vop->execute($req, $vop->accessToken);
            $result = $resp['jingdong_vop_order_queryDeliveryInfo_responce']['openRpcResult']['result'];
            $skuStr = implode(',',array_column($orderDetail[$orderId['wzOrderId']]['skuInfoList'],'skuId'));
            $deliveryDate['orderTrack'][$result['logisticInfoList'][0]['deliveryOrderId']][$skuStr] = $result['trackInfoList'];
        }else{
            foreach($orderDetail[$orderId['wzOrderId']]['childJdOrderIdList'] as $val){
                $req->setJdOrderId($val);//京东订单号
                $resp = $vop->execute($req, $vop->accessToken);
                $result = $resp['jingdong_vop_order_queryDeliveryInfo_responce']['openRpcResult']['result'];
                $jdOrderId = (string)$val;
                $skuStr = implode(',',array_column($orderDetail[$jdOrderId]['skuInfoList'],'skuId'));
                $deliveryDate['orderTrack'][$result['logisticInfoList'][0]['deliveryOrderId']][$skuStr] = $result['trackInfoList'];
            }
        }
        if(!empty($deliveryDate)){
            return ['code' => 200,'data' => $deliveryDate,'detail'=>'获取成功'];
        }else{
            return ['code' => 300,'data' => [],'detail'=>'暂无物流信息'];
        }
    }

    //VOP--库存API
    public function getStockByIdGoods($skuNums = null,$address = null){
        $getStockByIdGoodsReq = [
            'skuNumInfoList' => $skuNums,
            'areaInfo'      => [
                'provinceId'=> $address['province'],
                'cityId'    => $address['city'],
                'countyId'  => $address['county'],
                'townId'    => $address['town'],
            ]
        ];
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopGoodsGetNewStockByIdRequest');
        $req = new \VopGoodsGetNewStockByIdRequest();
        $req->setGetStockByIdGoodsReq($getStockByIdGoodsReq);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_goods_getNewStockById_responce']['openRpcResult'];
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            if($result['resultCode'] == '2004'){
                $dealResult = $this->dealGetStockByIdGoods($skuNums,$address,$result['resultMessage']);
                if($dealResult['code'] == 200){
                    return ['code' => 200,'data' => $dealResult['data'],'detail'=>$dealResult['detail']];
                }else{
                    return ['code' => 300,'data' => [],'detail'=>$dealResult['detail']];
                }
            }else{
                return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
            }
        }
    }
    //VOP--账户查询接口
    /**
     * 查询账户余额
     */
    public function checkAccountBalanceOpen(){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopOrderCheckAccountBalanceRequest');
        $req = new \VopOrderCheckAccountBalanceRequest();
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_order_checkAccountBalance_responce']['openRpcResult'];
        if($result['resultCode'] == '0000'){
            return ['code'=>200,'message'=>'获取成功','data'=>$result['result']['balanceInfo']['remainLimit']];
        }else{
            return ['code'=>300,'message'=>'系统开小差了~'];
        }
    }
    /**
     * 查询余额变动明细
     */
    public function checkBalanceChangeInfoOpen(){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopOrderCheckBalanceChangeInfoRequest');
        $req = new \VopOrderCheckBalanceChangeInfoRequest();
        $req->setStartDate('2022-04-01 00:00:00');
        $req->setJdOrderId();
        $req->setPageSize(20);
        $req->setEndDate('2022-11-01 00:00:00');
        $req->setPageIndex(1);

        $resp = $vop->execute($req, $vop->accessToken);
        print_r($resp);die();
        
    }

    //VOP--售后API
    /**
     * 申请售后
     */
    public function applyAfterSaleOpen($afterData){
        $customerInfoVo = json_decode($afterData['asCustomerDto'],true);//客户信息
        $pickupWare = json_decode($afterData['asPickwareDto'],true);//取件信息
        $returnWare = json_decode($afterData['asReturnwareDto'],true);
        $detailInfo = json_decode($afterData['asDetailDto'],true);
        $productName = M('mall_product')->where(['skuid'=>$detailInfo['skuId']])->getField('name');
        $applyAfterSaleOpenReq = [
            'thirdApplyId' => $afterData['csnum'],
            'isHasInvoice' => false,
            'applyInfoItemOpenReqList' => [
                [
                    'customerExpect' => $afterData['customerExpect'],//客户期望售后类型。10退货，20换货，30维修
                    'wareDescInfoOpenReq' => [
                        'questionDesc'          => $afterData['questionDesc'],//问题描述
                        'questionPic'           => $afterData['questionPic'],//问题描述图片，最多2000字符，支持多张图片，用逗号分隔（英文逗号）
                        'packageDesc'           => "10",//包装描述：0 无包装 10 包装完整 20 包装破损
                    ],
                    'wareDetailInfoOpenReq' => [
                        'wareId'     => $detailInfo['skuId'],//商品编号
                        'mainWareId' => $detailInfo['skuId'],//主商品编号
                        'wareName'   => $productName,//商品名称
                        'wareNum'    => $detailInfo['skuNum'],//商品申请数量
                        'wareType'   => "10",
                    ],
                ]
            ],
            'orderId' => $afterData['wzOrderId'],
            'customerInfoVo' => [
                'customerName'        => $customerInfoVo['customerContactName'],//用户名
                'customerMobilePhone' => $customerInfoVo['customerTel'],//联系人手机号
                'customerContactName' => $customerInfoVo['customerContactName'],//联系人
            ],
            'pickupWareInfoOpenReq' => [
                'pickWareType'    => $pickupWare['pickwareType'],//取件方式 4上门取件 7客户送货， 40客户发货
                'pickWareProvince'=> $pickupWare['pickwareProvince'],//取件 省（12）
                'pickWareCounty'  => $pickupWare['pickwareCity'],//取件 县（904）
                'pickWareCity'    => $pickupWare['pickwareCounty'],//取件 市（3379）
                'pickWareVillage' => $pickupWare['pickwareVillage'],//取件 乡镇（62183）
                'pickWareAddress' => $pickupWare['pickwareAddress'],//取件街道地址
                'reserveDateBegin'=> '',//预约取件开始时间
                'reserveDateEnd'  => '',//预约取件结束时间
            ],
            'returnWareInfoOpenReq' => [
                'returnWareType'    => $returnWare['returnwareType'],//返件方式。10自营配送，20第三方配送
                'returnWareProvince'=> $returnWare['returnwareProvince'],	//返件省
                'returnWareCity'	=> $returnWare['returnwareCity'],	//返件市
                'returnWareCountry'	=> $returnWare['returnwareCounty'],	//返件县
                'returnWareVillage'	=> $returnWare['returnwareVillage'],	//返件乡镇
                'returnWareAddress'	=> $returnWare['returnwareAddress']    //返件街道地址
            ]
        ];
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopAfsCreateAfsApplyRequest');
        $req = new \VopAfsCreateAfsApplyRequest();
        $req->setApplyAfterSaleOpenReq($applyAfterSaleOpenReq);
        $resp = $vop->execute($req, $vop->accessToken);
        file_put_contents('Runtime/jd_log/jd' . date('Y-m-d') . '.log','请求地址：applyAfterSaleOpen,请求数据：' . json_encode($applyAfterSaleOpenReq) . '接收数据：' . json_encode($resp, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        if($resp['jingdong_vop_afs_createAfsApply_responce']['openRpcResult']['result'] == true){
            return ['code'=>200,'message'=>'提交成功'];
        }else{
            return ['code'=>300,'message'=>'提交失败'];
        }
    }

    /**
     * 填写运单信息
     */
    public function updateAfterSaleWayBillOpen(){
        $updateAfterSaleWayBillOpenReq = [
            'thirdApplyId' => '',
            'waybillInfoVoOpenReqList' => [
                'deliverDate' => '',
                'wareNum' => '',
                'expressCode' => '',
                'wareId' => '',
                'wareType' => '',
                'expressCompany' => '',
                'freightMoney' => '',
            ],
            'orderId' => '',
        ];
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopAfsUpdateSendInfoRequest');
        $req = new \VopAfsUpdateSendInfoRequest();
        $req->setUpdateAfterSaleWayBillOpenReq($updateAfterSaleWayBillOpenReq);
        $resp = $vop->execute($req, $vop->accessToken);
        var_dump($resp);die();
    }

    /**
     * 取消售后
     */
    public function cancelAfterSaleApplyOpen(){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopAfsCancelAfsApplyRequest');
        $req = new \VopAfsCancelAfsApplyRequest();
        $req->setThirdApplyId( );
        $req->setRemark('取消');
        $req->setOrderId( );
        $resp = $vop->execute($req, $vop->accessToken);
        
        if($resp['jingdong_vop_order_queryDeliveryInfo_response']['openRpcResult']['resultCode'] == '0000'){
            $data = $resp['jingdong_vop_order_queryDeliveryInfo_response']['openRpcResult']['result'];
            return ['code' => 200,'data' => $data];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    
    /**
     * 批量查询订单下商品售后权益
     */
    public function queryAfterSaleAttributesOpen($orderId = null){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopAfsGetGoodsAttributesRequest');
        $req = new \VopAfsGetGoodsAttributesRequest();
        $req->setWareId($orderId['skuId']);
        $req->setOrderId($orderId['wzOrderId']);
        $resp = $vop->execute($req, $vop->accessToken);
        $result = $resp['jingdong_vop_afs_getGoodsAttributes_responce']['openRpcResult'];

        if($result['resultCode'] == '0000'){
            $data = $result['result'][0]['customerExpect'];
            $customerExpect = [];
            $customerExpectName = [10=>'退货',20=>'换货',30=>'维修'];
            foreach($data as $val){
                $customerExpect[] = [
                    'code' => $val,
                    'name' => $customerExpectName[$val]
                ];
            }
            return ['code' => 200,'data' => $customerExpect,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }
    /**
     * 查询售后概要
     */
    public function queryAfterSaleOutlineOpen($orderId = ''){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopAfsGetAfsOutlineRequest');
        $req = new \VopAfsGetAfsOutlineRequest();
        $req->setOrderId($orderId);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_afs_getAfsOutline_responce']['openRpcResult'];
        if($result['resultCode'] == '0000'){
            $data = $result['result'];
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    //VOP 消息API
    /**
     * 消息查询接口
     */
    public function queryTransByVopNormal($type = 6){
        $type = (int)$type;
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();

        import('JdVop.jd.request.VopMessageQueryTransByVopNormalRequest');
        $req = new \VopMessageQueryTransByVopNormalRequest();
        $req->setType([$type]);//消息类型（2-商品价格变更，4-商品上下架变更，5-订单已妥投，6-添加、删除商品池内商品，16-商品介绍及规格参数变更）
        $req->setReadType(2);
        $resp = $vop->execute($req, $vop->accessToken);

        $result = $resp['jingdong_vop_message_queryTransByVopNormal_responce']['openRpcResult'];
        if($result['resultCode'] == '0000' && !empty($result['result'])){
            $Tobeprocessed = $result['result'];
            $data = [];
            foreach($Tobeprocessed as $key => $val){
                $data[] = [
                    'result' => json_decode($val['content'],true),
                    'id'     => $val['id'],
                    'time'   => date('Y-m-d H:i:s',$val['created'] / 1000),
                    'type'   => $val['type']
                ]; 
            }
            return ['code' => 200,'data' => $data,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 消息删除接口
     */
    public function deleteClientMsgByIdList($id = null){
        import('JdVop.jd.JdClient');
        $vop = new \JdClient();
        $vop->appKey = $this->appKey;
        $vop->appSecret = $this->appSecret;
        $vop->accessToken = $this->getAccessToken();
        import('JdVop.jd.request.VopMessageDeleteClientMsgByIdListRequest');
        $req = new \VopMessageDeleteClientMsgByIdListRequest();
        $req->setId($id);
        $resp = $vop->execute($req, $vop->accessToken);
        $result = $resp['jingdong_vop_message_deleteClientMsgByIdList_responce']['vopOrderRpcResult'];
        if($result['resultCode'] == '0000'){
            return ['code' => 200,'data' => $result,'detail'=>$result['resultMessage']];
        }else{
            return ['code' => 300,'data' => [],'detail'=>$result['resultMessage']];
        }
    }

    /**
     * 处理京东接口数据
     */
    public function dealGetStockByIdGoods($skuNums,$address = null,$msg){
        $sku = strstr($msg,'不在用户商品池，请联系商务经理',true);
        $sku = explode(',',$sku);
        foreach($skuNums as $key => $val){
            if(in_array($val['skuId'],$sku)){
                unset($skuNums[$key]);
            }
        }
        $skuNums = array_values($skuNums);
        $result = $this->getStockByIdGoods($skuNums,$address);
        foreach($sku as $val){
            $result['data'][] = [
                'remainNum' => -1,
                'stockStateType' => 34,
                'stockStateDesc' => '无货',
                'skuId' => $val
            ];
        }
        return $result;
    }
}