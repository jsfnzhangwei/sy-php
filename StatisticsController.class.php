<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/7
 * Time: 11:31
 */

namespace Admin\Controller;

use Think\Controller;
use Org\Util\Response;

class StatisticsController extends Controller
{
    private $cids = '-1';

    public function _initialize()
    {
        if (APIE == 1) {
            $this->cids = '504,804,805,448,455,461,462,463,464,469,477,479,493,497,511,531,532,534,545,546,564,571,578,582,584,585,587,602,603,604,605,606,607,608,609,610,611,612,613,614,615,616,617,618,619,620,621,622,623,624,625,626,627,661,663,669,670,676,678,684,685,686,687,688,689,690,691,695,696,697,704,706,711,720,721,723,739,741,746,482,495,509,553,646,648,649,672,673,682,448,707,735,497,748,767,770,686,567,674,667';
        }
    }

    public function useView()
    {
        $this->display();
    }

    public function useChartList()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        $categories = [];
        $list = [];
        foreach ($dateArr as $item) {
            if ($item['showDate'] == 0) {
                $categories[] = substr($item['start'], 11, 5) . '-' . substr($item['end'], 11, 5);
            } else {
                $categories[] = substr($item['start'], 5, 5);
            }
            $list[] = getAppViewsUserNum($item['start'], $item['end']);
        }

