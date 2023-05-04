<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/6
 * Time: 19:45
 */

namespace Admin\Controller;
use Org\Util\Response;


class NewBannerController extends CommonController {
    public function index(){
        $list   = $this->lists('Banner');
        $totalNum = getMallViewsNum();
        foreach ($list as $k => $v) {
            $list[$k]['images'] = format_img($v['images'], WEB_URL_IMG_UP);
            $list[$k]['view_rate'] = round(($v['views'] / $totalNum) * 100, 2) . '%';
            $banner_name = M('banner_position')->field('name')->where('id = ' . $v['ad_position_id'])->find();
            $list[$k]['banner_name'] = $banner_name['name'];
        }
        $this->assign('_list', $list);
        $this->meta_title = '用户信息';
        $this->display();
    }

    /**
     * 广告位
     * @return mixed
     */
    public function index_position(){
        $map = ['status'=>['gt',-1]];
        $list = $this->lists('BannerPosition',$map);
        $this->assign('_list', $list);
        $this->display();
    }

    /**
     * 添加广告位
     */
    public function add_position(){
        if(IS_POST){
            $model = D('BannerPosition');
            $data = $model->create();
            if($data){
                $model->create_time = time();
                if(false !== $model->add()){
                    $this->success('新增成功！', U('index_position'));
                }else{
                    $error = $model->getError();
                    $this->error(empty($error) ? '未知错误！' : $error);
                }
            }else{
                $this->error($model->getError());
            }
        }else{
            $this->display();
        }
    }

    public function add(){
        if(IS_POST){
            $model = D('Banner');
            $model->create();
            if(false !== $model->add()){
                $this->success('新增成功',U('index'));
            }else{
                $error = $model->getError();
                $this->error(empty($error)?'未知错误!':$error);
            }
        }else{
            $this->assign('position',M('BannerPosition')->where(['status'=>1])->select());
            $this->display();
        }
    }

    public function changeStatus($method=null,$model='Banner'){
        $id = array_unique((array)I('id',0));
        $id = is_array($id) ? implode(',',$id) : $id;
        if ( empty($id) ) {
            $this->error('请选择要操作的数据!');
        }
        $map['id'] =   array('in',$id);
        switch ( strtolower($method) ){
            case 'forbid':
                $this->forbid($model, $map );
                break;
            case 'resume':
                $this->resume($model, $map );
                break;
            case 'delete':
                $this->delete($model, $map );
                break;
            default:
                $this->error('参数非法');
        }
    }


    //编辑广告界面
    public function edit()
    {
        $id = I('id');
        if (empty($id)) {
            $this->error('请选择要操作的数据!');
        }

        $info = M('Banner')->where(['id' => $id])->find();
        if (empty($info)) {
            $this->error('请选择要操作的数据!');
        }

        $info['images'] = format_img($info['images'], WEB_URL_IMG_UP);
        $this->assign('info', $info);
//        echo '<pre/>';
//        print_r($info);
//        exit;

        $this->assign('position', M('BannerPosition')->where(['status' => 1])->select());
        $this->display();
    }

    //编辑操作保存
    public function update()
    {
        $model = D('Banner');
        $model->create();

        $id = $model->id;
        if (empty($id)) {
            $this->error('请选择要操作的数据!');
        }

        if (false !== $model->save()) {
            $this->success('编辑成功', U('index'));
        } else {
            $error = $model->getError();
            $this->error(empty($error) ? '未知错误!' : $error);
        }
    }

    /**
     * 编辑广告位
     */
    public function edit_position()
    {
        $model = D('BannerPosition');
        if (IS_POST) {
            $model->create();

            $id = $model->id;
            if (empty($id)) {
                $this->error('请选择要操作的数据!');
            }

            $model->update_time = time();
            if (false !== $model->save()) {
                $this->success('编辑成功', U('index_position'));
            } else {
                $error = $model->getError();
                $this->error(empty($error) ? '未知错误!' : $error);
            }

        } else {
            $id = I('id');
            if (empty($id)) {
                $this->error('请选择要操作的数据!');
            }

            $info = $model->where(['id' => $id])->find();
            if (empty($info)) {
                $this->error('请选择要操作的数据!');
            }
            $this->assign('info', $info);

            $this->display();
        }
    }

