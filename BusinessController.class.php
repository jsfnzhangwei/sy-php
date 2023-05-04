<?php
/**
 * Created by phpStorm
 * User: mj
 * Date: 2019/08/16
 * Time: 16:10
 */

namespace Admin\Controller;

use Think\Controller;
use Org\Util\Response;
use Common\Model\CorporateBusinessModel;


class BusinessController extends Controller
{
    /**
     * 活动列表页
     */
    public function businessPage()
    {
        $this->display();
    }

    /**
     * 活动列表
     */
    public function getBusinessList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $title = I('title', '');
        $status = I('status', -1);
        $date = I('date', -1);
        $nowDate = date('Y-m-d');

        $where = [];
        if (!empty($title)) {
            $where['title'] = ['like', '%' . $title . '%'];
        }
        if (in_array($status, [0, 1])) {
            $where['status'] = $status;
        }
        switch ($date) {
            case 0://未开始
                $where['start_date'] = ['gt', $nowDate];
                break;
            case 1://进行中
                $where['start_date'] = ['elt', $nowDate];
                $where['end_date'] = ['egt', $nowDate];
                break;
            case 2://已结束
                $where['end_date'] = ['lt', $nowDate];
                break;
        }

        $businessModel = new CorporateBusinessModel();
        $list = $businessModel->getBusinessList($where, $page, $limit, '', '');
        $count = $businessModel->getBusinessCount($where);

