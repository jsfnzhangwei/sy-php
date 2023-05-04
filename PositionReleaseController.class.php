<?php
/**
 * Created by PhpStorm.
 * User: 苏鹰-杜明辉
 * Date: 2018/5/9
 * Time: 14:39
 */

namespace Admin\Controller;


use Admin\Model\PositionReleaseModel;
use Org\Util\Response;
use Think\Controller;
use Think\Db;

class PositionReleaseController extends Controller
{
    public function _initialize()
    {
        is_logout();
    }

    public function index(){
            $this->assign('title','职位列表');
            $this->display('index');
        }

    /**
     * 读取数据列表
     */
        public function listsQy(){
            $pageIndex = I('pageIndex',0);
            $pageIndex ++;
            $map = array();
            $Model = new PositionReleaseModel();
            $count = $Model->where($map)->count();
            $data = $Model->getPositionByWhere($map,$pageIndex);
            $data = $this->formatData($data);
            return Response::mjson($data,$count);
        }

    public function lists()
    {
        $pageIndex = I('pageIndex', 0);
        $pageIndex++;
        $limit = 10;

        $title = I('title', '');
        $status = I('status', -1);
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');

        $Model = new PositionReleaseModel();

        $map = [];
        $map['status'] = 1;
        if (!empty($title)) {
            $positionModel = M('position', 'cxt_', 'db2');
            $positionIds = $positionModel->where(['position_name' => ['like', '%' . $title . '%']])->getField('position_id', true);
            if (empty($positionIds)) {
                $positionIds = [-1];
            }
            $map['position_id'] = ['in', $positionIds];
        }
        if (in_array($status, [1, 2, 3, 4, 99])) {
            $map['status'] = $status;
        } else {
            $map['status'] = ['in', [2, 3, 4, 99]];
        }
        if (!empty($startDate)) {
            $map['create_time'][] = ['egt', $startDate . ' 00:00:00'];
        }
        if (!empty($endDate)) {
            $map['create_time'][] = ['elt', $endDate . ' 23:59:59'];
        }

        $count = $Model->where($map)->count();
        $data = $Model->where($map)
            ->page($pageIndex, $limit)
            ->field('position_release_id,position_id,status,update_time create_time,user_id,release_time')
            ->order('position_release_id desc')
            ->select();
        $data = $this->formatData($data);
        return Response::mjson($data, $count);
    }

    private function formatData($array)
    {
        $positionModel = M('position', 'cxt_', 'db2');
        $employmentFormModel = M('EmploymentForm', 'cxt_', 'db2');
        $workTypeModel = M('WorkType', 'cxt_', 'db2');
        $paymentMethodModel = M('PaymentMethod', 'cxt_', 'db2');
        $corporateModel = M('corporate', 'cxt_', 'db2');
        $userModel = M('user', 'cxt_', 'db2');
        $regionModel = M('region', 'cxt_', 'db2');

        $data = [];
        foreach ($array as $key => $item) {
            $info = $positionModel
                ->where(['position_id' => $item['position_id']])
//                ->field('position_id,position_name,educational_requirements,work_years,sex_requirements,wage,wage_unit,work_province,work_city,work_area,work_address,employment_form_id,work_type_id,payment_method_id,corporate_id,position_welfare_names')
                ->find();
            $info['position_release_id'] = $item['position_release_id'];
            $info['status'] = $item['status'];
            $info['create_time'] = $item['create_time'];
            $info['release_time'] = $item['release_time'];
            if (empty($info['release_time'])) {
                $info['release_time'] = '--';
            }
            $info['educational_requirements'] = $this->getEducation($info['educational_requirements']);
            $info['work_years'] = $this->getWorkExperience($info['work_years']);
            $info['statusname'] = $this->getStatus($info['status']);
            $info['sex'] = $this->getSex($info['sex_requirements']);
            $wage = $this->getWage($info['wage']);
            $wage_unit = $this->getWageUnit($info['wage_unit']);

            $info['wage'] = $wage . $wage_unit;
            $province = $regionModel->where(['region_code' => $info['work_province']])->getField('region_name');
            $city = $regionModel->where(['region_code' => $info['work_city']])->getField('region_name');
            if (in_array($city, ['市辖区', '县'])) {
                $city = '';
            }
            $area = $regionModel->where(['region_code' => $info['work_area']])->getField('region_name');
            $info['work_address'] = $province . $city . $area . $info['work_address'];

            $info['employment_form_name'] = $employmentFormModel->where(['employment_form_id' => $info['employment_form_id']])->getField('employment_form_name');
            $info['work_type_name'] = $workTypeModel->where(['work_type_id' => $info['work_type_id']])->getField('work_type_name');
            $info['payment_method_name'] = $paymentMethodModel->where(['payment_method_id' => $info['payment_method_id']])->getField('payment_method_name');
            $info['corporateName'] = $corporateModel->where(['corporate_id' => $info['corporate_id']])->getField('corporate_name');
            if ($info['status'] == 1) {
                $userInfo = $userModel->where(['user_id' => $item['user_id']])->field('name,mobile')->find();
                $info['uname'] = $userInfo['name'];
                $info['umobile'] = $userInfo['mobile'];
            }
            $info['video'] = format_img($info['video']);
            $info['enterprise_location'] = format_img($info['enterprise_location']);
            $info['work_environment'] = format_img($info['work_environment']);
            $info['canteen'] = format_img($info['canteen']);
            $info['dormitory'] = format_img($info['dormitory']);
            $info['head_pic'] = format_img($info['head_pic']);
            $info['release_selection_arr'] = explode(',', $info['release_selection']);
            $info['release_selection'] = getReleaseSelection($info['release_selection']);
            $data[] = $info;
        }
        return $data;
    }

