<?php

class WP_SoundSystem_Core_Tracks{

    public $title_metakey = '_wpsstm_track';
    public $qvar_track_admin = 'admin';
    public $qvar_new_track = 'new';
    public $qvar_track_lookup = 'lookup_track';
    public $qvar_subtracks_hide = 'hide_subtracks';
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
        
        add_action( 'init', array($this,'register_track_endpoints' ));
        
        add_filter( 'wp', array($this,'add_new_track'));
        add_filter( 'template_include', array($this,'track_admin_template_filter'));
        add_action( 'wp', array($this,'track_save_admin_gui'));
        
        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracks_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracks_scripts_styles_shared' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracks_scripts_styles_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tracks_scripts_styles_backend' ) );
        
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_by_track_title') );
        add_action( 'save_post', array($this,'update_title_track'), 99);

        add_action( 'add_meta_boxes', array($this, 'metabox_track_register'));
        add_action( 'save_post', array($this,'metabox_track_title_save'), 5);
        
        add_filter('manage_posts_columns', array($this,'tracks_column_lovedby_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'tracks_column_lovedby_content'), 10, 2 );

        //tracklist shortcode
        add_shortcode( 'wpsstm-track',  array($this, 'shortcode_track'));
        
        //subtracks
        add_action( 'admin_notices',  array($this, 'toggle_subtracks_notice') );
        add_action( 'current_screen',  array($this, 'toggle_subtracks_store_option') );
        add_filter( 'pre_get_posts', array($this,'default_exclude_subtracks') );
        add_filter( 'pre_get_posts', array($this,'exclude_subtracks') );
        
        //ajax : toggle love track
        add_action('wp_ajax_wpsstm_love_unlove_track', array($this,'ajax_love_unlove_track'));
        
        //ajax : get tracks source auto
        add_action('wp_ajax_wpsstm_sources_auto_lookup', array($this,'ajax_sources_auto_lookup'));
        add_action('wp_ajax_nopriv_wpsstm_sources_auto_lookup', array($this,'ajax_sources_auto_lookup'));

        //ajax : add new tracklist
        add_action('wp_ajax_wpsstm_create_playlist', array($this,'ajax_create_playlist'));
        
        //ajax : add/remove playlist track
        add_action('wp_ajax_wpsstm_add_playlist_track', array($this,'ajax_add_playlist_track'));
        add_action('wp_ajax_wpsstm_remove_playlist_track', array($this,'ajax_remove_playlist_track'));

    }
    
    function register_track_endpoints(){
        // (existing track) admin
        add_rewrite_endpoint($this->qvar_track_admin, EP_PERMALINK ); 
        
        //(new track) admin - wordpress/wpsstm_tracks/new
        $new_track_regex = sprintf('%s/%s$',wpsstm()->post_type_track,$this->qvar_new_track);
        $new_track_redirect_args = array(
            'post_type' =>              wpsstm()->post_type_track,
            $this->qvar_new_track =>    true
        );
        $new_track_redirect = add_query_arg($new_track_redirect_args,'index.php');
        add_rewrite_rule( $new_track_regex, $new_track_redirect, 'top' );
    }
    
    function register_tracks_scripts_styles_shared(){
        //CSS
        wp_register_style( 'wpsstm-tracks', wpsstm()->plugin_url . '_inc/css/wpsstm-tracks.css', array('font-awesome','thickbox','wpsstm-track-sources'),wpsstm()->version );
        //JS
        wp_register_script( 'wpsstm-tracks', wpsstm()->plugin_url . '_inc/js/wpsstm-tracks.js', array('jquery','thickbox','wpsstm-track-sources'),wpsstm()->version );
        
    }
    
    function enqueue_tracks_scripts_styles_frontend(){
        //TO FIX load only when single track is displayed ? but anyway is loaded through wpsstm-tracklists ?
        wp_enqueue_style( 'wpsstm-tracks' );
        wp_enqueue_script( 'wpsstm-tracks' );
        
    }

    function enqueue_tracks_scripts_styles_backend(){
        
        if ( !wpsstm()->is_admin_page() ) return;
        
        wp_enqueue_script( 'wpsstm-tracks' );
        wp_enqueue_style( 'wpsstm-tracks' );

    }
    
    /*
    Add new track when url is 'wordpress/wpsstm_track/new/'
    */
    
    //TO FIX is this required ?
    
    function add_new_track(){
        $is_new_track = get_query_var($this->qvar_new_track);
        if (!$is_new_track) return;

        $track = new WP_SoundSystem_Track();
        $track->artist = ( isset($_REQUEST['track_artist']) ) ? $_REQUEST['track_artist'] : null;
        $track->title = ( isset($_REQUEST['track_title']) ) ? $_REQUEST['track_title'] : null;
        $track->album = ( isset($_REQUEST['track_album']) ) ? $_REQUEST['track_album'] : null;
        
        if ( !$track->post_id && !$track->populate_track_post_auto() ){//track does not exists in DB
            $track->save_temp_track();
        }
        
        if (!$track->post_id) return;
        
        //tracklist ID
        $tracklist_id = ( isset($_REQUEST['tracklist_id']) ) ? $_REQUEST['tracklist_id'] : null;
        
        if ($tracklist_id){
            $tracklist = wpsstm_get_post_tracklist($tracklist_id);
            $tracklist->append_subtrack_ids($track->post_id);
        }

        $track_admin_url = $track->get_track_admin_gui_url('edit');
        
        wp_redirect( $track_admin_url );
        exit;

    }
    
    /**
    *    From http://codex.wordpress.org/Template_Hierarchy
    *
    *    Adds a custom template to the query queue.
    */
    
    function track_admin_template_filter($template){
        global $wp_query;
        global $post;

        if( !isset( $wp_query->query_vars[$this->qvar_track_admin] ) ) return $template; //don't use $wp_query->get() here
        if ( get_post_type($post) != wpsstm()->post_type_track ) return $template;
        
        $file = 'track-admin.php';
        if ( file_exists( wpsstm_locate_template( $file ) ) ){
            $template = wpsstm_locate_template( $file );

            //TO FIX should be registered in register_tracks_scripts_styles_shared() then enqueued here, but it is not working
            wp_enqueue_script( 'wpsstm-track-admin', wpsstm()->plugin_url . '_inc/js/wpsstm-track-admin.js', array('jquery','jquery-ui-tabs'),wpsstm()->version, true );
            
            add_filter( 'body_class', array($this,'track_popup_body_classes'));
        }
        
        return $template;
    }
    
    function track_popup_body_classes($classes){
        //remove default
        if(($key = array_search('wpsstm_track-template-default', $classes)) !== false) {
            unset($classes[$key]);
            $classes[] = 'wpsstm_track-template-admin';
        }

        return $classes;
    }
    
    function track_save_admin_gui(){
        global $post;
        global $wp_query;

        $post_type = get_post_type();
        if ( $post_type != wpsstm()->post_type_track ) return;
        
        $track = new WP_SoundSystem_Track($post->ID);
        $popup_action = ( isset($_REQUEST['wpsstm-admin-track-action']) ) ? $_REQUEST['wpsstm-admin-track-action'] : null;
        if (!$popup_action || !$track->post_id) return;

        switch($popup_action){
            case 'edit':
                
                //nonce check
                if ( !isset($_POST['wpsstm_admin_track_gui_details_nonce']) || !wp_verify_nonce($_POST['wpsstm_admin_track_gui_details_nonce'], 'wpsstm_admin_track_gui_details_'.$track->post_id ) ) {
                    wpsstm()->debug_log(array('track_id'=>$track->post_id,'track_gui_action'=>$popup_action),"invalid nonce"); 
                    break;
                }

                $track->artist = ( isset($_POST[ 'wpsstm_track_artist' ]) ) ? $_POST[ 'wpsstm_track_artist' ] : null;
                $track->title = ( isset($_POST[ 'wpsstm_track_title' ]) ) ? $_POST[ 'wpsstm_track_title' ] : null;
                $track->album = ( isset($_POST[ 'wpsstm_track_album' ]) ) ? $_POST[ 'wpsstm_track_album' ] : null;
                $track->mbid = ( isset($_POST[ 'wpsstm_track_mbid' ]) ) ? $_POST[ 'wpsstm_track_mbid' ] : null;

                $track->save_track();
                
            break;
            case 'sources':
                
                //nonce check
                if ( !isset($_POST['wpsstm_admin_track_gui_sources_nonce']) || !wp_verify_nonce($_POST['wpsstm_admin_track_gui_sources_nonce'], 'wpsstm_admin_track_gui_sources_'.$track->post_id ) ) {
                    wpsstm()->debug_log(array('track_id'=>$track->post_id,'track_gui_action'=>$popup_action),"invalid nonce"); 
                    break;
                }
                
                $sources_raw = ( isset($_POST[ 'wpsstm_track_sources' ]) ) ? $_POST[ 'wpsstm_track_sources' ] : array();
                
                var_dump($sources_raw);die();
                
                //TO FIX TO CHECK
                //$track->update_track_sources($sources_raw);
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
            $notice = sprintf(__('Click %s if you want to include tracks belonging to albums and playlists in this listing.','wpsstm'),$notice_link);
        }else{
            $notice = sprintf(__('Click %s if you want to exclude tracks belonging to albums and playlists of this listing.','wpsstm'),$notice_link);
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
    
    function default_exclude_subtracks( $query ) {
        
        //only for main query
        if ( !$query->is_main_query() ) return $query;
        
        //only for tracks
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;
        
        //already defined
        if ( $query->get($this->qvar_subtracks_hide) ) return $query;
        
        //option enabled ?
        if ($this->subtracks_hide){
            $query->set($this->qvar_subtracks_hide,true);
        }

        return $query;
    }
    
    
    /**
    If query var 'hide_subtracks' is set,
    Filter tracks queries so tracks belonging to tracklists (albums/playlists/live playlists)) are not listed.
    TO FIX should update the post count too. see wp_count_posts
    **/
    
    function exclude_subtracks( $query ) {
        
        //only for main query
        if ( !$query->is_main_query() ) return $query;
        
        //only for tracks
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;
        
        //hide subtracks ?
        
        if ( $query->get($this->qvar_subtracks_hide) ){
            if ( $subtrack_ids = wpsstm_get_subtrack_ids() ) {
                $query->set('post__not_in',$subtrack_ids);
            }
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
            $after['track-lovedby'] = __('Loved by','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }
    
    function tracks_column_lovedby_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
            case 'track-lovedby':
                $output = '—';
                $track = new WP_SoundSystem_Track($post_id);
                $links = array();
                if ( $user_ids = $track->get_track_loved_by() ){
                    foreach($user_ids as $user_id){
                        $user_info = get_userdata($user_id);
                        $links[] = sprintf('<a href="%s" target="_blank">%s</a>',get_author_posts_url($user_id),$user_info->user_login);
                    }
                    $output = implode(', ',$links);
                }
                echo $output;
            break;
        }
    }

    function pre_get_posts_by_track_title( $query ) {

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
    
    /**
    Update the post title to match the artist/album/track, so we still have a nice post permalink
    **/
    
    function update_title_track( $post_id ) {
        
        //only for tracks
        if (get_post_type($post_id) != wpsstm()->post_type_track) return;

        //check capabilities
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $has_cap = current_user_can('edit_post', $post_id);
        if ( $is_autosave || $is_autodraft || $is_revision || !$has_cap ) return;

        $title = wpsstm_get_post_track($post_id);
        $artist = wpsstm_get_post_artist($post_id);
        
        if (!$title || !$artist) return;
        
        $post_title = sanitize_text_field( sprintf('%s - "%s"',$artist,$title) );

        //no changes - use get_post_field here instead of get_the_title() so title is not filtered
        if ( $post_title == get_post_field('post_title',$post_id) ) return;

        //log
        wpsstm()->debug_log(array('post_id'=>$post_id,'post_title'=>$post_title),"update_title_track()"); 

        $args = array(
            'ID'            => $post_id,
            'post_title'    => $post_title
        );

        remove_action( 'save_post',array($this,'update_title_track'), 99 ); //avoid infinite loop - ! hook priorities
        wp_update_post( $args );
        add_action( 'save_post',array($this,'update_title_track'), 99 );

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

            'supports' => array( 'title','thumbnail', 'comments' ),
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
            'capability_type' => 'post', //track
            //'map_meta_cap'        => true,
            /*
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
            ),
            */
        );

        register_post_type( wpsstm()->post_type_track, $args );
    }
    
    function add_query_vars_track( $qvars ) {
        $qvars[] = $this->qvar_track_lookup;
        $qvars[] = $this->qvar_track_admin;
        $qvars[] = $this->qvar_new_track;
        $qvars[] = $this->qvar_subtracks_hide;
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

        // Attributes
        $default = array(
            'post_id'       => $post->ID 
        );
        $atts = shortcode_atts($default,$atts);

        $tracklist = wpsstm_get_post_tracklist($atts['post_id']);
        
        return $tracklist->get_tracklist_table();

    }
    
    function ajax_love_unlove_track(){

        $ajax_data = wp_unslash($_POST);

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );

        $do_love = $result['do_love'] = ( isset($ajax_data['do_love']) ) ? filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN) : null; //ajax do send strings

        $track = new WP_SoundSystem_Track($ajax_data['post_id']);

        if ( ($do_love!==null) ){
            
            $success = $track->love_track($do_love);
            $result['track'] = $track;
            wpsstm()->debug_log( json_encode($track,JSON_UNESCAPED_UNICODE), "ajax_love_unlove_track()"); 

            if( is_wp_error($success) ){
                $code = $success->get_error_code();
                $result['message'] = $success->get_error_message($code); 
            }else{
                $result['success'] = $success; 
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_sources_auto_lookup(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'new_html'  => null,
            'success'   => false
        );

        $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;

        $track = new WP_SoundSystem_Track($post_id);
        $track->populate_auto_sources();

        $track = $result['track'] = $track;

        $result['new_html'] = wpsstm_sources()->get_track_sources_list($track);
        $result['success'] = true;

        header('Content-type: application/json');
        wp_send_json( $result ); 

    }

    function ajax_create_playlist(){
        $ajax_data = wp_unslash($_POST);

        wpsstm()->debug_log($ajax_data,"ajax_create_playlist()");
        

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false,
            'new_html'  => null
        );

        //create tracklist
        $tracklist_title = $result['tracklist_title'] = ( isset($ajax_data['playlist_title']) ) ? trim($ajax_data['playlist_title']) : null;

        $playlist = wpsstm_get_post_tracklist();
        $playlist->title = $tracklist_title;
        
        $tracklist_id = $playlist->save_playlist();
        
        if ( is_wp_error($tracklist_id) ){
            
            $code = $tracklist_id->get_error_code();
            $result['message'] = $tracklist_id->get_error_message($code);
            
        }else{
            
            $result['playlist_id'] = $tracklist_id;
            $result['success'] = true;
            $tracklist_ids = array(); //TO FIX required ?
            $list_all = wpsstm_get_user_playlists_list(array('checked_ids'=>$tracklist_ids));
            
            $result['new_html'] = $list_all;

        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
        
    }
    
    function ajax_add_playlist_track(){
        $ajax_data = wp_unslash($_POST);
        
        wpsstm()->debug_log($ajax_data,"ajax_add_playlist_track()"); 

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false,
            'new_html'  => null
        );
        
        $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
        
        //tracklist
        $playlist_id = $result['playlist_id'] = isset($ajax_data['playlist_id']) ? $ajax_data['playlist_id'] : null;

        $tracklist = new WP_SoundSystem_Static_Tracklist($playlist_id);
        $track = new WP_SoundSystem_Track($track_args);
        
        //get track ID or create it
        if (!$track_id = $track->post_id){
            $track_id = $track->save_track();
        }
        
        wpsstm()->debug_log($track,"ajax_add_playlist_track()");

        if ( is_wp_error($track_id) ){
            
            $code = $track_id->get_error_code();
            $result['message'] = $track_id->get_error_message($code);
            
        }else{
            
            $result['track_id'] = $track_id;
            $success = $tracklist->append_subtrack_ids($track_id);
            
            if ( is_wp_error($success) ){
                $code = $success->get_error_code();
                $result['message'] = $success->get_error_message($code);
            }else{
                $result['success'] = $success;
            }
            
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_remove_playlist_track(){
        $ajax_data = wp_unslash($_POST);

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false,
            'new_html'  => null
        );
        
        $post_id = isset($ajax_data['post_id']) ? $ajax_data['post_id'] : null;
        $playlist_id = $result['playlist_id'] = isset($ajax_data['playlist_id']) ? $ajax_data['playlist_id'] : null;
        $tracklist = new WP_SoundSystem_Static_Tracklist($playlist_id);
        $track = new WP_SoundSystem_Track( $post_id );
        
        //track ID is required
        if ( !$track->post_id && !$track->populate_track_post_auto() ) return;//track does not exists in DB

        //wpsstm()->debug_log($track,"ajax_remove_playlist_track()"); 
        
        $success = $tracklist->remove_subtrack_ids($track->post_id);

        if ( is_wp_error($success) ){
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = $success;
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
}

function wpsstm_tracks() {
	return WP_SoundSystem_Core_Tracks::instance();
}

wpsstm_tracks();