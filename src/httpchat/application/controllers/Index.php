<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Index extends CI_Controller
{
    private $client_ip;
    private $client_ip_long;
    //private $host = "192.168.100.210";
    private $host = "127.0.0.1";
    private $port = 8888;

    public function __construct()
    {
        parent::__construct();
        $this->client_ip = $this->input->ip_address();
        $this->client_ip_long = bindec(decbin(ip2long($this->client_ip)));
    }

    public function index()
    {
        $data = array(
            'nav' => 'chat_online',
            'user' => $this->client_ip,
        );
        $this->load->view('index', $data);
    }

    public function join()
    {
        if($_SERVER['REQUEST_METHOD'] == "POST") {
            $data = array(
                'ip' => array('N',$this->client_ip_long),
                'protocol' => array('n',0x0001),
                'bodyLen' => array('n',4)
            );
            $package_data = $this->onPackage($data);

            set_time_limit(0);
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); // 创建一个Socket
            if($socket) {
                $connection = socket_connect($socket, $this->host, $this->port);    //  连接
                if($connection) {
                    $nwrite = socket_write($socket, $package_data); // 数据传送 向服务器发送消息
                    if($nwrite) {
                        socket_close($socket);
                        $this->ajaxReturn(0, "发送成功");
                    } else {
                        socket_close($socket);
                        $this->ajaxReturn(4, "发送失败");
                    }
                } else {
                    socket_close($socket);
                    $this->ajaxReturn(3, "连接服务器失败");
                }
            } else {
                $this->ajaxReturn(2, "创建socket失败");
            }
        } else {
            $this->ajaxReturn(1, "仅支持post请求");
        }
    }

    public function ipList()
    {
        if($_SERVER['REQUEST_METHOD'] == "POST") {
            $data = array(
                'ip' => array('N',$this->client_ip_long),
                'protocol' => array('n',0x0009),
                'bodyLen' => array('n',4)
            );
            $package_data = $this->onPackage($data);
            $ips = array();
            set_time_limit(0);
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); // 创建一个Socket
            if($socket) {
                $connection = socket_connect($socket, $this->host, $this->port);    //  连接
                if($connection) {
                    $nwrite = socket_write($socket, $package_data); // 数据传送 向服务器发送消息
                    if($nwrite) {
                        $buff = socket_read($socket, 4096, PHP_BINARY_READ);
                        $format = array(
                            'ip' => array('N',0),
                            'protocol' => array('n',4),
                            'bodyLen' => array('n',6),
                            'ips' => array('N*',8)
                        );
                        $ret = $this->unPackage($buff, $format);
                        foreach($ret[3] as $v) {
                            array_push($ips, long2ip($v));
                        }
                        socket_close($socket);
                        $this->ajaxReturn(0, "", $ips);
                    } else {
                        socket_close($socket);
                        $this->ajaxReturn(4, "发送失败");
                    }
                } else {
                    socket_close($socket);
                    $this->ajaxReturn(3, "连接服务器失败");
                }
            } else {
                $this->ajaxReturn(2, "创建socket失败");
            }
        } else {
            $this->ajaxReturn(1, "仅支持post请求");
        }
    }

    public function left()
    {
        if($_SERVER['REQUEST_METHOD'] == "POST") {
            $data = array(
                'ip' => array('N',$this->client_ip_long),
                'protocol' => array('n',0x0003),
                'bodyLen' => array('n',4)
            );
            $package_data = $this->onPackage($data);

            set_time_limit(0);
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); // 创建一个Socket
            if($socket) {
                $connection = socket_connect($socket, $this->host, $this->port);    //  连接
                if($connection) {
                    $nwrite = socket_write($socket, $package_data); // 数据传送 向服务器发送消息
                    if($nwrite) {
                        socket_close($socket);
                        $this->ajaxReturn(0, "发送成功");
                    } else {
                        socket_close($socket);
                        $this->ajaxReturn(4, "发送失败");
                    }
                } else {
                    socket_close($socket);
                    $this->ajaxReturn(3, "连接服务器失败");
                }
            } else {
                $this->ajaxReturn(2, "创建socket失败");
            }
        } else {
            $this->ajaxReturn(1, "仅支持post请求");
        }
    }

    public function send()
    {
        if($_SERVER['REQUEST_METHOD'] == "POST") {
            //$msg = htmlspecialchars($_POST['msg']);
            $msg = str_replace("|", "", $_POST['msg']);
            $msg = str_replace("\n", "", $msg);
            $msg = substr($msg, 0, 100);
            if(empty($msg)) {
                $this->ajaxReturn(11, "消息不能为空");
            }

            $data = array(
                'ip' => array('N',$this->client_ip_long),
                'protocol' => array('n',0x0005),
                'bodyLen' => array('n',strlen($msg)),
                //'msg' => array('c*',$msg)
            );
            $package_data = $this->onPackage($data).$msg;

            set_time_limit(0);
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); // 创建一个Socket
            if($socket) {
                $connection = socket_connect($socket, $this->host, $this->port);    //  连接
                if($connection) {
                    $nwrite = socket_write($socket, $package_data); // 数据传送 向服务器发送消息
                    if($nwrite) {
                        socket_close($socket);
                        $this->ajaxReturn(0, "发送成功");
                    } else {
                        socket_close($socket);
                        $this->ajaxReturn(4, "发送失败");
                    }
                } else {
                    socket_close($socket);
                    $this->ajaxReturn(3, "连接服务器失败");
                }
            } else {
                $this->ajaxReturn(2, "创建socket失败");
            }
        } else {
            $this->ajaxReturn(1, "仅支持post请求");
        }
    }

    public function recv()
    {
        if($_SERVER['REQUEST_METHOD'] == "POST") {
            $data = array(
                'ip' => array('N',$this->client_ip_long),
                'protocol' => array('n',0x0007),
                'bodyLen' => array('n',4)
            );
            $package_data = $this->onPackage($data);
            set_time_limit(0);
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); // 创建一个Socket
            if($socket) {
                $connection = socket_connect($socket, $this->host, $this->port);    //  连接
                if($connection) {
                    $nwrite = socket_write($socket, $package_data); // 数据传送 向服务器发送消息
                    if($nwrite) {
                        $buff = socket_read($socket, 4096, PHP_BINARY_READ);
                        /*$format = array(
                            'ip' => array('N',0),
                            'protocol' => array('n',4),
                            'bodyLen' => array('n',6),
                            //'msg' => array('c*',8)
                        );
                        $ret = $this->unPackage($buff, $format);*/
                        $msg = substr($buff, 8);
                        socket_close($socket);
                        $this->ajaxReturn(0, "", $msg);
                    } else {
                        socket_close($socket);
                        $this->ajaxReturn(4, "发送失败");
                    }
                } else {
                    socket_close($socket);
                    $this->ajaxReturn(3, "连接服务器失败");
                }
            } else {
                $this->ajaxReturn(2, "创建socket失败");
            }
        } else {
            $this->ajaxReturn(1, "仅支持post请求");
        }
    }

    private function onPackage($data)
    {
        $ret = '';
        foreach($data as $v) {
            $ret .= pack($v[0], $v[1]);
        }
        return $ret;
    }

    private function unPackage($data, $format)
    {
        $ret = array();
        foreach($format as $v) {
            array_push($ret, unpack($v[0], substr($data, $v[1])));
        }
        return $ret;
    }

    private function ajaxReturn($code, $msg = "", $data = array())
    {
        echo json_encode(array("code" => $code, "msg" => $msg, "data" => $data));
        exit($code) ;
    }
}