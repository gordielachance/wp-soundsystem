<?php
class WP_SoundSystem_Preset_SomaFM_Stations extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'somafm';
    var $preset_url =       'http://somafm.com/';
    
    var $pattern =          '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
    var $redirect_url =     'http://somafm.com/songs/%somafm-slug%.xml';
    var $variables =        array(
        'somafm-slug' => null
    );

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);

        $this->preset_name = __('Soma FM Stations','wpsstm');

    }
    
    function get_remote_title(){
        if ( !$slug = $this->get_variable_value('somafm-slug') ) return;
        return sprintf(__('Somafm : %s','wppstm'),$slug);
    }

}

//register preset

function register_somafm_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_SomaFM_Stations';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_somafm_preset');