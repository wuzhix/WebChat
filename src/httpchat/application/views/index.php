<?php
defined('BASEPATH') OR exit('No direct script access allowed');
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- 上述3个meta标签*必须*放在最前面，任何其他内容都*必须*跟随其后！ -->
    <title>在线聊天室</title>

    <!-- Bootstrap -->
    <link href="/resources/bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="http://cdn.bootcss.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="http://cdn.bootcss.com/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->
</head>
<body>
<?php require "header.php" ?>

<div style="width: 800px;height: 500px;margin-left: auto;margin-right: auto;">
    <!--右侧显示用户信息-->
    <div class="panel panel-primary" id="ipList" style="margin-bottom:0px;width: 200px;height: 500px;overflow-y: auto;float: right;">
    </div>
    <!--标题-->
    <div class="panel panel-primary" style="width: 600px;margin-bottom:0px;">
        <div class="panel-heading">
            <h3 class="panel-title">在线聊天室</h3>
        </div>
    </div>
    <!--聊天内容-->
    <div class="panel panel-primary" id="chat_msg" style="width: 600px;height: 460px;overflow-y: auto;margin-bottom:0px;padding: 0 0 10px">
    </div>
    <!--聊天输入框-->
    <div class="input-group">
        <textarea class="form-control custom-control" rows="3" id="msg" maxlength="100" placeholder="请输入消息，长度不能超过100个字符，|和换行符会被忽略" style="resize:none;width: 600px;"></textarea>
        <span class="input-group-addon btn btn-primary" style="width:200px;" onclick="sendMsg();">发送消息</span>
    </div>
</div>


