<?php

abstract class WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug;    
    var $name;
    var $description;

    var $scraper;
    
    var $options = array();

    function __construct(WP_SoundSytem_Playlist_Scraper $scraper){
        $this->scraper = $scraper;
    }
    
    /*
    Check that this preset can be loaded by the scraper.
    You should override this in your preset class.
    */

    function can_load_preset(){
        return false;
    }
    
    function init_preset(){
        $this->override_scraper_options();
    }

    function override_scraper_options(){

        if (!$this->options) return;
        
        $default_options = WP_SoundSytem_Playlist_Scraper::get_default_options();
        $preset_options =  array_replace_recursive($default_options, $this->options );
        $this->scraper->options = array_replace_recursive($this->scraper->options, $preset_options );
    }
    

    
}

/**
Class for presets that should run before the remote page is requested, eg. to filter the feed url or so.
**/

abstract class WP_SoundSytem_Playlist_Scraper_Preset_BEFORE extends WP_SoundSytem_Playlist_Scraper_Preset{
    
    var $pattern; //pattern used to check if the URL to scrape fits the preset
    var $variables; //list of variables that matches the regex groups from $pattern
    var $redirect_url;

    function can_load_preset(){
        if ( $this->scraper->page->datas !== null ) return false; //source already populated
        if ( !$this->is_feed_url_match() ) return false;
        return true;
    }
    
    function init_preset(){
        
        parent::init_preset();
        
        //populate variables from URL
        if ($this->pattern){
            preg_match($this->pattern, $this->scraper->feed_url, $url_matches);

            if ($url_matches){
                array_shift($url_matches); //remove first item (full match)
                $this->populate_variable_values($url_matches);
            }
        }

        //update scraper url
        if ($this->redirect_url){
            $this->scraper->redirect_url = $this->variables_fill_string($this->redirect_url);
        }
    }

    /**
    Checks the input URL to see if this preset should be loaded or not, and init it if required.
    **/
    
    function is_feed_url_match(){

        preg_match($this->pattern, $this->scraper->feed_url, $url_matches);
        return ($url_matches);
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
    
}


class WP_SoundSytem_Playlist_Scraper_Default extends WP_SoundSytem_Playlist_Scraper_Preset_BEFORE{
    var $slug = 'default';
    var $name = null;
    var $description = null;
    
    function can_load_preset(){
        return ($this->scraper->feed_url);
    }
    
    function init_preset(){
        $this->name = __('Default','wpsstm');
        
        parent::init_preset();
    }
    
}


class WP_SoundSytem_Playlist_Scraper_LastFM extends WP_SoundSytem_Playlist_Scraper_Preset_BEFORE{

    var $slug = 'last-fm-website';
    var $name = null;
    var $description = null;
    
    var $pattern = '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))(?:/([^/]+))?~';
    var $variables = array();
    
    var $options = array(
        'selectors' => array(
            'tracks'           => array('path'=>'table.chartlist tbody tr'),
            'track_artist'     => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap .chartlist-artists a'),
            'track_title'      => array('path'=>'td.chartlist-name .chartlist-ellipsis-wrap > a'),
            'track_image'      => array('path'=>'.chartlist-play-image')
        )
    );
    