        /**
         * 更新状态
         * @return string
         */
        public function changeStatus(){
            $ids = I('ids','');
            $status = I('status',0);
            foreach ($ids as $id){
                if(0<=$status && $status<=4){
                    $data['id'] = $id;
                    $data['status'] = $status;
                    if($data['status'] == 2){
                        $data['release_time'] = NOW;
                        $data['end_time'] = date('Y-m-d H:i:s', strtotime('+30days'));
                    }
                    $Model =  new PositionReleaseModel();
                    $flag = $Model->updatePosition($data);
                    return Response::show($flag['code'],$flag['msg']);
                }
            }
        }

    /**
     * 审核管理列表
     */
        public function auditingMangeList(){
            $this->assign('title','审核管理列表');
            $this->display('mageList');
        }

        public function auditingMange(){
            $pageIndex = I('pageIndex',0);
            $pageIndex ++;
            $map = array();
            $Model = new PositionModel();
            $count = M('','cxt_position_release')->where($map)->count();
            $data = $Model->getPositionByWhere($map,$pageIndex);
            return Response::mjson($data,$count);
        }

    private function getEducation($key)
    {
        $education = array(
            '0' => '不限',
            '1' => '初中以下',
            '2' => '初中',
            '3' => '高中',
            '4' => '中专',
            '5' => '大专',
            '6' => '本科',
            '7' => '硕士',
            '8' => '博士及以上'
        );
        return (isset($education[$key])) ? $education[$key] : '不限';
    }

    private function getWorkExperience($key)
    {
        $workExperience = array(
            '0' => '不限',
            '1' => '无经验',
            '2' => '1年以下',
            '3' => '1-3年',
            '4' => '3-5年',
            '5' => '5-10年',
            '6' => '10年以上',
        );
        return (isset($workExperience[$key])) ? $workExperience[$key] : '不限';
    }

    private function getStatus($key)
    {
        $status = array(
            '1' => '审核中',
            '2' => '已上架',
            '3' => '已下架',
            '4' => '审核不通过',
            '99' => '已删除',
        );
        return (isset($status[$key])) ? $status[$key] : '未知';
    }

    private function getSex($key)
    {
        $sex = array(
            '0' => '不限',
            '1' => '男',
            '2' => '女'
        );
        return (isset($sex[$key])) ? $sex[$key] : '不限';
    }

    private function getWage($key)
    {
        $wage = array(
            '-1' => '1-2千',
            '-2' => '2-3千',
            '-3' => '3-4千',
            '-4' => '4-5千',
            '-5' => '5-6千',
            '-6' => '6-7千',
            '-7' => '7-8千',
            '-8' => '8-9千',
            '-9' => '0.9-1万',
            '-10' => '1万以上'
        );
        return (isset($wage[$key])) ? $wage[$key] : $key;
    }

    private function getWageUnit($key)
    {
        $wageUnit = array(
            '0' => '元/月',
            '1' => '元/小时',
            '2' => '元/次',
            '3' => '元/天',
            '4' => '元/单'
        );
        return (isset($wageUnit[$key])) ? $wageUnit[$key] : '未知';
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

        $Model = new PositionReleaseModel();

        $data = $Model->where(['position_id' => $rid])
            ->field('position_release_id,position_id,status,create_time,user_id,release_time')
            ->order('position_release_id desc')
            ->select();

        if (empty($data))
            $info = [];
        else {
            $data = $this->formatData($data);
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