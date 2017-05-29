<?php
class WP_SoundSytem_Preset_SomaFM_Stations extends WP_SoundSytem_Live_Playlist_Preset{
    var $preset_slug =      'somafm';
    var $preset_url =       'http://somafm.com/';
    
    var $pattern =          '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
    var $redirect_url =     'http://somafm.com/songs/%somafm-slug%.xml';
    var $variables =        array(
        'somafm-slug' => null
    );

    var $options_default =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);

        $this->preset_name = __('Soma FM Stations','wpsstm');

    }
    
    function get_tracklist_title(){
        if ( !$slug = $this->get_variable_value('somafm-slug') ) return;
        return sprintf(__('Somafm : %s','wppstm'),$slug);
    }

}