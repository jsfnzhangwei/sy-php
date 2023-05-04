<?php
/**
 * Created by phpStorm
 * User: mj
 * Date: 2019/08/16
 * Time: 16:10
 */

namespace Admin\Controller;

use Think\Controller;
use Org\Util\Response;
use Common\Model\ErrorModel;
use Common\Model\ActivityAlertModel;
use Common\Model\ActivityModel;


class SpecialController extends Controller
{
    /**
     * 弹框列表页
     */
    public function getAlertPage()
    {
        $this->display();
    }

    /**
     * 弹框列表
     */
    public function getAlertList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;

        $where = [];

        $totalNum1 = getMallViewsNum();
        $totalNum2 = getViewsNum();
        $totalNum = $totalNum1 + $totalNum2;

        $activityAlertModel = new ActivityAlertModel();
        $list = $activityAlertModel->getAlertList($where, $page, $limit);
        $count = $activityAlertModel->getAlertCount($where);
        $nowDate = date('Y-m-d');
        foreach ($list as $k => $v) {
            if ($v['end_date'] < $nowDate) {
                $date_status = '已结束';
            } else {
                if ($v['start_date'] > $nowDate) {
                    $date_status = '未开始';
                } else {
                    $date_status = '进行中';
                }
            }
            $list[$k]['date_status'] = $date_status;
            $list[$k]['view_rate'] = round(($v['views'] / $totalNum) * 100, 2) . '%';
        }

