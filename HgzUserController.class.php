<?php
/**
 * Created by phpStorm
 * User: mj
 * Date: 2020/02/21
 * Time: 09:10
 */

namespace Admin\Controller;

use Think\Controller;
use Org\Util\Response;
use Common\Model\ErrorModel;


class HgzUserController extends Controller
{
    /**
     * 弹框列表
     */
    public function companyList()
    {
        $keyword = I('keyword', '');

        $where = [
            'review_status' => 1
        ];
        if (!empty($keyword)) {
            $where['corporate_name'] = ['like', '%' . $keyword . '%'];
        }

        $corporate = M('corporate', 'cxt_', 'db2');
        $list = $corporate
            ->where($where)
            ->order('CONVERT(LEFT(`corporate_name`,1) USING gbk) ASC,`corporate_id` ASC')
            ->field('`corporate_id`,`corporate_name`')
            ->select();

        return Response::json(200, '', $list);
    }

    /**
     * 弹框列表
     */
    public function getUserList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = (int)$_REQUEST['limit'];

        $keyword = I('keyword', '');
        $resource = I('resource', -1);
        $status = I('status', -1);

        $where = [];
        if (!empty($keyword)) {
            $whereOr = [
                'mobile' => ['like', '%' . $keyword . '%']
                , 'name' => ['like', '%' . $keyword . '%']
            ];
            $whereOr['_logic'] = 'or';
            $where['_complex'] = $whereOr;
        }
        if ($resource > -1) {
            $where['user_resource'] = $resource;
        }
        if ($status > -1) {
            $where['status'] = $status;
        }

