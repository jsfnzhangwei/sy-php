<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/8
 * Time: 19:21
 */

namespace Admin\Controller;


use Think\Controller;

class PublicuploadController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    public function upload(){
        if (SDE == 0) {
            $fastDfs = A('app/FastDfs');
            $fastDfsDes = $fastDfs->uploadImg1($_FILES['file']);
            $fastDfsDes['img_prefix'] = WEB_URL_IMG;
            echo json_encode($fastDfsDes);
            die;
        }

        $return = array('code' => 1, 'msg' => '上传成功');
        $upload = new \Think\Upload();
        $upload->rootPath  = ROOT_PATH . '/Public/Uploads/images/';
        $info   =   $upload->upload();
        /* 记录图片信息 */
        if($info){
            $return['img_prefix'] = '/dashengyun';
            $return['data'] = '/Public/Uploads/images/' . $info['file']['savepath'] . $info['file']['savename'];
            $return['img_full_path'] = WEB_URL . $return['img_prefix'] . $return['data'];
            echo json_encode($return);
            die;
            echo '/Public/Uploads/images/'.$info['file']['savepath'].$info['file']['savename'];
        } else {
            $return['code'] = 0;
            $return['msg'] = $upload->getError();
            echo json_encode($return);
            die;
            echo $upload->getError();
        }
    }
}