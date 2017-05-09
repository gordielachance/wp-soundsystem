<?php
class WP_SoundSytem_Playlist_BBC_Station_Scraper extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'bbc-station';

    var $pattern = '~^https?://(?:www.)?bbc.co.uk/(?!music)([^/]+)/?~i';
    var $redirect_url= 'http://www.bbc.co.uk/%bbc-slug%/playlist';
    var $variables = array(
        'bbc-slug' => null
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'.pll-playlist-item-wrapper'),
            'track_artist'      => array('path'=>'.pll-playlist-item-details .pll-playlist-item-artist'),
            'track_title'       => array('path'=>'.pll-playlist-item-details .pll-playlist-item-title'),
            'track_image'       => array('path'=>'img.pll-playlist-item-image','attr'=>'src')
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('BBC station','wpsstm');

    }

}

class WP_SoundSytem_Playlist_BBC_Playlist_Scraper extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'bbc-playlist';
    
    var $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)/?$~i';
    var $variables = array(
        'bbc-playlist-id' => null
    );
    
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'ul.plr-playlist-trackslist li'),
            'track_artist'      => array('path'=>'.plr-playlist-trackslist-track-name-artistlink'),
            'track_title'       => array('path'=>'.plr-playlist-trackslist-track-name-title'),
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('BBC playlist','wpsstm');

    } 

}