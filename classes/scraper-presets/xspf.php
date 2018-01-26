<?php

function wpsstm_xspf_tracklist_options($options,$tracklist){

    if ( !$tracklist->response_type ) return $options; // remote content not yet fetched
    
    $split = explode('/',$tracklist->response_type);

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

function register_xspf_service_link($links){
    $links[] = array(
        'slug'      => 'xspf',
        'name'      => 'XSPF',
        'url'       => false,
        'pages'     => array(
            array(
                'slug'      => 'playlists',
                'name'      => __('playlists','wpsstm'),
                'example'   => 'http://.../FILE.xspf',
            ),
        )
    );
    return $links;
}
add_filter('wpsstm_wizard_services_links','register_xspf_service_link');
add_filter('wpsstm_live_tracklist_scraper_options','wpsstm_xspf_tracklist_options',20,2); //priority after presets