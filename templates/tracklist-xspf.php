<?php

global $wpsstm_tracklist;
$wpsstm_tracklist->populate_subtracks();

$tracklist = $wpsstm_tracklist;

$is_download = wpsstm_get_array_value('dl',$_REQUEST);
$is_download = filter_var($is_download, FILTER_VALIDATE_BOOLEAN);

if ( $is_download ){
    $now = current_time( 'timestamp', true );
    $filename = $post->post_name;
    $filename = sprintf('%s-%s.xspf',$filename,$now);
    header("Content-Type: application/xspf+xml");
    header('Content-disposition: attachment; filename="'.$filename.'"');
}else{
    header("Content-Type: text/xml");
}

require wpsstm()->plugin_dir . '_inc/php/xspf.php';

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
if ( $tracklist->have_subtracks() ) {
    while ( $tracklist->have_subtracks() ) {
        $tracklist->the_subtrack();
        global $wpsstm_track;
        $arr = $wpsstm_track->to_xspf_array();
        $xspf->addTrack($arr);
    }
}

echo $xspf->output();

exit;

