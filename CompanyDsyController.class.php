<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/9/22 0022
 * Time: 下午 4:29
 */

namespace Admin\Controller;

use Org\Util\Response;
use Think\Controller;

class CompanyDsyController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 注册审核主页
     */
    public function reg_check_index()
    {
        $this->display();
    }

    /**
     * 注册列表信息
     */
    public function reg_check_index_info()
    {
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = !empty($page) ? 10 : '';
        $status = I('status', '');
        $cname = I('cname', '');
        $where = [];
        if (empty($status)) {
            if ($status === (string)0) {
                $where['review_status'] = $status;
            }
        } else {
            $where['review_status'] = $status;
        }
        if (!empty($cname)) {
            $where['corporate_name'] = array('like', "%$cname%");
        }
        $Corporate = M('Corporate', 'cxt_', 'db2');
        $userModel = M('user', 'cxt_', 'db2');
        $info = $Corporate->where($where)->page($page, $limit)->order('corporate_id desc')->select();
        foreach ($info as $key => $item) {
            $info[$key]['business_licence'] = format_img($item['business_licence'], IMG_VIEW);
            $info[$key]['owned_industry'] = $this->formatIndustry($item['owned_industry']);
            $area = M('Provincial')->where(['id' => $item['city']])->getField('name');
            if (in_array($area, ['市', '县', '市辖区'])) {
                $area = M('Provincial')->where(['id' => $item['province']])->getField('name');
            }
            $info[$key]['area'] = $area;

            $userInfo = $userModel->where(['user_id' => $item['user_id']])->field('name,mobile')->find();
            $info[$key]['uname'] = $userInfo['name'];
            $info[$key]['umobile'] = $userInfo['mobile'];
        }
        $num = $Corporate->where($where)->count();
        return Response::mjson($info, $num);
    }

    /**
     * 注册列表查看详情
     */
    public function reg_check_detail()
    {
        $id = I('id', 0);
        $where = [];
        $where['corporate_id'] = $id;

        $Corporate = M('Corporate', 'cxt_', 'db2');
        $info = $Corporate->where($where)->find();
        $info['owned_industry'] = $this->formatIndustry($info['owned_industry']);
        $area = '';
        if (!empty($info['province'])) {
            $area .= M('Provincial')->where(['id' => $info['province']])->getField('name');
        }
        if (!empty($info['city'])) {
            $area .= M('Provincial')->where(['id' => $info['city']])->getField('name');
        }
        if (!empty($info['area'])) {
            $area .= M('Provincial')->where(['id' => $info['area']])->getField('name');
        }
        $info['area'] = $area . $info['address'];
        $info['corporate_email'] = trim($info['corporate_email']);

        $info['corporate_logo'] = format_img($info['corporate_logo'], IMG_VIEW);
        $info['business_licence'] = format_img($info['business_licence'], IMG_VIEW);
        $info['proxy'] = format_img($info['proxy'], IMG_VIEW);
        $info['picture'] = format_img($info['picture'], IMG_VIEW);
        $info['picture1'] = format_img($info['picture1'], IMG_VIEW);
        $info['picture2'] = format_img($info['picture2'], IMG_VIEW);

        $info['video'] = format_img($info['video']);
        $info['enterprise_location'] = format_img($info['enterprise_location']);
        $info['work_environment'] = format_img($info['work_environment']);
        $info['canteen'] = format_img($info['canteen']);
        $info['dormitory'] = format_img($info['dormitory']);

        $this->assign('info', $info);
        $this->display('reg_detail');
    }

    /**
     * 注册审核同意操作
     */
    public function reg_agree()
    {
        $id = I('ids', '');
        if (empty($id)) {
            return Response::show(300, 'Miss Params');
        }
        $id = $id[0];

        $user = M('Corporate', 'cxt_', 'db2');
        $where1['corporate_id'] = array('eq', $id);
        $user->review_status = 1;
        if (!empty($reson)) {
            $user->remark = $reson;
        }
        $one = $user->where($where1)->save();
        $auditRes = $this->corporateAudit($id, 1);
        $info = $user
            ->where($where1)
            ->find();
        $cname = $info['corporate_name'];
        $mobile = $info['mobile'];
        //添加操作日志
        $admin_log = '苏鹰找人企业【' . $cname . '】注册审核通过';
        if ($one == true && $auditRes != false) {
            M('user', 'cxt_', 'db2')->where(['review_status' => 0, 'user_id' => $info['user_id']])->setField('review_status', 1);
            try {
                $to = $mobile;
                $datas = array(
                );
                $tempId = '485879';
                SendTemplateSMS($to, $datas, $tempId, 3);
            } catch (Exception $e) {

            }
            admin_log($admin_log, 1, 'cxt_corporate:' . $id);
            return Response::show(200, 'Success');
        } else {
            admin_log($admin_log, 0, 'cxt_corporate:' . $id);
            return Response::show(400, 'Fail');
        }
    }

    /**
     * 注册审核驳回操作
     */
    public function reg_cancle()
    {
        $reson = I('reson', '');
        $ids = I('ids', '');
        $id = $ids[0];
        $reson = trim($reson);
        $user = M('Corporate', 'cxt_', 'db2');
        $where1['corporate_id'] = array('eq', $id);
        $user->review_status = 2;
//        if (!empty($reson)) {
//            $user->remark = $reson;
//        }
        $one = $user->where($where1)->save();
        $auditRes = $this->corporateAudit($id, 2, $reson);
        $info = $user
            ->where($where1)
            ->find();
        $cname = $info['corporate_name'];
        $mobile = $info['contacts_mobile'];
        //添加操作日志
        $admin_log = '苏鹰找人企业【' . $cname . '】注册审核不通过，驳回原因：' . $reson;
        if ($one == true && $auditRes != false) {
            M('user', 'cxt_', 'db2')->where(['review_status' => 0, 'user_id' => $info['user_id']])->setField('review_status', 2);
            try {
                $to = $mobile;
                $datas = array(
                    0 => $reson,
                );
                $tempId = '485882';
                SendTemplateSMS($to, $datas, $tempId, 3);
            } catch (Exception $e) {

            }
            admin_log($admin_log, 1, 'cxt_corporate:' . $id);
            return Response::show(200, 'Success');
        } else {
            admin_log($admin_log, 0, 'cxt_corporate:' . $id);
            return Response::show(400, 'Fail');
        }


    }

    /**
     * 审核日志记录
     * @param $cid
     * @param $status 0：待审核 1：审核成功 2：审核失败
     * @param string $remark
     * @return int
     */
    private function corporateAudit($cid, $status, $remark = '')
    {
        $param = [
            'user_id' => intval(M('admin')->where(['username' => $_COOKIE['username']])->getField('id')),
            'corporate_id' => $cid,
            'review_status' => $status,
        ];
        if (!empty($remark)) {
            $param['remark'] = $remark;
        }
        $auditModel = M('corporate_audit', 'cxt_', 'db2');
        //记录不存在新增，存在则修改
        $info = $auditModel
            ->where(['corporate_id' => $cid, 'review_status' => 0])
            ->field('corporate_audit_id')
            ->find();
        if (empty($info)) {
            return $auditModel->add($param);
        }
        return $auditModel->where(['corporate_id' => $cid, 'review_status' => 0])->save($param);
    }

    /**
     * 资料变更审核
     */
    public function change_info_index()
    {
        $this->display();
    }

    public function company_lis()
    {
        $pageIndex = I('pageIndex', '');
        $page = !empty($pageIndex) ? $pageIndex + 1 : '';
        $limit = !empty($limit) ? $limit : 10;
        $cname = I('cname', '');
        $cname = trim($cname);
        $status = I('status', '');
        if (!empty($cname)) {
            $where['a.corporate_name'] = array('like', "%$cname%");
        }
        if (empty($status)) {
            if ($status === (string)0) {
                $where['a.status'] = array('eq', $status);
            }
        } else {
            $where['a.status'] = array('eq', $status);
        }
        //可能用到
        $corporate_auditing = M('corporate_auditing', 't_');
        $info = $corporate_auditing
            ->join('as a left join t_corporate as b on a.corporate_id = b.corporate_id')
            ->where($where)
            ->field('a.id,a.corporate_id as cid,a.corporate_name as cname,a.owned_industry,a.number,a.address,b.create_time,a.province,a.city,a.area,a.business_licence,a.status,b.is_well_known')
            ->order('a.id desc')
            ->page($page, $limit)
            ->select();

        $num = $corporate_auditing
            ->join('as a left join t_corporate as b on a.corporate_id = b.corporate_id')
            ->where($where)
            ->count();
        $local = M('provincial', 'dsy_');
        foreach ($info as $key => $value) {
            $string = $value['province'] . ',' . $value['city'] . ',' . $value['area'];
            $where1['id'] = array('in', $string);
            $localcation = $local->where($where1)->field('name')->select();
            $info[$key]['area'] = $localcation[0]['name'] . $localcation[1]['name'] . $localcation[2]['name'];
            $info[$key]['address'] = $localcation[0]['name'] . $localcation[1]['name'] . $localcation[2]['name'] . $value['address'];

            $info[$key]['owned_industry'] = $this->formatIndustry($value['owned_industry']);
            if (empty($value['business_licence'])) {
                $info[$key]['business_licence'] = '';
            } else {
                $img = format_img($value['business_licence'], IMG_VIEW);
                $info[$key]['business_licence'] = '<a href="' . $img . '" target="view_window">点击查看</a>';
            }

        }
        return Response::mjson($info, $num);
    }

    /**
     * 修改资料审核通过
     */
    public function change_agree()
    {
        $id = I('ids', '');
        $id = $id[0];
        $corporate_auditing = M('corporate_auditing', 't_');
        $corporate_auditing->startTrans();
        $corporate_auditing->status = 1;
        $where['id'] = array('eq', $id);
        $one = $corporate_auditing->where($where)->save();

        $all = $corporate_auditing->where($where)->find();
        $cname = trim($all['corporate_name']);
        $where_check['corporate_name'] = array('eq', $cname);
        $where_check['corporate_id'] = array('neq', $all['corporate_id']);
        $is_exist = M('corporate', 't_')->where($where_check)->find();
        if (!empty($is_exist)) {
            return Response::show(400, '公司名称重复');
        }
        //判断修改的企业名称是否重复
        $cid = $all['corporate_id'];
        $data['owned_industry'] = $all['owned_industry'];
        $data['province'] = $all['province'];
        $data['city'] = $all['city'];
        $data['area'] = $all['area'];
        $data['number'] = $all['number'];
        $data['corporate_name'] = $all['corporate_name'];
        $data['contacts_name'] = $all['contacts_name'];
        $data['business_licence'] = $all['business_licence'];
        $data['proxy'] = $all['proxy'];
//        $data['contacts_mobile'] = $all['contacts_mobile'];
        $data['address'] = $all['address'];
        $where1['corporate_id'] = array('eq', $cid);
        $two = M('corporate', 't_')->where($where1)->save($data);

        $info = $corporate_auditing->where($where)->find();
        $cid = $info['corporate_id'];
        $where1['corporate_id'] = array('eq', $cid);
        $cinfo = M('corporate', 't_')->where($where1)->find();
        $mobile = $cinfo['contacts_mobile'];
        //添加操作日志
        $admin_log = '苏鹰找人企业【' . $cname . '】修改申请同意';
        if ($one && $two) {
            $corporate_auditing->commit();
            try {
                $to = $mobile;
                $datas = array(
                );
                $tempId = '485879';
                SendTemplateSMS($to, $datas, $tempId, 3);

            } catch (Exception $e) {

            }
            admin_log($admin_log, 1, 't_corporate_auditing:' . $id);
            return Response::show(200, 'Success');
        } else {
            $corporate_auditing->rollback();
            admin_log($admin_log, 0, 't_corporate_auditing:' . $id);
            return Response::show(400, 'Fail');
        }
    }

    /**
     * 修改资料审核不通过
     */
    public function change_disagree()
    {
        $reson = I('reson', '');
        $ids = I('ids', '');
        $id = $ids[0];
        $reson = trim($reson);
        $corporate_auditing = M('corporate_auditing', 't_');
        $where['id'] = array('eq', $id);
        if (!empty($reson)) {
            $corporate_auditing->remark = $reson;
        }
        $corporate_auditing->status = 2;
        $one = $corporate_auditing->where($where)->save();

        $info = $corporate_auditing->where($where)->find();
        $cid = $info['corporate_id'];
        $corporate = M('corporate', 't_');
        $where1['corporate_id'] = array('eq', $cid);
        $linfo = $corporate->where($where1)->find();

        $mobile = $linfo['contacts_mobile'];
        //添加操作日志
        $admin_log = '苏鹰找人企业【' . $info['corporate_name'] . '】修改申请驳回，驳回原因：' . $reson;
        if ($one == true) {
            try {
                $to = $mobile;
                $datas = array(
                    0 => $reson,
                );
                $tempId = '485882';
                SendTemplateSMS($to, $datas, $tempId, 3);

            } catch (Exception $e) {

            }
            admin_log($admin_log, 1, 't_corporate_auditing:' . $id);
            return Response::show(200, 'Success');
        } else {
            admin_log($admin_log, 0, 't_corporate_auditing:' . $id);
            return Response::show(400, 'Fail');
        }

    }

    private function formatIndustry($code = null)
    {
        $data = [
            1 => 'IT/通讯/电子/互联网',
            2 => '金融',
            3 => '房地产/建筑业',
            4 => '商业服务',
            5 => '贸易/批发/零售/租赁业',
            6 => '教育',
            7 => '生产/加工/制造',
            8 => '交通/运输/物流/仓储',
            9 => '服务业',
            10 => '文化/传媒/娱乐/体育',
            11 => '能源/矿产/环保',
            12 => '医疗/卫生/保健',
            13 => '政府公共事业非盈利机构',
            14 => '餐饮/酒店/旅游',
            15 => '其他'
        ];
        if ($code == null) {
            return $data;
        } else {
            return $data[$code];
        }
    }

    public function setWellKnown()
    {
        $rid = (int)I('rid', -1);
        $status = I('status', -1);

        if (!in_array($status, [0, 1])) {
            return Response::show(300, '错误操作');
        }

        $corporateModel = M('corporate', 'cxt_', 'db2');
        $info = $corporateModel->where(['corporate_id' => $rid])->field('corporate_name,is_well_known')->find();
        if (empty($info)) {
            return Response::show(300, '找不到该企业');
        }

        if ($status == 1) {
            $wellKnownStr = '设为名企';
            $oldStatus = 0;

        } else {
            $wellKnownStr = '取消名企';
            $oldStatus = 1;
        }

        $setWellKnownRes = $corporateModel
            ->where("`corporate_id`='" . $rid . "' and (`is_well_known` is null or `is_well_known`='" . $oldStatus . "')")
            ->setField('is_well_known', $status);

        if ($setWellKnownRes === false) {
            return Response::show(300, '操作失败');
        } else if ($setWellKnownRes === 0) {
            return Response::show(300, '已' . $wellKnownStr);
        } else {
            //添加操作日志
            $admin_log = '苏鹰找人企业【' . $info['corporate_name'] . '】成功' . $wellKnownStr;
            admin_log($admin_log, 1, 'cxt_corporate:' . $rid);

            return Response::show(200, '成功' . $wellKnownStr);
        }
    }

}