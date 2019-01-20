<?php
header( 'content-type: text/html; charset:utf-8' );
error_reporting(0);

/*
2019-01-20 0.1.5
- Made design responsive
- Made player sticky on top
- Made player width customisable
- Highlighted current song in directory index
- Added background image

2019-01-19 0.1.0
- Persistent music player for single files allowing free simultaneous browsing

todo:
- playlist stored in cookie
  - add files from browser (after current)
  - remove files in playlist view
  - reorder files in playlist view
  - when you select a file, add all following files to playlist (?)
    - or: separate "current directory only mode" (?)
  - when audio ends: go to next file in playlist


N O T E S

cookies:
_playmode = browse|playlist
_playlist_browse = json array of current directory's songs
_playlist_playlist = json array of current playlist
_currentdir

when requesting play from browser, playmode = browse, populate _playlist_browse with rest of the directory
when requesting play from playlist, playmode = playlist

update _currentdir for every browse

AJAX call ?skip=1 ?skip=-1 to play next/previous; depending on _playmode, get from different _playlist; get ?play= url to redirect to

always browse to _currentdir if not _viewmode = playlist
    if _viewmode == playlist:
        window.onload get playlist
    elseif isset _currentdir
        window.onload browse currentdir
    elseif ?play=song
        window.onload browse song directory
    else
        window.onload browse .

    window.onload = {$onload};


themes:
$backgroundimg = background image name
eval($theme[$backgroundimg]) containing all colour variables
*/


$allowedExtensions = array( 'mp3', 'flac' );

$width = '60%';
$backgroundimg = 'bg.jpg';
$accent = '#fc0';

if( !empty( $_GET['play'] ) ) {
    ### playing the indicated song and browsing to its directory

    $song = sanitizeString( $_GET['play'] );

    if ( is_file( $song ) ) {
        # obtaining song info and setting current directory
        setcookie( 'noctifermusic_nowplaying', $song, strtotime( '+1 day' ) );
        $songInfo = getsonginfo( $song );
        $dir = dirname( $song );
        $msg = '';
    } else {
        # defaulting to root directory and displaying error message
        $songInfo = array();
        $dir = '.';
        $msg = "Could not find file {$song}.";
        $song = '';
    }

    renderPage( $song, $dir, $msg, $songInfo );
} elseif( !empty( $_GET['dir'] ) )  {
    ### responding to AJAX request for directory contents

    $basedir = sanitizeString( $_GET['dir'] );

    $fileList = array();
    $dirList = array();

    # browsing given directory
    if ( is_dir( $basedir ) ) {
        if ( $dh = opendir( $basedir ) ) {
            while ( $itemName = readdir( $dh ) ) {
                # ignoring certain files
                if ( $itemName != '.' && $itemName != '..' && $itemName != '.htpasswd' && $itemName != '.htaccess' ) {
                    if ( is_file( $basedir . '/' . $itemName ) ) {
                        # found a file: adding allowed files to file array
                        $info = pathinfo( $itemName );
                        if ( isset( $info['extension'] ) && in_array( strtolower( $info['extension'] ), $allowedExtensions ) ) {
                            $fileList[] = $info['filename'] . '.' . $info['extension'];
                        }
                    } else if ( is_dir( $basedir . '/' . $itemName ) ) {
                        # found a directory: adding to directory array
                        $dirList[] = $itemName;
                    }
                }
            }
            closedir($dh);
        }

        # returning breadcrumbs
        $breadcrumbs = explode( '/', $basedir );

        echo '<div id="breadcrumbs">';
        for ( $i = 0; $i != sizeof( $breadcrumbs ); $i++ ) {
            $title = $breadcrumbs[$i] == '.'  ? 'Root'  : $breadcrumbs[$i];

            if ($i == sizeof($breadcrumbs) - 1) {
                # current directory
                echo "<span id=\"breadcrumbactive\">{$title}</span>";
            } else {
                # previous directories with link
                $link = implode( '/', array_slice( $breadcrumbs, 0, $i+1 ) );
                echo "<span class=\"breadcrumb\" onclick=\"gotodir('{$link}');\">{$title}</span><span class=\"separator\">/</span>";
            }
        }
        echo '</div>';

        # returning directory list
        if ( !empty( $dirList ) ) {
            echo '<div id="dirlist" class="list">';
            foreach ( $dirList as $dir ) {
                $link = $basedir . '/' . $dir;
                echo "<div class=\"dir\" onclick=\"gotodir('{$link}');\">{$dir}</div>";
            }
            echo '</div>';
        }

        # returning file list
        if ( !empty( $fileList ) ) {
            echo '<div id="filelist" class="list">';
            foreach ( $fileList as $file ) {
                $link = $basedir . '/' . $file;
                if ( isset( $_COOKIE['noctifermusic_nowplaying'] ) && $_COOKIE['noctifermusic_nowplaying'] == $link ) {
                    echo "<div class=\"file nowplaying\"><a href=\"?play={$link}\">&#x25ba; {$file}</a></div>";
                } else {
                    echo "<div class=\"file\"><a href=\"?play={$link}\">&#x25ba; {$file}</a></div>";
                }
            }
            echo '</div>';
        }
    }
} else {
    ### rendering default site
    renderPage();
}


