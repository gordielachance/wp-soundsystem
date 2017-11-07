<?php
class WP_SoundSystem_Preset_LastFM_User_Scraper extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'last-fm-user';
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

class WP_SoundSystem_Preset_LastFM_Artist_Similar_Scraper extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'last-fm-artist';
    var $preset_url =       'https://www.last.fm';
    var $pattern =          '~^https?://(?:www.)?last.fm/music/([^/]+)/\+similar~';
    var $variables =        array(
        'lastfm-artist-slug' => null,
    );

    var $preset_options =  array(
        'selectors' => array(
            'tracks'            => array('path'=>'>playlist'),
            'track_artist'      => array('path'=>'artists > name'),
            'track_title'       => array('path'=>'playlist > name'),
            'track_source_urls' => array('path'=>'playlinks url'),
        )
    );

    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM artist : Similar','wpsstm');
    }
    
    function get_tracklist_title(){
        $artist = $this->get_variable_value('lastfm-artist-slug');
        return sprintf( __('Similar to: %s','wpsstm'), $artist );
    }

}

//register preset

function register_lastfm_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_LastFM_User_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Artist_Similar_Scraper';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_lastfm_preset');