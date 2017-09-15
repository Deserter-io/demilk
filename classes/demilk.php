<?php


class Demilk {
	
	const STORAGE = STORAGE;

	protected $projectName;
	protected $chWeb;	// cURL handle for web site requests
	protected $chVK;	// cURL handle for VK API
	protected $url;
	
	public function run( $url) {

		// project name
		$projectURL = trim( $url, '/');
		$projectParts = explode('/', $projectURL);
		$projectName = $projectParts[ count($projectParts) - 1];
		if( preg_match('/^\d+$/', $projectName)) $projectName = $projectParts[ count($projectParts) - 2];
		
		if( empty( $projectName)) die("Failed to extract project name");
		if( strlen($projectName) < 5) die("Project name appeares too short. " . $projectName);

		$this->projectName = $projectName;


		// Folder for photos
		$folder = self::STORAGE . '/' . $this->projectName;
		if( !file_exists($folder)  &&  !mkdir($folder, 0775, true)) die("Failed to created folder $folder");
		
		
		// VK album for photos
		$album = $this->getAlbum( $this->projectName, $url);
		
		// upload url
		$uploadUrl = $this->getUploadURL( $album->id);
		
		
		// Let's get the images
		$html = $this->getPageHTML( $url);
		
		// Remove head and tail
		$html = strstr( $html, '<h2>#');
		$html = strstr( $html, '<div class="social">', true);
		
		if( ($len = strlen($html)) < 100) {
			echo "HTML is suspiciously short: only $len bytes. HTML:\n\n$html";
			die();
		}


		// Extract urls
		$pattern = '%\<h2\>#\d+\</h2\>\s+\<p\>\<img src="([^\"]+)"[^\>]+\>\</p\>\s+\<p\>Image source\:.*\<a.+href="([^\"]+)"%';
		
		preg_match_all($pattern, $html, $matches);
		
		if( count($matches[1]) < 1) {
			echo("Didn't find any images.\nMatches:\n");
			print_r($matches);
			echo "HTML:";
			print($html);
			die("Didn't find any images.");
		} 
		
		foreach($matches[1] AS $i => $imgUrl) {
			
			$fileNameParts = explode('.', $imgUrl); 
			$extension = end($fileNameParts);

			$fname = $folder . sprintf('/%02d.%s', $i, $extension);
			$fp = fopen($fname, 'w');
			
			// download image
			$ch = $this->getCurl('web');

			curl_setopt_array( $ch, [
				CURLOPT_URL		=> $imgUrl,
				CURLOPT_FILE	=> $fp,
			]);
			
			curl_exec($ch);
			
			fclose($fp);


			// upload file to VK
			$cf = new CURLFile($fname);
			$params = [
				'file1'	=> $cf
			];
			
			$ch = $this->getCurl('vk');
			curl_setopt_array( $ch, [
				CURLOPT_URL				=> $uploadUrl
				, CURLOPT_POSTFIELDS	=> $params
			]);
			
			$response = curl_exec($ch);
			$data = json_decode($response);
			if( is_null($data)) {
				echo $response;
				throw new Exception('Bad response from VK');
			}
			
			
			// caption
			if( isset( $matches[2], $matches[2][$i])) {
				$caption = "Оригинал: " . $matches[2][$i];
			} else {
				$caption = "Источник: " . $url;
			}


			// save photo to album
			$photos = $this->vkapi( 'photos.save', [
				'album_id'		=> $album->id
				, 'group_id'	=> - $_ENV['VK_ALBUM_OWNER_ID']
				, 'server'		=> $data->server
				, 'photos_list'	=> $data->photos_list
				, 'hash'		=> $data->hash
				, 'caption'		=> $caption
			]);


			// cleanup
			unlink( $fname);
		}
		
		
		rmdir( $folder);
	}