        $data = [
            'times' => $categories,
            'list' => $list,
        ];
        echo json_encode($data);
        die;
    }

    public function useTimeList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $limit = 10;

        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        $list = [];
        foreach ($dateArr as $item) {
            if ($item['showDate'] == 0) {
                $date = substr($item['start'], 11, 5) . '-' . substr($item['end'], 11, 5);
            } else {
                $date = substr($item['start'], 5, 5);
            }
            $list[] = [
                'date' => $date,
                'users' => getAppViewsUserNum($item['start'], $item['end']),
                'times' => getAppViewsNum($item['start'], $item['end']),
            ];
        }
        $count = count($list);
        $list = array_slice($list, $pageIndex * $limit, $limit);

        return Response::mjson($list, $count);
    }

    public function useComList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $limit = 10;

        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange, 1);
        $start = $dateArr['start'];
        $end = $dateArr['end'];

        $sql = "SELECT COUNT(1) AS num FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ");";
        $count = M()->query($sql);
        $count = $count[0]['num'];
        $list = [];
        if ($count > 0) {
            $sql = "SELECT c.`corporate_id`,c.`corporate_name`,e.`personal_id` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`corporate_id` ASC LIMIT " . ($pageIndex * $limit) . "," . $limit . ";";
            $list = M()->query($sql);
            foreach ($list as $key => $item) {
                $list[$key]['contacts_name'] = M('personal', 't_')->where(['personal_id' => $item['personal_id']])->getField('name');
                $list[$key]['users'] = getAppViewsUserNumByCid($item['corporate_id'], $start, $end);
                $list[$key]['times'] = getAppViewsNumByCid($item['corporate_id'], $start, $end);
            }
        }

        return Response::mjson($list, $count);
    }

    public function useTimeExport()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $i = 0;
        $i++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '时间');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '用户总数');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '用户打开次数');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);

        foreach ($dateArr as $item) {
            if ($item['showDate'] == 0) {
                $date = substr($item['start'], 11, 5) . '-' . substr($item['end'], 11, 5);
            } else {
                $date = substr($item['start'], 5, 5);
            }

            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $date);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, getAppViewsUserNum($item['start'], $item['end']));
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, getAppViewsNum($item['start'], $item['end']));
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '活跃用户 时间维度 ' . $dateRange;
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

    public function useComExport()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange, 1);
        $start = $dateArr['start'];
        $end = $dateArr['end'];

        $sql = "SELECT COUNT(1) AS num FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ");";
        $count = M()->query($sql);
        $count = $count[0]['num'];

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(40);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $i = 0;
        $i++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '序号');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '单位名称');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '管理员');
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, '活跃人次');
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, '用户打开次数');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':E' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':E' . $i)->getFont()->setBold(true);

        if ($count > 0) {
            $sql = "SELECT c.`corporate_id`,c.`corporate_name`,e.`personal_id` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`corporate_id` ASC;";
            $list = M()->query($sql);
            foreach ($list as $key => $item) {
                $item['contacts_name'] = M('personal', 't_')->where(['personal_id' => $item['personal_id']])->getField('name');
                $i++;
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $item['corporate_id']);
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $item['corporate_name']);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $item['contacts_name']);
                $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, getAppViewsUserNumByCid($item['corporate_id'], $start, $end));
                $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, getAppViewsNumByCid($item['corporate_id'], $start, $end));
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':E' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            }
        } else {
            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '暂无记录');
            $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':E' . $i);
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':E' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '活跃用户 企业维度 ' . $dateRange;
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

    public function newUserView()
    {
        $this->display();
    }

    public function newUserChartList()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        $categories = [];
        $list = [];
        foreach ($dateArr as $item) {
            if ($item['showDate'] == 0) {
                $categories[] = substr($item['start'], 11, 5) . '-' . substr($item['end'], 11, 5);
            } else {
                $categories[] = substr($item['start'], 5, 5);
            }
            $list[] = getAppNewUserNum($item['start'], $item['end']);
        }

        $data = [
            'times' => $categories,
            'list' => $list,
        ];
        echo json_encode($data);
        die;
    }

    public function newUserTimeList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $limit = 10;

        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        $list = [];
        foreach ($dateArr as $item) {
            if ($item['showDate'] == 0) {
                $date = substr($item['start'], 11, 5) . '-' . substr($item['end'], 11, 5);
            } else {
                $date = substr($item['start'], 5, 5);
            }
            $list[] = [
                'date' => $date,
                'usersNew' => getAppNewUserNum($item['start'], $item['end']),
                'users' => getAppViewsUserNum($item['start'], $item['end']),
            ];
        }
        $count = count($list);
        $list = array_slice($list, $pageIndex * $limit, $limit);

        return Response::mjson($list, $count);
    }

    public function newUserComList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $limit = 10;

        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange, 1);
        $start = $dateArr['start'];
        $end = $dateArr['end'];

        $sql = "SELECT COUNT(1) AS num FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ");";
        $count = M()->query($sql);
        $count = $count[0]['num'];
        $list = [];
        if ($count > 0) {
            $sql = "SELECT c.`corporate_id`,c.`corporate_name`,e.`personal_id` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`corporate_id` ASC LIMIT " . ($pageIndex * $limit) . "," . $limit . ";";
            $list = M()->query($sql);
            foreach ($list as $key => $item) {
                $list[$key]['contacts_name'] = M('personal', 't_')->where(['personal_id' => $item['personal_id']])->getField('name');
                $list[$key]['usersNew'] = getAppNewUserNumByCid($item['corporate_id'], $start, $end);
                $list[$key]['users'] = getAppViewsUserNumByCid($item['corporate_id'], $start, $end);
            }
        }

        return Response::mjson($list, $count);
    }

    public function newUserTimeExport()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $i = 0;
        $i++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '时间');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '新增用户');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '活跃用户');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);

        foreach ($dateArr as $item) {
            if ($item['showDate'] == 0) {
                $date = substr($item['start'], 11, 5) . '-' . substr($item['end'], 11, 5);
            } else {
                $date = substr($item['start'], 5, 5);
            }

            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $date);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, getAppNewUserNum($item['start'], $item['end']));
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, getAppViewsUserNum($item['start'], $item['end']));
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':C' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '新增用户 时间维度 ' . $dateRange;
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

    public function newUserComExport()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange, 1);
        $start = $dateArr['start'];
        $end = $dateArr['end'];

        $sql = "SELECT COUNT(1) AS num FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ");";
        $count = M()->query($sql);
        $count = $count[0]['num'];

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(40);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
        $i = 0;
        $i++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '序号');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '单位名称');
        $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, '管理员');
        $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, '新增用户');
        $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, '活跃用户');
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':E' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':E' . $i)->getFont()->setBold(true);

        if ($count > 0) {
            $sql = "SELECT c.`corporate_id`,c.`corporate_name`,e.`personal_id` FROM `t_corporate` c LEFT JOIN `t_employee` e ON e.`corporate_id`=c.`corporate_id` WHERE e.`review_status`=1 AND e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND c.`corporate_id` NOT IN (" . $this->cids . ") ORDER BY c.`corporate_id` ASC;";
            $list = M()->query($sql);
            foreach ($list as $key => $item) {
                $item['contacts_name'] = M('personal', 't_')->where(['personal_id' => $item['personal_id']])->getField('name');
                $i++;
                $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $item['corporate_id']);
                $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $item['corporate_name']);
                $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, $item['contacts_name']);
                $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, getAppNewUserNumByCid($item['corporate_id'], $start, $end));
                $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, getAppViewsUserNumByCid($item['corporate_id'], $start, $end));
                $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':E' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            }
        } else {
            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '暂无记录');
            $objPHPExcel->getActiveSheet()->mergeCells('A' . $i . ':E' . $i);
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':E' . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '新增用户 企业维度 ' . $dateRange;
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


    public function lcView()
    {

        $this->display();
    }

    public function lcChartList()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        $categories = [];
        $list = [];
        foreach ($dateArr as $item) {
            if ($item['showDate'] == 0) {
                $categories[] = substr($item['start'], 11, 5) . '-' . substr($item['end'], 11, 5);
            } else {
                $categories[] = substr($item['start'], 5, 5);
            }
            $list[] = getAppViewsUserNum($item['start'], $item['end']);
        }

        $data = [
            'times' => $categories,
            'list' => $list,
        ];
        echo json_encode($data);
        die;
    }

    public function lcTimeList()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        //最大的天数
        $maxDay = intval((time() - strtotime($dateArr[0]['start'])) / 86400);

        $columns = [];
        for ($d = 1; $d <= $maxDay; $d++) {
            $columns[] = $d;
        }
        $list = [];
        foreach ($dateArr as $k => $item) {
            $sDate = $item['start'];
            $eDate = $item['end'];
            $usersNew = getAppNewUserNum($sDate, $eDate);
            $newItem = [
                'date' => substr($sDate, 5, 5),
                'usersNew' => $usersNew,
            ];
            for ($d = 1; $d <= $maxDay; $d++) {
                $usersNewByDay = 0;
                if ($usersNew > 0) {
                    $start = date('Y-m-d H:i:s', strtotime($sDate) + ($d * 86400));
                    $end = date('Y-m-d H:i:s', strtotime($sDate) + (($d + 1) * 86400));
                    $usersNewByDay = getAppNewUserViewsNum($sDate, $eDate, $start, $end);
                }
                $newItem['user' . $d] = $usersNewByDay;
                $newItem['day' . $d] = round(($usersNewByDay / $usersNew) * 100, 2) . '%';
            }
            $list[] = $newItem;
            $maxDay--;
        }

        $data = [
            'list' => $list,
            'columns' => $columns,
        ];

        echo json_encode($data);
        die;
    }

    public function lcTimeExport()
    {
        $eType = I('eType', 1);
        $dateType = I('dateType', 1);
        $dateRange = I('dateRange', '');

        //获取时间分割线
        $dateArr = getDateArr($dateType, $dateRange);

        //最大的天数
        $maxDay = intval((time() - strtotime($dateArr[0]['start'])) / 86400);
        $chars = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD', 'AE', 'AF'];

        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(15);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        for ($d = 1; $d <= $maxDay; $d++) {
            $objPHPExcel->getActiveSheet()->getColumnDimension($chars[$d - 1])->setWidth(15);
        }
        $i = 0;
        $i++;
        $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, '时间');
        $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, '新增用户');
        for ($d = 1; $d <= $maxDay; $d++) {
            $objPHPExcel->getActiveSheet()->setCellValue($chars[$d - 1] . $i, $d . '天后(人)');
        }
        $endChar = end($chars);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':' . $endChar . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':' . $endChar . $i)->getFont()->setBold(true);

        foreach ($dateArr as $k => $item) {
            $sDate = $item['start'];
            $eDate = $item['end'];
            $usersNew = getAppNewUserNum($sDate, $eDate);
            $i++;
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, substr($sDate, 5, 5));
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, $usersNew);
            for ($d = 1; $d <= $maxDay; $d++) {
                $usersNewByDay = 0;
                if ($usersNew > 0) {
                    $start = date('Y-m-d H:i:s', strtotime($sDate) + ($d * 86400));
                    $end = date('Y-m-d H:i:s', strtotime($sDate) + (($d + 1) * 86400));
                    $usersNewByDay = getAppNewUserViewsNum($sDate, $eDate, $start, $end);
                }
                $objPHPExcel->getActiveSheet()->setCellValue($chars[$d - 1] . $i, $usersNewByDay);
            }
            $objPHPExcel->getActiveSheet()->getStyle('A' . $i . ':' . $endChar . $i)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
            $maxDay--;
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = '留存数据 ' . $dateRange;
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
}