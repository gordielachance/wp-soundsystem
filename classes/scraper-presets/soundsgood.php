<?php
class WP_SoundSystem_Soundsgood_Playlists_Api{
    var $preset_slug =      'soundsgood-playlist';
    var $preset_url =       'https://soundsgood.co/';

    private $client_id;
    private $station_slug;
    
    function __construct($tracklist){
        $this->tracklist = $tracklist;
        $this->client_id = $this->get_client_id();
        $this->station_slug = $this->get_station_slug();

        add_filter( 'wpsstm_live_tracklist_url',array($this,'get_remote_url') );
        
        add_filter( 'wpsstm_live_tracklist_request_args',array($this,'remote_request_args') );
        
        add_filter( 'wpsstm_live_tracklist_scraper_options',array($this,'get_live_tracklist_options'), 10, 2 );
        
        add_filter( 'wpsstm_live_tracklist_title',array($this,'get_remote_title') );
        
    }

    function can_handle_url(){
        if ( !$this->station_slug ) return;
        return true;
    }

    function get_remote_url($url){
        if ( $this->can_handle_url() ){
            $url = sprintf('https://api.soundsgood.co/playlists/%s/tracks',$this->station_slug);
        }
        return $url;
    }
    
    function get_live_tracklist_options($options,$tracklist){
        
        if ( $this->can_handle_url() ){
            $options['selectors'] = array(
                'tracks'            => array('path'=>'root > element'),
                'track_artist'      => array('path'=>'artist'),
                'track_title'       => array('path'=>'title'),
                'track_source_urls' => array('path'=>'sources permalink')
            );
        }
        return $options;
    }

    function get_station_slug(){
        $pattern = '~^https?://play.soundsgood.co/playlist/([^/]+)~i';
        preg_match($pattern, $this->tracklist->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
    function remote_request_args($args){
        if ( $this->can_handle_url() ){
            $args['headers']['client'] = $this->client_id;
        }
        return $args;
    }

    static function get_client_id(){
        return '529b7cb3350c687d99000027:dW6PMNeDIJlgqy09T4GIMypQsZ4cN3IoCVXIyPzJYVrzkag5';
    }
    
    function get_remote_title($title){
        if ( $this->can_handle_url() ){
            $url = sprintf(__('%s on Soundsgood','wpsstm'),$this->station_slug);
        }
        return $title;
    }
    
}

//register preset
function register_soundsgood_preset($tracklist){
    new WP_SoundSystem_Soundsgood_Playlists_Api($tracklist);
}

if ( WP_SoundSystem_Soundsgood_Playlists_Api::get_client_id() ){
    add_action('wpsstm_get_remote_tracks','register_soundsgood_preset');
}

