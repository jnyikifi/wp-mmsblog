<?php

   /**
    * wp-mail.php
    *
    * Copyright (c) 2003-2004 The Wordpress Team
    *
    * Copyright (c) 2004 - John B. Hewitt - jb@stcpl.com.au
    * Copyright (c) 2004 - Dan Cech - dcech@lansmash.com
    *
    * Licensed under the GNU GPL. For full terms see the file COPYING.
    *
    *
    */
	
require(dirname(__FILE__) . '/wp-config.php');

require_once(ABSPATH.WPINC.'/class-pop3.php');

require_once (dirname(__FILE__) . '/mimedecode.php');

error_reporting(2037);

$time_difference = get_settings('gmt_offset');

//retrieve mail
$pop3 = new POP3();

if (!$pop3->connect(get_settings('mailserver_url'), get_settings('mailserver_port'))) :
	echo "Ooops $pop3->ERROR <br />\n";
	exit;
endif;

$count = $pop3->login(get_settings('mailserver_login'), get_settings('mailserver_pass'));
if (0 == $count) die(__('There doesn&#8217;t seem to be any new mail.'));

for ($i=1; $i <= $count; $i++)
{
	//variables
	$content_type = '';
	$boundary = '';
	$bodysignal = 0;
	
	$input = implode ('',$pop3->get($i));
	
	
	if(!$pop3->delete($i)) {
		echo '<p>Oops '.$pop3->ERROR.'</p></div>';
		$pop3->reset();
		exit;
	} else {
		echo "<p>Mission complete, message <strong>$i</strong> deleted.</p>";
	}
	
	//decode the mime
	$params['include_bodies'] = true;
	$params['decode_bodies'] = true;
	$params['decode_headers'] = true;
	$params['input'] = $input;
	$structure = Mail_mimeDecode::decode($params);
	
	$subject = trim($structure->headers['subject']);
	$ddate = trim($structure->headers['date']);
	$from = trim($structure->headers['from']);
	if (preg_match('/^[^<>]+<([^<>]+)>$/',$from,$matches))
	{
		$from = $matches[1];
	}
	print_r ($structure);
	$content = get_content($structure);
	
	//date reformating 
	$post_date = date('Y-m-d H:i:s', time($ddate) + ($time_difference * 3600));
	$post_date_gmt = gmdate('Y-m-d H:i:s', time($ddate) );
	
	//filter content
	$search = array (
		'/ (\n|\r\n|\r)/',
		'/(\n|\r\n|\r)/'
	);
	
	$replace = array (
		' ',
		"\n"
	);
	
	// strip extra line breaks
	$content = preg_replace($search,$replace,trim($content));
	
	//try and determine category
	if ( preg_match('/.*\[(.+)\](.+)/', $subject, $matches) )
	{
		$post_categories[0] = $matches[1];
		$subject = $matches[2];
	}
	
	if (empty($post_categories))
		$post_categories[] = get_settings('default_category');
	
	//report
	// print '<p><b>Mail Format</b>: ' . $mailformat . '</p>' . "\n";
	print '<p><b>From</b>: ' . $from . '<br />' . "\n";
	print '<b>Date</b>: ' . $post_date . '<br />' . "\n";
	print '<b>Date GMT</b>: ' . $post_date_gmt . '<br />' . "\n";
	print '<b>Category</b>: ' . $post_categories[0] . '<br />' . "\n";
	print '<b>Subject</b>: ' . $subject . '<br />' . "\n";
	print '<b>Posted content:</b></p><hr />' . $content . '<hr />';
	
	$sql = 'SELECT id FROM '.$tableusers.' WHERE user_email=\'' . addslashes($from) . '\'';
	if (!$poster = $wpdb->get_var($sql))
	{
		echo 'invalid sender: ' . htmlentities($from) . "<br />\n";
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
	$sql = 'INSERT INTO '.$tableposts.' ('. implode(',',array_keys($details)) .') VALUES (\''. implode('\',\'',array_map('addslashes',$details)) . '\')';

	$result = $wpdb->query($sql);
	$post_ID = $wpdb->insert_id;

	do_action('publish_post', $post_ID);
	do_action('publish_phone', $post_ID);
	pingback($content, $post_ID);

	foreach ($post_categories as $post_category)
	{
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
} // end looping over messages

$pop3->quit();

/** FUNCTIONS **/

//tear apart the meta part for useful information
function get_content ($part) 
{
	//global $photosdir;
	$photosdir = 'wp-photos/';
	$filesdir = 'wp-filez/';
	
	switch ($part->ctype_primary)
	{
		case 'multipart':
			$meta_return = '';
			foreach ($part->parts as $section)
			{
				$meta_return .= get_content($section);
			}
			break;
		case 'text':
			//dump the enriched stuff
			if ($part->ctype_secondary=='enriched') {
				
			} else {
				$meta_return = htmlentities($part->body) ."\n";
			}
			break;

		case 'image':
			$filename = $photosdir . rand() . '.' . $part->ctype_secondary;
			$fp = fopen($filename, 'w');
			fwrite($fp, $part->body);
			fclose($fp);
			$meta_return = '<img src="' . $filename . '" alt="' . $part->ctype_parameters['name'] . '"/>' . "\n";
			break;
		case 'application':
			//pgp signature
			if ( $part->ctype_secondary == 'pgp-signature' ) {break;}
			//other attachments
			$filename = $filesdir . $part->ctype_parameters['name'];
			$fp = fopen($filename, 'w');
			fwrite($fp, $part->body);
			fclose($fp);
			$meta_return = '<a href="' . $filename . '">' . $part->ctype_parameters['name'] . '</a>' . "\n";
			break;
	}		
	return $meta_return;
}

// end of script
