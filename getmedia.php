<?php
require_once('../../../wp-config.php');
require_once(TRAFFICLIMITER_PLUGIN_DIR . '/download.php');

$site_url = get_option('siteurl');
$url = substr($site_url, 0, strpos($site_url, '://'))  . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
$file = str_replace($site_url . '/', ABSPATH, $url);
$file = str_replace('../', '/', $file);

trafficlimiter_download($file);
exit;
?>