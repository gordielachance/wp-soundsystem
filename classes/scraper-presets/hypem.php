<?php
class WP_SoundSystem_Hypem_Scraper extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'http://hypem.com/';

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'.section-track'),
            'track_artist'      => array('path'=>'.track_name .artist'),
            'track_title'       => array('path'=>'.track_name .track'),
            //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
        );
    }
    
    function can_handle_url(){
        $domain = wpsstm_get_url_domain( $this->feed_url );
        if ( $domain != 'hypem') return;
        return true;
    }

}

//register preset
function register_hypem_preset($presets){
    $presets[] = 'WP_SoundSystem_Hypem_Scraper';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_hypem_preset');