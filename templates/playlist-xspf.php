<?php

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

$tracklist = wpsstm_get_post_tracklist();

foreach ( $tracklist->tracks as $track){
    $arr = $track->get_array_for_xspf();
    $xspf->addTrack($arr);
}

echo $xspf->output();

die();