    /**
     * 查看弹框/商城banner/人事邦banner点击量
     */
    public function viewPage()
    {
        $rid = (int)I('rid', 0);
        $source = (int)I('source', 0);
        $this->assign('id', $rid);
        $this->assign('source', $source);
        $this->display();
    }

    /**
     * 浏览量列表
     */
    public function getViewList()
    {
        $rid = (int)I('rid', 0);
        $source = I('source', -1);
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $etype = I('etype', -1);

        $where = [
            'data_id' => $rid,
            'source' => $source,
        ];
        if (!empty($startDate)) {
            $where['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($etype, [0, 1, 2]))
            $where['etype'] = $etype;

        $model = M('banner_view');
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
        }

        return Response::mjson($list, $count);
    }

    /**
     * 获取点击率
     */
    public function getRate()
    {
        $rid = (int)I('rid', 0);
        $source = (int)I('source', 0);
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');

        $where = [
            'data_id' => $rid,
            'source' => $source,
        ];
        $mallViewWhere = [];
        $viewWhere = [];
        if (!empty($startDate)) {
            $where['create_date'][] = ['egt', $startDate . ' 00:00:00'];
            $mallViewWhere['create_date'][] = ['egt', $startDate . ' 00:00:00'];
            $viewWhere['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['create_date'][] = ['elt', $endDate . ' 23:59:59'];
            $mallViewWhere['create_date'][] = ['elt', $endDate . ' 23:59:59'];
            $viewWhere['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }

        $bannerViews = (array)M('banner_view')->where($where)->group('etype')->field('etype,count(1) as num')->select();
        $bannerList = [0, 0, 0];
        foreach ($bannerViews as $v) {
            switch ($v['etype']) {
                case 0://小程序
                    $bannerList[0] = $v['num'];
                    break;
                case 1://Ios
                    $bannerList[1] = $v['num'];
                    break;
                case 2://Android
                    $bannerList[2] = $v['num'];
                    break;
            }
        }
        $wxBanner = $bannerList[0];
        $iosBanner = $bannerList[1];
        $androidBanner = $bannerList[2];

        switch ($source) {
            case 1://弹框
                $mallViews = getMallViews($mallViewWhere);
                $views = getViews($viewWhere);
                $wx = $mallViews[0] + $views[0];
                $ios = $mallViews[1] + $views[1];
                $android = $mallViews[2] + $views[2];
                break;
            case 2://苏鹰banner
                $mallViews = getMallViews($mallViewWhere);
                $wx = $mallViews[0];
                $ios = $mallViews[1];
                $android = $mallViews[2];
                break;
            case 3://人事邦banner
                $views = getViews($viewWhere);
                $wx = $views[0];
                $ios = $views[1];
                $android = $views[2];
                break;
            case 4://首页个人/专区菜单
                $mallViews = getMallViews($mallViewWhere);
                $wx = $mallViews[0];
                $ios = $mallViews[1];
                $android = $mallViews[2];
                break;
            default:
                $wx = 0;
                $ios = 0;
                $android = 0;
                break;
        }

        $arr = [
            'rate' => round((($wxBanner + $iosBanner + $androidBanner) / ($wx + $ios + $android)) * 100, 2) . '%',
            'wxRate' => round(($wxBanner / $wx) * 100, 2) . '%',
            'iosRate' => round(($iosBanner / $ios) * 100, 2) . '%',
            'androidRate' => round(($androidBanner / $android) * 100, 2) . '%',
        ];

        echo json_encode($arr);
        die;
    }
}