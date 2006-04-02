DIR="$1"
date
# mkdir ../wp-photos ../wp-thumbs ../wp-temp
mkdir $DIR/wp-temp
# cp -v mimedecode.php mmsblog-mail.php mmsblog-show-pic.php $DIR
cp -v mmsblog-mail.php $DIR
cp -v mmsblog-show-pic.php $DIR
cp -v mmsblog.php $DIR/wp-content/plugins
cp -v ref.mov $DIR/wp-photos
cp -v audio-refmovie.mov $DIR/wp-photos
# cp -v mmsblog-convert.php ..
