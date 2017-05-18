<?php
class WP_SoundSytem_Playlist_SomaFM_Scraper extends WP_SoundSytem_Live_Playlist_Preset{
    var $preset_slug = 'somafm';
    
    var $pattern = '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
    var $redirect_url = 'http://somafm.com/songs/%somafm-slug%.xml';
    var $variables = array(
        'somafm-slug' => null
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        )
    );

    function __construct(){
        parent::__construct();

        $this->preset_name = __('Soma FM Station','wpsstm');

    }
    
    function get_tracklist_title(){
        if ( !$slug = $this->get_variable_value('somafm-slug') ) return;
        return sprintf(__('Somafm : %s','wppstm'),$slug);
    }

}