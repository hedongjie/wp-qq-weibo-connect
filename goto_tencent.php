<?php
  session_start();

  include_once dirname(__FILE__).'/Config.php';
  include_once dirname(__FILE__).'/Tencent.php';

  OAuth::init($client_id, $client_secret);

  $callback = 'http://'.$_SERVER['HTTP_HOST'].'/wp-content/plugins/qq-connect/goto_tencent.php';//回调url
  $callback = isset($_GET['redirect_to']) ? $callback.'?redirect_to='.$_GET['redirect_to'] : $callback;
  $callback = strpos($callback,'?') !== false ? $callback.'&from=qq' : $callback.'?from=qq';

  $redirect_url = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : 'http://cross.hk/wp-login.php?loggedout=true';
  $redirect_url = strpos($redirect_url,'?') !== false ? $redirect_url.'&from=qq' : $redirect_url.'?from=qq';

  if ($_GET['code']) {//已获得code
        $code = $_GET['code'];
        $openid = $_GET['openid'];
        $openkey = $_GET['openkey'];
        //获取授权token
        $url = OAuth::getAccessToken($code, $callback);
        $r = Http::request($url);
        if(!$r){
          print 'Checking auth info......<script type="text/javascript">var currentHref = location.href;location.href = currentHref;</script>';
          exit;
        }
        parse_str($r, $out);
        //存储授权数据
        if ($out['access_token']) {
            $_SESSION['t_access_token'] = $out['access_token'];
            $_SESSION['t_refresh_token'] = $out['refresh_token'];
            $_SESSION['t_expire_in'] = $out['expire_in'];
            $_SESSION['t_code'] = $code;
            $_SESSION['t_openid'] = $openid;
            $_SESSION['t_openkey'] = $openkey;

            //验证授权
            $r = OAuth::checkOAuthValid();
            if ($r && $redirect_url) {
                //如果是后台页面跳转
                if(strpos($redirect_url,'wp-admin') !== false){
                  $redirect_url = 'http://cross.hk?from=qq&redirect_to='.$redirect_url;
                }
                header('Location: ' . $redirect_url);//刷新页面
            } else {
                exit('<h3>授权失败,请重试</h3>');
            }
        } else {
            exit($r);
        }
    } else {//获取授权code
        if ($_GET['openid'] && $_GET['openkey']){//应用频道
            $_SESSION['t_openid'] = $_GET['openid'];
            $_SESSION['t_openkey'] = $_GET['openkey'];
            //验证授权
            $r = OAuth::checkOAuthValid();
            if ($r) {
                header('Location: ' . $callback);//刷新页面
            } else {
                exit('<h3>授权失败,请重试</h3>');
            }
        } else{
            $url = OAuth::getAuthorizeURL($callback);
            header('Location: ' . $url);
        }
  }
?>