<?php
header( 'content-type: text/html; charset:utf-8' );
error_reporting(0);

/*
2019-01-19 0.1.0
- Persistent music player for single files allowing free simultaneous browsing

todo:
- store currently playing file in cookie to be able to persistently highlight it
- playlist stored in cookie
  - add files from browser (after current)
  - remove files in playlist view
  - reorder files in playlist view
  - when you select a file, add all following files to playlist (?)
    - or: separate "current directory only mode" (?)
  - when audio ends: go to next file in playlist
*/


$allowedExtensions = array( 'mp3', 'flac' );

if( !empty( $_GET['play'] ) ) {
    ### playing the indicated song and browsing to its directory

    $song = sanitizeString( $_GET['play'] );

    if ( is_file( $song ) ) {
        # obtaining song info and setting current directory
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
                echo "<div class=\"file\"><a href=\"?play={$link}\">&#x25ba; {$file}</a></div>";
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

    # hiding error message div if there is no message to display
    $msgDisplay = empty( $msg ) ? 'none' : 'block';

    # setting player layout depending on available information
    if ( empty( $songInfo ) ) {
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
                font-family: sans-serif;
                background-color: #aaa; }

        #playercontainer {
                padding: 10px 0;
                background-color: #333;
                background-image: linear-gradient(#2a2a2a, #555); }

            #player {
                    width: 60%;
                    margin: 0 auto;
                    display: flex;
                    box-sizing: border-box;
                    padding: 10px;
                    background-color: #111; }

                #albumart {
                        display: {$artDisplay};
                        width: 9vw;
                        min-width: 9vw;
                        height: 9vw;
                        margin-right: 10px;
                        background: #996 url({$art});
                        background-size: contain; }

                #song {
                        flex-grow: 1;
                        display: flex;
                        flex-direction: column;
                        justify-content: space-between; }

                    #songinfo {
                            color: grey;
                            text-align: {$songInfoalign};
                            font-size: 1.5vw; }

                        #songinfo div {
                                height: 1.75vw;
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
                            height: 1.5vw;
                            margin-top: 1.5vw; }

        #divisor {
                height: 10px;
                background-color: #996;
                margin-bottom: 10px; }

        #message {
                box-sizing: border-box;
                width: 60%;
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
                    width: 60%;
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
                    width: 60%;
                    box-sizing: border-box;
                    margin: 0 auto;
                    padding: 10px 10px;
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

                .list .file a {
                        display: block;
                        color: #333;
                        text-decoration: none; }

                .list .file a:active {
                        display: block;
                        color: #fff;
                        text-decoration: none; }
    </style>
</head>

<body>

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
                <audio autoplay controls>
                    <source src="{$song}" />
                </audio>
            </div>
        </div>
    </div>
</div>

<div id="divisor"></div>
<div id="message">{$msg}</div>
<div id="interactioncontainer"></div>

</body>
</html>
HTML;
}

?>