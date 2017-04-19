<?php

abstract class WP_SoundSytem_Playlist_Scraper_Preset extends WP_SoundSytem_Playlist_Scraper_Datas{
    var $slug = null;

    var $name = null;
    var $description = null;
    
    var $pattern = null; //regex pattern that would match an URL
    var $redirect_url = null; //real URL of the tracklist; can use the values from the regex groups captured with the pattern above.
    var $variables = array(); //list of slugs that would match the regex groups captured with the pattern above - eg. array('username','playlist-id')
    
    /*
    Check if we can use this preset.
    Could return false if something required is missing (eg. an API key)
    */
    
    abstract function can_use_preset();
    
    /*
    If the preset isn't able to get a tracklist directly, it should not be available frontend.
    Eg. the Twitter preset do prefills some fileds of the wizard but requires the user to complete more informations to get a tracklist.
    */
    abstract function can_use_preset_frontend();
    
}

class WP_SoundSytem_Playlist_Scraper_LastFM extends WP_SoundSytem_Playlist_Scraper_Preset{

    var $slug = 'last-fm-website';

    var $name = null;
    var $description = null;
    
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
            'track_image'      => array('path'=>'.chartlist-play-image')
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('Last.FM website','wpsstm');

    }
    
    function can_use_preset(){
        return true;
    }
    
    function can_use_preset_frontend(){
        return true;
    }

}

class WP_SoundSytem_Playlist_Scraper_Spotify_Playlist extends WP_SoundSytem_Playlist_Scraper_Preset{

    var $slug = 'spotify-playlist';

    var $name = null;
    var $description = null;
    
    var $pattern = '~^https?://(?:open|play).spotify.com/user/(.+)/playlist/(.+)/?$~i';
    var $redirect_url = 'https://open.spotify.com/user/%spotify-user%/playlist/%spotify-playlist%';
    var $variables = array(
        'spotify-user' => null,
        'spotify-playlist' => null
    );
    
    
    var $options = array(
        'selectors' => array(
            'tracks'           => array('path'=>'.tracklist-container li.tracklist-row'),
            'track_artist'     => array('path'=>'.artists-albums a:eq(1)'),
            'track_title'      => array('path'=>'.track-name'),
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('Spotify Playlist','wpsstm');
        
    }
    
    function can_use_preset(){
        return true;
    }
    
    function can_use_preset_frontend(){
        return true;
    }

}

class WP_SoundSytem_Playlist_Scraper_Radionomy extends WP_SoundSytem_Playlist_Scraper_Preset{

    var $slug = 'radionomy';

    var $name = null;
    var $description = null;
    
    var $pattern = '~^https?://(?:www.)?radionomy.com/.*?/radio/([^/]+)~';
    var $redirect_url = 'http://radionomy.letoptop.fr/ajax/ajax_last_titres.php?radiouid=%radionomy-id%';

    var $variables = array(
        'radionomy-slug' => null,
        'radionomy-id' => null
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'div.titre'),
            'track_artist'      => array('path'=>'table td','regex'=>'^(.*?)(?:<br ?/?>)'),
            'track_title'       => array('path'=>'table td i'),
            'track_image'       => array('path'=>'img')
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('Radionomy Station','wpsstm');
    }
    
    protected function get_remote_url(){

        //set station ID
        if ( $station_id = $this->get_station_id() ){
            $this->set_variable_value('radionomy-id',$station_id);
        }
        
        return parent::get_remote_url();

    }
    
    function can_use_preset(){
        return true;
    }
    
    function can_use_preset_frontend(){
        return true;
    }

    function get_station_id(){
        
        $slug = $this->get_variable_value('radionomy-slug');
        if (!$slug) return false;

        $transient_name = 'radionomy-' . $slug . '-id';

        if ( false === ( $station_id = get_transient($transient_name ) ) ) {

            $station_url = sprintf('http://www.radionomy.com/en/radio/%1$s',$slug);
            $response = wp_remote_get( $station_url );

            if ( is_wp_error($response) ) return;

            $response_code = wp_remote_retrieve_response_code( $response );
            if ($response_code != 200) return;

            $content = wp_remote_retrieve_body( $response );

            libxml_use_internal_errors(true);

            //QueryPath
            try{
                $title = htmlqp( $content, 'head meta[property="og:title"]', WP_SoundSytem_Playlist_Scraper_Datas::$querypath_options )->attr('content');
                if ($title) $this->radionomy_title = $title;
            }catch(Exception $e){
            }

            //QueryPath
            try{
                $imagepath = htmlqp( $content, 'head meta[property="og:image"]', WP_SoundSytem_Playlist_Scraper_Datas::$querypath_options )->attr('content');
            }catch(Exception $e){
                return false;
            }

            libxml_clear_errors();

            $image_file = basename($imagepath);

            $pattern = '~^([^.]+)~';
            preg_match($pattern, $image_file, $matches);
            
            if ( isset($matches[1]) ){
                $station_id = $matches[1];
                set_transient( $transient_name, $station_id, 1 * DAY_IN_SECONDS );
            }

        }
        
        return $station_id;

    }

}

