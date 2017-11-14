<?php
class WP_SoundSystem_Preset_RadioKing_Api extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'radioking';
    var $preset_url =       'https://www.radioking.com';

    var $preset_options =  array(
        'datas_cache_min'   => 15,
        'selectors' => array(
            'tracks'            => array('path'=>'root > data'),
            'track_artist'      => array('path'=>'artist'),
            'track_album'       => array('path'=>'album'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'cover'),
        )
    );
    
    var $station_data =     null;

    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Radioking Stations','wpsstm');
    }
    
    function can_load_preset(){
        if ( !$station_slug = $this->get_station_slug() ) return;
        return true;
    }
    
    function get_remote_url(){

        $station_id = $this->get_station_id();
        if ( is_wp_error($station_id) ) return $station_id;

        return sprintf('https://www.radioking.com/api/radio/%s/track/ckoi?limit=20',$station_id);

    }

    function get_station_slug(){
        $pattern = '~^https?://(?:.*\.)?radioking.com/radio/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_station_data(){
        
        if ( !$station_slug = $this->get_station_slug() ){
            return new WP_Error( 'wpsstm_radioking_missing_station_slug', __('Required station slug missing.','wpsstm') );
        }
        
        $transient_name = 'wpsstm-radioking-' . $slug . '-data';

        if ( false === ( $station_data = get_transient($transient_name ) ) ) {
            $response = wp_remote_get( sprintf('https://www.radioking.com/api/radio/slug/%s',$slug) );
            $json = wp_remote_retrieve_body($response);
            if ( is_wp_error($json) ) return $json;
            $api = json_decode($json,true);
            if ( $station_data = wpsstm_get_array_value(array('data'), $api) ){
                set_transient( $transient_name, $station_data, 1 * DAY_IN_SECONDS );
            }
        }
        
        return $station_data;
        
    }
    
    function get_station_id(){
        $station_data = $this->get_station_data();
        if ( is_wp_error($station_data) ) return $station_data;
        
        return wpsstm_get_array_value(array('idradio'),$station_data);
    }

    function get_remote_title(){
        $station_data = $this->get_station_data();
        return wpsstm_get_array_value(array('name'), $station_data);
    }
    
    protected function get_track_image($track_node){
        $selectors = $this->get_options(array('selectors','track_image'));
        
        if ( $image_id = $this->get_track_node_content($track_node,$selectors) ){
           $image = sprintf('https://www.radioking.com/api/track/cover/%s?width=55&height=55',$image_id);
        }

        return $image;
    }

}

//register preset

function register_radioking_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_RadioKing_Api';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_radioking_preset');