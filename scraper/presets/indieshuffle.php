<?php

/*
Should try to support playlists and songs, eg.
https://www.indieshuffle.com/playlists/best-songs-of-april-2017/
https://www.indieshuffle.com/songs/hip-hop/
*/

class WP_SoundSystem_Preset_IndieShuffle_Scraper extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'indie-shuffle';
    var $preset_url =       'https://www.indieshuffle.com';
    var $pattern =          '~http(?:s)?://(?:www\.)?indieshuffle.com/(.+)~';
    var $variables =        array(
        'uri' => null
    );

    var $options_default =  array(
        'datas_cache_min'   => 1440,
        'selectors' => array(
            'tracks'           => array('path'=>'#mainContainer .commontrack'),
            'track_artist'     => array('attr'=>'data-track-artist'),
            'track_title'      => array('attr'=>'data-track-title'),
            'track_image'      => array('path'=>'img','attr'=>'src'),
            'track_source_urls' => array('attr'=>'data-source'),
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);
        $this->preset_name = __('Indie Shuffle','wpsstm');
    }

}