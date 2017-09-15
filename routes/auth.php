<?php
require_once '../common.php';


$code = filter_input(INPUT_GET, 'code');
if( empty($code)) {
	$tmpl = <<<EOFHTML
<!DOCTYPE html><html lang=""><head>  <meta charset="utf-8"><title>DeMilked – Авторизация</title><meta name="description" content="" />  <meta name="keywords" content="" /><meta name="robots" content="" /></head><body><a href="%s">Войти через ВКонтакте</a></body></html>
EOFHTML;
	printf( $tmpl, getAuthLink());
	exit();
}

$token = getToken( $code);

echo "Token: " . $token . "\n";


function getAuthLink() {
	
	$params = [
		'client_id'			=> $_ENV['VK_APP_ID']
		, 'redirect_uri'	=> $_ENV['VK_REDIRECT_URL']
		, 'display'			=> 'page'
		, 'scope'			=> 'photos,groups,offline'
		, 'response_type'	=> 'code'
		, 'v'				=> 5.68
		, 'state'			=> substr( md5( time()), 2, 4)
	];

	$url = 'https://oauth.vk.com/authorize';

	return $url . '?' . http_build_query($params);
}


function getToken( $code) {

	$params = [
		'client_id'			=> $_ENV['VK_APP_ID']
		, 'client_secret'	=> $_ENV['VK_APP_SECRET']
		, 'redirect_uri'	=> $_ENV['VK_REDIRECT_URL']
		, 'code'			=> $code
	];

	$url = 'https://oauth.vk.com/access_token';
	
	$response = file_get_contents( $url .'?'.http_build_query($params));
	$result = json_decode( $response);
	
	if( !isset($result, $result->access_token, $result->expires_in, $result->user_id)) {
		echo "Bad response from VK:<pre>\n" . $response . "\n</pre>" . PHP_EOL;
		return;
	}
	
	if( $result->expires_in !== 0) {
		echo "The token will expire in " . $result->expires_in . "s. \n";
	}
	
	return $result->access_token;
}