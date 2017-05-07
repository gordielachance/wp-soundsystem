<?php

abstract class WP_SoundSytem_Playlist_Scraper_Preset extends WP_SoundSytem_Playlist_Scraper_Datas{
    var $slug = null;
    var $name = null;
    
    var $pattern = null; //regex pattern that would match an URL
    var $redirect_url = null; //real URL of the tracklist; can use the values from the regex groups captured with the pattern above.
    var $variables = array(); //list of slugs that would match the regex groups captured with the pattern above - eg. array('username','playlist-id')
    
    var $can_use_preset = true; //if this preset requires special conditions (eg. an API key or so), override this in your preset class.
    var $wizard_suggest = true; //suggest or not this preset in the wizard

    public function init($url,$options){
        parent::init($url,$options);
        
        //populate variables from URL
        if ($this->pattern){
            
            preg_match($this->pattern, $this->url, $url_matches);
            if ( $url_matches ){
                
                array_shift($url_matches); //remove first item (full match)
                $this->populate_variable_values($url_matches);
            }
        }
    }
    
    /**
    If $url_matches is empty, it means that the feed url does not match the pattern.
    **/
    
    public function can_load_tracklist_url($url){

        if (!$this->pattern) return true;

        preg_match($this->pattern, $url, $url_matches);

        return (bool)$url_matches;
    }
    
    /**
    Fill the preset $variables.
    The array keys from the preset $variables and the input $values_arr have to match.
    **/

    protected function populate_variable_values($values_arr){
        
        $key = 0;

        foreach((array)$this->variables as $variable_slug=>$variable){
            $value = ( isset($values_arr[$key]) ) ? $values_arr[$key] : null;
            
            if ($value){
                $this->set_variable_value($variable_slug,$value);
            }
            
            $key++;
        }

    }

    protected function set_variable_value($slug,$value='null'){
        $this->variables[$slug] = $value;
    }
    
    public function get_variable_value($slug){

        foreach($this->variables as $variable_slug => $variable){
            
            if ( $variable_slug == $slug ){
                return $variable;
            }
        }

    }
    
    /*
    Update a string and replace all the %variable-key% parts of it with the value of that variable if it exists.
    */
    
    public function variables_fill_string($str){

        foreach($this->variables as $variable_slug => $variable_value){
            $pattern = '%' . $variable_slug . '%';
            $value = $variable_value;
            
            if ($value) {
                $str = str_replace($pattern,$value,$str);
            }
        }

        return $str;
    }
    

}

class WP_SoundSytem_Playlist_Scraper_LastFM extends WP_SoundSytem_Playlist_Scraper_Preset{

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

class WP_SoundSytem_Playlist_Scraper_Spotify_Playlist extends WP_SoundSytem_Playlist_Scraper_Preset{

    //TO FIX is limited to 100 tracks.  Find a way to get more.
    //https://developer.spotify.com/web-api/console/get-playlist-tracks
    
    var $slug = 'spotify-playlist';
    
    var $pattern = '~^https?://(?:open|play).spotify.com/user/([^/]+)/playlist/([^/]+)/?$~i';
    var $redirect_url = 'https://api.spotify.com/v1/users/%spotify-user%/playlists/%spotify-playlist%/tracks';
    var $variables = array(
        'spotify-user' => null,
        'spotify-playlist' => null
    );
    
    var $token = null;

