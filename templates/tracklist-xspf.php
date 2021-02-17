<?php

global $wpsstm_tracklist;

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

echo $wpsstm_tracklist->to_xspf();

exit;
