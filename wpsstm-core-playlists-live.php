<?php
class WP_SoundSystem_Core_Live_Playlists{
    
    public $allowed_post_types;
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Live_Playlists;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-stats.php');
        
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){
        
    }

    function setup_actions(){
        
        if ( wpsstm()->get_options('live_playlists_enabled') != 'on' ) return;
        
        add_action( 'plugins_loaded', array($this, 'spiff_upgrade'));
        add_action( 'init', array($this,'register_post_type_live_playlist' ));
        
        //listing
        add_action( 'pre_get_posts', array($this, 'sort_stations'));
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_live_playlist), array(&$this,'post_column_register'), 5);
        add_filter( sprintf('manage_edit-%s_sortable_columns',wpsstm()->post_type_live_playlist), array(&$this,'post_column_sortable_register'), 5);
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_live_playlist), array(&$this,'post_column_content'), 5, 2);
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
                update_post_meta( $settings_post->ID, WP_SoundSystem_Remote_Tracklist::$wizard_url, $settings["feed_url"] );
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

            update_post_meta($settings_post->ID,WP_SoundSystem_Remote_Tracklist::$wizard_options,$settings);
            
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
        
        //rename health meta
        $query_post_meta = $wpdb->prepare( 
            "UPDATE $wpdb->postmeta SET meta_key = '%s' WHERE meta_key = '%s'",
            WP_SoundSystem_Live_Playlist_Stats::$meta_key_health,
            'spiff_station_health'
        );
        $wpdb->query($query_post_meta);
            
        //rename other old post meta
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
            'name'                  => _x( 'Live Playlists', 'Live Playlists General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Live Playlist', 'Live Playlist Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Live Playlists', 'wpsstm' ),
            'name_admin_bar'        => __( 'Live Playlist', 'wpsstm' ),
            'archives'              => __( 'Live Playlist Archives', 'wpsstm' ),
            'attributes'            => __( 'Live Playlist Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Live Playlist:', 'wpsstm' ),
            'all_items'             => __( 'All Live Playlists', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Live Playlist', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Live Playlist', 'wpsstm' ),
            'edit_item'             => __( 'Edit Live Playlist', 'wpsstm' ),
            'update_item'           => __( 'Update Live Playlist', 'wpsstm' ),
            'view_item'             => __( 'View Live Playlist', 'wpsstm' ),
            'view_items'            => __( 'View Live Playlists', 'wpsstm' ),
            'search_items'          => __( 'Search Live Playlist', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            //'insert_into_item'      => __( 'Insert into live playlist', 'wpsstm' ),
            //'uploaded_to_this_item' => __( 'Uploaded to this live playlist', 'wpsstm' ),
            'items_list'            => __( 'Live Playlists list', 'wpsstm' ),
            'items_list_navigation' => __( 'Live Playlists list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter live playlists list', 'wpsstm' ),
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
    
    public function get_preset_tracklist($post_id_or_feed_url = null){

        $post_id = null;
        $feed_url = null;

        $tracklist = new WP_SoundSystem_Remote_Tracklist($post_id_or_feed_url);
 
        //load page preset
        if ( $live_tracklist_preset = $this->get_live_tracklist_preset($tracklist->feed_url) ){
            $tracklist = $live_tracklist_preset;
            $tracklist->__construct($post_id_or_feed_url);
            $tracklist->add_notice( 'wizard-header', 'preset_loaded', sprintf(__('The preset %s has been loaded','wpsstm'),'<em>'.$live_tracklist_preset->preset_name.'</em>') );
        }

        return $tracklist;
    }
    
    function get_live_tracklist_preset($feed_url){
        
        $enabled_presets = array();

        $available_presets = self::get_available_presets();

        //get matching presets
        foreach((array)$available_presets as $preset){

            if ( $preset->can_load_tracklist_url($feed_url) ){
                $enabled_presets[] = $preset;
            }

        }
        
        //return last (highest priority) preset
        return end($enabled_presets);

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

    function post_column_register($columns){
        
        $columns['health'] = __('Live','spiff');
        $columns['requests-month'] = __('Requests (month)','spiff');
        $columns['requests-total'] = __('Requests (total)','spiff');
        
        return $columns;
    }
    
    function post_column_sortable_register($columns){
        $columns['health'] = 'health';
        $columns['requests-month'] = 'trending';
        $columns['requests-total'] = 'popular';
        return $columns;
    }
    
    function post_column_content($column_name, $post_id){
        
        if ( !in_array($column_name,array('health','requests-month','requests-total')) ) return;
        
        $output = '—';
        
        switch($column_name){
            //health
            case 'health':

                if ( get_post_status($post_id) != 'publish') break;

                $percentage = WP_SoundSystem_Live_Playlist_Stats::get_health($post_id);
                $output = wpsstm_get_percent_bar($percentage);
            break;
            
            //month requests
            case 'requests-month':
                
                if ( get_post_status($post_id) != 'publish') break;
                
                $output = WP_SoundSystem_Live_Playlist_Stats::get_monthly_request_count($post_id);
            break;
                
            //total requests
            case 'requests-total':
                
                if ( get_post_status($post_id) != 'publish') break;
                
                $output = WP_SoundSystem_Live_Playlist_Stats::get_request_count($post_id);

                
            break;  
        }

        echo $output;
    }
    
    function sort_stations( $query ) {

        if ( ($query->get('post_type')==wpsstm()->post_type_live_playlist) && ( $orderby = $query->get( 'orderby' ) ) ){

            $order = ( $query->get( 'order' ) ) ? $query->get( 'order' ) : 'DESC';

            switch ($orderby){

                case 'health':
                    $query->set('meta_key', WP_SoundSystem_Live_Playlist_Stats::$meta_key_health );
                    $query->set('orderby','meta_value_num');
                    $query->set('order', $order);
                break;
                    
                case 'trending':
                    //TO FIX check https://wordpress.stackexchange.com/questions/95847/popular-posts-by-view-with-jetpack
                    $query->set('meta_key', WP_SoundSystem_Live_Playlist_Stats::$meta_key_monthly_requests );
                    $query->set('orderby','meta_value_num');
                    $query->set('order', $order);
                break;
                    
                case 'popular':
                    //TO FIX check https://wordpress.stackexchange.com/questions/95847/popular-posts-by-view-with-jetpack
                    $query->set('meta_key', WP_SoundSystem_Live_Playlist_Stats::$meta_key_requests );
                    $query->set('orderby','meta_value_num');
                    $query->set('order', $order);
                break;
                break;
                    
            }

        }

        return $query;
        
    }
    
}

function wpsstm_live_playlists() {
	return WP_SoundSystem_Core_Live_Playlists::instance();
}

wpsstm_live_playlists();