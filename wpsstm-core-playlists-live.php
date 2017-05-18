<?php
class WP_SoundSytem_Core_Live_Playlists{
    
    public $allowed_post_types;
    public $qvar_frontend_wizard_url = 'wpsstm_feed_url'; // ! should match the wizard form input name
    public $frontend_wizard_page_id = null;
    public $frontend_wizard_url = null;
    public $frontend_wizard = null;
    
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
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-stats.php');
        
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){
        $this->frontend_wizard_page_id = (int)wpsstm()->get_options('frontend_scraper_page_id');
    }

    function setup_actions(){
        
        add_action( 'plugins_loaded', array($this, 'spiff_upgrade'));
        add_action( 'init', array($this,'register_post_type_live_playlist' ));
        add_filter( 'wpsstm_get_post_tracklist', array($this,'get_live_playlist_tracklist'), 10, 2);
        
        //listing
        add_action( 'pre_get_posts', array($this, 'sort_stations'));
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_live_playlist), array(&$this,'post_column_register'), 5);
        add_filter( sprintf('manage_edit-%s_sortable_columns',wpsstm()->post_type_live_playlist), array(&$this,'post_column_sortable_register'), 5);
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_live_playlist), array(&$this,'post_column_content'), 5, 2);
        
        //frontend wizard
        add_filter( 'query_vars', array($this,'add_query_var_feed_url'));
        add_filter( 'page_rewrite_rules', array($this,'frontend_wizard_rewrite') );
        add_action( 'wp', array($this,'frontend_wizard_populate' ) );
        add_filter( 'wpsstm_get_post_tracklist', array($this,'get_frontend_wizard_tracklist'), 10, 2);
        add_filter( 'the_content', array($this,'frontend_wizard_display'));
        add_filter( 'wpsstm_get_tracklist_link', array($this,'frontend_wizard_get_tracklist_link'), 10, 4);

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
                update_post_meta( $settings_post->ID, WP_SoundSytem_Remote_Tracklist::$meta_key_scraper_url, $settings["feed_url"] );
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

            update_post_meta($settings_post->ID,WP_SoundSytem_Remote_Tracklist::$meta_key_options_scraper,$settings);
            
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
            WP_SoundSytem_Live_Playlist_Stats::$meta_key_health,
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
    
    public function init_live_playlist($url){
        
    }
    
    public function get_live_playlist_tracklist($tracklist,$post_id){
        
        $post_type = get_post_type($post_id);
        if ($post_type != wpsstm()->post_type_live_playlist) return $tracklist;

        $scraper = new WP_SoundSytem_Playlist_Scraper($post_id);
        $tracklist = $scraper->tracklist;

        return $tracklist;
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

                $percentage = WP_SoundSytem_Live_Playlist_Stats::get_health($post_id);
                $output = wpsstm_get_percent_bar($percentage);
            break;
            
            //month requests
            case 'requests-month':
                
                if ( get_post_status($post_id) != 'publish') break;
                
                $output = WP_SoundSytem_Live_Playlist_Stats::get_monthly_request_count($post_id);
            break;
                
            //total requests
            case 'requests-total':
                
                if ( get_post_status($post_id) != 'publish') break;
                
                $output = WP_SoundSytem_Live_Playlist_Stats::get_request_count($post_id);

                
            break;  
        }

        echo $output;
    }
    
    function sort_stations( $query ) {

        if ( ($query->get('post_type')==wpsstm()->post_type_live_playlist) && ( $orderby = $query->get( 'orderby' ) ) ){

            $order = ( $query->get( 'order' ) ) ? $query->get( 'order' ) : 'DESC';

            switch ($orderby){

                case 'health':
                    $query->set('meta_key', WP_SoundSytem_Live_Playlist_Stats::$meta_key_health );
                    $query->set('orderby','meta_value_num');
                    $query->set('order', $order);
                break;
                    
                case 'trending':
                    $query->set('meta_key', WP_SoundSytem_Live_Playlist_Stats::$meta_key_monthly_requests );
                    $query->set('orderby','meta_value_num');
                    $query->set('order', $order);
                break;
                    
                case 'popular':
                    $query->set('meta_key', WP_SoundSytem_Live_Playlist_Stats::$meta_key_requests );
                    $query->set('orderby','meta_value_num');
                    $query->set('order', $order);
                break;
                break;
                    
            }

        }

        return $query;
        
    }
    
    /**
    Overrides the function from WP_SoundSytem_Core_Playlists
    **/
    
    function metabox_tracklist_scripts_styles(){
        // CSS
        wp_enqueue_style( 'wpsstm-tracklist',  wpsstm()->plugin_url . '_inc/css/wpsstm-tracklist.css',null,wpsstm()->version );
    }
    
    /**
    *   Add the 'xspf' query variable so Wordpress
    *   won't mangle it.
    */
    function add_query_var_feed_url($vars){
        $vars[] = $this->qvar_frontend_wizard_url;
        return $vars;
    }

    
    function frontend_wizard_populate(){
        global $wp_query;

        if ( !is_page($this->frontend_wizard_page_id) ) return;

        $frontend_wizard_url = $wp_query->get($this->qvar_frontend_wizard_url);

        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-wizard.php');
        $this->frontend_wizard = new WP_SoundSytem_Playlist_Scraper_Wizard($frontend_wizard_url);
    }

    function frontend_wizard_display($content){
        
        if ( !is_page($this->frontend_wizard_page_id) ) return $content;

        ob_start();
        $this->frontend_wizard->wizard_display();
        $output = ob_get_clean();
        
        return $content . sprintf('<form method="post" action="%s">%s</form>',get_permalink(),$output);
        
    }
    
    function frontend_wizard_get_tracklist_link($link,$post_id,$xspf,$download){
        global $wp_query;
        
        if ( $post_id != $this->frontend_wizard_page_id ) return $link;
        
        $frontend_wizard_url = $wp_query->get($this->qvar_frontend_wizard_url);

        if ( $frontend_wizard_url ) {
            $link = add_query_arg(array($this->qvar_frontend_wizard_url=>$frontend_wizard_url),$link);
        }
        
        return $link;
    }
    
    function get_frontend_wizard_tracklist($tracklist,$post_id){
        if ( ( $post_id == $this->frontend_wizard_page_id ) && ( $this->frontend_wizard ) ) {
            $tracklist = $this->frontend_wizard->scraper->tracklist;
        }
        return $tracklist;
    }
    
    /*
    Handle the XSPF endpoint for the frontend wizard page
    */
    
    function frontend_wizard_rewrite($rules){
        global $wp_rewrite;
        if ( !$this->frontend_wizard_page_id ) return $rules;
        
        $page_slug = get_post_field( 'post_name', $this->frontend_wizard_page_id );

        $wizard_rule = array(
            $page_slug . '/xspf/?' => sprintf('index.php?pagename=%s&%s=true',$page_slug,wpsstm_tracklists()->qvar_xspf)
        );

        return array_merge($wizard_rule, $rules);
    }
}

function wpsstm_live_playlists() {
	return WP_SoundSytem_Core_Live_Playlists::instance();
}



wpsstm_live_playlists();