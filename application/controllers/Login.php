<?php
/**
 * Created by PhpStorm.
 * User: dongdong
 * Date: 16-3-9
 * Time: 下午7:27
 */
defined('BASEPATH') OR exit('No direct script access allowed');
class Login extends CI_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(array('form', 'url'));
    }

    //载入视图
    public function index()
    {
        $this->load->view('login.html');
    }

    //new captcha
    public function get_code()
    {
        $this->load->library('captcha');
        $code = strtolower($this->captcha->getCaptcha());
        $this->session->set_userdata('code', md5($code . 'new captcha'));
        $this->captcha->showImg();
    }

    /*  old function of captcha
    public function captcha()
    {
        $this->load->helper('captcha');
        //captcha cofig
        $speed = 'abcdefghijklmnopqrstuvwxyz1234567890';
        $word = '';
        for ($i = 0; $i < 4; $i++) {
            $word .= $speed[mt_rand(0, strlen($speed) - 1)];
        }
        $vals = array(
            'word'      => $word,
            'img_path'  => './captcha/',
            'img_url'   => 'http://localhost:5555/captcha',
            'font_path' => './path/to/fonts/texb.ttf',
            'img_width' => '50',
            'img_height'    => '30',
            'expiration'    => '180',
            'word_length'   => '100',
            'font_size' => '100',
            'img_id'    => 'Imageid',
            'pool'      => '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'colors'    => array(
                'background' => array(255, 255, 255),
                'border' => array(255, 255, 255),
                'text' => array(0, 0, 0),
                'grid' => array(255, 40, 40)
            )
        );

        $cap = create_captcha($vals);
        //验证码值写入SESSION，载入视图
        if(!isset($_SESSION)){session_start();}

        $_SESSION['dong'] = sha1($cap['word'].'dongdong_captcha');
        $index['captcha'] = $cap['image'];
        //echo $index['captcha'];
        return $index;
    }

    */

    //获取提交的信息
    public function add()
    {
        $this->load->model('Admin_model', 'admin');
        $this->load->library('email');
        //$error[] = array();

        //xss clean and verify whether the data is valid
        $captcha = $this->security->xss_clean($this->input->post('captcha'));
        $captcha = strtolower($captcha);
        if (md5($captcha . 'new captcha') != $_SESSION['code']) {
            echo 1; //验证码错误
        }

        $name = $this->security->xss_clean($this->input->post('name'));
        $email = $this->security->xss_clean($this->input->post('email'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo 5; //邮箱不符合格式
        }

        $sex = $this->security->xss_clean($this->input->post('sex'));
        $phone = $this->security->xss_clean($this->input->post('phone'));
        $grade = $this->security->xss_clean($this->input->post('grade'));
        $school = $this->security->xss_clean($this->input->post('school'));
        $company = $this->security->xss_clean($this->input->post('company'));
        $major = $this->security->xss_clean($this->input->post('major'));
        $location = $this->security->xss_clean($this->input->post('location'));
        $words = $this->security->xss_clean($this->input->post('words'));
        //set time zone and format time
        date_default_timezone_set('Asia/Shanghai');
        $reg_time = date('F j Y h:i:s A');

        //make a token
        $token = sha1(session_id() . $name);

        //upload

        $config['upload_path'] = './uploads/';
        $config['allowed_types'] = 'bmp|jpg|png';
        $config['encrypt_name'] = TRUE;
        $config['max_size'] = 0;
        $config['max_width'] = 0;
        $config['max_height'] = 0;

        $this->load->library('upload', $config);
        if (!$this->upload->do_upload('image')) {
            echo 2; //图片上传失败
        } else {
            $imgname = '/uploads/' . $this->upload->data('file_name');
        }


        //todo:图片过滤


        $data = array(
            'name' => $name,
            'sex' => $sex,
            'email' => $email,
            'phone' => $phone,
            'grade' => $grade,
            'school' => $school,
            'company' => $company,
            'major' => $major,
            'location' => $location,
            'words' => $words,
            'regtime' => $reg_time,
            'token' => $token,
            'imgname' => $imgname
        );

        //检查是否重复注册
        $error = $this->admin->check_new($data);
        if ($error <> 0)
            echo $error;
        else {
            //insert into database
            $this->admin->add($data);

            //send the email
            $emailbody = "亲爱的" . $data['username'] . "：<br/>感谢您在我站注册了新帐号。<br/>请点击链接激活您的帐号。<br/>
        <a href='http://localhost/login/check?token='" . $data['token'] . " target=
    '_blank'>http://localhost/login/check?token=" . $data['token'] . "</a><br/>
        如果以上链接无法点击，请将它复制到你的浏览器地址栏中进入访问，该链接24小时内有效。";


            $this->email->from('wangdong95@126.com', 'star');
            $this->email->to('tzzmain@qq.com');
            $this->email->subject('邮箱激活通知');
            $this->email->message($emailbody);
            $error = $this->email->send(); //成功返回true

            if (!$error)
                echo '邮件发送失败';
            else
                echo '发送成功！';
        }
    }

    public function check()
    {
        $token = $_GET['token'];
        $this->load->model('Admin_model', 'admin');
        $a = $this->admin->check($token);
        echo "status:" . $a[0]["status"];
        echo '<br />';

        //对比session值是否一致 && 是否已经认证
        if ($a[0]["status"] == 1) {
            echo '已认证';
        } else {
            if ($token == $a[0]['token']) {
                $uid = $a[0]['uid'];
                if ($this->admin->change($uid)) {
                    echo 'success';
                } else {
                    echo 'lose';
                }

            } else
                echo '验证失败，请联系管理员';
        }
    }

/*
    public function user_add()
    {
        //接受表单数据
        $data = array(
            'name' => $this->input->post('name'),
            'email' => $this->input->post('email'),
            'phpnenumber' => $this->input->post('phonenumber'),
            'college' => $this->input->post('college'),
            'major' => $this->input->post('major'),
            'work' => $this->input->post('work'),
        );
//        var_dump($data);


        //图片上传处理,之前应该验证一下是否有图片,前端会传一个值，我们判断值的属性，为1则执行上传，为0则不执行，存入数据库，代表有无图片


        $config['upload_path'] = './uploads/';
        $config['allowed_types'] = 'gif|jpg|png';
        $config['max_size'] = 10000;
        $config['max_width'] = 1920;
        $config['max_height'] = 1080;

        $this->load->library('upload', $config);
        $this->upload->do_upload('picture');
        $data1 = array('upload_data' => $this->upload->data());
        var_dump($data);
        var_dump($data1);


    }*/



}