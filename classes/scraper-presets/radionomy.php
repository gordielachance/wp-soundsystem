<?php

class WP_SoundSystem_Radionomy_Playlists_API extends WP_SoundSystem_URL_Preset{
    var $preset_slug =      'radionomy-station';
    var $preset_url =       'https://www.radionomy.com';
    private $station_slug;
    private $station_id;
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->station_slug = $this->get_station_slug();
        
        $this->scraper_options['selectors'] = array(
            'tracks'            => array('path'=>'tracks track'),
            'track_artist'      => array('path'=>'artists'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'cover'),
            //playduration
        );
        
    }

    function can_handle_url(){
        if ( !$this->station_slug ) return;
        return true;
    }

    function get_remote_url(){
        
        $station_id = $this->get_station_id();
        if ( is_wp_error($station_id) ) return $station_id;
        
        return sprintf('http://api.radionomy.com/tracklist.cfm?radiouid=%s&apikey=XXX&amount=20&type=xml&cover=true',$station_id);

    }

    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?radionomy.com/.*?/radio/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_station_id(){
        
        if (!$this->station_id){
            $transient_name = 'wpsstm-radionomy-' . $this->station_slug . '-id';

            if ( false === ( $station_id = get_transient($transient_name ) ) ) {

                $station_url = sprintf('http://www.radionomy.com/en/radio/%1$s',$this->station_slug);
                $response = wp_remote_get( $station_url );

                if ( is_wp_error($response) ) return;

                $response_code = wp_remote_retrieve_response_code( $response );
                if ($response_code != 200) return;

                $content = wp_remote_retrieve_body( $response );

                libxml_use_internal_errors(true);

                //QueryPath
                try{
                    $imagepath = htmlqp( $content, 'head meta[property="og:image"]', WP_SoundSystem_Remote_Tracklist::$querypath_options )->attr('content');
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
            $this->station_id = $station_id;
        }

        return $this->station_id;

    }
    
    function get_remote_title(){
        return sprintf('Radionomy: %s', $this->station_slug);
    }

}

//register preset
function register_radionomy_preset($presets){
    $presets[] = 'WP_SoundSystem_Radionomy_Playlists_API';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_radionomy_preset');