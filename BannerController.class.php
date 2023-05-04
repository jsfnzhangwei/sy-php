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
use Org\Util\Upload;
class BannerController extends CommonController {
    //广告主界面
    public function banner_lis()
    {
        $this->display('Banner/banner_lis');
    }
    //广告主界面数据
    public function banner_lis_info()
    {
        $limit = I('limit');
        $pageIndex = I('pageIndex');
        $page = $pageIndex+1;
        $where['isshow'] = array('eq',1);
        $Banner = M('mall_banner');//实例化公告表
        $num =  $Banner
            ->count('id');

        $vo = $Banner
            ->order('time desc')
            ->where($where)
            ->page($page,$limit)
            ->field('
                pic,url,id
                ,case isshow when 1 then \'启用中\' when 2 then \'停用中\' end as isshow
                ,time
            ')
            ->select();

        return Response::mjson($vo,$num);

    }
    //删除广告
    public  function banner_del(){
        $ids = I('ids');
        $id = implode(',',$ids);
        $Anc = M('mall_banner');
        $where['id'] = array('in',$id);
        if($Anc->where($where)->delete())
            return Response::show(200,'操作成功');
        else return Response::show(200,'操作失败');
    }
    //添加广告界面
    public function banner_add(){
        $this->display('Banner/add');
    }
    //添加操作
    public function banner_add_do(){
        $url = I('url','');
        $pic = $_FILES['pic'];
        if(!empty($pic)){
            $Banner = M('mall_banner');
            $data['url'] = $url;
            $data['time'] = NOW;
            $data['isshow'] = 1;
            $data['pic'] = uploadfile($pic);
            if(empty($data['pic'])){
                return Response::show(400,'图片上传失败');
            }
            if($Banner->add($data))
                return Response::show(200,'新增成功');
            else return Response::show(400,'新增失败');
        }else return Response::show(400,'请选择图片');



    }
    //编辑广告界面
    public function banner_edit(){
        $id = I('id');
        $Banner = M('mall_banner');
        $where['id'] = array('eq',$id);
        $vo = $Banner->where($where)->select();

        $vo[0]['pic'] = format_img($vo[0]['pic'], IMG_VIEW);
        $this->assign('vo',$vo);
        $this->display('update');
    }
    //编辑操作保存
    public function banner_edit_do(){
        $Banner = M('mall_banner');
        $id = I('id');
        $where['id'] = array('eq',$id);
        $Banner->url = I('url');
        if(!empty($_FILES['pic'])) {
            $newpic = uploadfile($_FILES['pic']);
            if(empty($newpic)){
                return Response::show(400,'图片上传失败');
            }
            $Banner->pic = $newpic;
        }
        if($Banner->where($where)->save()!==false)
            return Response::show(200,'操作成功');
        else return Response::show(400,'操作失败');
    }


    //广告主界面
    public function active_lis()
    {
        $map = [];
        $list = $this->lists('banner_active', $map, '`id` asc');
        foreach ($list as $k => $v) {
            $list[$k]['images'] = format_img($v['images'], WEB_URL_IMG_UP);
        }
        $this->assign('list', $list);
        $this->meta_title = '活动专区列表';
        $this->display();
    }

    //编辑广告界面
    public function active_edit()
    {
        $model = D('banner_active');
        $id = I('id');
        if (empty($id)) {
            $this->error('请选择要操作的数据!');
        }

        $info = $model->where(['id' => $id])->find();
        if (empty($info)) {
            $this->error('请选择要操作的数据!');
        }

        if (IS_POST) {
            $model->create();
            if ($info['pid'] == 3) {
                $pros = $model->link_url;
                $pros = str_replace(' ', '', $pros);
                $pros = array_unique(array_filter(explode(',', $pros)));
                if (empty($pros)) {
                    $this->error('请填写商品sku!');
                }

                $goods = M('mall_product')
                    ->where(['skuid' => ['in', $pros], 'status' => 1, 'upanddown' => 1])
                    ->order('field(skuid,\'' . implode("','", $pros) . '\')')
                    ->field('skuid,name')
                    ->select();
                $skuids = array_column($goods, 'skuid');

                //获取数组中不同的元素
                $diff_skuids = empty($skuids) ? $pros : array_diff($pros, $skuids);
                if (!empty($diff_skuids)) {
                    $this->error('商品sku“' . implode('，', $diff_skuids) . '”找不到或已下架、不可售');
                }
                $model->link_url = implode(',', $skuids);
            }

            $model->update_time = NOW;
            //添加操作日志
            $admin_log = '编辑活动专区广告:' . $model->title;
            if (false !== $model->save()) {
                admin_log($admin_log, 1, 'dsy_banner_active:' . $id);
                $this->success('编辑成功', U('active_lis'));
            } else {
                admin_log($admin_log, 0, 'dsy_banner_active:' . $id);
                $error = $model->getError();
                $this->error(empty($error) ? '未知错误!' : $error);
            }
        } else {
            $info['images'] = format_img($info['images'], WEB_URL_IMG_UP);
            $this->assign('info', $info);

            $this->display();
        }
    }

    public function getNavPage()
    {
        $this->display();
    }

    public function getNavList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $type = I('type', 0);
        $status = I('status', -1);
        $hot = I('hot', -1);

        $where = [];
//        $where['link_type'] = ['neq', -1];
        if (in_array($type, [0, 1])) {
            $where['type'] = $type;
        }
        if (in_array($status, [0, 1])) {
            $where['status'] = $status;
        }
        if ($hot == 1) {
            $where['is_hot'] = $hot;
        }

        $model = M('mall_nav');
        $count = $model
            ->where($where)
            ->count();
        $list = [];
        if ($count > 0) {
            //总访问量
            $totalNum = getMallViewsNum();
            //列表
            $list = $model
                ->where($where)
                ->order('`type` ASC,`sort` ASC,`id` DESC')
                ->page($page, $limit)
                ->select();
            foreach ($list as $k => $v) {
                $list[$k]['img'] = format_img($v['img'], IMG_VIEW);
                if (empty($v['link_data'])) {
                    $list[$k]['link_data'] = '--';
                } else {
                    $list[$k]['link_data'] = getLinkTypeName($v['link_type']) . ': ' . $v['link_data'];
                }
                $list[$k]['view_rate'] = round(($v['views'] / $totalNum) * 100, 2) . '%';
            }
        }
        return Response::mjson($list, $count);
    }

