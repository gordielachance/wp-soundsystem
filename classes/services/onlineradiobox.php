<?php

class WPSSTM_OnlineRadioBox{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array(__class__,'register_onlineradiobox_service_link'));
        add_action('wpsstm_before_remote_response',array(__class__,'register_onlineradiobox_preset'));
    }
    //register preset
    static function register_onlineradiobox_preset($remote){
        new WPSSTM_OnlineRadioBox_Preset($remote);
    }
    static function register_onlineradiobox_service_link($links){
        $links[] = array(
            'slug'      => 'onlineradiobox',
            'name'      => 'Online Radio Box',
            'url'       => 'http://onlineradiobox.com/',
            'pages'     => array(
                array(
                    'slug'      => 'playlists',
                    'name'      => __('playlists','wpsstm'),
                    'example'   => 'http://onlineradiobox.com/COUNTRY/RADIO_SLUG',
                ),
            )
        );
        return $links;
    }
}

class WPSSTM_OnlineRadioBox_Preset{

    function __construct($remote){
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        
    }
    
    function can_handle_url($url){
        if ( !$this->get_station_slug($url) ) return;
        return true;
    }
    
    function get_remote_url($url){
        if ( $this->can_handle_url($url) ){
            $station_slug = $this->get_station_slug($url);
            $url = sprintf('http://onlineradiobox.com/gr/%s/playlist',$station_slug);
        }
        return $url;
    }
    
    function set_selectors($remote){
        
        if ( !$this->can_handle_url($remote->redirect_url) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'.tablelist-schedule tr'),
            'track_artist'      => array('path'=>'a','regex'=>'(.+?)(?= - )'),
            'track_title'       => array('path'=>'a','regex'=>' - (.*)'),
        );
    }

    function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?onlineradiobox.com/[^/]+/([^/]+)/~i';
        preg_match($pattern,$url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}


function wpsstm_onlineradiobox_init(){
    new WPSSTM_OnlineRadioBox();
}

add_action('wpsstm_init','wpsstm_onlineradiobox_init');