<?php
class WP_SoundSystem_Preset_Soundsgood_Playlists_Api extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'soundsgood';
    var $preset_url =       'https://soundsgood.co/';
    
    var $pattern =          '~^https?://play.soundsgood.co/playlist/([^/]+)~i';
    var $redirect_url=      'https://api.soundsgood.co/playlists/%soundsgood-playlist-slug%/tracks';
    var $variables =        array(
        'soundsgood-playlist-slug' => null,
    );

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'root > element'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_source_urls' => array('path'=>'sources permalink')
        )
    );
    
    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Soundsgood playlists','wpsstm');

    } 

    function get_request_args(){
        $args = parent::get_request_args();

        if ( $client_id = $this->get_client_id() ){
            $args['headers']['client'] = $client_id;
            $this->set_variable_value('soundsgood-client-id',$client_id);
        }

        return $args;
    }

    function get_client_id(){
        return '529b7cb3350c687d99000027:dW6PMNeDIJlgqy09T4GIMypQsZ4cN3IoCVXIyPzJYVrzkag5';
    }
    
    function get_remote_title(){
        $slug = $this->get_variable_value('soundsgood-playlist-slug');
        return sprintf(__('%s on Soundsgood','wpsstm'),$slug);
    }
    
}

//register preset

function register_soundsgood_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_Soundsgood_Playlists_Api';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_soundsgood_preset');