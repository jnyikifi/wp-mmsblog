Wordpress 1.2 wp-mail hack
Released 2004-06-16 - Version 1.0
By John B. Hewitt - blade@lansmash.com
Blog: http://blade.lansmash.com

This hack is a 'drop in' replacement for the current wp-mail.php. It uses 
pear as a mime compliant email 'decoder'.  What's cool is that you can 
attach images inline in your email messages and they'll be shown as an 
image in your wordpress article.  It does this by decoding the image 
attachments and writing them to 'wp-photos' directory (not in default wp 
install).

It has all the abilities of the current wp-mail.php plus:
	- Allows image attachments (posts inline)
	- Checks if user email address is in the database (otherwise 
discards message)
	- Allows other file attachments (zip's, exe's, etc)
	- Fairly good cleaner for removing excessive line breaks

I'm planning on more updates, but would love to see this update included 
in future wordpress releases.  Would be happy to maintain it to, I'm 
fairly sure it complies with most of the coding guidelines I've read on 
the wordpress website.

To install you grab the zip file.
- -  Drop the two files (wp-mail.php & mailmimedecode.php) into a 
wordpress 1.2 root directory
- - Create the directories 'wp-photos'  and 'wp-filez' in the wordpress 
root directory(with writing permissions (probably 0777).

