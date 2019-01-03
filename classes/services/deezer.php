<?php

class WPSSTM_Deezer{
    function __construct(){
        add_filter('wpsstm_remote_presets',array($this,'register_deezer_preset'));
        add_filter('wpsstm_wizard_service_links',array(__class__,'register_deezer_service_links'), 8);
    }
    //register preset
    static function register_deezer_preset($presets){
        $presets[] = new WPSSTM_Deezer_Preset();
        return $presets;
    }

    static function register_deezer_service_links($links){
        $item = sprintf('<a href="https://www.deeer.com" target="_blank" title="%s"><img src="%s" /></a>',__('Deezer playlists','wpsstm'),wpsstm()->plugin_url . '_inc/img/deezer-icon.jpeg');
        $links[] = $item;
        return $links;
    }
}

class WPSSTM_Deezer_Preset extends WPSSTM_Remote_Tracklist{
    var $playlist_id;
    function __construct($url = null,$options = null) {
        
        $this->default_options['selectors'] = array(
            'tracks'            => array('path'=>'#tab_tracks_content [itemprop="track"]'),
            'track_artist'      => array('path'=>'[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_album'       => array('path'=>'[itemprop="inAlbum"]')
        );
        
        parent::__construct($url,$options);
        
    }
    
    function init_url($url){
        $this->playlist_id = $this->get_playlist_id($url);
        return $this->playlist_id;
    }


    function get_playlist_id($url){
        $pattern = '~^https?://(?:www.)?deezer.com/(?:.*/)?playlist/([^/]+)~i';
        preg_match($pattern,$url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }

}

function wpsstm_deezer_init(){
    new WPSSTM_Deezer();
}

add_action('wpsstm_init','wpsstm_deezer_init');