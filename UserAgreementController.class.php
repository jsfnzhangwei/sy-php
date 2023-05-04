<?php

namespace Admin\Controller;

use Think\Controller;
use Org\Util\Response;


class UserAgreementController extends Controller
{

    /**
     * 弹框列表
     */
    public function getList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = (int)$_REQUEST['limit'];

        $keyword = I('keyword', '');
        $position = I('position', '');

        $where = [
            'status' => 1
        ];
        if (!empty($keyword)) {
            $where['title'] = ['like', '%' . $keyword . '%'];
        }
        switch ($position) {
            case 'info_show':
            case 'real_show':
                $where[$position] = 1;
                break;
            default:
                break;
        }

        $model = M('user_agreement', 't_');
        $count = $model->where($where)->count();
        $list = [];
        if ($count > 0) {
            $list = $model->where($where)->page($page, $limit)->order('`id` DESC')->select();
            foreach ($list as $k => $v) {
                $list [$k]['total_url'] = format_img($v['url'], IMG_VIEW);
            }
        }
        return Response::mjson($list, $count);
    }

    /**
     * 获得记录详情
     */
    public function getInfo()
    {
        $rid = I('rid', 0);
        $info = M('user_agreement', 't_')->where(['id' => ['in', $rid]])->find();
        if (empty($info)) {
            return Response::show(300, '找不到可编辑的记录');
        }
        return Response::json(200, '', $info);
    }

    /**
     * 新增/编辑弹框页
     */
    public function update()
    {
        $rid = I('rid', 0);
        $title = I('title', '');
        $info_show = I('info_show', '');
        $real_show = I('real_show', '');
        $url = I('url', '');
        $img_url = I('img_url', '');
        $content = I('content', '');
        $content = htmlspecialchars_decode($content);
        $info_show = $info_show == 1 ? 1 : 2;
        $real_show = $real_show == 1 ? 1 : 2;
        if (empty($title)) {
            return Response::show(300, '请填写标题');
        }
        if ($info_show == 2 && $real_show == 2) {
            return Response::show(300, '请至少选择一个展示位置');
        }
//        if (empty($url)) {
//            return Response::show(300, '请选择协议文件');
//        }
        if (empty($content)) {
            return Response::show(300, '请填写协议内容');
        }
        $param = [
            'title' => $title,
            'url' => $url,
            'img_url' => $img_url,
            'content' => $content,
            'info_show' => $info_show,
            'real_show' => $real_show,
        ];
        $model = M('user_agreement', 't_');
        if ($rid > 0) {
            $res = $model->where(['id' => $rid])->save($param);
        } else {
            $res = $model->add($param);
        }
        if ($res === false) {
            return Response::show(300, "操作失败");
        }
        return Response::show(200, "操作成功");
    }

    public function delete()
    {
        $rid = I('rid', 0);
        $res = M('user_agreement', 't_')->where(['id' => ['in', $rid]])->setField('status', 2);
        if ($res === false) {
            return Response::show(300, "操作失败");
        }
        return Response::show(200, "操作成功");
    }

}