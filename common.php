<?php
require_once 'vendor/autoload.php';

$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();


define('HOME', __DIR__);
define('DPUBLIC', HOME . '/public');
define('STORAGE', HOME . '/storage');
