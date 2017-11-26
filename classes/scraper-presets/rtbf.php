<?php
class WP_SoundSystem_RTBF_Stations extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'https://www.rtbf.be/';

    var $station_slug;
    
    static $wizard_suggest = false;

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->station_slug = $this->get_station_slug();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'li.radio-thread__entry'),
            'track_artist'      => array('path'=>'span[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_image'       => array('path'=>'img[itemprop="inAlbum"]','attr'=>'data-src')
        );
    }
    
    function can_handle_url(){
        if (!$this->station_slug ) return;
        return true;
    }

    function get_remote_url(){
        return sprintf('https://www.rtbf.be/%s/conducteur',$this->station_slug);
    }  

    
    function get_station_slug(){
        $pattern = '~^https?://(?:www.)?rtbf.be/(?!lapremiere)([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

//register preset
function register_rtbf_preset($presets){
    $presets[] = 'WP_SoundSystem_RTBF_Stations';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_rtbf_preset');