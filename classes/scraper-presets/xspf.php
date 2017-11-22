<?php
class WP_SoundSystem_Preset_XSPF extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'xspf';

    var $preset_options =  array(
        'selectors' => array(
            'tracklist_title'   => array('path'=>'title'),
            'tracks'            => array('path'=>'trackList track'),
            'track_artist'      => array('path'=>'creator'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
            'track_source_urls' => array('path'=>'location'),
            'track_image'       => array('path'=>'image')
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('XSPF files','wpsstm');

    }
    
    /*
    Eventually convert an XML response type to an XSPF response type
    */
    function get_response_type(){
        
        if ( $this->response_type ) return $this->response_type; //already populated

        $response_type = parent::get_response_type();
        
        if ( !is_wp_error($response_type) ){
            
            libxml_use_internal_errors(true);

            switch($response_type){
                case 'application/xml':
                case 'text/xml':

                    $split = explode('/',$response_type);
                    $response = $this->get_remote_response();
                    $content = wp_remote_retrieve_body($response);
                    
                    //QueryPath
                    try{
                        if ( qp( $content, 'playlist trackList track', self::$querypath_options )->length > 0 ){
                            $response_type = sprintf('%s/xspf+xml',$split[0]);
                        }
                    }catch(Exception $e){

                    }

                break;
            }

        }
        
        $this->response_type = $response_type;
        return $response_type;
    }
    
    function can_load_feed(){
        $response_type = $this->get_response_type();
        if( is_wp_error($response_type) ) return false;
        $split = explode('/',$response_type);
        if ( isset($split[1]) && ( $split[1] == 'xspf+xml' ) ) return true;
    }
}

//register preset

function register_xspf_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_XSPF';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_xspf_preset', 50); //low priority since we need to fetch the remote page first