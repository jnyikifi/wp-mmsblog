<?php 
        /* Don't remove this line. */
        require('./wp-blog-header.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head profile="http://gmpg.org/xfn/1">
	<title><?php bloginfo('name'); ?><?php wp_title(); ?></title>
	
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo('charset'); ?>" />
	<meta name="generator" content="WordPress <?php bloginfo('version'); ?>" /> <!-- leave this for stats -->

	<style type="text/css" media="screen">
		@import url( <?php echo get_settings('siteurl'); ?>/wp-layout.css );
	</style>
	
	<?php wp_head(); ?>
</head>

<body>
    <div id="mmspic">
        <img src="<?php print $_REQUEST[pic]; ?>">
        <br/>
        <br/>
        <a href="#" onClick="return window.close()">close</a>
    </div>
</body>
</html>
