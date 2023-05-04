<?php
/**
 * Created by PhpStorm.
 * User: 苏鹰-马洁
 * Date: 2018/12/28
 * Time: 10:55
 */

namespace Admin\Controller;

use Org\Util\Response;

class HolidayController extends CommonController
{
    public function holidayPage()
    {
        $this->display();
    }

    public function holidayEditPage()
    {
        $rid = (int)I('rid', 0);
        $this->assign('rid', $rid);
        $this->display();
    }

    public function holidayList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $year = I('year', '');

        $where = [];
        if (!empty($year)) {
            $where['year'] = $year;
        }

        $model = M('holiday');
        $count = $model->where($where)->count();
        if ($count > 0) {
            $list = $model->where($where)->page($page, $limit)->order('`id` DESC')->select();
        } else {
            $list = [];
        }

        return Response::mjson($list, $count);
    }

    public function holidayInfo()
    {
        $rid = (int)I('rid', 0);
        $info = M('holiday')->where(['id' => $rid])->field('name,date,type')->find();
        if (empty($info))
            $info = [];
        return Response::json(200, '', $info);
    }

    public function holidayEdit()
    {
        $rid = (int)I('rid', 0);
        $name = I('name', '');
        $date = I('date', '');
        $type = I('type', 1);

        $model = M('holiday');

        $hasWhere = [
            'date' => $date
        ];
        if ($rid > 0) {
            $hasWhere['id'] = ['neq', $rid];
        }

        $hasCount = $model->where($hasWhere)->field('id')->count();
        if ($hasCount > 0) {
            return Response::show(300, '该日期已关联其他节假日');
        }
        $data = [
            'name' => $name,
            'date' => $date,
            'year' => date('Y', strtotime($date)),
            'type' => $type,
        ];
        if ($rid > 0) {
            $res = $model->where(['id' => $rid])->save($data);
        } else {
            $res = $model->add($data);
        }
        if ($res === false) {
            return Response::show(300, '操作失败');
        }
        return Response::show(200, '操作成功');
    }

    public function holidayDel()
    {
        $rids = I('rids', 0);
        $res = M('holiday')->where(['id' => ['in', $rids]])->delete();
        if ($res === false) {
            return Response::show(300, '操作失败');
        }
        return Response::show(200, '操作成功');
    }

    public function holidaySupplyPage()
    {
        $this->display();
    }

    public function holidaySupplyEditPage()
    {
        $rid = (int)I('rid', 0);
        $this->assign('rid', $rid);
        $this->display();
    }

    public function holidaySupplyList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = 10;
        $year = I('year', '');

        $where = [];
        if (!empty($year)) {
            $where['year'] = $year;
        }

        $model = M('holiday_supply');
        $count = $model->where($where)->count();
        if ($count > 0) {
            $list = $model->where($where)->page($page, $limit)->order('`id` DESC')->select();
        } else {
            $list = [];
        }

        return Response::mjson($list, $count);
    }

    public function holidaySupplyInfo()
    {
        $rid = (int)I('rid', 0);
        $info = M('holiday_supply')->where(['id' => $rid])->field('holiday as name,date,week')->find();
        if (empty($info))
            $info = [];
        return Response::json(200, '', $info);
    }

    public function holidaySupplyEdit()
    {
        $rid = (int)I('rid', 0);
        $name = I('name', '');
        $date = I('date', '');
        $week = I('week', '');

        $model = M('holiday_supply');

        $hasWhere = [
            'date' => $date
        ];
        if ($rid > 0) {
            $hasWhere['id'] = ['neq', $rid];
        }

        $hasCount = $model->where($hasWhere)->field('id')->count();
        if ($hasCount > 0) {
            return Response::show(300, '该日期已关联其他节假补班日');
        }
        $data = [
            'name' => $name . '补班',
            'date' => $date,
            'year' => date('Y', strtotime($date)),
            'holiday' => $name,
            'week' => $week,
        ];
        if ($rid > 0) {
            $res = $model->where(['id' => $rid])->save($data);
        } else {
            $res = $model->add($data);
        }
        if ($res === false) {
            return Response::show(300, '操作失败');
        }
        return Response::show(200, '操作成功');
    }

    public function holidaySupplyDel()
    {
        $rids = I('rids', 0);
        $res = M('holiday_supply')->where(['id' => ['in', $rids]])->delete();
        if ($res === false) {
            return Response::show(300, '操作失败');
        }
        return Response::show(200, '操作成功');
    }

    public function holidayYear()
    {
        $years = M('holiday')->group('year')->order('`year` ASC')->getField('year', true);
        if (empty($years))
            $years = [];
        return Response::json(200, '', $years);
    }

    public function holiday()
    {
        $holiday = [
            '元旦',
            '春节',
            '清明',
            '劳动节',
            '端午节',
            '中秋节',
            '国庆节'
        ];
        return Response::json(200, '', $holiday);
    }
}