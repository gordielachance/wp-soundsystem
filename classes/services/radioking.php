<?php

class WPSSTM_RadioKing{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_radioking_service_link'));
        add_action('wpsstm_before_remote_response',array($this,'register_radioking_preset'));
    }
    //register preset
    function register_radioking_preset($remote){
        new WPSSTM_RadioKing_Api_Preset($remote);
    }
    function register_radioking_service_link($links){
        $links[] = array(
            'slug'      => 'radioking',
            'name'      => 'RadioKing',
            'url'       => 'https://www.radioking.com',
            'pages'     => array(
                array(
                    'slug'      => 'playlists',
                    'name'      => __('playlists','wpsstm'),
                    'example'   => 'https://www.radioking.com/radio/RADIO_SLUG',
                ),
            )
        );
        return $links;
    }
}

class WPSSTM_RadioKing_Api_Preset{

    function __construct($remote){
        add_filter( 'wpsstm_live_tracklist_url',array($this,'web_to_api_url') );
        add_action( 'wpsstm_did_remote_response',array($this,'set_selectors') );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title'),10,2 );
        add_filter( 'wpsstm_live_tracklist_track_image',array($this,'get_remote_track_image'),10,3 );
    }
    
    function can_handle_url($url){
        if ( !$this->get_station_slug($url) ) return;
        return true;
    }
    
    function is_api_url($url){
        $pattern = '~^http(?:s)?://(?:www\.)?radioking.com/api/(.*)~i';
        preg_match($pattern,$url, $matches);
        return ( !empty($matches) );
    }

    function web_to_api_url($url){
        
        if ( $this->can_handle_url($url) ){
            $station_id = $this->get_station_id($url);
            if ( is_wp_error($station_id) ) return $station_id;

            $url = sprintf('https://www.radioking.com/api/radio/%s/track/ckoi?limit=20',$station_id);
        }
        return $url;

    }
    
    function set_selectors($remote){
        
        if ( !$this->is_api_url($remote->redirect_url) ) return;
        $remote->options['selectors'] = array(
            'tracks'            => array('path'=>'root > data'),
            'track_artist'      => array('path'=>'artist'),
            'track_album'       => array('path'=>'album'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'cover'),
        );
    }

    function get_station_slug($url){
        $pattern = '~^https?://(?:.*\.)?radioking.com/radio/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_station_data($url){

        $station_slug = $this->get_station_slug($url);
        $transient_name = 'wpsstm-radioking-' . $station_slug . '-data';

        if ( false === ( $station_data = get_transient($transient_name ) ) ) {
            $response = wp_remote_get( sprintf('https://www.radioking.com/api/radio/slug/%s',$station_slug) );
            $json = wp_remote_retrieve_body($response);
            if ( is_wp_error($json) ) return $json;
            $api = json_decode($json,true);
            if ( $station_data = wpsstm_get_array_value(array('data'), $api) ){
                set_transient( $transient_name, $station_data, 1 * DAY_IN_SECONDS );
            }
        }
        return $station_data;

    }
    
    function get_station_id($url){
        $station_data = $this->get_station_data($url);
        if ( is_wp_error($station_data) ) return $station_data;
        
        return wpsstm_get_array_value(array('idradio'),$station_data);
    }

    function get_remote_title($title,$remote){
        if ( $this->is_api_url($remote->redirect_url) ){
            $station_data = $this->get_station_data($remote->url);
            if ( !is_wp_error($station_data) ){
                $title = wpsstm_get_array_value(array('name'), $station_data);
            }
        }
        return $title;
    }
    
    //TOUFIX TOUCHECK
    protected function get_remote_track_image($image,$track_node,$remote){
        $selectors = $remote->get_selectors( array('track_image'));
        
        if ( $image_id = $this->parse_node($track_node,$selectors) ){
           $image = sprintf('https://www.radioking.com/api/track/cover/%s?width=55&height=55',$image_id);
        }

        return $image;
    }

}


function wpsstm_radioking_init(){
    new WPSSTM_RadioKing();
}

add_action('wpsstm_init','wpsstm_onlineradiobox_init');