    var $options = array(
        'selectors' => array(
            'tracks'           => array('path'=>'root > items'),
            'track_artist'     => array('path'=>'track > artists > name'),
            'track_album'      => array('path'=>'track > album > name'),
            'track_title'      => array('path'=>'track > name'),
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('Spotify Playlist','wpsstm');

        $client_id = wpsstm()->get_options('spotify_client_id');
        $client_secret = wpsstm()->get_options('spotify_client_secret');
        
        if ( !$client_id || !$client_secret ){
            $this->can_use_preset = false;
        }

    }
    
    function get_tracklist_title(){
        if ( !$user_id = $this->get_variable_value('spotify-user') ) return;
        if ( !$playlist_id = $this->get_variable_value('spotify-playlist') ) return;
        
        $response = wp_remote_get( sprintf('https://api.spotify.com/v1/users/%s/playlists/%s',$user_id,$playlist_id), $this->get_request_args() );
        
        $json = wp_remote_retrieve_body($response);
        
        if ( is_wp_error($json) ) return $json;
        
        $api = json_decode($json,true);
        
        return wpsstm_get_array_value('name', $api);
    }
    
    function get_tracklist_author(){
        return $this->get_variable_value('spotify-user');
    }
    
    function get_request_args(){
        $args = parent::get_request_args();

        if ( $token = $this->get_access_token() ){

            $args['headers']['Authorization'] = 'Bearer ' . $token;
            $this->set_variable_value('spotify-token',$token);
            
        }
        
        $args['headers']['Accept'] = 'application/json';

        return $args;
    }

    function get_access_token(){
        
        if ($this->token === null){
            
            $this->token = false;
            
            $client_id = wpsstm()->get_options('spotify_client_id');
            $client_secret = wpsstm()->get_options('spotify_client_secret');

            $args = array(
                'headers'   => array(
                    'Authorization' => 'Basic '.base64_encode($client_id.':'.$client_secret)
                ),
                'body'      => array(
                    'grant_type'    => 'client_credentials'
                )
            );


            $response = wp_remote_post( 'https://accounts.spotify.com/api/token', $args );

            if ( is_wp_error($response) ){
                wpsstm()->debug_log($response->get_error_message(),'Spotify preset error' ); 
            }
            $body = wp_remote_retrieve_body($response);
            $body = json_decode($body);
            $this->token = $body->access_token;
            
        }
        
        return $this->token;

    }
    
}

class WP_SoundSytem_Playlist_Scraper_Radionomy extends WP_SoundSytem_Playlist_Scraper_Preset{

    var $slug = 'radionomy';
    
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
            'track_image'       => array('path'=>'img','attr'=>'src')
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

    function get_station_id(){
        
        $slug = $this->get_variable_value('radionomy-slug');
        if (!$slug) return false;

        $transient_name = 'wpsstm-radionomy-' . $slug . '-id';

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
    
    function get_tracklist_title(){
        if ( !$slug = $this->get_variable_value('radionomy-slug') ) return;
        return sprintf(__('Radionomy : %s','wppstm'),$slug);
    }

}

class WP_SoundSytem_Playlist_Scraper_SomaFM extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'somafm';
    
    var $pattern = '~^https?://(?:www.)?somafm.com/([^/]+)/?$~i';
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
    
    function get_tracklist_title(){
        if ( !$slug = $this->get_variable_value('somafm-slug') ) return;
        return sprintf(__('Somafm : %s','wppstm'),$slug);
    }

}

class WP_SoundSytem_Playlist_Scraper_BBC_Station extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'bbc-station';

    var $pattern = '~^https?://(?:www.)?bbc.co.uk/(?!music)([^/]+)/?~i';
    var $redirect_url= 'http://www.bbc.co.uk/%bbc-slug%/playlist';
    var $variables = array(
        'bbc-slug' => null
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'.pll-playlist-item-wrapper'),
            'track_artist'      => array('path'=>'.pll-playlist-item-details .pll-playlist-item-artist'),
            'track_title'       => array('path'=>'.pll-playlist-item-details .pll-playlist-item-title'),
            'track_image'       => array('path'=>'img.pll-playlist-item-image','attr'=>'src')
        )
    );

    function __construct(){
        parent::__construct();

        $this->name = __('BBC station','wpsstm');

    }

}

class WP_SoundSytem_Playlist_Scraper_BBC_Playlist extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'bbc-playlist';
    
    var $pattern = '~^https?://(?:www.)?bbc.co.uk/music/playlists/([^/]+)/?$~i';
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

}

