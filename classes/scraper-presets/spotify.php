<?php

class WP_SoundSystem_Spotify_URL_Playlists_Api extends WP_SoundSystem_URL_Preset{
    var $preset_slug =      'spotify-playlist';
    var $preset_url =       'https://open.spotify.com';

    private $token = null;
    private $client_id;
    private $client_secret;
    private $user_slug;
    private $playlist_slug;

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->client_id = wpsstm()->get_options('spotify_client_id');
        $this->client_secret = wpsstm()->get_options('spotify_client_secret');
        
        $this->user_slug = $this->get_user_slug();
        $this->playlist_slug = $this->get_playlist_slug();
        
        $this->scraper_options['selectors'] = array(
            'tracks'           => array('path'=>'root > items'),
            'track_artist'     => array('path'=>'track > artists > name'),
            'track_album'      => array('path'=>'track > album > name'),
            'track_title'      => array('path'=>'track > name'),
        );
        
    }
    
    function can_use_preset(){
        if ( !$this->client_id ){
            return new WP_Error( 'wpsstm_missing_client_id', __('Required Spotify client ID missing.','wpsstm') );
        }
        if ( !$this->client_secret ){
            return new WP_Error( 'wpsstm_missing_client_secret', __('Required Spotify client secret missing.','wpsstm') );
        }
        return true;
    }

    function can_handle_url(){

        if ( !$this->user_slug ) return;
        if ( !$this->playlist_slug ) return;

        return true;
    }

    function get_remote_url(){

        $url = sprintf('https://api.spotify.com/v1/users/%s/playlists/%s/tracks',$this->user_slug,$this->playlist_slug);

        //handle pagination
        $pagination_args = array(
            'limit'     => $this->request_pagination['page_items_limit'],
            'offset'    => ($this->request_pagination['current_page'] - 1) * $this->request_pagination['page_items_limit']
        );
        
        $url = add_query_arg($pagination_args,$url);
        return $url;
        

    }
    
    function get_user_slug(){
        $pattern = '~^https?://(?:open|play).spotify.com/user/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);

        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_playlist_slug(){
        $pattern = '~^https?://(?:open|play).spotify.com/user/[^/]+/playlist/([\w\d]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

    function get_remote_tracks($args = null){ //TOFIXGGG TO CHECK
        
        $track_count = $this->get_spotify_playlist_track_count();
        
        if ( is_wp_error($track_count) ){
            return $track_count;
        }
        
        $this->track_count = $track_count;
        
        //init pagination before request
        $pagination_args = array(
            'page_items_limit'  => 100
        );

        $this->set_request_pagination( $pagination_args );
        
        return parent::get_remote_tracks($args);
    }
    
    function get_remote_title(){

        $response = wp_remote_get( sprintf('https://api.spotify.com/v1/users/%s/playlists/%s',$this->user_slug,$this->playlist_slug), $this->get_request_args() );
        
        $json = wp_remote_retrieve_body($response);
        
        if ( is_wp_error($json) ) return $json;
        
        $api = json_decode($json,true);
        
        return wpsstm_get_array_value('name', $api);
    }
    
    function get_tracklist_author(){
        return $this->user_slug;
    }
    
    //TO FIX TO IMPROVE just get playlist data ?
    protected function get_spotify_playlist_track_count(){

        $response = wp_remote_get( sprintf('https://api.spotify.com/v1/users/%s/playlists/%s',$this->user_slug,$this->playlist_slug), $this->get_request_args() );
        
        $json = wp_remote_retrieve_body($response);
        
        if ( is_wp_error($json) ){
            return $json;
        }else{
            $api = json_decode($json,true);
            return wpsstm_get_array_value(array('tracks','total'), $api);
        }
        
    }
    
    function get_request_args(){
        
        if ( $token = $this->get_access_token() ){
            $args['headers']['Authorization'] = 'Bearer ' . $token;           
        }
        
        $args['headers']['Accept'] = 'application/json';

        return $args;
    }

    function get_access_token(){
        
        if ($this->token === null){
            
            $this->token = false;

            $args = array(
                'headers'   => array(
                    'Authorization' => 'Basic '.base64_encode($this->client_id.':'.$this->client_secret)
                ),
                'body'      => array(
                    'grant_type'    => 'client_credentials'
                )
            );


            $response = wp_remote_post( 'https://accounts.spotify.com/api/token', $args );

            if ( is_wp_error($response) ){
                wpsstm()->debug_log($response->get_error_message(),'Spotify preset error' ); 
            }
            $body = wp_remote_retrieve_body($response);
            $body = json_decode($body);
            $this->token = $body->access_token;
            
        }
        
        return $this->token;

    }

}

//Spotify Playlists URIs
class WP_SoundSystem_Spotify_URI_Playlists_Api extends WP_SoundSystem_Spotify_URL_Playlists_Api{
    
    function get_user_slug(){
        $pattern = '~^spotify:user:([^:]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function get_playlist_slug(){
        $pattern = '~^spotify:user:.*:playlist:([\w\d]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

//register presets
function register_spotify_presets($presets){
    $presets[] = 'WP_SoundSystem_Spotify_URL_Playlists_Api';
    $presets[] = 'WP_SoundSystem_Spotify_URI_Playlists_Api';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_spotify_presets',10,2);