<?php

class WP_SoundSystem_Core_Tracks{

    public $title_metakey = '_wpsstm_track';
    public $image_url_metakey = '_wpsstm_track_image_url';
    public $qvar_track_action = 'track-action';
    public $qvar_track_lookup = 'lookup_track';
    public $qvar_user_favorites = 'user-favorites';
    public $track_mbtype = 'recording'; //musicbrainz type, for lookups
    
    public $subtracks_hide = true; //default hide subtracks in track listings
    public $favorited_track_meta_key = '_wpsstm_user_favorite';

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Tracks;
                    self::$instance->init();
            }
            return self::$instance;
    }
    
    private function __construct() { /* Do nothing here */ }
    
    function init(){
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
    }
    
    function setup_globals(){
        
        if ( isset($_REQUEST['wpsstm_subtracks_hide']) ){
            $this->subtracks_hide = ($_REQUEST['wpsstm_subtracks_hide'] == 'on') ? true : false;
        }elseif ( $subtracks_hide_db = get_option('wpsstm_subtracks_hide') ){
            $this->subtracks_hide = ($subtracks_hide_db == 'on') ? true : false;
        }
    }

    function setup_actions(){

        add_action( 'init', array($this,'register_post_type_track' ));
        add_filter( 'query_vars', array($this,'add_query_vars_track') );

        add_action( 'template_redirect', array($this,'handle_track_action'));
        add_action( 'template_redirect', array($this,'handle_track_popup_form'));
        add_filter( 'template_include', array($this,'new_track_redirect'));
        add_filter( 'template_include', array($this,'track_popup_template'));

        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracks_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracks_scripts_styles_shared' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracks_scripts_styles_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tracks_scripts_styles_backend' ) );
        
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_tracks_submenu' ) );
        
        

        add_action( 'add_meta_boxes', array($this, 'metabox_track_register'));
        add_action( 'save_post', array($this,'metabox_track_title_save'), 5);
        
        add_filter('manage_posts_columns', array($this,'tracks_column_lovedby_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'tracks_column_lovedby_content'), 10, 2 );
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track), array(wpsstm(),'register_community_view') );

        //tracklist shortcode
        add_shortcode( 'wpsstm-track',  array($this, 'shortcode_track'));
        
        //subtracks queries
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_by_track_title') );
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_user_favorites') );
        //TO FIX add filters to exclude tracks if 'exclude_subtracks' query var is set
        add_filter( 'posts_join', array($this,'subtracks_join_query'), 10, 2 );
        add_filter( 'posts_where', array($this,'subtracks_where_query'), 10, 2 );
        add_filter( 'posts_orderby', array($this,'sort_subtracks_by_position'), 10, 2 );
        
        /*
        add_action( 'admin_notices',  array($this, 'toggle_subtracks_notice') );
        add_action( 'current_screen',  array($this, 'toggle_subtracks_store_option') );
        add_filter( 'pre_get_posts', array($this,'default_exclude_subtracks') );
        */
        
        //delete sources when post is deleted
        add_action( 'wp_trash_post', array($this,'trash_track_sources') );
        
        add_filter( 'the_title', array($this, 'the_track_post_title'), 9, 2 );
        
        /*
        AJAX
        */

        add_action('wp_ajax_wpsstm_toggle_favorite_track', array($this,'ajax_toggle_favorite_track'));
        add_action('wp_ajax_nopriv_wpsstm_toggle_favorite_track', array($this,'ajax_toggle_favorite_track')); //so we can output the non-logged user notice
        
        add_action('wp_ajax_wpsstm_set_track_position', array($this,'ajax_set_track_position'));
        add_action('wp_ajax_wpsstm_trash_track', array($this,'ajax_trash_track'));

        //add/remove tracklist track
        add_action('wp_ajax_wpsstm_toggle_playlist_subtrack', array($this,'ajax_toggle_playlist_subtrack'));
        
        add_action('wp_ajax_wpsstm_update_track_sources_order', array($this,'ajax_update_sources_order'));

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
        wp_register_script( 'wpsstm-tracks', wpsstm()->plugin_url . '_inc/js/wpsstm-tracks.js', array('jquery','jquery-ui-tabs','wpsstm-track-sources'),wpsstm()->version );
        
    }
    
    function enqueue_tracks_scripts_styles_frontend(){
        //TO FIX load only when single track is displayed ? but anyway is loaded through wpsstm-tracklists ?
        wp_enqueue_script( 'wpsstm-tracks' );
        
    }

    function enqueue_tracks_scripts_styles_backend(){
        
        if ( !wpsstm()->is_admin_page() ) return;
        
        wp_enqueue_script( 'wpsstm-tracks' );

    }
    
    /**
    *    From http://codex.wordpress.org/Template_Hierarchy
    *
    *    Adds a custom template to the query queue.
    */
    
    function track_popup_template($template){
        global $wp_query;
        global $post;
        global $wpsstm_track;

        $post_type = get_post_type($post);
        $track_action = get_query_var( $this->qvar_track_action );
        if ( $track_action != 'popup' ) return $template;
        
        $wpsstm_track = new WP_SoundSystem_Track( get_the_ID() );

        if ( $template = wpsstm_locate_template( 'track-popup.php' ) ){
            add_filter( 'body_class', array($this,'track_popup_body_classes'));
        }
        
        return $template;
    }
    
    /*
    when requesting wpsstm_track/?new-track=...&QUERYSTR=
    create the track and redirect to wpsstm_track/XXX/?QUERYSTR=
    */
    
    function new_track_redirect($template){
        
        $redirect_url = null;
        
        $track_action =  get_query_var( $this->qvar_track_action );
        if ( $track_action != 'new-track' ) return $template;

        //get current query
        $query_str = $_SERVER['QUERY_STRING']; 
        parse_str($query_str,$query); //make an array of it
        $redirect_args = isset($query['wpsstm-redirect']) ? $query['wpsstm-redirect'] : null;

        $track_args = isset($query['track']) ? $query['track'] : null;
        $track_args = wp_unslash($track_args);
        $track_args = json_decode($track_args);
        
        $track = new WP_SoundSystem_Track();
        $track->from_array($track_args);

        if ( $track->post_id ){
            $track_url = get_permalink($track->post_id);
            $track_url = add_query_arg( array('wpsstm_success_code'=>'track-exists'),$track_url );
        }else{
            $success = $track->save_track();
            if ( is_wp_error($success) ){
                $track_url = get_post_type_archive_link( wpsstm()->post_type_track ); //TO FIX TO CHECK or current URL ? more logical.
                $track_url = add_query_arg(array('wpsstm_error_code'=>$success->get_error_code()),$track_url);
            }else{
                $track_url = get_permalink($track->post_id);
                $track_url = add_query_arg( array('wpsstm_success_code'=>'new-track'),$track_url );
            }
        }

        //pass the redirect args now
        $track_url = add_query_arg($redirect_args,$track_url);

        wp_redirect($track_url);
        exit;
    }
    
    function track_popup_body_classes($classes){
        $classes[] = 'wpsstm-track-popup wpsstm-popup';
        return $classes;
    }
    
    function handle_track_action(){
        global $post;
        if (!$post) return;
        
        if( !$action = get_query_var( $this->qvar_track_action ) ) return;
        
        $track = new WP_SoundSystem_Track($post->ID);
        $success = null;

        switch($action){
            case 'popup':
                //see track_popup_template
            break;
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

            wp_redirect($redirect_url);
            exit();
        }

    }
    
    function handle_track_popup_form(){
        global $post;
        global $wp_query;

        $post_type = get_post_type();
        if ( $post_type != wpsstm()->post_type_track ) return;
        
        $track = new WP_SoundSystem_Track($post->ID);
        $popup_action = ( isset($_POST['wpsstm-track-popup-action']) ) ? $_POST['wpsstm-track-popup-action'] : null;
        if ( !$popup_action ) return;
        
        $redirect_url = $track->get_track_popup_url($popup_action);

        switch($popup_action){

            case 'edit':
                
                //nonce check
                if ( !isset($_POST['wpsstm_track_edit_nonce']) || !wp_verify_nonce($_POST['wpsstm_track_edit_nonce'], sprintf('wpsstm_track_edit_nonce',$wpsstm_track->post_id) ) ) {
                    wpsstm()->debug_log(array('track_id'=>$track->post_id,'track_gui_action'=>$popup_action),"invalid nonce"); 
                    break;
                }

                $track->artist = ( isset($_POST[ 'wpsstm_track_artist' ]) ) ? $_POST[ 'wpsstm_track_artist' ] : null;
                $track->title = ( isset($_POST[ 'wpsstm_track_title' ]) ) ? $_POST[ 'wpsstm_track_title' ] : null;
                $track->album = ( isset($_POST[ 'wpsstm_track_album' ]) ) ? $_POST[ 'wpsstm_track_album' ] : null;
                $track->mbid = ( isset($_POST[ 'wpsstm_track_mbid' ]) ) ? $_POST[ 'wpsstm_track_mbid' ] : null;

                $track->save_track();
                
            break;
            case 'sources-manager':

                //nonce check
                if ( !isset($_POST['wpsstm_track_new_source_nonce']) || !wp_verify_nonce($_POST['wpsstm_track_new_source_nonce'], sprintf('wpsstm_track_%s_new_source_nonce',$track->post_id) ) ) {
                    wpsstm()->debug_log(array('track_id'=>$track->post_id,'track_gui_action'=>$popup_action),"invalid nonce"); 
                    break;
                }
                
                $data = isset($_POST['wpsstm_sources']) ? $_POST['wpsstm_sources'] : null;
                $track_id = isset($_POST['wpsstm-track-id']) ? $_POST['wpsstm-track-id'] : null;
                $track = new WP_SoundSystem_Track($track_id);
                $source_action = isset($data['action']) ? $data['action'] : null;
                
                //new source
                if ( isset($source_action['new-source']) ){
                    $source = new WP_SoundSystem_Source();
                    $source_args = array(
                        'url'   =>      isset($data['source-url']) ? $data['source-url'] : null,
                        'track_id' =>   $track_id,
                    );
                    $source->from_array( $source_args );
                    $source_id = $source->add_source();

                    if ( is_wp_error($source_id) ){
                        $redirect_url = add_query_arg( array('wpsstm_error_code'=>$source_id->get_error_code()),$redirect_url );
                    }else{
                        $redirect_url = add_query_arg( array('wpsstm_success_code'=>'new-source'),$redirect_url );
                    }

                    wp_redirect($redirect_url);
                    exit();
                }
                
                //suggest sources
                if ( isset($source_action['autosource']) ){
                    
                    $success = $track->autosource();

                    if ( is_wp_error($success) ){
                        $redirect_url = add_query_arg( array('wpsstm_error_code'=>$success->get_error_code()),$redirect_url );
                    }else{
                        $redirect_url = add_query_arg( array('wpsstm_success_code'=>'autosource'),$redirect_url );
                    }

                    wp_redirect($redirect_url);
                    exit();
                }
                
                //view backend
                if ( isset($source_action['backend']) ){
                    $redirect_url = $track->get_backend_sources_url();
                    wp_redirect($redirect_url);
                    exit();
                }
                
                
            break;
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
    
    function tracks_column_lovedby_register($defaults) {
        global $post;

        $allowed_post_types = array(
            wpsstm()->post_type_track
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$allowed_post_types) ){
            $after['track-lovedby'] = __('Loved by:','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }
    

    
    function tracks_column_lovedby_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
            case 'track-lovedby':
                $output = '—';
                $track = new WP_SoundSystem_Track($post_id);
                if ( $list = $track->get_loved_by_list() ){
                    $output = $list;
                }
                echo $output;
            break;
        }
    }
    
    function pre_get_posts_user_favorites( $query ) {
        
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;

        if ( $user_id = $query->get( $this->qvar_user_favorites ) ){

            $meta_query = (array)$query->get('meta_query');

            $meta_query[] = array(
                'key'     => $this->favorited_track_meta_key,
                'value'   => $user_id,
            );

            $query->set( 'meta_query', $meta_query);
            
        }

        return $query;
    }

    function pre_get_posts_by_track_title( $query ) {
        
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;

        if ( $track = $query->get( $this->qvar_track_lookup ) ){

            $query->set( 'meta_query', array(
                array(
                     'key'     => $this->title_metakey,
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
            $subtracks_table_name = $wpdb->prefix . wpsstm()->subtracks_table_name;
            $join .= sprintf("INNER JOIN %s AS subtracks ON (%s.ID = subtracks.track_id)",$subtracks_table_name,$wpdb->posts);
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
        $qvars[] = $this->qvar_track_lookup;
        $qvars[] = $this->qvar_track_action;
        return $qvars;
    }
    
    function metabox_track_register(){
        
        $metabox_post_types = array(
            wpsstm()->post_type_track
        );

        add_meta_box( 
            'wpsstm-track', 
            __('Track','wpsstm'),
            array($this,'metabox_track_content'),
            $metabox_post_types, 
            'after_title', 
            'high' 
        );
        
    }
    
    function metabox_track_content( $post ){

        $track_title = get_post_meta( $post->ID, $this->title_metakey, true );
        
        ?>
        <input type="text" name="wpsstm_track" class="wpsstm-fullwidth" value="<?php echo $track_title;?>" placeholder="<?php printf("Enter track title here",'wpsstm');?>"/>
        <?php
        wp_nonce_field( 'wpsstm_track_meta_box', 'wpsstm_track_meta_box_nonce' );

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
    
    function metabox_track_title_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_track_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_track);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_track_meta_box_nonce'], 'wpsstm_track_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_track_meta_box_nonce']);

        $track = ( isset($_POST[ 'wpsstm_track' ]) ) ? $_POST[ 'wpsstm_track' ] : null;

        if (!$track){
            delete_post_meta( $post_id, $this->title_metakey );
        }else{
            update_post_meta( $post_id, $this->title_metakey, $track );
        }

    }
    
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
            $wpsstm_tracklist = wpsstm_get_post_tracklist($atts['post_id']);
            $output = $wpsstm_tracklist->get_tracklist_html();
            wp_reset_postdata();
        }

        return $output;

    }

    
    function ajax_toggle_playlist_subtrack(){
        
        $ajax_data = wp_unslash($_POST);
        
        wpsstm()->debug_log($ajax_data,"ajax_toggle_playlist_subtrack"); 

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );
        
        $track_id = isset($ajax_data['track_id']) ? $ajax_data['track_id'] : null;
        $track = $result['track'] = new WP_SoundSystem_Track($track_id);
        
        $tracklist_id  = isset($ajax_data['tracklist_id']) ? $ajax_data['tracklist_id'] : null;
        $tracklist = $result['tracklist'] = new WP_SoundSystem_Tracklist($tracklist_id);
        
        $track_action = isset($ajax_data['track_action']) ? $ajax_data['track_action'] : null;
        $success = false;

        if ($track_id && $tracklist->post_id && $track_action){

            switch($track_action){
                case 'append':
                    $success = $tracklist->append_subtrack_ids($track->post_id);
                break;
                case 'remove':
                    $success = $tracklist->remove_subtrack_ids($track->post_id);
                break;
            }
            
        }
        
        if ( is_wp_error($success) ){
            $code = $success->get_error_code();
            $result['message'] = $success->get_error_message($code);
        }else{
            $result['success'] = $success;
        }

   
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_update_sources_order(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );
        
        $track_id = isset($ajax_data['track_id']) ? $ajax_data['track_id'] : null;
        $track = $result['track'] = new WP_SoundSystem_Track($track_id);
        
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

    
    function ajax_toggle_favorite_track(){

        $ajax_data = wp_unslash($_POST);

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );
        
        $do_love = $result['do_love'] = ( isset($ajax_data['do_love']) ) ? filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN) : null; //ajax do send strings
        $track = new WP_SoundSystem_Track();
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
                $result['track'] = $track;
                wpsstm()->debug_log( json_encode($track,JSON_UNESCAPED_UNICODE), "ajax_toggle_favorite_track()"); 

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
    
    function ajax_set_track_position(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );
        
        $result['tracklist_id']  =  $tracklist_id =     ( isset($ajax_data['tracklist_id']) ) ? $ajax_data['tracklist_id'] : null;
        $tracklist = wpsstm_get_post_tracklist($tracklist_id);
        
        $track = new WP_SoundSystem_Track();
        $track->from_array($ajax_data['track']);
        $result['track'] = $track;

        if ( $tracklist->post_id && $track->post_id && ($track->index != -1) ){
            $success = $tracklist->save_track_position($track->post_id,$track->index);
            
            if ( is_wp_error($success) ){
                $result['message'] = $success->get_error_message();
            }else{
                $result['success'] = $success;
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

        $track = new WP_SoundSystem_Track();
        $track->from_array($ajax_data['track']);

        $success = $track->trash_track();
        $result['track'] = $track;

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
        $track = new WP_SoundSystem_Track($post_id);
        
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
            wpsstm()->debug_log(json_encode(array('post_id'=>$post_id,'sources'=>$sources_query->post_count,'trashed'=>$trashed)),"WP_SoundSystem_Tracklist::trash_track_sources()");
        }

    }
    
    //TO FIX have a query that directly selects the flushable tracks without having to populate them all ?
    //would be much faster.
    function get_flushable_track_ids(){
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
            $track = new WP_SoundSystem_Track($track_id);
            if ( $track->can_be_flushed() ){
                $flushable_ids[] = $track->post_id;
            }
            
        }
        
        return $flushable_ids;
        
    }
    
    /*
    Flush community tracks
    */
    function flush_community_tracks(){

        $flushed_ids = array();
        
        if ( $flushable_ids = $this->get_flushable_track_ids() ){

            foreach( (array)$flushable_ids as $track_id ){
                $track = new WP_SoundSystem_Track($track_id);
                $success = $track->trash_track();
                if ( !is_wp_error($success) ) $flushed_ids[] = $track->post_id;
            }
        }

        wpsstm()->debug_log(json_encode(array('flushable'=>count($flushable_ids),'flushed'=>count($flushed_ids))),"WP_SoundSystem_Tracklist::flush_community_tracks()");

        return $flushed_ids;

    }
    
    function the_track_post_title($title,$post_id){

        //post type check
        $post_type = get_post_type($post_id);
        if ( $post_type !== wpsstm()->post_type_track ) return $title;

        $title = get_post_meta( $post_id, $this->title_metakey, true );
        $artist = get_post_meta( $post_id, wpsstm_artists()->artist_metakey, true );
        
        return sprintf('"%s" - %s',$title,$artist);
    }
    
}

function wpsstm_tracks() {
	return WP_SoundSystem_Core_Tracks::instance();
}

wpsstm_tracks();