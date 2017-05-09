<?php
class WP_SoundSytem_Playlist_Deezer_Scraper extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'deezer';
    
    var $pattern = '~^https?://(?:www.)?deezer.com/playlist/([^/]+)~i';
    
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'#tab_tracks_content [itemprop="track"]'),
            'track_artist'      => array('path'=>'[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_album'       => array('path'=>'[itemprop="inAlbum"]')
        )
    );
    
    function __construct(){
        parent::__construct();
        $this->name = __('Deezer Playlist','wpsstm');
    }
}