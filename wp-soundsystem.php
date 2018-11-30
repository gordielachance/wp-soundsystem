<?php
/*
Plugin Name: WP SoundSystem
Description: Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  The perfect fit for your music blog !
Plugin URI: https://github.com/gordielachance/wp-soundsystem
Author: G.Breant
Author URI: https://profiles.wordpress.org/grosbouff/#content-plugins
Version: 1.9.9
License: GPL2
*/

class WP_SoundSystem {
    /** Version ***************************************************************/
    /**
    * @public string plugin version
    */
    public $version = '1.9.9';
    /**
    * @public string plugin DB version
    */
    public $db_version = '160';
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
    
    public $subtracks_table_name = 'wpsstm_subtracks';

    /**
    * @var The one true Instance
    */
    private static $instance;

    public $meta_name_options = 'wpsstm_options';
    
    var $menu_page;
    var $options = array();

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
        
        $options_default = array(
            'frontend_scraper_page_id'          => null,
            'visitors_wizard'                   => 'on',
            'recent_wizard_entries'             => get_option( 'posts_per_page' ),
            'community_user_id'                 => null,
            'cache_api_results'                 => 1, //days a musicbrainz query (for an url) is cached
            'player_enabled'                    => 'on',
            'autoplay'                          => 'on',
            'autosource'                        => 'on',
            'limit_autosources'                 => 5,
            'toggle_tracklist'                  => 3, //shorten tracklist to X visible tracks
        );
        
