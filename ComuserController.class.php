<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/6
 * Time: 19:45
 */

namespace Admin\Controller;
use Org\Util\Response;
use Think\Controller;

class ComuserController extends Controller{
    /**
     * 企业用户首页
     */
    public function index(){
        $this->display();
    }
    /**
     * 企业用户数据
     */
    public function com_user_list(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex +1;
        $limit = !empty($page)?10:'';
        $name = I('name','');
        
        $where['e.review_status'] = array('eq',1);//审核通过
        $where['a.comvip_status'] = array('eq',1);//已成为企业会员
        if(!empty($name)){
            $where['a.corporate_name'] = array('like',"%$name%");
        }
        $result = M('corporate','t_')
                    ->alias('a')
                    ->field('a.corporate_id,a.corporate_name,a.number,p.name,p.mobile,a.comvip_time,p.user_id')
                    ->join('LEFT JOIN t_employee e ON e.`corporate_id`=a.`corporate_id`')
                    ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
                    ->where("e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
                    ->where($where)
                    ->order('e.`review_status` ASC,a.`create_time` DESC')
                    ->page($page,$limit)
                    ->select();
        $num =  M('corporate','t_')
                    ->alias('a')
                    ->join('left join t_employee e ON e.`corporate_id`=a.`corporate_id`')
                    ->join('left join t_personal p ON p.`personal_id`=e.`personal_id`')
                    ->where("e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
                    ->where($where)
                    ->count();
        return Response::mjson($result,$num);
    }
    /**
     * 添加企业会员用户首页
     */
    public function com_user_audit(){
        $this->display();
    }
    /**
     *添加企业会员用户数据
     */
    public function com_audit_data(){
        $pageIndex = $_REQUEST['pageIndex'];
        $page = $pageIndex+1;
        $limit = !empty($page)?10:'';
        $status = I('status','');
        $cname = I('cname','');

        $where = [];
        if ($status != '') {
            $where['c.comvip_status'] = array('eq',$status);
        }
        if(!empty($cname)){
            $where['c.corporate_name'] = array('like',"%$cname%");
        }
        $where['e.review_status'] = array('eq',1);
        $info = (array)M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=c.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where("e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
            ->where($where)
            ->field('p.`mobile` uname,p.`name` user_name,e.`review_status`,c.`corporate_id`,c.`corporate_name`,c.`comvip_status`,c.`comvip_img`,c.`contacts_name`,c.`contacts_mobile` cmobile,c.`create_time`,c.`owned_industry`,c.`number`,c.`address`,c.`province`,c.`city`,c.`area`,c.`business_licence`')
            ->order('e.`review_status` ASC,c.`create_time` DESC')
            ->page($page, $limit)
            ->select();
        $num = (int)M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=c.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where("e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5")
            ->where($where)
            ->count();

        $local = M('provincial','dsy_');
        if(!empty($info)){
            foreach($info as $key=>$value){
                $string = $value['province'].','.$value['city'].','.$value['area'];
                $where1['id'] = array('in',$string);
                $localcation = $local->where($where1)->field('name')->select();
                if (in_array($localcation[1]['name'], ['市辖区', '县'])) {
                    $localcation[1]['name'] = '';
                }
                if (in_array($localcation[2]['name'], ['市辖区', '县'])) {
                    $localcation[2]['name'] = '';
                }
                $info[$key]['area'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'];
                $info[$key]['address'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'].$value['address'];

                $info[$key]['owned_industry'] = $this->getIndustryType($value['owned_industry']);
                if(empty($value['business_licence'])){
                    $info[$key]['business_licence'] = '';
                }else{
                    $img = format_img($value['business_licence'], IMG_VIEW);
                    $info[$key]['business_licence'] = '<a href="'.$img.'" target="view_window">点击查看</a>';
                }
                if(empty($value['comvip_img'])){
                    $info[$key]['comvip_img'] = 0;
                }else{
                    $info[$key]['comvip_img'] = 1;
                }
            }
        }

        return Response::mjson($info,$num);
    }
    /**
     * 设置企业会员
     */
    public function com_user_agree(){
        $id = I('id','');
        if(!empty($id)){
            $info = M('corporate','t_')
            ->alias('a')
            ->field('a.corporate_id,a.corporate_name,p.name,p.mobile,a.province,a.city,a.area,a.number,a.create_time')
            ->join('LEFT JOIN t_employee e ON e.`corporate_id`=a.`corporate_id`')
            ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
            ->where('e.`emp_type`=1 AND e.`del_status`=0 AND e.`status`!=5 AND a.corporate_id=' . $id)
            ->find();
        }else{
            echo 3;
        }
        $local = M('provincial','dsy_');
        if(!empty($info)){
                $string = $info['province'].','.$info['city'].','.$info['area'];
                $where1['id'] = array('in',$string);
                $localcation = $local->where($where1)->field('name')->select();
                if (in_array($localcation[1]['name'], ['市辖区', '县'])) {
                    $localcation[1]['name'] = '';
                }
                if (in_array($localcation[2]['name'], ['市辖区', '县'])) {
                    $localcation[2]['name'] = '';
                }
                $info['area'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'];
                $info['address'] = $localcation[0]['name'].$localcation[1]['name'].$localcation[2]['name'].$value['address'];
            $this->assign('info',$info);
        }
        $this->display();
    }
    /**
     * 上传企业合作协议
     */
    public function com_user_cooperation(){
        $id = I('id');
        $img = I('img','');
        if(empty($id)){
            return Response::show(300,'数据异常,请重新提交');
        }
        $img = array_unique($img);
        $switch = 0;
        foreach($img as $key=>$val){
            if(!empty($val)){
                $switch = 1;
            }else{
                unset($img[$key]);
            }
        }
        if($switch != 1){
            return Response::show(300,'合作协议不能为空,请上传合作协议');
        }
        $img = array_filter($img);
        $data['comvip_img'] =  implode(',',$img);
        $data['comvip_time'] = date('Y-m-d H:i:s',time());
        $data['comvip_status'] = 1;
        $send_mobile = M('corporate','t_')
                                    ->alias('a')
                                    ->field('p.mobile,a.corporate_name')
                                    ->join('LEFT JOIN t_employee e ON e.`corporate_id`=a.`corporate_id`')
                                    ->join('LEFT JOIN t_personal p ON p.`personal_id`=e.`personal_id`')
                                    ->where('a.corporate_id=' . $id)
                                    ->find();
        $result = M('corporate','t_')->where('corporate_id=' . $id)->save($data);
        $admin_log = "设置公司:'" . $send_mobile['corporate_name'] . "'为企业会员";
        if($result !== false){
            admin_log($admin_log, 1, 't_corporate:' . $id);
            $tempId = '598819';
            $send_msg = SendTemplateSMS($send_mobile['mobile'],null,$tempId,2);
            return Response::show(200,'设置企业会员成功');
        }else{
            admin_log($admin_log, 0, 't_corporate:' . $id);
            return Response::show(300,'设置企业会员失败');
        }
    }
    /**
     * 取消企业会员
     */
    public function  com_user_disagree(){
        $id = I('id','');
        if(empty($id)){
            return Response::show(300,'数据异常,请重新提交');
        }
        $data = [
            'comvip_status' => 0,
        ];
        $result = M('corporate','t_')
                ->where('corporate_id=' . $id)
                ->save($data);
        $info = M('corporate','t_')->field('corporate_name')->where('corporate_id=' . $id)->find();
        $admin_log = "取消公司:'" . $info['corporate_name'] . "'为企业会员";
        if($result !== false){
            admin_log($admin_log, 1, 't_corporate:' . $id);
            return Response::show(200,'取消成功');
        }else{
            admin_log($admin_log, 0, 't_corporate:' . $id);
            return Response::show(300,'取消失败');
        }
    }
    /**
     * 查看合作协议
     */
    public function com_user_img(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'数据异常,请重新提交');
        }
        $result = M('corporate','t_')
                    ->field('comvip_img')
                    ->where('corporate_id='.$id)
                    ->find();
        $img = array_filter(explode(',',$result['comvip_img']));
        if(!empty($img)){
            $str = '';
            foreach($img as $key=>$val){
                $src = format_img($val, IMG_VIEW);
                $str .= "<a href='" .$src. "' target='_blank'><img class='xy_img' src='" . $src ."'></a>";
            }
            return Response::show(200,$str);
        }else{
            return Response::show(300,'该企业没有上传合作协议');
        }
    }
    /**
     * 获取企业行业
     * @param $key
     * @return mixed|string
     */
    private function getIndustryType($key)
    {
        $industryArr = [
            1 => 'IT/通信',
            2 => '金融业',
            3 => '房地产/建筑业',
            4 => '商业服务',
            5 => '贸易/批发/零售/租赁业',
            6 => '文体教育/工业美术',
            7 => '生产/加工/制造',
            8 => '交通/运输/物流/仓储',
            9 => '服务业',
            10 => '文化/传媒/娱乐/体育',
            11 => '能源/矿产/环保'
        ];
        return isset($industryArr[$key]) ? $industryArr[$key] : '其他';
    }
    /**
     * 上传图片
     */
    public function uploadPic()
    {
        $pic = $_FILES['pic'];
        if($pic['size'] > 2097152){
            return Response::show(300,'照片大小超过2M,请重新上传');
        }
        if (SDE == 0) {
            $fastDfs = A('app/FastDfs');
            $fastDfsDes = $fastDfs->uploadImg1($pic);
            if ($fastDfsDes['code'] != 1) {
                return Response::show(300, $fastDfsDes['msg']);
            }
            $path = $fastDfsDes['data'];
            $data = [
                'path' => $path,
                'fixPath' => format_img($path, IMG_VIEW),
            ];
            return Response::json(200, '上传成功！', $data);
        }

        if (empty($pic))
            return Response::show(300, '请选择图片！');
        $config = [
            'maxSize' => 6145728,// 设置附件上传大小
            'rootPath' => '/htdocs/images/',// 设置附件上传根目录
            'exts' => ['jpg', 'gif', 'png', 'jpeg'],// 设置附件上传类型
            'autoSub' => true,// 开启子目录保存 并以日期（格式为Ymd）为子目录
            'subName' => ['date', 'Ymd'],
        ];
        $upload = new \Think\Upload($config);
        $info = $upload->uploadOne($pic);
        if ($info === false) {
            return Response::show(300, $upload->getError());
        }

        $path = '/' . $info['savepath'] . $info['savename'];
        $data = [
            'path' => $path,
            'fixPath' => format_img($path, IMG_VIEW),
        ];
        return Response::json(200, '上传成功！', $data);
    }
}