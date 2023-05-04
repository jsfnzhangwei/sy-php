<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/10/9 0009
 * Time: 14:28
 */
namespace Admin\Controller;
use Org\Util\Token;
use Think\Controller_No_check;

class LoginController extends Controller_No_check {
    public function index(){
        $token = $_COOKIE['token'];
        if (!empty($token)) {
            header('Location: /dashengyun/admin.php');
            die;
        }
        $this->display('login/index');
    }
    public function index2(){
        $token = $_COOKIE['token'];
        if (!empty($token)) {
            header('Location: /dashengyun/admin.php');
            die;
        }
        $this->display('login/index2');
    }

    public function user_login(){
        $ip = get_client_ip();
        $ip_arr = C('sy_ip');
        if(!in_array($ip,$ip_arr)){
            echo 4;die();
        }
        $token = $_COOKIE['token'];
        if (!empty($token)) {
            echo 3; //提示已经登入其他账户
            die;
        }
        if(!empty($_REQUEST['username']) && !empty($_REQUEST['pwd'] ) && !empty($_REQUEST['code'])){
            $username = $_REQUEST['username'];
            $pwd = $_REQUEST['pwd'];
            $code = $_REQUEST['code'];
            $md5pwd = md5($pwd);
        }else return false;
        $Admin = M('admin');
        $Admin->startTrans();
        $where['username'] =array( 'eq',$username);
        $where['pwd'] =array( 'eq',$md5pwd);
        $result = $Admin ->where($where)->select();

        if(!empty($result)) {
            $session_code  = session($result[0]['email']);
            //$session_code  = 999999;
            if($code != $session_code){
                echo 5;
                die();
            }
            $id = $result[0]['id'];
            $token = Token::makeToken();
            $data = array(
                'token' => $token,
                'expired_time' => time(),
            );
            $where_qx['id'] = array('eq',$id);
            $r = M('admin')->where($where_qx)->save($data);
            if($r !== false){
                $Admin->commit();
                $expireTime = time() + 24 * 60;
                setcookie('token', $token, $expireTime, "/");
                setcookie('username', $username, $expireTime, "/");
                session($result[0]['email'],null);
                echo 1; //登陆成功
            } else {
                $Admin->rollback();
                echo 2;
            }

        }else echo 2;//用户名或密码错误
    }
    public function email(){
        $username = I('username');
        $Admin = M('admin');
        $where['username'] =array( 'eq',$username);
        $result = $Admin ->where($where)->select();
        if(empty($result)){
            echo json_encode(['code'=>300, 'msg'=>'用户不存在']);
            die();
        }
        if(empty($result[0]['email'])){
            echo json_encode(['code'=>300, 'msg'=>'未绑定邮箱,请联系管理员绑定邮箱']);
            die();
        }
        $email= $result[0]['email'];//获取收件人邮箱
                 //return $email;
        $sendmail = 'hanbo159357@126.com'; //发件人邮箱
        $sendmailpswd = "XTWIILULNKUABMYO"; //客户端授权密码,而不是邮箱的登录密码，就是手机发送短信之后弹出来的一长串的密码
        $send_name = '苏鹰集团';// 设置发件人信息，如邮件格式说明中的发件人，
        $toemail = $email;//定义收件人的邮箱
        $to_name = 'sy';//设置收件人信息，如邮件格式说明中的收件人
        import('PHPMailer.PHPMailer');
        $mail = new \PHPMailer();
        $mail->isSMTP();// 使用SMTP服务
        $mail->CharSet = "utf8";// 编码格式为utf8，不设置编码的话，中文会出现乱码
        $mail->Host = "smtp.126.com";// 发送方的SMTP服务器地址
        $mail->SMTPAuth = true;// 是否使用身份验证
        $mail->Username = $sendmail;//// 发送方的
        $mail->Password = $sendmailpswd;//客户端授权密码,而不是邮箱的登录密码！
        $mail->SMTPSecure = "ssl";// 使用ssl协议方式
        $mail->Port = 465;//  qq端口465或587）
        $mail->setFrom($sendmail, $send_name);// 设置发件人信息，如邮件格式说明中的发件人，
        $mail->addAddress($toemail, $to_name);// 设置收件人信息，如邮件格式说明中的收件人，
        $mail->addReplyTo($sendmail, $send_name);// 设置回复人信息，指的是收件人收到邮件后，如果要回复，回复邮件将发送到的邮箱地址
        $mail->Subject = "登录验证";// 邮件标题

        $code=rand(100000,999999);
        session($email,$code);
        //return $code."----".session("code");
        $mail->Body = "你好，本次登录验证码是：" . $code ."，仅供个人登录大管理后台使用，请不要向外透露  此邮件由系统发出，请勿直接回复。";// 邮件正文
        //$mail->AltBody = "This is the plain text纯文本";// 这个是设置纯文本方式显示的正文内容，如果不支持Html方式，就会用到这个，基本无用
        if (!$mail->send()) { // 发送邮件
            echo json_encode(['code'=>300, 'msg'=>'发送失败']);
        } else {
            echo json_encode(['code'=>200, 'msg'=>'发送成功']);
        }
    }

