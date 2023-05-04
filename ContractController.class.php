<?php

namespace Admin\Controller;

use Think\Controller;
use Org\Util\Response;


class ContractController extends Controller
{
    public function signList()
    {
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = (int)$_REQUEST['limit'];

        $keyword = I('keyword', '');

        $where = [
            'c.`corporate_id`' => ['exp', 'IS NOT NULL']
        ];
        if (!empty($keyword)) {
            $where['c.corporate_name'] = ['like', '%' . $keyword . '%'];
        }

        $model = M('contract_convert_deptname');

        $count = $model
            ->alias('ccd')
            ->join('LEFT JOIN `t_corporate` c ON c.`corporate_id`=ccd.`cid`')
            ->where($where)
            ->count();
        $list = [];
        if ($count > 0) {
            $list = $model
                ->alias('ccd')
                ->join('LEFT JOIN `t_corporate` c ON c.`corporate_id`=ccd.`cid`')
                ->join('LEFT JOIN `dsy_corporate_signature` cs ON cs.`signature_id`=c.`signature_use`')
                ->where($where)
                ->page($page, $limit)
                ->order('ccd.`cid` ASC')
                ->field('c.`corporate_id`,c.`corporate_name`,cs.`signature_url` sign_url,ccd.`name` d_name')
                ->select();
            foreach ($list as $k => $v) {
                $list[$k]['sign_url'] = format_img($v['sign_url'], IMG_VIEW);
            }
        }

        return Response::mjson($list, $count);
    }

    public function comList()
    {
        $where = [
            'c.`signature_use`' => ['exp', 'IS NOT NULL']
            , 'ccd.`cid`' => ['exp', 'IS NULL']
        ];
        $list = M('corporate', 't_')
            ->alias('c')
            ->join('LEFT JOIN `dsy_corporate_signature` cs ON cs.`signature_id`=c.`signature_use`')
            ->join('LEFT JOIN `dsy_contract_convert_deptname` ccd ON ccd.`cid`=c.`corporate_id`')
            ->where($where)
            ->order('c.`corporate_id` ASC')
            ->field('c.`corporate_id`,c.`corporate_name`,cs.`signature_url` sign_url')
            ->select();
        foreach ($list as $k => $v) {
            $list[$k]['sign_url'] = format_img($v['sign_url'], IMG_VIEW);
        }
        if (empty($list)) {
            $list = [];
        }

        return Response::json(200, "", $list);
    }

    public function signInfo()
    {
        $rid = (int)I('rid', 0);

        $where = [
            'c.`corporate_id`' => $rid
        ];
        $info = M('contract_convert_deptname')
            ->alias('ccd')
            ->join('LEFT JOIN `t_corporate` c ON c.`corporate_id`=ccd.`cid`')
            ->join('LEFT JOIN `dsy_corporate_signature` cs ON cs.`signature_id`=c.`signature_use`')
            ->where($where)
            ->order('ccd.`cid` ASC')
            ->field('c.`corporate_id`,c.`corporate_name`,cs.`signature_url` sign_url,ccd.`name` d_name')
            ->find();
        if (empty($info)) {
            $info = [];
        } else {
            $info['sign_url'] = format_img($info['sign_url'], IMG_VIEW);
        }
        return Response::json(200, "", $info);
    }

    public function updateSign()
    {
        $rid = (int)$_REQUEST['rid'];
        $cid = (int)I('comId', 0);
        $d_name = I('d_name', '');

        $data = [
            'name' => $d_name,
        ];
        $model = M('contract_convert_deptname');
        if ($rid > 0) {
            $res = $model->where(['cid' => $rid])->save($data);
        } else {
            $data['cid'] = $cid;
            $res = $model->add($data);
        }
        if ($res == false) {
            return Response::show(300, '操作失败');
        }
        return Response::show(200, '操作成功');
    }

    public function signComList()
    {
        $rid = (int)$_REQUEST['rid'];
        $pageIndex = (int)$_REQUEST['pageIndex'];
        $page = $pageIndex + 1;
        $limit = (int)$_REQUEST['limit'];

        $keyword = I('keyword', '');

        $where = [
            'ccs.`status`' => 0
            , 'ccs.`convert_cid`' => $rid
            , 'c.`corporate_id`' => ['exp', 'IS NOT NULL']
        ];
        if (!empty($keyword)) {
            $where['c.corporate_name'] = ['like', '%' . $keyword . '%'];
        }

        $model = M('contract_convert_signature');
        $count = $model
            ->alias('ccs')
            ->join('LEFT JOIN `t_corporate` c ON c.`corporate_id`=ccs.`cid`')
            ->where($where)
            ->count();
        $list = [];
        if ($count > 0) {
            $list = $model
                ->alias('ccs')
                ->join('LEFT JOIN `t_corporate` c ON c.`corporate_id`=ccs.`cid`')
                ->where($where)
                ->page($page, $limit)
                ->order('ccs.`id` DESC')
                ->field('ccs.`id`,c.`corporate_id`,c.`corporate_name`')
                ->select();
        }

        return Response::mjson($list, $count);
    }

    public function updateSignCom()
    {
        $rid = (int)$_REQUEST['rid'];
        $cid = (int)I('comId', 0);

        $data = [
            'cid' => $cid,
            'convert_cid' => $rid,
        ];
        $res = M('contract_convert_signature')->add($data);
        if ($res == false) {
            return Response::show(300, '操作失败');
        }
        return Response::show(200, '操作成功');
    }

    public function delSignCom()
    {
        $rid = (int)$_REQUEST['rids'];

        $res = M('contract_convert_signature')->where(['id' => $rid])->setField('status', 1);
        if ($res == false) {
            return Response::show(300, '操作失败');
        }
        return Response::show(200, '操作成功');
    }


    public function getAllComList()
    {
        $rid = (int)$_REQUEST['rid'];
        $keyword = I('keyword', '');

        $where = [];
        $where['corporate_id'][] = ['neq', $rid];
        $where['corporate_id'][] = ['exp', 'NOT IN (SELECT `cid` FROM `dsy_contract_convert_signature` WHERE `convert_cid`="' . $rid . '" AND `status`=0)'];
        if (!empty($keyword)) {
            $where['corporate_name'] = ['like', '%' . $keyword . '%'];
        }

        $list = M('corporate', 't_')
            ->where($where)
            ->order('`corporate_id` ASC')
            ->field('`corporate_id`,`corporate_name`')
            ->select();
        if (empty($list)) {
            $list = [];
        }

        return Response::json(200, "", $list);
    }
}