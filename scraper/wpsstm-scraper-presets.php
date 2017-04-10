<?php

class WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug;
    var $pattern;
    
    var $name;
    var $description;
    var $options = array();
    
    var $feed_url;//source URL
    var $variables;
    var $redirect_url;//URL of the page that will be parsed if different from $feed_url
    
    function __construct(){
        $default_options = WP_SoundSytem_Playlist_Scraper::get_default_options();
        $this->options =  array_replace_recursive($default_options, $this->options );
    }
    
    function is_preset_match($scraper_url){

        preg_match($this->pattern, $scraper_url, $url_matches);
        
        if (!$url_matches) return false;

        //init preset
        $this->feed_url = $scraper_url;
        
        //matches
        array_shift($url_matches); //remove first item (full match)
        $this->populate_variable_values($url_matches);

        return true;
    }
    
    function populate_variable_values($values_arr){
        
        $key = 0;

        foreach((array)$this->variables as $variable_slug=>$variable){
            
            $value = ( isset($values_arr[$key]) ) ? $values_arr[$key] : null;
            $this->set_variable_value($variable_slug,$value);
            $key++;
        }
        
    }
    
    function set_variable_value($slug,$value='null'){
        $this->variables[$slug]['value'] = $value;
    }
    
    function get_variable_value($slug){
        
        $output = null;

        foreach($this->variables as $variable_slug => $variable){
            
            if ( $variable_slug == $slug ){
                return $variable['value'];
            }
        }

    }
    
    function variables_fill_string($str){
        foreach($this->variables as $variable_slug => $variable){
            $pattern = '%' . $variable_slug . '%';
            $value = $variable['value'];
            
            if ($value) {
                $str = str_replace($pattern,$value,$str);
            }
        }
        return $str;
    }
    
    function get_redirect_url(){
        if ($this->redirect_url){
            $this->redirect_url = $this->variables_fill_string($this->redirect_url);
            return $this->redirect_url;
            
        }else{
            return $this->feed_url;
        }
    }
    
}

class WP_SoundSytem_Playlist_Scraper_LastFM extends WP_SoundSytem_Playlist_Scraper_Preset{

    var $slug = 'last-fm-website';
    var $pattern = '~https?://(?:www.)?last.fm/user/([\w\d]+)/([\w\d]+)~i';
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
        
        $this->variables = array(
            'lastfm-user' => array(
            'name'  => __('Last.FM user','wpsstm'),
            'value' => null 
            ),
            'lastfm-page' => array(
            'name'  => __('Last.FM page','wpsstm'),
             'value' => null
            )
        );
    }

}

class WP_SoundSytem_Playlist_Scraper_Spotify_Playlist extends WP_SoundSytem_Playlist_Scraper_Preset{

    var $slug = 'spotify-playlist';
    var $pattern = '~^https?://(?:open|play).spotify.com/user/([\w\d]+)/playlist/([\w\d]+)/?$~i';
    var $redirect_url = 'https://open.spotify.com/user/%spotify-user%/playlist/%spotify-playlist%';
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
        
        $this->variables = array(
            'spotify-user' => array(
                'name'  => __('Spotify user','wpsstm'),
                'value' => null 
            ),
            'spotify-playlist' => array(
                'name'  => __('Spotify playlist','wpsstm'),
                'value' => null
            )
        );
    }

}

class WP_SoundSytem_Playlist_Scraper_Radionomy extends WP_SoundSytem_Playlist_Scraper_Preset{

    var $slug = 'radionomy';
    var $pattern = '^https?://(?:www.)?radionomy.com/.*?/radio/([^/]+)~';
    /*
            '~^(?:http(?:s)?://(?:www\.)?radionomy.com/.*?/radio/)([^/]+)~',
            '~^(?:http(?:s)?://listen.radionomy.com/)([^/]+)~',
            '~^(?:http(?:s)?://streaming.radionomy.com/)([^/]+)~',
    */
    var $redirect_url = 'http://radionomy.letoptop.fr/ajax/ajax_last_titres.php?radiouid=%radionomy-id%';
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
        
        $this->variables = array(
            'radionomy-slug' => array(
                'name'  => __('Radionomy slug','wpsstm'),
                'value' => null 
            ),
            'radionomy-id' => array(
                'name'  => __('Radionomy ID','wpsstm'),
                'value' => null 
            )
        );

    }
    
    function get_redirect_url(){
        $station_id = $this->get_station_id();
        
        if ( !$station_id) {
            return new WP_Error( 'scraper_radionomy_station_id', __('Missing required station ID.','wpsstm') );
        }
        
        $this->set_variable_value('radionomy-id',$station_id);
        
        return parent::get_redirect_url();
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
    var $pattern = '~^https?://(?:www.)?somafm.com/([\w\d]+)/?$~i';
    var $redirect_url = 'http://somafm.com/songs/%somafm-slug%.xml';
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
        
        $this->variables = array(
            'somafm-slug' => array(
                'name'  => __('Station slug','wpsstm'),
                'value' => null 
            )
        );

    }
}

class WP_SoundSytem_Playlist_Scraper_BBC_Station extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'bbc-station';
    var $pattern = '^https?://(?:www.)?bbc.co.uk/(?!music)([\w\d]+)~i';
    var $redirect_url= 'http://www.bbc.co.uk/%bbc-slug%/playlist';
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
        
        $this->variables = array(
            'bbc-slug' => array(
                'name'  => __('Station slug','wpsstm'),
                'value' => null 
            )
        );

    }
    
    
}

class WP_SoundSytem_Playlist_Scraper_BBC_Playlist extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'bbc-playlist';
    var $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([\w\d]+)~i';
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
        
        $this->variables = array(
            'bbc-playlist-id' => array(
                'name'  => __('Playlist ID','wpsstm'),
                'value' => null 
            )
        );

    } 
}

class WP_SoundSytem_Playlist_Scraper_Slacker_Station extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'slacker-station-tops';
    var $pattern = '~^(?:http(?:s)?://(?:www.)?slacker.com/station/)([^/]*)~i';
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
        
        $this->variables = array(
            'slacker-station-slug' => array(
                'name'  => __('Station Slug','wpsstm'),
                'value' => null 
            )
        );

    } 
}