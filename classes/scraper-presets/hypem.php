<?php
class WP_SoundSystem_Preset_Hypem_Scraper extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'hypem';
    var $preset_url =       'http://hypem.com/';
    var $pattern =          '~^https?://(?:www.)?hypem.com/~i';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'.section-track'),
            'track_artist'      => array('path'=>'.track_name .artist'),
            'track_title'       => array('path'=>'.track_name .track'),
            //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
        )
    );
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = 'Hype Machine';
    }
 
}

//register preset

function register_hypem_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_Hypem_Scraper';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_hypem_preset');