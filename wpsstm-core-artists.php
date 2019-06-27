<?php

class WPSSTM_Core_Artists{

    function __construct(){
        add_action( 'wpsstm_init_post_types', array($this,'register_artist_post_type' ));
        add_action( 'wpsstm_init_post_types', array($this,'register_artist_taxonomy' ));
        
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_artists_submenu' ) );
        
        add_action( 'add_meta_boxes', array($this, 'metabox_artist_register'));
    }
    
    //add custom admin submenu under WPSSTM
    function backend_artists_submenu($parent_slug){
        //capability check
        $post_type_slug = wpsstm()->post_type_artist;
        $post_type_obj = get_post_type_object($post_type_slug);
        
         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );
        
    }

    function register_artist_post_type() {
        $labels = array(
            'name'                  => _x( 'Artists', 'Artists General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Artist', 'Artist Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Artists', 'wpsstm' ),
            'name_admin_bar'        => __( 'Artist', 'wpsstm' ),
            'archives'              => __( 'Artist Archives', 'wpsstm' ),
            'attributes'            => __( 'Artist Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Artist:', 'wpsstm' ),
            'all_items'             => __( 'All Artists', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Artist', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Artist', 'wpsstm' ),
            'edit_item'             => __( 'Edit Artist', 'wpsstm' ),
            'update_item'           => __( 'Update Artist', 'wpsstm' ),
            'view_item'             => __( 'View Artist', 'wpsstm' ),
            'view_items'            => __( 'View Artists', 'wpsstm' ),
            'search_items'          => __( 'Search Artists', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            'insert_into_item'      => __( 'Insert into artist', 'wpsstm' ),
            'uploaded_to_this_item' => __( 'Uploaded to this artist', 'wpsstm' ),
            'items_list'            => __( 'Artists list', 'wpsstm' ),
            'items_list_navigation' => __( 'Artists list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter artists list', 'wpsstm' ),
        );
        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,
            'supports' => array( 'author','thumbnail', 'comments' ),
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
            'rewrite' => array(
                'slug' => sprintf('%s/%s',WPSSTM_BASE_SLUG,WPSSTM_ARTISTS_SLUG),
                'with_front' => FALSE
            ),
            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You 
             * can use a string or an array (for singular and plural forms).  The array is useful if the 
             * plural form can't be made by simply adding an 's' to the end of the word.  For example, 
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'artist', // string|array (defaults to 'post')
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
                'edit_post'              => 'edit_artist',
                'read_post'              => 'read_artist',
                'delete_post'            => 'delete_artist',
                // primitive/meta caps
                'create_posts'           => 'create_artists',
                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_artists',
                'edit_others_posts'      => 'manage_artists',
                'publish_posts'          => 'manage_artists',
                'read_private_posts'     => 'read',
                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_artists',
                'delete_private_posts'   => 'manage_artists',
                'delete_published_posts' => 'manage_artists',
                'delete_others_posts'    => 'manage_artists',
                'edit_private_posts'     => 'edit_artists',
                'edit_published_posts'   => 'edit_artists'
            )
        );
        register_post_type( wpsstm()->post_type_artist, $args );
    }
    
    function register_artist_taxonomy(){

        $labels = array(
            'name'                       => _x( 'Track Artists', 'Taxonomy General Name', 'wpsstm' ),
            'singular_name'              => _x( 'Track Artist', 'Taxonomy Singular Name', 'wpsstm' ),
            'menu_name'                  => __( 'Taxonomy', 'wpsstm' ),
            'all_items'                  => __( 'All Items', 'wpsstm' ),
            'parent_item'                => __( 'Parent Item', 'wpsstm' ),
            'parent_item_colon'          => __( 'Parent Item:', 'wpsstm' ),
            'new_item_name'              => __( 'New Item Name', 'wpsstm' ),
            'add_new_item'               => __( 'Add New Item', 'wpsstm' ),
            'edit_item'                  => __( 'Edit Item', 'wpsstm' ),
            'update_item'                => __( 'Update Item', 'wpsstm' ),
            'view_item'                  => __( 'View Item', 'wpsstm' ),
            'separate_items_with_commas' => __( 'Separate items with commas', 'wpsstm' ),
            'add_or_remove_items'        => __( 'Add or remove items', 'wpsstm' ),
            'choose_from_most_used'      => __( 'Choose from the most used', 'wpsstm' ),
            'popular_items'              => __( 'Popular Items', 'wpsstm' ),
            'search_items'               => __( 'Search Items', 'wpsstm' ),
            'not_found'                  => __( 'Not Found', 'wpsstm' ),
            'no_terms'                   => __( 'No items', 'wpsstm' ),
            'items_list'                 => __( 'Items list', 'wpsstm' ),
            'items_list_navigation'      => __( 'Items list navigation', 'wpsstm' ),
        );
        $capabilities = array(
            'manage_terms'               => 'manage_tracks',
            'edit_terms'                 => 'manage_tracks',
            'delete_terms'               => 'manage_tracks',
            'assign_terms'               => 'edit_tracks',
        );
        $args = array(
            'labels'                     => $labels,
            'hierarchical'               => false,
            'public'                     => false,
            'show_ui'                    => false,
            'show_admin_column'          => false,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'capabilities'               => $capabilities,
        );
        register_taxonomy(WPSSTM_Core_Tracks::$artist_taxonomy, array( wpsstm()->post_type_track ), $args );

    }
    
    function metabox_artist_register(){

        add_meta_box( 
            'wpsstm-artist-info', 
            __('Artist','wpsstm'),
            array('WPSSTM_Core_Tracks','metabox_music_infos_content'),
            wpsstm()->post_type_artist, 
            'after_title', 
            'high' 
        );
    }

}
function wpsstm_artists_init(){
    new WPSSTM_Core_Artists();
}
add_action('wpsstm_init','wpsstm_artists_init');