class WP_SoundSytem_Playlist_Scraper_Slacker_Station extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'slacker-station-tops';
    
    var $pattern = '~^https?://(?:www.)?slacker.com/station/([^/]+)/?~i';
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

}

class WP_SoundSytem_Playlist_Scraper_Soundcloud extends WP_SoundSytem_Playlist_Scraper_Preset{
    
    var $slug = 'soundcloud';
    
    var $pattern = '~^https?://(?:www.)?soundcloud.com/([^/]+)/?([^/]+)?~i';
    var $redirect_url= 'http://api.soundcloud.com/users/%soundcloud-userid%/%soundcloud-api-page%?client_id=%soundcloud-client-id%';
    var $variables = array(
        'soundcloud-username' => null,
        'soundcloud-page' => null
    );
    var $page_api = null;

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

        $this->name = __('Soundcloud user tracks or likes','wpsstm');
        
        if ( $client_id = wpsstm()->get_options('soundcloud_client_id') ){
            $this->set_variable_value('soundcloud-client-id',$client_id);
        }else{
            $this->can_use_preset = false;
        }

    }

    function get_remote_url(){
        
        //get soundcloud user ID
        $user_id = $this->get_user_id();
        if (!$user_id) return false;
        $this->set_variable_value('soundcloud-userid',$user_id);

        $page = $this->get_variable_value('soundcloud-page');
        
        switch($page){
            case 'likes':
                $this->page_api = 'favorites';
            break;
            default:
                $this->page_api = 'tracks';
            break;
        }
        $this->set_variable_value('soundcloud-api-page',$this->page_api);
        
        return parent::get_remote_url();
    }
    
    function get_user_id(){
        
        $username = $this->get_variable_value('soundcloud-username');
        if (!$username) return false;
        
        $client_id = $this->get_variable_value('soundcloud-client-id');
        if (!$client_id) return false;

        $transient_name = 'wpsstm-soundcloud-' . $username . '-userid';

        if ( false === ( $user_id = get_transient($transient_name ) ) ) {

            $api_url = sprintf('http://api.soundcloud.com/resolve.json?url=http://soundcloud.com/%s&client_id=%s',$username,$client_id);
            $response = wp_remote_get( $api_url );

            if ( is_wp_error($response) ) return;

            $response_code = wp_remote_retrieve_response_code( $response );
            if ($response_code != 200) return;

            $content = wp_remote_retrieve_body( $response );
            if ( is_wp_error($content) ) return;
            $content = json_decode($content);

            if ( $user_id = $content->id ){
                set_transient( $transient_name, $user_id );
            }

        }
        
        return $user_id;

    }
    
    function get_tracklist_title(){
        
        $page = $this->get_variable_value('soundcloud-page');
        $username = $this->get_variable_value('soundcloud-username');
        
        $title = sprintf(__('%s on Soundcloud','wpsstm'),$username);
        $subtitle = null;
        
        switch($this->page_api){
            case 'favorites':
                $subtitle = __('Favorite tracks','wpsstm');
            break;
            case 'tracks':
                $subtitle = __('Tracks','wpsstm');
            break;
        }
        
        if ($subtitle){
            return $title . ' - ' . $subtitle;
        }else{
            return $title;
        }
    }

}

class WP_SoundSytem_Playlist_Scraper_Soundsgood extends WP_SoundSytem_Playlist_Scraper_Preset{
    
    var $slug = 'soundsgood';
    
