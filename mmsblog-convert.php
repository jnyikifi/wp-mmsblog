<html>
	<head></head>
<body>
<pre>
<?php

   /**
	* derived from wp-mail.php
	*
	* Copyright (c) 2003-2004 The Wordpress Team
	*
	* Copyright (c) 2004 - John B. Hewitt - jb@stcpl.com.au
	* Copyright (c) 2004 - Dan Cech - dcech@lansmash.com
	* Copyright (c) 2004 - Kristoffer Nyberg jny@lokala.org
	*
	* Licensed under the GNU GPL. For full terms see the file COPYING.
	*
	*
	*/

require(dirname(__FILE__) . '/wp-config.php');
require_once (dirname(__FILE__) . '/wp-content/plugins/mmsblog.php');


$time_difference = get_settings('gmt_offset');
$photosdir = 'wp-photos';
$thumbsdir = 'wp-thumbs';
$filesdir = 'wp-filez';
$tempdir = 'wp-temp';
$_CONVERT = "/usr/bin/convert";
$_THUMBPARS = "-geometry 320x320 -sharpen 2x1";
$_NORPARS = "-geometry '640x480>' -sharpen 2x1";
$datadir = "mt-mmsblog/data";


$postcnt = 0;

if ($dir = opendir($datadir)) {
	while ($file = readdir($dir)) {
		if (!preg_match("/\.dat$/", $file)) {
			continue;
		}
		print "$file\n";
		$dat_cont = file("$datadir/$file");
		$dat = parse_dat($dat_cont);
		$id = get_wp_id($dat["from"]);
		$dat["id"] = $id;
		$dest_file = preg_replace("/^mt-mmsblog\/data\//", "", $dat["file"]);
		$dat["new_file"] = preg_replace("/:/", "", get_filename($dest_file));
		$dat["new_thumb"] = preg_replace("/:/", "", get_thumbname($dest_file));
		do_file($dat);
		// print_r($dat);
		do_post($dat);
		$postcnt++;
		// if ($postcnt == 4) exit;
	}
	closedir($dir);
	debug_p("*** TOTAL $postcnt posts");
}

function do_post($dat) {
	global $photosdir;
	global $tableposts;
	global $wpdb;
	global $tablepost2cat;
	
	if (mmsblog_is_image($dat["file"])) {
		$post = mmsblog_get_picture_tag($dat["new_file"], $dat["new_thumb"]);
	} else {
		$dat["new_file"] = preg_replace("/^$photosdir\//", "", $dat["new_file"]);
		$post = mmsblog_get_video_controller_tag("$photosdir/ref.mov", $dat["new_file"]);
	}
	$post = 
	$details = array(
		'post_author'		=> $dat["id"],
		'post_date'			=> $dat["date"],
		'post_date_gmt'		=> $dat["date"],
		'post_content'		=> $post . $dat["msg"],
		'post_title'		=> $dat["subject"],
		'post_modified'		=> $dat["date"],
		'post_modified_gmt' => $dat["date"],
		'comment_status'	=> 'open'
	);
	// print_r($details);
	$sql = 'INSERT INTO ' . $tableposts . ' (' . 
		implode(',', array_keys($details)) . ') VALUES (\'' . 
		implode('\',\'', array_map('addslashes', $details)) . '\')';
	debug_p("  SQL: $sql");
	$result = $wpdb->query($sql);
	$post_ID = $wpdb->insert_id;
	debug_p("  post_ID $post_ID");

	do_action('publish_post', $post_ID);
	do_action('publish_phone', $post_ID);
	pingback($dat["msg"], $post_ID);
	$post_categories[] = get_settings('default_category');
	foreach ($post_categories as $post_category) {
		$post_category = intval($post_category);

		// Double check it's not there already
		$exists = $wpdb->get_row("SELECT * FROM $tablepost2cat WHERE post_id = $post_ID AND category_id = $post_category");
		if (!$exists && $result) { 
			$wpdb->query("
			INSERT INTO $tablepost2cat
			(post_id, category_id)
			VALUES
			($post_ID, $post_category)
			");
		}
	}
}

function do_file($dat) {
	global $photosdir;
	global $thumbsdir;
	global $filesdir;
	global $tempdir;
	global $_CONVERT;
	global $_THUMBPARS;
	global $_NORPARS;
	global $datadir;

	$do_thumb = "$_CONVERT $_THUMBPARS '" . $dat["file"] . "' '" . $dat["new_thumb"] . "'";
	$do_file = "cp '" . $dat["file"] . "' '" . $dat["new_file"] . "'";
	debug_p("  File: $do_file");
	exec($do_file);
	if (!mmsblog_is_video($dat["file"])) {
		debug_p("  Thumb: $do_thumb");
		exec($do_thumb);
	}
}

function get_wp_id($from) {
	global $tableusers;
	global $wpdb;

	$sql = 'SELECT id FROM ' . $tableusers . ' WHERE user_nickname LIKE \'%' . 
		addslashes($from) . '%\'';
	// debug_p("SQL: $sql");
	$id = $wpdb->get_var($sql);
	debug_p("  Nick: $from ID $id");
	return($id);
}

function parse_dat($dat_arr) {
	global $datadir;
	
	$ret = array();
	$ret["Msg"] = "";
	while (list($line_num, $line) = each ($dat_arr)) {
		// echo "Line $line_num: " . htmlspecialchars ($line) . "\n";
		if (preg_match("/^File: (.*)/", $line, $m)) {
			$ret["file"] = $m[1];
		} elseif (preg_match("/^From: (.*)/", $line, $m)) {
			$ret["from"] = $m[1];
		} elseif (preg_match("/^Date: (.*)/", $line, $m)) {
			$ret["date"] = $m[1];
		} elseif (preg_match("/^Subject: (.*)/", $line, $m)) {
			$ret["subject"] = $m[1];
		} elseif (preg_match("/^Thumb:/", $line)) {
		} elseif (preg_match("/^Message:/", $line)) {
		} else {
			$ret["msg"] .= $line;
		}
	}
	$ret["file"] = preg_replace("/^data/", $datadir, $ret["file"]);
	return $ret;
}

function get_ext($name) {
	$ext = "";
	preg_match('/\.([^\.]+)$/i', $name, $matches);
	if (is_array($matches) && isset($matches[0])) {
		$ext = $matches[0];
	}
	return $ext;
}

function mmsblog_is_image($name) {
	$ext = get_ext($name);
	$is_image = preg_match('/jpg|jpeg|gif|png/i', $ext);
	return $is_image;
}

function mmsblog_is_video($name) {
	$ext = get_ext($name);
	$is_video = preg_match('/mov|avi|3gp|mpg|mpeg/i', $ext);
	return $is_video;
}

function get_filename($file) {
	global $photosdir;
	return($photosdir . "/" . $file);
}

function get_thumbname($file) {
	global $thumbsdir;
	return($thumbsdir . "/" . $file);
}

function get_tempname($file) {
	global $tempdir;
	return($tempdir . "/" . $file); 
}


?>
</pre>
</body>
</html>
