<?php
/*
Plugin Name: MMSBlog
Plugin URI: http://lokala.org/
Version: 0.1
Description: Small plugin for handling MMSBlog posts
Author: Kristoffer Nyberg
Author URI: http://lokala.org/
*/

add_filter('the_content', 'mmsblog_tags');

function mmsblog_tags($content) {
    $ret = preg_replace('/@@mmsblogPic (\S+) (\S+)@@/', 
            mmsblog_get_picture('\\1', '\\2'), 
            $content);
    $ret = preg_replace('/@@mmsblogVideo (\S+) (\S+)@@/', 
            mmsblog_get_video_controller('\\1', '\\2', '256'), 
            $ret);
    $ret = preg_replace('/@@mmsblogVideo (\S+) (\S+) (\S+)@@/', 
            mmsblog_get_video_controller('\\1', '\\2', '\\3'), 
            $ret);
    return $ret;
    
}

function mmsblog_get_picture_tag($file, $thumb) {
    $ret = "@@mmsblogPic $file $thumb@@ ";
    return $ret;
}

function mmsblog_get_picture($file, $thumb) {
    $ret = '';
    $ret .= '<span class="leftbox">';
    $ret .= '<a href="#" onClick="return window.open(\'mmsblog-show-pic.php?pic=';
    $ret .= $file;
    $ret .= '\', \'Picture\', \'width=720,height=560,scrollbars=no\')">';
    $ret .= '<img src="' . $thumb . '"/></a>';
    $ret .= '</span>';
    return $ret;
}

function mmsblog_picture($file, $thumb) {
    print mmsblog_get_picture($file, $thumb) . "\n";
}

function mmsblog_get_video_controller_tag($refmovie, $movie, $height) {
    $ret = "@@mmsblogVideo $refmovie $movie $height@@ ";
    return $ret;
}

function mmsblog_get_video_controller($refmovie, $movie, $height) {
    $width = 320;
    $ret = '';
    $ret .= '<span class="leftbox">';
	$ret .= '<object classid="clsid:02BF25D5-8C17-4B23-BC80-D3488ABDDC6B" ';
	$ret .= "width=\"$width\" height=\"$height\" ";
	$ret .= 'codebase="http://www.apple.com/qtactivex/qtplugin.cab"> ';
	$ret .= '<param name="src" value="' . $refmovie . '"/> ';
	$ret .= '<param name="href" value="' . $movie . '"/> ';
	$ret .= '<param name="target" value="myself"/> ';
	$ret .= '<param name="autoplay" value="true"/> ';
	$ret .= '<param name="controller" value="false"/> ';
	$ret .= '<param name="scale" value="aspect"/> ';
	$ret .= '<embed color="black" src="' . $refmovie . '" ';
	$ret .= 'href="' . $movie . '" ';
	$ret .= "target=\"myself\" width=\"$width\" height=\"$height\" controller=\"false\" ";
	$ret .= 'scale="aspect" ';
	$ret .= 'pluginspage="http://www.apple.com/quicktime/download/"> ';
	$ret .= '</embed> </object>';
	$ret .= '</span>';
	return $ret;
}

function debug_p($txt) {
	print "$txt\n";
}
?>