class WP_SoundSytem_Playlist_Scraper_SomaFM extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'somafm';

    var $name = null;
    var $description = null;
    
    var $pattern = '~^https?://(?:www.)?somafm.com/(.+)/?$~i';
    var $redirect_url = 'http://somafm.com/songs/%somafm-slug%.xml';
    var $variables = array(
        'somafm-slug' => null
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('Soma FM Station','wpsstm');

    }
    
    function can_use_preset(){
        return true;
    }
    
    function can_use_preset_frontend(){
        return true;
    }
    
}

class WP_SoundSytem_Playlist_Scraper_BBC_Station extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'bbc-station';

    var $name = null;
    var $description = null;

    var $pattern = '~^https?://(?:www.)?bbc.co.uk/(?!music)(.+)/?$~i';
    var $redirect_url= 'http://www.bbc.co.uk/%bbc-slug%/playlist';
    var $variables = array(
        'bbc-slug' => null
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'.pll-playlist-item-wrapper'),
            'track_artist'      => array('path'=>'.pll-playlist-item-details .pll-playlist-item-artist a'),
            'track_title'       => array('path'=>'.pll-playlist-item-details .pll-playlist-item-title'),
            'track_image'       => array('path'=>'img.pll-playlist-item-image')
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('BBC station','wpsstm');

    }
    
    function can_use_preset(){
        return true;
    }
    
    function can_use_preset_frontend(){
        return true;
    }

}

class WP_SoundSytem_Playlist_Scraper_BBC_Playlist extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'bbc-playlist';
    
    var $name = null;
    var $description = null;
    
    var $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/(.+)/?$~i';
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
    
    function can_use_preset(){
        return true;
    }
    
    function can_use_preset_frontend(){
        return true;
    }
    
}

class WP_SoundSytem_Playlist_Scraper_Slacker_Station extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'slacker-station-tops';
    
    var $name = null;
    var $description= null;
    
    var $pattern = '~^https?://(?:www.)?slacker.com/station/(.+)/?~i';
    var $variables = array(
        'slacker-station-slug' => null
    );
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'ol.playlistList li.row:not(.heading)'),
            'track_artist'      => array('path'=>'span.artist'),
            'track_title'       => array('path'=>'span.title')
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('Slacker.com station tops','wpsstm');

    } 
    
    function can_use_preset(){
        return true;
    }
    
    function can_use_preset_frontend(){
        return true;
    }

}
class WP_SoundSytem_Playlist_Scraper_Soundcloud extends WP_SoundSytem_Playlist_Scraper_Preset{
    
    var $slug = 'soundcloud';

    var $name = null;
    var $description = null;
    var $pattern = '~^https?://(?:www.)?soundcloud.com/([^/]+)/([^/]+)~i';
    var $redirect_url= 'http://api.soundcloud.com/users/%soundcloud-username%/%soundcloud-api-page%?client_id=%soundcloud-client-id%';
    var $variables = array(
        'soundcloud-username' => null,
        'soundcloud-page' => null
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'element'),
            'track_artist'      => array('path'=>'user username'),
            'track_title'       => array('path'=>'title'),
            'track_image'       => array('path'=>'artwork_url')
        )
    );
    
    function __construct(){
        parent::__construct();

        $this->name = __('Soundcloud','wpsstm');

    } 

    function can_use_preset(){
        if ( $client_id = wpsstm()->get_options('soundcloud_client_id') ){
            $this->set_variable_value('soundcloud-client-id',$client_id);
            return true;
        }
    }
    
    function can_use_preset_frontend(){
        return true;
    }

    function get_remote_url(){
        
        $page = $this->get_variable_value('soundcloud-page');
        $page_api = 'tracks';
        
        switch($page){
            case 'likes':
                $page_api = 'favorites';
        }
        $this->set_variable_value('soundcloud-api-page',$page_api);
        
        return parent::get_remote_url();
    }


}

class WP_SoundSytem_Playlist_Scraper_Twitter extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'twitter';
    
    var $name = null;
    var $description = null;
    
    var $pattern = '~^https?://(?:(?:www|mobile).)?twitter.com/(.+)/?$~i';
    var $redirect_url= 'https://mobile.twitter.com/%twitter-username%';
    var $variables = array(
        'twitter-username' => null
    );
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'#main_content .timeline .tweet .tweet-text div')
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('Twitter','wpsstm');

    } 
    
    function can_use_preset(){
        return true;
    }
    
    /*
    Prefills the wizard but is not able to get a tracklist by itself, so don't populate frontend.
    */
    function can_use_preset_frontend(){
        return false;
    }

}

/*
Register scraper presets.
*/
function wpsstm_register_scraper_presets($presets){
    
    $presets[] = new WP_SoundSytem_Playlist_Scraper_LastFM();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Spotify_Playlist();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Radionomy();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_SomaFM();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_BBC_Station();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_BBC_Playlist();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Slacker_Station();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Soundcloud();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Twitter();
    
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','wpsstm_register_scraper_presets');

    