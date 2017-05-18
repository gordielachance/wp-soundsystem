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

    
    function __construct($post_id_or_feed_url = null) {
        
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-live-tracklist-class.php');

        $this->setup_globals();
        $this->setup_actions();
        
        //populate post ID or URL
        if ( $post_id_or_feed_url ){
            if ( ctype_digit(strval($post_id_or_feed_url)) )  { //check is integer (post ID)
                $this->init_post($post_id_or_feed_url);
            }else{ //url
                $this->init($post_id_or_feed_url);
            }
        }
    }

    function setup_globals(){
        $this->options = self::get_default_options();
    }
    
    function setup_actions(){
        
    }
    
    static function get_default_options($keys = null){
        
        $default = array(
            'selectors' => array(
                'tracklist_title'   => array('path'=>'title','regex'=>null,'attr'=>null),
                'tracks'            => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="track"]'
                'track_artist'      => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="byArtist"]'
                'track_title'       => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="name"]'
                'track_album'       => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="inAlbum"]'
                'track_source_urls' => array('path'=>null,'regex'=>null,'attr'=>null),
                'track_image'       => array('path'=>null,'regex'=>null,'attr'=>null), //'[itemprop="thumbnailUrl"]'
            ),
            'tracks_order'              => 'desc',
            'datas_cache_min'           => (int)wpsstm()->get_options('live_playlists_cache_min'), //time tracklist is cached - if set to null, will take plugin value
            'musicbrainz'               => wpsstm()->get_options('mb_auto_id') //should we use musicbrainz to get the tracks data ? - if set to null, will take plugin value
        );
        
        return wpsstm_get_array_value($keys,$default);
    }

    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }

    function init_post($post_id){

        $this->tracklist = new WP_SoundSytem_Tracklist($post_id);
        
        $default_options = self::get_default_options();
        $db_options = get_post_meta($post_id,self::$meta_key_options_scraper,true);

        $this->options = array_replace_recursive($default_options,(array)$db_options);

        $feed_url = get_post_meta( $post_id, self::$meta_key_scraper_url, true );
        $this->init($feed_url);

    }
    
    function init($feed_url){

        if (!$feed_url) return;

        //cache only if several post are displayed (like an archive page)
        if ( !is_admin() ){
            $this->cache_only = ( !is_singular() );
        }else{ // is_singular() does not exists backend
            $screen = get_current_screen();
            $this->cache_only = ( $screen->parent_base != 'edit' );
        }

        //set feed url
        $this->feed_url = $feed_url;
        $this->id = md5( $this->feed_url ); //unique ID based on URL
        $this->transient_name_cache = 'wpsstm_ltracks_'.$this->id; //WARNING this must be 40 characters max !  md5 returns 32 chars.
        
        //set expire time
        $transient_timeout_name = '_transient_timeout_' . $this->transient_name_cache;
        $this->tracklist->expire_time = get_option ( $transient_timeout_name );

        //try to get cache first

        $this->datas = $this->datas_cache = $this->get_cache();
        if ($this->datas_cache){
            $this->tracklist->add($this->datas_cache['tracks']);
            //we got cached tracks, but do ignore them in wizard
            if ( ( $cached_total_items = count($this->tracklist->tracks) ) && $this->is_wizard ){
                $this->add_notice( 'wizard-header-advanced', 'cache_tracks_loaded', sprintf(__('A cache entry with %1$s tracks was found (%2$s); but is ignored within the wizard.','wpsstm'),$cached_total_items,gmdate(DATE_ISO8601,$this->datas_cache['timestamp'])) );
            }
        }

        //load page preset
        if ( $live_tracklist_preset = $this->get_live_tracklist_preset($this) ){
            $this->tracklist = $live_tracklist_preset;
            $this->add_notice( 'wizard-header', 'preset_loaded', sprintf(__('The preset %s has been loaded','wpsstm'),'<em>'.$page_preset->remote_name.'</em>') );
        }else{
            $this->tracklist = new WP_SoundSytem_Remote_Tracklist();
        }

        //populate page

        $this->tracklist->init($this->feed_url,$this->options);

        //get remote tracks
        if ( ( !$this->tracklist->tracks && (!$this->cache_only) ) || $this->is_wizard ){

            $this->datas_remote = false; // so we can detect that we ran a remote request
            if ( $remote_tracks = $this->tracklist->get_all_raw_tracks() ){

                if ( !is_wp_error($remote_tracks) ) {

                    if ( current_user_can('administrator') ){ //this could reveal 'secret' urls (API keys, etc.) So limit the notice display.
                        if ( $this->feed_url != $this->tracklist->redirect_url ){
                            $this->add_notice( 'wizard-header-advanced', 'scrapped_from', sprintf(__('Scraped from : %s','wpsstm'),'<em>'.$this->tracklist->redirect_url.'</em>') );
                        }
                    }

                    $this->tracklist->add($remote_tracks);

                    //Musicbrainz lookup
                    //TO FIX quite slow for big playlists. Think about a way to handle this.
                    /*
                    if ( $this->get_options('musicbrainz') == 'on'  ){
                        foreach ($this->tracklist->tracks as $track){
                            $track->musicbrainz();
                        }
                    }
                    */

                    //populate page notices
                    foreach($this->tracklist->notices as $notice){
                        $this->notices[] = $notice;
                    }

                    $tracks_arr = $this->tracklist->array_export();

                    //format response
                    $this->datas = $this->datas_remote = array(
                        'title'         => $this->tracklist->get_tracklist_title(),
                        'author'        => $this->tracklist->get_tracklist_author(),
                        'tracks'        => $remote_tracks,
                        'timestamp'     => current_time( 'timestamp' )
                    );

                    //set cache if there is none
                    if ( !$this->datas_cache ){
                        $this->set_cache();
                    }

                }else{
                    $this->add_notice( 'wizard-header', 'remote-tracks', $remote_tracks->get_error_message(),true );
                }
            }

        }

        //get options back from page (a preset could have changed them)
        $this->options = $this->tracklist->options; 

        /*
        Build Tracklist
        */
        
        //tracklist informations
        //set only if not already defined (eg. by a post ID); except for timestamp
        
        $this->tracklist->updated_time = wpsstm_get_array_value('timestamp', $this->datas);
        
        if ( !$this->tracklist->title ){
            $this->tracklist->title = wpsstm_get_array_value('title', $this->datas);
        }
        if ( !$this->tracklist->author ){
            $this->tracklist->author = wpsstm_get_array_value('author', $this->datas);
        }

        if ( !$this->tracklist->location ){
            $this->tracklist->location = $this->feed_url;
        }

        //stats
        if ( $this->datas_remote !==null ){ //we made a remote request
            new WP_SoundSytem_Live_Playlist_Stats($this->tracklist);
        }

    }
    
    public function get_cache(){
        if ( !$this->get_options('datas_cache_min') ){

                $this->add_notice( 'wizard-header-advanced', 'cache_disabled', __("The cache is currently disabled.  Once you're happy with your settings, it is recommanded to enable it (see the Options tab).",'wpsstm') );

            return false;
        }

        if ( $cache = get_transient( $this->transient_name_cache ) ){
            $cache_debug = $cache;
            $cache_debug['tracks_count'] = ( isset($cache['tracks']) ) ? count($cache['tracks']) : null;
            unset($cache_debug['tracks']);
            wpsstm()->debug_log(array('transient'=>$this->transient_name_cache,'cache'=>json_encode($cache_debug)),"WP_SoundSytem_Remote_Tracklist::get_cache()"); 
        }
        
        return $cache;

    }
    
    function set_cache(){

        if ( !$duration_min = $this->get_options('datas_cache_min') ) return;
        
        $duration = $duration_min * MINUTE_IN_SECONDS;
        $success = set_transient( $this->transient_name_cache, $this->datas_remote, $duration );
        
        $debug_cache = $this->datas_remote;
        $debug_cache['tracks_count'] = ( isset($debug_cache['tracks']) ) ? count($debug_cache['tracks']) : null;
        unset($debug_cache['tracks']);
            
        wpsstm()->debug_log(array('success'=>$success,'transient'=>$this->transient_name_cache,'duration_min'=>$duration_min,'cache'=>json_encode($debug_cache)),"WP_SoundSytem_Remote_Tracklist::set_cache()"); 
        
    }

    function delete_cache(){
        delete_transient( $this->transient_name_cache );
    }

    static function get_available_presets(){
        
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-presets.php');
        
        $available_presets = array();
        $available_presets = apply_filters( 'wpsstm_get_scraper_presets',$available_presets );
        
        foreach((array)$available_presets as $key=>$preset){
            if ( !$preset->can_use_preset ) unset($available_presets[$key]);
        }

        return $available_presets;
    }

    function get_live_tracklist_preset($scraper){
        
        $enabled_presets = array();

        $available_presets = self::get_available_presets();

        //get matching presets
        foreach((array)$available_presets as $preset){

            if ( $preset->can_load_tracklist_url($scraper->feed_url) ){
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