        $user = M('user', 'cxt_', 'db2');
        $count = $user->where($where)->count();
        $list = [];
        if ($count > 0) {
            $list = $user->where($where)->page($page, $limit)->order('`user_id` DESC')->select();
            $scoreModel = M('score_get_record', 'cxt_', 'db2');
            foreach ($list as $k => $v) {
                $list[$k]['total_score'] = (int)$scoreModel->where(['user_id' => $v['user_id']])->sum('get_score_value');
            }
        }
        return Response::mjson($list, $count);
    }

    /**
     * 新增/编辑弹框页
     */
    public function editAlert()
    {
        $rid = (int)I('rid', 0);
        $activityAlertModel = new ActivityAlertModel();
        $info = $activityAlertModel->getAlertInfo($rid);
        return Response::json(200, '', $info);
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
     * 会员列表
     */
    public function userList()
    {
        $keyword = I('keyword', '');
        $startDate = I('sDate', '');
        $endDate = I('eDate', '');
        $startDateP = I('sDateC', '');
        $endDateP = I('eDateC', '');
        $overInfo = I('overInfo', '');

        $page = (int)$_REQUEST['pageNo'];
        $limit = (int)$_REQUEST['pageSize'];

        $where = [];
        if (!empty($keyword)) {
            $whereOr = [
                'u.mobile' => ['like', '%' . $keyword . '%']
                , 'u.name' => ['like', '%' . $keyword . '%']
            ];
            $whereOr['_logic'] = 'or';
            $where['_complex'][] = $whereOr;
        }
        if (!empty($startDate)) {
            $where['u.create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['u.create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($overInfo, [1, 2])) {
            if ($overInfo == 1) {
                $where['r.name'] = ['exp', '!=""'];
                $where['r.work_state'] = ['exp', 'IS NOT NULL'];
            } else {
                $whereOr = [
                    'r.name' => ['exp', '=""']
                    , 'r.work_state' => ['exp', 'IS NULL']
                ];
                $whereOr['_logic'] = 'or';
                $where['_complex'][] = $whereOr;
            }
        }

        $list = M('user', 'cxt_', 'db2')
            ->alias('u')
            ->join('LEFT JOIN `cxt_resume` r ON r.`user_id`=u.`user_id`')
            ->where($where)
            ->limit(($page - 1) * $limit, $limit)
            ->field('u.`user_id`,u.`name`,u.`mobile`,u.`create_time`,r.`name` resume_name,r.`work_state` resume_work')
            ->order('u.`create_time` DESC')
            ->select();
        foreach ($list as $k => $v) {
            $pUser1 = $this->getUserP1($v['user_id']);
            $list[$k]['pUser1'] = empty($pUser1) ? '无' : $pUser1['name'] . ' ' . $pUser1['mobile'];

            $cUser1 = $this->getUserC1($v['user_id'], $startDateP, $endDateP, $overInfo);
            $cUser2 = $this->getUserC2($v['user_id'], $startDateP, $endDateP, $overInfo);
            $list[$k]['cUser1'] = $cUser1;
            $list[$k]['cUser2'] = $cUser2;
            if ($cUser1 > 0) {
                $list[$k]['viewC'] = 1;
            }
            if ($cUser2 > 0) {
                $list[$k]['viewC'] = 2;
            }
            $list[$k]['over_info'] = (empty($v['resume_name']) || empty($v['resume_work'])) ? 2 : 1;
        }
        if (empty($list)) {
            $list = [];
        }
        $arr = [
            'list' => $list,
        ];

        return Response::json(200, "", $arr);
    }

    /**
     * 直接查看下一级会员
     */
    public function userShareList()
    {
        $pid = I('pid', '');
        $level = I('level', '');
        $startDate = I('sDate', '');
        $endDate = I('eDate', '');
        $startDateP = I('sDateC', '');
        $endDateP = I('eDateC', '');
        $overInfo = I('overInfo', '');

        $where = [];
        $where['ur1.user_id'] = $pid;
        if (!empty($startDate)) {
            $where['u.create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['u.create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($overInfo, [1, 2])) {
            if ($overInfo == 1) {
                $where['r.name'] = ['exp', '!=""'];
                $where['r.work_state'] = ['exp', 'IS NOT NULL'];
            } else {
                $whereOr = [
                    'r.name' => ['exp', '=""']
                    , 'r.work_state' => ['exp', 'IS NULL']
                ];
                $whereOr['_logic'] = 'or';
                $where['_complex'] = $whereOr;
            }
        }

        $userRegister = M('user_register', 'cxt_', 'db2');
        $list = $userRegister
            ->alias('ur1')
            ->join('LEFT JOIN `cxt_user` u ON u.`user_id`=ur1.`invitation_user_id`')
            ->join('LEFT JOIN `cxt_resume` r ON r.`user_id`=u.`user_id`')
            ->where($where)
            ->field('u.`user_id`,u.`name`,u.`mobile`,u.`create_time`,r.`name` resume_name,r.`work_state` resume_work')
            ->order('u.`create_time` DESC')
            ->group('u.`user_id`')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['cUser1'] = 0;
            if ($level == 1) {
                $cUser1 = $this->getUserC1($v['user_id'], $startDateP, $endDateP, $overInfo);
                $list[$k]['cUser1'] = $cUser1;
                if ($cUser1 > 0) {
                    $list[$k]['viewC'] = 1;
                }
            }
            $list[$k]['over_info'] = (empty($v['resume_name']) || empty($v['resume_work'])) ? 2 : 1;
        }

        $arr = [
            'pLevel1' => $pid,
            'list' => $list,
        ];
        if ($level > 1) {
            $arr['pLevel2'] = $userRegister->where(['invitation_user_id' => $pid])->order('`create_time` DESC')->getField('user_id');
            if (empty($arr['pLevel2'])) {
                $arr['pLevel2'] = 0;
            }
        }

        return Response::json(200, "", $arr);
    }

    /**
     * 通过下二级查看下一级会员
     */
    public function userShareList2()
    {
        $pid = I('pid', '');
        $level = I('level', '');
        $startDate = I('sDate', '');
        $endDate = I('eDate', '');
        $startDateP = I('sDateC', '');
        $endDateP = I('eDateC', '');
        $overInfo = I('overInfo', '');

        $where = [];
        $where['ur1.user_id'] = $pid;
        $whereOr1 = [];
        $whereOr2 = [];
        if (!empty($startDate)) {
            $whereOr1['ur1.create_time'][] = ['egt', $startDate . ' 00:00:00'];
            $whereOr2['ur2.create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $whereOr1['ur1.create_time'][] = ['elt', $endDate . ' 23:59:59'];
            $whereOr2['ur2.create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (!empty($whereOr1)) {
            $whereOr = [];
            $whereOr[] = $whereOr1;
            $whereOr[] = $whereOr2;
            $whereOr['_logic'] = 'or';
            $where['_complex'][] = $whereOr;
        }
        $where['ur1.invitation_user_id'] = ['exp', 'IS NOT NULL'];
        if (in_array($overInfo, [1, 2])) {
            if ($overInfo == 1) {
                $where['r.name'] = ['exp', '!=""'];
                $where['r.work_state'] = ['exp', 'IS NOT NULL'];
            } else {
                $whereOr = [
                    'r.name' => ['exp', '=""']
                    , 'r.work_state' => ['exp', 'IS NULL']
                ];
                $whereOr['_logic'] = 'or';
                $where['_complex'][] = $whereOr;
            }
        }

        $userRegister = M('user_register', 'cxt_', 'db2');
        $list = $userRegister
            ->alias('ur1')
            ->join('LEFT JOIN `cxt_user_register` ur2 ON ur2.`user_id`=ur1.`invitation_user_id`')
            ->join('LEFT JOIN `cxt_user` u ON u.`user_id`=ur1.`invitation_user_id`')
            ->join('LEFT JOIN `cxt_resume` r ON r.`user_id`=u.`user_id`')
            ->where($where)
            ->field('u.`user_id`,u.`name`,u.`mobile`,u.`create_time`,r.`name` resume_name,r.`work_state` resume_work')
            ->order('u.`create_time` DESC')
            ->group('ur1.`invitation_user_id`')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['cUser1'] = 0;
            if ($level == 1) {
                $cUser1 = $this->getUserC1($v['user_id'], $startDateP, $endDateP, $overInfo);
                $list[$k]['cUser1'] = $cUser1;
                if ($cUser1 > 0) {
                    $list[$k]['viewC'] = 1;
                }
            }
            $list[$k]['over_info'] = (empty($v['resume_name']) || empty($v['resume_work'])) ? 2 : 1;
        }

        $arr = [
            'pLevel1' => $pid,
            'list' => $list,
        ];
        if ($level > 1) {
            $arr['pLevel2'] = $userRegister->where(['invitation_user_id' => $pid])->order('`create_time` DESC')->getField('user_id');
            if (empty($arr['pLevel2'])) {
                $arr['pLevel2'] = 0;
            }
        }

        return Response::json(200, "", $arr);
    }

    /**
     * 下一级会员数
     * @param $uid
     * @param $startDate
     * @param $endDate
     * @param $overInfo
     * @return array
     */
    private function getUserC1($uid, $startDate, $endDate, $overInfo)
    {
        $where = [];
        $where['ur.user_id'] = $uid;
        if (!empty($startDate)) {
            $where['ur.create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['ur.create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($overInfo, [1, 2])) {
            if ($overInfo == 1) {
                $where['r.name'] = ['exp', '!=""'];
                $where['r.work_state'] = ['exp', 'IS NOT NULL'];
            } else {
                $whereOr = [
                    'r.name' => ['exp', '=""']
                    , 'r.work_state' => ['exp', 'IS NULL']
                ];
                $whereOr['_logic'] = 'or';
                $where['_complex'] = $whereOr;
            }
        }

        $userRegister = M('user_register', 'cxt_', 'db2');
        $pUser = $userRegister
            ->alias('ur')
            ->join('LEFT JOIN `cxt_resume` r ON r.`user_id`=ur.`invitation_user_id`')
            ->where($where)
            ->field('ur.`invitation_user_id`')
            ->select();

        return count(array_unique(array_filter(array_column($pUser, 'invitation_user_id'))));
    }


    /**
     * 下两级会员数
     * @param $uid
     * @param $startDate
     * @param $endDate
     * @param $overInfo
     * @return array
     */
    private function getUserC2($uid, $startDate, $endDate, $overInfo)
    {
        $where = [];
        $where['ur1.user_id'] = $uid;
        if (!empty($startDate)) {
            $where['ur2.create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['ur2.create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($overInfo, [1, 2])) {
            if ($overInfo == 1) {
                $where['r.name'] = ['exp', '!=""'];
                $where['r.work_state'] = ['exp', 'IS NOT NULL'];
            } else {
                $whereOr = [
                    'r.name' => ['exp', '=""']
                    , 'r.work_state' => ['exp', 'IS NULL']
                ];
                $whereOr['_logic'] = 'or';
                $where['_complex'] = $whereOr;
            }
        }

        $userRegister = M('user_register', 'cxt_', 'db2');
        $pUser = $userRegister
            ->alias('ur1')
            ->join('LEFT JOIN `cxt_user_register` ur2 ON ur2.`user_id`=ur1.`invitation_user_id`')
            ->join('LEFT JOIN `cxt_resume` r ON r.`user_id`=ur2.`invitation_user_id`')
            ->where($where)
            ->field('ur2.`invitation_user_id`')
            ->select();

        return count(array_unique(array_filter(array_column($pUser, 'invitation_user_id'))));
    }

    /**
     * 下两级会员数
     * @param $uid
     * @return array
     */
    private function getUserC($uid)
    {
        $where = [];
        $where['ur1.user_id'] = $uid;

        $userRegister = M('user_register', 'cxt_', 'db2');
        $pUser = $userRegister
            ->alias('ur1')
            ->join('LEFT JOIN `cxt_user_register` ur2 ON ur2.`user_id`=ur1.`invitation_user_id`')
            ->where($where)
            ->field('ur1.`invitation_user_id` cid1,ur2.`invitation_user_id` cid2')
            ->select();

        $arr = [
            'cUser1' => count(array_unique(array_filter(array_column($pUser, 'cid1')))),
            'cUser2' => count(array_unique(array_filter(array_column($pUser, 'cid2')))),
        ];

        return $arr;
    }

    /**
     * 上一级会员信息
     * @param $uid
     * @return array
     */
    private function getUserP1($uid)
    {
        $record = M('user_register', 'cxt_', 'db2')
            ->where(['invitation_user_id' => $uid])
            ->group('`user_id`')
            ->find();

        if (!empty($record)) {
            $pUserArr = M('user', 'cxt_', 'db2')->where(['user_id' => $record['user_id']])->field('`name`,`mobile`')->find();
            if (!empty($pUserArr)) {
                return $pUserArr;
            }
        }

        return [];
    }

    /**
     * 上两级会员信息
     * @param $uid
     * @return array
     */
    private function getUserP($uid)
    {
        $user = M('user', 'cxt_', 'db2');
        $userRegister = M('user_register', 'cxt_', 'db2');
        $pUser = $userRegister
            ->alias('ur1')
            ->join('LEFT JOIN `cxt_user_register` ur2 ON ur2.`invitation_user_id`=ur1.`user_id`')
            ->where(['ur1.invitation_user_id' => $uid])
            ->field('ur1.`user_id` pid1,ur2.`user_id` pid2')
            ->group('ur1.`user_id`')
            ->find();
        $pUserIds = array_values(array_filter($pUser));
        $arr = [];
        if (!empty($pUserIds)) {
            $pUserArr = $user->where(['user_id' => ['in', $pUserIds]])->getField('user_id,name,mobile', true);
            if (!empty($pUser['pid1']) && isset($pUserArr[$pUser['pid1']])) {
                $arr['pUser1'] = $pUserArr[$pUser['pid1']];
            }
            if (!empty($pUser['pid2']) && isset($pUserArr[$pUser['pid2']])) {
                $arr['pUser2'] = $pUserArr[$pUser['pid2']];
            }
        }

        return $arr;
    }


    /**
     * 会员数量
     */
    public function userCount()
    {
        $keyword = I('keyword', '');
        $startDate = I('sDate', '');
        $endDate = I('eDate', '');
        $overInfo = I('overInfo', '');

        $where = [];
        if (!empty($keyword)) {
            $whereOr = [
                'u.mobile' => ['like', '%' . $keyword . '%']
                , 'u.name' => ['like', '%' . $keyword . '%']
            ];
            $whereOr['_logic'] = 'or';
            $where['_complex'][] = $whereOr;
        }
        if (!empty($startDate)) {
            $where['u.create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['u.create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($overInfo, [1, 2])) {
            if ($overInfo == 1) {
                $where['r.name'] = ['exp', '!=""'];
                $where['r.work_state'] = ['exp', 'IS NOT NULL'];
            } else {
                $whereOr = [
                    'r.name' => ['exp', '=""']
                    , 'r.work_state' => ['exp', 'IS NULL']
                ];
                $whereOr['_logic'] = 'or';
                $where['_complex'][] = $whereOr;
            }
        }

        $count = M('user', 'cxt_', 'db2')
            ->alias('u')
            ->join('LEFT JOIN `cxt_resume` r ON r.`user_id`=u.`user_id`')
            ->where($where)
            ->count();

        return Response::json(200, "", $count);
    }

    /**
     * 会员列表
     */
    public function userListDownload()
    {
        $keyword = I('keyword', '');
        $startDate = I('sDate', '');
        $endDate = I('eDate', '');
        $startDateP = I('sDateC', '');
        $endDateP = I('eDateC', '');
        $overInfo = I('overInfo', '');

        $where = [];
        if (!empty($keyword)) {
            $whereOr = [
                'u.mobile' => ['like', '%' . $keyword . '%']
                , 'u.name' => ['like', '%' . $keyword . '%']
            ];
            $whereOr['_logic'] = 'or';
            $where['_complex'][] = $whereOr;
        }
        if (!empty($startDate)) {
            $where['u.create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $where['u.create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }
        if (in_array($overInfo, [1, 2])) {
            if ($overInfo == 1) {
                $where['r.name'] = ['exp', '!=""'];
                $where['r.work_state'] = ['exp', 'IS NOT NULL'];
            } else {
                $whereOr = [
                    'r.name' => ['exp', '=""']
                    , 'r.work_state' => ['exp', 'IS NULL']
                ];
                $whereOr['_logic'] = 'or';
                $where['_complex'][] = $whereOr;
            }
        }

        $user = M('user', 'cxt_', 'db2');
        $list = $user
            ->alias('u')
            ->join('LEFT JOIN `cxt_resume` r ON r.`user_id`=u.`user_id`')
            ->where($where)
            ->field('u.`user_id`,u.`name`,u.`mobile`,u.`create_time`,r.`name` resume_name,r.`work_state` resume_work')
            ->order('u.`create_time` DESC')
            ->select();

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '用户ID');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '姓名');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '手机号');
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, '上一级会员姓名');
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, '上一级会员手机');
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, '注册时间');
        $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, '是否完善简历');
        $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, '下一级会员数');
        $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, '下二级会员数');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':I' . $i)->getFont()->setBold(true);

        if (empty($list)) {
            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '暂无记录');
            $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':I' . $i);
        } else {
            foreach ($list as $k => $v) {
                $pUser1 = $this->getUserP1($v['user_id']);
                $pUserN1 = empty($pUser1) ? '无' : $pUser1['name'];
                $pUserM1 = empty($pUser1) ? '无' : $pUser1['mobile'];
                $cUser1 = $this->getUserC1($v['user_id'], $startDateP, $endDateP, $overInfo);
                $cUser2 = $this->getUserC2($v['user_id'], $startDateP, $endDateP, $overInfo);

                $i++;
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $v['user_id']);
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['name']);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['mobile']);
                $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, $pUserN1);
                $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, $pUserM1);
                $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, $v['create_time']);
                $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, (empty($v['resume_name']) || empty($v['resume_work'])) ? '否' : '是');
                $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, $cUser1);
                $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, $cUser2);
            }
        }
        $objPHPExcel->getActiveSheet()->getStyle('A1:I' . $i)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A1:I' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '用户推荐列表' . date('YmdHis') . '.xlsx';
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName);
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }


    public function getShareList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = (int)$_REQUEST['limit'];

        $keyword = I('keyword', '');
        $type = I('type', -1);
        $sDate = I('sDate', '');
        $eDate = I('eDate', '');

        $where = [];
        if (!empty($keyword)) {
            $whereOr = [
                'u.mobile' => ['like', '%' . $keyword . '%']
                , 'u.name' => ['like', '%' . $keyword . '%']
            ];
            $whereOr['_logic'] = 'or';
            $where['_complex'] = $whereOr;
        }
        if ($type > -1) {
            $where['s.type'] = $type;
        }
        if (!empty($sDate)) {
            $where['s.create_time'][] = ['egt', $sDate . ' 00:00:00'];
        }
        if (!empty($eDate)) {
            $where['s.create_time'][] = ['elt', $eDate . ' 23:59:59'];
        }
        $where['u.user_id'] = ['exp', 'IS NOT NULL'];

        $model = M('share', 'cxt_', 'db2');

        $arr = $model
            ->alias('s')
            ->join('LEFT JOIN `cxt_user` u ON u.`user_id`=s.`user_id`')
            ->where($where)
            ->field('u.`user_id`,u.`name`,u.`mobile`,count(1) num')
            ->group('s.`user_id`')
            ->select();

        $list = array_slice($arr, ($page - 1) * $limit, $limit);
        $count = count($arr);

        return Response::mjson($list, $count);
    }

    public function getUserShareList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = (int)$_REQUEST['limit'];

        $uid = I('uid', 0);
        $type = I('type', -1);
        $sDate = I('sDate', '');
        $eDate = I('eDate', '');

        $where = [
            'user_id' => $uid
        ];
        if ($type > -1) {
            $where['type'] = $type;
        }
        if (!empty($sDate)) {
            $where['create_time'][] = ['egt', $sDate . ' 00:00:00'];
        }
        if (!empty($eDate)) {
            $where['create_time'][] = ['elt', $eDate . ' 23:59:59'];
        }

        $model = M('share', 'cxt_', 'db2');
        $count = $model
            ->where($where)
            ->count();
        $list = [];
        if ($count > 0) {
            $position = M('position', 'cxt_', 'db2');
            $list = $model
                ->where($where)
                ->page($page, $limit)
                ->order('`create_time` DESC')
                ->select();
            foreach ($list as $k => $v) {
                if ($v['type'] == 1) {
                    $msg = '小程序分享';
                } else {
                    $msg = '职位分享：' . $position->where(['position_id' => $v['position_id']])->getField('position_name');
                }
                $list[$k]['msg'] = $msg;
            }
        }

        return Response::mjson($list, $count);
    }

    public function getScoreList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = (int)$_REQUEST['limit'];

        $keyword = I('keyword', '');
        $keyword1 = I('keyword1', '');
        $sDate = I('sDate', '');
        $eDate = I('eDate', '');

        $where = [];
        if (!empty($keyword)) {
            $whereOr = [
                'u.mobile' => ['like', '%' . $keyword . '%']
                , 'u.name' => ['like', '%' . $keyword . '%']
            ];
            $whereOr['_logic'] = 'or';
            $where['_complex'] = $whereOr;
        }
        if (!empty($keyword1)) {
            $where['sgr.remark'] = ['like', '%' . $keyword1 . '%'];
        }
        if (!empty($sDate)) {
            $where['sgr.create_time'][] = ['egt', $sDate . ' 00:00:00'];
        }
        if (!empty($eDate)) {
            $where['sgr.create_time'][] = ['elt', $eDate . ' 23:59:59'];
        }
