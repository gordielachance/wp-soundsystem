<?php

global $wpsstm_tracklist;
$wpsstm_tracklist->options['ajax_refresh'] = false;
$wpsstm_tracklist->populate_subtracks(array('posts_per_page'=>-1));

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
if ( $title = $tracklist->get_title() ){
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
if ( $tracklist->have_tracks() ) {
    $track_position = 0;
    while ( $tracklist->have_tracks() ) {
        $tracklist->the_track();
        global $wpsstm_track;
        $arr = $wpsstm_track->to_xspf_array();
        $xspf->addTrack($arr);
    }
}

echo $xspf->output();

exit();

