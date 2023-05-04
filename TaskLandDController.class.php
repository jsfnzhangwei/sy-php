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
class TaskLandDController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    //通过
    public function tg($id){
        $id = $id;
        $where['a.id'] = array('eq',$id);
        //查询任务审核相关数据
        $Tasksub = M('subtask');
        $result = $Tasksub
            ->join('as a left join task_task as b on a.tid = b.id')
            ->where($where)
            ->field('a.status,a.tid,a.uid as uid,b.price as price,a.pic as pic')
            ->select();
        $status = $result[0]['status'];
        //如果任务状态不为审核中，则提示不能再次审核
//        if($status ==1){
//            echo 3;
//            return false;
//        }
        //添加消息记录
        $Msg = M('message');
        $data['uid'] = $result[0]['uid'];
        $data['content'] = '您提交的任务金额：￥'.$result[0]['price'].' 已通过审核，快去提现吧！';
        $data['time'] = date('Y-m-d H:i:s');
        //添加余额
        $User = M('user');
        $uid = $result[0]['uid'];
        $whereuser['id'] = array('eq',$uid);
        $oldbal = $User->where($whereuser)->field('balance,mobile')->select();
        $User->balance = $oldbal[0]['balance'] +$result[0]['price'];
        //添加明细记录
        $dtdata['pic'] = $result[0]['pic'];
        $dtdata['mobile'] = $oldbal[0]['mobile'];
        $dtdata['price'] = $result[0]['price'];
        $dtdata['type'] = 2;
        $dtdata['time'] = date('Y-m-d H:i:s');
        $Dt = M('detail');
        //改变审核状态
        $Tasksub->status = 1;
        $wheretub['id'] = array('eq',$id);
        //改变我的任务状态
        $Mytask = M('mytask');
        $Mytask->status = 3;
        $wheremt['uid'] = array('eq',$result[0]['uid']);
//        echo $result[0]['uid'];exit;
//        echo $id;exit;
        $wheremt['tid'] = array('in',$result[0]['tid']);
        $wheremt['status'] = array('eq',2);
        //发送推送通知
//        $message = $data['content'];
//        $type = array(
//            'type'=> 2,
//        );
        // $title = '任务审核信息';
        // $push = Jpushsend::sendNotifySpecial($message,$type,$uid);
        //echo $push;exit;
        $Tasksub->where($wheretub)->save() ;
        $Mytask->where($wheremt)->save() ;
        $Msg->add($data);
        $Dt->add($dtdata);
        $User->where($whereuser)->save();


    }
    //不通过
    public function ntg($id){
        $sid = $id;
        $where['a.id'] = array('eq',$sid);
        $Tasksub = M('subtask');
        $result = $Tasksub
            ->join('as a left join task_task as b on a.tid = b.id')
            ->where($where)
            ->field('a.status,a.uid as uid,b.price,a.tid')
            ->select();
        //添加消息记录
        $Msg = M('message');
        $uid = $result[0]['uid'];
        $data['uid'] = $uid;
        //  echo $data['uid '];
        $data['content'] = '您提交的任务金额：￥'.$result[0]['price'].' 已被驳回，若有疑问请咨询在线客服!';
        $data['time'] = date('Y-m-d H:i:s');
        //改变审核状态
        $Tasksub->status = 2;
        $wheretub['id'] = array('eq',$id);
        //改变我的任务状态
        $Mytask = M('mytask');
        $Mytask->status = 4;
        $wheremt['uid'] = array('eq',$result[0]['uid']);
        $wheremt['tid'] = array('eq',$result[0]['tid']);
        $wheremt['status'] = array('eq',2);
        $Msg->add($data) ;
        $Mytask->where($wheremt)->save();
        $Tasksub->where($wheretub)->save() ;

    }
    //导出
    public function save()
    {
        import('Org.Util.PHPExcel');
        $objPHPExcel = new \PHPExcel();
//    $type = new\PHPExcel_Cell_DataType();
        $objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
        $objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(16);
        $objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(12);
        $objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(16);
        $objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
        $objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(19);
        $objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(10);

        $objPHPExcel->getActiveSheet()->setCellValue('A1', '编号');
        $objPHPExcel->getActiveSheet()->setCellValue('B1', '任务标题');
        $objPHPExcel->getActiveSheet()->setCellValue('C1', '用户手机号');
        $objPHPExcel->getActiveSheet()->setCellValue('D1', '备注姓名');
        $objPHPExcel->getActiveSheet()->setCellValue('E1', '备注手机号');
        $objPHPExcel->getActiveSheet()->setCellValue('F1', '备注其他信息');
        $objPHPExcel->getActiveSheet()->setCellValue('G1', '提交时间');
        $objPHPExcel->getActiveSheet()->setCellValue('H1', '审核状态');
        $objPHPExcel->getActiveSheet()->setCellValue('I1', '（1通过，2不通过，3正在审核）');
        $Tasksub = M('subtask');
        $where['a.status'] = array('eq', 3);
        if (!empty($_REQUEST['tid']) && !empty($_REQUEST['time'])) {
            $where['a.tid'] = array('eq', $_REQUEST['tid']);
            $where['a.time'] = array('like', $_REQUEST['time'] . '%');
        } else {
            echo 3;
            exit;
        }

        $row = $Tasksub
            ->join('as a left join task_task as b on a.tid = b.id')
            ->join('left join task_user as c on a.uid = c.id')
            ->where($where)
            ->field('a.id,a.tid,a.name,a.time,a.mobile as mobile,a.else,b.title as title,c.mobile as umobile,a.status as status')
            ->order('time desc')
            ->select();
// print_r($row);exit;
        $count = count($row);
//    echo $count;exit;
        for ($i = 2; $i <= $count + 1; $i++) {
            $objPHPExcel->getActiveSheet()->setCellValue('A' . $i, $row[$i - 2]['id']);
            $objPHPExcel->getActiveSheet()->setCellValue('B' . $i, ' ' . $row[$i - 2]['title']);
            $objPHPExcel->getActiveSheet()->setCellValue('C' . $i, ' ' . $row[$i - 2]['umobile']);
            $objPHPExcel->getActiveSheet()->setCellValue('D' . $i, ' ' . $row[$i - 2]['name']);
            $objPHPExcel->getActiveSheet()->setCellValue('E' . $i, ' ' . $row[$i - 2]['mobile']);
            $objPHPExcel->getActiveSheet()->setCellValue('F' . $i, ' ' . $row[$i - 2]['else']);
            $objPHPExcel->getActiveSheet()->setCellValue('G' . $i, ' ' . $row[$i - 2]['time']);
            $objPHPExcel->getActiveSheet()->setCellValue('H' . $i, $row[$i - 2]['status']);
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

    //导入
    public function uploadsubtask()
    {
        if (isset($_FILES['f'])){
           // $filename = $_FILES['f']['name'];
            $tmp_name = $_FILES['f']['tmp_name'];
            //导入Excel文件
            //自己设置的上传文件存放路径
            $filePath = 'Public/Subtask/';
            $str = ""; //下面的路径按照你PHPExcel的路径来修改
            //注意设置时区
            $time = date("YmdHis"); //取当前上传的时间
            //获取上传文件的扩展名
            //$extend = strrchr($file, '.');
            //上传后的文件名
            $name = $time . '.xlsx';
            $uploadfile = $filePath . $name;
            //上传后的文件名地址
            //move_uploaded_file() 函数将上传的文件移动到新位置。若成功，则返回 true，否则返回 false
            $result = move_uploaded_file($tmp_name, $uploadfile); //假如上传到当前目录下
//              echo $result;exit;
            if ($result == true) { //如果上传文件成功，就执行导入excel操作
                /**
                 * $id = ''; //任务审核id
                 * $tid='';//任务id
                 * $status=''//任务审核状态 1通过 2不通过
                 * $uid = '';//用户id
                 **/

                require_once APP_PATH.'PHPExcel.php';
                require_once APP_PATH.'PHPExcel/Writer/Excel2007.php';
                require_once APP_PATH.'PHPExcel/Writer/Excel5.php';
                $PHPExcel = new \PHPExcel();
                /**默认用excel2007读取excel，若格式不对，则用之前的版本进行读取*/
                $PHPReader = new \PHPExcel_Reader_Excel2007();
                if(!$PHPReader->canRead($filePath)){
                    $PHPReader = new PHPExcel_Reader_Excel5();
                    if(!$PHPReader->canRead($filePath)){
                        echo 'no Excel';
                        return;
                    }
                }

                $PHPExcel = $PHPReader->load($filePath);
                $currentSheet = $PHPExcel->getSheet(0);  //读取excel文件中的第一个工作表
                $allColumn = $currentSheet->getHighestColumn(); //取得最大的列号
                $allRow = $currentSheet->getHighestRow(); //取得一共有多少行
                $erp_orders_id = array();  //声明数组
//                $res = Service

                /**从第二行开始输出，因为excel表中第一行为列名*/
                for($currentRow = 1;$currentRow <= $allRow;$currentRow++){

                    /**从第A列开始输出*/
                    for($currentColumn= 'A';$currentColumn<= $allColumn; $currentColumn++){

                        $val = $currentSheet->getCellByColumnAndRow(ord($currentColumn) - 65,$currentRow)->getValue();/**ord()将字符转为十进制数*/
                        if($val!=''){
                            $erp_orders_id[] = $val;
                        }
                        /**如果输出汉字有乱码，则需将输出内容用iconv函数进行编码转换，如下将gb2312编码转为utf-8编码输出*/
                        //echo iconv('utf-8','gb2312', $val)."\t";

                    }
                }
                return $erp_orders_id;











                $filename = $uploadfile;
//                echo $filename;exit;
                $objReader= \PHPExcel_IOFactory::createReaderForFile($filename);
//                var_dump($objReader);exit;
                $objPHPExcel = $objReader->load($filename);
                echo 1;exit;
                $objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
//                //获取总列数
//                $allColumn = $objWorksheet->getHighestColumn();
//                echo $allColumn;exit;
                //获取总行数
                $allRow = $objWorksheet->getHighestRow();
                echo $allRow;exit;
                try{
                    for($currentrow = 2;$currentrow<=$allRow;$currentrow++){
                        $id = $objPHPExcel->getActiveSheet()->getCell('A'.$currentrow)->getValue();
                        $status = $objPHPExcel->getActiveSheet()->getCell('H'.$currentrow)->getValue();
                        if($status==1){
                            $this->tg($id);
                        }else if($status==2){
                            $this->ntg($id);
                        }
                    }
                    echo 1;
                }catch(Exception $e){
                    echo 1;
                }




            }
        }else echo 3;


    }




}