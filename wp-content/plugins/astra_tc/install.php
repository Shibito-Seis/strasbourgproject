<?php

opcache_reset();

error_reporting(E_ALL); ini_set('display_errors', 1);
//print_r($_SERVER);

$flag_register = __DIR__ . '/cz-flag-register.txt';

if(file_exists($flag_register)){
    die("Astra already installed");
}

include(__DIR__ . '/PP_setup.php');

$setup = new PP_setup();

$register = $setup->register();

var_dump($register);