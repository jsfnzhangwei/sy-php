<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/29
 * Time: 13:54
 */

namespace Admin\Controller;


use Admin\Model\MallWquotaRechargeRecordModel;
use Common\Model\MallWquotaDetailModel;
use Common\Model\SmsModel;
use Common\Model\UserModel;
use Common\Model\VerifyModel;
use Common\Model\WquotaModel;
use Org\Util\Response;

class Wquotav1Controller extends CommonController
{
    /**
     * 活动列表
     */
    public function index($companyName = null,$type=null,$startTime=null,$endTime=null){
        $this->display('index');
    }

    public function getIndex(){
        $pageIndex = I('pageIndex','');
        $page = !empty($pageIndex)?$pageIndex+1:'';
        $limit = !empty($limit)?$limit:10;
        $where = [];
        $model = M('MallWquotaRechargeRecord');
        $data = $model
            ->field('id,name')
            ->where($where)
            ->page($page,$limit)
            ->order('id desc')
            ->select();
        $detailModel = new MallWquotaDetailModel();
        foreach ($data as $key=>$item){
            $data[$key]['balance'] = $detailModel->getBalanceByAid($item['id']);
            $data[$key]['coast'] = $detailModel->getCoastByAid($item['id']);
            $data[$key]['total'] = $data[$key]['balance'] + $data[$key]['coast'];
        }
        $num = $model
            ->where($where)
            ->count();
        return Response::mjson($data,$num);
    }

    /**
     * 个人福利豆发放
     */
    public function pindex(){
        $nickname       =   I('name');
        if(!empty($nickname)) {
            $map1['user_name'] = ['like', '%' . $nickname . '%'];
            $uids = M('User','t_')->where($map1)->getField('user_id',true);
            if(!empty($uids)){
                $map['uid'] = ['in',$uids];
            }
        }
        $map['send_type'] = 2;
        $Record = new MallWquotaDetailModel();
        $list   = $this->lists($Record, $map);
        foreach($list as $key=>$val){
            $log = M('action_log','sys_')->field('user_id')->where(['record_id'=>'dsy_mall_wquota_recharge_record:' . $val['rid']])->find();
            $list[$key]['operator'] = $log['user_id'];
        }
        int_to_string($list);
        $this->assign('_list', $list);
        $this->meta_title = '个人充值列表';
        $this->display();
    }

    public function activityUsersList(){
        $rid = I('get.aid');
        $this->assign('aid',$rid);
        $this->assign('title','活动人员名单');

        $quotaModel = new WquotaModel();
        $quotaBalance = $quotaModel->getQuotaBalance(['rid' => $rid, 'type' => 1]);
        $this->assign('totalQuota', $quotaBalance['totalQuota']);
        $this->assign('restQuota', $quotaBalance['restQuota']);
        $this->assign('expireQuota', $quotaBalance['expireQuota']);
        $this->assign('useQuota', $quotaBalance['useQuota']);

        $this->display();
    }
    /**
     * @param integer 活动ID号
     * 活动人员表
     */
    public function activityUsers(){
        $aid = I('aid');
        $model = new MallWquotaDetailModel();
        $users = $model->getUsersByAid($aid);
        $count = M('MallWquotaDetail')->where(['rid'=>$aid,'type'=>1])->count();
        return Response::mjson($users, $count);
    }


    /**
     * 短信群发处理
    **/
    public function  sendInfor(){
        $uids = I('uids','');
        $time = I('time','');
        $total = I('total','');
        $uid = $uids[0];
        $time = $time[0];
        $total = $total[0];
        if(empty($uid)|| empty($time)||empty($total)){
            return Response::show(300,'缺少参数');
        }
        $this->sendSmsForExpire($uid,$total,$time);
    }


    /**
     * 提醒福利豆到期
     * @param $uid  integer 用户id
     * @param $total    剩余福利豆数目
     * @param $time     string 到期时间
     * @return string   发送结果
     */
    public function sendSmsForExpire($uid,$total,$time){
        $model = new SmsModel();
        $res = $model->sendSmsForExpire($uid,$total,$time);
        if($res===0){
            return Response::show(200,'发送成功！');
        }
        return Response::show(400,$res);
    }

