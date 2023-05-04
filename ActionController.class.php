<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/7
 * Time: 11:31
 */

namespace Admin\Controller;

use Org\Util\Response;

class ActionController extends CommonController
{
    private $cids = '-1';

    public function _initialize()
    {
        if (APIE == 1) {
            $this->cids = '504,804,805,448,455,461,462,463,464,469,477,479,493,497,511,531,532,534,545,546,564,571,578,582,584,585,587,602,603,604,605,606,607,608,609,610,611,612,613,614,615,616,617,618,619,620,621,622,623,624,625,626,627,661,663,669,670,676,678,684,685,686,687,688,689,690,691,695,696,697,704,706,711,720,721,723,739,741,746,482,495,509,553,646,648,649,672,673,682,448,707,735,497,748,767,770,686,567,674,667';
        }
    }

    //弃用
    public function actionLog1()
    {
        //获取列表数据
        $map['status'] = array('gt', -1);
        $model = M('ActionLog', 'sys_');
        $list = $this->lists($model, $map);
        int_to_string($list);
        foreach ($list as $key => $value) {
            $list[$key]['username'] = M('Admin', 'dsy_')->where($value['user_id'])->getField('username');
        }
        $this->assign('_list', $list);
        $this->meta_title = '行为日志';
        $this->display();
    }

    public function edit($id = 0)
    {
        empty($id) && $this->error('参数错误！');

        $info = M('ActionLog', 'sys_')->field(true)->find($id);

        $this->assign('info', $info);
        $this->meta_title = '查看行为日志';
        $this->display();
    }

    /**
     * 删除日志
     * @param mixed $ids
     */
    public function remove($ids = 0)
    {
        empty($ids) && $this->error('参数错误！');
        if (is_array($ids)) {
            $map['id'] = array('in', $ids);
        } elseif (is_numeric($ids)) {
            $map['id'] = $ids;
        }
        $res = M('ActionLog', 'sys_')->where($map)->delete();
        if ($res !== false) {
            $this->success('删除成功！');
        } else {
            $this->error('删除失败！');
        }
    }

    /**
     * 清空日志
     */
    public function clear()
    {
        $res = M('ActionLog', 'sys_')->where('1=1')->delete();
        if ($res !== false) {
            $this->success('日志清空成功！');
        } else {
            $this->error('日志清空失败！');
        }
    }

    //操作日志
    public function actionLog()
    {
        $this->display();
    }

    //获取操作列表
    public function getLogList()
    {
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;

        $model = M('ActionLog', 'sys_');

        //获取列表数据
        $map = [];
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        if (!empty($startDate)) {
            $map['create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $map['create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }

        $count = intval($model->where($map)->count());
        if ($count == 0) {
            $list = [];
        } else {
            $list = $model->where($map)->page($page, $limit)->order('create_time desc')->select();
        }

        return Response::mjson($list, $count);
    }

    //导出操作列表
    public function downLog()
    {
        $model = M('ActionLog', 'sys_');

        //获取列表数据
        $map = [];
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        if (!empty($startDate)) {
            $map['create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $map['create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }

        $list = $model->where($map)->order('create_time desc')->select();

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(100);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->getActiveSheet()->setCellValue('A1', '编号');
        $objPHPExcel->getActiveSheet()->setCellValue('B1', '执行时间');
        $objPHPExcel->getActiveSheet()->setCellValue('C1', '内容');
        $objPHPExcel->getActiveSheet()->setCellValue('D1', '执行者');
        $objPHPExcel->getActiveSheet()->setCellValue('E1', 'IP');
        $objPHPExcel->getActiveSheet()->setCellValue('F1', '结果');

        foreach ($list as $k => $v) {
            $i = $k + 2;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $v['id']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['create_time']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['remark']);
            $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, $v['user_id']);
            $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, $v['action_ip']);
            $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, $v['status'] == 1 ? '成功' : '失败');
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '操作日志' . date('YmdHis') . '.xlsx';
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

    /**
     * 人事邦统计
     */
    public function weekExcel()
    {
        $this->display('week_excel');
    }

    /**
     * 统计 企业审批
     */
    public function balance()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

        //新注册人员数
        $sql = "SELECT COUNT(1) as num FROM `t_employee` AS e LEFT JOIN `t_personal` AS p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` AS u ON p.`user_id`=u.`user_id` WHERE e.`del_status`=0 AND u.`register_time`>='" . $sDate . "' AND u.`register_time`<='" . $eDate . "' AND e.`corporate_id` NOT IN (" . $this->cids . ");";
        $arr = M()->query($sql);
        $num1 = intval($arr[0]['num']);
        //新激活人员数
        $sql = "SELECT COUNT(1) as num FROM `t_employee` AS e LEFT JOIN `t_personal` AS p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` AS u ON p.`user_id`=u.`user_id` WHERE e.`del_status`=0 AND u.`is_login_app`=1 AND u.`activate_date`>='" . $sDate . "' AND u.`activate_date`<='" . $eDate . "' AND e.`corporate_id` NOT IN (" . $this->cids . ");";
        $arr = M()->query($sql);
        $num16 = intval($arr[0]['num']);
        //离职人员数
        $sql = "SELECT COUNT(1) as num FROM `t_employee` AS e LEFT JOIN `t_personal` AS p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` AS u ON p.`user_id`=u.`user_id` WHERE e.`del_status`=0 AND e.`status`=5 AND e.`leave_time`>='" . $sDate . "' AND e.`leave_time`<='" . $eDate . "' AND e.`corporate_id` NOT IN (" . $this->cids . ");";
        $arr = M()->query($sql);
        $num2 = intval($arr[0]['num']);
        //新注册中激活人员数
        $sql = "SELECT COUNT(1) as num FROM `t_employee` AS e LEFT JOIN `t_personal` AS p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` AS u ON p.`user_id`=u.`user_id` WHERE e.`del_status`=0 AND u.`is_login_app`=1 AND u.`register_time`>='" . $sDate . "' AND u.`register_time`<='" . $eDate . "' AND e.`corporate_id` NOT IN (" . $this->cids . ");";
        $arr = M()->query($sql);
        $num10 = intval($arr[0]['num']);
        //在职人员数
        $sql = "SELECT COUNT(1) as num FROM `t_employee` AS e LEFT JOIN `t_personal` AS p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` AS u ON p.`user_id`=u.`user_id` WHERE e.`del_status`=0 AND e.`status`!=5 AND u.`register_time`<='" . $eDate . "';";
        $arr = M()->query($sql);
        $num14 = intval($arr[0]['num']);
        //在职人员激活数
        $sql = "SELECT COUNT(1) as num FROM `t_employee` AS e LEFT JOIN `t_personal` AS p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` AS u ON p.`user_id`=u.`user_id` WHERE e.`del_status`=0 AND e.`status`!=5 AND u.`is_login_app`=1 AND u.`activate_date`<='" . $eDate  . "';";
        $arr = M()->query($sql);
        $num15 = intval($arr[0]['num']);
        //查看工资条功能使用人员数
        $sql = "SELECT COUNT(1) as num FROM `dsy_salary_user` AS su LEFT JOIN `dsy_salary_record` AS sr ON sr.`id`=su.`rid` WHERE su.`isread`=1 AND su.`time`>='" . $sDate . "' AND su.`time`<='" . $eDate . "' AND sr.`cid` NOT IN (" . $this->cids . ") GROUP BY su.`mobile`;";
        $arr = M()->query($sql);
        $num3 = count($arr);
        //新注册企业数
        $sql = "SELECT COUNT(1) as num FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`create_time`>='" . $sDate . "' AND c.`create_time`<='" . $eDate . "' AND c.`corporate_id` NOT IN (" . $this->cids . ");";
        $arr = M()->query($sql);
        $num4 = intval($arr[0]['num']);
        //考勤功能使用企业数
        $sql = "SELECT COUNT(1) as num FROM `dsy_attendance_record` AS ar LEFT JOIN `t_employee` AS e ON ar.`uid`=e.`employee_id` WHERE ar.`atime`>='" . $sDate . "' AND ar.`atime`<='" . $eDate . "' AND e.`corporate_id` NOT IN (" . $this->cids . ") GROUP BY e.`corporate_id`;";
        $arr = M()->query($sql);
        $num5 = count($arr);
        //上传工资条功能使用企业数
        $sql = "SELECT COUNT(1) as num FROM `dsy_salary_record` WHERE `stype`=2 AND `time`>='" . $sDate . "' AND `time`<='" . $eDate . "' AND `cid` NOT IN (" . $this->cids . ") GROUP BY `cid`;";
        $arr = M()->query($sql);
        $num6 = count($arr);
        //使用工资条功能使用企业数
        $sql = "SELECT COUNT(1) as num FROM `dsy_salary_user` AS su LEFT JOIN `dsy_salary_record` AS sr ON sr.`id`=su.`rid` WHERE su.`isread`=1 AND su.`time`>='" . $sDate . "' AND su.`time`<='" . $eDate . "' AND sr.`cid` NOT IN (" . $this->cids . ") GROUP BY sr.`cid`;";
        $arr = M()->query($sql);
        $num7 = count($arr);
        //社保功能使用企业数
        $sql = "SELECT COUNT(1) as num FROM `t_social_payment` WHERE `create_time`>='" . $sDate . "' AND `create_time`<='" . $eDate . "' AND `corporate_id` NOT IN (" . $this->cids . ") GROUP BY `corporate_id`;";
        $arr = M()->query($sql);
        $num8 = count($arr);
        //薪资功能使用企业数
        $sql = "SELECT COUNT(1) as num FROM `t_salary_check_computation` WHERE `status`=0 AND `updatetime`>='" . $sDate . "' AND `updatetime`<='" . $eDate . "' AND `cid` NOT IN (" . $this->cids . ") GROUP BY `cid`;";
        $arr = M()->query($sql);
        $num9 = count($arr);

