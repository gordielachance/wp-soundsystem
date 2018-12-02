<?php

class WPSSTM_IndieShuffle{
    function __construct(){
        add_action('wpsstm_before_remote_response',array(__class__,'register_indieshuffle_preset'));
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_indieshuffle_service_links'));
    }
    //register preset
    static function register_indieshuffle_preset($remote){
        new WPSSTM_IndieShuffle_Preset($remote);
    }

    static function register_indieshuffle_service_links($links){
        $links[] = array(
            'slug'      => 'indieshuffle',
            'name'      => 'indie shuffle',
            'url'       => 'https://www.indieshuffle.com/',
        );
        return $links;
    }
}

/*
Should try to support playlists and songs, eg.
https://www.indieshuffle.com/playlists/best-songs-of-april-2017/
https://www.indieshuffle.com/songs/hip-hop/
*/

class WPSSTM_IndieShuffle_Preset{

    function __construct($remote){
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
    }
    
    function can_handle_url($url){
        $domain = wpsstm_get_url_domain( $url );
        if ( $domain != 'indieshuffle.com') return;
        return true;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->redirect_url) ) return;
        $remote->options['selectors'] = array(
            'tracks'           => array('path'=>'#mainContainer .commontrack'),
            'track_artist'     => array('attr'=>'data-track-artist'),
            'track_title'      => array('attr'=>'data-track-title'),
            'track_image'      => array('path'=>'img','attr'=>'src'),
            'track_source_urls' => array('attr'=>'data-source'),
        );
    }

}

function wpsstm_indieshuffle(){
    new WPSSTM_IndieShuffle();
}

add_action('wpsstm_init','wpsstm_hypem');