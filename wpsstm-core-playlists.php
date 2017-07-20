<?php

class WP_SoundSystem_Core_Playlists{

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Playlists;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        
        require wpsstm()->plugin_dir . 'scraper/wpsstm-live-tracklist-class.php';
        
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

        register_post_type( wpsstm()->post_type_playlist, $args );
    }

}

function wpsstm_playlists() {
	return WP_SoundSystem_Core_Playlists::instance();
}

wpsstm_playlists();