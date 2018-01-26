<?php

/*
Should try to support playlists and songs, eg.
https://www.indieshuffle.com/playlists/best-songs-of-april-2017/
https://www.indieshuffle.com/songs/hip-hop/
*/

class WPSSTM_IndieShuffle_Scraper{

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
    }
    
    function can_handle_url(){
        $domain = wpsstm_get_url_domain( $this->tracklist->feed_url );
        if ( $domain != 'indieshuffle.com') return;
        return true;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
            'tracks'           => array('path'=>'#mainContainer .commontrack'),
            'track_artist'     => array('attr'=>'data-track-artist'),
            'track_title'      => array('attr'=>'data-track-title'),
            'track_image'      => array('path'=>'img','attr'=>'src'),
            'track_source_urls' => array('attr'=>'data-source'),
        );
        }
        return $options;
    }

}

//register preset
function register_indieshuffle_preset($tracklist){
    new WPSSTM_IndieShuffle_Scraper($tracklist);
}

function register_indieshuffle_service_links($links){
    $links[] = array(
        'slug'      => 'indieshuffle',
        'name'      => 'indie shuffle',
        'url'       => 'https://www.indieshuffle.com/',
    );
    return $links;
}

add_action('wpsstm_get_remote_tracks','register_indieshuffle_preset');
add_filter('wpsstm_wizard_services_links','register_indieshuffle_service_links');