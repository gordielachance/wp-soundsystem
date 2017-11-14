<?php
class WP_SoundSystem_Preset_Soundcloud_Api extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'soundcloud';
    var $preset_url =       'https://soundcloud.com';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'element'),
            'track_artist'      => array('path'=>'user username'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'artwork_url')
        )
    );
    
    var $page_api = null;
    
    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Soundcloud user tracks or likes','wpsstm');

    }
    
    function get_remote_url(){
        
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $this->domain != 'soundcloud') return;

        if ( !$user_id = $this->get_user_id() ){
            return new WP_Error( 'wpsstm_soundcloud_missing_user_id', __('Required user ID missing.','wpsstm') );
        }
        
        if ( !$client_id = wpsstm()->get_options('soundcloud_client_id') ){
            return new WP_Error( 'wpsstm_soundcloud_missing_client_id', __('Required client ID missing.','wpsstm') );
        }
        
        $page = $this->get_user_page();
        $page = ($page) ? $page : 'tracks'; //default subpage

        return sprintf('http://api.soundcloud.com/users/%s/%s?client_id=%s',$user_id,$page,$client_id);

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
        
        if ( !$client_id = wpsstm()->get_options('soundcloud_client_id') ){
            return new WP_Error( 'wpsstm_soundcloud_missing_client_id', __('Required client ID missing.','wpsstm') );
        }

        if ( !$user_slug = $this->get_user_slug() ){
            return new WP_Error( 'wpsstm_soundcloud_missing_user_slug', __('Required user slug missing.','wpsstm') );
        }

        $transient_name = 'wpsstm-soundcloud-' . $user_slug . '-userid';

        if ( false === ( $user_id = get_transient($transient_name ) ) ) {

            $api_url = sprintf('http://api.soundcloud.com/resolve.json?url=http://soundcloud.com/%s&client_id=%s',$user_slug,$client_id);
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
        
        return $user_id;

    }
    
    function get_remote_title(){
        
        $page = $this->get_user_page();
        $user_slug = $this->get_user_slug();
        
        $title = sprintf(__('%s on Soundcloud','wpsstm'),$user_slug);
        $subtitle = null;
        
        switch($page){
            case 'favorites':
                $subtitle = __('Favorite tracks','wpsstm');
            break;
            case 'tracks':
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
    $presets[] = 'WP_SoundSystem_Preset_Soundcloud_Api';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_soundcloud_preset');