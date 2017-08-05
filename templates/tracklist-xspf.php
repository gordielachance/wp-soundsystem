<?php

the_post();

global $wpsstm_tracklist;
$tracklist = $wpsstm_tracklist;


if ( isset($_REQUEST['download']) && ((bool)$_REQUEST['download'] == true) ){
    $filename = $post->post_name;
    $filename = sprintf('%1$s.xspf',$filename);
    header("Content-Type: application/xspf+xml");
    header('Content-disposition: attachment; filename="'.$filename.'"');
}else{
    header("Content-Type: text/xml");
}


require wpsstm()->plugin_dir . 'classes/wpsstm-playlist-xspf.php';

$xspf = new mptre\Xspf();



//playlist
if ( $title = get_the_title() ){
    $xspf->addPlaylistInfo('title', $title);
}

if ( $author = $tracklist->author ){
    $xspf->addPlaylistInfo('creator', $author);
}

if ( $timestamp = $tracklist->updated_time ){
    $date = gmdate(DATE_ISO8601,$timestamp);
    $xspf->addPlaylistInfo('date', $date);
}

if ( $location = $tracklist->location ){
    $xspf->addPlaylistInfo('location', $location);
}

$annotation = sprintf( __('Station generated with the %s plugin — %s','wpsstm'),'WP SoundSystem','https://wordpress.org/plugins/wp-soundsystem/');
$xspf->addPlaylistInfo('annotation', $annotation);


//subtracks
$subtracks_query = $tracklist->query_subtracks();
if ( $subtracks_query->have_posts() ) {
    $track_position = 0;
    while ( $subtracks_query->have_posts() ) {
        $subtracks_query->the_post();
        global $wpsstm_track;
        $track_position++;
        $wpsstm_track->position = $track_position;
        $arr = $wpsstm_track->to_xspf_array();
        $xspf->addTrack($arr);
    }
}

echo $xspf->output();

exit();

