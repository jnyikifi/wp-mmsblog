<html>
	<head></head>
<body>
<pre>
<?php

   /**
    * wp-mail.php
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
require_once(ABSPATH.WPINC.'/class-pop3.php');
require_once (dirname(__FILE__) . '/mimedecode.php');
require_once (dirname(__FILE__) . '/wp-content/plugins/mmsblog.php');

error_reporting(2037);

$time_difference = get_settings('gmt_offset');
$photosdir = 'wp-photos';
$thumbsdir = 'wp-thumbs';
$filesdir = 'wp-filez';
$tempdir = 'wp-temp';
$_CONVERT = "/sw/bin/convert";
$_THUMBPARS = "-geometry 320x320 -sharpen 2x1";
$_NORPARS = "-geometry '640x480>' -sharpen 2x1";

//retrieve mail
$pop3 = new POP3();

if (!$pop3->connect(get_settings('mailserver_url'), get_settings('mailserver_port'))) {
	echo "Ooops $pop3->ERROR <br />\n";
	exit;
}

$count = $pop3->login(get_settings('mailserver_login'), get_settings('mailserver_pass'));
if (0 == $count) die(__('There doesn&#8217;t seem to be any new mail.'));

for ($i=1; $i <= $count; $i++) {
	$content_type = '';
	$boundary = '';
	$bodysignal = 0;
	
	print "Message $i of $count\n";
	
	$input = implode('', $pop3->get($i));

	if(!$pop3->delete($i)) {
		echo 'Oops ' . $pop3->ERROR . "\n";
		$pop3->reset();
		exit;
	} else {
		echo "Mission complete, message $i deleted.\n";
	}
	
	//decode the mime
	$params['include_bodies'] = true;
	$params['decode_bodies'] = true;
	$params['decode_headers'] = true;
	$params['input'] = $input;
	$structure = Mail_mimeDecode::decode($params);
	print_r($structure->headers);

    if (preg_match('/utf-8/i', $structure->headers['content-type']) ||
        preg_match('/Subject: .*=\?UTF-8\?/i', $input)) {
        print "UTF8 subject\n";
		$subject = $structure->headers['subject'];
	} else {
		$subject = utf8_encode($structure->headers['subject']);
	}
	if (!$subject) {
	   $subject = "MMS";
    }

	$ddate = trim($structure->headers['date']);
	$from = trim($structure->headers['from']);
	if (preg_match('/^[^<>]+<([^<>]+)>$/', $from, $matches)) {
		$from = $matches[1];
	}
	$content = get_content($structure);
	
	//date reformating 
	$post_date = date('Y-m-d H:i:s', time($ddate));
	$post_date_gmt = gmdate('Y-m-d H:i:s', time($ddate) );
	
	//filter content
	$search = array(
		'/ (\n|\r\n|\r)/',
		'/(\n|\r\n|\r)/'
	);
	
	$replace = array(
		' ',
		"\n"
	);
	
	// strip extra line breaks
	$content = preg_replace($search, $replace, trim($content));
	
	//try and determine category
	if (preg_match('/.*\[(.+)\](.+)/', $subject, $matches)) {
		$post_categories[0] = $matches[1];
		$subject = $matches[2];
	}
	
	if (empty($post_categories))
		$post_categories[] = get_settings('default_category');
	
	// report
	print "\n  Mail Format: " . $mailformat . "\n";
	print '  From: ' . $from . "\n";
	print '  Date: ' . $post_date . "\n";
	// print '  Date GMT: ' . $post_date_gmt . "\n";
	print '  Category: ' . $post_categories[0] . "\n";
	print '  Subject: ' . $subject . "\n";
	// print '  Posted content:' . $content . "\n";

	// First check the table of email aliases
	$sql = 'SELECT wp_email FROM mmsblog_alias WHERE email=\'' . addslashes($from) . '\'';
	$wp_email = $wpdb->get_var($sql);
	if ($wp_email) {
	   debug_p("  Email from $from corresponds to $wp_email");
	   $from = $wp_email;
    }
	
	$sql = 'SELECT id FROM ' . $tableusers . ' WHERE user_email=\'' . addslashes($from) . '\'';
	if (!$poster = $wpdb->get_var($sql)) {
		echo 'invalid sender: ' . htmlentities($from) . "\n";
		continue;
	}
	
	$details = array(
		'post_author'		=> $poster,
		'post_date'			=> $post_date,
		'post_date_gmt'		=> $post_date_gmt,
		'post_content'		=> $content,
		'post_title'		=> $subject,
		'post_modified'		=> $post_date,
		'post_modified_gmt'	=> $post_date_gmt
	);
	
	//generate sql	
	$sql = 'INSERT INTO ' . $tableposts . ' (' . implode(',', array_keys($details)) . ') VALUES (\'' .  implode('\',\'', array_map('addslashes', $details)) . '\')';

	$result = $wpdb->query($sql);
	$post_ID = $wpdb->insert_id;
	debug_p("post_ID $post_ID");

	do_action('publish_post', $post_ID);
	do_action('publish_phone', $post_ID);
	pingback($content, $post_ID);
	
	foreach ($post_categories as $post_category) {
		$post_category = intval($post_category);

		// Double check it's not there already
		$exists = $wpdb->get_row("SELECT * FROM $tablepost2cat WHERE post_id = $post_ID AND category_id = $post_category");
		debug_p("exists = $exists");

		 if (!$exists && $result) { 
			$wpdb->query("
			INSERT INTO $tablepost2cat
			(post_id, category_id)
			VALUES
			($post_ID, $post_category)
			");
		}
	}
} // end looping over messages

$pop3->quit();

function get_image($part) {
	global $_CONVERT;
	global $_THUMBPARS;
	global $_NORPARS;

	$random = rand();
	$attname = get_attachment_name($part);
	$filename = get_tempname($random . '-' . $attname);
	$normalname = get_filename($random . '-' . $attname);
	$normalname = preg_replace('/ |\_/', '', $normalname);
	$thumbname = get_thumbname($random . '-' . $attname);
	$thumbname = preg_replace('/ |\_/', '', $thumbname);
	write_file($filename, $part);
	exec("$_CONVERT $_NORPARS '$filename' '$normalname'", $res);
	print_r($res);
	exec("$_CONVERT $_THUMBPARS '$filename' '$thumbname'", $res);
	print_r($res);
	unlink("'$filename'");
	$ret = mmsblog_get_picture_tag($normalname, $thumbname);
	return $ret;
}

function get_video($part) {
	global $photosdir;

	$random = rand();
	$attname = get_attachment_name($part);
	$basename = $random . '-' . $attname;
	$basename = preg_replace('/ |\_/', '', $basename);
	$filename = get_filename($basename);
	$filename = preg_replace('/ |\_/', '', $filename);
	write_file($filename, $part);
	$ret = mmsblog_get_video_controller_tag("$photosdir/ref.mov", $basename);
	return $ret;
}

function get_text($part) {
	$ret = "";
	//dump the enriched stuff
	if ($part->ctype_secondary == 'enriched') {
	} else {
		if (preg_match('/8859/', $part->ctype_parameters['charset'])) {
			$ret = utf8_encode($part->body) ."\n";
		} else {
			$ret = $part->body ."\n";
		}
	}
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

function get_attachment_name($part) {
	$name = "";
	if (is_array($part->ctype_parameters) && isset($part->ctype_parameters['name'])) {
		$name = $part->ctype_parameters['name'];
	} elseif (is_array($part->d_parameters) && isset($part->d_parameters['filename'])) {
		$name = $part->d_parameters['filename'];
    } elseif (isset($part->headers['content-location'])) {
		$name = $part->headers['content-location'];
	} else {
		debug_p("Bad: could not get attachment name");
		print_r($part->headers);
	}
	return $name;
}

//tear apart the meta part for useful information
function get_content ($part) {
	
	switch ($part->ctype_primary) {
		case 'multipart':
			$meta_return = '';
			foreach ($part->parts as $section) {
				$meta_return .= get_content($section);
			}
			break;
		case 'text':
			$meta_return = get_text($part);
			debug_part($part);
			debug_p("posting $meta_return");
			break;
		case 'image':
			$meta_return = get_image($part);
			debug_part($part);
			debug_p("posting $meta_return");
			break;
		case 'video':
			$meta_return = get_video($part);
			debug_part($part);
			debug_p("posting $meta_return");
			break;
		case 'application':
			// try to figure out the type from the filename
			$name = get_attachment_name($part);
			if (mmsblog_is_image($name)) {
				$meta_return = get_image($part);
			} elseif (mmsblog_is_video($name)) {
				$meta_return = get_video($part);
			} else {
				$meta_return = "";
			}
			debug_part($part);
			debug_p("posting $meta_return");
			break;
	}
	return $meta_return;
}

function debug_part($part) {
	$part->body = "";
	print_r($part);
}

function write_file($filename, $part) {
	$fp = fopen($filename, 'w');
	fwrite($fp, $part->body);
	fclose($fp);
}
?>
</pre>
</body>
</html>