    /**
     * 添加单个人的福利豆
     */
    public function add(){
        $ip = get_client_ip();
        $ip_arr = C('sy_ip');
        if(!in_array($ip,$ip_arr)){
            $this->error('非法充值');
        }
        if(IS_POST){
            $param = I('post.');
            //验证手机号码是否正确
            if(!VerifyModel::mobile($param['mobile'])){
                $this->error('手机号码不正确');
            }
            if(empty($param['remarks'])){
                $this->error('备注不能为空');
            }
            $uid = M('User','t_')->where(['user_name'=>$param['mobile']])->getField('user_id');
            if(empty($uid)){
                //如果账户不存在则去创建账户
                $user = new UserModel();
                $password = substr($param['mobile'], -6, 6);

                $uid = $user->registerByMobile($param['mobile'],$password);
                //给这个手机号码发送密码
                if(0<$uid){
                    SmsModel::sendSmsForRegister($param['mobile'],$password);
                }
            }
            if(0>$param['num']){
                $this->error('充值额度必须大于0');
            }
            if(date("Y-m-d",time())>$param['end_time']){
                $this->error('有效期必须大于今天');
            }
            $company = ADMINSHOPID;
            $Detail = new MallWquotaDetailModel();
            $Record = new MallWquotaRechargeRecordModel();
            $id = $Record->addPersonalRecord($param['num'],1,3,$param['remarks'],$param['end_time']);
            //添加操作日志
            $admin_log = '给会员' . $param['mobile'] . '充值' . $param['num'] . '个福利豆';
            if(0<$id){
                $endTimeS = strtotime($param['end_time'] . ' 00:00:00');
                $rid = $Detail->addRecord($uid,1,$param['num'],$endTimeS,0,$company,$param['remarks'],$id);
                if($rid){
                    $cname = getcnamebycid($company);
                    //添加充值的理由
                    SmsModel::sendQuotaSms($param['mobile'], $param['mobile'], $cname, $param['num'], $param['end_time']);
                    //发放福利豆安全提醒
                    SmsModel::sendQuotaSmsSafety($param['num'],1,$param['remarks']);
                    //重置福利豆提醒已看时间
                    resetQuotaDate($uid, $param['end_time']);
                    admin_log($admin_log, 1, 'dsy_mall_wquota_recharge_record:' . $id);
                    $this->success('充值成功', U('wquotav1/pindex'));
                    //记录行为
                } else {
                    admin_log($admin_log, 0, 'dsy_mall_wquota_recharge_record');
                    $this->error(' 充值失败');
                }
            } else {
                admin_log($admin_log, 0, 'dsy_mall_wquota_recharge_record');
                $this->error($Record->getError());
            }
        } else {
            $this->meta_title = '添加个人福利豆';
            $menus = getWquotaReson();
            $this->assign('Menus',$menus);
            $this->display();
        }
    }
    /**
     * 查询某会员福利豆到期时间
     **/
    public function editEnd()
    {
        $id = I('id');
        $end = I('end');//1537372800

        if (empty($id) || empty($end)) {
            return Response::show(400, '参数不全');
        }

        if ($_POST) {
            $detailInfo = M('mall_wquota_detail')->where(['id' => $id])->field('end_time,expire_quota,balance')->find();
            if (empty($detailInfo)) {
                return Response::show(400, '记录不存在，请刷新页面');
            }
            $end_time = strtotime($end . ' 00:00:00');
            $updateData = [
                'end_time' => $end_time,
            ];
            if ($end_time <= (time() - 1 * 24 * 60 * 60)) {
                //新时间已过期，旧时间未过期
                if ($detailInfo['end_time'] > (time() - 1 * 24 * 60 * 60)) {
                    $updateData['expire_quota'] = ['exp', '`expire_quota`+`balance`'];
                    $updateData['balance'] = 0;
                }
            } else {
                //新时间未过期，旧时间已过期
                if ($detailInfo['end_time'] <= (time() - 1 * 24 * 60 * 60)) {
                    $updateData['balance'] = ['exp', '`balance`+`expire_quota`'];
                    $updateData['expire_quota'] = 0;
                }
            }
            $res = M('mall_wquota_detail')->where(['id' => $id])->save($updateData);
            if ($res == false) {
                return Response::show(400, '修改失败');
            }
            $detailInfoNew = M('mall_wquota_detail')->where(['id' => $id])->field('end_time,expire_quota,balance')->find();
            //添加操作日志
            $admin_log = '编辑福利豆有效期。原过期时间:' . date('Y-m-d', $detailInfo['end_time']) . '，原可用豆:' . round($detailInfo['balance'], 2) . '，原过期豆:' . round($detailInfo['expire_quota'], 2);
            $admin_log .= '；现过期时间:' . date('Y-m-d', $detailInfoNew['end_time']) . '，现可用豆:' . round($detailInfoNew['balance'], 2) . '，现过期豆:' . round($detailInfoNew['expire_quota'], 2);
            admin_log($admin_log, 1, 'dsy_mall_wquota_detail:' . $id);
            return Response::show(200, '修改成功');
        }

        $end = date('Y-m-d', $end);
        $this->assign('id', $id);
        $this->assign('end', $end);
        $this->display('editend');
    }
}