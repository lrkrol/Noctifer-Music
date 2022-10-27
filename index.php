<?php
header( 'content-type: text/html; charset:utf-8' );
session_start();
error_reporting( 0 );

/*
Noctifer Music 0.7.0
Copyright 2019, 2022 Laurens R. Krol
noctifer.net, lrkrol.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


# +-----------------------------------+
# |     C O N F I G U R A T I O N     |
# +-----------------------------------+

# whether or not to ask for a password, and if yes, the password to access directory/playlist contents
$usepassword = true;
$password = '123';

# files with the following extensions will be displayed (case-insensitive)
# note that it depends on your browser whether or not these will actually play
$allowedextensions = array( 'mp3', 'flac', 'wav', 'ogg', 'opus', 'webm' );

# the following directories and files will not be displayed (case-sensitive)
$excluded = array( '.', '..', '.git', '.htaccess', '.htpasswd', 'backgrounds', 'cgi-bin', 'docs', 'getid3', 'logs', 'usage' );

# the width of the player (in desktop mode)
$width = '40%';

# different themes given by their background image and element colours
    # "shore"
$backgroundimg = './backgrounds/bg_shore.jpg';
$background = '#222';
$accentfg = '#000';
$accentbg = '#fc0';
$menubg = '#eee';
$menushadow = '#ddd';
$gradient1 = '#1a1a1a';
$gradient2 = '#444';
$filebuttonfg = '#bbb';

    # "dark"
// $backgroundimg = './backgrounds/bg_dark.jpg';
// $background = '#333';
// $accentfg = '#000';
// $accentbg = '#fff';
// $menubg = '#ddd';
// $menushadow = '#ccc';
// $gradient1 = '#1a1a1a';
// $gradient2 = '#444';
// $filebuttonfg = '#bbb';

    # "forest"
// $backgroundimg = './backgrounds/bg_forest.jpg';
// $background = '#556555';
// $accentfg = '#000';
// $accentbg = '#c4dd2a';
// $menubg = '#eee';
// $menushadow = '#ddd';
// $gradient1 = '#1a1a1a';
// $gradient2 = '#444';
// $filebuttonfg = '#bbb';


/*
# +---------------------------+
# |     C H A N G E L O G     |
# +---------------------------+

2022-10-27 0.7.0
- Added cookie to maintain volume when changed
- Minor cleanup

2019-04-08 0.6.1
- Added keyboard shortcuts and swipe events
- Also password-protected playlist view

2019-04-07 0.5.7
- When setting play mode, selected song now always starts at index 0 when shuffle is on
- Equalised URI encoding of cookies
- In playlist mode, adding/removing a song to the playlist now also updates the active songlist
- Added password protection
- Added buttons to reorder files in playlist
- Added theme parameters
- Updated URI encoding within cookies
- Added directory to playlist items
- Removed file extensions from list
- Updated URI encoding for next/previous
- Fixed issue with nm_songs_active not being set correctly

2019-02-09 0.4.5
- Removed background-repeat and accent colour from albumart

2019-02-08 0.4.4
- Prevented directories above root from being accessed
- Escaped apostrophe in add to/remove from playlist links
- Added "Clear playlist" button

2019-01-29 0.4.1
- Implemented playlist
- Switched from window.onload to DOMContentLoaded event listener

2019-01-27 0.3.2
- Implemented shuffle
- Updated button placement
- Made displayed browse directory constant

2019-01-21 0.2.4
- Updated ignored file list
- Added empty directory message
- Enabled automatic advance to next song in directory
- Added playback error message
- Fixed session and cookie handling
- Added buttons to layout
- Fixed sorting bug

2019-01-20 0.1.5
- Made design responsive
- Made player sticky on top
- Made player width customisable
- Highlighted current song in directory index
- Added background image

2019-01-19 0.1.0
- Persistent music player for single files allowing free simultaneous browsing

*/


