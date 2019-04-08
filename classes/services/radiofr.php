<?php

class WPSSTM_radiofr{
    static $api_key = '0f7572fe7ad5ed80c810fc9f3bbcaeb42df2cbc2';
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_radiofr_presets'));
        add_filter('wpsstm_wizard_service_links',array(__class__,'register_radiofr_service_link'));
    }
    
    //register preset
    function register_radiofr_presets($presets){
        $presets[] = new WPSSTM_RadioFR_Preset();
        return $presets;
    }

    static function register_radiofr_service_link($links){
        $item = sprintf('<a href="https://www.radio.fr" target="_blank" title="%s"><img src="%s" /></a>',__('radio.fr stations','wpsstm'),wpsstm()->plugin_url . '_inc/img/radiofr-icon.png');
        $links[] = $item;
        return $links;
    }
}

class WPSSTM_RadioFR_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;
    var $station_id;
    
    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
                'selectors' => array(
                'tracks'            => array('path'=>'> *'),
                'track_artist'      => array('path'=>'streamTitle','regex'=>'^(.*) -'),
                'track_title'       => array('path'=>'streamTitle','regex'=>'- (.*)$'),
                'track_image'       => array('path'=>'coverImageUrl100'),
            )
        );
        
        parent::__construct($url,$options);

    }
    
    public function init_url($url){

        if ( $this->station_slug = self::get_station_slug($url) ){
            $this->station_id = $this->get_station_id($this->station_slug);
        }

        if ( is_wp_error($this->station_id) ) return false;
        return $this->station_id;
    }

    static function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?radio.fr/s/([[\w\d-]+)~';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_station_id($station_slug){

        $transient_name = 'wpsstm-radiofr-' . $station_slug . '-id';

        if ( false === ( $station_id = get_transient($transient_name ) ) ) {

            $station_url = sprintf('https://www.radio.fr/s/%s',$station_slug);
            $response = wp_remote_get( $station_url );
            if ( is_wp_error($response) ) return $response;

            $response_code = wp_remote_retrieve_response_code( $response );
            if ($response_code != 200) return;

            $content = wp_remote_retrieve_body( $response );

            $pattern = '~"rank":.*,"id":([^,]+),~m';
            preg_match($pattern, $content, $matches);

            if ( !isset($matches[1]) ){
                return new WP_Error( 'wpsstm_radiofr_missing_station_id', __('Required station ID missing.','wpsstm') );
            }

            $station_id = $matches[1];
            set_transient( $transient_name, $station_id, 1 * DAY_IN_SECONDS );

        }

        return $station_id;

    }
    
    function get_remote_request_url(){
        return sprintf('https://api.radio.fr/info/v2/search/nowplayingbystations?stations=%s&apikey=%s&numberoftitles=%s',$this->station_id,WPSSTM_radiofr::$api_key,50);
    }

}

function wpsstm_radiofr_init(){
    new WPSSTM_radiofr();
}

add_action('wpsstm_load_services','wpsstm_radiofr_init');