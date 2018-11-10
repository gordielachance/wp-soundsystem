<?php
class WPSSTM_Slacker{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_slacker_service_links'));
        add_action('wpsstm_before_remote_response',array($this,'register_slacker_preset'));
    }
    //register preset
    function register_slacker_preset($tracklist){
        new WPSSTM_Slacker_Preset($tracklist);
    }
    function register_slacker_service_links($links){
        $links[] = array(
            'slug'      => 'slacker',
            'name'      => 'Slacker',
            'url'       => 'http://www.slacker.com',
            'pages'     => array(
                array(
                    'slug'      => 'stations',
                    'name'      => __('stations','wpsstm'),
                    'example'   => 'http://www.slacker.com/station/STATION_SLUG',
                ),
            )
        );
        return $links;
    }
}
class WPSSTM_Slacker_Preset{

    function __construct($remote){
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
    }
    
    function can_handle_url($url){
        $station_slug = $this->get_station_slug($url);
        if (!$station_slug ) return;
        return true;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->feed_url_no_filters) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
            'track_artist'      => array('path'=>'span.artist'),
            'track_title'       => array('path'=>'span.title')
        );
    }

    function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?slacker.com/station/([^/]+)/?~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }


}


function wpsstm_slacker_init(){
    new WPSSTM_Slacker();
}

//TO FIX TO REPAIR add_action('wpsstm_init','wpsstm_reddit_init');