if( isset( $_POST['password'] ) ) {
    if ( htmlspecialchars($password) == htmlspecialchars( $_POST['password'] ) ) {
        $_SESSION['authenticated'] = 'yes';
        header( "Location: {$_SERVER['HTTP_REFERER']}" );
    }
} elseif( isset( $_GET['play'] ) ) {
    ### playing the indicated song
    $song = sanitizeGet( $_GET['play'] );

    if ( is_file( $song ) ) {
        # obtaining song info
        $songinfo = getsonginfo( $song );

        # getting list of songs in this directory
        $dirsonglist = getDirContents( dirname( $song ) );
        foreach ($dirsonglist['files'] as &$file) {
            $file = dirname( $song ) . '/' . $file;
        } unset($file);

        # setting cookies
        setcookie( 'nm_nowplaying', urlencode( $song ), strtotime( '+1 day' ) );
        setcookie( 'nm_songs_currentsongdir', json_encode( $dirsonglist['files'] ), strtotime ( '+1 week' ) );
        
        # updating active song list and active song index
        if ( !isset( $_COOKIE['nm_songs_active'] ) ) {
            # if no active song list set: setting current dir as active song list 
            setcookie( 'nm_songs_active', json_encode( $dirsonglist['files'] ), strtotime ( '+1 week' ) );
            setcookie( 'nm_songs_active_idx', array_search( $song, $dirsonglist['files'] ), strtotime ( '+1 week' ) );
        } else {
            $activesonglist = json_decode( $_COOKIE['nm_songs_active'], true );
            if ( array_search( $song, $activesonglist ) === false ) {
                # if current song not in active song list: we must be in browse mode and entered a new directory
                if ( isset( $_COOKIE['nm_shuffle'] ) && $_COOKIE['nm_shuffle'] == 'on' ) {
                    # if shuffle on: first shuffling list
                    shuffle( $dirsonglist['files'] );
                    array_splice( $dirsonglist['files'], array_search( $song, $dirsonglist['files'] ), 1 );
                    array_unshift( $dirsonglist['files'], $song );
                }
                setcookie( 'nm_songs_active', json_encode( $dirsonglist['files'] ), strtotime ( '+1 week' ) );                
                setcookie( 'nm_songs_active_idx', array_search( $song, $dirsonglist['files'] ), strtotime ( '+1 week' ) );
            } else {    
                setcookie( 'nm_songs_active_idx', array_search( $song, $activesonglist ), strtotime ( '+1 week' ) );
            }
        }
        
        # no error message
        $error = '';
    } else {
        # defaulting to root directory and displaying error message
        $songinfo = array();
        $error = "Could not find file {$song}.";
        $song = '';
    }

    loadPage( $song, $error, $songinfo );
} elseif( isset( $_GET['which'] ) )  {
    ### responding to AJAX request for next/previous song in songlist
    $which = sanitizeGet( $_GET['which'] );

    if ( isset( $_COOKIE['nm_songs_active'] ) && isset( $_COOKIE['nm_songs_active_idx'] ) ) {
        $songlist = json_decode( $_COOKIE['nm_songs_active'], true );
        $currentindex = $_COOKIE['nm_songs_active_idx'];

        if ( $which === 'next' && isset( $songlist[$currentindex + 1] ) ) {
            echo urlencode( $songlist[$currentindex + 1] );
        } elseif ( $which === 'previous' && isset( $songlist[$currentindex - 1] ) ) {
            echo urlencode( $songlist[$currentindex - 1] );
        }
    }
} elseif( isset( $_GET['dir'] ) )  {
    ### responding to AJAX request for directory contents

    if ( $usepassword && !isset ( $_SESSION['authenticated'] ) ) {
        # show "Password required [             ]"
        echo <<<PASSWORDREQUEST
<div id="header"><div id="passwordrequest">
    Password required
    <form action="." method="post">
        <input type="password" name="password" id="passwordinput" />
        <input type="submit" value="Submit" />
    </form>
</div></div>
PASSWORDREQUEST;
    } else {
    
        $basedir = sanitizeGet( $_GET['dir'] );

        if ( is_dir( $basedir ) && !in_array( '..', explode( '/', $basedir ) ) ) {
            # setting currentbrowsedir cookie
            setcookie( 'nm_currentbrowsedir', urlencode( $basedir ), strtotime( '+1 day' ) );

            # listing directory contents
            $dircontents = getDirContents( $basedir );

            # returning header
            echo '<div id="header">';
            renderButtons();
            echo '<div id="breadcrumbs">';
            $breadcrumbs = explode( '/', $basedir );
            for ( $i = 0; $i != sizeof( $breadcrumbs ); $i++ ) {
                $title = $breadcrumbs[$i] == '.'  ? 'Root'  : $breadcrumbs[$i];

                if ($i == sizeof($breadcrumbs) - 1) {
                    # current directory
                    echo "<span id=\"breadcrumbactive\">{$title}</span>";
                } else {
                    # previous directories with link
                    $link = urlencode( implode( '/', array_slice( $breadcrumbs, 0, $i+1 ) ) );
                    echo "<span class=\"breadcrumb\" onclick=\"goToDir('{$link}');\">{$title}</span><span class=\"separator\">/</span>";
                }
            }
            echo '</div>';
            echo '</div>';

            if ( empty( $dircontents['dirs'] ) && empty( $dircontents['files'] ) ) {
                # nothing to show
                echo '<div id="filelist" class="list"><div>This directory is empty.</div></div>';
            } else {
                # returning directory list
                if ( !empty( $dircontents['dirs'] ) ) {
                    echo '<div id="dirlist" class="list">';
                    foreach ( $dircontents['dirs'] as $dir ) {
                        $link = urlencode( $basedir . '/' . $dir );
                        echo "<div class=\"dir\" onclick=\"goToDir('{$link}');\">{$dir}</div>";
                    } unset( $dir );
                    echo '</div>';
                }

                # returning file list
                if ( !empty( $dircontents['files'] ) ) {
                    echo '<div id="filelist" class="list">';
                    foreach ( $dircontents['files'] as $file ) {
                        $link = urlencode( $basedir . '/' . $file );
                        $song = pathinfo( $file, PATHINFO_FILENAME );
                        $jslink = str_replace( "'", "\'", $link );
                        $nowplaying = ( isset( $_COOKIE['nm_nowplaying'] ) && $_COOKIE['nm_nowplaying'] == $link ) ? ' nowplaying' : '';
                        echo "<div class=\"file{$nowplaying}\"><a href=\"?play={$link}\" onclick=\"setPlayMode('browse', '{$jslink}');\">&#x25ba; {$song}</a><div class=\"filebutton\" onclick=\"addToPlaylist('{$jslink}');\" title=\"Add to playlist\">+</div></div>";
                    } unset( $file );
                    echo '</div>';
                }
            }
        }
    }
} elseif( isset( $_GET['playlist'] ) )  {
    ### responding to AJAX request for playlist contents

    if ( $usepassword && !isset ( $_SESSION['authenticated'] ) ) {
        # show "Password required [             ]"
        echo <<<PASSWORDREQUEST
<div id="header"><div id="passwordrequest">
    Password required
    <form action="." method="post">
        <input type="password" name="password" id="passwordinput" />
        <input type="submit" value="Submit" />
    </form>
</div></div>';
PASSWORDREQUEST;
    } else {
        if ( isset( $_COOKIE['nm_songs_playlist'] ) ) {
            $playlist = json_decode( $_COOKIE['nm_songs_playlist'], true );
        }

        # returning header
        echo '<div id="header">';
        renderButtons();
        echo '<div id="playlisttitle">Playlist</div>';
        echo '</div>';

        if ( empty( $playlist ) ) {
            # nothing to show
            echo '<div id="filelist" class="list"><div>This playlist is empty.</div></div>';
        } else {
            echo '<div id="filelist" class="list">';
            foreach ( $playlist as $link ) {
                $song = pathinfo( $link, PATHINFO_FILENAME );
                $dir = dirname( $link );
                
                $playlistdir = ( $dir == '.' ? '' : "<span class=\"playlistdirectory\">{$dir}</span><br />" );
                
                $link = urlencode( $link );
                $nowplaying = ( isset( $_COOKIE['nm_nowplaying'] ) && $_COOKIE['nm_nowplaying'] == $link ) ? ' nowplaying' : '';
                $jslink = str_replace( "'", "\'", $link );
                echo "<div class=\"file{$nowplaying}\"><a href=\"?play={$link}\" onclick=\"setPlayMode('playlist', '{$jslink}');\">{$playlistdir}&#x25ba; {$song}<br /></a><div class=\"filebutton\" onclick=\"moveInPlaylist('{$jslink}', -1);\"title=\"Move up\">&#x2191</div><div class=\"filebutton\" onclick=\"moveInPlaylist('{$jslink}', 1);\"title=\"Move down\">&#x2193</div><div class=\"filebutton\" onclick=\"removeFromPlaylist('{$jslink}');\" title=\"Remove from playlist\">&#x00d7</div></div>";
            } unset( $file );
            echo '</div>';
        }
    }
} else {
    ### rendering default site
    loadPage();
}


