<?php
class WP_SoundSystem_Deezer_Playlists extends WP_SoundSystem_URL_Preset{
    var $preset_url =       'http://www.deezer.com/';
    var $playlist_id;

    function __construct($feed_url = null){
        parent::__construct($feed_url);
        $this->playlist_id = $this->get_playlist_id();
        
        $this->options['selectors'] = array(
            'tracks'            => array('path'=>'#tab_tracks_content [itemprop="track"]'),
            'track_artist'      => array('path'=>'[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_album'       => array('path'=>'[itemprop="inAlbum"]')
        );
    }
    
    function can_handle_url(){
        if ( !$this->playlist_id ) return;
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
    $presets[] = 'WP_SoundSystem_Deezer_Playlists';
    return $presets;
}

add_action('wpsstm_get_scraper_presets','register_deezer_preset');