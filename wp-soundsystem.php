<?php
/*
Plugin Name: WP SoundSystem
Description: Manage a music library within Wordpress; including playlists, tracks, artists, albums and radios.  The perfect fit for your music blog !
Plugin URI: https://api.spiff-radio.org
Author: G.Breant
Author URI: https://profiles.wordpress.org/grosbouff/#content-plugins
Version: 3.1.6
License: GPL2
*/

define("WPSSTM_BASE_SLUG", "music");

define("WPSSTM_LIVE_PLAYLISTS_SLUG", "radios");
define("WPSSTM_LIVE_PLAYLIST_SLUG", "radio");
define("WPSSTM_PLAYLISTS_SLUG", "playlists");
define("WPSSTM_PLAYLIST_SLUG", "playlist");
define("WPSSTM_ARTISTS_SLUG", "artists");
define("WPSSTM_ARTIST_SLUG", "artist");
define("WPSSTM_ALBUMS_SLUG", "albums");
define("WPSSTM_ALBUM_SLUG", "album");
define("WPSSTM_TRACKS_SLUG", "tracks");
define("WPSSTM_TRACK_SLUG", "track");
define("WPSSTM_SUBTRACKS_SLUG", "subtracks");
define("WPSSTM_SUBTRACK_SLUG", "subtrack");
define("WPSSTM_LINKS_SLUG", "links");
define("WPSSTM_LINK_SLUG", "link");

define("WPSSTM_NEW_ITEM_SLUG", "new");
define("WPSSTM_MANAGER_SLUG", "manager");

define('WPSSTM_REST_NAMESPACE','wpsstm/v1');

class WP_SoundSystem {
    /** Version ***************************************************************/
    /**
    * @public string plugin version
    */
    public $version = '3.1.6';
    /**
    * @public string plugin DB version
    */
    public $db_version = '212';
    /** Paths *****************************************************************/
    public $file = '';
    /**
    * @public string Basename of the plugin directory
    */
    public $basename = '';
    /**
    * @public string Absolute path to the plugin directory
    */
    public $plugin_dir = '';

    public $post_type_album =       'wpsstm_release';
    public $post_type_artist =      'wpsstm_artist';
    public $post_type_track =       'wpsstm_track';
    public $post_type_track_link =  'wpsstm_track_link';
    public $post_type_playlist =    'wpsstm_playlist';
    public $post_type_radio =       'wpsstm_radio';
    
    public $tracklist_post_types = array('wpsstm_playlist','wpsstm_radio','wpsstm_release');

    public $subtracks_table_name = 'wpsstm_subtracks';
    public $user;

    public $meta_name_options = 'wpsstm_options';
    
