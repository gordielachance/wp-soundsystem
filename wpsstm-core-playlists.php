<?php

class WPSSTM_Core_Playlists{

    function __construct() {

        add_action( 'init', array($this,'register_post_type_playlist' ));
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_playlists_submenu' ) );

        add_filter( 'pre_get_posts', array($this,'pre_get_posts_favtracks_playlists') );

        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_playlist), array($this,'register_favtracks_playlists_view') );

        /*
        ajax
        */

        add_action('wp_ajax_wpsstm_track_start', array($this,'ajax_update_now_playing'));
        add_action('wp_ajax_nopriv_wpsstm_track_start', array($this,'ajax_update_now_playing'));

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
            'rewrite' => array(
                'slug' => sprintf('%s/%s',WPSSTM_BASE_SLUG,WPSSTM_PLAYLISTS_SLUG), // = /music/playlists
                'with_front' => FALSE
            ),
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

    //add custom admin submenu under WPSSTM
    function backend_playlists_submenu($parent_slug){

        //capability check
        $post_type_slug = wpsstm()->post_type_playlist;
        $post_type_obj = get_post_type_object($post_type_slug);

         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );

    }

    function register_favtracks_playlists_view($views){

        $screen = get_current_screen();

        $link = add_query_arg(
          array(
            'post_type'=>wpsstm()->post_type_playlist,
            'favtrack-playlists'=>true
          ),
          admin_url('edit.php')
        );

        $attr = array(
            'href' =>   $link,
        );

        $attr['class'] = get_query_var( 'favtrack-playlists' ) ? 'current' : null;

        $count = count(WPSSTM_Core_User::get_sitewide_favtracks_playlist_ids());

        $views['favtracks-playlists'] = sprintf('<a %s>%s <span class="count">(%d)</span></a>',wpsstm_get_html_attr($attr),__('Favorite Tracks','wpsstm'),$count);

        return $views;
    }

    function pre_get_posts_favtracks_playlists( $query ) {

      if ( !$query->get( 'favtrack-playlists' ) ) return $query;

      if ( $ids = WPSSTM_Core_User::get_sitewide_favtracks_playlist_ids() ){
          $query->set ( 'post__in', $ids );
      }else{
          $query->set ( 'post__in', array(0) ); //force no results
      }
      return $query;
    }

    function ajax_update_now_playing(){

      $ajax_data = wp_unslash($_POST);

      $track = new WPSSTM_Track();
      $track->from_array($ajax_data['track']);

      $result = array(
        'input'         => $ajax_data,
        'timestamp'     => current_time('timestamp'),
        'error_code'    => null,
        'message'       => null,
        'track'         => $track,
        'success'       => false,
      );

      $success = $this->insert_now_playing($track);

      if ( is_wp_error($success) ){
        $result['error_code'] = $success->get_error_code();
        $result['message'] = $success->get_error_message();
      }else{
        $result['success'] = $success;
      }

      header('Content-type: application/json');
      wp_send_json( $result );

    }

    private function insert_now_playing(WPSSTM_Track $track){

      //get playlist
      $nowplaying_id = wpsstm()->get_options('nowplaying_id');
      if (!$nowplaying_id) return new WP_Error('missing_nowplaying_id','Missing Now Playing Playlist ID');
      $tracklist = new WPSSTM_Post_Tracklist($nowplaying_id);

      //clear
      $clear = $this->clear_now_playing();

      //update
      $now_track = clone $track;
      $now_track->subtrack_id = null; //reset subtrack
      $now_track->subtrack_author = get_current_user_id();

      return $tracklist->insert_subtrack($now_track);
    }

    private function clear_now_playing(){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        //get playlist
        $nowplaying_id = wpsstm()->get_options('nowplaying_id');
        if (!$nowplaying_id) return new WP_Error('missing_nowplaying_id','Missing Now Playing Radio ID');

        if ( !$delay = wpsstm()->get_options('play_history_timeout') ) return false;

        $now = current_time('timestamp');
        $limit = $now - $delay;
        $limit_datetime = date("Y-m-d H:i:s", $limit);

        $query = $wpdb->prepare(" DELETE FROM `$subtracks_table` WHERE tracklist_id = %s AND subtrack_time < %s",$nowplaying_id,$limit_datetime);
        //WP_SoundSystem::debug_log($query,"clear now playing");
        return $wpdb->query($query);

    }

}

function wpsstm_playlists_init(){
    new WPSSTM_Core_Playlists();
}

add_action('plugins_loaded','wpsstm_playlists_init');
