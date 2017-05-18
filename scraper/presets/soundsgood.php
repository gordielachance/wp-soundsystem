<?php
class WP_SoundSytem_Playlist_Soundsgood_Api extends WP_SoundSytem_Live_Playlist_Preset{
    
    var $preset_slug = 'soundsgood';
    
    var $pattern = '~^https?://play.soundsgood.co/playlist/([^/]+)~i';
    var $redirect_url= 'https://api.soundsgood.co/playlists/%soundsgood-playlist-slug%/tracks';
    var $variables = array(
        'soundsgood-playlist-slug' => null,
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'root > element'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_source_urls' => array('path'=>'sources permalink')
        )
    );
    
    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url = null);

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
    
    function get_tracklist_title(){
        $slug = $this->get_variable_value('soundsgood-playlist-slug');
        return sprintf(__('%s on Soundsgood','wpsstm'),$slug);
    }
    
}