<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Admin\Controller;
use Think\Cache\Driver\Memcachesae;
use Think\Controller;
use Org\Util\Response;
class WorkTypeController extends Controller {
    public function _initialize()
    {
        is_logout();
    }

    /**
     * 跳转新增编辑结算周期页面
     */
    public function addPaymentMethod(){
        $id = I("id","");
        if($id!=''){
            $payment_method = M("payment_method",'cxt_','db2');
            $data = $payment_method->where("payment_method_id=".$id)->find();
            $this->assign("data",$data);
        }
        $this->display();
    }

    /**
     * 跳转新增编辑用功形式
     */
    public function addEmploymentForm(){
        $id = I("id","");
        if($id!=''){
            $employment_form = M("employment_form",'cxt_','db2');
            $data = $employment_form->where("employment_form_id=".$id)->find();
            $this->assign("data",$data);
        }
        $this->display();
    }

    /**
     * @return string
     * 设置是否优选职位
     */
    public function updateWorkType(){
        $data["is_priority"]=I("is_priority","");
        $data["parent_work_type_id"]=I("parent_work_type_id","");
        $data["work_type_id"]=I("work_type_id","");
        $data["work_type_name"]=I("work_type_name","");
        $result = M("work_type","cxt_",'db2')->save($data);
        if($result!==false){
            return Response::show(200,"编辑成功");
        }else{
            return Response::show(400,"编辑失败");
        }
    }

    /**
     * 新增结算周期
     */
    public function  addPaymentMethodData(){
        $payment_method_name = I("payment_method_name","");
        $payment_method_id = I("payment_method_id","");
        if($payment_method_id!=''){
            $where["payment_method_id"] = array("neq",$payment_method_id);
            $data["payment_method_id"] = $payment_method_id;
        }
        if($payment_method_name==""){
            return Response::show(300,"结算方式不能为空");
        }
        $payment_method = M("payment_method","cxt_",'db2');
        $where["payment_method_id"] = array("eq",$payment_method_name);
        $result = $payment_method->where($where)->select();
        if(!empty($result)){
            return Response::show(300,"结算方式已存在");
        }
        $data["payment_method_name"]=$payment_method_name;
        if($payment_method_id!=''){
            $result = $payment_method->save($data);
        }else{
            $result = $payment_method->add($data);
        }

        if($result!==false){
            return Response::show(200,"新增成功");
        }else{
            return Response::show(400,"新增失败");
        }
    }
    /**
     * 新增工种
     */
    public function addWorkTypeData(){
        $work_type_name = I("work_type_name","");
        $parent_work_type_id = I("parent_work_type_id",0);
        if($work_type_name==""){
            return Response::show(300,"工种名称不能为空");
        }
        $data["work_type_name"]=$work_type_name;
        $data["parent_work_type_id"]=$parent_work_type_id;

        $worktype = M("work_type","cxt_",'db2');
        $where["work_type_name"] = array("eq",$work_type_name);
        $work_type_id = I("work_type_id",'');
        if($work_type_id!=""){
            $where["work_type_id"] = array("neq",$work_type_id);
            $data['work_type_id'] = $work_type_id;
        }
        $result = $worktype->where($where)->select();
        if(!empty($result)){
            return Response::show(300,"当前工种已存在");
        }
        if($work_type_id==""){
            $result = $worktype->add($data);
        }else{
            $result = $worktype->save($data);
        }
        if($result!==false){
            return Response::show(200,"操作成功");
        }else{
            return Response::show(400,"操作失败");
        }
    }

    /**
     * 跳转新增编辑工种页面
     */
    public function addWorkType(){
        $id = I("id","");
        $worktype = M("work_type","cxt_",'db2');
        $result = $worktype->where("parent_work_type_id=0 and status=0")->select();
        $result1 = array(array(0=>'无'));
        foreach ($result as $key=>$value){
            $result1[$value["work_type_id"]] = $value["work_type_name"];
        }
        if($id!=""){
            $result = $worktype->where("work_type_id=".$id)->find();
            $this->assign("worktype",$result);
        }
        $data["all"]=json_encode($result1);
        $this->assign("data",$data);
        $this->display();
    }
    /**
     * 新增结算进度
     */
    public function  addEmploymentFormData(){
        $employment_form_name = I("employment_form_name","");
        $employment_form_id = I("employment_form_id","");
        if($employment_form_id!==""){
            $where["employment_form_id"] = array("neq",$employment_form_id);
            $data["employment_form_id"] = $employment_form_id;
        }
        if($employment_form_name==''){
            return Response::show(300,"结算方式不能为空");
        }
        $where["employment_form_name"] = array("eq",$employment_form_name);
        $result = M("employment_form",'cxt_','db2')->where($where)->find();
        if(!empty($result)){
            return Response::show(300,"当前结算方式已存在");
        }
        $data["employment_form_name"]= $employment_form_name;
        if($employment_form_id!==""){
            $result = M("employment_form",'cxt_','db2')->save($data);
        }else{
            $result = M("employment_form",'cxt_','db2')->add($data);
        }
        if($result!==false){
            return Response::show(200,"新增成功");
        }else{
            return Response::show(400,"新增失败");
        }
    }
    public function workTypeList(){
        $pageIndex = I('start',0);
        $work_type = M("work_type","cxt_",'db2');
        $result = $work_type->limit($pageIndex,10)->select();
        for($i=0;$i<count($result);$i++){
            $work_type_data = $result[$i];
            if( $work_type_data["parent_work_type_id"]!=0){
                $where["work_type_id"] = $work_type_data["parent_work_type_id"];
                $work_type_data_p =  $work_type->where($where)->find();
                $result[$i]["parent_work_type_name"] = $work_type_data_p["work_type_name"];
            }else{
                $result[$i]["parent_work_type_name"] = "无";
            }

        }
        $count = $work_type->count();
        return Response::mjson($result,$count);
    }


