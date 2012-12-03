<?php
/*
Plugin Name: 腾讯连接
Author:  Denis
Author URI: http://fairyfish.net/
Plugin URI: http://fairyfish.net/2010/12/20/qq-connect/
Description: 使用腾讯微博瓣账号登陆你的 WordPress 博客，并且留言使用腾讯微博的头像，博主可以同步日志到腾讯微博，用户可以同步留言到腾讯微博。
Version: 2.2
*/
$sc_loaded = false;
$user = null;
$client_id = '';
$openid = '';
$openkey = '';
$client_secret = '';

include_once dirname(__FILE__).'/Config.php';
include_once dirname(__FILE__).'/Tencent.php';

OAuth::init($client_id, $client_secret);

add_action('init', 'cross_t_init');
function cross_t_init(){
    /*
      打包发布前删除这行代码
      如果wordpress有做静态化，应该在此清除缓存
    */
    do_action('extra_login_del_cache');
	if (session_id() == "") {
		session_start();
	}
	if($_GET['from'] === 'qq'){
	    global $user;
        do_action('t_before_login');
        if(!is_user_logged_in()) {
            authuser();
            if($user){
              cross_q_confirm();
            }
        }
    }
}
function authuser(){
  global $user,$openid,$openkey;
  if($_SESSION['t_access_token'] || ($_SESSION['t_openid'] && $_SESSION['t_openkey'])){
    $openid = $_SESSION['t_openid'];
    $openkey = $_SESSION['t_openkey'];
    $user = Tencent::api('user/info');
  }
}

add_action("login_form", "qq_connect",1000);
add_action("register_form", "qq_connect",1000);
function qq_connect($id="",$callbackurl=null){
	global $qc_loaded;
	if($qc_loaded) {
		return;
	}

	$qc_url = WP_PLUGIN_URL.'/'.dirname(plugin_basename (__FILE__));

?>
	<style type="text/css">
	.qc_button img{ border:none;}
    </style>
	<p id="qc_connect" class="qc_button">
	<a href="<?php echo WP_PLUGIN_URL.'/'.dirname(plugin_basename (__FILE__)).'/goto_tencent.php'.(isset($_GET['redirect_to']) ? '?redirect_to='.$_GET['redirect_to'] : ($callbackurl ? '?redirect_to='.$callbackurl : '')); ?>"><img src="<?php echo $qc_url; ?>/qq_button.png" alt="使用腾讯微博登陆" style="cursor: pointer; margin-right: 20px;" /></a>
	</p>
<?php
    $qc_loaded = true;
}

add_filter("get_avatar", "qc_get_avatar",10,4);
function qc_get_avatar($avatar, $id_or_email='',$size='32') {
	global $comment;
	if(is_object($comment)) {
		$id_or_email = $comment->user_id;
	}
	if (is_object($id_or_email)){
		$id_or_email = $id_or_email->user_id;
	}

	//工具条上显示的头像，必须是当前用户的头像
    //QQ微博用户则用qcid，原博客用户直接使用Gavater
    if($size === 16 || $size === 64){
      $current_user = wp_get_current_user();
      $id_or_email = $current_user->ID;
    }

	if($qcid = get_usermeta($id_or_email, 'qcid')){
		$out = $qcid.'/100';
		if($qcid === 'default'){
          $out = 'http://mat1.gtimg.com/app/opent/images/wiki/resource/weiboicon24.png';
        }
		$avatar = "<img alt='' src='{$out}' class='avatar avatar-{$size}' height='{$size}' width='{$size}' />";

		return $avatar;
	}else {
		return $avatar;
	}
}

function cross_q_confirm(){
    global $user,$client_secret;
    $qqInfo = $user;

	$qqInfo = json_decode($qqInfo,true);

	$qqInfo = $qqInfo['data'];

	qc_login($qqInfo['head'].'|'.$qqInfo['name'].'|'.$qqInfo['nick'].'|'.$_SESSION['t_access_token'] .'|'.$client_secret);
}

