<?php
/**
 * Created by PhpStorm.
 * User: 苏鹰-马洁
 * Date: 2018/12/28
 * Time: 10:55
 */

namespace Admin\Controller;

use Org\Util\Response;
use Common\Model\ScoreModel;
use Common\Model\ScoreWithdrawModel;
use Common\Model\SignInModel;

class ScoreController extends CommonController
{
    //弃用
    public function index()
    {
        $score = new ScoreModel();
        //获取积分值
        $listAll = $score->getScoreAll();

        $list = [];
        foreach ($listAll as $v) {
            $list[$v['source_type']][] = $v;
        }
        $this->assign('list', $list);

        $this->display();
    }

    public function edit()
    {
        $valueArr = I('score_value', []);
        $statusArr = I('score_status', []);
        $typeArr = I('limit_type', []);
        $numArr = I('limit_num', []);
        if (empty($valueArr) && empty($statusArr)) {
            return Response::show('300', '请填写完整信息');
        }
        $scoreModel = new ScoreModel();
        foreach ($valueArr as $k => $v) {
            $save_data = [
                'score_value' => $v,
                'score_status' => isset($statusArr[$k]) ? 0 : 1,
                'limit_num' => isset($numArr[$k]) ? $numArr[$k] : 0,
                'limit_type' => isset($typeArr[$k]) ? $typeArr[$k] : 0,
            ];
            $res = $scoreModel->updateScoreValue($k, $save_data);
            if ($res['code'] != 200) {
                return Response::show(300, '设置失败');
            }
        }
        return Response::show(200, '设置成功');
    }

    public function userList()
    {
        $this->assign('stype', I('stype', ''));
        $this->display('user_list');
    }

