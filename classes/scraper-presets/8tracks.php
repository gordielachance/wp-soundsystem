<?php
class WP_SoundSystem_8Tracks_Playlists{
    var $preset_slug =      '8tracks-playlist';
    var $preset_url =       'https://8tracks.com';
    private $user_slug;
    private $playlist_slug;
    private $mix_data;
    
    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->user_slug = $this->get_user_slug();
        $this->playlist_slug = $this->get_tracklist_slug();

        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
    }
    
    function can_handle_url(){
        if ( !$this->user_slug ) return;
        if ( !$this->playlist_slug ) return;
        return true;
    }

    function get_user_slug(){
        global $wpsstm_tracklist;
        $pattern = '~^https?://(?:www.)?8tracks.com/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_tracklist_slug(){
        global $wpsstm_tracklist;
        $pattern = '~^https?://(?:www.)?8tracks.com/[^/]+/([[\w\d-]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_url($url){
        
        if ( $this->can_handle_url() ){
            $mix_data = $this->get_mix_data();
            if ( is_wp_error($mix_data) ) return $mix_data;

            //populate mix ID
            if ( !$mix_id = wpsstm_get_array_value(array('id'),  $mix_data) ) {
                return new WP_Error( 'wpsstm_8tracks_missing_mix_id', __('Required mix ID missing.','wpsstm') );
            }

            $url = sprintf('https://8tracks.com/mixes/%s/tracks_for_international.jsonh',$mix_id);
        }

        return $url;

    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'>tracks'),
                'track_artist'      => array('path'=>'performer'),
                'track_title'       => array('path'=>'name')
            );
        }
        return $options;
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
function register_8tracks_playlists_preset($tracklist){
    new WP_SoundSystem_8Tracks_Playlists($tracklist);
}

function register_8tracks_service_link($links){
    $links[] = array(
        'slug'      => '8tracks',
        'name'      => sprintf(__('%s playlists','wpsstm'),'8tracks'),
        'desc'      => null,
        'url'       => 'https://8tracks.com',
        'example'   => 'https://8tracks.com/USER/PLAYLIST',
    );
    return $links;
}

add_action('wpsstm_get_remote_tracks','register_8tracks_playlists_preset');
add_filter('wpsstm_wizard_services_links','register_8tracks_service_link');