function qc_login($Userinfo) {
    global $openid,$openkey;
	$userinfo = explode('|',$Userinfo);
	if(count($userinfo) < 5) {
		wp_die("An error occurred while trying to contact qq Connect.");
	}

	$userdata = array(
		'user_pass' => wp_generate_password(),
		'user_login' => 'qq_t_'.$userinfo[1],
		'display_name' => $userinfo[2],
		'user_email' => $userinfo[1].'@t.qq.com'
	);

	if(!function_exists('wp_insert_user')){
		include_once( ABSPATH . WPINC . '/registration.php' );
	}

	$wpuid = username_exists('qq_t_'. $userinfo[1]);
    if(!$userinfo[0]){
      $userinfo[0] = 'default';
    }

	if(!$wpuid){
		if($userinfo[0]){
			$wpuid = wp_insert_user($userdata);

			if($wpuid){
				update_usermeta($wpuid, 'qcid', $userinfo[0]);
				$qc_array = array (
					"oauth_access_token" => $userinfo[3],
					"oauth_access_token_secret" => $userinfo[4],
				);
				update_usermeta($wpuid, 'qcdata', $qc_array);
			}
		}
	} else {
		update_usermeta($wpuid, 'qcid', $userinfo[0]);
		$qc_array = array (
			"oauth_access_token" => $userinfo[3],
			"oauth_access_token_secret" => $userinfo[4],
		);
		update_usermeta($wpuid, 'qcdata', $qc_array);
	}

	if($wpuid) {
	    update_user_meta($wpuid,'openid',$openid);
	    update_user_meta($wpuid,'openkey',$openkey);
		wp_set_auth_cookie($wpuid, true, false);
		wp_set_current_user($wpuid);
	}
}

if(!function_exists('connect_login_form_login')){
	add_action("login_form_login", "connect_login_form_login");
	add_action("login_form_register", "connect_login_form_login");
	function connect_login_form_login(){
		if(is_user_logged_in()){
			$redirect_to = admin_url('profile.php');
			wp_safe_redirect($redirect_to);
		}
	}
}

function my_get_ip(){
	if(getenv('HTTP_CLIENT_IP')) {
	$onlineip = getenv('HTTP_CLIENT_IP');
	} elseif(getenv('HTTP_X_FORWARDED_FOR')) {
	$onlineip = getenv('HTTP_X_FORWARDED_FOR');
	} elseif(getenv('REMOTE_ADDR')) {
	$onlineip = getenv('REMOTE_ADDR');
	} else {
	$onlineip = $HTTP_SERVER_VARS['REMOTE_ADDR'];
	}
}

/*
  同步 qq 微博的设置
*/
add_action('admin_menu', 'cross_q_options');
function cross_q_options() {
	add_options_page('同步文章到QQ微博', '同步文章到QQ微博', 'read', 'qc_options', 'cross_q_options_do_page');
}
add_option('t_weibo_log','');
function show_t_weibo_setting(){
  $value = get_option('t_weibo_log');
  echo "<div class=\"wrap\"><h2>设置QQ微博发送日志</h2>";
  echo '<form action="options-general.php?page=qc_options" method="post">'.
        '<input type="hidden" name="updated_t_weibo_log" value="true" />'.
        '<table class="form-table">'.
          '<tr><th>日志地址</th><td><input type="text" name="t_weibo_log" value="'.$value.'" />(请勿必确保日志文件可写)</td></tr>'.
        '</table>';
  submit_button();
  echo "</form></div>";
}
if($_POST['updated_t_weibo_log']){
  echo "<div class=\"wrap\"><div class=\"updated\" style=\"padding:10px;\">QQ微博发送日志更新成功。</div></div>";
  update_option('t_weibo_log',$_POST['t_weibo_log']);
}