    var $pattern = '~^https?://play.soundsgood.co/playlist/([^/]+)~i';
    var $redirect_url= 'https://api.soundsgood.co/playlists/%soundsgood-playlist-slug%/tracks';
    var $variables = array(
        'soundsgood-playlist-slug' => null,
    );

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'root > element'),
            'track_artist'      => array('path'=>'artist'),
            'track_title'       => array('path'=>'title'),
            'track_source_urls' => array('path'=>'sources permalink')
        )
    );
    
    function __construct(){
        parent::__construct();

        $this->name = __('Soundsgood playlists','wpsstm');

    } 

    function get_request_args(){
        $args = parent::get_request_args();

        if ( $client_id = $this->get_client_id() ){
            $args['headers']['client'] = $client_id;
            $this->set_variable_value('soundsgood-client-id',$client_id);
        }

        return $args;
    }

    function get_client_id(){
        return '529b7cb3350c687d99000027:dW6PMNeDIJlgqy09T4GIMypQsZ4cN3IoCVXIyPzJYVrzkag5';
    }
    
    function get_tracklist_title(){
        $slug = $this->get_variable_value('soundsgood-playlist-slug');
        return sprintf(__('%s on Soundsgood','wpsstm'),$slug);
    }
    
}

class WP_SoundSytem_Playlist_Scraper_Deezer extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'deezer';
    
    var $pattern = '~^https?://(?:www.)?deezer.com/playlist/([^/]+)~i';
    
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'#tab_tracks_content [itemprop="track"]'),
            'track_artist'      => array('path'=>'[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'pan[itemprop="name"]'),
            'track_album'       => array('path'=>'[itemprop="inAlbum"]')
        )
    );
    
    function __construct(){
        parent::__construct();
        $this->name = __('Deezer Playlist','wpsstm');
    }
    /*
    function get_body_node($content){
        print_r("<xmp>");
        print_r($content);
        print_r("</xmp>");
    }
    */
}

class WP_SoundSytem_Playlist_Scraper_Hypem extends WP_SoundSytem_Playlist_Scraper_Preset{
    
    var $slug = 'hypem';
    
    var $pattern = '~^https?://(?:www.)?hypem.com/~i';

    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'.section-track'),
            'track_artist'      => array('path'=>'.track_name .artist'),
            'track_title'       => array('path'=>'.track_name .track'),
            //'track_image'       => array('path'=>'a.thumb','attr'=>'src')
        )
    );
    
    function __construct(){
        parent::__construct();
        $this->name = __('Hype Machine','wpsstm');
    }
 
}

class WP_SoundSytem_Playlist_Scraper_Twitter extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'twitter';
    
    var $pattern = '~^https?://(?:(?:www|mobile).)?twitter.com/([^/]+)/?$~i';
    var $redirect_url= 'https://mobile.twitter.com/%twitter-username%';
    var $variables = array(
        'twitter-username' => null
    );
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'#main_content .timeline .tweet .tweet-text div')
        )
    );
    
    var $wizard_suggest = false; //Prefills the wizard but is not able to get a tracklist by itself, so don't populate frontend.

    function __construct(){
        parent::__construct();

        $this->name = __('Twitter','wpsstm');

    } 

}

class WP_SoundSytem_Playlist_Scraper_RTBF extends WP_SoundSytem_Playlist_Scraper_Preset{
    var $slug = 'rtbf';
    
    var $pattern = '~^https?://(?:www.)?rtbf.be/(?!lapremiere)([^/]+)~i'; //ignore la premiere which has different selectors.
    var $redirect_url= 'https://www.rtbf.be/%rtbf-slug%/conducteur';
    var $variables = array(
        'rtbf-slug' => null
    );
    var $options = array(
        'selectors' => array(
            'tracks'            => array('path'=>'li.radio-thread__entry'),
            'track_artist'      => array('path'=>'span[itemprop="byArtist"]'),
            'track_title'       => array('path'=>'span[itemprop="name"]'),
            'track_image'       => array('path'=>'img[itemprop="inAlbum"]','attr'=>'data-src')
        )
    );
    
    var $wizard_suggest = false;

    function __construct(){
        parent::__construct();

        $this->name = __('RTBF radios','wpsstm');

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
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Soundsgood();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Deezer();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Hypem();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_Twitter();
    $presets[] = new WP_SoundSytem_Playlist_Scraper_RTBF();
    
    return $presets;
}

add_filter('wpsstm_get_scraper_presets','wpsstm_register_scraper_presets');

    