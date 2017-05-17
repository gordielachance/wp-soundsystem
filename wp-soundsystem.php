<?php
/*
Plugin Name: WP SoundSystem
Description: Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  The perfect fit for your music blog !
Plugin URI: https://github.com/gordielachance/wp-soundsystem
Author: G.Breant
Author URI: https://profiles.wordpress.org/grosbouff/#content-plugins
Version: 0.9.9.4
License: GPL2
*/

class WP_SoundSytem {
    /** Version ***************************************************************/
    /**
    * @public string plugin version
    */
    public $version = '0.9.9.4';
    /**
    * @public string plugin DB version
    */
    public $db_version = '104';
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
                    self::$instance = new WP_SoundSytem;
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
            'live_playlists_enabled'            => 'off',
            'frontend_scraper_page_id'          => null,
            'live_playlists_cache_min'          => '2',
            'cache_api_results'                 => 1, //days a musicbrainz query (for an url) is cached
            'lastfm_client_id'                  => null,
            'lastfm_client_secret'              => null,
            'lastfm_scrobbling'                 => 'on',
            'lastfm_favorites'                  => 'on',
            'spotify_client_id'                 => null,
            'spotify_client_secret'             => null,
            'soundcloud_client_id'              => null,
            'soundcloud_client_secret'          => null,
            'player_enabled'                    => 'on',
            'autoplay'                          => 'on',
            'autoredirect'                      => 0, //seconds
            'autosource'                        => 'on',
            'autosource_cache'                  => 1* WEEK_IN_SECONDS
        );
        
        $this->options = wp_parse_args(get_option( $this->meta_name_options), $this->options_default);
    }
    
    function includes(){
        
        require $this->plugin_dir . 'classes/wpsstm-track-class.php';
        require $this->plugin_dir . 'classes/wpsstm-tracklist-class.php';
        
        require $this->plugin_dir . 'wpsstm-templates.php';
        require $this->plugin_dir . 'wpsstm-functions.php';
        require $this->plugin_dir . 'wpsstm-settings.php';
        require $this->plugin_dir . 'wpsstm-core-artists.php';
        require $this->plugin_dir . 'wpsstm-core-tracks.php';
        require $this->plugin_dir . 'wpsstm-core-sources.php';
        require $this->plugin_dir . 'wpsstm-core-tracklists.php';
        require $this->plugin_dir . 'wpsstm-core-albums.php';
        require $this->plugin_dir . 'wpsstm-core-playlists.php';
        require $this->plugin_dir . 'wpsstm-core-lastfm.php';

        require $this->plugin_dir . 'wpsstm-ajax.php';

        if ( wpsstm()->get_options('musicbrainz_enabled') == 'on' ){
            require $this->plugin_dir . 'wpsstm-core-musicbrainz.php';
        }
        
        if ( wpsstm()->get_options('live_playlists_enabled') == 'on' ){
            require $this->plugin_dir . 'wpsstm-core-playlists-live.php';
        }
        
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

        add_action( 'admin_init', array($this,'load_textdomain'));

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles_admin' ) );
        
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

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

            if ($current_version < 101){
                
                //update sources from plugin Post_Bookmarks
                if ( class_exists('Post_Bookmarks') ){

                    //get tracks
                    $tracks_args = array(
                        'post_type'     => wpsstm()->post_type_track,
                        'post_status'   => 'any',
                        'posts_per_page'    => -1,
                        'fields'            => 'ids'
                    );

                    $query = new WP_Query( $tracks_args );
                    
                    $sources_cat = get_term_by( 'slug', 'wpsstm-sources', 'link_category');
                    $sources_cat_id = $sources_cat->term_id;
                    
                    $bookmarks_args = array(
                        'category' => $sources_cat_id
                    );

                    foreach ($query->posts as $track_id){
                        
                        if ( !$bookmarks = post_bkmarks_get_post_links($track_id, $bookmarks_args) ) continue;
                        
                        $track = new WP_SoundSystem_Track( array('post_id'=>$track_id) );
                        $sources_db = $this->get_track_sources_db($track,false); //without filters

                        foreach ((array)$bookmarks as $bookmark){
                            $sources_db[] = $bookmark->link_url;
                        }

                        wpsstm_sources()->update_post_sources($track_id,$sources_db);
                        
                    }
                }

            }
            
            if ($current_version < 103){
                
                //sources : update from a string -> an array 
                
                $sources_meta_key = wpsstm_sources()->sources_metakey;
                
                $metas_query = $wpdb->get_results( $wpdb->prepare( "
                    SELECT post_id,meta_value FROM {$wpdb->postmeta} pm
                    LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key = '%s'
                ", $sources_meta_key ) );
                
                foreach((array)$metas_query as $post_sources){
                    $post_id = $post_sources->post_id;
                    $meta_value = $post_sources->meta_value;
                    $meta_value = maybe_unserialize( $meta_value );
                    foreach((array)$meta_value as $key=>$post_source){
                        $new_source = wpsstm_sources()->source_blank;
                        $new_source['url'] = $post_source;
                        $new_source = array_filter($new_source);
                        $meta_value[$key] = $new_source;
                    }
                    wpsstm_sources()->update_post_sources($post_id,$meta_value);
                }
            }
            
            if ($current_version < 104){
                
                //rename post metas
                $update_posts = $wpdb->prepare( 
                    "UPDATE `".$wpdb->prefix . "postmeta` SET meta_key = '%s' WHERE meta_key = '%s'",
                    wpsstm_sources()->sources_metakey,
                    '_wpsstm_source_urls'
                );
                $wpdb->query($update_posts);
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

    function enqueue_scripts_styles_admin( $hook ){

            if ( !$this->is_admin_page() ) return;

            // css
            wp_register_style('font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',false,'4.7.0');
            wp_register_style( 'wpsstm_admin',  $this->plugin_url . '_inc/css/wpsstm-admin.css',array('font-awesome'),$this->version );
            // js
            wp_register_script( 'wpsstm-shortenTables', $this->plugin_url . '_inc/js/wpsstm-shortenTables.js', array('jquery'),$this->version);
            wp_register_script( 'wpsstm_admin', $this->plugin_url . '_inc/js/wpsstm-admin.js', array('jquery-core', 'jquery-ui-core', 'jquery-ui-sortable','suggest','wpsstm-shortenTables'),$this->version);

            //localize vars
            $localize_vars=array(
                'ajaxurl'           => admin_url( 'admin-ajax.php' )
            );
        
            wp_localize_script('wpsstm_admin','wpsstmL10n', $localize_vars);

            wp_enqueue_script( 'wpsstm_admin' );
            wp_enqueue_style( 'wpsstm_admin' );
            
            
        //}
        
    }
    
    function enqueue_scripts_styles(){
        
        //TO FIX TO CHECK embed only for music post types ?
        
        wp_register_script( 'wpsstm-shortenTables', $this->plugin_url . '_inc/js/wpsstm-shortenTables.js', array('jquery'),$this->version);
        wp_register_script( 'wpsstm', $this->plugin_url . '_inc/js/wpsstm.js', array('jquery','wpsstm-shortenTables'),$this->version);
        
        $datas = array(
            'debug'             => (WP_DEBUG),
            'ajaxurl'           => admin_url( 'admin-ajax.php' ),
            'logged_user_id'    => get_current_user_id(),
            'clipboardtext'     => __('You can copy and share this link:','wpsstm')
        );
        
        wp_localize_script( 'wpsstm', 'wpsstmL10n', $datas );
        wp_enqueue_script( 'wpsstm' );
        
        wp_register_style('font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css',false,'4.7.0');
        wp_register_style( 'wpsstm',  $this->plugin_url . '_inc/css/wpsstm.css',array('font-awesome'),$this->version );
        
        wp_enqueue_style( 'wpsstm' );
        
    }

    /*
    Checks that we are on one of backend pages of the plugin
    */
    
    function is_admin_page(){

        $screen = get_current_screen();
        $post_type = $screen->post_type;
        $allowed_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_album,
            wpsstm()->post_type_track,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
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

}

function wpsstm() {
	return WP_SoundSytem::instance();
}

wpsstm();