    public function EmploymentFormList(){
        $pageIndex = I('start',0);
        $employment_form = M("employment_form","cxt_",'db2');
        $result = $employment_form->limit($pageIndex,10)->select();
        $count = $employment_form->count();
        return Response::mjson($result,$count);
    }

    public function PaymentethodList(){
        $pageIndex = I('start',0);
        $employment_form = M("payment_method","cxt_",'db2');
        $result = $employment_form->limit($pageIndex,10)->select();
        $count = $employment_form->count();
        return Response::mjson($result,$count);
    }


    public function positionWelfareList(){
        $pageIndex = I('start',0);
        $position_welfare = M("position_welfare","cxt_",'db2');
        $result = $position_welfare->limit($pageIndex,10)->select();
        $count = $position_welfare->count();
        return Response::mjson($result,$count);
    }

    public function addPositionWelfareData(){
        $position_welfare_name = I("position_welfare_name","");
        $position_welfare_id = I("position_welfare_id","");
        if($position_welfare_id!==""){
            $where["position_welfare_id"] = array("neq",$position_welfare_id);
            $data["position_welfare_id"] = $position_welfare_id;
        }
        if($position_welfare_name==''){
            return Response::show(300,"福利名称不能为空");
        }
        $where["position_welfare_name"] = array("eq",$position_welfare_name);
        $result = M("position_welfare",'cxt_','db2')->where($where)->find();
        if(!empty($result)){
            return Response::show(300,"当前福利名称已存在");
        }
        $data["position_welfare_name"]= $position_welfare_name;
        if($position_welfare_id!==""){
            $result = M("position_welfare",'cxt_','db2')->save($data);
        }else{
            $result = M("position_welfare",'cxt_','db2')->add($data);
        }
        if($result!==false){
            return Response::show(200,"新增成功");
        }else{
            return Response::show(400,"新增失败");
        }
    }
    /**
     * 跳转新增编辑职位福利
     */
    public function addPositionWelfare(){
        $id = I("id","");
        if($id!=''){
            $position_welfare = M("position_welfare","cxt_",'db2');
            $result = $position_welfare->where("position_welfare_id=".$id)->find();
            $this->assign("data",$result);
        }
        $this->display();
    }

    /**
     * 设置职位类型状态
     */
    public function changeStatus()
    {
        $data = [];
        $data["status"] = I("status", 0);
        $data["work_type_id"] = I("work_type_id", 0);
        $result = M("work_type", "cxt_", 'db2')->save($data);
        if ($result !== false) {
            return Response::show(200, "设置成功");
        } else {
            return Response::show(400, "设置失败");
        }
    }

    /**
     * 设置用工形式状态
     */
    public function changeEmployStatus()
    {
        $data = [];
        $data["status"] = I("status", 0);
        $data["employment_form_id"] = I("employment_form_id", 0);
        $result = M("employment_form", "cxt_", 'db2')->save($data);
        if ($result !== false) {
            return Response::show(200, "设置成功");
        } else {
            return Response::show(400, "设置失败");
        }
    }

    /**
     * 设置结算周期状态
     */
    public function changePayStatus()
    {
        $data = [];
        $data["status"] = I("status", 0);
        $data["payment_method_id"] = I("payment_method_id", 0);
        $result = M("payment_method", "cxt_", 'db2')->save($data);
        if ($result !== false) {
            return Response::show(200, "设置成功");
        } else {
            return Response::show(400, "设置失败");
        }
    }

    /**
     * 设置职位福利状态
     */
    public function changeJobStatus()
    {
        $data = [];
        $data["status"] = I("status", 0);
        $data["position_welfare_id"] = I("position_welfare_id", 0);
        $result = M("position_welfare", "cxt_", 'db2')->save($data);
        if ($result !== false) {
            return Response::show(200, "设置成功");
        } else {
            return Response::show(400, "设置失败");
        }
    }

}
