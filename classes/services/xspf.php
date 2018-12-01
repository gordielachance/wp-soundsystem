<?php

class WPSSTM_XSPF{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_xspf_service_link'));
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors'),20); //lower priority
    }
    function set_selectors($remote){

        if ( !$remote->response_type ) return; // remote content not yet fetched

        $split = explode('/',$remote->response_type);

        if ( isset($split[1]) && ($split[1]=='xspf+xml') ){
            $remote->options['selectors'] = array(
                'tracklist_title'   => array('path'=>'title'),
                'tracks'            => array('path'=>'trackList track'),
                'track_artist'      => array('path'=>'creator'),
                'track_title'       => array('path'=>'title'),
                'track_album'       => array('path'=>'album'),
                'track_source_urls' => array('path'=>'location'),
                'track_image'       => array('path'=>'image')
            );
        }
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
}

function wpsstm_xspf_init(){
    new WPSSTM_XSPF();
}

add_action('wpsstm_init','wpsstm_xspf_init');