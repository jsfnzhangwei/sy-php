<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/04/25
 * Time: 09:45
 */

namespace Admin\Controller;

class ArticleController extends CommonController
{
    public function index()
    {
        $this->display();
    }

    public function edit()
    {
        $aid = I('aid', 0);
        $this->assign('aid', $aid);

        $this->display();
    }


    public function preview()
    {
        $title = I('articleTitle', '标题');
        $source = I('articleSource', '来源');
        $content = I('articleContent', '内容');
        $this->assign('title', $title);
        $this->assign('source', $source);
        $this->assign('content', htmlspecialchars_decode($content));
        $this->assign('now', date('Y-m-d'));

        echo $this->fetch();
        die();
    }
}