<?php

/*
How the scraper works :

It would be too much queries to save the real tracks history for a live playlist : 
it would require to query each remote playlist URL every ~30sec to check if a new track has been played, 
which would use a LOT of resources; and create thousands and thousands of track posts.

Also, we want live playlists tracklists to be related to an URL rather than to a post ID, allowin us to setup 'services'.
Services are playlists were the sources URL contains regular expressions.  
They are meant to fetch different tracklists depending on the input URL (eg. for Spotify or Last.FM)

So live playlists do not create track posts, but we use the WordPress Transients API to temporary store the tracklist.
When the live playlist is viewed, we refresh the tracklist only if the transient is expired.

The transient name is based on the parser URL, not on the parser ID; which allows us more flexibility.

*/

class WP_SoundSytem_Playlist_Scraper{
    
    static $meta_key_scraper_url = '_wpsstm_scraper_url';
    static $meta_key_options_scraper = '_wpsstm_scraper_options';
    public $options_default;
    public $options;
    public $preset;
    
    public $feed_url;
    public $page;
    public $page_response = null;
    public $tracklist;
    
    public $is_wizard = false;
    public $cache_only = false;

    
    function __construct() {
        
        require_once(wpsstm()->plugin_dir . 'scraper/_inc/php/autoload.php');
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-datas.php');
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-presets.php');
        
        $this->setup_globals();
        $this->setup_actions();
        
    }

    function setup_globals(){

        $this->tracklist = new WP_SoundSytem_Tracklist();
        $this->page = new WP_SoundSytem_Playlist_Scraper_Datas($this);
        
    }
    
    function setup_actions(){
        
    }

    function init_post($post_id){
        
        $this->tracklist = new WP_SoundSytem_Tracklist($post_id);
        
        $db_options = get_post_meta($post_id,self::$meta_key_options_scraper,true);
        $this->options = wp_parse_args($db_options, self::get_default_options() );

        $feed_url = get_post_meta( $post_id, self::$meta_key_scraper_url, true );
        $this->init($feed_url);

    }
    
    function init($feed_url){
        
        $this->feed_url = $feed_url;
        
        //set preset options
        $presets_class = new WP_SoundSytem_Playlist_Scraper_Presets();
        $this->preset = $presets_class->get_url_preset($feed_url);
        if ( $this->preset ){
            $this->options = wp_parse_args($this->preset['options'], $this->options );
            
            if ( is_admin() ){
                $message = sprintf(__('The %s preset has been loaded.','wpsstm'),'<em>'.$this->preset['name'].'</em>' );
                add_settings_error( 'wizard-header', 'has-presets',$message,'updated inline' );
            }

        }

        //populate tracklist
        $datas = $this->page->get_datas($this->cache_only);
        if ($datas && !is_wp_error($datas) && isset($datas['tracks']) ){
            $this->tracklist->add($datas['tracks']);
        }

    }

    static function get_default_options($keys = null){
        
        $default = array(
            'website_url'               => null, //url to parse
            'regexes'                   => array(),
            'selectors' => array(
                'tracks'           => array('path'=>null,'regex'=>null),
                'track_artist'     => array('path'=>null,'regex'=>null),
                'track_title'      => array('path'=>null,'regex'=>null),
                'track_album'      => array('path'=>null,'regex'=>null),
                'track_location'   => array('path'=>null,'regex'=>null),
                'track_image'      => array('path'=>null,'regex'=>null)
            ),
            'tracks_order'              => 'desc',
            'datas_cache_min'          => (int)wpsstm()->get_options('live_playlists_cache_min'),
            'musicbrainz'               => false                               //check tracks with musicbrainz
        );
        
        return wpsstm_get_array_value($keys,$default);
    }

    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);

    }

}



