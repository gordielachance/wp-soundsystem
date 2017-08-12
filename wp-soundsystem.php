<?php
/*
Plugin Name: WP SoundSystem
Description: Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  The perfect fit for your music blog !
Plugin URI: https://github.com/gordielachance/wp-soundsystem
Author: G.Breant
Author URI: https://profiles.wordpress.org/grosbouff/#content-plugins
Version: 1.6.9
License: GPL2
*/

class WP_SoundSystem {
    /** Version ***************************************************************/
    /**
    * @public string plugin version
    */
    public $version = '1.6.9';
    /**
    * @public string plugin DB version
    */
    public $db_version = '151';
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
            'mb_suggest_bookmarks'              => 'on',
            'live_playlists_enabled'            => 'on',
            'frontend_scraper_page_id'          => null,
            'visitors_wizard'                   => 'on',
            'community_user_id'                 => null,
            'live_playlists_cache_min'          => 15,
            'cache_api_results'                 => 1, //days a musicbrainz query (for an url) is cached
            'lastfm_client_id'                  => null,
            'lastfm_client_secret'              => null,
            'lastfm_scrobbling'                 => 'on',
            'lastfm_favorites'                  => 'on',
            'lastfm_community_scrobble'         => 'off',
            'spotify_client_id'                 => null,
            'spotify_client_secret'             => null,
            'soundcloud_client_id'              => null,
            'soundcloud_client_secret'          => null,
            'player_enabled'                    => 'on',
            'autoplay'                          => 'on',
            'autosource'                        => 'on',
            'toggle_tracklist'                  => 3, //shorten tracklist to X visible tracks
            'hide_empty_columns'                => 'on', //hide a tracklist column when it has a unique value for every row
            'autosource_filter_ban_words'       => array('cover'),
            'autosource_filter_requires_artist' => 'off'
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
        
        require $this->plugin_dir . 'wpsstm-templates.php';
        require $this->plugin_dir . 'wpsstm-functions.php';
        require $this->plugin_dir . 'wpsstm-settings.php';
        require $this->plugin_dir . 'wpsstm-core-artists.php';
        require $this->plugin_dir . 'wpsstm-core-tracks.php';
        require $this->plugin_dir . 'wpsstm-core-sources.php';
        require $this->plugin_dir . 'wpsstm-core-tracklists.php';
        require $this->plugin_dir . 'wpsstm-core-albums.php';
        require $this->plugin_dir . 'wpsstm-core-playlists.php';
        require $this->plugin_dir . 'classes/wpsstm-lastfm-user.php';
        require $this->plugin_dir . 'wpsstm-core-lastfm.php';
        //require $this->plugin_dir . 'wpsstm-core-buddypress.php';

        if ( wpsstm()->get_options('musicbrainz_enabled') == 'on' ){
            require $this->plugin_dir . 'wpsstm-core-musicbrainz.php';
        }
        
        
        require $this->plugin_dir . 'wpsstm-core-playlists-live.php';
        
        if ( wpsstm()->get_options('player_enabled') == 'on' ){
            require $this->plugin_dir . 'wpsstm-core-player.php';
        }
        
        if ( class_exists( 'Post_Bookmarks' ) && ( wpsstm()->get_options('mb_suggest_bookmarks') == 'on' ) ) {
            require wpsstm()->plugin_dir . 'wpsstm-post_bkmarks.php';
        }

        do_action('wpsstm_loaded');
        
        
    }
    function setup_actions(){

        add_action( 'plugins_loaded', array($this, 'upgrade'));

        //roles & capabilities
        register_activation_hook( $this->file, array( $this, 'add_custom_capabilites' ) );
        register_deactivation_hook( $this->file, array( $this, 'remove_custom_capabilities' ) );

        add_action( 'admin_init', array($this,'load_textdomain'));

        add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts_styles_shared' ), 9 );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_styles_shared' ), 9 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles_backend' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_styles_frontend' ) );

        add_action('edit_form_after_title', array($this,'metabox_reorder'));
        
        add_action( 'all_admin_notices', array($this, 'promo_notice'), 5 );

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

            /*
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
            */

        }else{
            
            if($current_version < 151){ //switch post type to 'pin'
                
                //rename old source URL metakeys
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'", wpsstm_sources()->source_url_metakey, '_wpsstm_source' );

                $result = $wpdb->get_results ( $querystr );
                
            }

        }
        
        //update DB version
        update_option("_wpsstm-db_version", $this->db_version );
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

        //JS
        wp_register_script( 'jquery.toggleChildren', $this->plugin_url . '_inc/js/jquery.toggleChildren.js', array('jquery'),'1.36');
        
        //js
        wp_register_script( 'wpsstm-shared', $this->plugin_url . '_inc/js/wpsstm.js', array('jquery','wpsstm-tracklists'),$this->version);
        
        $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
        $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
        $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
        $wp_auth_notice = $wp_auth_icon.' '.$wp_auth_text;

        $datas = array(
            'debug'             => (WP_DEBUG),
            'ajaxurl'           => admin_url( 'admin-ajax.php' ),
            'logged_user_id'    => get_current_user_id(),
            'wp_auth_notice'    => $wp_auth_notice
        );

        wp_localize_script( 'wpsstm-shared', 'wpsstmL10n', $datas );
        
    }

    function enqueue_scripts_styles_backend( $hook ){

            if ( !$this->is_admin_page() ) return;

            // css
            wp_register_style( 'wpsstm-admin',  $this->plugin_url . '_inc/css/wpsstm-backend.css',array('font-awesome','wpsstm-tracklists'),$this->version );
            wp_enqueue_style( 'wpsstm-admin' );

            
            
        //}
        
    }
    
    function enqueue_scripts_styles_frontend(){
        
        //TO FIX TO CHECK embed only for music post types ?
        wp_enqueue_script( 'wpsstm-shared' );
        
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

    public function debug_log($message,$title = null) {

        if (WP_DEBUG_LOG !== true) return false;

        $prefix = '[wpsstm] ';
        if($title) $prefix.=$title.': ';

        if (is_array($message) || is_object($message)) {
            error_log($prefix.print_r($message, true));
        } else {
            error_log($prefix.$message);
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

}

function wpsstm() {
	return WP_SoundSystem::instance();
}

wpsstm();