        $this->options = wp_parse_args(get_option( $this->meta_name_options),$options_default);
        
        
        //validate options
        /* TO FIX NOT WORKING HERE because of get_userdata() that should be fired after 'plugins_loaded'
        https://wordpress.stackexchange.com/a/126206/70449
        
        if ( $this->options['frontend_scraper_page_id'] && !is_string( get_post_status( $this->options['frontend_scraper_page_id'] ) ) ) $this->options['community_user_id'] = null;
        if ( $this->options['community_user_id'] && !get_userdata( $this->options['community_user_id'] ) ) $this->options['community_user_id'] = null;
        if ( ( $this->options['scrobble_along'] == 'on' ) && !get_userdata( $this->options['scrobble_along'] ) ) $this->options['scrobble_along'] = 'off';
        */
    }
    
    function includes(){
        
        require_once(wpsstm()->plugin_dir . '_inc/php/autoload.php'); // PHP dependencies (last.fm, scraper, etc.)
        
        require $this->plugin_dir . 'wpsstm-templates.php';
        require $this->plugin_dir . 'wpsstm-functions.php';
        require $this->plugin_dir . 'wpsstm-settings.php';
        require $this->plugin_dir . 'wpsstm-core-artists.php';
        require $this->plugin_dir . 'wpsstm-core-tracks.php';
        require $this->plugin_dir . 'wpsstm-core-sources.php';
        require $this->plugin_dir . 'wpsstm-core-tracklists.php';
        require $this->plugin_dir . 'wpsstm-core-albums.php';
        require $this->plugin_dir . 'wpsstm-core-playlists.php';
        require $this->plugin_dir . 'wpsstm-core-buddypress.php';        
        require $this->plugin_dir . 'wpsstm-core-playlists-live.php';
        
        require $this->plugin_dir . 'classes/wpsstm-track-class.php';
        require $this->plugin_dir . 'classes/wpsstm-tracklist-class.php';
        require $this->plugin_dir . 'classes/wpsstm-source-class.php';
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
    }
    
    function setup_actions(){
        
        do_action('wpsstm_init');
        
        /* Now that files have been loaded, init all core classes */
        //TOUFIX should be better to hook this on a wpsstm_init action
        new WPSSTM_Core_Albums();
        new WPSSTM_Core_Artists();
        new WPSSTM_Core_BuddyPress();
        new WPSSTM_Core_Live_Playlists();
        new WPSSTM_Core_Playlists();
        new WPSSTM_Core_Sources();
        new WPSSTM_Core_Tracklists();
        new WPSSTM_Core_Tracks();
        new WPSSTM_Core_Wizard();
        new WPSSTM_Player();

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
        
        add_filter( 'query_vars', array($this,'add_wpsstm_query_vars'));
    }
    
    function add_wpsstm_query_vars($vars){
        $vars[] = $this->qvar_wpsstm_statii;
        return $vars;
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
            
            //tracks seconds > milliseconds
            if ($current_version < 157){
                $querystr = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_key = '%s', meta_value = meta_value * 1000 WHERE meta_key = '%s'", WPSSTM_Core_Tracks::$length_metakey, '_wpsstm_length' );

                $result = $wpdb->get_results ( $querystr );
            }
            
            if ($current_version < 158){
                //update subtracks table
                $subtracks_table = $wpdb->prefix . $this->subtracks_table_name;
                $wpdb->query("ALTER TABLE $subtracks_table ADD artist longtext NOT NULL");
                $wpdb->query("ALTER TABLE $subtracks_table ADD title longtext NOT NULL");
                $wpdb->query("ALTER TABLE $subtracks_table ADD album longtext");
            }
            if ($current_version < 160){
                $subtracks_table = $wpdb->prefix . $this->subtracks_table_name;
                $wpdb->query("ALTER TABLE $subtracks_table ADD time datetime NOT NULL DEFAULT '0000-00-00 00:00:00'");
                $wpdb->query("ALTER TABLE $subtracks_table ADD from_tracklist bigint(20) UNSIGNED NOT NULL DEFAULT '0'");
            }

        }
        
        //update DB version
        update_option("_wpsstm-db_version", $this->db_version );
    }
    
    function setup_subtracks_table(){
        global $wpdb;

        $subtracks_table = $wpdb->prefix . $this->subtracks_table_name;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $subtracks_table (
            ID bigint(20) NOT NULL AUTO_INCREMENT,
            track_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
            tracklist_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
            from_tracklist bigint(20) UNSIGNED NULL,
            track_order int(11) NOT NULL DEFAULT '0',
            artist longtext NOT NULL,
            title longtext NOT NULL,
            album longtext,
            time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
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
        
        $subtracks_table = $wpdb->prefix . $this->subtracks_table_name;
        
        //get all subtracks metas
        $querystr = $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key = '%s' OR meta_key = '%s'", 'wpsstm_subtrack_ids','wpsstm_live_subtrack_ids' );
        $metas = $wpdb->get_results ( $querystr );

        foreach($metas as $meta){
            $subtrack_ids = maybe_unserialize( $meta->meta_value );
            $subtrack_pos = 0;
            foreach((array)$subtrack_ids as $subtrack_id){
                $wpdb->insert($subtracks_table, array(
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

    function register_scripts_styles_shared(){

        //TO FIX conditional / move code ?
        
        //CSS
        wp_register_style( 'font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',false,'4.7.0');
        wp_register_style( 'wpsstm', wpsstm()->plugin_url . '_inc/css/wpsstm.css',array('font-awesome'),wpsstm()->version );

        //JS
        wp_register_script( 'jquery.toggleChildren', $this->plugin_url . '_inc/js/jquery.toggleChildren.js', array('jquery'),'1.36', true);
        
        //wp_register_script( 'iframeResizerContentWindow', $this->plugin_url . '_inc/js/iframe-resizer/iframeResizer.contentWindow.min.js', null,'13.5.15');//TOUFIX load in iframes only
        //wp_register_script( 'iframeResizer', $this->plugin_url . '_inc/js/iframe-resizer/iframeResizer.min.js', array('iframeResizerContentWindow'),'13.5.15');
        
        //js
        wp_register_script( 'wpsstm', $this->plugin_url . '_inc/js/wpsstm.js', array('jquery','jquery-ui-autocomplete','jquery-ui-dialog','jquery-ui-sortable','wpsstm-tracklists'),$this->version, true);

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

    public function debug_log($data,$title = null, $file = null) {

        if (WP_DEBUG_LOG !== true) return false;

        $prefix = '[wpsstm] ';
        if($title) $prefix.=$title.': ';
        
        $output = null;

        if (is_array($data) || is_object($data)) {
            $data = "\n" . json_encode($data);
        } else {
            $output = $prefix . $data;
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
}

function wpsstm() {
	return WP_SoundSystem::instance();
}

wpsstm();