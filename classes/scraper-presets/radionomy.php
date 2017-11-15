<?php

/*
http://api.radionomy.com/currentsong.cfm?radiouid=0f973ea3-2059-482d-993d-d43e8c5d6a1a&type=xml&cover=yes&callmeback=yes&defaultcover=yes&streamurl=yes&zoneid=0&countrycode=&size=90&dynamicconf=yes&cachbuster=144626

http://api.radionomy.com/tracklist.cfm?radiouid=0f973ea3-2059-482d-993d-d43e8c5d6a1a&apikey=XXX&amount=10&type=xml&cover=true

*/

class WP_SoundSystem_Preset_Radionomy_Playlists_API extends WP_SoundSystem_Live_Playlist_Preset{
    //api max tracks = 40
    var $remote_url = 'http://api.radionomy.com/tracklist.cfm?radiouid=%radionomy-id%&apikey=XXX&amount=20&type=xml&cover=true';
    
    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'tracks track'),
            'track_artist'      => array('path'=>'artists'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'cover'),
            //playduration
        )
    );
    var $preset_slug =      'radionomy';
    var $preset_url =       'https://www.radionomy.com';

    
    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Radionomy Stations','wpsstm');
    }
    
    function can_load_feed(){
        if ( !$slug = $this->get_station_slug() ) return;
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

        if ( !$slug = $this->get_station_slug() ){
            return new WP_Error( 'wpsstm_radionomy_missing_station_slug', __('Required station slug missing.','wpsstm') );
        }

        $transient_name = 'wpsstm-radionomy-' . $slug . '-id';

        if ( false === ( $station_id = get_transient($transient_name ) ) ) {

            $station_url = sprintf('http://www.radionomy.com/en/radio/%1$s',$slug);
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
        
        return $station_id;

    }
    
    function get_remote_title(){
        return sprintf('Radionomy: %s',$this->get_station_slug());
    }

}

//register preset

function register_radionomy_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_Radionomy_Playlists_API';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_radionomy_preset');