<?php
class WP_SoundSytem_Preset_BBC_Stations extends WP_SoundSytem_Live_Playlist_Preset{
    
    var $preset_slug =      'bbc-station';
    var $preset_url =       'http://www.bbc.co.uk/radio';
    var $pattern =          '~^https?://(?:www.)?bbc.co.uk/(?!music)([^/]+)/?~i';
    var $redirect_url =     'http://www.bbc.co.uk/%bbc-slug%/playlist';
    var $variables =        array(
        'bbc-slug' => null
    );

    var $options_default =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'.pll-playlist-item-wrapper'),
            'track_artist'      => array('path'=>'.pll-playlist-item-details .pll-playlist-item-artist'),
            'track_title'       => array('path'=>'.pll-playlist-item-details .pll-playlist-item-title'),
            'track_image'       => array('path'=>'img.pll-playlist-item-image','attr'=>'src')
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);
        $this->preset_name =    __('BBC stations','wpsstm');
    }

}

class WP_SoundSytem_Preset_BBC_Playlists extends WP_SoundSytem_Live_Playlist_Preset{
    var $preset_slug =      'bbc-playlist';
    var $preset_url =       'http://www.bbc.co.uk/music/playlists/'; 
    var $pattern =          '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)/?$~i';
    var $variables =        array(
        'bbc-playlist-id' => null
    );
    
    var $options_default =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'ul.plr-playlist-trackslist li'),
            'track_artist'      => array('path'=>'.plr-playlist-trackslist-track-name-artistlink'),
            'track_title'       => array('path'=>'.plr-playlist-trackslist-track-name-title'),
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);
        $this->preset_name =    __('BBC playlist','wpsstm');
    } 

}