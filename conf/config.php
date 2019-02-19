<?php 

spl_autoload_register(function($nameClass) {

    $dirClass = "class";
    $filename = str_replace ("\\", "/", $dirClass . DIRECTORY_SEPARATOR . $nameClass . ".php");

    if (file_exists($filename)) {
        require_once($filename);
    }
});

define('USERNAME_ATTR', 'username');
define('MAXLINES', 50);

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host']   = 'localhost';
$config['db']['user']   = 'srmq';
$config['db']['pass']   = 'LulaLivr3';
$config['db']['dbname'] = 'iirrclouddb';


?>