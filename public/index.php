<?php
	
	$prefix = '/demilked/';
	
	$uri = $_SERVER['REQUEST_URI'];
	
	
	switch( $uri) {
		case $prefix . 'demilk':
			include('../routes/demilk.php');
			break;
		
		case $prefix:
		default:
			include('../routes/auth.php');
			
	}
