<?php
class WPSSTM_Core_Albums{

    function __construct() {

        add_action( 'init', array($this,'register_post_type_album' ));
        add_action( 'init', array($this,'register_album_taxonomy' ));

        add_action( 'wpsstm_register_submenus', array( $this, 'backend_albums_submenu' ) );

        add_action( 'add_meta_boxes', array($this, 'metabox_album_register'));

        add_filter( 'the_title', array($this, 'the_album_post_title'), 9, 2 );

    }

    function metabox_album_register(){

        add_meta_box(
            'wpsstm-album-info',
            __('Album','wpsstm'),
            array('WPSSTM_Core_Tracks','metabox_music_infos_content'),
            wpsstm()->post_type_album,
            'after_title',
            'high'
        );
    }

    //add custom admin submenu under WPSSTM
    function backend_albums_submenu($parent_slug){
        //capability check
        $post_type_slug = wpsstm()->post_type_album;
        $post_type_obj = get_post_type_object($post_type_slug);

         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );

    }

    function register_post_type_album() {
        $labels = array(
            'name'                  => _x( 'Albums', 'Albums General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Album', 'Album Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Albums', 'wpsstm' ),
            'name_admin_bar'        => __( 'Album', 'wpsstm' ),
            'archives'              => __( 'Album Archives', 'wpsstm' ),
            'attributes'            => __( 'Album Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Album:', 'wpsstm' ),
            'all_items'             => __( 'All Albums', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Album', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Album', 'wpsstm' ),
            'edit_item'             => __( 'Edit Album', 'wpsstm' ),
            'update_item'           => __( 'Update Album', 'wpsstm' ),
            'view_item'             => __( 'View Album', 'wpsstm' ),
            'view_items'            => __( 'View Albums', 'wpsstm' ),
            'search_items'          => __( 'Search Albums', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            'insert_into_item'      => __( 'Insert into album', 'wpsstm' ),
            'uploaded_to_this_item' => __( 'Uploaded to this album', 'wpsstm' ),
            'items_list'            => __( 'Albums list', 'wpsstm' ),
            'items_list_navigation' => __( 'Albums list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter albums list', 'wpsstm' ),
        );
        $args = array(
            'labels' => $labels,
            'hierarchical' => false,
            'supports' => array( 'author','thumbnail','comments' ),
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
                'slug' => sprintf('%s/%s',WPSSTM_BASE_SLUG,WPSSTM_ALBUMS_SLUG),
                'with_front' => FALSE
            ),
            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You
             * can use a string or an array (for singular and plural forms).  The array is useful if the
             * plural form can't be made by simply adding an 's' to the end of the word.  For example,
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'album', // string|array (defaults to 'post')
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
                'edit_post'              => 'edit_album',
                'read_post'              => 'read_album',
                'delete_post'            => 'delete_album',
                // primitive/meta caps
                'create_posts'           => 'create_albums',
                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_albums',
                'edit_others_posts'      => 'manage_albums',
                'publish_posts'          => 'manage_albums',
                'read_private_posts'     => 'read',
                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_albums',
                'delete_private_posts'   => 'manage_albums',
                'delete_published_posts' => 'manage_albums',
                'delete_others_posts'    => 'manage_albums',
                'edit_private_posts'     => 'edit_albums',
                'edit_published_posts'   => 'edit_albums'
            )
        );
        register_post_type( wpsstm()->post_type_album, $args );
    }

    function register_album_taxonomy(){

        $labels = array(
            'name'                       => _x( 'Track Albums', 'Taxonomy General Name', 'wpsstm' ),
            'singular_name'              => _x( 'Track Album', 'Taxonomy Singular Name', 'wpsstm' ),
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

        register_taxonomy(
            WPSSTM_Core_Tracks::$album_taxonomy,
            array(
                wpsstm()->post_type_track,
                wpsstm()->post_type_album
            ),
            $args
        );

    }

    function the_album_post_title($title,$post_id){
        //post type check
        $post_type = get_post_type($post_id);
        if ( $post_type !== wpsstm()->post_type_album ) return $title;
        $title = wpsstm_get_post_track($post_id);
        $artist = wpsstm_get_post_artist($post_id);

        if (!$title || !$artist) return null;

        return sprintf('"%s" - %s',$title,$artist);
    }

}
function wpsstm_albums_init(){
    new WPSSTM_Core_Albums();
}
add_action('plugins_loaded','wpsstm_albums_init');
