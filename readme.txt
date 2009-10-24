=== Traffic Limiter ===
Contributors: fabifott
Donate link: http://fabi.me/donate/
Tags: trafficlimiter, traffic, download, downloads, attachment, attachments, media, bandwidth, bitrate, modrewrite, mod_rewrite, images, files, file
Requires at least: 2.2.0
Tested up to: 2.8.5
Stable tag: 0.1.1

Limits traffic and download bandwidth of media files. Additionally this plugin includes a download statistic system.

== Description ==

Traffic Limiter is a useful addon for blogs with many media files which can cause high data traffic. 
There are three traffic limits to avoid over traffic costs: daily, weekly and monthly limit. If one of these limits is exceeded downloads will either be redirected to a custom URL or a custom message is displayed.
For images you can define a fallback icon which is displayed instead of the message.
Additionally a download bandwidth can be set for registered users and guests.

Also included is a statistic system which displays the current traffic consumption and individual file stats to see which media produces the most traffic.

All files are sent to the browser by a PHP script, supporting the most HTTP headers like `If-Modified-Since` (for caching) and `Range` (for partial downloads)

If the Apache extension `mod_rewrite` is installed download links stay the same, if not links to media files are automatically redirected.
It is recommended to install `mod_rewrite`, although this plugin works without this extension.

**Note:** If you provide lots of downloads or media files you should try my download manager [WP-Filebase](http://wordpress.org/extend/plugins/wp-filebase/ "WP-Filebase").

== Installation ==

1. Upload the `traffic-limiter` folder with all its files to `wp-content/plugins`
2. Make sure that your upload directory (default is `wp-content/uploads`) is writable by the server
2. Activate the Plugin and customize the settings under `Media->Traffic Limiter`

== Screenshots ==

1. An overview about the traffic amount and the limits
2. The files which causes the most traffic are listed here

== Changelog ==

= 0.1.1 =
* New Option *Force download* (to avoid media streaming)
* Fixed mod_rewrite rule bug
* Enhanced download code

= 0.1.0 =
* First version