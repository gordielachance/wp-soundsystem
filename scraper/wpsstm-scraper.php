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

    public $feed_url;
    public $redirect_url;
    public $id;
    public $transient_name_cache;
    public $datas_cache = null;
    public $datas_remote = null;
    public $datas = null;
    
    public $page;
    public $page_response = null;
    public $tracklist;
    
    public $is_wizard = false;
    public $cache_only = false;
    
    public $notices = array();

    
    function __construct() {
        
        require_once(wpsstm()->plugin_dir . 'scraper/_inc/php/autoload.php');
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-remote.php');
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-presets.php');

        $this->setup_globals();
        $this->setup_actions();
        
    }

    function setup_globals(){

        $this->tracklist = new WP_SoundSytem_Tracklist();
        $this->page = new WP_SoundSytem_Playlist_Scraper_Datas();
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

        if (!$feed_url) return;
        
        //set feed url
        $this->feed_url = $this->redirect_url = $feed_url;
        $this->id = md5( $this->feed_url ); //unique ID based on URL
        $this->transient_name_cache = 'wpsstm_'.$this->id; //WARNING this must be 40 characters max !  md5 returns 32 chars.

        //try to get cache first
        $this->datas = $this->datas_cache = $this->get_cache();
        
        //we got cached tracks, but do ignore them in wizard
        if ( count($this->datas['tracks']) && $this->is_wizard ){
            $this->add_notice( 'wizard-header-advanced', 'cache_tracks_loaded', sprintf(__('A cache entry with %1$s tracks was found (%2$s); but is ignored within the wizard.','wpsstm'),count($this->datas['tracks']),gmdate(DATE_ISO8601,$this->datas['time'])) );
        }
        
        //load page preset
        if ( $page_preset = $this->populate_scraper_presets($this) ){
            $this->page = $page_preset;
            $this->add_notice( 'wizard-header', 'preset_loaded', sprintf(__('The preset %s has been loaded','wpsstm'),'<em>'.$page_preset->name.'</em>') );
        }
        //populate page
        $this->page->init($this->feed_url,$this->options);

        //get remote tracks
        if ( ( !$this->datas && (!$this->cache_only) ) || $this->is_wizard ){

            $remote_tracks = $this->page->get_tracks();
            
            if ( $this->feed_url != $this->page->redirect_url ){
                $this->add_notice( 'wizard-header-advanced', 'scrapped_from', sprintf(__('Scraped from : %s','wpsstm'),'<em>'.$this->page->redirect_url.'</em>') );
            }

            if ( !is_wp_error($remote_tracks) ) {
                
                //lookup
                /*
                if ( ($this->get_options('musicbrainz')) && ( !$this->is_wizard ) ){
                    foreach ($this->tracklist->tracks as $track){
                        $track->musicbrainz();
                    }
                }
                */
                
                //populate page notices
                foreach($this->page->notices as $notice){
                    $this->notices[] = $notice;
                }
                
                //format response
                $this->datas = $this->datas_remote = array(
                    'tracks'    => $remote_tracks,
                    'time'      => current_time( 'timestamp' )
                );

                //set cache if there is none
                if ( !$this->datas_cache ){
                    $this->set_cache();
                }
                
            }else{
                $this->add_notice( 'wizard-header', 'remote-tracks', $remote_tracks->get_error_message(),true );
            }
            


            //repopulate author & title as we might change them depending of the page content
            //$this->title = $this->get_station_title();
            //$this->author = $this->get_station_author();

        }
        
        //get options back from page (a preset could have changed them)
        $this->options = $this->page->options; 

        if ($this->datas && isset($this->datas['tracks']) ){
            $this->tracklist->add($this->datas['tracks']);
        }

    }
    
    public function get_cache(){
        if ( !$this->get_options('datas_cache_min') ){

                $this->add_notice( 'wizard-header-advanced', 'cache_disabled', __("The cache is currently disabled.  Once you're happy with your settings, it is recommanded to enable it (see the Options tab).",'wpsstm') );

            return false;
        }

        if ( $cache = get_transient( $this->transient_name_cache ) ){
            wpsstm()->debug_log(array('transient'=>$this->transient_name_cache,'cache'=>json_encode($cache)),"WP_SoundSytem_Playlist_Scraper_Datas::get_cache()"); 
        }
        
        return $cache;

    }
    
    function set_cache(){

        if ( !$duration_min = $this->get_options('datas_cache_min') ) return;
        
        $duration = $duration_min * MINUTE_IN_SECONDS;
        $success = set_transient( $this->transient_name_cache, $this->datas_remote, $duration );

        wpsstm()->debug_log(array('success'=>$success,'transient'=>$this->transient_name_cache,'duration_min'=>$duration_min,'cache'=>json_encode($this->datas_remote)),"WP_SoundSytem_Playlist_Scraper_Datas::set_cache()"); 
        
    }

    function delete_cache(){
        delete_transient( $this->transient_name_cache );
    }

    static function get_default_options($keys = null){
        
        $default = array(
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
            'musicbrainz'               => false //check tracks with musicbrainz
        );
        
        return wpsstm_get_array_value($keys,$default);
    }

    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }

    function populate_scraper_presets($scraper){
        
        $enabled_presets = array();

        $all_presets = apply_filters( 'wpsstm_scraper_presets',array() );

        //get matching presets
        foreach((array)$all_presets as $preset){

            if ( $preset->can_load_preset($scraper->feed_url) ){
                $enabled_presets[] = $preset;
            }

        }
        
        //return last (highest priority) preset
        return end($enabled_presets);

    }
    
    /*
    Use a custom function to display our notices since natice WP function add_settings_error() works only backend.
    */
    
    function add_notice($slug,$code,$message,$error = false){
        
        wpsstm()->debug_log(json_encode(array('slug'=>$slug,'code'=>$code,'error'=>$error)),'[WP_SoundSytem_Playlist_Scraper notice]: ' . $message ); 
        
        $this->notices[] = array(
            'slug'      => $slug,
            'code'      => $code,
            'message'   => $message,
            'error'     => $error
        );

    }
       
    /*
    Render notices as WP settings_errors() would.
    */
    
    function display_notices($slug){
 
        foreach ($this->notices as $notice){
            if ( $notice['slug'] != $slug ) continue;
            
            $notice_classes = array(
                'inline',
                'settings-error',
                'notice',
                'is-dismissible'
            );
            
            $notice_classes[] = ($notice['error'] == true) ? 'error' : 'updated';

            printf('<div %s><p><strong>%s</strong></p></div>',wpsstm_get_classes_attr($notice_classes),$notice['message']);
        }
    }

}