        $viewWhere = [];
        $viewWhere['create_date'][] = ['egt', $sDate];
        $viewWhere['create_date'][] = ['elt', $eDate];
        //统计用户量【IOS】
        $num12 = getViewsUser(1, $viewWhere);
        //统计用户量【安卓】
        $num13 = getViewsUser(2, $viewWhere);
        //统计访问次数
        $views = getViews($viewWhere);

        //审批功能使用企业数
        $sql = "SELECT COUNT(1) as num FROM `t_mysubeaa` WHERE `time`>='" . $sDate . "' AND `time`<='" . $eDate . "' AND `cid` NOT IN (" . $this->cids . ") GROUP BY `cid`;";
        $arr = M()->query($sql);
        $num19 = count($arr);
        //审批功能使用人员数
        $sql = "SELECT COUNT(1) as num FROM `t_mysubeaa` WHERE `time`>='" . $sDate . "' AND `time`<='" . $eDate . "' AND `cid` NOT IN (" . $this->cids . ") GROUP BY `uid`;";
        $arr = M()->query($sql);
        $num20 = count($arr);

        //纸质合同和电子合同统计
        $sql = "SELECT c.corporate_name,count(*) num from dsy_contract co 
left join t_corporate c on co.party1_id = c.corporate_id 
WHERE co.`create_time`>='" . $sDate . "' AND co.`create_time`<='" . $eDate . "' AND co.sign_state in (1,6) and co.del_status = 0 and contract_state !=1 and co.sign_method = 1 AND c.`corporate_id` NOT IN (" . $this->cids . ") 
GROUP BY c.`corporate_id`;";
        $arr = M()->query($sql);
        $num25 = array_sum(array_column($arr,'num'));
        $num26 = count($arr);
        $sql = "SELECT c.corporate_name,count(*) num from dsy_contract co 
left join t_corporate c on co.party1_id = c.corporate_id 
WHERE co.`create_time`>='" . $sDate . "' AND co.`create_time`<='" . $eDate . "' AND co.sign_state in (1,6) and co.del_status = 0 and contract_state !=1 and co.sign_method = 0 AND c.`corporate_id` NOT IN (" . $this->cids . ") 
GROUP BY c.`corporate_id`;";
        $arr = M()->query($sql);
        $num27 = array_sum(array_column($arr,'num'));
        $num28 = count($arr);

        $data = [
            'num1' => $num1,
            'num2' => $num2,
            'num3' => $num3,
            'num4' => $num4,
            'num5' => $num5,
            'num6' => $num6,
            'num7' => $num7,
            'num8' => $num8,
            'num9' => $num9,
            'num10' => $num10,
            'num12' => $num12,
            'num13' => $num13,
            'num14' => $num14,
            'num15' => $num15,
            'num16' => $num16,
            'num17' => $views[1],
            'num18' => $views[2],
            'num19' => $num19,
            'num20' => $num20,
            'num25' => $num25,
            'num26' => $num26,
            'num27' => $num27,
            'num28' => $num28,
        ];
        echo json_encode($data);
    }

    /**
     * 统计 全部订单：支付成功且未退款
     */
    public function orderExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

        $where = [];
        $where['order_type'] = ['in', '1,2,3,4,5'];
        $where['status'] = ['in', '2,4,5,7'];
        $where['time'][] = ['egt', $sDate];
        $where['time'][] = ['elt', $eDate];

        $m_o_s_model = M('mall_order_specifications');
        $c_a_model = M('company_activity');
        $c_p_model = M('company_package');
        $m_p_model = M('mall_product');
        $u_model = M('user', 't_');
        $list = M('mall_order')
            ->where($where)
            ->field('`ordernum`,`status`,`payprice`,`paytime`,`time`,`name`,`mobile`,`address`,`uid`,`etype`,`enum`,`sid`,`pid`')
            ->order('`time` desc')
            ->select();

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(40);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(40);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(60);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '订单编号');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '下单时间');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '支付时间');
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, '商品名称');
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, '订单金额');
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, '买家姓名');
        $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, '买家手机号');
        $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, '所属企业');
        $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, '收货人姓名');
        $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, '收货人手机');
        $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, '收货地址');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':K' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':K' . $i)->getFont()->setBold(true);

        foreach ($list as $k => $v) {
            $i++;
            if (empty($v['pid'])) {
                continue;
            }
            $products = $m_p_model->where(['id' => ['in', $v['pid']]])->getField('name', true);
            $user = $u_model
                ->join('AS u LEFT JOIN t_personal AS p ON p.`user_id`=u.`user_id`')
                ->join('LEFT JOIN t_employee AS e ON e.`personal_id`=p.`personal_id`')
                ->join('LEFT JOIN t_corporate AS c ON c.`corporate_id`=e.`corporate_id`')
                ->where(['u.`user_id`' => intval($v['uid'])])
                ->field('u.`user_name`,p.`name`,c.`corporate_name`')
                ->find();
            $mos = $m_o_s_model->where(['ordernum' => $v['ordernum'], 'is_activity' => 2, 'act_type' => 1])->field('`rid`,`tid`,`act_type`')->find();
            if (!empty($mos)) {
                $activity = $c_a_model->where(['id' => $mos['rid']])->field('`get_type`')->find();
                if (!empty($activity)) {
                    if ($activity['get_type'] != 3) {
                        $package = $c_p_model->where(['id' => $mos['tid']])->field('`price`')->find();
                        if (!empty($package)) {
                            $v['payprice'] = $package['price'];
                        }
                    }
                }
            }
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, 'sn:' . $v['ordernum']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['time']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['paytime']);
            $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, implode("\n", $products));
            $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, $v['payprice']);
            $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, empty($user['name']) ? '游客' : $user['name']);
            $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, $user['user_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, empty($user['corporate_name']) ? '--' : $user['corporate_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, $v['name']);
            $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, $v['mobile']);
            $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, $v['address']);
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('E' . $i . ':G' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('I' . $i . ':J' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '统计有效订单' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 统计 商品购买排行：支付成功且未退款
     */
    public function goodsTopExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

        $where = [];
        $where['mo.`order_type`'] = ['in', '1,2,3,4,5'];
        $where['mo.`status`'] = ['in', '2,4,5,7'];
        $where['mo.`time`'][] = ['egt', $sDate];
        $where['mo.`time`'][] = ['elt', $eDate];

        $m_p_model = M('mall_product');
        $list = M('mall_order_specifications')
            ->join('AS mos LEFT JOIN dsy_mall_order AS mo ON mo.`ordernum`=mos.`ordernum`')
            ->where($where)
            ->field('mos.`pid`,SUM(mos.`num`) AS buy_num')
            ->group('mos.`pid`')
            ->order('buy_num DESC,mos.`pid` ASC')
            ->select();

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(40);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '商品编号');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '商品名称');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '销量');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);

        foreach ($list as $k => $v) {
            $i++;
            $products = $m_p_model->where(['id' => $v['pid']])->field('`skuid`,`name`')->find();
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, 'sku:' . $products['skuid']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $products['name']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['buy_num']);
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '商品购买排行' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 统计 商家员工
     */
    public function shopExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

