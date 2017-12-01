<?php
class WP_SoundSystem_Hypem_Scraper{

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
    }
    
    function can_handle_url(){
        $domain = wpsstm_get_url_domain( $this->tracklist->feed_url );
        if ( $domain != 'hypem.com') return;
        return true;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'.section-track'),
                'track_artist'      => array('path'=>'.track_name .artist'),
                'track_title'       => array('path'=>'.track_name .track'),
                //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
            );
        }
        return $options;
    }

}

//register preset
function register_hypem_preset($tracklist){
    new WP_SoundSystem_Hypem_Scraper($tracklist);
}

function register_hypem_service_links($links){
    $links[] = array(
        'slug'      => 'hypem',
        'name'      => 'Hypem',
        'url'       => 'https://www.hypem.com',
    );
    return $links;
}

add_action('wpsstm_get_remote_tracks','register_hypem_preset');
add_filter('wpsstm_wizard_services_links','register_hypem_service_links');