    public function user_logout()
    {
        clearCookie();
        $this->success('退出成功','index2');
    }

    /**
     * 菜单栏
     **/
    public function Menu()
    {
        $username = $_COOKIE['username'];

        $adminInfo = M('admin')->where(['username' => $username])->find();
        $type = $adminInfo['type'];
        $menu_ = json_decode($adminInfo['menu'], true);

        if (empty($type)) {
            echo json_encode([]);
            exit;
        }

        $menu = array();
        $model_menu = M('menu');
        $where_menu['status'] = array('eq',1);
        $order = 'shunxu asc';
        $result_menu = $model_menu->where($where_menu)->order($order)->select();
        if ($type == 99) {
            foreach ($result_menu as $k=>$v){
                $menu[$k]['id'] = $v['data_id'];
                $menu[$k]['name'] = $v['name'];
                $result_menu_navagation_first = selNavigationFirst($v['data_id']);
                foreach ($result_menu_navagation_first as $kk=>$vv) {
                    $menu[$k]['menu'][$kk]['text'] = $vv['name'];
                    $result_menu_navagation_second = selNavigationSecond($vv['data_id']);
                    foreach ($result_menu_navagation_second as $kkk=>$vvv) {
                        $menu[$k]['menu'][$kk]['items'][$kkk] = array('id'=>$vvv['data_id'],'text'=>$vvv['name'],'href'=>$vvv['url']);
                    }
                }
                $menu[$k]['homePage'] = $menu[$k]['menu'][0]['items'][0]['id'];

            }
            /*
            $menu[] = array(
                'id' =>'system',
                'homePage'=>'navigation',
                'menu'=>array(
                    array(
                        'text'=>'系统配置',
                        'items'=>array(
                            array('id'=>'navigation','text'=>'系统菜单','href'=>'/'.PROJECT_NAME.'/admin.php/System/navigation','closeable' => false)
                        )
                    )
                )

            );
            */
        }else{
            foreach ($result_menu as $k=>$v){
                if (in_array($v['data_id'],$menu_)) {
                    $menu[$k]['id'] = $v['data_id'];
                    $menu[$k]['name'] = $v['name'];
                    $result_menu_navagation_first = selNavigationFirst($v['data_id']);
                    foreach ($result_menu_navagation_first as $kk=>$vv) {
                        if (in_array($vv['data_id'],$menu_)) {
                            $menu[$k]['menu'][$kk]['text'] = $vv['name'];
                            $result_menu_navagation_second = selNavigationSecond($vv['data_id']);
                            foreach ($result_menu_navagation_second as $kkk=>$vvv) {
                                if (in_array($vvv['data_id'],$menu_)) {
                                    $menu[$k]['menu'][$kk]['items'][$kkk] = array('id'=>$vvv['data_id'],'text'=>$vvv['name'],'href'=>$vvv['url']);
                                }
                            }
                        }
                    }
                    $menu[$k]['homePage'] = $menu[$k]['menu'][0]['items'][0]['id'];
                    $menu = array_values($menu);
                }
            }
        }
        echo json_encode($menu);
    }

    /**
     * 验证登录状态
     */
    public function checkLogin()
    {
        if (empty($_COOKIE['token']) || empty($_COOKIE['username'])) {
            echo json_encode(['code' => 0, 'msg' => '登陆超时，请重新登录！']);
            die;
        }
        $token = $_COOKIE['token'];
        $username = $_COOKIE['username'];
        $is_logout = M('admin')->where(['token' => $token])->count();
        if ($is_logout == 0) {
            echo json_encode(['code' => 0, 'msg' => '该账户在其他设备登录，您已被迫下线！']);
            die;
        }
        $expireTime = time() + 24 * 60;
        setcookie('token', $token, $expireTime, "/");
        setcookie('username', $username, $expireTime, "/");
        echo json_encode(['code' => 1, 'msg' => '登陆中']);
        die;
    }
}
