<?php

class WPSSTM_RadioKing{
    function __construct(){
        add_filter('wpsstm_wizard_services_links',array($this,'register_radioking_service_link'));
        add_action('wpsstm_live_tracklist_init',array($this,'register_radioking_preset'));
    }
    //register preset
    function register_radioking_preset($tracklist){
        new WPSSTM_RadioKing_Api_Preset($tracklist);
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
    
    private $station_slug;
    private $station_data =     null;

    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->station_slug = $this->get_station_slug();
        
        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title') );
        
    }
    
    function can_handle_url(){
        if ( !$this->station_slug ) return;
        return true;
    }

    function get_remote_url($url){
        
        if ( $this->can_handle_url() ){
            $station_id = $this->get_station_id();
            if ( is_wp_error($station_id) ) return $station_id;

            $url = sprintf('https://www.radioking.com/api/radio/%s/track/ckoi?limit=20',$station_id);
        }
        return $url;

    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'root > data'),
                'track_artist'      => array('path'=>'artist'),
                'track_album'       => array('path'=>'album'),
                'track_title'       => array('path'=>'title'),
                'track_image'       => array('path'=>'cover'),
            );
        }
        return $options;
    }


    function get_station_slug(){
        global $wpsstm_tracklist;
        $pattern = '~^https?://(?:.*\.)?radioking.com/radio/([^/]+)~i';
        preg_match($pattern, $wpsstm_tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_station_data(){
        
        if ( !$this->station_data ){
            $transient_name = 'wpsstm-radioking-' . $this->station_slug . '-data';

            if ( false === ( $station_data = get_transient($transient_name ) ) ) {
                $response = wp_remote_get( sprintf('https://www.radioking.com/api/radio/slug/%s',$this->station_slug) );
                $json = wp_remote_retrieve_body($response);
                if ( is_wp_error($json) ) return $json;
                $api = json_decode($json,true);
                if ( $station_data = wpsstm_get_array_value(array('data'), $api) ){
                    set_transient( $transient_name, $station_data, 1 * DAY_IN_SECONDS );
                }
            }
            $this->station_data = $station_data;
        }

        return $this->station_data;
        
    }
    
    function get_station_id(){
        $station_data = $this->get_station_data();
        if ( is_wp_error($station_data) ) return $station_data;
        
        return wpsstm_get_array_value(array('idradio'),$station_data);
    }

    function get_remote_title($title){
        if ( $this->can_handle_url() ){
            $station_data = $this->get_station_data();
            if ( !is_wp_error($station_data) ){
                $title = wpsstm_get_array_value(array('name'), $station_data);
            }
        }
        return $title;
    }
    
    protected function get_track_image($track_node){
        $selectors = $this->get_selectors( array('track_image'));
        
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