    public function getNavInfo()
    {
        $rid = (int)I('rid', 0);
        $info = (array)M('mall_nav')
            ->where(['id' => $rid])
            ->find();
        if (empty($info)) {
            return Response::show(300, '该记录不存在');
        }
        $info['imgFix'] = format_img($info['img'], IMG_VIEW);
        return Response::json(200, '', $info);
    }

    public function editNav()
    {
        $rid = (int)I('rid', 0);
        $this->assign('rid', $rid);
        $this->display();
    }

    public function updateNav()
    {
        $rid = (int)I('rid', 0);
        $type = (int)I('type', 0);
        $title = I('title', '');
        $img = I('img', '');
        $link_type = (int)I('link_type', 0);
        $link_data = I('link_data', '');
        $is_hot = (int)I('is_hot', 0);
        $sort = (int)I('sort', 99);
        $status = (int)I('status', 0);

        if ($link_type == 1) {
            if (intval(M('mall_product')->where(['skuid' => $link_data, 'status' => 1, 'upanddown' => 1])->count()) == 0) {
                return Response::show(300, '该商品找不到或已下架、不可售，请检查该商品的sku编号！');
            }
        }

        $data = [
            'type' => $type,
            'title' => $title,
            'img' => $img,
            'link_type' => $link_type,
            'link_data' => $link_data,
            'is_hot' => $is_hot,
            'sort' => $sort,
            'status' => $status,
        ];
        if ($rid > 0) {
            $res = M('mall_nav')->where(['id' => $rid])->save($data);
        } else {
            $res = M('mall_nav')->add($data);
        }
        if ($res == false) {
            if ($rid > 0 && $res === 0) {
                return Response::show(200, '操作成功');
            }
            return Response::show(300, '操作失败');
        }
        return Response::show(200, '操作成功');
    }

