<?php

class WP_SoundSytem_Playlist_Scraper_Presets{
    var $all_presets = array();

    function __construct(){        
        $all_presets = array();
        $this->all_presets = apply_filters('wpsstm_scraper_presets',$all_presets);
    }

    function register_preset($preset){
        $default_options = WP_SoundSytem_Playlist_Scraper::get_default_options();
        $preset['options'] = array_replace_recursive($default_options, $preset['options'] );
        $this->presets[] = $preset;
    }
    
    function get_url_preset($feed_url){
        $url_presets = $this->get_presets($feed_url);
        if ( empty($url_presets) ) return;
        
        //last preset (highest priority)
        $preset = end($url_presets);
        $preset = $this->fill_preset_matches($preset,$feed_url);
        return $preset;
    }
    
    private function get_presets($feed_url){

        $url_presets = array();
            
        foreach ($this->all_presets as $preset){
            preg_match($preset['pattern'], $feed_url, $matches);
            if (!$matches) continue;
            $url_presets[] = $preset;
        }

        return $url_presets;
    }

    /**
    Fill the preset matches using the url given.
    **/
    
    function fill_preset_matches($preset,$feed_url){
        preg_match($preset['pattern'], $feed_url, $matches);

        if ( empty($matches) ) return;
        
        array_shift($matches); //remove first item (full match)
        
        foreach((array)$preset['matches'] as $key=>$preset_match){
            
            $match_value = ( isset($matches[$key]) ) ? $matches[$key] : null;
            $preset['matches'][$key]['value'] = $match_value;
        }
        
        return $preset;
        
    }
 
}


///

function wpsstm_register_scraper_preset_last_fm_website($presets){
    $preset = array(
        'name'      => __('Last.FM website','wpsstm'),
        'slug'      => 'last-fm-website',
        'pattern'   => '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))(?:/([^/]+))?~',
        /*
        Those arrays should match the captured groups from 'pattern'.
        Values will be filled within fill_preset_matches()
        */
        'matches'   => array(
            array(
            'slug'  => 'lastfm-user',
            'name'  => __('Last.FM user','wpsstm'),
            'value' => null 
            ),
            array(
            'slug'  => 'lastfm-page',
            'name'  => __('Last.FM page','wpsstm'),
             'value' => null
            )
        ),
        'options'   => array(
            'selectors' => array(
                'tracks'           => array('path'=>'table.chartlist tbody tr'),
                'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
                'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
                'track_image'      => array('path'=>'.chartlist-play-image')
            )
        )
    );
    $presets[] = $preset;
    return $presets;

}

function wpsstm_register_scraper_preset_spotify_playlist($presets){
    $preset = array(
        'name'      => __('Spotify Playlist','wpsstm'),
        'slug'      => 'spotify-playlist',
        'pattern'   => '/^https?:\/\/(?:open|play)\.spotify\.com\/user\/([\w\d]+)\/playlist\/([\w\d]+)$/i',
        /*
        Those arrays should match the captured groups from 'pattern'.
        Values will be filled within fill_preset_matches()
        */
        'matches'   => array(
            array(
            'slug'  => 'spotify-user',
            'name'  => __('Spotify user','wpsstm'),
            'value' => null 
            ),
            array(
            'slug'  => 'spotify-playlist',
            'name'  => __('Spotify playlist','wpsstm'),
             'value' => null
            )
        ),
        'redirect_url'  => 'https://open.spotify.com/user/%spotify-user%/playlist/%spotify-playlist%',
        'options'   => array(
            'selectors' => array(
                'tracks'           => array('path'=>'.tracklist-container li.tracklist-row'),
                'track_artist'     => array('path'=>'.artists-albums a:eq(1)'),
                'track_title'      => array('path'=>'.track-name'),
            )
        )
    );
    $presets[] = $preset;
    return $presets;
}

add_filter('wpsstm_scraper_presets','wpsstm_register_scraper_preset_last_fm_website');
add_filter('wpsstm_scraper_presets','wpsstm_register_scraper_preset_spotify_playlist');