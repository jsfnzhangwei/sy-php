<?php
/**
 * Created by phpStorm
 * User: mj
 * Date: 2019/10/11
 * Time: 17:10
 */

namespace Admin\Controller;

use Think\Controller;
use Org\Util\Response;
use Common\Model\ErrorModel;
use Common\Model\ActivityAlertModel;
use Common\Model\ActivityModel;


class TalentController extends Controller
{
    /**
     * 人才库
     */
    public function poolPage()
    {
        $this->display();
    }

    /**
     * 人才信息导入
     */
    public function infoPage()
    {
        $this->display();
    }

    /**
     * 积分列表
     */
    public function scorePage()
    {
        $this->display();
    }

    /**
     * 积分配置
     */
    public function scoreEditPage()
    {
        $rid = (int)I('rid', 0);
        $this->assign('rid', $rid);
        $this->display();
    }

}