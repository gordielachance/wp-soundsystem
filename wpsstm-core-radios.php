<?php
class WPSSTM_Core_Radios{
    static $remote_author_meta_name = 'wpsstm_remote_author_name';
    static $is_expired_meta_name = 'wpsstm_is_expired';
    static $import_success_time_meta_name = 'wpsstm_import_success_time';
    static $import_attempt_time_meta_name = 'wpsstm_import_attempt_time';
    static $importer_slug_meta_name = 'wpsstm_importer_slug';
    static $cache_timeout_meta_name = 'wpsstm_cache_timeout'; //seconds

    function __construct() {

        /*
        Even if we don't have the API key enabling radios, we still want to register the radios post type :
        If, for example, we HAD an API key but that it has expired; and that we don't register the radios post type, accessing those posts will return a 404 error, which is confusing:
        Better register the post type and fire a tracklist notice to say that it cannot be refreshed because API key is invalid.
        */

        add_action( 'init', array($this,'register_post_type_radio' ));

        add_filter( 'pre_get_posts', array($this,'pre_get_tracklist_by_pulse') );
        add_filter( 'wpsstm_tracklist_classes', array($this, 'live_tracklist_classes'), 10, 2 );
        add_filter( 'wpsstm_tracklist_actions', array($this, 'filter_live_tracklist_actions'),10,2 );


        //backend
        add_action('admin_notices', array(__class__,'radios_notice') );
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_radios_submenu' ) );
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_radio), array(wpsstm(),'register_imported_view'), 5 );

        //special radios
        add_action( 'wpsstm_populated_tracklist', array(__class__,'now_playing_radio') );
        add_action( 'wpsstm_populated_tracklist', array(__class__,'sitewide_favorites_radio') );


    }

    function register_post_type_radio() {

        $labels = array(
            'name'                  => _x( 'Radios', 'Radios General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Radio', 'Radio Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Radios', 'wpsstm' ),
            'name_admin_bar'        => __( 'Radio', 'wpsstm' ),
            'archives'              => __( 'Radio Archives', 'wpsstm' ),
            'attributes'            => __( 'Radio Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Radio:', 'wpsstm' ),
            'all_items'             => __( 'All Radios', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Radio', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Radio', 'wpsstm' ),
            'edit_item'             => __( 'Edit Radio', 'wpsstm' ),
            'update_item'           => __( 'Update Radio', 'wpsstm' ),
            'view_item'             => __( 'View Radio', 'wpsstm' ),
            'view_items'            => __( 'View Radios', 'wpsstm' ),
            'search_items'          => __( 'Search Radio', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            //'insert_into_item'      => __( 'Insert into radio', 'wpsstm' ),
            //'uploaded_to_this_item' => __( 'Uploaded to this radio', 'wpsstm' ),
            'items_list'            => __( 'Radios list', 'wpsstm' ),
            'items_list_navigation' => __( 'Radios list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter radios list', 'wpsstm' ),
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
            'rewrite' => array(
                'slug' => sprintf('%s/%s',WPSSTM_BASE_SLUG,WPSSTM_LIVE_PLAYLISTS_SLUG), // = /music/radios
                'with_front' => FALSE
            ),

            //http://justintadlock.com/archives/2013/09/13/register-post-type-cheat-sheet

            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You
             * can use a string or an array (for singular and plural forms).  The array is useful if the
             * plural form can't be made by simply adding an 's' to the end of the word.  For example,
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'radio', // string|array (defaults to 'post')

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
                'edit_post'              => 'edit_radio',
                'read_post'              => 'read_radio',
                'delete_post'            => 'delete_radio',

                // primitive/meta caps
                'create_posts'           => 'create_radios',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_radios',
                'edit_others_posts'      => 'manage_radios',
                'publish_posts'          => 'manage_radios',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_radios',
                'delete_private_posts'   => 'manage_radios',
                'delete_published_posts' => 'manage_radios',
                'delete_others_posts'    => 'manage_radios',
                'edit_private_posts'     => 'edit_radios',
                'edit_published_posts'   => 'edit_radios'
            )

        );

        register_post_type( wpsstm()->post_type_radio, $args );
    }

    //add custom admin submenu under WPSSTM
    function backend_radios_submenu($parent_slug){

        //capability check
        $post_type_slug = wpsstm()->post_type_radio;
        $post_type_obj = get_post_type_object($post_type_slug);

         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );

    }

    function live_tracklist_classes($classes,$tracklist){
        if ( get_post_type($tracklist->post_id) == wpsstm()->post_type_radio ){
            $classes[] = 'wpsstm-live-tracklist';
        }
        return $classes;
    }

    function filter_live_tracklist_actions($actions,$tracklist){

        if ($tracklist->tracklist_type !== 'live' ) return $actions;
        if (!$tracklist->feed_url) return $actions;

        $new_actions['refresh'] = array(
            'text' =>       __('Refresh', 'wpsstm'),
            'href' =>       $tracklist->get_tracklist_action_url('refresh'),
            'classes' =>    array('wpsstm-reload-bt'),
        );

        return $new_actions + $actions;
    }

    function pre_get_tracklist_by_pulse( $query ) {

        //TOUFIX what if the post doesn't have a cache min meta ?

        if ( !$meta_query = $query->get( 'meta_query') ) $meta_query = array();
        $max = $query->get( 'pulse-max' );

        if ( $max && ctype_digit($max) ) {

            $meta_query[] = array(
                'key' => self::$cache_timeout_meta_name,
                'value' => $max,
                'type' => 'NUMERIC',
                'compare' => '<='
            );

            $query->set( 'meta_query', $meta_query);

        }

        return $query;
    }

    static function radios_notice(){
        $screen = get_current_screen();
        if ( ($screen->base != 'edit') || ($screen->post_type != wpsstm()->post_type_radio) ) return;

        $notice = __("Radios are how we call 'live playlists'. Those playlists are synced with remote webpages or services (a Spotify URL, a XSPF file, etc.), and are refreshing seamlessly after a user-defined delay.  Setup the 'Tracklist Importer' metabox while editing a radio.",'wpsstm');
        printf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>',$notice);
    }

    static function now_playing_radio($tracklist){
        $nowplaying_id = wpsstm()->get_options('nowplaying_id');

        if ($tracklist->post_id != $nowplaying_id) return;

        //do not try to sync radio with URL, we do this automatically by filtering get_subtracks().
        $tracklist->last_import_time = current_time( 'timestamp', true );
        $tracklist->date_timestamp = $tracklist->last_import_time;
        $tracklist->is_expired = false;

    }

    static function sitewide_favorites_radio($tracklist){
        $sitewide_favorites_id = wpsstm()->get_options('sitewide_favorites_id');

        if ($tracklist->post_id != $sitewide_favorites_id) return;

        //do not try to sync radio with URL, we do this automatically by filtering get_subtracks().
        $tracklist->last_import_time = current_time( 'timestamp', true );
        $tracklist->date_timestamp = $tracklist->last_import_time;
        $tracklist->is_expired = false;
    }

}

function wpsstm_radios_init(){
    new WPSSTM_Core_Radios();
}

add_action('plugins_loaded','wpsstm_radios_init');
