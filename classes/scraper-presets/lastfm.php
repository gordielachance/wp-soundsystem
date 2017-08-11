<?php
class WP_SoundSystem_Preset_LastFM_Scraper extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'last-fm-website';
    var $preset_url =       'https://www.last.fm';
    var $pattern =          '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))(?:/([^/]+))?~';
    var $variables =        array(
        'lastfm-user' => null,
        'lastfm-page' => null
    );

    var $preset_options =  array(
        'selectors' => array(
            'tracks'           => array('path'=>'table.chartlist tbody tr'),
            'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
            'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
            'track_image'      => array('path'=>'img.cover-art','attr'=>'src'),
            'track_source_urls' => array('path'=>'a[data-youtube-url]','attr'=>'href'),
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM website','wpsstm');
    }

}

//register preset

function register_lastfm_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Scraper';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_lastfm_preset');