<?php
class WP_SoundSystem_Core_Live_Playlists{

    public $feed_url_meta_name = '_wpsstm_scraper_url';
    public $scraper_meta_name = '_wpsstm_scraper_options';
    public $subtracks_live_metaname = 'wpsstm_live_subtrack_ids';
    public $remote_title_meta_name = 'wpsstm_remote_title';
    public $remote_author_meta_name = 'wpsstm_remote_author_name';
    public $presets;
    
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
        require_once(wpsstm()->plugin_dir . 'classes/wpsstm-scraper-stats.php');
        
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){
        $this->presets = (array)$this->get_available_presets();
    }

    function setup_actions(){
        
        if ( wpsstm()->get_options('live_playlists_enabled') != 'on' ) return;

        add_action( 'init', array($this,'register_post_type_live_playlist' ));
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_live_playlists_submenu' ) );

        //listing
        add_action( 'pre_get_posts', array($this, 'sort_live_playlists'));
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_live_playlist), array(&$this,'post_column_register'), 5);
        add_filter( sprintf('manage_edit-%s_sortable_columns',wpsstm()->post_type_live_playlist), array(&$this,'post_column_sortable_register'), 5);
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_live_playlist), array(&$this,'post_column_content'), 5, 2);

        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_live_playlist), array(wpsstm(),'register_community_view') );
        
        add_filter('wpsstm_get_remote_url',array($this,'filter_dropbox_url'));
        
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

            'supports' => array( 'author','title','editor','author','thumbnail', 'comments' ),
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
            
            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You 
             * can use a string or an array (for singular and plural forms).  The array is useful if the 
             * plural form can't be made by simply adding an 's' to the end of the word.  For example, 
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'live_playlist', // string|array (defaults to 'post')

            /**
             * Whether WordPress should map the meta capabilities (edit_post, read_post, delete_post) for 
             * you.  If set to FALSE, you'll need to roll your own handling of this by filtering the 
             * 'map_meta_cap' hook.
             */
            'map_meta_cap'        => true, // bool (defaults to FALSE)

            /**
             * Provides more precise control over the capabilities than the defaults.  By default, WordPress 
             * will use the 'capability_type' argument to build these capabilities.  More often than not, 
             * this results in many extra capabilities that you probably don't need.  The following is how 
             * I set up capabilities for many post types, which only uses three basic capabilities you need 
             * to assign to roles: 'manage_examples', 'edit_examples', 'create_examples'.  Each post type 
             * is unique though, so you'll want to adjust it to fit your needs.
             */
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_live_playlist',
                'read_post'              => 'read_live_playlist',
                'delete_post'            => 'delete_live_playlist',

                // primitive/meta caps
                'create_posts'           => 'create_live_playlists',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_live_playlists',
                'edit_others_posts'      => 'manage_live_playlists',
                'publish_posts'          => 'manage_live_playlists',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_live_playlists',
                'delete_private_posts'   => 'manage_live_playlists',
                'delete_published_posts' => 'manage_live_playlists',
                'delete_others_posts'    => 'manage_live_playlists',
                'edit_private_posts'     => 'edit_live_playlists',
                'edit_published_posts'   => 'edit_live_playlists'
            )

        );

        register_post_type( wpsstm()->post_type_live_playlist, $args );
    }
    
    //add custom admin submenu under WPSSTM
    function backend_live_playlists_submenu($parent_slug){

        //capability check
        $post_type_slug = wpsstm()->post_type_live_playlist;
        $post_type_obj = get_post_type_object($post_type_slug);
        
         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );
        
    }

    /*
    Register scraper presets.
    */
    private function get_available_presets(){

        $presets_path = trailingslashit( wpsstm()->plugin_dir . 'classes/scraper-presets' );
        
        //default class
        require_once($presets_path . 'default.php');
        
        //get all files in /presets directory
        $preset_files = glob( $presets_path . '*.php' ); 

        foreach ($preset_files as $file) {
            require_once($file);
        }

        $class_names = apply_filters( 'wpsstm_get_scraper_presets',array() );
        $class_names = array_reverse($class_names); //reverse in order to respect filters priority

        //check and run
        foreach((array)$class_names as $class_name){
            if ( !class_exists($class_name) ) continue;
            $can_use_preset = $class_name::can_use_preset();
            if ( $can_use_preset !== true ) continue;
            $presets[] = new $class_name();
        }
        
        return $presets;
    }
    
    function is_preset_loaded($slug){
        foreach ($this->presets as $preset){
            if ( $slug == $preset->preset_slug ) return true;
        }
        return false;
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

    function sort_live_playlists( $query ) {

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

    function can_live_playlists(){
        $tracklist_obj = get_post_type_object( wpsstm()->post_type_live_playlist );
        $community_user_id = wpsstm()->get_options('community_user_id');
        return user_can($community_user_id,$tracklist_obj->cap->edit_posts);
    }
    
    function filter_dropbox_url($url){

        $domain = wpsstm_get_url_domain($url );

        //dropbox : convert to raw link
        if ($domain=='dropbox'){
            $url_no_args = strtok($url, '?');
            $url = add_query_arg(array('raw'=>1),$url_no_args); //http://stackoverflow.com/a/11846251/782013
        }
        
        return $url;
    }

}

function wpsstm_live_playlists() {
	return WP_SoundSystem_Core_Live_Playlists::instance();
}

wpsstm_live_playlists();