<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;
use Org\Util\Upload;
use Org\Util\Jpushsend;
use Think\Db;

class IDCheckController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    public function id_check()
    {
        return Response::show(200,'true');
        $us = I('us','');
        if(!empty($us)){
            $log = M('admin_log');
            $where['sessionid'] = array('eq',$us);
            $where['ischeck'] = array('eq',1);
            $result = $log->where($where)->select();
            $usvalue = session($us);
            if(!empty($usvalue) && !empty($result)){
                if(time()-$usvalue < 600){
                    session($us,time());
                    return Response::show(200,'true');
                }else{
                    return Response::show(400,'登陆超时');
                }
            }else{
                return Response::show(400,'登陆超时');
            }
        } else {
            return Response::show(300,'缺少参数');
        }
    }

}