//        $p_model = M('personal', 't_');
        $sql = "SELECT c.`corporate_id`,c.`corporate_name`,c.`contacts_name`,c.`user_id` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`corporate_id` ASC;";
        $list = M()->query($sql);

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(15);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '企业');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '联系人');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '总人数');
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, '总激活');
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, '总离职');
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, '当前新增');
        $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, '当前新增中激活');
        $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, '当前激活');
        $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, '当前离职');
        $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, '在职人数');
        $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, '在职人数中激活');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':K' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':K' . $i)->getFont()->setBold(true);

        foreach ($list as $k => $v) {
            $i++;
            $sql = 'SELECT COUNT(1) as num FROM `t_employee` AS e LEFT JOIN `t_personal` AS p ON e.`personal_id`=p.`personal_id` LEFT JOIN `t_user` AS u ON p.`user_id`=u.`user_id` WHERE e.`corporate_id`=' . $v['corporate_id'] . ' AND e.`del_status`=0';
            $arr1 = M()->query($sql);
            $arr2 = M()->query($sql . ' AND u.`is_login_app`=1');
            $arr3 = M()->query($sql . ' AND e.`status`=5');
            $arr4 = M()->query($sql . " AND u.`register_time`>='" . $sDate . "' AND u.`register_time`<='" . $eDate . "'");
            $arr5 = M()->query($sql . " AND u.`is_login_app`=1 AND u.`register_time`>='" . $sDate . "' AND u.`register_time`<='" . $eDate . "'");
            $arr9 = M()->query($sql . " AND u.`is_login_app`=1 AND u.`activate_date`>='" . $sDate . "' AND u.`activate_date`<='" . $eDate . "'");
            $arr6 = M()->query($sql . " AND e.`status`=5 AND e.`leave_time`>='" . $sDate . "' AND e.`leave_time`<='" . $eDate . "'");
            $arr7 = M()->query($sql . ' AND e.`status`!=5');
            $arr8 = M()->query($sql . ' AND e.`status`!=5 AND u.`is_login_app`=1');
//            $personal = $p_model->where(['user_id' => $v['user_id']])->field('`name`')->find();
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $v['corporate_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['contacts_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $arr1[0]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, $arr2[0]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, $arr3[0]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, $arr4[0]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, $arr5[0]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, $arr9[0]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, $arr6[0]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, $arr7[0]['num']);
            $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, $arr8[0]['num']);
            $objPHPExcel->getActiveSheet()->getStyle('B' . $i . ':K' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '统计企业' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 统计 新注册的商家
     */
    public function newShopExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

//        $p_model = M('personal', 't_');
        $sql = "SELECT c.`corporate_id`,c.`corporate_name`,c.`contacts_name`,c.`user_id`,c.`create_time` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`create_time`>='" . $sDate . "' AND c.`create_time`<='" . $eDate . "' AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`create_time` DESC;";
        $list = M()->query($sql);

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '企业');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '联系人');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '注册时间');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);

        foreach ($list as $k => $v) {
            $i++;
//            $personal = $p_model->where(['user_id' => $v['user_id']])->field('`name`')->find();
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $v['corporate_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['contacts_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['create_time']);
            $objPHPExcel->getActiveSheet()->getStyle('B' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '新注册企业' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 统计 企业审批
     */
    public function examineExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

//        $p_model = M('personal', 't_');
        $sql = "SELECT c.`corporate_id`,c.`corporate_name`,c.`contacts_name`,c.`user_id` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`corporate_id` ASC;";
        $list = M()->query($sql);

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('Q')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('R')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('S')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('T')->setWidth(10);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '企业');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '联系人');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '请假');
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, '离职');
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, '转正');
        $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, '续签');
        $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, '薪酬');
        $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, '考勤');
        $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, '加班');
        $objPHPExcel->getActiveSheet()->setCellValue('J' . $i, '换班');
        $objPHPExcel->getActiveSheet()->setCellValue('K' . $i, '办事预约');
        $objPHPExcel->getActiveSheet()->setCellValue('L' . $i, '外出办事');
        $objPHPExcel->getActiveSheet()->setCellValue('M' . $i, '出差');
        $objPHPExcel->getActiveSheet()->setCellValue('N' . $i, '盖章预约');
        $objPHPExcel->getActiveSheet()->setCellValue('O' . $i, '通用审批');
        $objPHPExcel->getActiveSheet()->setCellValue('P' . $i, '调岗');
        $objPHPExcel->getActiveSheet()->setCellValue('Q' . $i, '销假');
        $objPHPExcel->getActiveSheet()->setCellValue('R' . $i, '协议');
        $objPHPExcel->getActiveSheet()->setCellValue('S' . $i, '报销');
        $objPHPExcel->getActiveSheet()->setCellValue('T' . $i, '借款');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':T' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':T' . $i)->getFont()->setBold(true);

        $arrCol = [1 => 'C', 18 => 'D', 14 => 'E', 4 => 'F', 5 => 'G', 6 => 'H', 7 => 'I', 8 => 'J', 9 => 'K', 10 => 'L', 11 => 'M', 12 => 'N', 13 => 'O', 15=> 'P',16 =>'Q',17 => 'R', 19=>'S',20=>'T'];

        foreach ($list as $k => $v) {
            $i++;
            $arr1 = M()->query("SELECT `etype`,COUNT(1) as num FROM `t_mysubeaa` WHERE `cid`=" . $v['corporate_id'] . " AND `time`>='" . $sDate . "' AND `time`<='" . $eDate . "' GROUP BY `etype`;");
//            $personal = $p_model->where(['user_id' => $v['user_id']])->field('`name`')->find();
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $v['corporate_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['contacts_name']);
            foreach ($arr1 as $item) {
                if (isset($arrCol[$item['etype']]))
                    $objPHPExcel->getActiveSheet()->setCellValue($arrCol[$item['etype']] . $i, $item['num']);
            }
            $objPHPExcel->getActiveSheet()->getStyle('B' . $i . ':T' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '企业审批' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 统计 员工查看工资条的企业
     */
    public function socialPlanExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

//        $p_model = M('personal', 't_');
        $sql = "SELECT c.`corporate_id`,c.`corporate_name`,c.`contacts_name`,c.`user_id` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`corporate_id` ASC;";
        $list = M()->query($sql);

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
        $i = 1;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '企业');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '联系人');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '使用人数');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);

        foreach ($list as $k => $v) {
            $i++;
            $arr1 = M()->query("SELECT COUNT(1) as num FROM `dsy_salary_user` AS su LEFT JOIN `dsy_salary_record` AS sr ON sr.`id`=su.`rid` WHERE su.`isread`=1 AND sr.`cid`=" . $v['corporate_id'] . " AND su.`time`>='" . $sDate . "' AND su.`time`<='" . $eDate . "' GROUP BY su.`mobile`;");
//            $personal = $p_model->where(['user_id' => $v['user_id']])->field('`name`')->find();
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $v['corporate_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['contacts_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, count($arr1));
            $objPHPExcel->getActiveSheet()->getStyle('B' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '员工查看工资条' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 统计 创建社保方案的企业
     */
    public function functionShopExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

        //考勤功能使用企业数
        $sql = "SELECT e.`corporate_id` AS cid FROM `dsy_attendance_record` AS ar LEFT JOIN `t_employee` AS e ON ar.`uid`=e.`employee_id` WHERE ar.`atime`>='" . $sDate . "' AND ar.`atime`<='" . $eDate . "' AND e.`corporate_id` NOT IN (" . $this->cids . ") GROUP BY e.`corporate_id`,e.`employee_id`;";
        $total = M()->query($sql);
        $total = array_count_values(array_column($total, 'cid'));
        $nums = [];
        $coms = [];
        foreach ($total as $k => $v) {
            $nums[] = $v;
            $coms[] = ['cid' => $k, 'num' => $v];
        }
        array_multisort($nums, SORT_DESC, $coms);
        $arr[] = ['title' => '考勤', 'list' => $coms];
        //上传工资条功能使用企业数
        $sql = "SELECT a.`cid`,COUNT(DISTINCT a.id) as num FROM `dsy_salary_record` AS a LEFT JOIN `dsy_salary_user` AS b on b.`rid`=a.`id` WHERE a.`time`>='" . $sDate . "' AND a.`time`<='" . $eDate . "' AND a.`cid` NOT IN (" . $this->cids . ") AND b.`status` = 1 GROUP BY a.`cid` ORDER BY num DESC;";
        $arr[] = ['title' => '上传工资条', 'list' => M()->query($sql)];
        //创建社保方案功能使用企业数
        $sql = "SELECT `corporate_id` AS cid,COUNT(1) as num FROM `t_social_plan` WHERE `updatetime`>='" . $sDate . "' AND `updatetime`<='" . $eDate . "' AND `corporate_id` NOT IN (" . $this->cids . ") GROUP BY `corporate_id` ORDER BY num DESC;";
        $arr[] = ['title' => '创建社保方案', 'list' => M()->query($sql)];
        //社保功能使用企业数
        $sql = "SELECT `corporate_id` AS cid,COUNT(1) as num FROM `t_social_payment` WHERE `create_time`>='" . $sDate . "' AND `create_time`<='" . $eDate . "' AND `corporate_id` NOT IN (" . $this->cids . ") GROUP BY `corporate_id` ORDER BY num DESC;";
        $arr[] = ['title' => '社保', 'list' => M()->query($sql)];
        //薪资功能使用企业数
        $sql = "SELECT `corporate_id` AS `cid`,COUNT(1) as num FROM `t_salary_payment` WHERE `status`=1 AND `create_time`>='" . $sDate . "' AND `create_time`<='" . $eDate . "' AND `corporate_id` NOT IN (" . $this->cids . ") GROUP BY `corporate_id` ORDER BY num DESC;";
        $arr[] = ['title' => '薪资', 'list' => M()->query($sql)];
        //合同功能使用企业数
        $sql = "SELECT `party1_id` AS cid,COUNT(1) as num FROM `dsy_contract` WHERE `del_status`=0 AND `create_time`>='" . $sDate . "' AND `create_time`<='" . $eDate . "' AND `party1_id` NOT IN (" . $this->cids . ") GROUP BY `party1_id` ORDER BY num DESC;";
        $arr[] = ['title' => '合同', 'list' => M()->query($sql)];
        //电子合同
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        $sql = "SELECT c.corporate_name,count(*) num,c.contacts_name
        FROM dsy_contract co
        LEFT JOIN t_corporate c ON co.party1_id = c.corporate_id 
        WHERE co.del_status = 0 AND contract_state !=1 AND co.sign_method = 0 AND sign_state IN (1,6)
        AND date(co.create_time) BETWEEN '$sDate' AND '$eDate'
        GROUP BY c.corporate_id ";
        $brr[] = M()->query($sql);
        //纸质合同
        $sql = "SELECT c.corporate_name,count(*) num,c.contacts_name
        FROM dsy_contract co 
        LEFT JOIN t_corporate c ON co.party1_id = c.corporate_id 
        WHERE co.sign_state IN (1,6) AND co.del_status = 0 AND contract_state !=1 AND co.sign_method = 1 
        AND date(co.create_time) BETWEEN '$sDate' AND '$eDate'
        GROUP BY c.corporate_id ";
        $brr[] = M()->query($sql);
        //工资条短信次数
        $sql = "SELECT c.corporate_name,count(*) num,c.contacts_name
        FROM dsy_salary_record_message sr 
        LEFT JOIN dsy_salary_record r ON sr.rid = r.id
        LEFT JOIN t_corporate c ON r.cid = c.corporate_id 
        WHERE date(sr.create_time) BETWEEN '$sDate' AND '$eDate' 
        GROUP BY c.corporate_id";
        $brr[] = M()->query($sql);
        //入职+续签数据统计
        $sql = "SELECT c.corporate_name,((SELECT count(*) 
        FROM t_employee e LEFT JOIN t_personal p ON e.personal_id = p.personal_id 
        WHERE e.corporate_id = c.corporate_id AND e.status != 5 AND e.del_status = 0 AND e.entry_time BETWEEN '$sDate' AND '$eDate'  
        AND e.emp_type = 0 AND !FIND_IN_SET(p.mobile,c.contacts_mobile) ) +(SELECT count(*) 
        FROM dsy_contract dc 
        LEFT JOIN t_personal p ON dc.party2_id = p.personal_id 
        left join t_employee e ON p.personal_id = e.personal_id   
        WHERE dc.del_status = 0 AND dc.sign_state IN (1,6) AND dc.end_time BETWEEN '$sDate' AND '$eDate' AND 
        e.status != 5 AND e.del_status = 0 AND e.entry_time < '$sDate' AND dc.party1_id = c.corporate_id))
        totalPer
        ,c.contacts_name FROM t_corporate c";
        $brr[] = M()->query($sql);
        $c_model = M('corporate', 't_');

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->createSheet(0);
        $objPHPExcel->setactivesheetindex(0);
        $objPHPExcel->getActiveSheet()->setTitle('功能使用');
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
        $i = 0;
        foreach ($arr as $k => $item) {
            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $item['title']);
            $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':C' . $i);//合并单元格
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//文字居中
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);//字体加粗
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);//填充样式
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFill()->getStartColor()->setARGB('FFFFF100');//填充颜色
            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '企业');
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '联系人');
            if ($k == 0) {
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '使用人数');
            } else {
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '使用次数');
            }
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);
            $list = $item['list'];
            if (!empty($list)) {
                foreach ($list as $v) {
                    $i++;
                    $corporate = $c_model->where(['corporate_id' => $v['cid']])->field('`corporate_name`,`contacts_name`')->find();
                    $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $corporate['corporate_name']);
                    $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $corporate['contacts_name']);
                    $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['num']);
                    $objPHPExcel->getActiveSheet()->getStyle('B' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                }
            } else {
                $i++;
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '暂无记录');
                $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':C' . $i);
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            }
        }
        $title = ['电子合同','纸质合同','工资条短信次数','入职+续签数据统计'];
        foreach($brr as $key => $val){
            $objPHPExcel->createSheet($key+1);
            $objPHPExcel->setactivesheetindex($key+1);
            $objPHPExcel->getActiveSheet()->setTitle($title[$key]);
            $objPHPExcel->getActiveSheet()->setCellValue('A1','企业');
            $objPHPExcel->getActiveSheet()->setCellValue('B1','数量');
            $objPHPExcel->getActiveSheet()->setCellValue('C1','联系人');
            $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
            $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
            $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
            $objPHPExcel->getActiveSheet()->getStyle('A1:C1')->getFont()->setBold(true);
            $k=2;
            $count = count($val);
            if($count != 0){
                for($i = 0;$i < $count;$i++){
                    $j = 0;
                    foreach($val[$i] as $ky=>$vl){
                        $objPHPExcel->getActiveSheet()->setCellValue($letter[$j] . $k, $vl);
                        $j++;
                    }
                    $k++;
                }
            }else{
                $objPHPExcel->getActiveSheet()->setCellValue('A' . 2, '暂无记录');
                $objPHPExcel->getActiveSheet()->mergeCells('A' . 2 . ':C' . 2);
            }
            $objPHPExcel->getActiveSheet()->getStyle('A1:C' . $k)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A1:C' . $k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '功能使用企业' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

    /**
     * 统计 创建社保方案的企业
     */
    public function viewLogExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

        //天数，没有包括当天，真正的天数还需加一，但这边开发需要就没加
        $days = ((strtotime($endDate) - strtotime($startDate)) / 86400);
        if ($days > 30) {
            die('时间段控制在31天内哦');
        }
        //表列头
        $chars = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG'];
        $char = $chars[$days];

        $sql = "SELECT c.`corporate_id`,c.`corporate_name`,c.`contacts_name`,c.`user_id` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`corporate_id` ASC;";
        $list = M()->query($sql);
        array_push($list, ['corporate_id' => 0, 'corporate_name' => '游客', 'contacts_name' => '-']);

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        for ($day = 0; $day <= $days; $day++)
            $objPHPExcel->getActiveSheet()->getColumnDimension($chars[$day])->setWidth(20);

        $i = 1;
//        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '日活量人数统计说明：总数=小程序+IOS+安卓');
//        $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':' . $char . $i);
//        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':' . $char . $i)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);//填充样式
//        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':' . $char . $i)->getFill()->getStartColor()->setARGB('FFFF00');//填充颜色
//        $i++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '企业');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '联系人');
        for ($day = 0; $day <= $days; $day++)
            $objPHPExcel->getActiveSheet()->setCellValue($chars[$day] . $i, date('Y-m-d', strtotime($sDate) + ($day * 86400)));
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':' . $char . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':' . $char . $i)->getFont()->setBold(true);

        foreach ($list as $k => $v) {
            $i++;

            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $v['corporate_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $v['contacts_name']);
            //每天统计
            if ($v['corporate_id'] == 0)
                $sql1 = " AND vl.`eid`=0";
            else
                $sql1 = " AND e.`corporate_id`=" . $v['corporate_id'];
            for ($day = 0; $day <= $days; $day++) {
                $date = date('Y-m-d', strtotime($sDate) + ($day * 86400));
                $sql = "SELECT vl.`eid`,COUNT(1) AS num FROM `dsy_view_log` AS vl LEFT JOIN `t_employee` AS e ON e.`employee_id`=vl.`eid` WHERE LEFT(vl.`create_date`,10)='" . $date . "'" . $sql1;
                $arr1 = M()->query($sql . ' AND vl.`etype` IN(1,2) GROUP BY vl.`eid`;');
                $objPHPExcel->getActiveSheet()->setCellValue($chars[$day] . $i, count($arr1) );
            }
            $objPHPExcel->getActiveSheet()->getStyle('B' . $i . ':' . $char . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '企业员工访问量' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }
    public function employeeExcel(){
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';
        //获取入职人员信息
        $title[0][] = ['姓名','手机号','公司名称','联系人','入职时间','是否激活'];
        $sql = "SELECT p.name,p.mobile,c.corporate_name,c.contacts_name,date(e.entry_time),if(u.is_login_app=1,'激活','未激活') AS act FROM t_employee e 
                LEFT JOIN t_personal p ON e.personal_id = p.personal_id 
                LEFT JOIN t_corporate c ON e.corporate_id = c.corporate_id 
                LEFT JOIN t_user u ON p.user_id = u.user_id
                WHERE e.status != 5 AND e.del_status = 0 AND e.entry_time BETWEEN '$sDate' AND '$eDate' AND e.emp_type = 0 AND !FIND_IN_SET(p.mobile,c.contacts_mobile) 
                ORDER BY c.corporate_id";
        $result = M()->query($sql);
        foreach($result as $val){
            $title[0][] = array_values($val);
        }
        //获取续签人员明细
        $title[1][] = ['姓名','手机号','公司名称','联系人','入职时间','合同到期时间'];
        $sql = "SELECT distinct p.name,p.mobile,c.corporate_name,c.contacts_name,date(e.entry_time),date(dc.end_time)
                FROM dsy_contract dc
                LEFT JOIN t_personal p ON dc.party2_id = p.personal_id 
                LEFT JOIN t_employee e ON p.personal_id = e.personal_id
                LEFT JOIN t_corporate c ON e.corporate_id = c.corporate_id
                WHERE dc.del_status = 0 AND dc.sign_state IN (1,6) AND dc.end_time BETWEEN '$sDate' AND '$eDate'
                AND e.status != 5 AND e.del_status = 0 AND e.entry_time < '$sDate'
                ORDER BY c.corporate_id";
        $result = M()->query($sql);
        foreach($result as $val){
            $title[1][] = array_values($val);
        }
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        foreach($title as $key=>$val){
            $objPHPExcel->createSheet($key);
            $objPHPExcel->setactivesheetindex($key);
            if($key == 0){
                $objPHPExcel->getActiveSheet()->setTitle('入职');
            }else{
                $objPHPExcel->getActiveSheet()->setTitle('续签');
            }
            $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(15);
            $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
            $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(50);
            $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
            $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
            $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
            $k = 1;
            $count = count($title[$key]);
            $count_title = count($title[$key][0]) - 1; 
            $objPHPExcel->getActiveSheet()->getStyle('A1' . ':' . $letter[$count_title] . 1)->getFont()->setBold(true);
            for($i = 0;$i < $count;$i++){
                foreach($title[$key][$i] as $ky => $vl){
                    $objPHPExcel->getActiveSheet()->setCellValue($letter[$ky] . $k, $vl);
                }
                $k++;
            }
            if($count == 1){
                $objPHPExcel->getActiveSheet()->setCellValue('A' . 2, '暂无记录');
                $objPHPExcel->getActiveSheet()->mergeCells('A' . 2 . ':'. $letter[$count_title] . 2);
            }
            $objPHPExcel->getActiveSheet()->getStyle('A1:F' . $k)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A1:F' . $k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '新入职员工和续签员工明细' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }
    /**
     * 商城统计
     */
    public function mallExcel()
    {
        $this->display();
    }

    /**
     * 统计
     */
    public function mallBalance()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';

        $viewWhere = [];
        $viewWhere['create_date'][] = ['egt', $sDate];
        $viewWhere['create_date'][] = ['elt', $eDate];
        $num1 = getMallViewsUser(0, $viewWhere);
        $num3 = getMallViewsUser(1, $viewWhere);
        $num5 = getMallViewsUser(2, $viewWhere);
        //统计访问次数
        $views = getMallViews($viewWhere);
        // //下单数
        // $where = [];
        // $where['order_type'] = ['in', '1,2,3,4,5'];
        // $where['status'] = ['in', '2,4,5,7'];
        // $where['time'][] = ['egt', $sDate];
        // $where['time'][] = ['elt', $eDate];
        // $num7 = M('mall_order')->where($where)->field('`ordernum`')->count();
        $shoporder = A('ShopOrder');
        $shop_sn_order = $shoporder->order_sn_data($sDate,$eDate,'');
        $shop_jd_order = $shoporder->order_jd_data($sDate,$eDate,'');
        $shop_sy_order = $shoporder->order_sy_data($sDate,$eDate,'');
        $num7 = $shop_sn_order['count_num'] + $shop_jd_order['count_num'] + $shop_sy_order['count_num'];
        $num8 = $shop_sn_order['count_price'] + $shop_jd_order['count_price'] + $shop_sy_order['count_price'];
        $data = [
            'num1' => $num1,
            'num2' => $views[0],
            'num3' => $num3,
            'num4' => $views[1],
            'num5' => $num5,
            'num6' => $views[2],
            'num7' => $num7,
            'num8' => $num8,
            'num9' => $shop_sn_order['count_num'],
            'num10' => $shop_sn_order['count_price'],
            'num11' => $shop_jd_order['count_num'],
            'num12' => $shop_jd_order['count_price'],
            'num13' => $shop_sy_order['count_num'],
            'num14' => $shop_sy_order['count_price'],
        ];
        echo json_encode($data);
    }
    public  function statistics(){
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE');
        header('Access-Control-Allow-Headers:x-requested-with,content-type');
        $a = ['+','+','-','+','+','+','-','-'];
        //今日浏览量
        $data['views']['num'] = mt_rand(2000,5000);
        $viewsRand = mt_rand(0,7);
        $data['views']['RiseOrFall'] = $a[$viewsRand];
        $data['views']['percentage'] = mt_rand(10,30) . '%';
        //今日订单数
        $data['orderNum']['num'] = mt_rand(20,50);
        $orderNumRand = mt_rand(0,7);
        $data['orderNum']['RiseOrFall'] = $a[$orderNumRand];
        $data['orderNum']['percentage'] = mt_rand(10,30) . '%';
        //今日销售额
        $data['orderPriceNum']['num'] = mt_rand(2000,8000);
        $orderPriceNumRand = mt_rand(0,7);
        $data['orderPriceNum']['RiseOrFall'] = $a[$orderPriceNumRand];
        $data['orderPriceNum']['percentage'] = mt_rand(10,30) . '%';
        //实时订单
        $order = M('mall_order')->field('ordernum,pid,name')->order('time desc')->page(0,10)->select();
        foreach($order as $key=>$val){
            $product = M('mall_order_specifications')->field('pro_name')->where(['ordernum'=>$val['ordernum'],'pid'=>array('in',$val['pid'])])->find();
            $data['order'][$key]['name'] = substr_replace($val['name'],'****',3);
            $data['order'][$key]['pro_name'] = $product['pro_name'];
        }
        //跟团游
        $data['travelNum'] = M('mall_order')->where(['status'=>array('in',[2,4,5,6,7,8])])->count();;
        //话费充值
        $data['mobileNum'] = M('mobile_order')->count();
        //油卡充值
        $data['oilNum'] = M('oil_order')->count();
        //购物
        $data['shopNum'] = M('mall_order')->count();
        //实时在线人数
        $data['viewsNum'] = mt_rand(1000,3000);
        //今日注册数register_time
        $key = 6;
        for($i = 0;$i < 7;$i++){
            $day =  date('Y-m-d',strtotime("-$i day",time()));
            $userCount = M('user','t_')->where(['register_time'=>array('like',"$day%")])->count();
            $data['registerNum'][$key]['day'] = date('m-d',strtotime($day));
            $data['registerNum'][$key]['num'] = $userCount * 3;
            $key--;
        }
        $data['registerNum'] = array_reverse(array_values($data['registerNum']));
        //获取商城每月成交订单量
        $time[0]['start'] = date('Y-m-d H:i:s',mktime(0,0,0,date('m'),1,date('Y')));
        $time[0]['end'] = date('Y-m-d H:i:s',mktime(23,59,59,date('m'),date('t'),date('Y')));
        for($i = 1;$i < 12;$i++){
            $time[$i]['start'] = date('Y-m-d H:i:s',strtotime("-$i month",mktime(0,0,0,date('m'),1,date('Y'))));
            $time[$i]['end'] = date('Y-m-d H:i:s',strtotime("-$i month",mktime(23,59,59,date('m'),date('t'),date('Y'))));
        }
        $time = array_reverse($time);
        foreach($time as  $key=>$val){
            $data['productMonthNum'][$key]['Ym'] = date('Y-m',strtotime($val['start']));
            $count = M('mall_order')->where(['time'=>array('between',array($val['start'],$val['end']))])->count();
            $data['productMonthNum'][$key]['num'] = $count * 10;
        }
        $data['productMonthNum'] = array_values($data['productMonthNum']);
        $product = M('mall_order_specifications')
                    ->alias('a')
                    ->field('c.name,count(a.id) as num')
                    ->join('left join dsy_mall_product as b on a.pid=b.id')
                    ->join('left join dsy_mall_flevel as c on b.flid=c.id')
                    ->where(['a.time'=>array('between',array($time[11]['start'],$time[11]['end']))])
                    ->where("b.flid != ''")
                    ->group('b.flid')
                    ->order('count(a.id) desc')
                    ->page(0,5)
                    ->select();
        $num = array_column($product,'num');
        $count = 0;
        foreach($num as $val){
            $count += $val;
        }
        foreach($product as $key => $val){
            $data['classification'][$key]['num'] = round($val['num'] / $count,2);
            $data['classification'][$key]['className'] = $val['name'];
            $percentage = $data['classification'][$key]['num'] * 100;
            $data['classification'][$key]['percentage'] = $percentage . '%';
        }
        if(empty($data['classification'])){
            $data['classification'][0]['num'] = 1;
            $data['classification'][0]['className'] = '数码/家电';
            $data['classification'][0]['percentage'] = '100%';
            $data['classification'] = array_values($data['classification']);
        }
        echo json_encode($data);die();
    }
    public function retainedExcel(){
        $start_time = I('startDate');
        $end_time = I('endDate');
        $title = '用户留存数据统计';
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_date'] = array('like',"%$start_time%");
                $title = $start_time . '-' . $end_time . '用户留存数据统计';
            }else{
                $startTime = $start_time.' 00:00:00';
                $endTime = $end_time.' 23:59:59';
                $where['a.create_date'] = array('between',array($startTime,$endTime));  
                $title = $start_time . '-' . $end_time . '用户留存数据统计';
            }
        }else{
            $day = date('Y-m');
            $where['a.create_date'] = array('like',"%$day%");
            $title = $day . '用户留存数据统计';
        }
        $result = M('mall_view_log')
            ->alias('a')
            ->field('d.user_name,c.corporate_name,a.etype,a.create_date,a.app_type,p.age,p.sex')
            ->join('LEFT JOIN t_employee as b on a.uid=b.user_id')
            ->join('LEFT JOIN t_corporate as c on b.corporate_id=c.corporate_id')
            ->join('LEFT JOIN t_user as d on a.uid=d.user_id')
            ->join('LEFT JOIN t_personal as p on b.personal_id = p.personal_id')
            ->where($where)
            ->group('a.uid,a.etype,a.app_type')
            ->select();
        $data[] = ['用户','年龄','性别','公司名称','类型','时间','访问来源'];
        $num = 1;
        foreach($result as $key=>$val){
            if($val['etype'] == 1){
                $type = '苹果';
            }elseif($val['etype'] == 2){
                $type = '安卓';
            }else{
                $type = '小程序';
            }
            if(!empty($val['sex'])){
                $val['sex'] = $val['sex'] == 1 ? '男' : '女';
            }
            $arr = [
                'user_name' => $val['user_name'],
                'age' => $val['age'],
                'sex' => $val['sex'],
                'corporate_name' => $val['corporate_name'],
                'etype' => $type,
                'create_date' => $val['create_date'],
                'app_type' => $val['app_type'] == 1 ? '人事邦APP' : '苏鹰商城APP'
            ];
            $data[$num++] = array_values($arr);
        }
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
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(5);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(5);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('E')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('F')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('G')->setWidth(25);
        $excel->getActiveSheet()->getStyle('A1:G1')->getFont()->setBold(true);
        for ($i = 1;$i <= count($data);$i++) {
        $j = 0;
        foreach ($data[$i-1] as $key=>$value) {
        
        if(!strpos($value,'Public/Uploads')){
            //文字生成
            $excel->getActiveSheet()->setCellValue("$letter[$j]$i","$value");
        }else{
        // 图片生成
        $objDrawing[$key] = new \PHPExcel_Worksheet_Drawing();
        $objDrawing[$key]->setPath("." . $value);
        // 设置宽度高度
        $objDrawing[$key]->setHeight(140);//照片高度
        $objDrawing[$key]->setWidth(210); //照片宽度
        /*设置图片要插入的单元格*/
        $objDrawing[$key]->setCoordinates("$letter[$j]$i");
        // 图片偏移距离
        // $objDrawing[$key]->setOffsetX(50);
        // $objDrawing[$key]->setOffsetY(10);
        $objDrawing[$key]->setWorksheet($excel->getActiveSheet());
        }
        $j++;
        }
        }
        $excel->getActiveSheet()->getStyle('A1:D' . $key)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
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
    public function shopsActionExcel(){
        $start_time = I('startDate');
        $end_time = I('endDate');
        $title = '行为数据统计';
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where_str = "like '%$start_time%'";
                $title = $start_time . '-' . $end_time . $title;
            }else{
                $startTime = $start_time.' 00:00:00';
                $endTime = $end_time.' 23:59:59';
                $where_str = "between '$startTime' AND '$endTime'";
                $title = $start_time . '-' . $end_time . $title;
            }
        }else{
            $day = date('Y-m');
            $where_str = "like '%$day%'";
            $title = $day . $title;
        }
        //分享事件
        $result = M('mall_share')
                    ->alias('a')
                    ->field('u.user_name,c.corporate_name,p.name,a.create_time')
                    ->join('left join t_user as u on a.uid = u.user_id')
                    ->join('left join t_employee as b on a.uid=b.user_id')
                    ->join('left join t_corporate as c on b.corporate_id=c.corporate_id')
                    ->join('left join dsy_mall_product as p on a.sku_id = p.skuid')
                    ->union("SELECT u.user_name,c.corporate_name,p.name,a.create_time FROM dsy_mall_share AS a
                            LEFT JOIN t_user AS u ON a.uid = u.user_id
                            LEFT JOIN t_employee AS b ON a.uid=b.user_id
                            LEFT JOIN t_corporate AS c ON b.corporate_id=c.corporate_id
                            LEFT JOIN dsy_jc_product AS p ON a.sku_id = p.sku_id
                            WHERE a.type = 4 AND a.create_time " . $where_str)
                    ->where('a.create_time '. $where_str . 'AND (a.type=1 OR a.type=2)')
                    ->group('a.create_time,a.uid')
                    ->select();
        $data[] = $result;
        //商品点击事件
        $result = M('mall_product_view')
                    ->alias('a')
                    ->field('u.user_name,c.corporate_name,p.name,a.create_date as create_time')
                    ->join('left join t_user as u on a.uid = u.user_id')
                    ->join('left join t_employee as b on a.uid=b.user_id')
                    ->join('left join t_corporate as c on b.corporate_id=c.corporate_id')
                    ->join('left join dsy_mall_product as p on a.pid = p.id')
                    ->where('a.create_date ' . $where_str)
                    ->group('a.create_date,a.uid')
                    ->select();
        $data[] = $result;
        //商城加购
        $result = M('mall_shopcart')
                    ->alias('a')
                    ->field('u.user_name,c.corporate_name,p.name,a.time as create_time')
                    ->join('left join t_user as u on a.uid = u.user_id')
                    ->join('left join t_employee as b on a.uid=b.user_id')
                    ->join('left join t_corporate as c on b.corporate_id=c.corporate_id')
                    ->join('left join dsy_mall_product as p on a.pid = p.id')
                    ->where('a.time ' . $where_str)
                    ->group('a.time,a.uid')
                    ->select();
        $data[] = $result;
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $sheet = ['分享事件','商品点击事件','商品加购事件'];
        foreach($data as $key => $val){
            $objPHPExcel->createSheet($key);
            $objPHPExcel->setactivesheetindex($key);
            $objPHPExcel->getActiveSheet()->setTitle($sheet[$key]);
            $objPHPExcel->getActiveSheet()->setCellValue('A1','用户');
            $objPHPExcel->getActiveSheet()->setCellValue('B1','公司名称');
            $objPHPExcel->getActiveSheet()->setCellValue('C1','商品名称');
            $objPHPExcel->getActiveSheet()->setCellValue('D1','时间');
            $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
            $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(30);
            $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(60);
            $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
            $objPHPExcel->getActiveSheet()->getStyle('A1:D1')->getFont()->setBold(true);
            $k=2;
            $count = count($val);
            if($count != 0){
                for($i = 0;$i < $count;$i++){
                    $j = 0;
                    foreach($val[$i] as $ky=>$vl){
                        $objPHPExcel->getActiveSheet()->setCellValue($letter[$j] . $k, $vl);
                        $j++;
                    }
                    $k++;
                }
            }else{
                $objPHPExcel->getActiveSheet()->setCellValue('A' . 2, '暂无记录');
                $objPHPExcel->getActiveSheet()->mergeCells('A' . 2 . ':D' . 2);
            }
            $objPHPExcel->getActiveSheet()->getStyle('A1:D' . $k)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
            $objPHPExcel->getActiveSheet()->getStyle('A1:D' . $k)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = $title;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }

        /**
     * 统计 创建社保方案的企业
     */
    public function functionNewShopExcel()
    {
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';
        $Date = [];
        if($startDate == $endDate){
            $Date[] = ['start' => $sDate, 'end' => $eDate ,'time'=>$startDate];
        }else{
            $dateNum = round((strtotime($eDate) - strtotime($sDate)) / 86400);
            $Date[] = ['start' => $startDate . ' 00:00:00', 'end' =>$endDate . ' 23:59:59','time'=> $startDate .'至'. $endDate];
            for($i = 0; $i < $dateNum;$i++){
                $time = date('Y-m-d',strtotime("+$i day",strtotime($startDate)));
                $start = $time . ' 00:00:00';
                $end = $time . ' 23:59:59';
                $Date[] = ['start' => $start,'end' =>  $end , 'time' => $time];
            }
        }
        $brr = [];
        foreach($Date as $key => $val){
            $arr = [];
            //考勤功能使用企业数
            $sql = "SELECT e.`corporate_id` AS cid FROM `dsy_attendance_record` AS ar LEFT JOIN `t_employee` AS e ON ar.`uid`=e.`employee_id` WHERE ar.`atime`>='" . $val['start'] . "' AND ar.`atime`<='" . $val['end'] . "' AND e.`corporate_id` NOT IN (" . $this->cids . ") GROUP BY e.`corporate_id`,e.`employee_id`;";
            $total = M()->query($sql);
            $total = array_count_values(array_column($total, 'cid'));
            $nums = [];
            $coms = [];
            foreach ($total as $k => $v) {
                $nums[] = $v;
                $coms[] = ['cid' => $k, 'num' => $v];
            }
            array_multisort($nums, SORT_DESC, $coms);
            $arr[] = ['title' => '考勤', 'list' => $coms];
            //上传工资条功能使用企业数
            $sql = "SELECT a.`cid`,COUNT(DISTINCT a.id) as num FROM `dsy_salary_record` AS a LEFT JOIN `dsy_salary_user` AS b on b.`rid`=a.`id` WHERE a.`time`>='" . $val['start'] . "' AND a.`time`<='" . $val['end'] . "' AND a.`cid` NOT IN (" . $this->cids . ") AND b.`status` = 1 GROUP BY a.`cid` ORDER BY num DESC;";
            $arr[] = ['title' => '上传工资条', 'list' => M()->query($sql)];
            //创建社保方案功能使用企业数
            $sql = "SELECT `corporate_id` AS cid,COUNT(1) as num FROM `t_social_plan` WHERE `updatetime`>='" . $val['start'] . "' AND `updatetime`<='" . $val['end'] . "' AND `corporate_id` NOT IN (" . $this->cids . ") GROUP BY `corporate_id` ORDER BY num DESC;";
            $arr[] = ['title' => '创建社保方案', 'list' => M()->query($sql)];
            //社保功能使用企业数
            $sql = "SELECT `corporate_id` AS cid,COUNT(1) as num FROM `t_social_payment` WHERE `create_time`>='" . $val['start'] . "' AND `create_time`<='" . $val['end'] . "' AND `corporate_id` NOT IN (" . $this->cids . ") GROUP BY `corporate_id` ORDER BY num DESC;";
            $arr[] = ['title' => '社保', 'list' => M()->query($sql)];
            //薪资功能使用企业数
            $sql = "SELECT `corporate_id` AS `cid`,COUNT(1) as num FROM `t_salary_payment` WHERE `status`=1 AND `create_time`>='" . $val['start'] . "' AND `create_time`<='" . $val['end'] . "' AND `corporate_id` NOT IN (" . $this->cids . ") GROUP BY `corporate_id` ORDER BY num DESC;";
            $arr[] = ['title' => '薪资', 'list' => M()->query($sql)];
            //纸质合同功能使用企业数
            $sql = "SELECT `party1_id` AS cid,COUNT(1) as num FROM `dsy_contract` WHERE `del_status`=0 AND `sign_method` = 1 AND `create_time`>='" . $val['start'] . "' AND `create_time`<='" . $val['end'] . "' AND `party1_id` NOT IN (" . $this->cids . ") GROUP BY `party1_id` ORDER BY num DESC;";
            $arr[] = ['title' => '纸质合同', 'list' => M()->query($sql)];
            //电子合同功能使用企业数
            $sql = "SELECT `party1_id` AS cid,COUNT(1) as num FROM `dsy_contract` WHERE `del_status`=0 AND `sign_method` = 0 AND `create_time`>='" . $val['start'] . "' AND `create_time`<='" . $val['end'] . "' AND `party1_id` NOT IN (" . $this->cids . ") GROUP BY `party1_id` ORDER BY num DESC;";
            $arr[] = ['title' => '电子合同', 'list' => M()->query($sql)];
            $brr[] = $arr;
        }
        $c_model = M('corporate', 't_');
        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        foreach($brr as $kk => $vv){
            $objPHPExcel->createSheet($kk);
            $objPHPExcel->setactivesheetindex($kk);
            $objPHPExcel->getActiveSheet()->setTitle($Date[$kk]['time']);
            $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(50);
            $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
            $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
            $i = 0;
            foreach ($vv as $k => $item) {
                $i++;
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $item['title']);
                $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':C' . $i);//合并单元格
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);//文字居中
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);//字体加粗
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFill()->setFillType(\PHPExcel_Style_Fill::FILL_SOLID);//填充样式
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFill()->getStartColor()->setARGB('FFFFF100');//填充颜色
                $i++;
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '企业');
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '联系人');
                if ($k == 0) {
                    $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '使用人数');
                } else {
                    $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '使用次数');
                }
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);
                $list = $item['list'];
                if (!empty($list)) {
                    foreach ($list as $v) {
                        $i++;
                        $corporate = $c_model->where(['corporate_id' => $v['cid']])->field('`corporate_name`,`contacts_name`')->find();
                        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $corporate['corporate_name']);
                        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $corporate['contacts_name']);
                        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $v['num']);
                        $objPHPExcel->getActiveSheet()->getStyle('B' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                    }
                } else {
                    $i++;
                    $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '暂无记录');
                    $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':C' . $i);
                    $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
                }
            }
        }
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '功能使用企业' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }
    public function costAccountingExcel(){
        //获取列表数据
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');
        $sDate = $startDate . ' 00:00:00';
        $eDate = $endDate . ' 23:59:59';
        $Date = [];
        $cid = [];
        //电子合同次数
        $result1 = M('contract')
                    ->alias('co')
                    ->field('c.corporate_name,count(*) num,c.contacts_name,co.party1_id as cid')
                    ->join('left join t_corporate c on co.party1_id = c.corporate_id')
                    ->where("co.`create_time`>='$sDate' AND co.`create_time`<='$eDate' AND co.sign_state in (1,6) and co.del_status = 0 and contract_state !=1 and co.sign_method = 0")
                    ->group('co.`party1_id`')
                    ->select();
        $result1 = $this->dealArray($result1);
        $contract = $result1['data'];
        $cid = array_unique(array_merge($cid,$result1['cid']));
        
        //电子工资条发放次数
        $result2 = M('salary_record')
                    ->alias('a')
                    ->field('c.corporate_name,count(*) num,c.contacts_name,a.cid')
                    ->join('left join t_corporate c on a.cid = c.corporate_id')
                    ->where("a.`time`>='$sDate' AND a.`time`<='$eDate'")
                    ->group('a.`cid`')
                    ->select();
        $result2 = $this->dealArray($result2);
        $payslip = $result2['data'];
        $cid = array_unique(array_merge($cid,$result2['cid']));
        //考勤服务
        $kq = M('attendance_record')
                    ->alias('a')
                    ->field('a.uid,e.corporate_id as cid,0 as num')
                    ->join('left join t_employee as e on a.uid=e.employee_id')
                    ->where("a.`atime`>='$sDate' AND a.`atime`<='$eDate'")
                    ->group('a.uid')
                    ->select();
        $attendance = [];
        foreach($kq as $val){
            $attendance[$val['cid']]['cid'] = $val['cid'];
            $attendance[$val['cid']]['num'] = $attendance[$val['cid']]['num'] + 1;
        }
        $cid = array_unique(array_merge($cid,array_column($attendance,'cid')));
        //行政管理
        $xz = M('mysubeaa','t_')
                    ->alias('a')
                    ->field('a.uid,e.corporate_id as cid,0 as num')
                    ->join('left join t_employee as e on a.uid=e.employee_id')
                    ->where("a.status !=4 AND a.`time`>='$sDate' AND a.`time`<='$eDate'")
                    ->group('a.uid')
                    ->select();
        $approval = [];
        foreach($xz as $val){
            $approval[$val['cid']]['cid'] = $val['cid'];
            $approval[$val['cid']]['num'] = $approval[$val['cid']]['num'] + 1;
        }
        $cid = array_unique(array_merge($cid,array_column($approval,'cid')));
        $cid = array_filter($cid);

        $corporate = M('corporate','t_')
                        ->alias('a')
                        ->field('a.corporate_id as cid,a.corporate_name,p.name as contacts_name')
                        ->join('left join t_employee as e on a.corporate_id = e.corporate_id')
                        ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
                        ->where(['a.corporate_id'=>array('in',$cid)])
                        ->where("e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
                        ->group('e.corporate_id')
                        ->order('e.personal_id')
                        ->select();
        $data = [];
        foreach($corporate as $key=>$val){
            $data[] = [
                'corporate_name'  => $val['corporate_name'],
                'contacts_name'   => $val['contacts_name'],
                'contract'        => empty($contract[$val['cid']]) ? 0 : $contract[$val['cid']]['num'],
                'contract_price'  => number_format(($contract[$val['cid']]['num'] * 1.5),2,'.',''),
                'payslip'         => empty($payslip[$val['cid']]) ? 0 : $payslip[$val['cid']]['num'],
                'payslip_price'   => number_format(($payslip[$val['cid']]['num'] * 5),2,'.',''),
                'attendance'      => empty($attendance[$val['cid']]) ? 0 : $attendance[$val['cid']]['num'],
                'attendance_price'=> number_format(($attendance[$val['cid']]['num'] * 5),2,'.',''),
                'approval'        => empty($approval[$val['cid']]) ? 0 : $approval[$val['cid']]['num'],
                'approval_price'  => number_format(($approval[$val['cid']]['num'] * 5),2,'.','')
            ];
        }
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        $excel_title = ['公司名称','负责人','电子合同次数','电子合同金额','电子工资条次数','电子工资条金额','考勤打卡人数','考勤打卡金额','考勤机数量','数据分析','数据分析金额','企业风险','企业风险金额','智能提醒','智能提醒金额','行政管理服务数','行政管理服务金额','一键发薪次数','一键发薪金额'];
        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        //第一个sheet
        $objPHPExcel->setactivesheetindex(0);
        //设置sheet标题
        $objPHPExcel->getActiveSheet()->setTitle('成本核算');
        $objPHPExcel->getActiveSheet()->setCellValue('A1','公司');
        $objPHPExcel->getActiveSheet()->mergeCells('A1:B1');
        $objPHPExcel->getActiveSheet()->setCellValue('C1','合同管理');
        $objPHPExcel->getActiveSheet()->mergeCells('C1:D1');
        $objPHPExcel->getActiveSheet()->setCellValue('E1','电子工资条');
        $objPHPExcel->getActiveSheet()->mergeCells('E1:F1');
        $objPHPExcel->getActiveSheet()->setCellValue('G1','考勤服务');
        $objPHPExcel->getActiveSheet()->mergeCells('G1:I1');
        $objPHPExcel->getActiveSheet()->setCellValue('J1','数据分析');
        $objPHPExcel->getActiveSheet()->mergeCells('J1:K1');
        $objPHPExcel->getActiveSheet()->setCellValue('L1','企业风险');
        $objPHPExcel->getActiveSheet()->mergeCells('L1:M1');
        $objPHPExcel->getActiveSheet()->setCellValue('N1','智能提醒');
        $objPHPExcel->getActiveSheet()->mergeCells('N1:O1');
        $objPHPExcel->getActiveSheet()->setCellValue('P1','行政管理');
        $objPHPExcel->getActiveSheet()->mergeCells('P1:Q1');
        $objPHPExcel->getActiveSheet()->setCellValue('R1','一键发薪');
        $objPHPExcel->getActiveSheet()->mergeCells('R1:S1');
        $objPHPExcel->getActiveSheet()->setCellValue('T1','合计');
        foreach($excel_title as $key => $val){
            $objPHPExcel->getActiveSheet()->setCellValue($letter[$key] . '2',$val);
        }
        //设置表格样式
        $objPHPExcel->getActiveSheet()->getRowDimension('1')->setRowHeight(50);
        $objPHPExcel->getActiveSheet()->getRowDimension('2')->setRowHeight(25);
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('Q')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('R')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('S')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('T')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getStyle('A1:T1')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//文字居中
        $objPHPExcel->getActiveSheet()->getStyle('A2:S2')->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);//文字居中
        $k = 3;
        foreach($data as $val){
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $k,$val['corporate_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $k,$val['contacts_name']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $k,$val['contract']);
            $objPHPExcel->getActiveSheet()->setCellValue('D' . $k,$val['contract_price']);
            $objPHPExcel->getActiveSheet()->setCellValue('E' . $k,$val['payslip']);
            $objPHPExcel->getActiveSheet()->setCellValue('F' . $k,$val['payslip_price']);
            $objPHPExcel->getActiveSheet()->setCellValue('G' . $k,$val['attendance']);
            $objPHPExcel->getActiveSheet()->setCellValue('H' . $k,$val['attendance_price']);
            $objPHPExcel->getActiveSheet()->setCellValue('P' . $k,$val['approval']);
            $objPHPExcel->getActiveSheet()->setCellValue('Q' . $k,$val['approval_price']);
            $k++;
        }
        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '成本核算企业' . $startDate . '至' . $endDate;
        ob_end_clean();
        header('pragma:public');
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName . '.xlsx');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
    }
    public function dealArray($arr){
        $brr = [];
        $cid = [];
        foreach($arr as $val){
            $brr[$val['cid']] = $val;
            array_push($cid,$cid,$val['cid']);
        }
        $cid = array_unique($cid);
        return ['data'=> $brr ,'cid' => $cid];
    }
}