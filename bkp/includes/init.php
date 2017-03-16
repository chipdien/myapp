<?php
require('config.php');
require('medoo.php');

session_start();


require __DIR__ . '/../vendor/autoload.php';
//require_once("../libs/phpFastCache/phpFastCache.php");
date_default_timezone_set("Asia/Ho_Chi_Minh");

use phpFastCache\CacheManager;
use phpFastCache\Core\phpFastCache;

CacheManager::setDefaultConfig([
    "path" => sys_get_temp_dir(),
]);
$cache = CacheManager::getInstance('files');