function renderButtons() {
    # toggling active class for active buttons
    $viewmode = ( isset( $_COOKIE['nm_viewmode'] ) && $_COOKIE['nm_viewmode'] == 'playlist' ) ? 'playlist' : 'browse';
    $playlistactive = ( $viewmode == 'playlist' ) ? ' active' : '';
    $browseactive = ( $viewmode == 'browse' ) ? ' active' : '';
    $shuffleactive = ( isset( $_COOKIE['nm_shuffle'] ) && $_COOKIE['nm_shuffle'] == 'on' ) ? ' active' : '';

    # setting browse directory when browse mode is activated
    if ( isset( $_COOKIE['nm_currentbrowsedir'] ) ) { $dir = $_COOKIE['nm_currentbrowsedir']; }
    elseif ( isset( $_COOKIE['nm_currentsongdir'] ) ) { $dir = $_COOKIE['nm_currentsongdir']; }
    else { $dir = '.'; }
    
    # rendering playlist buttons when in playlist mode
    if ( $viewmode == 'playlist' ) {
        $playlistbuttons = <<<PLBUTTONS
        <div class="button" onclick="clearPlaylist();"><span>Clear</span></div>
        <div class="separator"></div>
PLBUTTONS;
    } else {
        $playlistbuttons = '';
    }

    # rendering general buttons
    echo <<<BUTTONS
    <div class="buttons">
        {$playlistbuttons}
        <div class="button{$shuffleactive}" id="shufflebutton" onclick="toggleShuffle();"><span>Shuffle</span></div>
        <div class="separator"></div>
        <div class="button border{$browseactive}" onclick="goToDir('{$dir}');"><span>Browse</span></div>
        <div class="button{$playlistactive}" onclick="goToPlaylist('default')"><span>Playlist</span></div>
    </div>
BUTTONS;
}