    public function dealNav()
    {
        $rids = I('rids', 0);
        $key = I('key', "");
        $status = I('status', 0);

        $status = ($status == 1) ? 1 : 0;

        if (empty($rids)) {
            return Response::show(300, '缺少参数');
        }
        if (empty($key)) {
            return Response::show(300, '操作错误');
        }

        $result = M('mall_nav')
            ->where(['id' => ['in', $rids]])
            ->setField($key, $status);
        if ($key == "status") {
            $msg = ($status == 1) ? '启用' : '禁用';
        } else {
            $msg = ($status == 1) ? '设为热门' : '取消热门';
        }
        if ($result === false) {
            return Response::show(300, $msg . '失败');
        } elseif ($result === 0) {
            return Response::show(300, '已' . $msg);
        }
        return Response::show(200, $msg . '成功');
    }

    public function MessageNotification(){
        $this->display('messageNotification');
    }
    public function MessageNotificationData(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;

        $result = M('mall_shops_msg')->order('create_time desc')->page($page,$limit)->select();

        $num = M('mall_shops_msg')->count();

        return Response::mjson($result,$num);
    }
    public function MessageNotificationUpdate(){
        $id = I('id');
        $content = I('content');
        if(empty($content)){
            return Response::show(300,'头条通知不能为空，请输入');
        }
        $data['content'] = $content;
        $msg = '';
        if(empty($id)){
            $msg = '新增';
            $data['create_time'] = date('Y-m-d H:i:s',time());
            $result = M('mall_shops_msg')->add($data);
        }else{
            $msg = '编辑';
            $result = M('mall_shops_msg')->where(['id'=>$id])->save($data);
        }
        if($result !== false){
            return Response::show(200,$msg . '成功');
        }else{
            return Response::show(300,$msg . '失败');
        }
    }
    public function MessageNotificationStatus(){
        $id = I('ids');
        $status = I('status');
        if(empty($id)){
            return Response::show(300,'请选择要操作的头条通知');
        }

        $result = M('mall_shops_msg')->where(['id'=>['in',$id]])->save(['status'=>$status]);
        if($result !== false){
            return Response::show(200,'操作成功');
        }else{
            return Response::show(300,'操作失败');
        }
    }
    public function hotWords(){
        $words = M('config', 'sys_')->field('value')->where(['name'=>'SET_HOT_WORDS'])->find();
        $wordsArr = explode(',',$words['value']);
        $this->assign('info',$words['value']);
        $this->assign('wordsArr',$wordsArr);
        $this->display();
    }
    public function hotWordsUpdate(){
        $words = $_POST['value'];
        $model = M('config', 'sys_');
        $where = ['name' => 'SET_HOT_WORDS'];
        $words = str_replace(' ', '', $words);
        $words = array_unique(array_filter(explode(',', $words)));
        if (empty($words)) {
            return Response::show(300, '请填写热搜词');
        }
        if(count($words) > 8){
            return Response::show(300,'最多添加8个热搜词');
        }
        //添加操作日志
        $admin_log = '编辑苏鹰商城热搜词:' . implode(',', $words);
        $res = $model
            ->where($where)
            ->setField('value', implode(',', $words));
        if ($res == false) {
            admin_log($admin_log, 0, 'sys_config:SET_HOT_WORDS');
            $error = $model->getError();
            $error = empty($error) ? '编辑失败!' : $error;
            return Response::show(300, $error);
        }
        admin_log($admin_log, 1, 'sys_config:SET_HOT_WORDS');
        return Response::show(200, '编辑成功');
    }
}