    function __construct(WP_SoundSytem_Playlist_Scraper $scraper){
        parent::__construct($scraper);
        
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

class WP_SoundSytem_Playlist_Scraper_Spotify_Playlist extends WP_SoundSytem_Playlist_Scraper_Preset_BEFORE{

    var $slug = 'spotify-playlist';
    var $name = null;
    var $description = null;
    
    var $pattern = '/^https?:\/\/(?:open|play)\.spotify\.com\/user\/([\w\d]+)\/playlist\/([\w\d]+)$/i';
    var $variables = array();
    
    var $redirect_url = 'https://open.spotify.com/user/%spotify-user%/playlist/%spotify-playlist%';
    var $options = array(
        'selectors' => array(
            'tracks'           => array('path'=>'.tracklist-container li.tracklist-row'),
            'track_artist'     => array('path'=>'.artists-albums a:eq(1)'),
            'track_title'      => array('path'=>'.track-name'),
        )
    );
    
    function __construct(WP_SoundSytem_Playlist_Scraper $scraper){
        parent::__construct($scraper);

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

class WP_SoundSytem_Playlist_Scraper_Radionomy extends WP_SoundSytem_Playlist_Scraper_Preset_BEFORE{

    var $slug = 'radionomy';
    var $name = null;
    var $description = null;
    
    var $pattern = '~^(?:http(?:s)?://(?:www\.)?radionomy.com/.*?/radio/)([^/]+)~';
    /*
            '~^(?:http(?:s)?://(?:www\.)?radionomy.com/.*?/radio/)([^/]+)~',
            '~^(?:http(?:s)?://listen.radionomy.com/)([^/]+)~',
            '~^(?:http(?:s)?://streaming.radionomy.com/)([^/]+)~',
    */
    var $variables = array();
    
    var $redirect_url = 'http://radionomy.letoptop.fr/ajax/ajax_last_titres.php?radiouid=%radionomy-id%';
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'div.titre'),
            'track_artist'      => array('path'=>'table td','regex'=>'^(.*?)(?:<br ?/?>)'),
            'track_title'       => array('path'=>'table td i'),
            'track_image'       => array('path'=>'img')
        )
    );

    function __construct(WP_SoundSytem_Playlist_Scraper $scraper){
        parent::__construct($scraper);

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

        //set station ID //TO FIX TO MOVE
        if ( $station_id = $this->get_station_id() ){
            $this->set_variable_value('radionomy-id',$station_id);
        }
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

class WP_SoundSytem_Playlist_Scraper_SomaFM extends WP_SoundSytem_Playlist_Scraper_Preset_BEFORE{
    var $slug = 'somafm';
    var $name = null;
    var $description = null;
    
    var $pattern = '~^(?:http(?:s)?://(?:www\.)?somafm.com/)([^/]+)(?:/?)$~';
    var $variables = array();
    
    var $redirect_url = 'http://somafm.com/songs/%somafm-slug%.xml';
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'song'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
        )
    );

    function __construct(WP_SoundSytem_Playlist_Scraper $scraper){
        parent::__construct($scraper);

        $this->name = __('Soma FM Station','wpsstm');
        
        $this->variables = array(
            'somafm-slug' => array(
                'name'  => __('Station slug','wpsstm'),
                'value' => null 
            )
        );
    }
}

class WP_SoundSytem_Playlist_Scraper_BBC_Station extends WP_SoundSytem_Playlist_Scraper_Preset_BEFORE{
    var $slug = 'bbc-station';
    var $name = null;
    var $description = null;
    
    var $pattern = '~^https?://(?:www.)?bbc.co.uk/([\w\d]+)/?$~i';
    var $variables = array();
    
    var $redirect_url = 'http://www.bbc.co.uk/%bbc-slug%/playlist';
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'.pll-playlist-item-wrapper'),
            'track_artist'      => array('path'=>'.pll-playlist-item-details .pll-playlist-item-artist a'),
            'track_title'       => array('path'=>'.pll-playlist-item-details .pll-playlist-item-title'),
            'track_image'       => array('path'=>'img.pll-playlist-item-image')
        )
    );

    function __construct(WP_SoundSytem_Playlist_Scraper $scraper){
        parent::__construct($scraper);

        $this->name = __('BBC station','wpsstm');
        
        $this->variables = array(
            'bbc-slug' => array(
                'name'  => __('Station slug','wpsstm'),
                'value' => null 
            )
        );
    }

}

class WP_SoundSytem_Playlist_Scraper_BBC_Playlist extends WP_SoundSytem_Playlist_Scraper_Preset_BEFORE{
    var $slug = 'bbc-playlist';
    var $name = null;
    var $description = null;
    
    var $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([\w\d]+)~i';
    var $variables = array();
    
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'ul.plr-playlist-trackslist li'),
            'track_artist'      => array('path'=>'.plr-playlist-trackslist-track-name-artistlink'),
            'track_title'       => array('path'=>'.plr-playlist-trackslist-track-name-title'),
        )
    );

    function __construct(WP_SoundSytem_Playlist_Scraper $scraper){
        parent::__construct($scraper);

        $this->name = __('BBC playlist','wpsstm');
        
        $this->variables = array(
            'bbc-playlist-id' => array(
                'name'  => __('Playlist ID','wpsstm'),
                'value' => null 
            )
        );

    } 
}

abstract class WP_SoundSytem_Playlist_Scraper_Preset_AFTER extends WP_SoundSytem_Playlist_Scraper_Preset{
    
    function can_load_preset(){
        if ( $this->scraper->page->response_body === null ) return false; //source has not been populated yet
        return true;
    }
    
}

class WP_SoundSytem_Playlist_Scraper_XSPF extends WP_SoundSytem_Playlist_Scraper_Preset_AFTER{
    
    var $slug = 'xspf';
    var $name = null;
    var $description = null;

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'trackList track'),
            'track_artist'      => array('path'=>'creator'),
            'track_title'       => array('path'=>'title'),
            'track_album'       => array('path'=>'album'),
            'track_location'    => array('path'=>'location'),
            'track_image'       => array('path'=>'image')
        )
    );
    
    function can_load_preset(){
        if ( !parent::can_load_preset() ) return false;
        return ($this->scraper->page->response_type == 'text/xspf+xml');
    }
    
    function __construct(WP_SoundSytem_Playlist_Scraper $scraper){
        parent::__construct($scraper);
        $this->name = __('XSPF','wpsstm');
    } 
    
}