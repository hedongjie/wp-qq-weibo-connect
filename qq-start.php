<?php
include "../../../wp-config.php";

if(!class_exists('QQOAuth')){
	include dirname(__FILE__).'/qqOAuth.php';
}

$to = new QQOAuth($qq_consumer_key, $qq_consumer_secret);

	
$tok = $to->getRequestToken(get_option('home'));

$_SESSION["qq_oauth_token_secret"] = $tok['oauth_token_secret'];
if($_GET['callback_url']){
	$callback_url = $_GET['callback_url'];
}else{
	$callback_url = get_option('home');
}
$request_link = $to->getAuthorizeURL($tok['oauth_token'],$callback_url);

header('Location:'.$request_link);
?>
