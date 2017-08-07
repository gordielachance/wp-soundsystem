<?php

class WP_SoundSystem_Core_Playlists{

    /**
    * @var The one true Instance
    */
    private static $instance;
    public $subtracks_static_metaname = 'wpsstm_subtrack_ids';

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Playlists;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        
        require wpsstm()->plugin_dir . 'classes/wpsstm-live-tracklist-class.php';
        
        //add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }

    
    function setup_actions(){

        add_action( 'init', array($this,'register_post_type_playlist' ));

    }

    function register_post_type_playlist() {

        $labels = array(
            'name'                  => _x( 'Playlists', 'Playlists General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Playlist', 'Playlist Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Playlists', 'wpsstm' ),
            'name_admin_bar'        => __( 'Playlist', 'wpsstm' ),
            'archives'              => __( 'Playlist Archives', 'wpsstm' ),
            'attributes'            => __( 'Playlist Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Playlist:', 'wpsstm' ),
            'all_items'             => __( 'All Playlists', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Playlist', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Playlist', 'wpsstm' ),
            'edit_item'             => __( 'Edit Playlist', 'wpsstm' ),
            'update_item'           => __( 'Update Playlist', 'wpsstm' ),
            'view_item'             => __( 'View Playlist', 'wpsstm' ),
            'view_items'            => __( 'View Playlists', 'wpsstm' ),
            'search_items'          => __( 'Search Playlist', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            'insert_into_item'      => __( 'Insert into playlist', 'wpsstm' ),
            'uploaded_to_this_item' => __( 'Uploaded to this playlist', 'wpsstm' ),
            'items_list'            => __( 'Playlists list', 'wpsstm' ),
            'items_list_navigation' => __( 'Playlists list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter playlists list', 'wpsstm' ),
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
            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You 
             * can use a string or an array (for singular and plural forms).  The array is useful if the 
             * plural form can't be made by simply adding an 's' to the end of the word.  For example, 
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'playlist', // string|array (defaults to 'post')

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
            )
        );

        register_post_type( wpsstm()->post_type_playlist, $args );
    }
}

function wpsstm_playlists() {
	return WP_SoundSystem_Core_Playlists::instance();
}

wpsstm_playlists();