function cross_q_options_do_page() {
    $q_id = get_user_meta(get_current_user_id(),'qcid',true);

    if( current_user_can('update_core')){
      show_t_weibo_setting();
      return;
    }

    if(!$q_id){
      echo "<div class=\"wrap\"><div class=\"error\" style=\"padding:10px;\">当前登录的用户不是QQ微博用户。</div></div>";
      return;
    }

    global $current_user;
    get_currentuserinfo();

    if($_GET['delete']) {
      update_user_meta(get_current_user_id(),'sync_to_t_weibo',0);
    }
?>
	<div class="wrap">
    		<h2>同步到QQ微博</h2>
    		<form method="post" action="options.php">
                <?php
                global $user;
                $sync_to_t_weibo = get_user_meta(get_current_user_id(),'sync_to_t_weibo',true);
    			if($_GET['delete']){
                    if(!$sync_to_t_weibo){

                      echo '<div class="wrap"><div class="updated" style="padding:10px;">'.$current_user->display_name.'，您已成功取消与QQ微博数据同步。</div></div>';
                    }
    				 echo '<p><a href="'.menu_page_url('qc_options',false).'">重新绑定或者绑定其他帐号？</a></p>';
    			}else if($_GET['from'] === 'qq' || $sync_to_t_weibo){
    			    authuser();
    				if($sync_to_t_weibo || $user){
    				    $status = update_user_meta(get_current_user_id(),'sync_to_t_weibo',1);
    				    if($sync_to_t_weibo || $status){
    				      echo '<div class="wrap"><div class="updated" style="padding:10px;">'.$current_user->display_name.'，您已成功设置与QQ微博数据同步。<p>当你的博客更新的时候，会同时更新到QQ微博。</p></div></div>';
    				    }
    				    $qq_t_name = explode('qq_t_',get_user_meta(get_current_user_id(),'nickname',true));
    					echo '<p>QQ微博帐号 <a href="http://t.qq.com/'.($qq_t_name[1] ? $qq_t_name[1] : '').'" target="_blank">'.$current_user->display_name.'</a> 。<a href="'.menu_page_url('qc_options',false).'&delete=1">取消绑定或者绑定其他帐号？</a></p>';
    				}
    			}else{
                    echo '<p>点击下面的图标，将你的QQ微博客帐号和你的博客绑定，当你的博客更新的时候，会同时更新到QQ微博。</p>';
                    qq_connect('',menu_page_url('qc_options',false));
                }
    			?>
    </div>
	<?php
}

function publish_to_t_qq($status=null,$post_author,$pic){
    session_start();

    global $client_id,$openkey,$client_secret;

	$token = get_user_meta($post_author,'qcdata',true);
	$openid = get_user_meta($post_author,'openid',true);
	$openkey = get_user_meta($post_author,'openkey',true);

	if(!$token){return;}

	$token = $token['oauth_access_token'];

	if($token){
	  $_SESSION['t_access_token'] = $token;
	  $_SESSION['t_openid'] = $openid;
	  $_SESSION['t_openkey'] = $openkey;
	  $status = $status;
	  $ip = '114.249.223.241';


	include_once dirname(__FILE__).'/Config.php';
    include_once dirname(__FILE__).'/Tencent.php';

    OAuth::init($client_id, $client_secret);
    $params = array( "format" => "json", "content" => $status, "clientip" => $ip,"syncflag" => "0");
    if($pic){
      $api = 't/add_pic_url';
      $params['pic_url'] = $pic;
    }else{
      $api = 't/add';
    }

    $response = Tencent::api($api,$params,"post",false);
    $response = json_decode($response,true);
    if($response['data']){
        if($response['data']['id']){
          writing_t_qq_status($post_author,$status);
        }
      }
	}
}

function writing_t_qq_status($post_author,$status){
  $userinfo = get_userdata($post_author);
  $file = get_option('t_weibo_log');
  if(!$file){return;}

  if(!is_writable($file)){
    chmod($file,0777);
  }

  $fp = fopen($file,'ab');
  fwrite($fp,"微博内容：".$status."\n发送时间：".date( "Y-m-d   H:i:m ")."\nQQ微博用户：".$userinfo->display_name."\n\n\n\n\n");
  fclose($fp);
}

add_action('publish_post', 'publish_post_2_qq_t', 0);
function publish_post_2_qq_t($post_ID){
    global $post_author;
    $post_data = get_post($post_ID,'ARRAY_A');
    $post_author = $post_data['post_author'];

    if(!$post_author){return;}

    if($qcid = get_user_meta($post_author,'qcid',true)){
      add_post_meta($post_ID, 'from', $qcid, true);
    }else{
      return;
    }

	$sync_to_t_weibo = get_user_meta($post_author,'sync_to_t_weibo',1);

	if(!$sync_to_t_weibo) return;

	$c_post = get_post($post_ID);
	$post_title = $c_post->post_title;
    $post_content = strip_tags($c_post->post_content,'<img>');
    $pic = get_post_first_image($c_post->post_content);

	$title_len = mb_strlen($post_title,'UTF-8');
    $content_len = mb_strlen($post_content,'UTF-8');
    $rest_len = 120;

    if($title_len + $content_len> $rest_len) {
        $post_content = mb_substr($post_content,0,$rest_len-$title_len).'... ';
    }

    $status = '【'.$c_post->post_title.'】'.strip_tags($c_post->post_content).' '.get_permalink($post_ID);

	publish_to_t_qq($status,$post_author,$pic);
}

if(!function_exists('get_post_first_image')){
	function get_post_first_image($post_content){
		preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post_content, $matches);
		if($matches){
			return $matches[1][0];
		}else{
			return false;
		}
	}
}
