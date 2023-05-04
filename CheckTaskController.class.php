<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/11/1 0001
 * Time: 02:47
 */
namespace Manager\Controller;
use Think\Controller;
class CheckTaskController extends Controller
{
    public function checkT(){
        $myTask = M('mytask');
        $time = time();
        $outtime = $time - 7200;
        $date = date('Y-m-d H:i:s',$outtime);
        $where['status'] = array('eq',1);
        $where['time'] = array('lt',$date);
        $myTask->status = 5;
        if($myTask->where($where)->save() !==false)
            echo '检测完毕';
        else echo 2;


}
}