    public function userListPage()
    {
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $scoreModel = new ScoreModel();

        //获取列表数据
        $source_type = I('stype', '');
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $mobile = I('mobile', '');

        if (!in_array($source_type, [1, 2])) {
            $source_type = 0;
        }
        //条件
        $map = [];
        if (in_array($source_type, [0, 1, 2])) {
            $map['source_type'] = $source_type;
        }
        if (!empty($startDate)) {
            $map['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $map['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (!empty($mobile)) {
            $map['phone'] = ['like', $mobile . '%'];
        }

        $count = $scoreModel->getUserCount($map);
        if ($count == 0) {
            $list = [];
        } else {
            $list = $scoreModel->getUserList($map, '', $page, $limit);
            foreach ($list as $k => $v) {
                $invite = $scoreModel->getUserInfo(['user_id' => $v['pid'], 'source_type' => $v['source_type']], '`phone`');
                if (empty($invite))
                    $list[$k]['pphone'] = '--';
                else
                    $list[$k]['pphone'] = $invite['phone'];
                $list[$k]['invite_count'] = $scoreModel->getUserCount(['pid' => $v['user_id'], 'source_type' => $v['source_type']]);
            }
        }

        return Response::mjson($list, $count);
    }

    public function inviteList()
    {
        $us_id = I('usid', 0);
        $this->assign('usid', $us_id);
        $this->display('invite_list');
    }

    public function inviteListPage()
    {
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $scoreModel = new ScoreModel();

        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $mobile = I('mobile', '');
        $us_id = I('usid', 0);
        $invite = $scoreModel->getUserInfo(['id' => $us_id], '`user_id`,`source_type`');
        if (empty($invite)) {
            return Response::mjson([], 0);
        }

        //条件
        $map = ['pid' => $invite['user_id'], 'source_type' => $invite['source_type']];
        if (!empty($startDate)) {
            $map['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $map['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (!empty($mobile)) {
            $map['phone'] = ['like', $mobile . '%'];
        }

        $count = $scoreModel->getUserCount($map);
        if ($count == 0) {
            $list = [];
        } else {
            $list = $scoreModel->getUserList($map, '', $page, $limit);
        }

        return Response::mjson($list, $count);
    }

    public function scoreList()
    {
        $this->assign('stype', I('stype', ''));
        $this->display('score_list');
    }

    public function scoreListPage()
    {
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $scoreModel = new ScoreModel();

        //获取列表数据
        $source_type = I('stype', '');
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $mobile = I('mobile', '');
        $type = I('type', '');

        if (!in_array($source_type, [1, 2])) {
            $source_type = 0;
        }
        //条件
        $map = [];
        if (in_array($source_type, [0, 1, 2])) {
            $map['source_type'] = $source_type;
        }
        if (!empty($startDate)) {
            $map['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $map['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (!empty($mobile)) {
            $user_ids = [-1];
            $invite = $scoreModel->getUserAll(['phone' => ['like', $mobile . '%'], 'source_type' => $source_type], '`user_id`');
            if (!empty($invite)) {
                $user_ids = array_column($invite, 'user_id');
            }
            $map['user_id'] = ['in', $user_ids];
        }

        $count = $scoreModel->getScoreRecordCount($map, $type);
        if ($count == 0) {
            $list = [];
        } else {
            $list = $scoreModel->getScoreRecord($map, $type, '', $page, $limit);
            foreach ($list as $k => $v) {
                $userInfo = $scoreModel->getUserInfo(['user_id' => $v['user_id'], 'source_type' => $v['source_type']], '`phone`');
                if (empty($userInfo))
                    $list[$k]['phone'] = '--';
                else
                    $list[$k]['phone'] = $userInfo['phone'];
            }
        }

        return Response::mjson($list, $count);
    }

    public function withdrawList()
    {
        $this->assign('stype', I('stype', ''));
        $this->display('withdraw_list');
    }

    public function withdrawListPage()
    {
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $scoreModel = new ScoreModel();
        $scoreWithdrawModel = new ScoreWithdrawModel();

        //获取列表数据
        $source_type = I('stype', '');
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $mobile = I('mobile', '');
        $status = I('type', -1);

        if (!in_array($source_type, [1, 2])) {
            $source_type = 0;
        }
        //条件
        $map = [];
        if (in_array($source_type, [0, 1, 2])) {
            $map['source_type'] = $source_type;
        }
        if (in_array($status, [0, 1, 2])) {
            $map['withdraw_status'] = $status;
        }
        if (!empty($startDate)) {
            $map['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $map['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (!empty($mobile)) {
            $user_ids = [-1];
            $invite = $scoreModel->getUserAll(['phone' => ['like', $mobile . '%'], 'source_type' => $source_type], '`user_id`');
            if (!empty($invite)) {
                $user_ids = array_column($invite, 'user_id');
            }
            $map['user_id'] = ['in', $user_ids];
        }

        $count = $scoreWithdrawModel->getCount($map);
        if ($count == 0) {
            $list = [];
        } else {
            $list = $scoreWithdrawModel->getList($map, '', $page, $limit);
            foreach ($list as $k => $v) {
                $userInfo = $scoreModel->getUserInfo(['user_id' => $v['user_id'], 'source_type' => $v['source_type']], '`phone`');
                if (empty($userInfo))
                    $list[$k]['phone'] = '--';
                else
                    $list[$k]['phone'] = $userInfo['phone'];
                //审核状态
                switch ($v['withdraw_status']) {
                    case 1:
                        $app_type = '已同意';
                        break;
                    case 2:
                        $app_type = '已拒绝';
                        break;
                    default:
                        $app_type = '待审核';
                        $list[$k]['reply_id'] = '';
                        break;
                }
                $list[$k]['status'] = $app_type;
            }
        }

        return Response::mjson($list, $count);
    }

    public function withdrawDeal()
    {
        $withdraw_id = I('wid', '');
        $status = I('status', '');
        $msg = I('msg', '');
        if (!is_numeric($withdraw_id) || $withdraw_id <= 0) {
            return Response::show(300, '缺少参数');
        }
        if (!in_array($status, [1, 2])) {
            return Response::show(300, '错误操作');
        }
//        if ($status == 2) {
//            if (empty($msg)) {
//                return Response::show(300, '请输入拒绝原由');
//            }
//        }

        $scoreWithdrawModel = new ScoreWithdrawModel();

        $info = $scoreWithdrawModel->getInfo(['withdraw_id' => $withdraw_id]);
        if (empty($info)) {
            return Response::show(300, '找不到申请记录');
        }
        if ($info['withdraw_status'] != 0) {
            return Response::show(300, '该申请已经审核');
        }

        $verifyWithdrawRes = $scoreWithdrawModel->verifyWithdraw($withdraw_id, $status, $msg);
        if ($verifyWithdrawRes['code'] != 201) {
            if ($verifyWithdrawRes['code'] != 200) {
                return Response::show(300, '审核失败');
            }
        }

        return Response::show(200, '审核成功');
    }

    public function signInList()
    {
        $this->assign('stype', I('stype', ''));
        $this->display('sign_in_list');
    }

    public function signInListPage()
    {
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $scoreModel = new ScoreModel();
        $signInModel = new SignInModel();

        //获取列表数据
        $source_type = I('stype', '');
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $mobile = I('mobile', '');

        if (!in_array($source_type, [1, 2])) {
            $source_type = 0;
        }
        //条件
        $map = [];
        if (in_array($source_type, [0, 1, 2])) {
            $map['source_type'] = $source_type;
        }
        if (!empty($startDate)) {
            $map['create_date'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $map['create_date'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (!empty($mobile)) {
            $user_ids = [-1];
            $invite = $scoreModel->getUserAll(['phone' => ['like', $mobile . '%'], 'source_type' => $source_type], '`user_id`');
            if (!empty($invite)) {
                $user_ids = array_column($invite, 'user_id');
            }
            $map['user_id'] = ['in', $user_ids];
        }

        $count = $signInModel->getSignInCount($map);
        if ($count == 0) {
            $list = [];
        } else {
            $list = $signInModel->getSignInList($map, '', $page, $limit);
            foreach ($list as $k => $v) {
                $userInfo = $scoreModel->getUserInfo(['user_id' => $v['user_id'], 'source_type' => $v['source_type']], '`phone`');
                if (empty($userInfo))
                    $list[$k]['phone'] = '--';
                else
                    $list[$k]['phone'] = $userInfo['phone'];
                //签到来源
                switch ($v['app']) {
                    case 1:
                        $app_type = 'IOS';
                        break;
                    case 2:
                        $app_type = '安卓';
                        break;
                    default:
                        $app_type = '小程序';
                        break;
                }
                $list[$k]['app'] = $app_type;
            }
        }

        return Response::mjson($list, $count);
    }
}