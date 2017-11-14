<?php
class WP_SoundSystem_Preset_Deezer_Playlists extends WP_SoundSystem_Live_Playlist_Preset{
    
    var $preset_slug =      'deezer';
    var $preset_url =       'http://www.deezer.com/';

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'#tab_tracks_content [itemprop="track"]'),
            'track_artist'      => array('path'=>'[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_album'       => array('path'=>'[itemprop="inAlbum"]')
        )
    );
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name =    __('Deezer Playlists','wpsstm');
    }
    
    function can_load_preset(){
        if ( !$playlist_id = $this->get_playlist_id() ) return;
        return true;
    }

    function get_playlist_id(){
        $pattern = '~^https?://(?:www.)?deezer.com/(?:.*/)?playlist/([^/]+)~i';
        preg_match($pattern, $this->feed_url, $matches);
        return isset($matches[1]) ? $matches[1] : null;
    }
    
}

//register preset

function register_deezer_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_Deezer_Playlists';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_deezer_preset');