        return Response::mjson($list, $count);
    }

    /**
     * 启用/禁用活动
     */
    public function dealBusiness()
    {
        $rids = I('rids', 0);
        $status = I('status', 0);

        $status = ($status == 1) ? 1 : 0;

        if (empty($rids)) {
            ErrorModel::paramLose();
        }

        $businessModel = new CorporateBusinessModel();
        $res = $businessModel->updateBusinessStatus($rids, $status);

        return Response::show($res['code'], $res['msg']);
    }

    /**
     * 查看浏览量
     */
    public function quotaPage()
    {
        $rid = (int)I('rid', 0);
        $this->assign('rid', $rid);
        $this->display();
    }

    /**
     * 浏览量列表
     */
    public function getQuotaList()
    {
        $rid = (int)I('rid', 0);
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $startDate = I('startDate', '');
        $endDate = I('endDate', '');

        $where = [];
        if ($rid > 0) {
            $where['cid'] = $rid;
        }
        if (!empty($startDate)) {
            $where['date'][] = ['egt', $startDate];
        }
        if (!empty($endDate)) {
            $where['date'][] = ['elt', $endDate];
        }

        $businessModel = new CorporateBusinessModel();
        $list = $businessModel->getQuotaExchangeList($where, $page, $limit);
        $count = $businessModel->getQuotaExchangeCount($where);

        $userModel = M('user', 't_');
        $corporateModel = M('corporate', 't_');
        foreach ($list as $k => $v) {
            $list[$k]['user_name'] = $userModel->where(['user_id' => $v['user_id']])->getField('user_name');
            $list[$k]['corporate_name'] = $corporateModel->where(['corporate_id' => $v['cid']])->getField('corporate_name');
        }

        //兑换的福利豆统计
        $balanceNum = $businessModel->getQuotaExchangeNum($where);
        $extraData['moneyNum'] = empty($balanceNum['money_num']) ? 0 : $balanceNum['money_num'];
        $extraData['quotaNum'] = empty($balanceNum['quota_num']) ? 0 : $balanceNum['quota_num'];

        return Response::mjson($list, $count, $extraData);
    }

    /**
     * 视频兑换卷
     */
    public function video_vip_index(){
        $this->display();
    }

    /** 
     * 视频兑换卷活动列表
    */
    public function video_vip_activity(){
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $name = I('name');

        if(!empty($name)){
            $where['name'] = array('like',"%$name%");
        }

        $result = M('mall_video_activity')
                    ->field('id,name,start_time,end_time,num')
                    ->where($where)
                    ->page($page,$limit)
                    ->select();
        $num = M('mall_video_activity','dsy_')
                    ->where($where)
                    ->count();
        foreach($result as $key => $val){
            $result[$key]['finish'] = M('mall_video_detail')->where(['pid'=>$val['id'],'state'=>1])->count();
            $result[$key]['undone'] = M('mall_video_detail')->where(['pid'=>$val['id'],'state'=>0])->count();
        }

        return Response::mjson($result,$num);
    }
    public function add_video_activity_index(){
        $this->display();
    }

    public function add_video_activity(){
        $name = I('name');
        $num = I('num');
        $start = I('start');
        $end = I('end');

        if(empty($name)){
            return Response::show(300,'添加活动失败');
        }
        if(empty($num)){
            return Response::show(300,'活动券数量不能为空');
        }
        if($num > 3000){
            return Response::show(300,'兑换券数量最大不能超过3000张');
        }
        if(empty($start) || empty($end)){
            return Response::show(300,'起始时间不能为空');
        }
        $data = [
            'name' => $name,
            'num'  => $num,
            'start_time' => $start,
            'end_time'   => $end,
            'create_time'=> date('Y-m-d H:i:s',time()),
            'status' => 1
        ];
        $model = M('mall_video_activity');
        $model ->startTrans();
        $result = $model->add($data);
        if($result){
            $detail = [];
            for($i = 0;$i < $num;$i++){
                $detail[] = [
                    'pid'=>$result,
                    'video_num' => mt_rand(999999999, 9999999999),
                    'status' => 1,
                    'state' => 0,
                    'start_time' => $start,
                    'end_time'   => $end,
                ];
            }
            $addDetail = M('mall_video_detail')->addAll($detail);
            if($result != false && $addDetail != false){
                $model ->commit();
                return Response::show(200,'添加活动成功');
            }else{
                $model ->rollback();
                return Response::show(300,'添加活动失败');
            }
        }else{
            $model ->rollback();
            return Response::show(300,'添加活动失败');
        }
    }
    public function video_detail_index(){
        $id = I('id');
        $this->assign('id',$id);
        $this->display();
    }
    public function video_vip_detail(){
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $id = I('id');
        $num = I('num');
        $start_time = I('stime');
        $end_time = I('etime');
        $state = I('state');

        $where['pid'] = array('eq',$id);
        if(!empty($num)){
            $where['video_num'] = array('eq',$num);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['update_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['update_time'] = array('between',array($start_time,$end_time));
            }
        }
        if($state !== ''){
            $where['state'] = array('eq',$state);
        }
        $result = M('mall_video_detail')
                    ->where($where)
                    ->page($page,$limit)
                    ->select();
        $num = M('mall_video_detail')
                    ->where($where)
                    ->count();
        $videoClass = [1=>'爱奇艺年费',2=>'优酷年费',3=>'腾讯视频年费',4=>'芒果年费',5=>'哔哩哔哩年费'];
        foreach($result as $key=>$val){
            $result[$key]['state'] = $val['state'] == 1 ? '已兑换' : '未兑换';
            $result[$key]['video_class'] = $videoClass[$val['video_class']];
        }

        return Response::mjson($result,$num);
    }

    public function video_detail_excel(){
        $id = I('id');
        $num = I('num');
        $start_time = I('stime');
        $end_time = I('etime');
        $state = I('state');

        $where['pid'] = array('eq',$id);
        if(!empty($num)){
            $where['video_num'] = array('eq',$num);
        }
        if(!empty($start_time)&&!empty($end_time)){
            if($start_time==$end_time){
                $where['update_time'] = array('like',"%$start_time%");
            }else{
                $start_time = $start_time.' 00:00:00';
                $end_time = $end_time.' 23:59:59';
                $where['update_time'] = array('between',array($start_time,$end_time));
            }
        }
        if($state !== ''){
            $where['state'] = array('eq',$state);
        }
        $title = M('mall_video_activity')->where(['id'=>$id])->getField('name');
        $title = $title . '兑换明细';
        $result = M('mall_video_detail')
                    ->where($where)
                    ->select();
        $data[] = ['兑换券号','兑换状态','兑换手机号','兑换商品','兑换时间'];
        $videoClass = [1=>'爱奇艺年费',2=>'优酷年费',3=>'腾讯视频年费',4=>'芒果年费',5=>'哔哩哔哩年费'];
        foreach($result as $key=>$val){
            $arr = [
                    'video_num' => $val['video_num'],
                    'state' => $val['state'] == 1 ? '已兑换' : '未兑换',
                    'phone' => $val['phone'],
                    'video_class' => $videoClass[$val['video_class']],
                    'update_time' => $val['update_time']
                ];
            $data[] = array_values($arr);
        }
        $this->get_excel($data,$title);
    }
    /**
     * 数据导出excel
     */
    public function get_excel($data,$title){
        import('Org.Util.PHPExcel');
        $excel = new \PHPExcel();
        //Excel表格式,这里简略写了8列
        $letter = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
        //第一个sheet
        $excel->setactivesheetindex(0);
        //设置sheet标题
        $excel->getActiveSheet()->setTitle($title);
        //填充表格信息
        $excel->getActiveSheet()->setCellValue("A1",$title."统计报表");
        $excel->getActiveSheet()->getColumnDimension('A')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('B')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('C')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('D')->setWidth(15);
        $excel->getActiveSheet()->getColumnDimension('E')->setWidth(25);
        for ($i = 2;$i <= count($data) + 1;$i++) {
        $j = 0;
        foreach ($data[$i-2] as $key=>$value) {
        
        if(!strpos($value,'Public/Uploads')){
        //文字生成
        $excel->getActiveSheet()->setCellValue("$letter[$j]$i","$value");
        }else{
        // 图片生成
        $objDrawing[$key] = new \PHPExcel_Worksheet_Drawing();
        $objDrawing[$key]->setPath($value);
        // 设置宽度高度
        $objDrawing[$key]->setHeight(100);//照片高度
        //$objDrawing[$k]->setWidth(80); //照片宽度
        /*设置图片要插入的单元格*/
        $objDrawing[$key]->setCoordinates("$letter[$j]$i");
        // 图片偏移距离
        $objDrawing[$key]->setOffsetX(50);
        $objDrawing[$key]->setOffsetY(10);
        $objDrawing[$key]->setWorksheet($excel->getActiveSheet());
        }
        $j++;
        }
        }
        $title = $title . time();
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
    public function aaa(){
        $aaa =  mt_rand(999999999, 9999999999);
        echo $aaa;
    }
}