<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="http://cdn.bootcss.com/jquery/1.11.2/jquery.min.js"></script>
<!-- Include all compiled plugins (below), or include individual files as needed -->
<script src="/resources/bootstrap/js/bootstrap.min.js"></script>
<link rel="stylesheet" href="/resources/artDialog/css/ui-dialog.css">
<script src="/resources/artDialog/dist/dialog-min.js"></script>
</body>
<script type="application/javascript">
    var IsSending = false;
    $(function() {
        join();
    });
    $(window).unload(function(){
        left();
    });
    $(document).keypress(function(e) {
        // 回车键事件
        if(e.which == 13 && $("#msg").is(":focus")) {
            sendMsg();
            return false;
        }
    });
    function html_encode(str)
    {
        var s = "";
        if (str.length == 0) return "";
        s = str.replace(/&/g, "&gt;");
        s = s.replace(/</g, "&lt;");
        s = s.replace(/>/g, "&gt;");
        s = s.replace(/ /g, "&nbsp;");
        s = s.replace(/\'/g, "&#39;");
        s = s.replace(/\"/g, "&quot;");
        s = s.replace(/\n/g, "<br>");
        return s;
    }
    function showOtherMsg(user, msg)
    {
        var html = "<div style='clear: both;margin: 10px 0 0 10px;color: grey;'>"+user+"</div><div class='panel' style='float:left;width: auto;margin: 0 20px 5px;padding:5px 8px;background-color: lightblue;'>"+html_encode(msg)+"</div>";
        $('#chat_msg').append(html);
        $("#chat_msg").scrollTop($("#chat_msg")[0].scrollHeight - $("#chat_msg")[0].clientHeight);
    }
    function showMyMsg(msg)
    {
        var html = "<div class='panel' style='clear:both;float:right;width: auto;margin: 10px 20px 5px;padding:5px 8px;background-color: #d3d3d3;'>"+html_encode(msg)+"</div>";
        $('#chat_msg').append(html);
        $("#chat_msg").scrollTop($("#chat_msg")[0].scrollHeight - $("#chat_msg")[0].clientHeight);
    }
    function showTipsMsg(msg)
    {
        var html = "<div style='clear:both;width: auto;margin: 5px auto;text-align:center;color: grey;'>"+msg+"</div>";
        $('#chat_msg').append(html);
        $("#chat_msg").scrollTop($("#chat_msg")[0].scrollHeight - $("#chat_msg")[0].clientHeight);
    }
    function addUser(user)
    {
        $('#ipList').append("<div id='"+IP2Num(user)+"' style='margin: 5px;color: green;'>"+user+"</div>");
    }
    function removeUser(user)
    {
        $("#"+IP2Num(user)).remove();
    }
    function IP2Num(ip)
    {
        ip = ip.split(".");
        var num = Number(ip[0]) * 256 * 256 * 256 + Number(ip[1]) * 256 * 256 + Number(ip[2]) * 256 + Number(ip[3]);
        num = num >>> 0;
        return num;
    }
    function join()
    {
        $.ajax({
            url : '/Index/join',
            type : 'POST',
            dataType : 'json',
            success : function(res) {
                if(res.code == 0) {
                    var sysMsg = "在线聊天室是一款HTTP即时群聊工具<br>" +
                        "底层由C++ EPOLL实现<br>" +
                        "WEB服务器为CodeIgniter框架<br>" +
                        "前端采用Bootstrap风格<br>" +
                        "欢迎体验！";
                    showTipsMsg(sysMsg);
                    ipList();
                    recv();
                } else {
                    var d = dialog({
                        title: '提示',
                        content: res.msg
                    });
                    d.showModal();
                }
            },
            error : function(res) {
            }
        });
    }
    function ipList()
    {
        $.ajax({
            url : '/Index/ipList',
            type : 'POST',
            dataType : 'json',
            success : function(res) {
                if(res.code == 0) {
                    $('#ipList').html("");
                    for(var i in res.data) {
                        addUser(res.data[i]);
                    }
                } else {
                    var d = dialog({
                        title: '提示',
                        content: res.msg
                    });
                    d.showModal();
                }
            },
            error : function(res) {
            }
        });
    }
    function sendMsg()
    {
        if(!IsSending) {
            IsSending = true;
            var msg = $('#msg').val().replace(/\|/g,'').replace(/\n/g,'').substr(0,100);
            $.ajax({
                url : '/Index/send',
                type : 'POST',
                dataType : 'json',
                data : {
                    msg : msg
                },
                success : function(res) {
                    if(res.code == 0) {
                        showMyMsg(msg);
                        $('#msg').val("");
                        //recv(); //测试代码 后续删除 wuzx 2015-07-10
                    } else {
                        var d = dialog({
                            align: 'left',
                            content: res.msg,
                            quickClose: true// 点击空白处快速关闭
                        });
                        d.show(document.getElementById('msg'));
                    }
                    IsSending = false;
                },
                error : function(res) {
                    IsSending = false;
                }
            });
        }
    }
    function recv()
    {
        $.ajax({
            url : '/Index/recv',
            type : 'POST',
            dataType : 'json',
            success : function(res) {
                if(res.code == 0) {
                    if(res.data) {
                        var data_array = res.data.split("\n");
                        for(var i in data_array) {
                            var msg_array = data_array[i].split("|");
                            if(msg_array.length < 2) {
                                var sys_data = data_array[i].split(" ");
                                if(sys_data[1] == "0x0001") {
                                    showTipsMsg(sys_data[0]+"进入聊天室");
                                    addUser(sys_data[0]);
                                } else if(sys_data[1] == "0x0003") {
                                    showTipsMsg(sys_data[0]+"离开聊天室");
                                    removeUser(sys_data[0]);
                                }
                            } else {
                                showOtherMsg(msg_array[0], msg_array[1]);
                            }
                        }
                    }
                    setTimeout("recv()", 3000);
                } else {
                    /*var d = dialog({
                        title: '提示',
                        content: res.msg
                    });
                    d.showModal();*/
                }
            },
            error : function(res) {
                //setTimeout("recv()", 3000);
            }
        });
    }
    function left()
    {
        $.ajax({
            url : '/Index/left',
            type : 'POST',
            dataType : 'json',
            async : false,
            success : function(res) {
                //removeUser("<?php echo $user;?>");
            },
            error : function(res) {
            }
        });
    }
</script>
</html>