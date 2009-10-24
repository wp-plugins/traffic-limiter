<?php

define('TRAFFICLIMITER_HTACCESS_ID', TRAFFICLIMITER . '_1');

function trafficlimiter_add_options()
{
	$options = array( 
		'traffic_day'		=> 536870912, // 0.5 GiB
		'traffic_week'		=> 3221225472, // 3 GiB
		'traffic_month'		=> 10737418240, // 10 GiB
		
		'bandwidth_users'	=> 0,
		'bandwidth_guests'	=> 0,
		
		'traffic_exceeded_msg'	=> 'Traffic limit exceeded. Please try again later.',
		'traffic_exceeded_image' => (TRAFFICLIMITER_PLUGIN_URL . '/trafficexceeded.png')
		);
		
	add_option(TRAFFICLIMITER, $options);
}

function trafficlimiter_create_stats_table()
{
	global $wpdb;
	
	return $wpdb->query("CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . 'trafficlimiter_stats' . "` (
	  `id` bigint(20) unsigned NOT NULL auto_increment,
	  `file_path` varchar(255) NOT NULL,
	  `hits` bigint(20) NOT NULL,
	  `last_download` datetime NOT NULL,
	  `traffic_total` bigint(20) NOT NULL,
	  `traffic_year` bigint(20) NOT NULL,
	  `traffic_month` bigint(20) NOT NULL,
	  `traffic_week` bigint(20) NOT NULL,
	  `traffic_day` bigint(20) NOT NULL,
	  PRIMARY KEY  (`id`),
	  UNIQUE KEY `file_path` (`file_path`)
	) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;");
}

function trafficlimiter_format_size($file_size)
{
	if($file_size < 1024) {
		$unit = 'B';
	} elseif($file_size < 1048576) {
		$file_size /= 1024;
		$unit = 'KiB';
	} elseif($file_size < 1073741824) {
		$file_size /= 1048576;
		$unit = 'MiB';
	} elseif($file_size < 1099511627776) {
		$file_size /= 1073741824;
		$unit = 'GiB';
	} else {
		$file_size /= 1099511627776;
		$unit = 'TiB';
	}
	
	return sprintf('%01.1f %s', $file_size, $unit);
}

function trafficlimiter_progress_bar($progress, $label)
{
	$progress = round(100 * $progress);
	echo "<div class='trafficlimiter-progress'><div class='progress'><div class='bar' style='width: $progress%'></div></div><div class='label'><strong>$progress %</strong> ($label)</div></div>";
}

function trafficlimiter_size_input($name)
{
	static $traffic_units = array('B' => '1', 'KiB' => 1024, 'MiB' => 1048576, 'GiB' => 1073741824, 'TiB' => 1099511627776);
	
	$data = explode(' ', trafficlimiter_format_size(trafficlimiter_get_opt($name)));
	$value = $data[0];
	$unit = $data[1];	
	?>
	<input name="trafficlimiter_<?php echo $name ?>" type="text" id="trafficlimiter_<?php echo $name ?>" value="<?php echo $value ?>" class="small-text" />
	<select name="trafficlimiter_<?php echo $name ?>_unit" id="trafficlimiter_<?php echo $name ?>_unit">
		<?php foreach($traffic_units as $u_name => $u_bytes) {
			$sel = ($u_name == $unit) ? " selected='selected'" : '';
			echo "<option value='$u_bytes'$sel>$u_name</option>";
		} ?>
	</select> (<?php _e('0 =&gt; unlimited') ?>)
	<?php
}

function trafficlimiter_size_post_value($name) {
	$name = 'trafficlimiter_' . $name;
	return round(floatval($_POST[$name]) * floatval($_POST[$name.'_unit']));
}

function trafficlimiter_admin_page() {
	?>
	<div class="wrap">
		<?php trafficlimiter_admin_check_upload_path() ?>
		<?php trafficlimiter_admin_traffic() ?>
		<?php trafficlimiter_admin_filelist() ?>
		<?php trafficlimiter_admin_options() ?>
	</div><?php
}

function trafficlimiter_admin_check_upload_path()
{
	$upload_path = get_option('upload_path');
	$upload_dir = str_replace(ABSPATH, '', $upload_path);
	
	$errors = array();
	
	if(!is_dir($upload_path) && (!@mkdir($upload_path, 0777) || !@chmod($upload_path, 0777)))
		$errors[] = sprintf(__('The upload path <code>%s</code> does not exists. Please create it and make it writable for PHP'), $upload_dir, 'CHMOD 777 ' . $upload_dir);
	elseif(!is_writable($upload_path) && !@chmod($upload_path, 0777))
		$errors[] = sprintf(__('The upload path <code>%s</code> is not writable. You can fix this by executing the following FTP command: <code>%s</code>'), $upload_dir, 'CHMOD 777 ' . $upload_dir);
	elseif(!trafficlimiter_htaccess_ok() && !trafficlimiter_htaccess_create())
		$errors[] = sprintf(__('Could not create htaccess file <code>%s</code>!'), get_option('upload_path') . '/.htaccess');
	
	if(count($errors) > 0) { ?>
		<div class="error default-password-nag"><ul><?php foreach($errors as $err) { echo '<li>' . $err . '</li>'; } ?></ul></div>
	<?php }
}

function trafficlimiter_admin_traffic()
{
	global $trafficlimiter_time_units;
?>
<h2><?php _e('Traffic'); ?></h2>
<table class="form-table">
<?php
	$time_units_titles = array('total' => 'Total', 'year' => 'This year', 'month' => 'This month', 'week' => 'This week', 'day' => 'Today');
	
	$traffic_stats = trafficlimiter_get_traffic();
	
	foreach($trafficlimiter_time_units as $tu => $fc)
	{
		$limit = 1 * trafficlimiter_get_opt('traffic_' . $tu);
	?><tr>
		<th scope="row" style="width: 80px;"><?php _e($time_units_titles[$tu]); ?></th>
		<td><?php
			if($limit > 0)
				trafficlimiter_progress_bar($traffic_stats[$tu] / $limit, trafficlimiter_format_size($traffic_stats[$tu]) . '/' . trafficlimiter_format_size($limit));
			else
				echo trafficlimiter_format_size($traffic_stats[$tu]);
		?></td>
	</tr>
	<?php }?>
</table>
<?php
}

function trafficlimiter_admin_filelist()
{
	global $trafficlimiter_time_units, $wpdb;
	
	$results = $wpdb->get_results("SELECT * FROM $wpdb->trafficlimiter_stats ORDER BY traffic_day DESC, traffic_week DESC, traffic_month DESC, traffic_year DESC, traffic_total DESC LIMIT 10");	
	if(empty($results) || count($results) == 0)
		return;
	
?>
<h2><?php _e('Top Traffic Files'); ?></h2>
<table class="widefat">
	<thead>
	<tr>
		<th scope="col"><?php _e('File') ?></th>
		<th scope="col"><?php _e('Hits') ?></th>
		<th scope="col" colspan="5"><div class="column-parent"><?php _e('Traffic') ?></div></th>
		<th scope="col"><?php _e('Last Download') ?></th>
	</tr>
	<tr>
		<td></td>
		<td></td>
		<td><?php _e('Today') ?></td>
		<td><?php _e('This week') ?></td>
		<td><?php _e('This month') ?></td>
		<td><?php _e('This year') ?></td>
		<td><?php _e('Total') ?></td>
		<td></td>
	</tr>
	</thead>
	<tbody id="the-list" class="list:cat">
	<?php foreach($results as $f) { ?>
	<tr id='file-<?php echo $fid ?>'>
		<th scope='row'><?php echo wp_specialchars(str_replace(str_replace(ABSPATH, '', get_option('upload_path')), '', $f->file_path)) ?></th>
		<td class='num'><?php echo $f->hits ?></td>	
		<td class='num'><?php echo trafficlimiter_format_size($f->traffic_day) ?></td>	
		<td class='num'><?php echo trafficlimiter_format_size($f->traffic_week) ?></td>	
		<td class='num'><?php echo trafficlimiter_format_size($f->traffic_month) ?></td>	
		<td class='num'><?php echo trafficlimiter_format_size($f->traffic_year) ?></td>	
		<td class='num'><?php echo trafficlimiter_format_size($f->traffic_total) ?></td>		
		<td><?php echo mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $f->last_download) ?></td>
	</tr>
	<?php } ?>
	</tbody>
</table>
<?php
}

function trafficlimiter_admin_options()
{
	static $traffic_limit_options = array('day' => 'Daily traffic limit', 'week' => 'Weekly traffic limit', 'month' => 'Monthly traffic limit');
		
	if(isset($_POST['action']) && $_POST['action'] == 'trafficlimiter_update_options')
	{
		if(isset($_POST['reset']))
		{
			delete_option(TRAFFICLIMITER);
			trafficlimiter_add_options();
		} else {			
			$options = get_option(TRAFFICLIMITER);
			foreach($traffic_limit_options as $opt_tag => $opt_desc)
			{
				$opt_name = 'traffic_' .  $opt_tag;
				$options[$opt_name] = trafficlimiter_size_post_value($opt_name);
			}			
			$options['bandwidth_users'] = floatval($_POST['trafficlimiter_bandwidth_users']);
			$options['bandwidth_guests'] = floatval($_POST['trafficlimiter_bandwidth_guests']);
			$options['force_download'] = empty($_POST['trafficlimiter_force_download']) ? '0' : '1';
			update_option(TRAFFICLIMITER, $options);
		}
		
		trafficlimiter_htaccess_create();
	}
?>
<h2>Traffic Limiter Options</h2>	
<form method="post" action="">
<input type="hidden" name="action" value="trafficlimiter_update_options" />
<table class="form-table">

<?php foreach($traffic_limit_options as $opt_tag => $opt_desc) { ?>
<tr valign="top">
	<th scope="row"><label for="trafficlimiter_traffic_<?php echo $opt_tag ?>"><?php _e($opt_desc, TRAFFICLIMITER) ?></label></th>
	<td><?php trafficlimiter_size_input('traffic_' . $opt_tag) ?></td>
</tr><?php } ?>

<tr valign="top">
	<th scope="row"><label for="trafficlimiter_bandwidth_users"><?php _e('Download bandwidth for registered users', TRAFFICLIMITER) ?></label></th>
	<td><input name="trafficlimiter_bandwidth_users" type="text" id="trafficlimiter_bandwidth_users" value="<?php echo intval(trafficlimiter_get_opt('bandwidth_users')) ?>" class="small-text" /> KiB / s (<?php _e('0 =&gt; unlimited') ?>)</td>
</tr>
<tr valign="top">
	<th scope="row"><label for="trafficlimiter_bandwidth_guests"><?php _e('Download bandwidth for guests', TRAFFICLIMITER) ?></label></th>
	<td><input name="trafficlimiter_bandwidth_guests" type="text" id="trafficlimiter_bandwidth_guests" value="<?php echo intval(trafficlimiter_get_opt('bandwidth_guests')) ?>" class="small-text" /> KiB / s (<?php _e('0 =&gt; unlimited') ?>)</td>
</tr>
<tr valign="top">
	<th scope="row"><label for="trafficlimiter_force_download"><?php _e('Force download of media files (no streaming)', TRAFFICLIMITER) ?></label></th>
	<td><input name="trafficlimiter_force_download" type="checkbox" id="trafficlimiter_force_download" value="1" <?php checked(trafficlimiter_get_opt('force_download'), '1') ?> /></td>
</tr>
<tr valign="top">
	<th scope="row"><label for="trafficlimiter_traffic_exceeded_msg"><?php _e('Message shown if traffic limit is exceeded or URL to redirect', TRAFFICLIMITER) ?></label></th>
	<td><input name="trafficlimiter_traffic_exceeded_msg" type="test" id="trafficlimiter_traffic_exceeded_msg" value="<?php echo attribute_escape(trafficlimiter_get_opt('traffic_exceeded_msg')) ?>" class="regular-text" /></td>
</tr>
<tr valign="top">
	<th scope="row"><label for="trafficlimiter_traffic_exceeded_image"><?php _e('Fallback image if traffic limit is exceeded (should be small)', TRAFFICLIMITER) ?></label></th>
	<td>
		<input name="trafficlimiter_traffic_exceeded_image" type="test" id="trafficlimiter_traffic_exceeded_image" value="<?php echo attribute_escape(trafficlimiter_get_opt('traffic_exceeded_image')) ?>" class="regular-text" /><br />
		<image src="<?php echo attribute_escape(trafficlimiter_get_opt('traffic_exceeded_image')) ?>" alt="<?php echo attribute_escape(trafficlimiter_get_opt('traffic_exceeded_image')) ?>" />
	</td>
</tr>
</table>
<p class="submit"><input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes'); ?>" /><input type="submit" name="reset" class="button-secondary" value="<?php _e('Reset'); ?>" /></p>
</form>

<?php
}

function trafficlimiter_htaccess_create()
{
	$htaccess = get_option('upload_path') . '/.htaccess';
	
	if( !($fp = @fopen($htaccess, 'w')) )
		return false;

	fwrite($fp, "#" . TRAFFICLIMITER_HTACCESS_ID . "\n");	
	
	$siteurl = parse_url(get_option('siteurl'));
	$site_root = trailingslashit(isset($siteurl['path']) ? $siteurl['path'] : '');
			
	$upload_root = trailingslashit($site_root . str_replace(ABSPATH, '', get_option('upload_path')));
	$download_script = $site_root . str_replace(ABSPATH, '', TRAFFICLIMITER_PLUGIN_DIR) . '/getmedia.php';
					
	$rules = "<IfModule mod_rewrite.c>\n";
	$rules .= " RewriteEngine On\n";
	$rules .= " RewriteBase $upload_root\n";
	$rules .= " RewriteRule . $download_script [L]\n";
	$rules .= "</IfModule>\n";
	
	$rules .= "<IfModule !mod_rewrite.c>\n";
	$rules .= "Order deny,allow\n";
	$rules .= "Deny from all\n";
	$rules .= "</IfModule>\n";
	
	fwrite($fp, $rules);
	
	@fclose($fp);

	return trafficlimiter_htaccess_ok();
}

function trafficlimiter_htaccess_ok()
{
	$htaccess = get_option('upload_path') . '/.htaccess';
	
	if(!is_file($htaccess))
		return false;
		
	$fp = @fopen($htaccess, 'r');
	$ok = (trim(@fgets($fp)) == ("#" . TRAFFICLIMITER_HTACCESS_ID));
	@fclose($fp);
	
	return $ok;
}

?>