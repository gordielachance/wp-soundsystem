<?php

class WPSSTM_Core_Tracks{

    static $title_metakey = '_wpsstm_track';
    static $length_metakey = '_wpsstm_length_ms';
    static $image_url_metakey = '_wpsstm_track_image_url';
    static $qvar_track_action = 'track-action';
    static $qvar_track_lookup = 'lookup_track';
    static $qvar_loved_tracks = 'loved-tracks';
    static $loved_track_meta_key = '_wpsstm_user_favorite';
    
    var $subtracks_hide = null; //default hide subtracks in track listings

    function __construct() {
        global $wpsstm_track;
        
        //initialize global (blank) $wpsstm_track so plugin never breaks when calling it.
        $wpsstm_track = new WPSSTM_Track();
        
        if ( isset($_REQUEST['wpsstm_subtracks_hide']) ){
            $this->subtracks_hide = ($_REQUEST['wpsstm_subtracks_hide'] == 'on') ? true : false;
        }elseif ( $subtracks_hide_db = get_option('wpsstm_subtracks_hide') ){
            $this->subtracks_hide = ($subtracks_hide_db == 'on') ? true : false;
        }

        add_action( 'init', array($this,'register_post_type_track' ));
        add_filter( 'query_vars', array($this,'add_query_vars_track') );

        add_action( 'template_redirect', array($this,'handle_track_action'));
        
        //add_action( 'wp_print_styles', array($this,'track_template_no_css'), 99 );//TOUFIX
        add_filter( 'template_include', array($this,'track_template'));

        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracks_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracks_scripts_styles_shared' ) );
        
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_tracks_submenu' ) );

        add_action( 'add_meta_boxes', array($this, 'metabox_track_register'));
        add_action( 'save_post', array($this,'metabox_save_track_settings'), 5);
        
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_track), array(__class__,'tracks_columns_register') );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_track), array(__class__,'tracks_columns_content') );
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track), array(wpsstm(),'register_community_view') );

        //tracklist shortcode
        add_shortcode( 'wpsstm-track',  array($this, 'shortcode_track'));
        
        /*
        QUERIES
        */
        
        add_action( 'the_post', array($this,'the_track'),10,2);
        add_action( 'current_screen',  array($this, 'the_single_backend_track'));
        
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_by_track_title') );
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_loved_tracks') );
        //TO FIX add filters to exclude tracks if 'exclude_subtracks' query var is set
        add_filter( 'posts_join', array($this,'subtracks_join_query'), 10, 2 );
        add_filter( 'posts_where', array($this,'subtracks_where_query'), 10, 2 );
        add_filter( 'posts_orderby', array($this,'sort_subtracks_by_position'), 10, 2 );
        
        /*
        add_action( 'admin_notices',  array($this, 'toggle_subtracks_notice') );
        add_action( 'current_screen',  array($this, 'toggle_subtracks_store_option') );
        add_filter( 'pre_get_posts', array($this,'default_exclude_subtracks') );
        */

        add_filter( 'the_title', array($this, 'the_track_post_title'), 9, 2 );
        
        /*
        AJAX
        */
        
        add_action('wp_ajax_wpsstm_track_autosource', array($this,'ajax_track_autosource'));
        add_action('wp_ajax_nopriv_wpsstm_track_autosource', array($this,'ajax_track_autosource'));
        //add_action('wp', array($this,'test_autosource_ajax') );

        add_action('wp_ajax_wpsstm_toggle_favorite_track', array($this,'ajax_toggle_favorite_track'));
        add_action('wp_ajax_nopriv_wpsstm_toggle_favorite_track', array($this,'ajax_toggle_favorite_track')); //so we can output the non-logged user notice
        
        add_action('wp_ajax_wpsstm_trash_track', array($this,'ajax_trash_track'));
        add_action('wp_ajax_wpsstm_update_track_sources_order', array($this,'ajax_update_sources_order'));

        
        /*
        DB relationships
        */
        add_action( 'save_post', array($this,'set_subtrack_post_id'), 6);
        add_action( 'before_delete_post', array($this,'delete_subtrack_track_id') );
        add_action( 'wp_trash_post', array($this,'trash_track_sources') );
    }

    //add custom admin submenu under WPSSTM
    function backend_tracks_submenu($parent_slug){

        //capability check
        $post_type_slug = wpsstm()->post_type_track;
        $post_type_obj = get_post_type_object($post_type_slug);
        
         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );
        
    }

    
    function register_tracks_scripts_styles_shared(){
        //JS
        wp_register_script( 'wpsstm-tracks', wpsstm()->plugin_url . '_inc/js/wpsstm-tracks.js', array('jquery','jquery-ui-tabs','wpsstm-sources'),wpsstm()->version, true );
        
    }

    function track_template_no_css() {
        global $wp_styles;
        //if ( is_page_template( 'blankPage.php' ) ) {
            $wp_styles->queue = array();
        //}
    }
    
    function track_template($template){
        if ( !$track_action = get_query_var( self::$qvar_track_action ) ) return $template;
        the_post();
        $template = wpsstm_locate_template( 'track.php' );
        return $template;
    }

    function handle_track_action(){
        global $post;
        if (!$post) return;
        
        if( !$action = get_query_var( self::$qvar_track_action ) ) return;
        
        $track = new WPSSTM_Track($post->ID);
        $success = null;

        switch($action){
            case 'favorite':
                $success = $track->love_track(true);
            break;
            case 'unfavorite':
                $success = $track->love_track(false);
            break;
        }
        
        if ($success){ //redirect with a success / error code
            $redirect_url = ( wpsstm_is_backend() ) ? get_edit_post_link( $track->post_id ) : get_permalink($track->post_id);

            if ( is_wp_error($success) ){
                $redirect_url = add_query_arg( array('wpsstm_error_code'=>$success->get_error_code()),$redirect_url );
            }else{
                $redirect_url = add_query_arg( array('wpsstm_success_code'=>$action),$redirect_url );
            }

            wp_safe_redirect($redirect_url);
            exit();
        }

    }
    
    /*
    Display a notice (and link) to toggle view subtracks
    */
    
    function toggle_subtracks_notice(){
        
        $screen = get_current_screen();

        if ( $screen->post_type != wpsstm()->post_type_track ) return;
        if ( $screen->base != 'edit' ) return;
        
        $toggle_value = ($this->subtracks_hide) ? 'off' : 'on';
        
        $link = admin_url('edit.php');
        $post_status = ( isset($_REQUEST['post_status']) ) ? $_REQUEST['post_status'] : null;
        
        if ( $post_status ){
            $link = add_query_arg(array('post_status'=>$post_status),$link);
        }
        
        $link = add_query_arg(array('post_type'=>wpsstm()->post_type_track,'wpsstm_subtracks_hide'=>$toggle_value),$link);

        $notice_link = sprintf( '<a href="%s">%s</a>',$link,__('here','wpsstm') );
        
        $notice = null;
        
        if ($this->subtracks_hide){
            $notice = sprintf(__('Click %s if you want to include subtracks (tracks belonging to albums or (live) playlists) in this listing.','wpsstm'),$notice_link);
        }else{
            $notice = sprintf(__('Click %s if you want to exclude subtracks (tracks belonging to albums or (live) playlists) from this listing.','wpsstm'),$notice_link);
        }

        printf('<div class="notice notice-warning"><p>%s</p></div>',$notice);

    }
    
    /*
    Toggle view subtracks : store option then redirect
    */
    
    function toggle_subtracks_store_option($screen){
        
        if ( $screen->post_type != wpsstm()->post_type_track ) return;
        if ( $screen->base != 'edit' ) return;
        if ( !isset($_REQUEST['wpsstm_subtracks_hide']) ) return;
        
        $value = $_REQUEST['wpsstm_subtracks_hide'];

        update_option( 'wpsstm_subtracks_hide', $value );
        
        $this->subtracks_hide = ($value == 'on') ? true : false;

    }
    
    //TO FIX caution with this, will exclude tracks backend to.
    //We should find a way to run it only for backend listings.
    function default_exclude_subtracks( $query ) {

        //only for main query
        if ( !$query->is_main_query() ) return $query;
        
        //only for tracks
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;
        
        //already defined
        if ( $query->get('exclude_subtracks') ) return $query;
        
        //option enabled ?
        if ($this->subtracks_hide){
            $query->set('exclude_subtracks',true); //set to false
        }

        return $query;
    }

    static public function tracks_columns_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['sources'] = __('Sources','wpsstm');
        $after['track-playlists'] = __('Playlists','wpsstm');
        $after['track-lovedby'] = __('Favorited','wpsstm');
        
        return array_merge($before,$defaults,$after);
    }
    

    
    static public function tracks_columns_content($column){
        global $post;
        global $wpsstm_track;
        
        switch ( $column ) {
            case 'track-playlists':
                
                if ( $list = $wpsstm_track->get_parents_list() ){
                    echo $list;
                }else{
                    echo '—';
                }

                
            break;
            case 'track-lovedby':
                $output = '—';
                
                if ( $list = $wpsstm_track->get_loved_by_list() ){
                    $output = $list;
                }
                echo $output;
            break;
            case 'sources':
                
                $published_str = $pending_str = null;

                $sources_published_query = $wpsstm_track->query_sources();
                $sources_pending_query = $wpsstm_track->query_sources(array('post_status'=>'pending'));

                $url = admin_url('edit.php');
                $url = add_query_arg( array('post_type'=>wpsstm()->post_type_source,'post_parent'=>$wpsstm_track->post_id,'post_status'=>'publish'),$url );
                $published_str = sprintf('<a href="%s">%d</a>',$url,$sources_published_query->post_count);
                
                if ($sources_pending_query->post_count){
                    $url = admin_url('edit.php');
                    $url = add_query_arg( array('post_type'=>wpsstm()->post_type_source,'post_parent'=>$wpsstm_track->post_id,'post_status'=>'pending'),$url );
                    $pending_link = sprintf('<a href="%s">%d</a>',$url,$sources_pending_query->post_count);
                    $pending_str = sprintf('<small> +%s</small>',$pending_link);
                }
                
                echo $published_str . $pending_str;
                
            break;
        }
    }
    
    function pre_get_posts_loved_tracks( $query ) {
        
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;

        if ( $user_id = $query->get( self::$qvar_loved_tracks ) ){

            $meta_query = (array)$query->get('meta_query');

            $meta_query[] = array(
                'key'     => self::$loved_track_meta_key,
                'value'   => $user_id,
            );

            $query->set( 'meta_query', $meta_query);
            
        }

        return $query;
    }
    
    /*
    Register the global $wpsstm_tracklist and  $wpsstm_track global objects (hooked on 'the_post' action)
    */
    
    function the_track($post,$query){
        global $wpsstm_track;
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return;
        $wpsstm_track = new WPSSTM_Track( $post->ID );
    }
    
    function the_single_backend_track(){
        global $post;
        global $wpsstm_track;
        $screen = get_current_screen();
        if ( ( $screen->base == 'post' ) && ( $screen->post_type == wpsstm()->post_type_track )  ){
            $post_id = isset($_GET['post']) ? $_GET['post'] : null;
            $wpsstm_track = new WPSSTM_Track( $post_id );
        }
    }

    function pre_get_posts_by_track_title( $query ) {
        
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;

        if ( $track = $query->get( self::$qvar_track_lookup ) ){

            $query->set( 'meta_query', array(
                array(
                     'key'     => self::$title_metakey,
                     'value'   => $track,
                     'compare' => '='
                )
            ));
        }

        return $query;
    }

    function subtracks_join_query($join,$query){
        global $wpdb;
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $join;
        if ( $query->get('tracklist_id') ){
            $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
            $join .= sprintf("INNER JOIN %s AS subtracks ON (%s.ID = subtracks.track_id)",$subtracks_table,$wpdb->posts);
        }
        return $join;
    }
    
    function subtracks_where_query($where,$query){
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $where;
        if ( $tracklist_id = $query->get('tracklist_id') ){
            $where .= sprintf(" AND subtracks.tracklist_id = %s",$tracklist_id);
        }
        return $where;
    }
    
    /*
    By default, Wordpress will sort the subtracks by date.
    If we have a subtracks query with a tracklist ID set; and that no orderby is defined, rather sort by tracklist position.
    */
    
    function sort_subtracks_by_position($orderby_sql, $query){

        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $orderby_sql;
        
        $query_orderby = $query->get('orderby') ? $query->get('orderby') : 'track_order';

        if ( $query->get('tracklist_id') && ( $query_orderby == 'track_order') ){
            $orderby_sql = 'subtracks.track_order ' . $query->get('order');
        }
        
        return $orderby_sql;

    }
    

    function register_post_type_track() {

        $labels = array(
            'name'                  => _x( 'Tracks', 'Tracks General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Track', 'Track Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Tracks', 'wpsstm' ),
            'name_admin_bar'        => __( 'Track', 'wpsstm' ),
            'archives'              => __( 'Track Archives', 'wpsstm' ),
            'attributes'            => __( 'Track Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Track:', 'wpsstm' ),
            'all_items'             => __( 'All Tracks', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Track', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Track', 'wpsstm' ),
            'edit_item'             => __( 'Edit Track', 'wpsstm' ),
            'update_item'           => __( 'Update Track', 'wpsstm' ),
            'view_item'             => __( 'View Track', 'wpsstm' ),
            'view_items'            => __( 'View Tracks', 'wpsstm' ),
            'search_items'          => __( 'Search Tracks', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            'insert_into_item'      => __( 'Insert into track', 'wpsstm' ),
            'uploaded_to_this_item' => __( 'Uploaded to this track', 'wpsstm' ),
            'items_list'            => __( 'Tracks list', 'wpsstm' ),
            'items_list_navigation' => __( 'Tracks list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter tracks list', 'wpsstm' ),
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
            'rewrite' => true,
            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You 
             * can use a string or an array (for singular and plural forms).  The array is useful if the 
             * plural form can't be made by simply adding an 's' to the end of the word.  For example, 
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'track', // string|array (defaults to 'post')

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
                'edit_post'              => 'edit_track',
                'read_post'              => 'read_track',
                'delete_post'            => 'delete_track',

                // primitive/meta caps
                'create_posts'           => 'create_tracks',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_tracks',
                'edit_others_posts'      => 'manage_tracks',
                'publish_posts'          => 'manage_tracks',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_tracks',
                'delete_private_posts'   => 'manage_tracks',
                'delete_published_posts' => 'manage_tracks',
                'delete_others_posts'    => 'manage_tracks',
                'edit_private_posts'     => 'edit_tracks',
                'edit_published_posts'   => 'edit_tracks'
            )
        );

        register_post_type( wpsstm()->post_type_track, $args );
    }
    
    function add_query_vars_track( $qvars ) {
        $qvars[] = self::$qvar_track_lookup;
        $qvars[] = self::$qvar_track_action;
        $qvars[] = self::$qvar_loved_tracks;
        return $qvars;
    }
    
    function metabox_track_register(){

        add_meta_box( 
            'wpsstm-track-settings', 
            __('Track Settings','wpsstm'),
            array($this,'metabox_track_settings_content'),
            wpsstm()->post_type_track, 
            'after_title', 
            'high' 
        );

        add_meta_box( 
            'wpsstm-track-playlists', 
            __('Playlists','wpsstm'),
            array($this,'metabox_track_playlists_content'),
            wpsstm()->post_type_track, 
            'side', //context
            'default' //priority
        );

    }
    
    function metabox_track_settings_content( $post ){

        echo self::get_edit_track_title_input($post->ID);
        echo WPSSTM_Core_Artists::get_edit_artist_input($post->ID);
        echo WPSSTM_Core_Albums::get_edit_album_input($post->ID);
        echo self::get_edit_track_length_input($post->ID);

        wp_nonce_field( 'wpsstm_track_settings_meta_box', 'wpsstm_track_settings_meta_box_nonce' );

    }
    
    static function get_edit_track_title_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;
        $input_attr = array(
            'id' => 'wpsstm-track-title',
            'name' => 'wpsstm_track_title',
            'value' => get_post_meta( $post_id, self::$title_metakey, true ),
            'icon' => '<i class="fa fa-music" aria-hidden="true"></i>',
            'label' => __("Track title",'wpsstm'),
            'placeholder' => __("Enter track title here",'wpsstm')
        );
        return wpsstm_get_backend_form_input($input_attr);
    }
    
    static function get_edit_track_length_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;

        $input_attr = array(
            'id' => 'wpsstm-length',
            'name' => 'wpsstm_length',
            'value' => wpsstm_get_post_length($post_id,true),
            'icon' => '<i class="fa fa-music" aria-hidden="true"></i>',
            'label' => __("Length (seconds)",'wpsstm'),
            'placeholder' => __("Enter length here",'wpsstm')
        );
        return wpsstm_get_backend_form_input($input_attr);
    }
    
    function metabox_track_playlists_content( $post ){
        wpsstm_locate_template( 'track-admin-playlists.php',true );
    }

    function mb_populate_trackid( $post_id ) {
        
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        if ( $is_autosave || $is_autodraft || $is_revision ) return;
        
        //already had an MBID
        //$trackid = wpsstm_get_post_mbid($post_id);
        //if ($trackid) return;

        //requires a title
        $track = wpsstm_get_post_track($post_id);
        if (!$track) return;
        
        //requires an artist
        $artist = wpsstm_get_post_artist($post_id);
        if (!$artist) return;

        
    }
    
    /**
    Save track field for this post
    **/
    
    function metabox_save_track_settings( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_track_settings_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_track);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_track_settings_meta_box_nonce'], 'wpsstm_track_settings_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_track_settings_meta_box_nonce']);

        /*title*/
        $title = ( isset($_POST[ 'wpsstm_track_title' ]) ) ? $_POST[ 'wpsstm_track_title' ] : null;
        self::save_meta_track_title($post_id, $title);
        
        /*artist*/
        $artist = ( isset($_POST[ 'wpsstm_artist' ]) ) ? $_POST[ 'wpsstm_artist' ] : null;
        WPSSTM_Core_Artists::save_meta_artist($post_id, $artist);
        
        /*album*/
        $album = ( isset($_POST[ 'wpsstm_album' ]) ) ? $_POST[ 'wpsstm_album' ] : null;
        WPSSTM_Core_Albums::save_meta_album($post_id, $album);

        /*length*/
        $length = ( isset($_POST[ 'wpsstm_length' ]) ) ? ( $_POST[ 'wpsstm_length' ] * 1000 ) : null; //ms
        self::save_meta_track_length($post_id, $length);

    }
    
    static function save_meta_track_title($post_id, $value = null){
        $value = trim($value);
        if (!$value){
            delete_post_meta( $post_id, self::$title_metakey );
        }else{
            update_post_meta( $post_id, self::$title_metakey, $value );
        }
    }
    
    static function save_meta_track_length($post_id, $value = null){
        $value = trim($value);
        if (!$value){
            delete_post_meta( $post_id, self::$length_metakey );
        }else{
            update_post_meta( $post_id, self::$length_metakey, $value );
        }
    }
    
    //TOUFIX TOUCHECK
    function shortcode_track( $atts ) {
        global $post;
        global $wpsstm_tracklist;
        
        $output = null;

        // Attributes
        $default = array(
            'post_id'       => $post->ID 
        );
        
        $atts = shortcode_atts($default,$atts);
        
        if ( ( $post_type = get_post_type($atts['post_id']) ) && ($post_type == wpsstm()->post_type_track) ){ //check that the post exists
            //single track tracklist
            $wpsstm_tracklist = new WPSSTM_Post_Tracklist();
            $track = new WPSSTM_Track( $atts['post_id'] );
            $wpsstm_tracklist->add_tracks($track);
            $output = $wpsstm_tracklist->get_tracklist_html();
        }

        return $output;

    }
    
    function ajax_update_sources_order(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );
        
        $track_id = isset($ajax_data['track_id']) ? $ajax_data['track_id'] : null;
        $track = new WPSSTM_Track($track_id);
        $result['track'] = $track->to_array();
        
        $source_ids = isset($ajax_data['source_ids']) ? $ajax_data['source_ids'] : null;
        $success = $track->update_sources_order($source_ids);

        if ( is_wp_error($success) ){
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = $success;
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }

    function ajax_track_autosource(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );

        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);

        $result = array(
            'input'     => $ajax_data,
            'timestamp' => current_time('timestamp'),
            'message'   => null,
            'new_html'  => null,
            'success'   => false,
            'track'     => $track->to_array(),
        );
            
        //autosource
        $new_ids = array();
        
        $new_ids = $track->autosource();
        $result['success'] = ( !is_wp_error($new_ids) ) ? true : false;

        if ( is_wp_error($new_ids) ){
            $result['message'] = $new_ids->get_error_message();
        }else{
            $result['new_ids'] = $new_ids;
            $result['success'] = true;
        }
        
        //repopulate track (may have been created and thus have a post_id, etc.)
        //TO FIX TO CHECK maybe it is not necessary to repopulate the track here?
        ob_start();
        wpsstm_locate_template( 'content-track.php', true, false );
        $updated_track = ob_get_clean();
        $result['new_html'] = $updated_track;
        $result['success'] = true;

        header('Content-type: application/json');
        wp_send_json( $result );

    }
    
    function test_autosource_ajax(){
        
        if ( is_admin() ) return;
    
        $_POST = array(
            'track' => array('artist'=>'U2','title'=>'Sunday Bloody Sunday')
        );
        
        wpsstm()->debug_log($_POST,'testing autosource AJAX');
        
        $this->ajax_track_autosource();
    }
    
    function ajax_toggle_favorite_track(){

        $ajax_data = wp_unslash($_POST);

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );
        
        $do_love = $result['do_love'] = ( isset($ajax_data['do_love']) ) ? filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN) : null; //ajax do send strings
        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);
        
        if ( !get_current_user_id() ){
            
            $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
            if ($do_love){
                $action_link = $track->get_track_action_url('favorite');
            }else{
                $action_link = $track->get_track_action_url('unfavorite');
            }
            
            $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($action_link),__('here','wpsstm'));
            $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
            $result['notice'] = sprintf('<p id="wpsstm-dialog-auth-notice">%s</p>',$wp_auth_text);
            
        }else{

            if ( ($do_love!==null) ){

                $success = $track->love_track($do_love);
                $result['track'] = $track->to_array();
                $this->track_log( json_encode($track,JSON_UNESCAPED_UNICODE), "ajax_toggle_favorite_track()"); 

                if( is_wp_error($success) ){
                    $code = $success->get_error_code();
                    $result['message'] = $success->get_error_message($code); 
                }else{
                    $result['success'] = $success; 
                }
            }
            
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_trash_track(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );

        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);

        $success = $track->trash_track();
        $result['track'] = $track->to_array();

        if ( is_wp_error($success) ){
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = $success;
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
        
    function trash_track_sources($post_id){
        
        if ( get_post_type($post_id) != wpsstm()->post_type_track ) return;
        
        //get all sources
        $track = new WPSSTM_Track($post_id);
        
        $source_args = array(
            'posts_per_page' => -1,
            'fields'  =>        'ids',
            'post_status'=>     'any',
        );
        
        $sources_query = $track->query_sources($source_args);
        $trashed = 0;
        
        foreach($sources_query->posts as $source_id){
            if ( $success = wp_trash_post($source_id) ){
                $trashed ++;
            }
        }

        if ($trashed){
            $track->track_log( json_encode(array('post_id'=>$post_id,'sources'=>$sources_query->post_count,'trashed'=>$trashed)),"WPSSTM_Post_Tracklist::trash_track_sources()");
        }

    }
    
    /*
    After track post is updated, check for data occurences in the subtracks table and eventally set the post ID instead
    */
    
    function set_subtrack_post_id( $post_id ) {
        global $wpdb;
        
        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        if ( $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_track);
        if ( $post_type != wpsstm()->post_type_track ) return;
        
        $track = new WPSSTM_Track($post_id);
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        //update subtracks : delete track datas and set post ID

        $where_sql = array(
            'artist'=>  $track->artist,
            'title'=>   $track->title,
            'album'=>   $track->album
        );
        
        $where_sql = array_filter($where_sql);
        
        $success = $wpdb->update( 
            $subtracks_table, //table
            array(
                'track_id'=>$track->post_id,
                'artist'=>null,
                'title'=>null,
                'album'=>null), //data
            $where_sql //where
        );
        
    }
    
    /*
    Just before a track post is removed, remove post its post ID from the subtracks table and replace it by the track artist / title / album
    */

    function delete_subtrack_track_id($post_id){
        global $wpdb;

        if ( get_post_type($post_id) != wpsstm()->post_type_track ) return;

        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $track = new WPSSTM_Track($post_id);
        
        return $wpdb->update( 
            $subtracks_table, //table
            array('track_id'=>'','artist'=>$track->artist,'title'=>$track->title,'album'=>$track->album), //data
            array('track_id'=>$post_id) //where
        );


    }
    
    //TO FIX TO IMPROVE have a query that directly selects the flushable tracks without having to populate them all ?
    //would be much faster.
    static function get_flushable_track_ids(){
        $community_user_id = wpsstm()->get_options('community_user_id');
        if ( !$community_user_id ) return;
        
        $flushable_ids = array();
        
        //get community tracks
        $community_tracks_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'post_author' =>    $community_user_id,
            'post_status' =>    'any',
            'posts_per_page'=>  -1,
            'fields' =>         'ids',
        );
        
        $query = new WP_Query( $community_tracks_args );
        $community_tracks_ids = $query->posts;
        
        foreach( (array)$community_tracks_ids as $track_id ){
            $track = new WPSSTM_Track($track_id);
            if ( $track->can_be_flushed() ){
                $flushable_ids[] = $track->post_id;
            }
            
        }
        
        return $flushable_ids;
        
    }
    
    /*
    Flush community tracks
    */
    static function flush_community_tracks(){

        $flushed_ids = array();
        
        if ( $flushable_ids = self::get_flushable_track_ids() ){

            foreach( (array)$flushable_ids as $track_id ){
                $track = new WPSSTM_Track($track_id);
                $success = $track->trash_track();
                if ( !is_wp_error($success) ) $flushed_ids[] = $track->post_id;
            }
        }

        $this->track_log( json_encode(array('flushable'=>count($flushable_ids),'flushed'=>count($flushed_ids))),"WPSSTM_Post_Tracklist::flush_community_tracks()");

        return $flushed_ids;

    }
    
    function the_track_post_title($title,$post_id){

        //post type check
        $post_type = get_post_type($post_id);
        if ( $post_type !== wpsstm()->post_type_track ) return $title;

        $title = get_post_meta( $post_id, self::$title_metakey, true );
        $artist = get_post_meta( $post_id, WPSSTM_Core_Artists::$artist_metakey, true );
        
        return sprintf('"%s" - %s',$title,$artist);
    }
    
}