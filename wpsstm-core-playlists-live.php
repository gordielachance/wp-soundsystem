<?php
class WP_SoundSytem_Core_Live_Playlists{
    
    public $allowed_post_types;
    public $qvar_wizard_url = 'wpsstm_feed_url'; // ! should match the wizard form input name
    public $qvar_wizard_posts = 'wpsstm_wizard_posts';
    public $frontend_wizard_page_id = null;
    public $frontend_wizard_url = null;
    public $frontend_wizard = null;
    public $frontend_wizard_meta_key = '_wpsstm_is_frontend_wizard';
    public $wizard_post_status = 'wpsstm-wizard';
    
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
        
        if ( wpsstm()->get_options('live_playlists_enabled') != 'on' ) return;
        
        add_action( 'plugins_loaded', array($this, 'spiff_upgrade'));
        add_action( 'init', array($this,'register_post_type_live_playlist' ));
        
        //listing
        add_action( 'pre_get_posts', array($this, 'sort_stations'));
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_live_playlist), array(&$this,'post_column_register'), 5);
        add_filter( sprintf('manage_edit-%s_sortable_columns',wpsstm()->post_type_live_playlist), array(&$this,'post_column_sortable_register'), 5);
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_live_playlist), array(&$this,'post_column_content'), 5, 2);
        
        //frontend wizard
        add_filter( 'query_vars', array($this,'add_wizard_query_vars'));
        add_filter( 'page_rewrite_rules', array($this,'frontend_wizard_rewrite') );
        add_action( 'init', array($this,'register_wizard_post_status'));
        add_action( 'posts_where', array($this, 'default_exclude_wizard_post_status'),10,2);
        
        //add_action( 'pre_get_posts', array($this, 'is_single_wizard_post'));
        
        //add_action( 'pre_get_posts', array($this, 'filter_wizard_posts'));
        //add_action( 'pre_get_posts', array($this, 'include_wizard_drafts'));
        
        add_action( 'wp', array($this,'frontend_wizard_action' ) );
        add_action( 'wp', array($this,'frontend_wizard_populate' ) );
        add_filter( 'the_content', array($this,'wizard_status_notice'));
        add_filter( 'the_content', array($this,'frontend_wizard_display'));
        add_filter( 'wpsstm_get_tracklist_link', array($this,'frontend_wizard_get_tracklist_link'), 10, 2);

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

            update_post_meta($settings_post->ID,WP_SoundSytem_Remote_Tracklist::$live_playlist_options_meta_name,$settings);
            
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
    
    public function get_preset_tracklist($post_id_or_feed_url = null){

        $post_id = null;
        $feed_url = null;

        $tracklist = new WP_SoundSytem_Remote_Tracklist($post_id_or_feed_url);
 
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
    *   Add the 'xspf' query variable so Wordpress
    *   won't mangle it.
    */
    function add_wizard_query_vars($vars){
        $vars[] = $this->qvar_wizard_url;
        $vars[] = $this->qvar_wizard_posts;
        return $vars;
    }
    
    /*
    If a single post created with the wizard is requested (eg. Tracklist Wizard redirection), set the query as wizard query so the filters below can run
    */
    
    function is_single_wizard_post( $query ) {
        
        if ( $query->get('post_type') !=wpsstm()->post_type_live_playlist ) return $query;
        
        $post_id = $query->get( 'p' );
        
        if (!$post_id){
            $post_name = $query->get( 'name' );
            if ( $post = get_page_by_path($post_name,OBJECT,wpsstm()->post_type_live_playlist) ){
                $post_id = $post->ID;
            }
        }

        if ( !$post_id ) return $query;
        
        $post_status = get_post_status($post_id);
        
        
        
        print_r($post_status);die();

        $is_wizard = get_post_meta($post_id,wpsstm_live_playlists()->frontend_wizard_meta_key,true);
        if (!$is_wizard) return $query;

        $query->set($this->qvar_wizard_posts,true);
        return $query;
    }
    
    /*
    Default exclude wizard posts from queries, except if it is explicitly requested
    */
    
    function default_exclude_wizard_post_status( $where, $query ){
        global $wpdb;
        
        if ( $query->is_single() ) return $where;
        if ( $query->get('post_status') == $this->wizard_post_status ) return $where;

        $where .= sprintf(" AND {$wpdb->posts}.post_status NOT IN ( '%s' ) ", $this->wizard_post_status );
        return $where;
    }
    
    function filter_wizard_posts( $query ) {
        if ( $query->get('post_type')!=wpsstm()->post_type_live_playlist ) return $query;
        
        $meta_query = $query->get('meta_query');
        
        if ( !$query->get($this->qvar_wizard_posts) ){ //exclude wizard posts
            $meta_query[] = array(
                'key'       => wpsstm_live_playlists()->frontend_wizard_meta_key,
                'compare'   => 'NOT EXISTS'
            );
        }else{ //explicitly requested
            $meta_query[] = array(
                'key'   => wpsstm_live_playlists()->frontend_wizard_meta_key,
                'compare'   => 'EXISTS'
            );
        }

        $query->set('meta_query',$meta_query);

        return $query;

    }
    
    /*
    If wizard posts are explicitly requested, include drafts
    */
    
    function include_wizard_drafts( $query ) {
        
        if ( $query->get('post_type')!=wpsstm()->post_type_live_playlist ) return $query;
        if ( !$query->get($this->qvar_wizard_posts) ) return $query;
        
        //allow drafts for guest user queries
        $post_statii = $query->get( 'post_status' );
        $post_statii = array_filter((array)$post_statii);
        $post_statii = array_merge($post_statii,array('publish',$this->wizard_post_status));
        $query->set( 'post_status', $post_statii );
        $query->set( $this->qvar_wizard_posts, true );

        return $query;
        
    }
    
    function frontend_wizard_action(){
        global $post;
        $action = ( isset($_REQUEST['frontend-wizard-action']) ) ? $_REQUEST['frontend-wizard-action'] : null;
        
        if (!$action) return;
        
        wpsstm()->debug_log(json_encode(array('action'=>$action,'post_id'=>$post->ID,'user_id'=>get_current_user_id())),"frontend_wizard_action()"); 
        
        switch($action){
            case 'save':
                
                //capability check
                $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
                $required_cap = $post_type_obj->cap->edit_posts;

                $updated_post = array(
                    'ID'            => $post->ID,
                    'post_status'   => 'pending'
                );

                if ( wp_update_post( $updated_post ) ){
                    wp_redirect( get_permalink($post->ID) );
                    die();
                }
                
            break;
        }
    }
    
    function frontend_wizard_populate(){
        global $wp_query;

        if ( !is_page($this->frontend_wizard_page_id) ) return;
        
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-wizard.php');

        if ( isset($_REQUEST[ 'wpsstm_wizard' ]['post_id']) ){
            $wizard_id = $_REQUEST[ 'wpsstm_wizard' ]['post_id'];
            $this->frontend_wizard = new WP_SoundSytem_Scraper_Wizard($wizard_id);
        }else{
            $wizard_url = $wp_query->get($this->qvar_wizard_url);
            $this->frontend_wizard = new WP_SoundSytem_Scraper_Wizard($wizard_url);
        }

        $post_id = $this->frontend_wizard->save_frontend_wizard();

        if ( is_wp_error($post_id) ){
            $this->frontend_wizard->tracklist->add_notice( 'wizard-header', 'preset_loaded', __('There has been a problem while loading this playlist:','wpsstm') );
        }elseif($post_id){
            wp_redirect( get_permalink($post_id) );
        }
    }
    
    function frontend_wizard_last_entries(){
        
        $li_items = array();
        
        $query_args = array(
            'post_type'     => wpsstm()->post_type_live_playlist,
            'post_status'   => $this->wizard_post_status,
            /*
            'meta_query' => array(
                array(
                    'key'       => $this->frontend_wizard_meta_key,
                    'compare'   => 'EXISTS'
                )
            )
            */
        );

        $query = new WP_Query($query_args);
        
        foreach($query->posts as $post){
            $feed_url = get_post_meta( $post->ID, WP_SoundSytem_Remote_Tracklist::$meta_key_scraper_url,true );
            $li_items[] = sprintf('<li><a href="%s">%s</a> <small>%s</small></li>',get_permalink($post->ID),get_post_field('post_title',$post->ID),$feed_url);
        }
        if ($li_items){
            return sprintf('<div id="wpsstm-wizard-last-entries"><h2>%s</h2><ul>%s</ul></div>',__('Last requests','wpsstm'),implode("\n",$li_items));
        }
    }
    
    /*
    For posts that have the 'wpsstm-wizard' status, notice the author that it is a temporary playlist.
    */
    
    function wizard_status_notice($content){
        global $post;
        
        if ( get_current_user_id() != $post->post_author ) return $content;
        if ( get_post_status($post->ID) != $this->wizard_post_status ) return $content;
        
        $save_url = add_query_arg(array('frontend-wizard-action'=>'save'),get_permalink());

        $save_link = sprintf('<a href="%s">%s</a>',$save_url,__('here','wpsstm'));
        $save_text = sprintf(__('This is a tempory playlist.  If you want to save it to your account, click %s.','wpsstm'),$save_link);
        $notice = sprintf('<p class="wpsstm-notice wpsstm-bottom-notice">%s</p>',$save_text);
        
        return $notice . $content;
        
    }

    function frontend_wizard_display($content){
        
        if ( !is_page($this->frontend_wizard_page_id) ) return $content;

        //guest ID
        if ( !$user_id = get_current_user_id() ){
            $user_id = $guest_user_id = wpsstm()->get_options('guest_user_id');
        }

        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
        $required_cap = $post_type_obj->cap->edit_posts;

        if ( !user_can($user_id,$required_cap) ){
            
            $auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
            $auth_text = sprintf(__('Loading a tracklist requires you to be logged.  You can login or subscribe %s.','wpsstm'),$auth_link);
            $notice = sprintf('<p class="wpsstm-notice wpsstm-bottom-notice">%s</p>',$auth_text);
            
            return $notice;
        }

        ob_start();
        $this->frontend_wizard->wizard_display();
        $output = ob_get_clean();
        
        $form = sprintf('<form method="post" action="%s">%s</form>',get_permalink(),$output);
        $last_entries = $this->frontend_wizard_last_entries();
        
        return $content . $form . $last_entries;
        
    }
    
    function frontend_wizard_get_tracklist_link($link,$post_id){
        global $wp_query;
        
        if ( $post_id != $this->frontend_wizard_page_id ) return $link;
        
        $frontend_wizard_url = $wp_query->get($this->qvar_wizard_url);

        if ( $frontend_wizard_url ) {
            $link = add_query_arg(array($this->qvar_wizard_url=>$frontend_wizard_url),$link);
        }
        
        return $link;
    }
    
    function get_frontend_wizard_tracklist($tracklist,$post_id){
        if ( ( $post_id == $this->frontend_wizard_page_id ) && ( $this->frontend_wizard ) ) {
            $tracklist = $this->frontend_wizard->tracklist;
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
    
    function register_wizard_post_status(){
        register_post_status( $this->wizard_post_status, array(
            'label'                     => _x( 'Wizard', 'wpsstm' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop( 'Wizard <span class="count">(%s)</span>', 'Wizard <span class="count">(%s)</span>' ),
        ) );
    }
    
}

function wpsstm_live_playlists() {
	return WP_SoundSytem_Core_Live_Playlists::instance();
}

wpsstm_live_playlists();