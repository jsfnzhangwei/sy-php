<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Manager\Controller;
use Think\Controller;
use Org\Util\Jpushsend;
class WdcrashloadController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    //导出
    public function save()
    {
        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
//    $type = new\PHPExcel_Cell_DataType();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(16);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(30);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(16);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(10);

        $objPHPExcel->getActiveSheet()->setCellValue('A1', '编号');
        $objPHPExcel->getActiveSheet()->setCellValue('B1', '手机号');
        $objPHPExcel->getActiveSheet()->setCellValue('C1', '邀请码');
        $objPHPExcel->getActiveSheet()->setCellValue('D1', '所属公司');
        $objPHPExcel->getActiveSheet()->setCellValue('E1', '真实姓名');
        $objPHPExcel->getActiveSheet()->setCellValue('F1', '支付宝账号');
        $objPHPExcel->getActiveSheet()->setCellValue('G1', '提现金额');
        $objPHPExcel->getActiveSheet()->setCellValue('H1', '提现时间');
        $objPHPExcel->getActiveSheet()->setCellValue('I1', '审核状况');
        $objPHPExcel->getActiveSheet()->setCellValue('J1', '（1审核中，2通过，3不通过）');
        $where['a.status'] = array('eq', 1);//审核中
        if (!empty($_REQUEST['incode']) && !empty($_REQUEST['time'])) {
            $where['b.incode'] = array('eq', $_REQUEST['incode']);
            $where['a.time'] = array('like', $_REQUEST['time'] . '%');
        } else {
            echo 3;
            exit;
        }

        $Wdcrash = M('wdcrash');
        $row = $Wdcrash
            ->join('as a left join task_user as b on a.uid = b.id ')
            ->join('left join task_incode as c on b.incode = c.incode')
            ->field('a.id,a.mobile,b.incode,c.belong,a.status ,a.price,a.time,b.alipaynum,b.realname')
            ->where($where)
            ->select();
// print_r($row);exit;
        $count = count($row);
//    echo $count;exit;
        for ($i = 2; $i <= $count + 1; $i++) {
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $row[$i - 2]['id']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, ' ' . $row[$i - 2]['mobile']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, ' ' . $row[$i - 2]['incode']);
            $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, ' ' . $row[$i - 2]['belong']);
            $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, ' ' . $row[$i - 2]['realname']);
            $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, ' ' . $row[$i - 2]['alipaynum']);
            $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, ' ' . $row[$i - 2]['price']);
            $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, ' ' . $row[$i - 2]['time']);
            $objPHPExcel->getActiveSheet()->setCellValue('I' . $i, $row[$i - 2]['status']);
//            $objPHPExcel->getActiveSheet()->setCellValue('J'.$i, '（1审核中，2通过，3不通过）');
        }

        $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
        $outputFileName = time() . '.xlsx';
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename=' . $outputFileName);
        header("Content-Transfer-Encoding:binary");
//    $path = 'Public/Subtask/';
//    $objWriter->save($path.$outputFileName);
        $objWriter->save('php://output');

    }





}