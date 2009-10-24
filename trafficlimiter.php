<?php
/*
Plugin Name: Traffic Limiter
Plugin URI: http://fabi.me/wordpress-plugins/traffic-limiter/
Description: Limits media traffic and download bandwidth
Author: Fabian Schlieper
Version: 0.1.1
Author URI: http://fabi.me/
*/

define('TRAFFICLIMITER_PLUGIN_NAME', 'Traffic Limiter');
define('TRAFFICLIMITER', 'trafficlimiter');
define('TRAFFICLIMITER_PLUGIN_DIR', dirname(__FILE__));
define('TRAFFICLIMITER_PLUGIN_URL', str_replace(ABSPATH, get_option('siteurl') . '/', TRAFFICLIMITER_PLUGIN_DIR));

static $trafficlimiter_time_units = array('total' => '', 'year' => 'Y', 'month' => 'm', 'week' => 'W', 'day' => 'z');

if(isset($wpdb))
	$wpdb->trafficlimiter_stats = $wpdb->prefix . 'trafficlimiter_stats';

register_activation_hook(__FILE__, 'trafficlimiter_activate');

add_action('admin_menu',		'trafficlimiter_admin_menu');
add_action('admin_head',		'trafficlimiter_admin_head', 10);

if(!trafficlimiter_got_mod_rewrite())
{
	// no mod_rewrite, use url filter method_exists
	add_filter('the_content', 'trafficlimiter_link_filter', 50);
	add_filter('the_excerpt', 'trafficlimiter_link_filter', 50);
	add_filter('attachment_link', 'trafficlimiter_link_filter', 50);
	add_filter('attachment_innerHTML', 'trafficlimiter_link_filter', 50);	 
	add_filter('wp_get_attachment_url', 'trafficlimiter_link_filter', 50, 2);
}
	
add_action('template_redirect',	'trafficlimiter_redirect');

function trafficlimiter_activate()
{
	require_once(TRAFFICLIMITER_PLUGIN_DIR . '/admin.php');	
	trafficlimiter_add_options();
	trafficlimiter_create_stats_table();
}

function trafficlimiter_admin_menu()
{
	require_once(TRAFFICLIMITER_PLUGIN_DIR . '/admin.php');	
	add_submenu_page('upload.php', TRAFFICLIMITER_PLUGIN_NAME, TRAFFICLIMITER_PLUGIN_NAME, 'upload_files', 'trafficlimiter', 'trafficlimiter_admin_page');
}

function trafficlimiter_admin_head()
{
	echo "\n".'<link rel="stylesheet" type="text/css" href="' . TRAFFICLIMITER_PLUGIN_URL . '/admin.css" />' . "\n";
}

function trafficlimiter_get_opt($name)
{
/*
	$pos = strpos($name, 'trafficlimiter_');
	if($pos !== false && $pos == 0)
		$name = substr($name, $pos + 15);
	*/
	$opts = get_option(TRAFFICLIMITER);
	return $opts[$name];
}

function trafficlimiter_set_opt($name, $value)
{
	$opts = get_option(TRAFFICLIMITER);
	$opts[$name] = $value;
	update_option(TRAFFICLIMITER, $opts);
}

function trafficlimiter_get_traffic($file_path=null)
{
	global $trafficlimiter_time_units;
	
	// get the stats
	if(is_null($file_path))
		$traffic =  trafficlimiter_get_opt('traffic_stats');
	else {
		global $wpdb;
		$file_path = str_replace(ABSPATH, '', $file_path);
		$fdat = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->trafficlimiter_stats WHERE file_path = %s", $file_path));
		if(!is_null($fdat))
			$traffic = array('time' => mysql2date('U', $fdat->last_download), 'total' => $fdat->traffic_total, 'year' => $fdat->traffic_year, 'month' => $fdat->traffic_month, 'week' => $fdat->traffic_week, 'day' => $fdat->traffic_day); 
		else
			return null;
	}
	
	$stat_time = intval($traffic['time']);		
	$exp = false;
	foreach($trafficlimiter_time_units as $tu => $fc)
	{
		if(!empty($fc) && ($exp || intval(date($fc, $stat_time)) != intval(date($fc))))
		{
			$traffic[$tu] = 0;
			$exp = true;
		}
	}
		
	return $traffic;
}


function trafficlimiter_got_mod_rewrite()
{
	return apache_mod_loaded('mod_rewrite', true);
}

function trafficlimiter_link_filter($content, $dummy=null)
{
	global $trafficlimiter_link_filter_init, $trafficlimiter_upload_url, $trafficlimiter_upload_url_repl;
	
	if(!isset($trafficlimiter_link_filter_init))
	{
		$trafficlimiter_upload_url = str_replace(ABSPATH, get_option('siteurl') . '/', get_option('upload_path')) . '/';
		$trafficlimiter_upload_url_repl = get_option('siteurl') . '/?getmedia=';
		$trafficlimiter_link_filter_init = true;
	}
	
	$content = str_replace($trafficlimiter_upload_url, $trafficlimiter_upload_url_repl, $content);
	
	return $content;
}

function trafficlimiter_redirect()
{
	if(!empty($_GET['getmedia']))
	{
		$file = get_option('upload_path') . '/' . trim(str_replace('../', '/', $_GET['getmedia']), '/');
		require_once(TRAFFICLIMITER_PLUGIN_DIR . '/download.php');
		trafficlimiter_download($file);
	}
}


?>