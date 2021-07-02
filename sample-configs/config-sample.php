<?php
// A sample config file - copy this into the root folder and rename to config.php
$usepassword = true;
$password = '123';

# "shore" theme
$backgroundimg = './backgrounds/bg_shore.jpg';
$background = '#222';
$accentfg = '#000';
$accentbg = '#fc0';
$menubg = '#eee';
$menushadow = '#ddd';
$gradient1 = '#1a1a1a';
$gradient2 = '#444';
$filebuttonfg = '#bbb';

$allowedextensions = array( 'mp3', 'flac', 'wav', 'ogg', 'opus', 'webm' );

$excluded = array( '.', '..', '.git', '.htaccess', '.htpasswd', 'backgrounds', 'cgi-bin', 'docs', 'getid3', 'logs', 'usage', 'sample-configs');

$width = '50%';

# different themes given by their background image and element colours

# "dark"
//$backgroundimg = './backgrounds/bg_dark.jpg';
//$background = '#333';
//$accentfg = '#000';
//$accentbg = '#fff';
//$menubg = '#ddd';
//$menushadow = '#ccc';
//$gradient1 = '#1a1a1a';
//$gradient2 = '#444';
//$filebuttonfg = '#bbb';

# "forest"
//$backgroundimg = './backgrounds/bg_forest.jpg';
//$background = '#556555';
//$accentfg = '#000';
//$accentbg = '#c4dd2a';
//$menubg = '#eee';
//$menushadow = '#ddd';
//$gradient1 = '#1a1a1a';
//$gradient2 = '#444';
//$filebuttonfg = '#bbb';

# "simple theme" - author: @gigibu5
// $background = '#333';
// $backgroundimg = '';
// $accentfg = '#000';
// $accentbg = 'greenyellow';
// $gradient1 = '#333';
// $gradient2 = '#333';
// $menubg = '#ddd';
// $menushadow = '#ccc';
// $filebuttonfg = '#bbb';


# Language settings
$empty_song_title = "No file playing";
$music_title = "Music";

$shuffle_text = "Shuffle";
$browse_text = "Browse";
$playlist_text = "Playlist";
$root_directory_text = "Root";
$clear_text = "Clear";

$playlist_empty_text = "This playlist is empty.";
$directory_empty_text = "This directory is empty.";

$password_text = "Password required";
$password_submit_text = "Submit";
?>