<?php
class WP_SoundSystem_Preset_LastFM_User_Library_Scraper extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'last-fm-user-library';
    var $preset_url =       'https://www.last.fm/user/XXX/library';
    var $pattern =          '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))/library~';
    var $variables =        array(
        'lastfm-user-slug' => null,
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
        $this->preset_name = __('Last.FM user library','wpsstm');
    }

}

class WP_SoundSystem_Preset_LastFM_User_Scraper extends WP_SoundSystem_Preset_LastFM_User_Library_Scraper{
    var $preset_slug =      'last-fm-user';
    var $preset_url =       'https://www.last.fm/user/XXX';
    var $pattern =          '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))/?$~';
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM user','wpsstm');
    }

}

class WP_SoundSystem_Preset_LastFM_User_Favorites_Scraper extends WP_SoundSystem_Preset_LastFM_User_Library_Scraper{

    var $preset_slug =      'last-fm-user-favorites';
    var $preset_url =       'https://www.last.fm/user/XXX/loved';
    var $pattern =          '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))/loved~';
    
    function __construct($post_id = null){
        parent::__construct($post_id);
        $this->preset_name = __('Last.FM user favorites','wpsstm');
    }

}

class WP_SoundSystem_Preset_LastFM_Station_Similar_Artist_Scraper extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'last-fm-station-similar-artist';
    var $preset_url =       'https://www.last.fm/music/XXX/+similar';
    var $pattern =          '~^https?://(?:www.)?last.fm/music/([^/]+)/\+similar~';
    var $redirect_url =     'https://www.last.fm/player/station/music/%lastfm-artist-slug%?ajax=1';
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
        $this->preset_name = __('Last.FM stations (similar artist)','wpsstm');
    }
    
    function get_remote_title(){
        $artist = $this->get_variable_value('lastfm-artist-slug');
        return sprintf( __('Last.FM stations (similar artist): %s','wpsstm'), $artist );
    }

}

class WP_SoundSystem_Preset_LastFM_Station_User_Recommandations_Scraper extends WP_SoundSystem_Live_Playlist_Preset{

    var $preset_slug =      'last-fm-station-user-recommandations';
    var $preset_url =       'https://www.last.fm/user/XXX/recommended';
    var $pattern =          '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))/recommended~'; //TO FIX this is not a valid LAST.FM URL
    var $redirect_url =     'https://www.last.fm/player/station/user/%lastfm-user-slug%/recommended?ajax=1';
    var $variables =        array(
        'lastfm-user-slug' => null,
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
        $this->preset_name = __('Last.FM stations (user recommandations)','wpsstm');
    }
    
    function get_remote_title(){
        $artist = $this->get_variable_value('lastfm-user-slug');
        return sprintf( __('Last.FM stations (user recommandations): %s','wpsstm'), $artist );
    }

}


//register preset

function register_lastfm_preset($presets){
    $presets[] = 'WP_SoundSystem_Preset_LastFM_User_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_User_Library_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_User_Favorites_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Station_Similar_Artist_Scraper';
    $presets[] = 'WP_SoundSystem_Preset_LastFM_Station_User_Recommandations_Scraper';
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','register_lastfm_preset');