function getDirContents( $dir ) {
    global $excluded, $allowedextensions;
    $allowedextensions = array_map( 'strtolower', $allowedextensions );

    $dirlist = array();
    $filelist = array();
    
    # browsing given directory
    if ( $dh = opendir( $dir ) ) {
        while ( $itemname = readdir( $dh ) ) {
            # ignoring certain files
            if ( !in_array( $itemname, $excluded ) ) {
                if ( is_file( $dir . '/' . $itemname ) ) {
                    # found a file: adding allowed files to file array
                    $info = pathinfo( $itemname );
                    if ( isset( $info['extension'] ) && in_array( strtolower( $info['extension'] ), $allowedextensions ) ) {
                        $filelist[] = $info['filename'] . '.' . $info['extension'];
                    }
                } elseif ( is_dir( $dir . '/' . $itemname ) ) {
                    # found a directory: adding to directory array
                    $dirlist[] = $itemname;
                }
            }
        }
        closedir($dh);
    }

    if ( sizeof( $dirlist ) > 1 ) { usort( $dirlist, 'compareName' ) ; }
    if ( sizeof( $filelist ) > 1 ) { usort( $filelist, 'compareName' ) ; }

    return array('dirs' => $dirlist, 'files' => $filelist);
}


function getSongInfo( $song ) {
    ### if available, using getID3 to extract song info

    if ( file_exists( './getid3/getid3.php' ) ) {
        # getting song info
        require_once( './getid3/getid3.php' );
        $getID3 = new getID3;
        $fileinfo = $getID3->analyze( $song );
        getid3_lib::CopyTagsToComments( $fileinfo );
        
        # extracting song title, or defaulting to file name
        if ( isset( $fileinfo['comments_html']['title'][0] ) && !empty( trim( $fileinfo['comments_html']['title'][0] ) ) ) {
            $title = trim( $fileinfo['comments_html']['title'][0] );
        } else {
            $title = pathinfo($song, PATHINFO_FILENAME);
        }

        # extracting song artist, or defaulting to directory name
        if ( isset( $fileinfo['comments_html']['artist'][0] ) && !empty( trim( $fileinfo['comments_html']['artist'][0] ) ) ) {
            $artist = trim( $fileinfo['comments_html']['artist'][0] );
        } else {
            $artist = str_replace( '/', ' / ', dirname( $song ) );
        }

        # extracting song album
        if ( isset( $fileinfo['comments_html']['album'][0] ) && !empty( trim( $fileinfo['comments_html']['album'][0] ) ) ) {
            $album = trim( $fileinfo['comments_html']['album'][0] );
        } else {
            $album = '';
        }

        # extracting song year/date
        if ( isset( $fileinfo['comments_html']['year'][0] ) && !empty( trim( $fileinfo['comments_html']['year'][0] ) ) ) {
            $year = trim( $fileinfo['comments_html']['year'][0] );
        } elseif ( isset($fileinfo['comments_html']['date'][0] ) && !empty( trim( $fileinfo['comments_html']['date'][0] ) ) ) {
            $year = trim( $fileinfo['comments_html']['date'][0] );
        } else {
            $year = '';
        }

        # extracting song picture
        if ( isset( $fileinfo['comments']['picture'][0] ) ) {
            $art = 'data:'.$fileinfo['comments']['picture'][0]['image_mime'].';charset=utf-8;base64,'.base64_encode( $fileinfo['comments']['picture'][0]['data'] );
        } else {
            $art = '';
        }

        return array(
            "title" => $title,
            "artist" => $artist,
            "album" => $album,
            "year" => $year,
            "art" => $art
        );
    } else {
        # defaulting to song filename and directory when getID3 is not available
        return array(
            "title" => basename( $song ),
            "artist" => dirname( $song ),
            "album" => '',
            "year" => '',
            "art" => ''
        );
    }
}


function sanitizeGet( $str ) {
    $str = stripslashes( $str );
	return $str;
}


function compareName( $a, $b ) {
    # directory name comparison for usort
    return strnatcasecmp( $a, $b );
}


