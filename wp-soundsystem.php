<?php
/*
Plugin Name: WP SoundSystem
Description: Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  The perfect fit for your music blog !
Plugin URI: https://github.com/gordielachance/wp-soundsystem
Author: G.Breant
Author URI: https://profiles.wordpress.org/grosbouff/#content-plugins
Version: 1.9.4
License: GPL2
*/

class WP_SoundSystem {
    /** Version ***************************************************************/
    /**
    * @public string plugin version
    */
    public $version = '1.9.4';
    /**
    * @public string plugin DB version
    */
    public $db_version = '156';
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

    public $post_type_album = 'wpsstm_release';
    public $post_type_artist = 'wpsstm_artist';
    public $post_type_track = 'wpsstm_track';
    public $post_type_source = 'wpsstm_source';
    public $post_type_playlist = 'wpsstm_playlist';
    public $post_type_live_playlist = 'wpsstm_live_playlist';
    public $tracklist_post_types = array('wpsstm_release','wpsstm_playlist','wpsstm_live_playlist');
    public $static_tracklist_post_types = array('wpsstm_release','wpsstm_playlist');
    
    public $qvar_wpsstm_statii = 'wpsstm_statii';
    public $qvar_popup = 'wpsstm-popup';
    
    public $subtracks_table_name = 'wpsstm_subtracks';

    /**
    * @var The one true Instance
    */
    private static $instance;

    public $meta_name_options = 'wpsstm_options';
    
    var $menu_page;

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
        * A dummy constructor to prevent bbPress from being loaded more than once.
        *
        * @since bbPress (r2464)
        * @see bbPress::instance()
        * @see bbpress();
        */
    private function __construct() { /* Do nothing here */ }
    
    function setup_globals() {
        
        /** Paths *************************************************************/
        $this->file       = __FILE__;
        $this->basename   = plugin_basename( $this->file );
        $this->plugin_dir = plugin_dir_path( $this->file );
        $this->plugin_url = plugin_dir_url ( $this->file );
        $this->options_default = array(
            'musicbrainz_enabled'               => 'on',
            'mb_auto_id'                        => 'on',
            'frontend_scraper_page_id'          => null,
            'visitors_wizard'                   => 'on',
            'recent_wizard_entries'             => get_option( 'posts_per_page' ),
            'community_user_id'                 => null,
            'cache_api_results'                 => 1, //days a musicbrainz query (for an url) is cached
            'lastfm_client_id'                  => null,
            'lastfm_client_secret'              => null,
            'lastfm_scrobbling'                 => 'on',
            'lastfm_favorites'                  => 'on',
            'lastfm_community_scrobble'         => 'off',
            'youtube_api_key'                   => null,
            'spotify_client_id'                 => null,
            'spotify_client_secret'             => null,
            'tuneefy_client_id'                 => null,
            'tuneefy_client_secret'             => null,
            'soundcloud_client_id'              => null,
            'soundcloud_client_secret'          => null,
            'player_enabled'                    => 'on',
            'autoplay'                          => 'on',
            'autosource'                        => 'on',
            'toggle_tracklist'                  => 3, //shorten tracklist to X visible tracks
            'autosource_filter_ban_words'       => array('cover'),
            'playable_opacity_class'            => 'on',
            'minimal_css'                       => 'off',
        );
        
        $this->options = wp_parse_args(get_option( $this->meta_name_options), $this->options_default);
        
        
        //validate options
        /* TO FIX NOT WORKING HERE because of get_userdata() that should be fired after 'plugins_loaded'
        https://wordpress.stackexchange.com/a/126206/70449
        
        if ( $this->options['frontend_scraper_page_id'] && !is_string( get_post_status( $this->options['frontend_scraper_page_id'] ) ) ) $this->options['community_user_id'] = null;
        if ( $this->options['community_user_id'] && !get_userdata( $this->options['community_user_id'] ) ) $this->options['community_user_id'] = null;
        if ( ( $this->options['lastfm_community_scrobble'] == 'on' ) && !get_userdata( $this->options['lastfm_community_scrobble'] ) ) $this->options['lastfm_community_scrobble'] = 'off';
        */
    }
    