function getSongInfo( $song ) {
    ### if available, using getID3 to extract song info

    if ( file_exists( './getid3/getid3.php' ) ) {
        # getting song info
        require_once( './getid3/getid3.php' );
        $getID3 = new getID3;
        $fileInfo = $getID3->analyze( $song );
        getid3_lib::CopyTagsToComments( $fileInfo );

        # extracting song title, or defaulting to file name
        if ( isset( $fileInfo['comments_html']['title'][0] ) && !empty( trim( $fileInfo['comments_html']['title'][0] ) ) ) {
            $title = trim( $fileInfo['comments_html']['title'][0] );
        } else {
            $title = basename( $song );
        }

        # extracting song artist, or defaulting to directory name
        if ( isset( $fileInfo['comments_html']['artist'][0] ) && !empty( trim( $fileInfo['comments_html']['artist'][0] ) ) ) {
            $artist = trim( $fileInfo['comments_html']['artist'][0] );
        } else {
            $artist = dirname( $song );
        }

        # extracting song album
        if ( isset( $fileInfo['comments_html']['album'][0] ) && !empty( trim( $fileInfo['comments_html']['album'][0] ) ) ) {
            $album = trim( $fileInfo['comments_html']['album'][0] );
        } else {
            $album = '';
        }

        # extracting song year/date
        if ( isset( $fileInfo['comments_html']['year'][0] ) && !empty( trim( $fileInfo['comments_html']['year'][0] ) ) ) {
            $year = trim( $fileInfo['comments_html']['year'][0] );
        } elseif ( isset($fileInfo['comments_html']['date'][0] ) && !empty( trim( $fileInfo['comments_html']['date'][0] ) ) ) {
            $year = trim( $fileInfo['comments_html']['date'][0] );
        } else {
            $year = '';
        }

        # extracting song picture
        if ( isset( $fileInfo['comments']['picture'][0] ) ) {
            $art = 'data:'.$fileInfo['comments']['picture'][0]['image_mime'].';charset=utf-8;base64,'.base64_encode( $fileInfo['comments']['picture'][0]['data'] );
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


function sanitizeString( $str ) {
    $str = stripslashes( $str );
	return $str;
}


function compareName( $a, $b ) {
    # name comparison for usort
    return strnatcasecmp( $a['name'], $b['name'] );
}


function renderPage( $song = '', $dir = '.', $msg = '', $songInfo = array() ) {

    global $width, $backgroundimg, $accent;

    # hiding error message div if there is no message to display
    $msgDisplay = empty( $msg ) ? 'none' : 'block';

    # setting player layout depending on available information
    if ( empty( $songInfo ) ) {
        # no information means no file is playing
        setcookie( 'noctifermusic_nowplaying', $song, strtotime( '-1 day' ) );

        # hiding info elements
        $songTitle = 'No file playing';
        $songInfoalign = 'center';

        $artist = '';
        $artistDisplay = 'none';

        $album = '';
        $albumDisplay = 'none';

        $year = '';
        $yearDisplay = 'none';

        $art = '';
        $artDisplay = 'none';

        $pageTitle = "Music";
    } else {
        # displaying info elements where available
        $songTitle = $songInfo['title'];
        $pageTitle = $songTitle;
        if ( !empty( $songInfo['artist'] ) ) {
            $artist = $songInfo['artist'];
            $artistDisplay = 'block';
            $pageTitle = "$artist - $pageTitle";
        } else {
            $artistDisplay = 'none';
        }
        if ( !empty( $songInfo['album'] ) ) {
            $album = $songInfo['album'];
            $albumDisplay = 'block';
        } else {
            $album = '';
            $albumDisplay = 'none';
        }
        if ( !empty( $songInfo['year'] ) ) {
            $year = $songInfo['year'];
            $yearDisplay = 'inline-block';
        } else {
            $year = '';
            $yearDisplay = 'none';
        }
        if ( !empty( $songInfo['art'] ) ) {
            $art = $songInfo['art'];
            $artDisplay = 'block';
            $songInfoalign = 'left';
        } else {
            $art = '';
            $artDisplay = 'none';
            $songInfoalign = 'center';
        }
    }

    # writing page
    echo <<<HTML
<!doctype html>

<html lang="en" prefix="og: http://ogp.me/ns#">
<head>
    <meta charset="utf-8" />

    <title>{$pageTitle}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0" id="viewport" />

    <script>
        function gotodir(dir){
            if (window.XMLHttpRequest) {
                xmlhttp = new XMLHttpRequest();
            }

            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200){
                    document.getElementById("interactioncontainer").innerHTML = xmlhttp.responseText;
                }
            }

            xmlhttp.open("GET","index.php?dir="+dir,true);

            xmlhttp.send();
        }

        window.onload = gotodir('{$dir}');
    </script>

    <style>
        html, body {
                width: 100%;
                margin: 0px; padding: 0px;
                font-family: sans-serif; }

            html {
                    background: #bbb url('{$backgroundimg}') no-repeat fixed center top;
                    background-size: cover; }

            body {
                    min-height: 100vh;
                    box-sizing: border-box;
                    padding-bottom: 5px;
                    background-color: rgba(0, 0, 0, 0.25); }

        #stickycontainer {
                position: sticky;
                top: 0;
                margin-bottom: 20px; }

            #playercontainer {
                    padding: 20px 0;
                    background-color: #333;
                    background-image: linear-gradient(#2a2a2a, #555); }

                #player {
                        width: {$width};
                        margin: 0 auto;
                        display: flex;
                        box-sizing: border-box;
                        padding: 10px;
                        background-color: #111; }

                    #albumart {
                            display: {$artDisplay};
                            width: 7vw;
                            height: 7vw;
                            margin-right: 10px;
                            background: #996 url({$art});
                            background-size: contain; }

                    #song {
                            flex-grow: 1;
                            display: flex;
                            flex-direction: column;
                            justify-content: space-between; }

                        #songinfo { }

                            #songinfo div {
                                    color: grey;
                                    text-align: {$songInfoalign};
                                    font-size: 1.2vw;
                                    height: 1.4vw;
                                    width: 100%;
                                    overflow: hidden; }

                            #artist {
                                    display: {$artistDisplay}; }

                            #album {
                                    display: {$albumDisplay}; }

                            #year {
                                    margin-left: .35em;
                                    display: {$yearDisplay}; }

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
                        background-color: {$accent}; }

        #message {
                box-sizing: border-box;
                width: {$width};
                display: {$msgDisplay};
                color: white;
                text-align: center;
                margin: 0 auto;
                background-color: #a00;
                padding: 10px; }

        #interactioncontainer {
                box-sizing: border-box;
                line-height: 1.5; }

            #breadcrumbs {
                    width: {$width};
                    margin: 0 auto 10px auto;
                    color: #333;
                    background-color: #eee; }

                #breadcrumbs .breadcrumb, #breadcrumbs #breadcrumbactive {
                        display: inline-block;
                        padding: 10px; }

                #breadcrumbs .separator {
                        color: #bbb;
                        padding: 0 5px; }

                #breadcrumbs .breadcrumb:hover {
                        cursor: pointer;
                        background-color: #ddd; }

                #breadcrumbs #breadcrumbactive {
                        font-weight: bold; }

            .list div {
                    width: {$width};
                    box-sizing: border-box;
                    margin: 0 auto;
                    padding: 5px 10px;
                    color: #333;
                    cursor: pointer;
                    background-color: #eee;
                    border-bottom: 1px solid #ddd; }

                .list div:last-child {
                        margin-bottom: 10px;
                        border: 0; }

                .list div:hover {
                        background-color: #ddd;
                        font-weight: bold; }

                .list .nowplaying {
                        background-color: {$accent};
                        font-weight: bold; }

                .list .file a {
                        display: block;
                        color: #333;
                        text-decoration: none; }

                .list .file a:active {
                        display: block;
                        color: #fff;
                        text-decoration: none; }

        @media screen and (max-width: 900px) {
                #player, #message, #breadcrumbs, .list div { width: 95%; }
                #albumart { width: 20vw; height: 20vw; }
                #songinfo div { height: 4vw; font-size: 3.5vw; }
                #player audio { height: 4vw; }
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
                    <div id="songTitle"><b>{$songTitle}</b></div>
                    <div id="artist">{$artist}</div>
                    <div id="album">{$album}<span id="year">{$year}</span></div>
                </div>
                <div id="audiocontainer">
                    <audio controls>
                        <source src="{$song}" />
                    </audio>
                </div>
            </div>
        </div>
    </div>
    <div id="divider"></div>
</div>

<div id="message">{$msg}</div>
<div id="interactioncontainer"></div>

</body>
</html>
HTML;
}

?>