function loadPage( $song = '', $error = '', $songinfo = array() ) {
    global $width, $background, $backgroundimg, $accentfg, $accentbg, $menubg, $menushadow, $gradient1, $gradient2, $filebuttonfg;

    # hiding error message div if there is no message to display
    $errordisplay = empty( $error ) ? 'none' : 'block';

    if ( isset( $_COOKIE['nm_viewmode'] ) && $_COOKIE['nm_viewmode'] == 'playlist' ) {
        # loading playlist view
        $onloadgoto = "goToPlaylist('default');";
    } else {
        # loading directory view
        if ( isset( $_COOKIE['nm_currentbrowsedir'] ) ) { $dir = $_COOKIE['nm_currentbrowsedir']; }
        elseif ( isset( $_COOKIE['nm_currentsongdir'] ) ) { $dir = $_COOKIE['nm_currentsongdir']; }
        else { $dir = '.'; }
        $onloadgoto = "goToDir('{$dir}');";
    }

    # setting player layout depending on available information
    if ( empty( $songinfo ) ) {
        # no information means no file is playing
        $songtitle = 'No file playing';
        $songinfoalign = 'center';
        $songsrc = '';
        $pagetitle = "Music";

        # hiding info elements
        $artist = '';
        $artistdisplay = 'none';

        $album = '';
        $albumdisplay = 'none';

        $year = '';
        $yeardisplay = 'none';

        $art = '';
        $artdisplay = 'none';
    } else {
        # displaying info elements where available
        $songsrc = " src=\"{$song}\"";
        $songtitle = $songinfo['title'];
        $pagetitle = $songtitle;
        if ( !empty( $songinfo['artist'] ) ) {
            $artist = $songinfo['artist'];
            $artistdisplay = 'block';
            $pagetitle = "$artist - $pagetitle";
        } else {
            $artistdisplay = 'none';
        }
        if ( !empty( $songinfo['album'] ) ) {
            $album = $songinfo['album'];
            $albumdisplay = 'block';
        } else {
            $album = '';
            $albumdisplay = 'none';
        }
        if ( !empty( $songinfo['year'] ) ) {
            $year = $songinfo['year'];
            $yeardisplay = 'inline-block';
        } else {
            $year = '';
            $yeardisplay = 'none';
        }
        if ( !empty( $songinfo['art'] ) ) {
            $art = $songinfo['art'];
            $artdisplay = 'block';
            $songinfoalign = 'left';
        } else {
            $art = '';
            $artdisplay = 'none';
            $songinfoalign = 'center';
        }
    }

    # writing page
    echo <<<HTML
<!doctype html>

<html lang="en" prefix="og: http://ogp.me/ns#">
<head>
    <meta charset="utf-8" />

    <title>{$pagetitle}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0" id="viewport" />

    <script>
        function goToDir(dir) {
            setCookie('nm_viewmode', 'browse', 7);

            // getting and displaying directory contents
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200){
                    document.getElementById('interactioncontainer').innerHTML = xmlhttp.responseText;
                }
            }
            xmlhttp.open('GET', '?dir=' + dir, true);
            xmlhttp.send();
        };

        function goToPlaylist(playlist) {
            setCookie('nm_viewmode', 'playlist', 7);

            // getting and displaying playlist contents
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200){
                    document.getElementById('interactioncontainer').innerHTML = xmlhttp.responseText;
                }
            }
            xmlhttp.open('GET', '?playlist=' + playlist, true);
            xmlhttp.send();
        };

        function addToPlaylist(song) {
            song = song.replace(/\+/g, '%20');
            song = decodeURIComponent(song);
            // adding song to playlist, or initialising playlist with song
            var playlist = getCookie('nm_songs_playlist');
            if (playlist) {
                // removing song if it already exists
                playlist = JSON.parse(playlist);
                var songIdx = playlist.indexOf(song);
                if (songIdx >= 0) {
                    playlist.splice(songIdx, 1);
                }
                
                // adding song to end of playlist
                playlist.push(song);
            } else {
                var playlist = [song];
            }
            setCookie('nm_songs_playlist', JSON.stringify(playlist), 365);
            
            // if currently playing from playlist, also updating active songlist
            var playmode = getCookie('nm_playmode');
            if (playmode == 'playlist') {
                var shuffle = getCookie('nm_shuffle');
                if (shuffle == 'on') {
                    // adding new song between current and end of current shuffled songlist
                    var currentsong = getCookie('nm_nowplaying');
                    var songlist = getCookie('nm_songs_active');
                    if (songlist) {
                        songlist = JSON.parse(songlist);
                        var songIdx = songlist.indexOf(currentsong);
                        var randomIdx = Math.floor(Math.random() * (songlist.length - songIdx) + songIdx + 1);
                        songlist.splice(randomIdx, 0, song);
                        setCookie('nm_songs_active', JSON.stringify(songlist), 7);
                    }
                } else {
                    // getting current song's index in playlist
                    var currentsong = getCookie('nm_nowplaying');
                    var songIdx = playlist.indexOf(currentsong);

                    // setting cookies
                    setCookie('nm_songs_active', JSON.stringify(playlist), 7);
                    setCookie('nm_songs_active_idx', songIdx, 7);
                }
            }
            
        };

        function removeFromPlaylist(song) {
            song = song.replace(/\+/g, '%20');
            song = decodeURIComponent(song);
            var playlist = getCookie('nm_songs_playlist');
            if (playlist) {
                playlist = JSON.parse(playlist);
                var songIdx = playlist.indexOf(song);
                // moving to end if already in playlist
                if (songIdx >= 0) {
                    playlist.splice(songIdx, 1);
                }
                setCookie('nm_songs_playlist', JSON.stringify(playlist), 365);
                
                // if currently playing from playlist, also updating active songlist
                var playmode = getCookie('nm_playmode');
                if (playmode == 'playlist') {
                    var songlist = getCookie('nm_songs_active');
                    songlist = JSON.parse(songlist);
                    var currentsong = getCookie('nm_nowplaying');
                    var songIdx = songlist.indexOf(currentsong);
                    songlist.splice(songIdx, 1)
                    setCookie('nm_songs_active', JSON.stringify(songlist), 7);
                }
                    
                // showing updated playlist
                goToPlaylist('default');
            }
        };
        
        function moveInPlaylist(song, direction) {
            song = song.replace(/\+/g, '%20');
            song = decodeURIComponent(song);
            var playlist = getCookie('nm_songs_playlist');
            playlist = JSON.parse(playlist);
            var songIdx = playlist.indexOf(song);
            if (songIdx + direction >= 0 && songIdx + direction < playlist.length) {
                playlist.splice(songIdx, 1);
                playlist.splice(songIdx + direction, 0, song);
            }
            setCookie('nm_songs_playlist', JSON.stringify(playlist), 365);
                
            // if currently playing from playlist, also updating active songlist
            var playmode = getCookie('nm_playmode');
            var shuffle = getCookie('nm_shuffle');
            if (playmode == 'playlist' && shuffle != 'on') {
                var currentsong = getCookie('nm_nowplaying');
                var songIdx = playlist.indexOf(currentsong);
                setCookie('nm_songs_active', JSON.stringify(playlist), 7);           
                setCookie('nm_songs_active_idx', songIdx, 7);
            }
            
            // showing updated playlist
            goToPlaylist('default');
        };
        
        function clearPlaylist() {
            setCookie('nm_songs_playlist', '', 365);
                
            var playmode = getCookie('nm_playmode');
            if (playmode == 'playlist') {
                setCookie('nm_songs_active', '', 7);                
                setCookie('nm_songs_active_idx', '0', 7);
            }
                
            goToPlaylist('default');
        };

        function setPlayMode(mode, song) {
            setCookie('nm_playmode', mode, 7);

            // switching to appropriate songlist, shuffling where necessary
            if (mode == 'browse') {
                var songlist = getCookie('nm_songs_currentsongdir');
            } else if (mode == 'playlist') {
                var songlist = getCookie('nm_songs_playlist');
            }
            if (songlist) {
                songlist = JSON.parse(songlist)
                if (getCookie('nm_shuffle') == 'on') {
                    songlist = shuffleArray(songlist);
                    
                    // moving selected song to index 0
                    var songIdx = songlist.indexOf(song);
                    songlist[songIdx] = songlist[0];
                    songlist[0] = song;                
                }
                setCookie('nm_songs_active', JSON.stringify(songlist), 7);
            }
        };

        function advance(which) {
            // requesting next/previous song and loading it
            xmlhttp = new XMLHttpRequest();
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200){
                    if (xmlhttp.responseText) {
                        window.location.href = '?play=' + xmlhttp.responseText;
                    } else if (which == 'next' && getCookie('nm_shuffle') == 'on') {
                        // end of shuffle playlist: restarting shuffle
                        toggleShuffle();
                        toggleShuffle();
                        advance('next');
                    }
                }
            }
            xmlhttp.open('GET', '?which=' + which, true);
            xmlhttp.send();
        };

        function toggleShuffle() {
            var shuffle = getCookie('nm_shuffle');
            if (shuffle == 'on') {
                // updating shuffle cookie and graphics
                setCookie('nm_shuffle', 'off', 7);
                document.getElementById('shufflebutton').classList.remove('active');

                // putting back original songlist
                var playmode = getCookie('nm_playmode');
                if (playmode == 'browse') {
                    var songlist = JSON.parse(getCookie('nm_songs_currentsongdir'));
                } else if (playmode == 'playlist') {
                    var songlist = JSON.parse(getCookie('nm_songs_playlist'));
                }

                // getting current song's index in that list
                var song = getCookie('nm_nowplaying');
                var songIdx = songlist.indexOf(song);
                
                // setting cookies
                setCookie('nm_songs_active', JSON.stringify(songlist), 7);
                setCookie('nm_songs_active_idx', songIdx, 7);
            } else {
                // updating shuffle cookie and graphics
                setCookie('nm_shuffle', 'on', 7);
                document.getElementById('shufflebutton').classList.add('active');

                // randomising active songlist
                var songlist = JSON.parse(getCookie('nm_songs_active'));
                var songlist = shuffleArray(songlist);

                // getting current song's index in that list
                var song = getCookie('nm_nowplaying');
                var songIdx = songlist.indexOf(song);

                // moving it to index 0
                songlist[songIdx] = songlist[0];
                songlist[0] = song;

                // setting cookies
                setCookie('nm_songs_active', JSON.stringify(songlist), 7);
                setCookie('nm_songs_active_idx', 0, 7);
            }
        };

        function shuffleArray(array) {
            var currentindex = array.length, temporaryValue, randomIndex;

            // While there remain elements to shuffle...
            while (0 !== currentindex) {
                // Pick a remaining element...
                randomIndex = Math.floor(Math.random() * currentindex);
                currentindex -= 1;

                // And swap it with the current element.
                temporaryValue = array[currentindex];
                array[currentindex] = array[randomIndex];
                array[randomIndex] = temporaryValue;
            }

            return array;
        };

        function setCookie(cname, cvalue, exdays) {
            var d = new Date();
            d.setTime(d.getTime() + (exdays*24*60*60*1000));
            var expires = 'expires=' + d.toUTCString();
            document.cookie = cname + '=' + encodeURIComponent(cvalue) + ';' + expires;
        }

        function getCookie(cname) {
            var name = cname + '=';
            var decodedCookie = decodeURIComponent(document.cookie);
            var ca = decodedCookie.split(';');
            for(var i = 0; i < ca.length; i++) {
                var c = ca[i];
                while (c.charAt(0) == ' ') {
                    c = c.substring(1);
                }
                if (c.indexOf(name) == 0) {
                    var result = c.substring(name.length, c.length);
                    result = result.replace(/\+/g, '%20');
                    return decodeURIComponent(result);
                }
            }
            return '';
        };

        document.addEventListener("DOMContentLoaded", function() {
            var audio = document.getElementById('audio');
            
            audio.addEventListener('error', function() {
                document.getElementById('error').innerHTML = 'Playback error';
                document.getElementById('error').style.display = 'block';
                setTimeout(function(){ advance('next'); }, 2000);
            });

            audio.addEventListener('ended', function() {
                advance('next');
            });
            
            
            audio.addEventListener('volumechange', function() {
                setCookie('nm_volume', audio.volume, 14);
            });
            
            var volume = getCookie('nm_volume');
            if (volume != null && volume) {
                audio.volume = volume;
            }

            {$onloadgoto}
        }, false);
        
        document.onkeydown = function(e){
            switch (e.keyCode) {
                case 90: // z
                    advance('previous');
                    break;
                case 88: // x
                    document.getElementById('audio').play();
                    document.getElementById('audio').fastSeek(0);
                    break;
                case 67: // c
                    var audio = document.getElementById('audio');
                    if (audio.paused) {
                        audio.play();
                    } else {
                        audio.pause();
                    }
                    break;
                case 86: // v
                    document.getElementById('audio').pause();
                    document.getElementById('audio').fastSeek(0);
                    break;
                case 66: // b
                    advance('next');
                    break;
                case 37: // left
                    advance('previous');
                    break;
                case 39: // right
                    advance('next');
                    break;
            }
        };
        
        function swipedetect(el, callback){
            // based on code from JavaScript Kit @ http://www.javascriptkit.com/javatutors/touchevents2.shtml
            var touchsurface = el,
                swipedir,
                startX,
                startY,
                distX,
                distY,
                threshold = 50,
                handleswipe = callback || function(swipedir){}
            touchsurface.addEventListener('touchstart', function(e){
                var touchobj = e.changedTouches[0]
                swipedir = 'none'
                dist = 0
                startX = touchobj.pageX
                startY = touchobj.pageY
            }, false)
            touchsurface.addEventListener('touchend', function(e){
                var touchobj = e.changedTouches[0]
                distX = touchobj.pageX - startX
                distY = touchobj.pageY - startY
                if (Math.abs(distX) >= threshold && Math.abs(distX) > Math.abs(distY)){
                    swipedir = (distX < 0)? 'left' : 'right'
                } else if (Math.abs(distY) >= threshold && Math.abs(distY) > Math.abs(distX)){
                    swipedir = (distY < 0)? 'up' : 'down'
                }
                handleswipe(swipedir)
            }, false)
        };
        window.addEventListener('load', function(){
            var el = document.getElementById('interactioncontainer');
            swipedetect(el, function(swipedir){
                if (swipedir == 'left'){
                    advance('next');
                } else if (swipedir == 'right'){
                    advance('previous');
                }
            })
	    // Get and set volume with cookie
            var audio = document.getElementById('audio');
            audio.addEventListener('volumechange', function() {
                setCookie('volume', audio.volume, 14);
            });
            var volume = getCookie('volume');
            if (volume != null && volume) {
                audio.volume = volume;
            }
        }, false);
    </script>

    <style>
        html, body {
                width: 100%;
                margin: 0px; padding: 0px;
                font-family: sans-serif; }

            html {
                    background: {$background} url('{$backgroundimg}') no-repeat fixed center top;
                    background-size: cover;}

            body {
                    min-height: 100vh;
                    box-sizing: border-box;
                    padding-bottom: 5px;
                    background-color: rgba(0, 0, 0, 0.25);  }

        #stickycontainer {
                position: sticky;
                top: 0;
                margin-bottom: 10px; }

            #playercontainer {
                    padding: 20px 0;
                    background-color: #333;
                    background-image: linear-gradient({$gradient1}, {$gradient2}); }

                #player {
                        width: {$width};
                        margin: 0 auto;
                        display: flex;
                        box-sizing: border-box;
                        padding: 10px;
                        background-color: #111; }

                    #albumart {
                            display: {$artdisplay};
                            width: 7.25vw;
                            height: 7.25vw;
                            margin-right: 10px;
                            background: #333 url({$art}) center center / contain no-repeat; }

                    #song {
                            flex-grow: 1;
                            display: flex;
                            flex-direction: column;
                            justify-content: space-between; }

                        #songinfo { }

                            #songinfo div {
                                    color: grey;
                                    text-align: {$songinfoalign};
                                    font-size: 1.2vw;
                                    height: 1.4vw;
                                    width: 100%;
                                    overflow: hidden; }

                            #artist {
                                    display: {$artistdisplay}; }

                            #album {
                                    display: {$albumdisplay}; }

                            #year {
                                    margin-left: .35em;
                                    display: {$yeardisplay}; }

                                #year:before {
                                        content: "("; }

                                #year:after {
                                        content: ")"; }

                        #player audio {
                                width: 100%;
                                height: 1.3vw;
                                margin-top: 1.5vw; }

                #divider {
                        height: 2px;
                        background-color: {$accentbg}; }

        #error {
                box-sizing: border-box;
                width: {$width};
                display: {$errordisplay};
                color: white;
                text-align: center;
                word-break: break-all;
                margin: 20px auto 10px auto;
                background-color: #a00;
                padding: 10px; }

        #interactioncontainer {
                box-sizing: border-box;
                line-height: 1.5; }

            #header {
                    display: flex;
                    justify-content: flex-start;
                    flex-direction: row-reverse;
                    overflow: hidden;
                    flex-wrap: wrap;
                    font-size: 0;
                    width: {$width};
                    margin: 0 auto 10px auto; }

                #playlisttitle, #breadcrumbs, #passwordrequest {
                        font-size: medium;
                        margin-top: 10px;
                        flex-grow: 1;
                        color: #333;
                        background-color: {$menubg}; }

                    #playlisttitle {
                            font-weight: bold;
                            padding: 10px; }
                            
                    #passwordrequest {
                            display: flex;
                            padding: 10px; }
                            
                    #passwordrequest form {
                            display: flex;
                            flex-grow: 1; }
                            
                        #passwordrequest #passwordinput {
                                margin: 0 10px;
                                flex-grow: 1; }

                    .breadcrumb, #breadcrumbactive {
                            display: inline-block;
                            padding: 10px; }

                    .breadcrumb:hover {
                            cursor: pointer;
                            background-color: {$menushadow}; }

                    #breadcrumbactive {
                            font-weight: bold; }

                .buttons {
                        display: flex;
                        font-size: medium;
                        margin-left: 10px;
                        margin-top: 10px; }

                    .button {
                            padding: 10px;
                            background-color: {$menubg};  }

                        .button:hover {
                                cursor: pointer;
                                background-color: {$menushadow}; }

                        .border {
                            border-right: 1px solid {$menushadow}; }

                        .active {
                                font-weight: bold;  }

                            .active span {
                                    border-bottom: 2px solid {$accentbg}; }

                .separator {
                        color: #bbb;
                        padding: 0 5px; }

            .list div {
                    width: {$width};
                    box-sizing: border-box;
                    margin: 0 auto;
                    padding: 5px 10px;
                    color: #333;
                    background-color: {$menubg};
                    border-bottom: 1px solid {$menushadow}; }

                .list div:last-child {
                        margin-bottom: 10px;
                        border: 0; }

                .list .dir:hover, .list .file:hover {
                        cursor: pointer;
                        background-color: {$menushadow};
                        font-weight: bold; }

                .list .nowplaying {
                        background-color: {$accentbg};
                        font-weight: bold; }

                    .nowplaying > div {
                            background-color: {$accentbg}; }

                    .nowplaying:hover > div {
                            background-color: {$menubg}; }

                .list .file {
                        display: flex;
                        flex-wrap: nowrap;
                        justify-content: flex-start; }


                .list .file a {
                        display: block;
                        flex-grow: 1;
                        color: #333;
                        word-break: break-all;
                        text-decoration: none; }
                        
                .list .nowplaying a {
                        color: {$accentfg}; }

                .list .file a:active {
                        display: block;
                        color: #fff;
                        text-decoration: none; }

                .list .file .filebutton {
                        border-radius: 100%;
                        border: 0;
                        width: 25px;
                        min-width: 25px;
                        height: 25px;
                        min-height: 25px;
                        color: {$filebuttonfg};
                        text-align: center;
                        font-weight: normal;
                        margin: 0;
                        font-size: medium;
                        padding: 0;
                        display: block; }

                    .list .file .filebutton:hover {
                            color: {$accentfg};
                            background-color: {$accentbg}; }
                            
                .list .file .playlistdirectory {
                        width: 100%;
                        font-size: x-small; }

        @media screen and (max-width: 900px) and (orientation:portrait) {
                #player, #error, #header, .list div { width: 95%; }
                #albumart { width: 24vw; height: 24vw; }
                #songinfo div { height: 5vw; font-size: 4vw; }
                #player audio { height: 5vw; }
                #playlisttitle, #breadcrumbs, #passwordrequest, .buttons, .list { font-size: small; }
        }

        @media screen and (max-width: 900px) and (orientation:landscape) {
                #stickycontainer { position: static; }
                #player, #error, #header, .list div { width: 80%; }
                #albumart { width: 12vw; height: 12vw; }
                #songinfo div { height: 2.5vw; font-size: 2vw; }
                #player audio { height: 2.5vw; }
                #playlisttitle, #breadcrumbs, #passwordrequest, .buttons, .list { font-size: small; }
        }
    </style>
</head>

<body>

<div id="stickycontainer">
    <div id="playercontainer">
        <div id="player">
            <div id="albumart"></div>
            <div id="song">
                <div id="songinfo">
                    <div id="songTitle"><b>{$songtitle}</b></div>
                    <div id="artist">{$artist}</div>
                    <div id="album">{$album}<span id="year">{$year}</span></div>
                </div>
                <div id="audiocontainer">
                    <audio id="audio" autoplay controls{$songsrc}></audio>
                </div>
            </div>
        </div>
    </div>
    <div id="divider"></div>
</div>

<div id="error">{$error}</div>
<div id="interactioncontainer"></div>

</body>
</html>
HTML;
}

?>