    function includes(){
        
        require_once(wpsstm()->plugin_dir . '_inc/php/autoload.php'); // PHP dependencies (last.fm, scraper, etc.)
        
        require $this->plugin_dir . 'classes/wpsstm-track-class.php';
        require $this->plugin_dir . 'classes/wpsstm-tracklist-class.php';
        require $this->plugin_dir . 'classes/wpsstm-source-class.php';
        
        require $this->plugin_dir . 'classes/services/default.php';
        require $this->plugin_dir . 'classes/services/soundcloud.php';
        require $this->plugin_dir . 'classes/services/youtube.php';
        
        require $this->plugin_dir . 'wpsstm-templates.php';
        require $this->plugin_dir . 'wpsstm-functions.php';
        require $this->plugin_dir . 'wpsstm-settings.php';
        require $this->plugin_dir . 'wpsstm-core-artists.php';
        require $this->plugin_dir . 'wpsstm-core-tracks.php';
        require $this->plugin_dir . 'wpsstm-core-sources.php';
        require $this->plugin_dir . 'wpsstm-core-autosource.php';
        require $this->plugin_dir . 'wpsstm-core-tracklists.php';
        require $this->plugin_dir . 'wpsstm-core-albums.php';
        require $this->plugin_dir . 'wpsstm-core-playlists.php';
        require $this->plugin_dir . 'classes/wpsstm-lastfm-user.php';
        require $this->plugin_dir . 'wpsstm-core-lastfm.php';
        require $this->plugin_dir . 'wpsstm-core-buddypress.php';

        if ( wpsstm()->get_options('musicbrainz_enabled') == 'on' ){
            require $this->plugin_dir . 'wpsstm-core-musicbrainz.php';
        }
        
        
        require $this->plugin_dir . 'wpsstm-core-playlists-live.php';
        
        if ( wpsstm()->get_options('player_enabled') == 'on' ){
            require $this->plugin_dir . 'wpsstm-core-player.php';
        }
    }
    function setup_actions(){
        
        /* Now that files have been loaded, init all core classes */
        new WPSSTM_Core_Albums();
        new WPSSTM_Core_Artists();
        new WPSSTM_Core_BuddyPress();
        new WPSSTM_Core_LastFM();
        new WPSSTM_Core_MusicBrainz();
        new WPSSTM_Core_Player();
        new WPSSTM_Core_Live_Playlists();
        new WPSSTM_Core_Playlists();
        new WPSSTM_Core_Sources();
        new WPSSTM_Core_Autosource();
        new WPSSTM_Core_Tracklists();
        new WPSSTM_Core_Tracks();
        new WPSSTM_Core_Wizard();
        
        ////

        add_action( 'plugins_loaded', array($this, 'upgrade'));

        //roles & capabilities
        register_activation_hook( $this->file, array( $this, 'add_custom_capabilites' ) );
        register_deactivation_hook( $this->file, array( $this, 'remove_custom_capabilities' ) );

        add_action( 'admin_init', array($this,'load_textdomain'));

        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts_styles_shared' ), 9 );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_styles_shared' ), 9 );

        add_action('edit_form_after_title', array($this,'metabox_reorder'));
        
        add_action( 'all_admin_notices', array($this, 'promo_notice'), 5 );
        
        add_filter( 'body_class', array($this,'default_style_class'));
        
        add_filter( 'query_vars', array($this,'add_wpsstm_query_vars'));
        
        add_filter( 'template_include', array($this,'popup_template'));

    }
    
    function add_wpsstm_query_vars($vars){
        $vars[] = $this->qvar_popup;
        $vars[] = $this->qvar_wpsstm_statii;
        return $vars;
    }
    
    function default_style_class($classes){
        if ( wpsstm()->get_options('minimal_css') !== 'on'){
            $classes[] = 'wpsstm-default';
        }
        return $classes;
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

    function upgrade(){
        global $wpdb;

        $current_version = get_option("_wpsstm-db_version");

        if ($current_version==$this->db_version) return false;
        if(!$current_version){ //not installed

            $this->setup_subtracks_table();

        }else{
            
            if($current_version < 151){ //rename old source URL metakeys

                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'", WPSSTM_Core_Sources::$source_url_metakey, '_wpsstm_source' );

                $result = $wpdb->get_results ( $querystr );
                
            }
            
            if($current_version < 154){ //delete artist/album/track post title (we don't use them anymore)

                $querystr = $wpdb->prepare( "UPDATE $wpdb->posts SET post_title = '' WHERE post_type = '%s' OR post_type = '%s' OR post_type = '%s' ", $this->post_type_album,$this->post_type_artist,$this->post_type_track );

                $result = $wpdb->get_results ( $querystr );
                
            }

            if ($current_version < 155){
                $this->setup_subtracks_table();
                $this->migrate_subtracks();
            }
            
            if ($current_version < 156){
                
                //delete source provider slug metakey, now computed dynamically
                $querystr = $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = '%s'", '_wpsstm_source_provider' );
                $result = $wpdb->get_results ( $querystr );
                
                //delete source stream URL metakey, now computed dynamically
                $querystr = $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_key = '%s'", '_wpsstm_source_stream' );
                $result = $wpdb->get_results ( $querystr );
                
            }

        }
        
        //update DB version
        update_option("_wpsstm-db_version", $this->db_version );
    }
    
    function setup_subtracks_table(){
        global $wpdb;

        $subtracks_table_name = $wpdb->prefix . $this->subtracks_table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $subtracks_table_name (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            track_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
            tracklist_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
            track_order int(11) NOT NULL DEFAULT '0',
            PRIMARY KEY  (ID)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        return dbDelta( $sql );
    }
    
    /*
    Migrate old subtracks (stored in tracklist posts metas) to the new subtracks table
    Can be removed after a few months once the plugin has been updated.
    */
    function migrate_subtracks(){
        global $wpdb;
        
        $subtracks_table_name = $wpdb->prefix . $this->subtracks_table_name;
        
        //get all subtracks metas
        $querystr = $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key = '%s' OR meta_key = '%s'", 'wpsstm_subtrack_ids','wpsstm_live_subtrack_ids' );
        $metas = $wpdb->get_results ( $querystr );

        foreach($metas as $meta){
            $subtrack_ids = maybe_unserialize( $meta->meta_value );
            $subtrack_pos = 0;
            foreach((array)$subtrack_ids as $subtrack_id){
                $wpdb->insert($subtracks_table_name, array(
                    'track_id' =>       $subtrack_id,
                    'tracklist_id' =>   $meta->post_id,
                    'track_order' =>    $subtrack_pos
                ));
                
                //delete subtracks metas
                $querystr = $wpdb->prepare( "DELETE FROM $wpdb->postmeta WHERE meta_id = '%s'", $meta->meta_id );
                $wpdb->get_results ( $querystr );
                
                $subtrack_pos++;
            }
        }
    }
    
    
    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
    
    public function get_default_option($keys = null){
        return wpsstm_get_array_value($keys,$this->options_default);
    }

    function register_scripts_styles_shared(){

        //TO FIX conditional / move code ?
        
        //CSS
        wp_register_style( 'font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',false,'4.7.0');
        wp_register_style( 'wpsstm', wpsstm()->plugin_url . '_inc/css/wpsstm.css',array('font-awesome','wp-mediaelement'),wpsstm()->version );

        //JS
        wp_register_script( 'jquery.toggleChildren', $this->plugin_url . '_inc/js/jquery.toggleChildren.js', array('jquery'),'1.36', true);
        
        //js
        wp_register_script( 'wpsstm', $this->plugin_url . '_inc/js/wpsstm.js', array('jquery','jquery-ui-autocomplete','jquery-ui-dialog','jquery-ui-sortable','wp-mediaelement','wpsstm-tracklists'),$this->version, true);

        $datas = array(
            'debug'             => (WP_DEBUG),
            'ajaxurl'           => admin_url( 'admin-ajax.php' ),
        );

        wp_localize_script( 'wpsstm', 'wpsstmL10n', $datas );
        
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
            wpsstm()->post_type_source,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist,
        );

        $is_allowed_post_type =  ( in_array($post_type,$allowed_post_types) );
        $is_top_menu = ($screen->id == 'toplevel_page_wpsstm');

        if (!$is_allowed_post_type && !$is_top_menu) return;
        
        return true;
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

    public function debug_log($message,$title = null, $file = null) {

        if (WP_DEBUG_LOG !== true) return false;

        $prefix = '[wpsstm] ';
        if($title) $prefix.=$title.': ';
        
        $output = null;

        if (is_array($message) || is_object($message)) {
            $output = $prefix . implode("\n", $message);
        } else {
            $output = $prefix . $message;
        }
        
        if ($output){
            error_log($output);
        }
    }
    
    function register_community_view($views){
        
        if ( !$user_id = wpsstm()->get_options('community_user_id') ) return $views;
        
        $screen = get_current_screen();
        $post_type = $screen->post_type;

        $link = add_query_arg( array('post_type'=>$post_type,'author'=>$user_id),admin_url('edit.php') );
        
        $attr = array(
            'href' =>   $link,
        );
        
        $author_id = isset($_REQUEST['author']) ? $_REQUEST['author'] : null;
        
        if ($author_id==$user_id){
            $attr['class'] = 'current';
        }
        
        $count = count_user_posts( $user_id , $post_type  );

        $views['community'] = sprintf('<a %s>%s <span class="count">(%d)</span></a>',wpsstm_get_html_attr($attr),__('Community','wpsstm'),$count);
        
        return $views;
    }
    
    /*
    List of capabilities and which roles should get them
    */

    function get_roles_capabilities($role_slug){

        //array('subscriber','contributor','author','editor','administrator'),
        
        $all = array(
            
            //live playlists
            'manage_live_playlists'     => array('editor','administrator'),
            'edit_live_playlists'       => array('contributor','author','editor','administrator'),
            'create_live_playlists'     => array('contributor','author','editor','administrator'),
            
            //playlists
            'manage_playlists'     => array('editor','administrator'),
            'edit_playlists'       => array('contributor','author','editor','administrator'),
            'create_playlists'     => array('contributor','author','editor','administrator'),
            
            //tracks
            'manage_tracks'     => array('editor','administrator'),
            'edit_tracks'       => array('contributor','author','editor','administrator'),
            'create_tracks'     => array('contributor','author','editor','administrator'),
            
            //tracks & tracks sources
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
    
    //loads the popup template if 'wpsstm-popup' is defined
    function popup_template($template){
        $is_popup = get_query_var( $this->qvar_popup );
        if ( $is_popup ){
            $template = wpsstm_locate_template( 'popup.php' );
            add_filter('wpsstm_track_actions',array($this,'popup_template_action_links'));
        }

        return $template;
    }
    
    //if the popup template is loaded, append 'wpsstm-popup=true' to the action URLs
    function popup_template_action_links($actions){
        foreach((array)$actions as $key=>$action){
            if( isset($action['href']) ){
                $actions[$key]['href'] = add_query_arg(array($this->qvar_popup=>true),$action['href']);
            }
        }
        return $actions;
    }

}

function wpsstm() {
	return WP_SoundSystem::instance();
}

wpsstm();