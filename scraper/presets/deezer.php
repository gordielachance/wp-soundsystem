<?php
class WP_SoundSytem_Playlist_Deezer_Scraper extends WP_SoundSytem_Live_Playlist_Preset{
    var $preset_slug = 'deezer';
    
    var $pattern = '~^https?://(?:www.)?deezer.com/playlist/([^/]+)~i';
    
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'#tab_tracks_content [itemprop="track"]'),
            'track_artist'      => array('path'=>'[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_album'       => array('path'=>'[itemprop="inAlbum"]')
        )
    );
    
    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);
        $this->preset_name = __('Deezer Playlist','wpsstm');
    }
}