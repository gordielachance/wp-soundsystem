<?php
class WP_SoundSytem_Core_Live_Playlists{
    
    public $qvar_url_input='wpsstm_url_input';
    public $allowed_post_types;
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_Live_Playlists;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){

    }

    
    function setup_actions(){
        
        add_action( 'plugins_loaded', array($this, 'spiff_upgrade'));

        add_action( 'init', array($this,'register_post_type_live_playlist' ));
        add_filter('manage_posts_columns', array($this,'tracks_column_live_playlist_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'tracks_column_live_playlist_content'), 10, 2 );

    }
    
    function spiff_upgrade(){
        global $wpdb;

        if ( !$db_v = get_option("spiff-db") ) return;

        wpsstm()->debug_log("upgrade_from_spiff()"); 
        
        //upgrade old spiff settings
        $args = array(
            'post_type'         => 'station',
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'meta_key'          => 'spiff_settings'
        );
        $settings_posts = get_posts($args);
        foreach ($settings_posts as $settings_post){
            $settings = get_post_meta($settings_post->ID,'spiff_settings',true);
            
            //feed url
            if ( isset($settings["feed_url"]) ){
                update_post_meta( $settings_post->ID, WP_SoundSytem_Playlist_Scraper::$meta_key_scraper_url, $settings["feed_url"] );
                unset($settings["feed_url"]);
            }
            
            $new_settings['selectors'] = array();
            
            //selectors
            if ( isset($settings["selectors"]) ){
                foreach($settings["selectors"] as $selector_slug => $value){
                    if (!$value) continue;
                    $new_settings['selectors'][$selector_slug]['path'] = $value;
                }

            }
            //regexes
            if ( isset($settings["selectors_regex"]) ){
                foreach($settings["selectors_regex"] as $selector_slug => $value){
                    if (!$value) continue;
                    $new_settings['selectors'][$selector_slug]['regex'] = $value;
                }

            }
            
            $settings['selectors'] = $new_settings['selectors'];

            update_post_meta($settings_post->ID,WP_SoundSytem_Playlist_Scraper::$meta_key_options_scraper,$settings);
            
        }
        
        //upgrade old post type
        $query_post_type = $wpdb->prepare( 
            "UPDATE $wpdb->posts SET post_type = REPLACE(post_type, '%s', '%s')",
            'station',
            wpsstm()->post_type_live_playlist
        );
        $wpdb->query($query_post_type);
        
        //service
        $query_post_meta_service = $wpdb->prepare(
                "DELETE FROM $wpdb->postmeta
                WHERE meta_key = %s",
                'spiff_service'
            );
        $wpdb->query($query_post_meta_service);
            
        //upgrade other old post meta
        $query_post_meta = $wpdb->prepare( 
            "UPDATE $wpdb->postmeta SET meta_key = REPLACE(meta_key, '%s', '%s')",
            'spiff',
            'wpsstm'
        );
        $wpdb->query($query_post_meta);
        
        delete_option( "spiff-db" );
    }

    function register_post_type_live_playlist() {

        $labels = array( 
            'name' => _x( 'Live Playlists', 'wpsstm' ),
            'singular_name' => _x( 'Live Playlist', 'wpsstm' )
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'title','editor','author','thumbnail', 'comments' ),
            'taxonomies' => array( 'post_tag' ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            //http://justintadlock.com/archives/2013/09/13/register-post-type-cheat-sheet
            'capability_type' => 'post', //playlist
            //'map_meta_cap'        => true,
            /*
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_playlist',
                'read_post'              => 'read_playlist',
                'delete_post'            => 'delete_playlist',

                // primitive/meta caps
                'create_posts'           => 'create_playlists',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_playlists',
                'edit_others_posts'      => 'manage_playlists',
                'publish_posts'          => 'manage_playlists',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_playlists',
                'delete_private_posts'   => 'manage_playlists',
                'delete_published_posts' => 'manage_playlists',
                'delete_others_posts'    => 'manage_playlists',
                'edit_private_posts'     => 'edit_playlists',
                'edit_published_posts'   => 'edit_playlists'
            ),
            */
        );

        register_post_type( wpsstm()->post_type_live_playlist, $args );
    }
    

    function get_available_services_ids($ignore_ids = null){

        $services_ids = array();
        $ignore_ids = (array)$ignore_ids;

        //TO FIX should be filter with pre_get_posts
        $services_args = array(
            'post_type'         => wpsstm()->post_type_live_playlist,
            'posts_per_page'    => -1,
            'orderby'           => 'is_service',
            //optimize query :
            'fields'            => 'ids',
            //'no_found_rows' => true, //https://wpartisan.me/tutorials/wordpress-database-queries-speed-sql_calc_found_rows
            //'update_post_term_cache' => false, // grabs post terms
            //'update_post_meta_cache' => true // grabs post meta (here needed)
        );

        if ( !empty($ignore_ids) ){
            $services_args['post__not_in'] = $ignore_ids;
        }

        $query = new WP_Query($services_args);

        foreach ($query->posts as $post_id){
            $services_ids[] = $post_id;
        }

        return $services_ids;

    }
    
    /**
    Overrides the function from WP_SoundSytem_Core_Playlists
    **/
    
    function metabox_tracklist_scripts_styles(){
        // CSS
        wp_enqueue_style( 'wpsstm-tracklist',  wpsstm()->plugin_url . '_inc/css/wpsstm-tracklist.css',wpsstm()->version );
    }

    function tracks_column_live_playlist_register($defaults) {
        global $post;

        $post_types = array(
            wpsstm()->post_type_track
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$post_types) ){
            
            $hide_subtracks = wpsstm()->get_options('hide_subtracks');
            $hide_subtracks = ($hide_subtracks == 'on') ? true : false;
            
            $hide_subtracks = false; //TO FIX remove this line
            
            if (!$hide_subtracks){
                $after['live_playlist'] = __('Live playlist','wpsstm');
            }
            
        }
        
        return array_merge($before,$defaults,$after);
    }
    
    function tracks_column_live_playlist_content($column,$post_id){
        global $post;

        switch ( $column ) {
            case 'live_playlist':

                $tracklist_ids = wpsstm_get_tracklist_ids_for_track($post_id);
                $links = array();
                
                foreach((array)$tracklist_ids as $tracklist_id){
                    
                    $tracklist_post_type = get_post_type($tracklist_id);
                    if ( $tracklist_post_type != wpsstm()->post_type_live_playlist ) continue;
                    
                    $playlist_url = get_permalink($tracklist_id);
                    $playlist_name = get_the_title($tracklist_id);
                    $links[] = sprintf('<a href="%s">%s</a>',$playlist_url,$playlist_name);
                    
                }
                if ($links){
                    echo implode(',',$links);
                }else{
                    echo '—';
                }

                
            break;
        }
    }

}

function wpsstm_live_playlists() {
	return WP_SoundSytem_Core_Live_Playlists::instance();
}



wpsstm_live_playlists();