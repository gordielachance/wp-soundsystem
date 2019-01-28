<?php

class WPSSTM_Radionomy{
    function __construct(){
        add_filter('wpsstm_wizard_service_links',array($this,'register_radionomy_service_links'), 8);
        add_filter('wpsstm_remote_presets',array($this,'register_radionomy_preset'));
    }
    
    //register preset
    function register_radionomy_preset($presets){
        $presets[] = new WPSSTM_Radionomy_API_Preset();
        return $presets;
    }
    
    function register_radionomy_service_links($links){
        $item = sprintf('<a href="https://www.radionomy.com" target="_blank" title="%s"><img src="%s" /></a>',__('Radionomy stations','wpsstm'),wpsstm()->plugin_url . '_inc/img/radionomy-icon.jpg');
        $links[] = $item;
        return $links;
    }
}

class WPSSTM_Radionomy_API_Preset extends WPSSTM_Remote_Tracklist{
    
    var $station_slug;
    var $station_id;
    
    function __construct($url = null,$options = null) {
        
        $this->preset_options = array(
            'selectors' => array(
                'tracks'            => array('path'=>'tracks track'),
                'track_artist'      => array('path'=>'artists'),
                'track_title'       => array('path'=>'title'),
                'track_image'       => array('path'=>'cover'),
                //playduration
            )
        );
        
        parent::__construct($url,$options);

    }
    
    function init_url($url){
        if ( $this->station_slug = $this->get_website_url_station_slug($url) ) {
            $this->station_id = $this->get_station_id($this->station_slug);
        }
        
        if ( is_wp_error($this->station_id) ) return false;
        return $this->station_id;
    }

    function get_website_url_station_slug($url){
        $pattern = '~^https?://(?:www.)?radionomy.com/.*?/radio/([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_request_url(){
        return sprintf('http://api.radionomy.com/tracklist.cfm?radiouid=%s&apikey=XXX&amount=20&type=xml&cover=true',$this->station_id);
    }

    function get_station_id($station_slug){

        $transient_name = 'wpsstm-radionomy-' . $station_slug . '-id';

        if ( false === ( $station_id = get_transient($transient_name ) ) ) {

            $station_url = sprintf('http://www.radionomy.com/en/radio/%1$s',$station_slug);
            $response = wp_remote_get( $station_url );

            if ( is_wp_error($response) ) return;

            $response_code = wp_remote_retrieve_response_code( $response );
            if ($response_code != 200) return;

            $content = wp_remote_retrieve_body( $response );

            libxml_use_internal_errors(true);

            //QueryPath
            try{
                $imagepath = htmlqp( $content, 'head meta[property="og:image"]', WPSSTM_Remote_Tracklist::$querypath_options )->attr('content');
            }catch(Exception $e){
                return false;
            }

            libxml_clear_errors();

            $image_file = basename($imagepath);

            $pattern = '~^([^.]+)~';
            preg_match($pattern, $image_file, $matches);

            if ( !isset($matches[1]) ){
                return new WP_Error( 'wpsstm_radionomy_missing_station_id', __('Required station ID missing.','wpsstm') );
            }

            $station_id = $matches[1];
            set_transient( $transient_name, $station_id, 1 * DAY_IN_SECONDS );

        }

        return $station_id;

    }
    
    function get_remote_title(){
        return sprintf('Radionomy: %s', $this->station_slug);
    }

}

function wpsstm_radionomy_init(){
    new WPSSTM_Radionomy();
}

add_action('wpsstm_init','wpsstm_radionomy_init');