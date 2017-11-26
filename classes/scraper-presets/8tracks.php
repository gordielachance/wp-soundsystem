<?php
class WP_SoundSystem_8Tracks_Playlists extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'https://8tracks.com';
    var $user_slug;
    var $playlist_slug;
    var $mix_data;
    
    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->user_slug = $this->get_user_slug();
        $this->playlist_slug = $this->get_tracklist_slug();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'>tracks'),
            'track_artist'      => array('path'=>'performer'),
            'track_title'       => array('path'=>'name')
        );
    }
    
    function can_handle_url(){
        if ( !$this->user_slug ) return;
        if ( !$this->playlist_slug ) return;
        return true;
    }

    function get_user_slug(){
        $pattern = '~^https?://(?:www.)?8tracks.com/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_tracklist_slug(){
        $pattern = '~^https?://(?:www.)?8tracks.com/[^/]+/([[\w\d-]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_url(){

        $mix_data = $this->get_mix_data();
        if ( is_wp_error($mix_data) ) return $mix_data;

        //populate mix ID
        if ( !$mix_id = wpsstm_get_array_value(array('id'),  $mix_data) ) {
            return new WP_Error( 'wpsstm_8tracks_missing_mix_id', __('Required mix ID missing.','wpsstm') );
        }

        return sprintf('https://8tracks.com/mixes/%s/tracks_for_international.jsonh',$mix_id);

    }
    
    function get_mix_data(){

        //already populated
        if ( $this->mix_data ) return $this->mix_data;
        
        //can request ?
        if ( !$this->user_slug ){
            return new WP_Error( 'wpsstm_8tracks_missing_user_slug', __('Required user slug missing.','wpsstm') );
        }
        if ( !$this->playlist_slug ){
            return new WP_Error( 'wpsstm_8tracks_missing_tracklist_slug', __('Required tracklist slug missing.','wpsstm') );
        }
        
        $transient_name = 'wpsstm-8tracks-' . $this->playlist_slug . '-data';

        //cache?
        if ( false === ( $data = get_transient($transient_name ) ) ) {
            $mix_data_url = sprintf('https://8tracks.com/%s/%s?format=jsonh',$this->user_slug,$this->playlist_slug);
            $response = wp_remote_get($mix_data_url);
            $json = wp_remote_retrieve_body($response);
            if ( is_wp_error($json) ) return $json;
            
            $api = json_decode($json,true);
            if ( $data = wpsstm_get_array_value(array('mix'), $api) ){
                set_transient( $transient_name, $data, 1 * DAY_IN_SECONDS );
            }
        }
        
        return $data;
        
    }
    
    function get_remote_title(){
        if ( is_wp_error( $this->mix_data ) ) return $this->get_mix_data;
        return wpsstm_get_array_value('name', $this->mix_data);
    }
    

}

//register preset
function register_8tracks_playlists_preset($presets){
    $presets[] = 'WP_SoundSystem_8Tracks_Playlists';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_8tracks_playlists_preset');