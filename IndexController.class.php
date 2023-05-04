<?php
namespace Admin\Controller;
use Think\Controller_Sign;
class IndexController extends Controller_Sign {
    public function index(){
        $token = $_COOKIE['token'];
        if (empty($token)) {
            clearCookie();
            header('Location: ' . U('/Login/index2'));
            die;
        } else {
            $is_logout = M('admin')->where(['token' => $token])->count();
            if ($is_logout == 0) {
                clearCookie();
                header('Location: ' . U('/Login/index2'));
                die;
            }
        }
        $username = $_COOKIE['username'];
        $this->assign('username',$username)->display('/index');
    }

    //登陆成功跳转
    public function index_islogin(){
        $this->success('登陆成功！','store');
    }
    //主界面
    public function store()
    {

        if(isset($_SESSION['admin']) && isset($_SESSION['type'])) {
            if ($_SESSION['type'] == 1)
                $this->display('/index');
            else if($_SESSION['type'] == 2)
                $this->display('/index_2');
        }


    }




}