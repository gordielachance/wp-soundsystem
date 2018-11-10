<?php
class WPSSTM_Hypem{
    function __construct(){
        add_action('wpsstm_live_tracklist_init',array($this,'register_hypem_preset'));
        add_filter('wpsstm_wizard_services_links',array($this,'register_hypem_service_links'));
    }
    //register preset
    function register_hypem_preset($tracklist){
        new WPSSTM_Hypem_Preset($tracklist);
    }

    function register_hypem_service_links($links){
        $links[] = array(
            'slug'      => 'hypem',
            'name'      => 'Hypem',
            'url'       => 'https://www.hypem.com',
        );
        return $links;
    }
}

class WPSSTM_Hypem_Preset{

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
    }
    
    function can_handle_url(){
        $domain = wpsstm_get_url_domain( $this->tracklist->feed_url );
        if ( $domain != 'hypem.com') return;
        return true;
    }
    
    function set_selectors($datas){
        
        if ( !$this->can_handle_url() ) return;
        
        $datas->options['selectors'] = array(
            'tracks'            => array('path'=>'.section-track'),
            'track_artist'      => array('path'=>'.track_name .artist'),
            'track_title'       => array('path'=>'.track_name .track'),
            //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
        );
    }

}


function wpsstm_hypem(){
    new WPSSTM_Hypem();
}

add_action('wpsstm_init','wpsstm_hypem');