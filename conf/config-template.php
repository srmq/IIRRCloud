<?php 

define('USERNAME_ATTR', 'username');
define('MAXLINES', 50);
define('BUFSIZE', 1024);


$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host']   = 'localhost';
$config['db']['user']   = 'srmq';
$config['db']['pass']   = getenv('IIRRCDBPASS');
$config['db']['dbname'] = 'iirrclouddb';


?>