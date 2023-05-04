<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/6
 * Time: 19:45
 */

namespace Admin\Controller;

class MessageController extends CommonController
{
    /**
     * 信息列表
     */
    public function index()
    {
        $list = $this->lists(M('announcement', 't_'), "`groups`='' or `groups` is null");
        $this->assign('_list', $list);
        $this->meta_title = '消息列表';

        $this->display();
    }

    /**
     * 删除消息
     */
    public function del()
    {
        $id = array_unique((array)I('id', 0));
        $id = is_array($id) ? implode(',', $id) : $id;
        if (empty($id)) {
            $this->error('请选择要操作的数据!');
        }

        $model = M('announcement', 't_');

        $where = array();
        $where['id'] = array('in', $id);


        $data['status'] = -1;

        if ($model->where($where)->delete() !== false) {
            $this->success('操作成功！');
        } else {
            $this->error('操作失败！');
        }
    }

    /**
     * 添加消息
     */
    public function add()
    {
        if (IS_POST) {
            $model = M('announcement', 't_');
            $data = $model->create();
            if ($data) {
                $model->starttime = empty($model->starttime) ? 0 : strtotime(($model->starttime) . ' 00:00:00');
                $model->endtime = empty($model->endtime) ? 0 : strtotime(($model->endtime) . ' 23:59:59');
                $model->author = 1;//getAdminId();
                $model->create_time = NOW;

                if (!empty($model->starttime) && !empty($model->endtime) && $model->starttime >= $model->endtime) {
                    $this->error('开始时间不得超过结束时间!');
                }

                if (false !== $model->add()) {
                    $this->success('新增成功！', U('index'));
                } else {
                    $error = $model->getError();
                    $this->error(empty($error) ? '未知错误！' : $error);
                }
            } else {
                $this->error($model->getError());
            }
        } else {
            $this->display();
        }
    }

    /**
     * 编辑消息
     */
    public function edit()
    {
        $model = M('announcement', 't_');
        if (IS_POST) {
            $model->create();

            $id = $model->id;
            if (empty($id)) {
                $this->error('请选择要操作的数据!');
            }

            $model->starttime = empty($model->starttime) ? 0 : strtotime(($model->starttime) . ' 00:00:00');
            $model->endtime = empty($model->endtime) ? 0 : strtotime(($model->endtime) . ' 23:59:59');
            $model->author = 1;//getAdminId();
            $model->create_time = NOW;

            if (!empty($model->starttime) && !empty($model->endtime) && $model->starttime >= $model->endtime) {
                $this->error('开始时间不得超过结束时间!');
            }

            if (false !== $model->save()) {
                $this->success('编辑成功', U('index'));
            } else {
                $error = $model->getError();
                $this->error(empty($error) ? '未知错误!' : $error);
            }

        } else {
            $id = I('id');
            if (empty($id)) {
                $this->error('请选择要操作的数据!');
            }

            $info = $model->where(['id' => $id])->find();
            if (empty($info)) {
                $this->error('请选择要操作的数据!');
            }
            $this->assign('info', $info);

            $this->display();
        }
    }
}