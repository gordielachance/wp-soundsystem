<?php

function wpsstm_xspf_tracklist_options($options,$tracklist){

    if ( !$response_type = $tracklist->get_response_type() ) return $options; // remote content not yet fetched
    
    $split = explode('/',$response_type);

    if ( isset($split[1]) && ($split[1]=='xspf+xml') ){
        $options['selectors'] = array(
            'tracklist_title'   => array('path'=>'title'),
            'tracks'            => array('path'=>'trackList track'),
            'track_artist'      => array('path'=>'creator'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
            'track_source_urls' => array('path'=>'location'),
            'track_image'       => array('path'=>'image')
        );
    }
    
    return $options;
}
add_filter('wpsstm_tracklist_options','wpsstm_xspf_tracklist_options',20,2); //priority after presets