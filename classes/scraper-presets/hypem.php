<?php
class WP_SoundSystem_Hypem_Scraper extends WP_SoundSystem_URL_Preset{
    var $preset_slug =      'hypem';
    var $preset_url =       'http://hypem.com/';

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->scraper_options['selectors'] = array(
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

function register_hypem_service_links($links){
    $links[] = array(
        'slug'      => 'hypem',
        'name'      => 'Hypem',
        'url'       => 'https://www.hypem.com',
    );
    return $links;
}

add_action('wpsstm_get_scraper_presets','register_hypem_preset');
add_filter('wpsstm_wizard_services_links','register_hypem_service_links');