//        $where['u.user_id'] = ['exp', 'IS NOT NULL'];

        $model = M('score_get_record', 'cxt_', 'db2');
        $count = $model
            ->alias('sgr')
            ->join('LEFT JOIN `cxt_user` u ON u.`user_id`=sgr.`user_id`')
            ->where($where)
            ->count();
        $list = [];
        if ($count > 0) {
            $list = $model
                ->alias('sgr')
                ->join('LEFT JOIN `cxt_user` u ON u.`user_id`=sgr.`user_id`')
                ->where($where)
                ->page($page, $limit)
                ->field('u.`name`,u.`mobile`,sgr.*')
                ->order('sgr.`create_time` DESC')
                ->select();
        }

        return Response::mjson($list, $count);
    }

    public function getUserScoreList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = (int)$_REQUEST['limit'];

        $uid = I('uid', 0);
        $keyword1 = I('keyword1', '');
        $sDate = I('sDate', '');
        $eDate = I('eDate', '');

        $where = [
            'user_id' => $uid
        ];
        if (!empty($keyword1)) {
            $where['remark'] = ['like', '%' . $keyword1 . '%'];
        }
        if (!empty($sDate)) {
            $where['create_time'][] = ['egt', $sDate . ' 00:00:00'];
        }
        if (!empty($eDate)) {
            $where['create_time'][] = ['elt', $eDate . ' 23:59:59'];
        }

        $model = M('score_get_record', 'cxt_', 'db2');
        $count = $model
            ->where($where)
            ->count();
        $list = [];
        if ($count > 0) {
            $list = $model
                ->where($where)
                ->page($page, $limit)
                ->order('`create_time` DESC')
                ->select();
        }
        return Response::mjson($list, $count);
    }

    public function getUserScoreNum()
    {
        $uid = I('uid', 0);
        $keyword = I('keyword', '');
        $keyword1 = I('keyword1', '');
        $sDate = I('sDate', '');
        $eDate = I('eDate', '');

        $where = [];
        if (is_numeric($uid) && $uid > 0) {
            $where['user_id'] = $uid;
        }
        if (!empty($keyword)) {
            $userWhere = [
                'mobile' => ['like', '%' . $keyword . '%']
                , 'name' => ['like', '%' . $keyword . '%']
            ];
            $userWhere['_logic'] = 'or';
            $userIds = M('user', 'cxt_', 'db2')->where($userWhere)->getField('user_id', true);
            $where['user_id'] = ['in', empty($userIds) ? [-1] : $userIds];
        }
        if (!empty($keyword1)) {
            $where['remark'] = ['like', '%' . $keyword1 . '%'];
        }
        if (!empty($sDate)) {
            $where['create_time'][] = ['egt', $sDate . ' 00:00:00'];
        }
        if (!empty($eDate)) {
            $where['create_time'][] = ['elt', $eDate . ' 23:59:59'];
        }

        $score = (int)M('score_get_record', 'cxt_', 'db2')->where($where)->sum('get_score_value');

        return Response::json(200, "", $score);
    }
}