    var $menu_page;
    var $options = array();
    var $details_engine;
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
        
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem;
                    self::$instance->setup_globals();
                    self::$instance->includes();
                    self::$instance->setup_actions();
            }
            return self::$instance;
    }
    /**
    * A dummy constructor to prevent plugin from being loaded more than once.
    */
    private function __construct() { /* Do nothing here */ }
    
    function setup_globals() {
        
        /** Paths *************************************************************/
        $this->file       = __FILE__;
        $this->basename   = plugin_basename( $this->file );
        $this->plugin_dir = plugin_dir_path( $this->file );
        $this->plugin_url = plugin_dir_url ( $this->file );

        $options_default = array(
            'player_enabled'                    => true,
            'importer_page_id'                  => null,
            'nowplaying_id'                     => null,
            'play_history_timeout'              => 1 * DAY_IN_SECONDS, //how long a track is stored in the plays history
            'playing_timeout'                   => 5 * MINUTE_IN_SECONDS,//how long a track is considered playing 'now'
            'recent_wizard_entries'             => get_option( 'posts_per_page' ),
            'bot_user_id'                       => null,
            'autolink'                          => true,
            'autolink_timeout'                  => 7 * DAY_IN_SECONDS,
            'limit_autolinks'                   => 5,//max number of links returned by autolink
            'wpsstmapi_token'                   => null,
            'wpsstmapi_timeout'                 => 20,//timeout for API requests (seconds)
            'details_engines'                   => array('musicbrainz','spotify'),
            'excluded_track_link_hosts'         => array(),
            'playlists_manager'                 => true,
            'ajax_tracks'                       => false,//URGENT
            'ajax_links'                        => true,//URGENT
            'ajax_autolink'                     => true,
        );
        
        $db_option = get_option( $this->meta_name_options);
        $this->options = wp_parse_args($db_option,$options_default);
        
    }
    
    function includes(){
        
        require_once(wpsstm()->plugin_dir . '_inc/php/autoload.php'); // PHP dependencies
        
        require $this->plugin_dir . 'wpsstm-templates.php';
        require $this->plugin_dir . 'wpsstm-functions.php';
        require $this->plugin_dir . 'wpsstm-settings.php';
        
        require $this->plugin_dir . 'wpsstm-core-tracklists.php';
        require $this->plugin_dir . 'wpsstm-core-playlists.php';
        require $this->plugin_dir . 'wpsstm-core-radios.php';
        require $this->plugin_dir . 'wpsstm-core-albums.php';
        require $this->plugin_dir . 'wpsstm-core-artists.php';
        require $this->plugin_dir . 'wpsstm-core-tracks.php';
        require $this->plugin_dir . 'wpsstm-core-track-links.php';
        
        require $this->plugin_dir . 'wpsstm-core-user.php';
        require $this->plugin_dir . 'wpsstm-core-buddypress.php';
        require $this->plugin_dir . 'wpsstm-core-api.php';
        require $this->plugin_dir . 'classes/wpsstm-data-engine.php';
        require $this->plugin_dir . 'wpsstm-core-importer.php';
        
        require $this->plugin_dir . 'classes/wpsstm-track-class.php';
        require $this->plugin_dir . 'classes/wpsstm-tracklist-class.php';
        require $this->plugin_dir . 'classes/wpsstm-post-tracklist-class.php';
        require $this->plugin_dir . 'classes/wpsstm-track-link-class.php';
        require $this->plugin_dir . 'classes/wpsstm-player-class.php';
        
        //include APIs/services stuff (lastfm,youtube,spotify,etc.)
        $this->load_services();
    }
    
    /*
    Register scraper presets.
    */
    private function load_services(){
        
        $presets = array();

        $presets_path = trailingslashit( wpsstm()->plugin_dir . 'classes/services' );

        //get all files in /presets directory
        $preset_files = glob( $presets_path . '*.php' ); 

        foreach ($preset_files as $file) {
            require_once($file);
        }
        
        do_action('wpsstm_load_services');
    }
    
    function setup_actions(){
        // activation, deactivation...
        register_activation_hook( $this->file, array( $this, 'activate_wpsstm'));
        register_deactivation_hook( $this->file, array( $this, 'deactivate_wpsstm'));

        //init
        add_action( 'init', array($this,'init_post_types'), 5);
        add_action( 'init', array($this,'init_rewrite'), 5);
        add_action( 'init', array($this,'populate_data_engines'));
        add_action( 'admin_init', array($this,'load_textdomain'));
        
        add_action( 'init', array($this, 'upgrade'), 9);

        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts_styles' ), 9 );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_styles' ), 9 );

        add_action('edit_form_after_title', array($this,'metabox_reorder'));
        
        add_action( 'all_admin_notices', array($this, 'promo_notice'), 5 );
        
        add_filter( 'query_vars', array($this,'add_wpsstm_query_vars'));
        
        

        do_action('wpsstm_init');

    }

    function add_wpsstm_query_vars($qvars){
        $qvars[] = 'wpsstm_action';
        return $qvars;
    }

    // Move all "after_title" metaboxes above the default editor
    function metabox_reorder(){
        global $post, $wp_meta_boxes;
        do_meta_boxes(get_current_screen(), 'after_title', $post);
        unset($wp_meta_boxes[get_post_type($post)]['after_title']);
    }

    function load_textdomain() {
        load_plugin_textdomain( 'wpsstm', false, $this->plugin_dir . '/languages' );
    }
	
    function activate_wpsstm() {
        self::debug_log('activation');
        
        //clear some transients
        WPSSTM_Settings::clear_premium_transients();
        
        $this->add_custom_capabilites();
    }
    
    function init_post_types(){
        //self::debug_log('init post types');
        do_action('wpsstm_init_post_types');
    }
    
    /*
    Hook for rewrite rules.
    */
    function init_rewrite(){
        //self::debug_log('set rewrite rules');

        do_action('wpsstm_init_rewrite');
        
        flush_rewrite_rules();
    }

    function deactivate_wpsstm() {
        self::debug_log('deactivation');
        $this->remove_custom_capabilities();
        flush_rewrite_rules();
    }

    function upgrade(){

        global $wpdb;
        $current_version = get_option("_wpsstm-db_version");
        $subtracks_table = $wpdb->prefix . $this->subtracks_table_name;

        if ($current_version==$this->db_version) return false;
        if(!$current_version){ //not installed

            $this->setup_subtracks_table();
            $this->create_bot_user();
            $this->create_import_page();
            $this->create_nowplaying_post();
            $this->create_sitewide_favorites_post();

        }else{

            if ($current_version < 201){
                //
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'",'_wpsstm_details_musicbrainz_id', '_wpsstm_mbid' );
                $result = $wpdb->get_results ( $querystr );
                //
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'",'_wpsstm_details_musicbrainz_data', '_wpsstm_mbdata' );
                $result = $wpdb->get_results ( $querystr );
                //
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'",'_wpsstm_details_musicbrainz_time', '_wpsstm_mbdata_time' );
                $result = $wpdb->get_results ( $querystr );

                //
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s'  WHERE meta_key = '%s'",'_wpsstm_details_spotify_id', '_wpsstm_spotify_id' );
                $result = $wpdb->get_results ( $querystr );
                //
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'",'_wpsstm_details_spotify_data', '_wpsstm_spotify_data' );
                $result = $wpdb->get_results ( $querystr );
                //
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'",'_wpsstm_details_spotify_time', '_wpsstm_spotify_data_time' );
                $result = $wpdb->get_results ( $querystr );
            }
            
            if ($current_version < 202){
                
                $querystr = $wpdb->prepare( "SELECT post_id,meta_value FROM `$wpdb->postmeta` WHERE meta_key = %s", WPSSTM_Post_Tracklist::$importer_options_meta_name );
                
                $rows = $wpdb->get_results($querystr);

                foreach($rows as $row){
                    $metadata = maybe_unserialize($row->meta_value);
                    
                    $min = isset($metadata['remote_delay_min']) ? $metadata['remote_delay_min'] : false;
                    if( $min === false ) continue;

                    update_post_meta($row->post_id, WPSSTM_Core_Radios::$cache_min_meta_name, $min);
                    
                    unset($metadata['remote_delay_min']);
                    update_post_meta($row->post_id, WPSSTM_Post_Tracklist::$importer_options_meta_name, $metadata);
                    
                    
                }
            }
            
            if ($current_version < 204){
                
                //rename post type
                $querystr = $wpdb->prepare( "UPDATE $wpdb->posts SET post_type = '%s' WHERE post_type = '%s'",$this->post_type_track_link,'wpsstm_source' );
                $result = $wpdb->get_results ( $querystr );
                
                //rename _wpsstm_source_url metas
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'",WPSSTM_Core_Track_Links::$link_url_metakey, '_wpsstm_source_url' );
                $result = $wpdb->get_results ( $querystr );
                
                //rename _wpsstm_autosource_time metas
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'",WPSSTM_Core_Track_Links::$autolink_time_metakey, '_wpsstm_autosource_time' );
                $result = $wpdb->get_results ( $querystr );
                
            }
            
            if ($current_version < 205){
                $this->migrate_old_subtracks();
            }

            if ($current_version < 210){
                //TRACKS ARTIST - migrate meta to taxonomy
                $querystr = $wpdb->prepare( "SELECT post_id,meta_value FROM `$wpdb->postmeta` WHERE meta_key = %s", '_wpsstm_artist' );
                $results = $wpdb->get_results ( $querystr );

                foreach((array)$results as $meta){
                    
                    //TOUFIX TOUCHECK should this be here ?
                    $post_type = get_post_type($meta->post_id);
                    if ( $post_type !== wpsstm()->post_type_track ) continue;

                    $success = wp_set_post_terms( $meta->post_id, $meta->meta_value, WPSSTM_Core_Tracks::$artist_taxonomy);

                    if ( !is_wp_error($success) ){
                        $success = delete_post_meta($meta->post_id,'_wpsstm_artist');
                        
                    }
                    
                }
                
                //TRACKS TITLE - migrate meta to taxonomy
                $querystr = $wpdb->prepare( "SELECT post_id,meta_value FROM `$wpdb->postmeta` WHERE meta_key = %s", '_wpsstm_track' );
                $results = $wpdb->get_results ( $querystr );
            
                
                foreach((array)$results as $meta){
                    
                    //TOUFIX TOUCHECK should this be here ?
                    $post_type = get_post_type($meta->post_id);
                    if ( $post_type !== wpsstm()->post_type_track ) continue;

                    $success = wp_set_post_terms( $meta->post_id, $meta->meta_value, WPSSTM_Core_Tracks::$track_taxonomy);

                    if ( !is_wp_error($success) ){
                        delete_post_meta($meta->post_id,'_wpsstm_track');
                        
                    }
                    
                }
                
                //TRACKS ALBUM - migrate meta to taxonomy
                $querystr = $wpdb->prepare( "SELECT post_id,meta_value FROM `$wpdb->postmeta` WHERE meta_key = %s", '_wpsstm_release' );
                $results = $wpdb->get_results ( $querystr );
                
                foreach((array)$results as $meta){
                    
                    //TOUFIX TOUCHECK should this be here ?
                    $post_type = get_post_type($meta->post_id);
                    if ( $post_type !== wpsstm()->post_type_track ) continue;

                    $success = wp_set_post_terms( $meta->post_id, $meta->meta_value, WPSSTM_Core_Tracks::$album_taxonomy);

                    if ( !is_wp_error($success) ){
                        delete_post_meta($meta->post_id,'_wpsstm_release');
                        
                    }
                    
                }
                
            }
            
            if ($current_version < 211){
                
                //migrate community user
                if ( $community_id = $this->get_options('community_user_id') ){
                    $success = $this->update_option( 'bot_user_id', $community_id );
                }
                //migrate frontend importer
                if ( $page_id = $this->get_options('frontend_scraper_page_id') ){
                    $success = $this->update_option( 'importer_page_id', $page_id );
                }

                self::batch_delete_orphan_tracks();
                
                //remove unused music terms since we hadn't cleanup functions before this version
                self::batch_delete_unused_music_terms();
            }
            
            if ($current_version < 212){
                $results = $wpdb->query( "UPDATE `$wpdb->posts` SET `post_type` = 'wpsstm_radio' WHERE `wp_posts`.`post_type` = 'wpsstm_live_playlist'");
                $results = $wpdb->query( "ALTER TABLE `$subtracks_table` CHANGE `ID` `subtrack_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT" );
                $results = $wpdb->query( "ALTER TABLE `$subtracks_table` CHANGE `time` `subtrack_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00'");
                $results = $wpdb->query( "ALTER TABLE `$subtracks_table` CHANGE `track_order` `subtrack_order` int(11) NOT NULL DEFAULT '0'");
                $results = $wpdb->query( "ALTER TABLE `$subtracks_table` ADD subtrack_author bigint(20) UNSIGNED NULL" );
                
                $this->create_nowplaying_post();
                $this->create_sitewide_favorites_post();

            }

        }
        
        //update DB version
        update_option("_wpsstm-db_version", $this->db_version );
    }
    
    private function create_bot_user(){

        $bot_id = wp_create_user( 
            'wpsstm bot',
            wp_generate_password()
        );
        if ( is_wp_error($bot_id) ) return $bot_id;
        
        $this->update_option( 'bot_user_id', $bot_id );
        self::debug_log($bot_id,'created bot user');

        $user = new WP_User( $bot_id );
        
        //TOFIX URGENT should be caps instead of role ?
        $success = $user->set_role( 'author' );

        return $success;
    }
    
    //TOUFIX should be a radio, but breaks because then it has no URL
    private function create_import_page(){
        $post_details = array(
            'post_title' =>     __('Tracklist importer','wpsstm'),
            'post_status' =>    'publish',
            'post_author' =>    get_current_user_id(),
            'post_type' =>      'page'
        );
        $page_id = wp_insert_post( $post_details );
        if ( is_wp_error($page_id) ) return $page_id;
        
        self::debug_log($page_id,'created importer page');
        
        return $this->update_option( 'importer_page_id', $page_id );
    }
    
    //TOUFIX should be a radio, but breaks because then it has no URL
    private function create_nowplaying_post(){
        $post_details = array(
            'post_title' =>     __('Now playing','wpsstm'),
            'post_status' =>    'publish',
            'post_author' =>    get_current_user_id(),//TOUFIX SHOULD BE SPIFFBOT ? is he available ?
            'post_type' =>      wpsstm()->post_type_playlist
        );
        $page_id = wp_insert_post( $post_details );
        if ( is_wp_error($page_id) ) return $page_id;
        
        self::debug_log($page_id,'created now playing post');
        
        return $this->update_option( 'nowplaying_id', $page_id );
    }
    
    private function create_sitewide_favorites_post(){
        $post_details = array(
            'post_title' =>     __('Sitewide favorite tracks','wpsstm'),
            'post_status' =>    'publish',
            'post_author' =>    get_current_user_id(),//TOUFIX SHOULD BE SPIFFBOT ? is he available ?
            'post_type' =>      wpsstm()->post_type_playlist
        );
        $page_id = wp_insert_post( $post_details );
        if ( is_wp_error($page_id) ) return $page_id;
        
        self::debug_log($page_id,'created global favorites post');
        
        return $this->update_option( 'sitewide_favorites_id', $page_id );
    }
    
    function setup_subtracks_table(){
        global $wpdb;

        $subtracks_table = $wpdb->prefix . $this->subtracks_table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $subtracks_table (
            subtrack_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            track_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
            tracklist_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
            from_tracklist bigint(20) UNSIGNED NULL,
            subtrack_author bigint(20) UNSIGNED NULL,
            subtrack_order int(11) NOT NULL DEFAULT '0',
            subtrack_time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (ID)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        return dbDelta( $sql );
    }
    
    /*
    we don't use $wpdb->prepare here because it adds quotes like IN('1,2,3,4'), and we don't want that.
    https://wordpress.stackexchange.com/questions/78659/wpdb-prepare-function-remove-single-quote-for-s-in-sql-statment
    */
    
    private static function batch_delete_unused_music_terms(){
        global $wpdb;

        $unused_terms = array();

        $taxonomies = array(
            WPSSTM_Core_Tracks::$artist_taxonomy,
            WPSSTM_Core_Tracks::$track_taxonomy,
            WPSSTM_Core_Tracks::$album_taxonomy
        );

        foreach ($taxonomies as $taxonomy){

            //update music terms count
            $querystr = $wpdb->prepare( "UPDATE wp_term_taxonomy tt SET count = (SELECT count(p.ID) FROM wp_term_relationships tr LEFT JOIN wp_posts p ON p.ID = tr.object_id WHERE tr.term_taxonomy_id = tt.term_taxonomy_id) WHERE taxonomy='%s'",$taxonomy );
            $result = $wpdb->get_results ( $querystr );

            //get unused term IDs
            $querystr = $wpdb->prepare( "SELECT term_id,taxonomy FROM wp_term_taxonomy WHERE taxonomy='%s' AND count = 0 ",$taxonomy );
            $terms = $wpdb->get_results($querystr);
            $unused_terms = array_merge($unused_terms,$terms);
        }
        
        foreach($unused_terms as $term){
            wp_delete_term( $term->term_id, $term->taxonomy );
        }

    }
    
    private static function batch_delete_orphan_tracks(){
        
        if ( !current_user_can('manage_options') ){
            return new WP_Error('wpsstm_missing_capability',__("You don't have the capability required.",'wpsstm'));
        }

        $trashed = array();
        
        if ( $flushable_ids = WPSSTM_Core_Tracks::get_orphan_track_ids() ){

            foreach( (array)$flushable_ids as $track_id ){
                $success = wp_delete_post($track_id,true);
                if ( $success ) $trashed[] = $track_id;
            }
        }

        WP_SoundSystem::debug_log( json_encode(array('flushable'=>count($flushable_ids),'trashed'=>count($trashed))),"Deleted orphan tracks");

        return $trashed;

    }

    /*
    Before DB 205, we were sometimes storing track artist+title+album in the subtracks table to avoid creating a track post each time.
    But that logic is no good, so change that.
    We can delete this upgrade routine after some months.
    */
    function migrate_old_subtracks(){
        global $wpdb;
        
        $subtracks_table = $wpdb->prefix . $this->subtracks_table_name;
        $querystr = $wpdb->prepare( "SELECT * FROM `$subtracks_table` WHERE track_id = %s",'0' );
        $rows = $wpdb->get_results($querystr);

        foreach((array)$rows as $row){
            
            $track = new WPSSTM_Track();
            $track->subtrack_id = $row->ID;
            $track->artist = $row->artist;
            $track->title = $row->title;
            $track->album = $row->album;
            
            $valid = $track->validate_track();
            
            if ( is_wp_error( $valid ) ){
                
                $rowquerystr = $wpdb->prepare( "DELETE FROM `$subtracks_table` WHERE subtrack_id = '%s'",$row->ID );
                $result = $wpdb->get_results ( $rowquerystr );
                
            }else{
                
                $track_id = $track->insert_bot_track();
                
                if ( !is_wp_error($track_id) ){
                    
                    $rowquerystr = $wpdb->prepare( "UPDATE `$subtracks_table` SET track_id = '%s' WHERE subtrack_id = '%s'",$track_id, $row->ID );
                    $result = $wpdb->get_results ( $rowquerystr );

                }

            }

        }
        
        //now that the tracks are fixed, alter table
        $wpdb->query("ALTER TABLE `$subtracks_table` DROP artist");
        $wpdb->query("ALTER TABLE `$subtracks_table` DROP title");
        $wpdb->query("ALTER TABLE `$subtracks_table` DROP album");

    }

    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
    
    function update_option($key, $value){
        $db_option = get_option( $this->meta_name_options);
        $db_option[$key] = $value;
        return update_option( $this->meta_name_options, $db_option );
    }

    function register_scripts_styles(){

        //TO FIX conditional / move code ?
        
        //JSON VIEWER
        wp_register_script('jquery.json-viewer', $this->plugin_url . '_inc/js/jquery.json-viewer/jquery.json-viewer.js',array('jquery')); //TOFIX version
        wp_register_style('jquery.json-viewer', $this->plugin_url . '_inc/js/jquery.json-viewer/jquery.json-viewer.css',null); //TOFIX version
        
        //CSS
        wp_register_style( 'font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',false,'4.7.0');
        wp_register_style( 'wpsstm', wpsstm()->plugin_url . '_inc/css/wpsstm.css',array('font-awesome','jquery.json-viewer'),wpsstm()->version );

        //JS
        wp_register_script( 'wpsstm-functions', $this->plugin_url . '_inc/js/wpsstm-functions.js', array('jquery'),$this->version, true);

        wp_register_script( 'wpsstm', $this->plugin_url . '_inc/js/wpsstm.js', array('jquery','wpsstm-functions','wpsstm-tracklists','jquery-ui-autocomplete','jquery-ui-dialog','jquery-ui-sortable','jquery.json-viewer'),$this->version, true);

        $datas = array(
            'debug'                 => (WP_DEBUG),
            'ajaxurl'               => admin_url( 'admin-ajax.php' ),
            'ajax_tracks'           => wpsstm()->get_options('ajax_tracks'),
            'ajax_links'            => wpsstm()->get_options('ajax_links'),
        );

        wp_localize_script( 'wpsstm-functions', 'wpsstmL10n', $datas );
        
        //JS
        wp_enqueue_script( 'wpsstm' );
        
        //CSS
        wp_enqueue_style( 'wpsstm' );
 
    }

    /*
    Checks that we are on one of backend pages of the plugin
    */
    
    function is_admin_page(){
        
        if ( !wpsstm_is_backend() ) return;

        $screen = get_current_screen();
        $post_type = $screen->post_type;
        $allowed_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_album,
            wpsstm()->post_type_track,
            wpsstm()->post_type_track_link,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_radio,
        );

        $is_allowed_post_type =  ( in_array($post_type,$allowed_post_types) );

        return ( $is_allowed_post_type || self::is_settings_page() );
    }
    
    static function is_settings_page(){
        if ( !wpsstm_is_backend() ) return;
        if ( !$screen = get_current_screen() ) return;
        
        return ($screen->id == 'toplevel_page_wpsstm');
    }
    
    function promo_notice(){
        
        if ( !$this->is_admin_page() ) return;

        $rate_link_wp = 'https://wordpress.org/support/view/plugin-reviews/wp-soundsystem?rate#postform';
        $rate_link = '<a href="'.$rate_link_wp.'" target="_blank" href=""><i class="fa fa-star"></i> '.__('Reviewing the plugin','wpsstm').'</a>';
        $donate_link = '<a href="http://bit.ly/gbreant" target="_blank" href=""><i class="fa fa-usd"></i> '.__('make a donation','wpsstm').'</a>';
        ?>
        <div id="wpsstm-promo-notice">
            <p>
                <?php printf(__('Happy with WP SoundSystem ? %s and %s would help!','pinim'),$rate_link,$donate_link);?>
            </p>
        </div>
        <?php

    }

    public static function debug_log($data,$title = null) {

        if (WP_DEBUG_LOG !== true) return false;

        $prefix = '[wpsstm] ';
        if($title) $prefix.=$title.': ';

        if (is_array($data) || is_object($data)) {
            $data = "\n" . json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        error_log($prefix . $data);
    }
    
    function register_imported_view($views){
        
        if ( !$bot_id = $this->get_options('bot_user_id') ) return $views;
        
        $screen = get_current_screen();
        $post_type = $screen->post_type;

        $link = add_query_arg( array('post_type'=>$post_type,'author'=>$bot_id),admin_url('edit.php') );
        
        $attr = array(
            'href' =>   $link,
        );
        
        $author_id = isset($_REQUEST['author']) ? $_REQUEST['author'] : null;
        
        if ($author_id==$bot_id){
            $attr['class'] = 'current';
        }
        
        $count = count_user_posts( $bot_id , $post_type  );

        $views['imported'] = sprintf('<a %s>%s <span class="count">(%d)</span></a>',wpsstm_get_html_attr($attr),__('Imported','wpsstm'),$count);
        
        return $views;
    }
    
    /*
    List of capabilities and which roles should get them
    */

    function get_roles_capabilities($role_slug){

        //array('subscriber','contributor','author','editor','administrator'),
        
        $all = array(
            
            //radios
            'manage_radios'     => array('editor','administrator'),
            'edit_radios'       => array('contributor','author','editor','administrator'),
            'create_radios'     => array('contributor','author','editor','administrator'),
            
            //playlists
            'manage_playlists'     => array('editor','administrator'),
            'edit_playlists'       => array('contributor','author','editor','administrator'),
            'create_playlists'     => array('contributor','author','editor','administrator'),
            
            //tracks
            'manage_tracks'     => array('editor','administrator'),
            'edit_tracks'       => array('contributor','author','editor','administrator'),
            'create_tracks'     => array('contributor','author','editor','administrator'),
            
            //tracks & tracks links
            'manage_tracks'     => array('editor','administrator'),
            'edit_tracks'       => array('contributor','author','editor','administrator'),
            'create_tracks'     => array('contributor','author','editor','administrator'),
            
            //artists
            'manage_artists'     => array('editor','administrator'),
            'edit_artists'       => array('contributor','author','editor','administrator'),
            'create_artists'     => array('contributor','author','editor','administrator'),
            
            //albums
            'manage_albums'     => array('editor','administrator'),
            'edit_albums'       => array('contributor','author','editor','administrator'),
            'create_albums'     => array('contributor','author','editor','administrator'),
            
        );
        
        $role_caps = array();
        
        foreach ((array)$all as $cap=>$allowed_roles){
            if ( !in_array($role_slug,$allowed_roles) ) continue;
            $role_caps[] = $cap;
        }
        
        return $role_caps;
        
    }
    
    /*
    https://wordpress.stackexchange.com/questions/35165/how-do-i-create-a-custom-role-capability
    */
    
    function add_custom_capabilites(){

        $roles = get_editable_roles();
        foreach ($GLOBALS['wp_roles']->role_objects as $role_slug => $role) {
            if ( !isset($roles[$role_slug]) ) continue;
            $custom_caps = $this->get_roles_capabilities($role_slug);
            
            foreach($custom_caps as $caps){
                $role->add_cap($caps);
            }
            
        }

    }
    
    function remove_custom_capabilities(){
        
        $roles = get_editable_roles();
        foreach ($GLOBALS['wp_roles']->role_objects as $role_slug => $role) {
            if ( !isset($roles[$role_slug]) ) continue;
            $custom_caps = $this->get_roles_capabilities($role_slug);
            
            foreach($custom_caps as $caps){
                $role->remove_cap($caps);
            }
            
        }
        
    }
    
    static function get_notices_output($notices){
        
        $output = array();

        foreach ((array)$notices as $notice){

            $notice_classes = array(
                'inline',
                'settings-error',
                'wpsstm-notice',
                'is-dismissible'
            );
            
            //$notice_classes[] = ($notice['error'] == true) ? 'error' : 'updated';
            
            $notice_attr_arr = array(
                'id'    => sprintf('wpsstm-notice-%s',$notice['code']),
                'class' => implode(' ',$notice_classes),
            );

            $output[] = sprintf('<li %s><strong>%s</strong></li>',wpsstm_get_html_attr($notice_attr_arr),$notice['message']);
        }
        
        return implode("\n",$output);
    }

    public function is_bot_ready(){
        
        //bot
        $bot_id = $this->get_options('bot_user_id');
        
        if ( !$bot_id ){
            return new WP_Error( 'wpsstm_missing_bot', __("Missing bot user.",'wpsstm'));
        }
        
        if ( !$userdatas = get_userdata($bot_id) ) {
            return new WP_Error( 'wpsstm_invalid_bot', __("Invalid bot user.",'wpsstm'));
        }
        
        //check can create radios
        if ( !user_can($bot_id,'create_radios') ){
            return new WP_Error( 'wpsstm_missing_capability', __("The bot user requires the 'create_radios' capability.",'wpsstm'));
        }
        
        //check can create tracks
        if ( !user_can($bot_id,'create_tracks') ){
            return new WP_Error( 'wpsstm_missing_capability', __("The bot user requires the 'create_tracks' capability.",'wpsstm') );
        }
        
        //check can create track links
        //commented since it is the same capability than for tracks.
        /*
        if ( !user_can($bot_id,'create_tracks') ){
            return new WP_Error( 'wpsstm_missing_capability', __("The bot user requires the 'create_tracks' capability.",'wpsstm'));
        }
        */
        
        return true;
    }
    
    /*
    Get the list of services that could get music details
    */
    public function get_available_detail_engines(){
        return apply_filters('wpsstm_get_music_detail_engines',array());
    }
    
    public static function local_rest_request($endpoint = null, $namespace = null, $method = 'GET'){
        
        if (!$namespace) $namespace = WPSSTM_REST_NAMESPACE; 

        if (!$endpoint){
            return new WP_Error('wpsstm_no_rest_endpoint',__("Missing REST endpoint",'wpsstm'));
        }

        $rest_url = sprintf('/%s/%s',$namespace,$endpoint);
        
        self::debug_log(array('url'=>$rest_url,'method'=>$method),'local REST query...');

        //Create request
        $request = new WP_REST_Request( $method, $rest_url );

        //Get response
        $response = rest_do_request( $request );
        
        if ( $response->is_error() ) {
            
            $error = $response->as_error();
            $error_message = $error->get_error_message();
            
            self::debug_log($error_message,'local REST query error');

            return $error;
            
        }
        
        //Get datas
        $datas = $response->get_data();

        return $datas;

    }
    
    public static function format_rest_response($response){

        if ( is_wp_error($response) ){
            //force error status if not set
            $code = $response->get_error_code();
            $data = $response->get_error_data($code);
            $status = wpsstm_get_array_value('status',$data);
            if (!$status){
                $response->add_data(array('status'=>404, $code));
            }
            
        }
        
        $response = rest_ensure_response( $response );
        return $response;
    }
    
    function populate_data_engines(){
        $enabled_engine_slugs = $this->get_options('details_engines');
        $available_engines = $this->get_available_detail_engines();

        foreach((array)$available_engines as $engine){
            if ( !in_array($engine->slug,$enabled_engine_slugs) ) continue;
            $engine->setup_actions();
            $this->engines[] = $engine;
        }
    }

}

function wpsstm() {
	return WP_SoundSystem::instance();
}

wpsstm();