	/**
	 * Gets or creates a new album
	 */
	protected function getAlbum( $projectName, $description) {
		// get all albums first. 
		$albums = $this->vkapi( 'photos.getAlbums', [
			'owner_id'	=> $_ENV['VK_ALBUM_OWNER_ID']
		]);
		
		$found = false;
		
		foreach( $albums->items AS $album) {
			if( $album->title === $projectName) {
				$found = true;
				break;
			}
		}
		
		if(!$found) {
		
			unset($album);
		
			// create album
			$album = $this->vkapi( 'photos.createAlbum', [
				'title'						=> $projectName
				, 'description'				=> $description
				, 'group_id'				=> - $_ENV['VK_ALBUM_OWNER_ID']
				, 'upload_by_admins_only'	=> 1
				, 'privacy_view'			=> 'all'
				, 'privacy_comment'			=> 'all'
				, 'comments_disabled'		=> 0
			]);
		}
	
	
		return $album;
	}
	
	
	
	protected function getUploadURL( $album_id) {
		$data = $this->vkapi( 'photos.getUploadServer', [
			'album_id'		=> $album_id
			, 'group_id'	=> - $_ENV['VK_ALBUM_OWNER_ID']
		]);
		
		if( !isset( $data->upload_url)) die("Failed to get upload URL");
		
		return $data->upload_url;	
	}
	
	
	protected function getCurl($key) {
		
		switch($key) {
			case 'web':
				if( is_null( $this->chWeb)) {
					$this->chWeb = curl_init();
		
					curl_setopt_array( $this->chWeb, [
						CURLOPT_RETURNTRANSFER		=> true
						, CURLOPT_FOLLOWLOCATION	=> true
						, CURLOPT_CONNECTTIMEOUT	=> 2
						, CURLOPT_SSL_VERIFYHOST	=> 0
						, CURLOPT_SSL_VERIFYPEER	=> 0
						, CURLOPT_TIMEOUT			=> 3
						, CURLOPT_COOKIEFILE		=> self::STORAGE . '/cook.file.txt'
						, CURLOPT_COOKIEJAR			=> self::STORAGE . '/cook.jar.txt'
					 	, CURLOPT_REFERER			=> 'https://www.demilked.com/'
					 	, CURLOPT_USERAGENT			=> 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:55.0) Gecko/20100101 Firefox/55.0'
					]);
				}
				
				return $this->chWeb;
			
			case 'vk':
			default:
				if( is_null( $this->chVK)) {
					$this->chVK = curl_init();
					curl_setopt_array( $this->chVK, [
						CURLOPT_POST			=> true,
						CURLOPT_RETURNTRANSFER	=> true,
						CURLOPT_FOLLOWLOCATION	=> true,
						CURLOPT_CONNECTTIMEOUT	=> 2,
						CURLOPT_SSL_VERIFYHOST	=> 0,
						CURLOPT_SSL_VERIFYPEER	=> 0,
						CURLOPT_TIMEOUT			=> 3,
					]);
				}
				
				return $this->chVK;
		}
	}
	
	
	protected function getPageHTML( $url) {
		
		$ch = $this->getCurl('web');

		curl_setopt( $ch, CURLOPT_URL, $url);

		$html = curl_exec( $ch);
		
		$len = strlen($html);
		if( $len === 0) {
			echo "Error: " . curl_error( $ch) . PHP_EOL;
			print_r( curl_getinfo( $ch));
			exit();
		}
		
		echo "Response length: " . strlen($html) . PHP_EOL;
	
	
		return $html;
	}
	
	
	protected function vkapi( $methodName, $params) {
		
		$ch = $this->getCurl('vk');

		
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
			echo __METHOD__ . "VK error field present: " . PHP_EOL . $response;
			throw new Exception('VK Error');
		}
		
		if( !isset($data->response)) {
			echo __METHOD__ . "VK missing response: " . PHP_EOL .$response;
			throw new Exception('VK missing response');
		}
		
		return $data->response;
	}
	
	
	public function cli() {
		if( $argc < 2) exit( $this->help());
		$url = $argv[1];
		if( empty( $url)) exit( $this->help());
		$this->url = $url;
	}


	protected function help() {
		echo implode( PHP_EOL, [
			"Please pass a url to the post as an argument:"
			,"\tphp demilk.php https://www.demilked.com/cartoon-people-photos-lance-phan/"
			,""
		]);
	}

}