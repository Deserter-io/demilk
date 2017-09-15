<?php
require_once '../common.php';

/*
function help() {
	echo implode( PHP_EOL, [
		"Please pass a url to the post as an argument:"
		,"\tphp demilk.php https://www.demilked.com/cartoon-people-photos-lance-phan/"
		,""
	]);
}
	
if( $argc < 2) exit(help());
$url = $argv[1];
if(empty($url)) exit(help());
*/

$url = 'https://www.demilked.com/cartoon-people-photos-lance-phan/';

$project = trim($url, '/');
$project = explode('/', $project);
$project = $project[ count($project) - 1];

if(empty($project)) die("Failed to extract project name");

// Folder for photos
$folder = STORAGE . '/' . $project;
if(!file_exists($folder)  &&  !mkdir($folder, 0775, true)) die("Failed to created folder $folder");


// VK album for photos
// get all albums first
$albums = vkapi( 'photos.getAlbums', [
	'owner_id'	=> $_ENV['VK_ALBUM_OWNER_ID']
]);

print_r($albums); exit();


$ch = curl_init($url);
curl_setopt_array($ch, [
	CURLOPT_RETURNTRANSFER	=> true,
	CURLOPT_FOLLOWLOCATION	=> true,
	CURLOPT_CONNECTTIMEOUT	=> 2,
	CURLOPT_SSL_VERIFYHOST	=> 0,
	CURLOPT_SSL_VERIFYPEER	=> 0,
// 	CURLOPT_SSLVERSION		=> 6,
	CURLOPT_TIMEOUT			=> 3,
	CURLOPT_COOKIEFILE		=> __DIR__ . '/cook.file.txt',
	CURLOPT_COOKIEJAR		=> __DIR__ . '/cook.jar.txt',
 	CURLOPT_REFERER			=> 'https://www.demilked.com/',
 	CURLOPT_USERAGENT		=> 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:55.0) Gecko/20100101 Firefox/55.0',
]);

$html = curl_exec($ch);
$len = strlen($html);
if( $len === 0) {
	echo "Error: " . curl_error($ch) . PHP_EOL;
	print_r( curl_getinfo($ch));
	exit();
}

echo "Response length: " . strlen($html) . PHP_EOL;



// Parse
$html = strstr( $html, '<h2>#1</h2>');
$html = strstr( $html, '<div class="social">', TRUE);

$pattern = '%\<h2\>#\d+\</h2\>\s*\<p\>\<img src="([^\"]+)"[^\>]+\>\</p\>\s+\<p\>Image source\: \<a.+href="([^\"]+)".*</p>%';

preg_match_all($pattern, $html, $matches);


if( count($matches[1]) < 1) die("Didn't find any images");
$i = 0;
foreach($matches[1] AS $imgUrl) {
	$fname = tempnam(__DIR__, sprintf('%02d', $i));
	$fp = fopen($fname, 'w');
	
	// download image
	curl_setopt_array($ch, [
		CURLOPT_URL		=> $url,
		CURLOPT_FILE	=> $fp,
	]);
	
	fclose($fp);
	echo "Saved to $fname\n";
}






function vkapi( $methodName, $params) {
	static $ch;
	if( is_null($ch)) {
		$ch = curl_init();
		curl_setopt_array($ch, [
			CURLOPT_POST			=> true,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_FOLLOWLOCATION	=> true,
			CURLOPT_CONNECTTIMEOUT	=> 2,
			CURLOPT_SSL_VERIFYHOST	=> 0,
			CURLOPT_SSL_VERIFYPEER	=> 0,
			CURLOPT_TIMEOUT			=> 3,
		]);
	}
	
	$url = 'https://api.vk.com/method/' . $methodName;
	$params['v'] = '5.68';
	$params['access_token'] = $_ENV['VK_USER_TOKEN'];
	
	curl_setopt_array($ch, [
		CURLOPT_URL			=> $url,
		CURLOPT_POSTFIELDS	=> http_build_query($params),
	]);
	
	$response = curl_exec($ch);
	$data = json_decode($response);
	if(is_null($data)) {
		echo $response;
		throw new Exception('Bad response from VK');
	}
	
	if( isset($data->error)) {
		echo $response;
		throw new Exception('VK Error');
	}
	
	if( !isset($data->response)) {
		echo $response;
		throw new Exception('VK missing response');
	}
	
	return $data->response;
}