        return Response::mjson($list, $count);
    }

    /**
     * 新增/编辑弹框页
     */
    public function editAlert()
    {
        $rid = (int)I('rid', 0);
        if ($rid > 0) {
            $activityAlertModel = new ActivityAlertModel();
            $info = $activityAlertModel->getAlertInfo($rid);
        } else {
            $info = [
                'id' => 0,
                'start_date' => '',
                'end_date' => '',
                'pic' => '',
                'fix_pic' => '/dashengyun/Public/Admin/images/default_pic.png',
                'second' => '',
                'link_type' => 0,
                'link_url' => '',
            ];
        }
        $this->assign('info', $info);
        $this->display();
    }

    /**
     * 新增/编辑弹框
     */
    public function updateAlert()
    {
        $rid = (int)I('rid', 0);
        $pic = I('pic', '');
        $start_date = I('start_date', '');
        $end_date = I('end_date', '');
        $second = (int)I('second', 0);
        $link_type = (int)I('link_type', 0);
        $link_url = I('link_url', '');
        if (empty($pic)) {
            ErrorModel::picLose();
        }
        if (empty($start_date)) {
            ErrorModel::startDateLose();
        }
        if (empty($end_date)) {
            ErrorModel::endDateLose();
        }
        if ($end_date < date('Y-m-d')) {
            ErrorModel::endDateError();
        }
        if ($start_date > $end_date) {
            ErrorModel::dateCompare();
        }
        if (!is_numeric($second) || $second == 0) {
            ErrorModel::waitError();
        }
        if ($second > 60) {
            return Response::show(300, '停留时长不得超过60秒');
        }
        $data = [
            'pic' => $pic,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'second' => $second,
            'link_type' => $link_type,
            'link_url' => $link_url,
        ];
        $activityAlertModel = new ActivityAlertModel();
        if ($rid > 0) {
            $res = $activityAlertModel->updateAlert($rid, $data);
        } else {
            $res = $activityAlertModel->insertAlert($data);
        }
        return Response::show($res['code'], $res['msg']);
    }

    /**
     * 删除弹框
     */
    public function delAlert()
    {
        $rids = I('rids', 0);

        if (empty($rids)) {
            ErrorModel::paramLose();
        }

        $activityAlertModel = new ActivityAlertModel();
        $res = $activityAlertModel->deleteAlert($rids);

        return Response::show($res['code'], $res['msg']);
    }

    /**
     * 活动列表页
     */
    public function getActivityPage()
    {
        $this->display();
    }

    /**
     * 活动列表
     */
    public function getActivityList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $title = I('title', '');
        $status = I('status', -1);
        $date = I('date', -1);
        $nowDate = date('Y-m-d');

        $where = [];
        if (!empty($title)) {
            $where['title'] = ['like', '%' . $title . '%'];
        }
        if (in_array($status, [0, 1])) {
            $where['status'] = $status;
        }
        switch ($date) {
            case 0://未开始
                $where['start_date'] = ['gt', $nowDate];
                break;
            case 1://进行中
                $where['start_date'] = ['elt', $nowDate];
                $where['end_date'] = ['egt', $nowDate];
                break;
            case 2://已结束
                $where['end_date'] = ['lt', $nowDate];
                break;
        }

        $field = 'id,title,start_date,end_date,status,views,create_date,location';

        $activityModel = new ActivityModel();
        $list = $activityModel->getActivityList($where, $page, $limit, '', $field);
        $count = $activityModel->getActivityCount($where);
        foreach ($list as $k => $v) {
            if ($v['end_date'] < $nowDate) {
                $date_status = '已结束';
            } else {
                if ($v['start_date'] > $nowDate) {
                    $date_status = '未开始';
                } else {
                    $date_status = '进行中';
                }
            }
            $list[$k]['date_status'] = $date_status;
            $list[$k]['act_status'] = ($v['status'] == 1) ? '启用' : '禁用';
        }

        return Response::mjson($list, $count);
    }

    /**
     * 新增/编辑活动页
     */
    public function editActivity()
    {
        $rid = (int)I('rid', 0);
        if ($rid > 0) {
            $activityModel = new ActivityModel();
            $info = $activityModel->getActivityInfoById($rid);

            if (empty($info['fix_bg_pic']))
                $info['fix_bg_pic'] = '/dashengyun/Public/Admin/images/default_pic.png';
            $oneBlock = $info['one_block'];
            foreach ($oneBlock as &$v) {
                if (empty($v['fix_pic']))
                    $v['fix_pic'] = '/dashengyun/Public/Admin/images/default_pic.png';
            }
            $info['one_block'] = $oneBlock;
            $twoBlock = $info['two_block'];
            foreach ($twoBlock as &$v) {
                if (empty($v['fix_pic']))
                    $v['fix_pic'] = '/dashengyun/Public/Admin/images/default_pic.png';
            }
            $info['two_block'] = $twoBlock;
            $fourBlock = $info['four_block'];
            foreach ($fourBlock as &$v) {
                if (empty($v['fix_pic']))
                    $v['fix_pic'] = '/dashengyun/Public/Admin/images/default_pic.png';
            }
            $info['four_block'] = $fourBlock;
        } else {
            $info = [
                'id' => 0,
                'start_date' => '',
                'end_date' => '',
                'title' => '',
                'btn_bg' => '',
                'fix_btn_bg' => '/dashengyun/Public/Admin/images/default_pic.png',
                'bg_pic' => '',
                'fix_bg_pic' => '/dashengyun/Public/Admin/images/default_pic.png',
                'pics' => [],
                'one_block' => [],
                'two_block' => [],
//                'three_block' => '',
//                'fix_three_block' => '/dashengyun/Public/Admin/images/default_pic.png',
                'four_block' => [],
                'status' => 0,
            ];
        }
        $this->assign('info', $info);
        $this->display();
    }

    /**
     * 新增/编辑活动
     */
    public function updateActivity()
    {
        $rid = (int)I('rid', 0);
        $title = trim(I('title', ''));
        $btn_bg = I('btn_bg', '');
        $bg_pic = I('bg_pic', '');
        $bg_height = (float)I('bg_height', 0);
        $bg_color = I('bg_color', '');
        $pics = I('pics', []);
        $picsSku = I('picsSku', []);
        $picBlock1 = I('picBlock1', []);
        $skuBlock1 = I('skuBlock1', []);
        $picBlock2 = I('picBlock2', []);
        $skuBlock2 = I('skuBlock2', []);
        $picBlock4 = I('picBlock4', []);
        $skuBlock4 = I('skuBlock4', []);
        $start_date = I('start_date', '');
        $end_date = I('end_date', '');
        $status = (int)I('status', 0);

        if (empty($bg_color))
            $bg_color = '#FFFFFF';
        $status = ($status == 1) ? 1 : 0;

        if (empty($title)) {
            ErrorModel::titleLose();
        }
        if (empty($btn_bg)) {
            return Response::show(300, '请先上传按钮背景图');
        }
        if (empty($start_date)) {
            ErrorModel::startDateLose();
        }
        if (empty($end_date)) {
            ErrorModel::endDateLose();
        }
        if ($end_date < date('Y-m-d')) {
            ErrorModel::endDateError();
        }
        if ($start_date > $end_date) {
            ErrorModel::dateCompare();
        }
        if ((count(array_filter($pics))) < (count(array_filter($picsSku)))) {
            return Response::show(300, '请先完善轮播图中的图片');
        }

        //验证banner中商品
        $skuDiff = checkGoods($picsSku);
        if (!empty($skuDiff)) {
            return Response::show(300, '轮播图中商品sku“' . implode('，', $skuDiff) . '”找不到或已下架、不可售');
        }
        //验证版块1中商品
        $skuDiff1 = checkGoods($skuBlock1);
        if (!empty($skuDiff1)) {
            return Response::show(300, '版块1中商品sku“' . implode('，', $skuDiff1) . '”找不到或已下架、不可售');
        }
        //验证版块2中商品
        $skuDiff2 = checkGoods($skuBlock2);
        if (!empty($skuDiff2)) {
            return Response::show(300, '版块2中商品sku“' . implode('，', $skuDiff2) . '”找不到或已下架、不可售');
        }
        //验证版块4中商品
        $skuDiff4 = checkGoods($skuBlock4);
        if (!empty($skuDiff4)) {
            return Response::show(300, '版块3中商品sku“' . implode('，', $skuDiff4) . '”找不到或已下架、不可售');
        }

        $picsNew = [];
        foreach ($pics as $k => $v) {
            if (!empty($v)) {
                $picsNew[] = [
                    'sku' => $picsSku[$k],
                    'pic' => $v,
                ];
            }
        }
        $blockArr1 = [];
        foreach ($skuBlock1 as $k => $v) {
            if (!empty($v)) {
                $blockArr1[] = [
                    'sku' => $v,
                    'pic' => $picBlock1[$k],
                ];
            }
        }
        if (count($blockArr1) > 0 && count($blockArr1) < 3) {
            return Response::show(300, '版块1一旦配置则至少有三个商品');
        }
        $blockArr2 = [];
        foreach ($skuBlock2 as $k => $v) {
            if (empty($picBlock2[$k])) {
                return Response::show(300, '请完善版块2中图片');
            }
            if (!empty($v)) {
                $blockArr2[] = [
                    'sku' => $v,
                    'pic' => $picBlock2[$k],
                ];
            }
        }
        $blockArr4 = [];
        foreach ($skuBlock4 as $k => $v) {
            if (!empty($v)) {
                $blockArr4[] = [
                    'sku' => $v,
                    'pic' => $picBlock4[$k],
                ];
            }
        }
        if (empty($blockArr4)) {
            return Response::show(300, '请先配置版块3-商品列表');
        }

        $data = [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'title' => $title,
            'btn_bg' => $btn_bg,
            'bg_pic' => $bg_pic,
            'bg_height' => $bg_height,
            'bg_color' => $bg_color,
            'pics' => json_encode($picsNew),
            'one_block' => json_encode($blockArr1),
            'two_block' => json_encode($blockArr2),
            'four_block' => json_encode($blockArr4),
            'status' => $status,
        ];
        $activityModel = new ActivityModel();
        if ($rid > 0) {
            $res = $activityModel->updateActivity($rid, $data);
        } else {
            $res = $activityModel->insertActivity($data);
        }
        return Response::show($res['code'], $res['msg']);
    }

    /**
     * 启用/禁用活动
     */
    public function dealActivity()
    {
        $rids = I('rids', 0);
        $status = I('status', 0);

        $status = ($status == 1) ? 1 : 0;

        if (empty($rids)) {
            ErrorModel::paramLose();
        }

        $activityModel = new ActivityModel();
        $res = $activityModel->updateActivityStatus($rids, $status);

        return Response::show($res['code'], $res['msg']);
    }

    /**
     * 查看浏览量
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
            'aid' => $rid,
        ];
        if (!empty($startDate)) {
            $where['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($etype, [0, 1, 2]))
            $where['etype'] = $etype;
        if (in_array($source, [0, 1, 2, 3]))
            $where['source'] = $source;

        $activityModel = new ActivityModel();
        $list = $activityModel->getViewList($where, $page, $limit);
        $count = $activityModel->getViewCount($where);
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
            switch ($v['source']) {
                case 1:
                    $source = '首页弹框';
                    break;
                case 2:
                    $source = '商城banner';
                    break;
                case 3:
                    $source = '人事邦banner';
                    break;
                default:
                    $source = '其他';
                    break;
            }
            $list[$k]['etype'] = $etype;
            $list[$k]['source'] = $source;
        }

        return Response::mjson($list, $count);
    }

}