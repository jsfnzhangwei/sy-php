<?php
/**
 * Created by PhpStorm.
 * User: 苏鹰-马洁
 * Date: 2018/12/28
 * Time: 10:55
 */

namespace Admin\Controller;
use Think\Controller;
use Org\Util\Response;

class ExamController extends Controller
{
    /**
     * 学生列表首页
     */
    public function student_index(){
        $this->display();
    }
    /**
     * 学生列表数据
     */
    public function student_list(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        $mobile = I('mobile');
        $start_time = I('start1');
        $end_time = I('end');
        $status = I('status');


        if(!empty($name)){
            $where['a.name'] = array('like',"%$name%");
        }
        if(!empty($mobile)){
            $where['b.mobile'] = array('eq',$mobile);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }
        if($status == 1){
            $where['a.status'] = array('eq',1);
        }else{
            $where['a.status'] = array('in',[0,2]);
        }
        $result = M('exam_student')
                    ->alias('a')
                    ->field('a.*,b.mobile')
                    ->join('left join dsy_exam_login as b on a.uid=b.id')
                    ->where($where)
                    ->order('a.id desc')
                    ->page($page,$limit)
                    ->select();
        $count = M('exam_student')
                    ->alias('a')
                    ->join('left join dsy_exam_login as b on a.uid=b.id')
                    ->where($where)
                    ->count();
        $data = examData();
        foreach($result as $key => $val){
            $result[$key]['full_time'] = $val['full_time'] == 1 ? '是' : '否';
            $result[$key]['position'] = $data['position'][$val['position']];
            $result[$key]['education'] = $data['education'][$val['education']];
            $result[$key]['degree'] = $data['degree'][$val['degree']];
            $result[$key]['language_grade'] = $data['language_grade'][$val['language_grade']];
        }
        return Response::mjson($result,$count);
    }
    public function lookImg(){
        $pic = I('pic');
        $this->assign('pic',$pic);
        $this->display();
    }
    /**
     * 考生注册审核首页
     */
    public function student_register_index(){
        $this->display();
    }
    /**
     * 考生注册同意审核
     */
    public function student_register_agree(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'请选择要同意的考生注册审核。');
        }
        $result = M('exam_student')
                    ->where(['id'=>$id])
                    ->save(['status'=>1]);
        if($result){
            $mobile = M('exam_student')
                        ->alias('a')
                        ->join('left join dsy_exam_login as b on a.uid=b.id')
                        ->where(['a.id'=>$id])
                        ->getField('b.mobile');
            $result = SendTemplateSMS($mobile,[],'1452256',1);
            return Response::show(200,'审核同意成功');
        }else{
            return Response::show(300,'审核同意失败');
        }
    }
    /**
     * 考生注册驳回审核
     */
    public function student_register_reject(){
        $id = I('id');
        $reason = I('reason');
        if(empty($id)){
            return Response::show(300,'请选择要驳回的考生注册审核。');
        }
        if(empty($reason)){
            return Response::show(300,'请填写驳回原因。');
        }
        $data = [
            'status' => 2,
            'reason' => $reason
        ];
        $result = M('exam_student')
                    ->where(['id'=>$id])
                    ->save($data);
        if($result){
            $mobile = M('exam_student')
                            ->alias('a')
                            ->join('left join dsy_exam_login as b on a.uid=b.id')
                            ->where(['a.id'=>$id])
                            ->getField('b.mobile');
            $result = SendTemplateSMS($mobile,[$reason],'1424606',1);
            return Response::show(200,'审核驳回成功');
        }else{
            return Response::show(300,'审核驳回失败');
        }
    }
    /**
     * 考生信息变更
     */
    public function student_message_change(){
        $this->display();
    }
    /**
     * 考生信息变更列表
     */
    public function student_message_list(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        $mobile = I('mobile');
        $start_time = I('start1');
        $end_time = I('end');


        if(!empty($name)){
            $where['a.name'] = array('like',"%$name%");
        }
        if(!empty($mobile)){
            $where['b.mobile'] = array('eq',$mobile);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['b.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['b.create_time'] = array('between',array($start_time,$end_time));
            }
        }
        $where['a.status'] = array('eq',1);
        $where['a.edit_status'] = array('eq',1);
        $result = M('exam_student')
                    ->alias('a')
                    ->field('a.*,b.mobile')
                    ->join('left join dsy_exam_login as b on a.uid=b.id')
                    ->where($where)
                    ->order('a.id desc')
                    ->page($page,$limit)
                    ->select();
        $count = M('exam_student')
                    ->alias('a')
                    ->join('left join dsy_exam_login as b on a.uid=b.id')
                    ->where($where)
                    ->count();
        $data = examData();
        foreach($result as $key => $val){
            $before_data = json_decode($val['edit_before_data'],true);
            $result[$key]['name'] = $before_data['name'];
            $result[$key]['id_number'] = $before_data['id_number'];
            $result[$key]['user_photo'] = $before_data['user_photo'];
            $result[$key]['diploma_photo'] = $before_data['diploma_photo'];
            $result[$key]['degree_photo'] = $before_data['degree_photo'];
            $result[$key]['other_photo'] = $before_data['other_photo'];
            $result[$key]['full_time'] = $before_data['full_time'] == 1 ? '是' : '否';
            $result[$key]['position'] = $data['position'][$before_data['position']];
            $result[$key]['education'] = $data['education'][$before_data['education']];
            $result[$key]['degree'] = $data['degree'][$before_data['degree']];
            $result[$key]['language_grade'] = $data['language_grade'][$before_data['language_grade']];
            $result[$key]['registration_url'] = $before_data['registration_url'];
            $result[$key]['graduate_time'] = $before_data['graduate_time'];
        }
        return Response::mjson($result,$count);
    }
    /**
     * 考生信息修改同意审核
     */
    public function student_message_agree(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'请选择要同意的考生信息修改审核。');
        }
        $before_data = M('exam_student')
                        ->field('edit_before_data')
                        ->where(['id'=>$id])
                        ->find();
        $before_data = json_decode($before_data['edit_before_data'],true);
        $data = [
                 'name' => $before_data['name'],
            'id_number' => $before_data['id_number'],
           'user_photo' => $before_data['user_photo'],
        'diploma_photo' => $before_data['diploma_photo'],
         'degree_photo' => $before_data['degree_photo'],
          'other_photo' => $before_data['other_photo'],
            'full_time' => $before_data['full_time'],
             'position' => $before_data['position'],
            'education' => $before_data['education'],
               'degree' => $before_data['degree'],
       'language_grade' => $before_data['language_grade'],
     'registration_url' => $before_data['registration_url'],
        'graduate_time' => $before_data['graduate_time'],
          'edit_status' => 0,
     'edit_before_data' => ''
        ];
        $result = M('exam_student')
                    ->where(['id'=>$id])
                    ->save($data);
        if($result){
            return Response::show(200,'审核同意成功');
        }else{
            return Response::show(300,'审核同意失败');
        }
    }
        /**
     * 考生信息修改驳回审核
     */
    public function student_message_reject(){
        $id = I('id');
        $reason = I('reason');
        if(empty($id)){
            return Response::show(300,'请选择要驳回的考生信息修改审核。');
        }
        if(empty($reason)){
            return Response::show(300,'请填写驳回原因。');
        }
        $data = [
          'edit_status' => 2,
               'reason' => $reason,
     'edit_before_data' => ''
        ];
        $result = M('exam_student')
                    ->where(['id'=>$id])
                    ->save($data);
        if($result){
            return Response::show(200,'审核驳回成功');
        }else{
            return Response::show(300,'审核驳回失败');
        }
    }
    /**
     * 考试安排
     */
    public function exam_arrange_index(){
        $this->display();
    }
    /**
     * 考试列表
     */
    public function exam_arrange_list(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        $start_time = I('start1');
        $end_time = I('end');


        if(!empty($name)){
            $where['test_name'] = array('like',"%$name%");
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['test_time'] = array('eq',$start_time);
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['test_time'] = array('between',array($start_time,$end_time));
            }
        }

        $result = M('exam_detail')
                    ->where($where)
                    ->order('id desc')
                    ->page($page,$limit)
                    ->select();
        
        $count = M('exam_detail')
                    ->where($where)
                    ->count();

        return Response::mjson($result,$count);
    }
    /**
     * 考试新增首页
     */
    public function exam_arrange_add(){
        $num = 1;
        $this->assign('num',$num);
        $this->display();
    }
    public function exam_arrange_insert(){
        $test_time = I('test_time');
        $test_name = I('test_name');
        $test_address = I('test_address');
        $test_remind = I('test_remind');
        $name = I('name');
        $start = I('start');
        $end = I('end');
        $student_url = I('student_url');
        
        if(empty($test_time)){
            return Response::show(300,'考试日期不能为空');
        }
        if(empty($test_name)){
            return Response::show(300,'考试日期不能为空');
        }
        if(empty($test_address)){
            return Response::show(300,'考试地点不能为空');
        }
        if(empty($name)){
            return Response::show(300,'考试科目不能为空');
        }
        if(empty($student_url)){
            return Response::show(300,'请上传考生信息');
        }
        $test_content = [];
        foreach($name as $key=>$val){
            $test_content[] = [
                'name' => $val,
                'start' => $start[$key],
                'end' => $end[$key]
            ];
        }
        $time =  date('Y-m-d H:i:s',time());
        $data = [
            'test_time' => $test_time,
            'test_name' => $test_name,
            'test_address' => $test_address,
            'test_content' => json_encode($test_content),
            'test_remind' => $test_remind,
            'create_time' => $time
        ];

        $result = M('exam_detail')->add($data);
        if($result){
            $student = $this->student_get_data($student_url);
            $student_data = [];
            foreach($student as $val){
                $student_data[] = [
                    'uid' => $val['uid'],
                    'exam_id' => $result,
                    'create_time' => $time
                ];
            }
            $addStudent = M('exam_examinee')->addAll($student_data);
            return Response::show(200,'添加成功');
        }else{
            return Response::show(300,'添加失败');
        }
    }
    /**
     * 考试编辑
     */
    public function exam_arrange_edit(){
        $id = I('id');
        $result = M('exam_detail')->where(['id'=>$id])->find();
        $result['test_content'] = json_decode($result['test_content'],true);
        $num = count($result['test_content'],true);
        $this->assign('num',$num);
        $this->assign('result',$result);
        $this->display();
    }
    /**
     * 考试编辑保存
     */
    public function exam_arrange_save(){
        $id = I('id');
        $test_time = I('test_time');
        $test_name = I('test_name');
        $test_address = I('test_address');
        $test_remind = I('test_remind');
        $name = I('name');
        $start = I('start');
        $end = I('end');
        
        if(empty($test_time)){
            return Response::show(300,'考试日期不能为空');
        }
        if(empty($test_name)){
            return Response::show(300,'考试日期不能为空');
        }
        if(empty($test_address)){
            return Response::show(300,'考试地点不能为空');
        }
        if(empty($name)){
            return Response::show(300,'考试科目不能为空');
        }
        $test_content = [];
        foreach($name as $key=>$val){
            $test_content[] = [
                'name' => $val,
                'start' => $start[$key],
                'end' => $end[$key]
            ];
        }
        $time =  date('Y-m-d H:i:s',time());
        $data = [
            'test_time' => $test_time,
            'test_name' => $test_name,
            'test_address' => $test_address,
            'test_content' => json_encode($test_content),
            'test_remind' => $test_remind,
            'update_time' => $time
        ];

        $result = M('exam_detail')->where(['id'=>$id])->save($data);
        if($result){
            return Response::show(200,'修改成功');
        }else{
            return Response::show(300,'修改失败');
        }
    }
    /**
     * 考试删除
     */
    public function exam_arrange_delete(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'请选择要删除的考试。');
        }
        $result = M('exam_detail')->where(['id'=>$id])->delete();
        if($result){
            $del_student = M('exam_examinee')->where(['exam_id'=>$id])->delete();
            return Response::show(200,'删除成功');
        }else{
            return Response::show(300,'删除失败');
        }
    }
    /**
     * 考生删除
     */
    public function exam_student_delete(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'请选择要删除的考生。');
        }
        $result = M('exam_examinee')->where(['id'=>$id])->delete();
        if($result){
            return Response::show(200,'删除成功');
        }else{
            return Response::show(300,'删除失败');
        }
    }
    /**
     * 考生名单首页
     */
    public function exam_student_index(){
        $id = I('id');
        $this->assign('id',$id);
        $this->display();
    }
    /**
     * 考生名单数据
     */
    public function exam_student_data(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $id = I('id');
        $name = I('name');
        $mobile = I('mobile');

        if(!empty($name)){
            $where['b.name'] = array('like',"%$name%");
        }
        if(!empty($mobile)){
            $where['c.mobile'] = array('eq',$mobile);
        }
        $where['a.exam_id'] = array('eq',$id);

        $result = M('exam_examinee')
                    ->alias('a')
                    ->field('a.id,b.name,b.id_number,c.mobile')
                    ->join('left join dsy_exam_student as b on a.uid=b.uid')
                    ->join('left join dsy_exam_login as c on a.uid=c.id')
                    ->where($where)
                    ->page($page,$limit)
                    ->select();
           $num = M('exam_examinee')
                    ->alias('a')
                    ->join('left join dsy_exam_student as b on a.uid=b.uid')
                    ->join('left join dsy_exam_login as c on a.uid=c.id')
                    ->where($where)
                    ->count();
        return Response::mjson($result,$num);
    }
    /**
     * 成绩发放
     */
    public function result_index(){
        $this->display();
    }
    /**
     * 成绩列表
     */
    public function result_list(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $name = I('name');
        $start_time = I('start1');
        $end_time = I('end');


        if(!empty($name)){
            $where['a.test_name'] = array('like',"%$name%");
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.test_time'] = array('eq',$start_time);
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.test_time'] = array('between',array($start_time,$end_time));
            }
        }
        $result = M('exam_detail')
                    ->alias('a')
                    ->field('a.*,b.update_time')
                    ->join('left join dsy_exam_examinee as b on a.id=b.exam_id')
                    ->where($where)
                    ->group('a.id')
                    ->order('id desc')
                    ->page($page,$limit)
                    ->select();
        foreach($result as $key => $val){
            $result[$key]['num1'] = M('exam_examinee')->where(['exam_id'=>$val['id']])->count();
            $result[$key]['num2'] = M('exam_examinee')->where(['exam_id'=>$val['id'],'achievement'=>array('neq','')])->count();
        }
        $count = M('exam_detail')
                    ->where($where)
                    ->count();

        return Response::mjson($result,$count);
    }
    /**
     * 考试成绩单新增页
     */
    public function result_fraction_add(){
        $id = I('id');
        $result = M('exam_detail')->where(['id'=>$id])->find();
        $this->assign('result',$result);
        $this->display();
    }
    /**
     * 考试成绩单保存
     */
    public function result_fraction_save(){
        $id = I('id');
        $fraction_url = I('student_url');
        if(empty($fraction_url)){
            return Response::show(200,'请上传学生成绩');
        }
        $time = date('Y-m-d H:i:s',time());
        $fraction = $this->fraction_get_data($fraction_url);
        foreach($fraction as $key => $val){
            $result = M('exam_examinee')->where(['exam_id'=>$id,'uid'=>$key])->save(['achievement'=>json_encode($val),'update_time'=>$time]);
        }
        if($result){
            return Response::show(200,'发布成功');
        }else{
            return Response::show(200,'发布失败');
        }
    }
    /**
     * 考试成绩单详情
     */
    public function result_detail_index(){
        $pageIndex = I('pageIndex','');
        $limit = 10;
        $page = $pageIndex+1;
        $id = I('id');
        $name = I('name');
        $mobile = I('mobile');

        if(!empty($name)){
            $where['b.name'] = array('like',"%$name%");
        }
        if(!empty($mobile)){
            $where['c.mobile'] = array('eq',$mobile);
        }
        $where['a.exam_id'] = array('eq',$id);
        $num = M('exam_examinee')
                    ->alias('a')
                    ->join('left join dsy_exam_student as b on a.uid=b.uid')
                    ->join('left join dsy_exam_login as c on a.uid=c.id')
                    ->where($where)
                    ->count();
        $Page = new \Think\Page($num,10);// 实例化分页类 传入总记录数和每页显示的记录数

        $result = M('exam_examinee')
                    ->alias('a')
                    ->field('a.*,b.name,b.id_number,c.mobile')
                    ->join('left join dsy_exam_student as b on a.uid=b.uid')
                    ->join('left join dsy_exam_login as c on a.uid=c.id')
                    ->where($where)
                    ->limit($Page->firstRow.','.$Page->listRows)
                    ->select();
        $Page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
        $show = $Page->show();// 分页显示输出
        $count = count(json_decode($result[0]['achievement']),true);
        foreach($result as $key=>$val){
            $result[$key]['achievement'] = json_decode($val['achievement'],true);
        }
        $exam_name = M('exam_detail')->field('test_name')->where(['id'=>$id])->find();
        $this->assign('result',$result);
        $this->assign('exam_name',$exam_name);
        $this->assign('num2',$count);
        $this->assign('page',$show);// 赋值分页输出   
        $this->display();
    }
    /**
     * 考生成绩删除
     */
    public function result_delete(){
        $id = I('id');
        if(empty($id)){
            return Response::show(300,'请选择要删除的考生。');
        }
        $result = M('exam_examinee')->where(['exam_id'=>$id])->save(['achievement'=>'']);
        if($result){
            return Response::show(200,'删除成功');
        }else{
            return Response::show(300,'删除失败');
        }
    }
    /**
     * 考生数据导入
     */
    public function student_import_data(){
        import("Vendor.PHPExcel.PHPExcel");
        import("Vendor.PHPExcel.PHPExcel.Writer.Excel5");
        import("Vendor.PHPExcel.PHPExcel.IOFactory.php");
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;
        $cacheSettings = array( 'memoryCacheSize ' => '8MB');
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
        // 实例化exel对象
        //文件路径
        $files = $_FILES['excel'];
        $upload = new \Think\Upload();// 实例化上传类
	    $upload->maxSize   =     3145728 ;// 设置附件上传大小
	    $upload->exts      =     array('xlsx', 'xls');// 设置附件上传类型
	    $upload->rootPath  =     './'; // 设置附件上传根目录
        $upload->savePath  =     'Public/Uploads/temp/'; // 设置附件上传（子）目录
        $upload->subName   =     array('date','Ymd');
	    $info   =   $upload->uploadOne($files);
	    if(!$info) {
            // 上传错误提示错误信息
            return Response::show(300,$upload->getError()); 
		}else{
			// 上传成功 获取上传文件信息
            $infopath = $upload->rootPath.$info['savepath'].$info['savename'];
		}
        $file_path = $infopath;
        //文件的扩展名
        $ext = strtolower(pathinfo($file_path,PATHINFO_EXTENSION));
        if ($ext == 'xlsx'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objPHPExcel = $objReader->load($file_path);
        }elseif($ext == 'xls'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel5');
            $objPHPExcel = $objReader->load($file_path);
        }
        $objReader->setReadDataOnly(true);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();//获取总行数
        $highestColumn = $sheet->getHighestColumn();//获取总列数
        $record = array();//申明每条记录数组
        $num = 0;
        $error = [];//记录错误数据
        for ($i = 2;$i<=$highestRow;$i++){
            $col = 0;
            for ($j = 'A';$j<=$highestColumn;$j++){
                $record[$num][$col] = str_replace(array("\r\n", "\r", "\n","\t","\r\t","\t\r"), "",$objPHPExcel->getActiveSheet()->getCell("$j$i")->getValue());//读取单元格
                $col++;
            }
            header("Content-type:text/html;charset=utf-8");
            $num++;
        }
        if(!empty($record)){
            $student = array_column($record,2);
            $result = M('exam_student')->field('id_number')->where(['id_number'=>array('in',$student)])->select();
            $checkData = array_diff($student,array_column($result,'id_number'));
            if(empty($checkData)){
                // 上传成功 获取上传文件信息
                $data['contract_path'] = $file_path;
                $data['contract_name'] = $files['name'];
                return Response::show(200,$data);
            }else{
                foreach($record as $key => $val){
                    if(in_array($val[2],$checkData)){
                        $col = $key + 2;
                        $error[] = '第' . $col . '行' . $val[0] . '考生不存在';
                    }
                }
                $str = '错误提示:';
                foreach($error as $val){
                    $str.="<p>" . $val . "</p>";
                }
                $str .= '请重新填写后再次导入';
                return Response::show(300,$str);
            }
        }else{
            return Response::show(300,'请导入正确的考生信息文件');
        }
    }
    /**
     * 获取考生信息
     */
    public function student_get_data($file_path=null){
        import("Vendor.PHPExcel.PHPExcel");
        import("Vendor.PHPExcel.PHPExcel.Writer.Excel5");
        import("Vendor.PHPExcel.PHPExcel.IOFactory.php");
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;
        $cacheSettings = array( 'memoryCacheSize ' => '8MB');
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
        // 实例化exel对象

        //文件的扩展名
        $ext = strtolower(pathinfo($file_path,PATHINFO_EXTENSION));
        if ($ext == 'xlsx'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objPHPExcel = $objReader->load($file_path);
        }elseif($ext == 'xls'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel5');
            $objPHPExcel = $objReader->load($file_path);
        }
        $objReader->setReadDataOnly(true);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();//获取总行数
        $highestColumn = $sheet->getHighestColumn();//获取总列数
        $record = array();//申明每条记录数组
        $num = 0;
        $error = [];//记录错误数据
        for ($i = 2;$i<=$highestRow;$i++){
            $col = 0;
            for ($j = 'A';$j<=$highestColumn;$j++){
                $record[$num][$col] = str_replace(array("\r\n", "\r", "\n","\t","\r\t","\t\r"), "",$objPHPExcel->getActiveSheet()->getCell("$j$i")->getValue());//读取单元格
                $col++;
            }
            header("Content-type:text/html;charset=utf-8");
            $num++;
        }
        $student = array_column($record,2);
        $result = M('exam_student')->field('uid')->where(['id_number'=>array('in',$student)])->select();
        return $result;
    }
    /**
     * 考生数据导入
     */
    public function student_fraction_data(){
        // 实例化exel对象
        //文件路径
        $files = $_FILES['excel'];
        $upload = new \Think\Upload();// 实例化上传类
	    $upload->maxSize   =     3145728 ;// 设置附件上传大小
	    $upload->exts      =     array('xlsx', 'xls');// 设置附件上传类型
	    $upload->rootPath  =     './'; // 设置附件上传根目录
        $upload->savePath  =     'Public/Uploads/temp/'; // 设置附件上传（子）目录
        $upload->subName   =     array('date','Ymd');
	    $info   =   $upload->uploadOne($files);
	    if(!$info) {
            // 上传错误提示错误信息
            return Response::show(300,$upload->getError()); 
		}else{
			// 上传成功 获取上传文件信息
            $infopath = $upload->rootPath.$info['savepath'].$info['savename'];
            $data['contract_path'] = $infopath;
            $data['contract_name'] = $files['name'];
            return Response::show(200,$data);
		}

    }
    public function fraction_get_data($file_path = 'null'){
        import("Vendor.PHPExcel.PHPExcel");
        import("Vendor.PHPExcel.PHPExcel.Writer.Excel5");
        import("Vendor.PHPExcel.PHPExcel.IOFactory.php");
        $cacheMethod = \PHPExcel_CachedObjectStorageFactory:: cache_to_phpTemp;
        $cacheSettings = array( 'memoryCacheSize ' => '8MB');
        \PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
        // 实例化exel对象

        //文件的扩展名
        $ext = strtolower(pathinfo($file_path,PATHINFO_EXTENSION));
        if ($ext == 'xlsx'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objPHPExcel = $objReader->load($file_path);
        }elseif($ext == 'xls'){
            $objReader = \PHPExcel_IOFactory::createReader('Excel5');
            $objPHPExcel = $objReader->load($file_path);
        }
        $objReader->setReadDataOnly(true);
        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();//获取总行数
        $highestColumn = $sheet->getHighestColumn();//获取总列数
        $record = array();//申明每条记录数组
        $num = 0;
        $error = [];//记录错误数据
        for ($i = 1;$i<=$highestRow;$i++){
            $col = 0;
            for ($j = 'A';$j<=$highestColumn;$j++){
                $record[$num][$col] = str_replace(array("\r\n", "\r", "\n","\t","\r\t","\t\r"), "",$objPHPExcel->getActiveSheet()->getCell("$j$i")->getValue());//读取单元格
                $col++;
            }
            header("Content-type:text/html;charset=utf-8");
            $num++;
        }
        $result = [];
        $title = [];
        foreach($record as $key => $val){
            if($key != 0){
                $uid = M('exam_student')->field('uid')->where(['id_number'=>array('eq',$val[2])])->find();
                if(!empty($uid)){
                    $end = count($val);
                    $start = $end - ($end - 3);
                    for($i = $start;$i < $end;$i++){
                        $result[$uid['uid']][] = [
                            'subject' => $title[$i],
                            'fraction' => empty($val[$i])?0:$val[$i]
                        ];
                    }
                }
            }else{
                $title = $val;
            }
        }
        return $result;
    }
    public function student_export(){
        $name = I('name');
        $mobile = I('mobile');
        $start_time = I('start1');
        $end_time = I('end');

        if(!empty($name)){
            $where['a.name'] = array('like',"%$name%");
        }
        if(!empty($mobile)){
            $where['b.mobile'] = array('eq',$mobile);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['a.create_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['a.create_time'] = array('between',array($start_time,$end_time));
            }
        }
        $where['a.status'] = array('eq',1);
        $result = M('exam_student')
                    ->alias('a')
                    ->field('a.*,b.mobile')
                    ->join('left join dsy_exam_login as b on a.uid=b.id')
                    ->where($where)
                    ->order('a.id desc')
                    ->select();
        $data[] = ['姓名','手机号','身份证号','毕业时间','是否全日制','学历','学位','外语等级','报考岗位'];
        $num = 1;
        $examData = examData();
        foreach($result as $key=>$val){
            $arr = [
                'name' => $val['name'],
                'mobile' => $val['mobile'],
                'id_number' => $val['id_number'],
                'graduate_time' => $val['graduate_time'],
                'full_time' => $val['full_time'] == 1 ? '是' : '否',
                'education' => $examData['education'][$val['education']],
                'degree' => $examData['degree'][$val['degree']],
                'language_grade' => $examData['language_grade'][$val['language_grade']],
                'position' => $examData['position'][$val['position']],
            ];
            $data[$num++] = array_values($arr);
        }
        import('Org.Util.PHPExcel');
        $title = '考生信息';
        $excel = new \PHPExcel();
        //Excel表格式,这里简略写了8列
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        //第一个sheet
        $excel->setactivesheetindex(0);
        //设置sheet标题
        $excel->getActiveSheet()->setTitle($title);
        //填充表格信息
        $excel->getActiveSheet()->getColumnDimension('A')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('E')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('F')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('G')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('H')->setWidth(25);
        $excel->getActiveSheet()->getColumnDimension('I')->setWidth(25);
        $excel->getActiveSheet()->getStyle('A1:I1')->getFont()->setBold(true);
        for ($i = 1;$i <= count($data) + 1;$i++) {
        $j = 0;
        foreach ($data[$i-1] as $key=>$value) {
        
        if(!strpos($value,'Public/Uploads')){
            //文字生成
            $excel->getActiveSheet()->setCellValue("$letter[$j]$i","$value" . "\t");
        }else{
        // 图片生成
        $objDrawing[$key] = new \PHPExcel_Worksheet_Drawing();
        $objDrawing[$key]->setPath("." . $value);
        // 设置宽度高度
        $objDrawing[$key]->setHeight(140);//照片高度
        $objDrawing[$key]->setWidth(210); //照片宽度
        /*设置图片要插入的单元格*/
        $objDrawing[$key]->setCoordinates("$letter[$j]$i");
        // 图片偏移距离
        // $objDrawing[$key]->setOffsetX(50);
        // $objDrawing[$key]->setOffsetY(10);
        $objDrawing[$key]->setWorksheet($excel->getActiveSheet());
        }
        $j++;
        }
        }
        $excel->getActiveSheet()->getStyle('A1:I' . $key)->getAlignment()->setVertical(\PHPExcel_Style_Alignment::VERTICAL_CENTER);
        $title = $title . date('Y-m-d',time());
        $write = new \PHPExcel_Writer_Excel5($excel);
        ob_end_clean();
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");;
        header('Content-Disposition:attachment;filename="'.$title.'.xls"');
        header("Content-Transfer-Encoding:binary");
        $write->save('php://output');
    }
}