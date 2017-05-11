<?php
class WP_SoundSytem_Playlist_LastFM_Scraper extends WP_SoundSytem_Playlist_Scraper_Preset{

    var $slug = 'last-fm-website';
    
    var $pattern = '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))(?:/([^/]+))?~';
    var $variables = array(
        'lastfm-user' => null,
        'lastfm-page' => null
    );


    var $options = array(
        'selectors' => array(
            'tracks'           => array('path'=>'table.chartlist tbody tr'),
            'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
            'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
            'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('Last.FM website','wpsstm');

    }

}