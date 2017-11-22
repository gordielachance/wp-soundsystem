<?php
class WP_SoundSystem_Preset_RTBF_Stations extends WP_SoundSystem_Live_Playlist_Preset{
    var $preset_slug =      'rtbf';
    var $preset_url =       'https://www.rtbf.be/';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'li.radio-thread__entry'),
            'track_artist'      => array('path'=>'span[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_image'       => array('path'=>'img[itemprop="inAlbum"]','attr'=>'data-src')
        )
    );
    
    static $wizard_suggest = false;

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('RTBF stations','wpsstm');
    }
    
    static function can_handle_url($url){
        if (!self::get_station_slug($url) ) return;
        return true;
    }
    
    function get_remote_url(){
        return sprintf('https://www.rtbf.be/%s/conducteur',$this->get_station_slug());

    }
    
    static function get_station_slug($url){
        $pattern = '~^https?://(?:www.)?rtbf.be/(?!lapremiere)([^/]+)~i';
        preg_match($pattern, $url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

//register preset

function register_rtbf_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_RTBF_Stations';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_rtbf_preset');