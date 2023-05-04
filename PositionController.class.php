<?php
/**
 * Created by PhpStorm.
 * User: 苏鹰-杜明辉
 * Date: 2018/5/10
 * Time: 11:37
 */

namespace Admin\Controller;
use Admin\Model\PositionAuditModel;
use Admin\Model\PositionReleaseModel;
use Org\Util\Response;
use Think\Controller;

class PositionController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    public function index(){
        $this->assign('title','职位审核管理');
        $this->display('index');
    }

    public function lists(){
        $pageIndex = I('pageIndex',0);
        $pageIndex ++;
        $map = array();
        $Model = D('position');
        $count = $Model->where($map)->count();
        $data = $Model->relation(true)->getPositionByWhere($map,$pageIndex);
        return Response::mjson($data,$count);
    }

    //职位通过审核
    public function changeStatus(){
        $id = I("id");
        $data['position_id'] = $id;
        $position_release=D("position_release");
        $position_release->startTrans();
        $position_release_data = $position_release->where($data)->find();
        $position_audit_data["position_release_id"] = $position_release_data["position_release_id"];
        $position_audit_data["dateline"] = time();
        $position_release_data["status"] = 2;
        $position_release_data['release_time'] = NOW;
        $position_release_data['end_time'] = date('Y-m-d H:i:s', strtotime('+30days'));
        $position_release_data['update_time'] = date('Y-m-d H:i:s');
        $position_audit_data["results"]=1;
        $position_audit_data['audit_pass_time'] = NOW;
        $position_audit=D("position_audit");
        $result = $position_audit->add($position_audit_data);
        $result1 = $position_release->save($position_release_data);
//        echo $result.$result1;exit;
        if($result!==false && $result1!==false){
            $position_release->commit();
            return Response::show(200,"操作成功");
        }else{
            $position_release->rollback();
            return Response::show(400,"操作失败");
        }

    }

    /**
     * @return string 驳回操作
     */
    public function audit(){
        $reson = I('reson','');
        $id = I("id");

        $position_release=D("position_release");
        $position_release->startTrans();
        $data['position_id'] = $id;
        $position_release_data = $position_release->where($data)->find();

        $position_release_data["status"] = 4;
        $position_release_data['release_time'] = NOW;
        $position_release_data['update_time'] = date('Y-m-d H:i:s');
        $result1 = $position_release->save($position_release_data);

        $position_audit=D("position_audit");
        $position_audit_data["position_release_id"] = $position_release_data["position_release_id"];
        $position_audit_data["dateline"] = time();
        $position_audit_data["note"] = $reson;
        $position_audit_data["results"]=2;
        $result = $position_audit->add($position_audit_data);

        //退款转java接口
        $positionInfo = D("position")
            ->where(['position_id' => $position_release_data['position_id']])
            ->field('release_selection')
            ->find();
        if ($positionInfo['release_selection'] == 1) {
            $url = HGZ_ADMIN_URL . '/corporate/refundAmountForPosition';
            $param = [
                'positionReleaseId' => $position_release_data["position_release_id"],
            ];
            $res = curlMwJava($url, $param);
            switch ($res['code']) {
                case 200://正确
                case 201://正确(不需退款)
                    break;
                case 400://异常
                case 500://异常
                    $position_release->rollback();
                    return Response::show($res['code'], $res['message']);
                    break;
                default://错误
                    $position_release->rollback();
                    return Response::show(300, $res['message']);
                    break;
            }
        }
        //退款转java接口 end

        if($result!==false && $result1!==false){
            $position_release->commit();
            return Response::show(200,"操作成功");
        }else{
            $position_release->rollback();
            return Response::show(400,"操作失败");
        }
    }

    public function detail()
    {
        $rid = (int)I('rid', 0);
        $this->assign('rid', $rid);
        $this->display();
    }

    public function getInfo()
    {
        $rid = (int)I('rid', 0);

        $Model = D('position');
        $data = $Model->relation(true)->getPositionByWhere(['cxt_position.position_id' => $rid]);

        if (empty($data))
            $info = [];
        else {
            $info = $data[0];
            $returnRule = [];
            if (in_array(4, $info['release_selection_arr'])) {
                $returnRule = D('position')->getPositionReturnRule($rid);
            }
            $info['returnRule'] = $returnRule;
        }
        return Response::json(200, '', $info);
    }
}