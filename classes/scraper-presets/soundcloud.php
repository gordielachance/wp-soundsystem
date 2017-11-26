<?php
class WP_SoundSystem_Soundcloud_User_Api extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'https://soundcloud.com';

    var $user_slug;
    var $page_slug;
    var $page_api = null;
    var $client_id;
    
    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->user_slug = $this->get_user_slug();
        $this->page_slug = $this->get_user_page();
        $this->client_id = wpsstm()->get_options('soundcloud_client_id');
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'element'),
            'track_artist'      => array('path'=>'user username'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'artwork_url')
        );
        
    }
    
    function can_use_preset(){
        if ( !wpsstm()->get_options('soundcloud_client_id') ){
            return new WP_Error( 'wpsstm_soundcloud_missing_client_id', __('Required Soundcloud client ID missing.','wpsstm') );
        }
        return true;
    }

    function can_handle_url(){
        
        if ( !$this->user_slug ) return;
        return true;
    }

    function get_remote_url(){

        if ( !$user_id = $this->get_user_id() ){
            return new WP_Error( 'wpsstm_soundcloud_missing_user_id', __('Required user ID missing.','wpsstm') );
        }

        $api_page = null;

        switch($this->page_slug){
            case 'likes':
                $this->api_page = 'favorites';
            break;
            default:
                $this->api_page = 'tracks';
            break;
        }

        return sprintf('http://api.soundcloud.com/users/%s/%s?client_id=%s',$user_id,$this->api_page,$this->client_id);

    }

    function get_user_slug(){
        $pattern = '~^http(?:s)?://(?:www\.)?soundcloud.com/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_page(){
        $pattern = '~^http(?:s)?://(?:www\.)?soundcloud.com/[^/]+/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_user_id(){
        
        if (!$this->user_id){
            $transient_name = 'wpsstm-soundcloud-' . $this->user_slug . '-userid';

            if ( false === ( $user_id = get_transient($transient_name ) ) ) {

                $api_url = sprintf('http://api.soundcloud.com/resolve.json?url=http://soundcloud.com/%s&client_id=%s',$this->user_slug,$this->client_id);
                $response = wp_remote_get( $api_url );

                if ( is_wp_error($response) ) return;

                $response_code = wp_remote_retrieve_response_code( $response );
                if ($response_code != 200) return;

                $content = wp_remote_retrieve_body( $response );
                if ( is_wp_error($content) ) return;
                $content = json_decode($content);

                if ( $user_id = $content->id ){
                    set_transient( $transient_name, $user_id );
                }

            }
            $this->user_id = $user_id;
        }

        return $this->user_id;

    }
    
    function get_remote_title(){

        $title = sprintf(__('%s on Soundcloud','wpsstm'),$this->user_slug);
        $subtitle = null;
        
        switch($this->page_slug){
            case 'likes':
                $subtitle = __('Favorite tracks','wpsstm');
            break;
            default: //tracks
                $subtitle = __('Tracks','wpsstm');
            break;
        }
        
        if ($subtitle){
            return $title . ' - ' . $subtitle;
        }else{
            return $title;
        }
    }

}

//register preset
function register_soundcloud_preset($presets){
    $presets[] = 'WP_SoundSystem_Soundcloud_User_Api';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_soundcloud_preset');