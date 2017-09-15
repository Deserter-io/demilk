<?php
require_once '../common.php';
require_once '../classes/demilk.php';


$url = filter_input(INPUT_POST, 'url');
if( empty($url)) {

	echo file_get_contents(HOME . '/routes/form.html');
	
} else {

	$Demilk = new Demilk();
	$Demilk->run( $url );	

}




