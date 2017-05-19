<?php
class WP_SoundSytem_Playlist_LastFM_Scraper extends WP_SoundSytem_Live_Playlist_Preset{

    var $preset_slug = 'last-fm-website';
    
    var $pattern = '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))(?:/([^/]+))?~';
    var $variables = array(
        'lastfm-user' => null,
        'lastfm-page' => null
    );

    var $options_default = array(
        'selectors' => array(
            'tracks'           => array('path'=>'table.chartlist tbody tr'),
            'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
            'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
            'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
        )
    );

    function __construct($post_id_or_feed_url = null){
        parent::__construct($post_id_or_feed_url);
        $this->preset_name = __('Last.FM website','wpsstm');
    }

}