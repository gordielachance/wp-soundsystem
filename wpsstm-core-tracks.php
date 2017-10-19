<?php

class WP_SoundSystem_Core_Tracks{

    public $title_metakey = '_wpsstm_track';
    public $image_url_metakey = '_wpsstm_track_image_url';
    public $qvar_track_admin = 'admin';
    public $qvar_track_lookup = 'lookup_track';
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
        
        add_filter( 'template_include', array($this,'track_admin_template_filter'));
        add_action( 'wp', array($this,'track_save_admin_gui'));

        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracks_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracks_scripts_styles_shared' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracks_scripts_styles_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tracks_scripts_styles_backend' ) );
        
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_tracks_submenu' ) );
        
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_by_track_title') );
        add_action( 'save_post', array($this,'update_title_track'), 99);

        add_action( 'add_meta_boxes', array($this, 'metabox_track_register'));
        add_action( 'save_post', array($this,'metabox_track_title_save'), 5);
        
        add_filter('manage_posts_columns', array($this,'tracks_column_lovedby_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'tracks_column_lovedby_content'), 10, 2 );
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track), array(wpsstm(),'register_community_view') );

        //tracklist shortcode
        add_shortcode( 'wpsstm-track',  array($this, 'shortcode_track'));
        
        //subtracks
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_subtracks') );
        add_filter( 'posts_orderby', array($this,'sort_subtracks_by_position'), 10, 2 );
        
        /*
        add_action( 'admin_notices',  array($this, 'toggle_subtracks_notice') );
        add_action( 'current_screen',  array($this, 'toggle_subtracks_store_option') );
        add_filter( 'pre_get_posts', array($this,'default_exclude_subtracks') );
        */
        
        //delete sources when post is deleted
        add_action( 'wp_trash_post', array($this,'trash_track_sources') );
        
        //ajax : toggle love track
        add_action('wp_ajax_wpsstm_love_unlove_track', array($this,'ajax_love_unlove_track'));

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

    function register_track_endpoints(){
        // (existing track) admin
        add_rewrite_endpoint($this->qvar_track_admin, EP_PERMALINK ); 
    }
    
    function register_tracks_scripts_styles_shared(){
        //JS
        wp_register_script( 'wpsstm-tracks', wpsstm()->plugin_url . '_inc/js/wpsstm-tracks.js', array('jquery','thickbox','wpsstm-track-sources'),wpsstm()->version );
        
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
    
    function track_admin_template_filter($template){
        global $wp_query;
        global $post;

        $post_type = get_post_type($post);
        $track_admin_action =  get_query_var( $this->qvar_track_admin );
        $tracklist_admin_action =  get_query_var( wpsstm_tracklists()->qvar_tracklist_admin );

        $is_track_edit = ( $track_admin_action && ($post_type == wpsstm()->post_type_track) );
        $is_tracklist_new_track = ( ($tracklist_admin_action == 'new-subtrack') && in_array($post_type,wpsstm_tracklists()->static_tracklist_post_types) );

        if ( !$is_track_edit && !$is_tracklist_new_track ) return $template;

        if ( $template = wpsstm_locate_template( 'track-admin.php' ) ){

            //TO FIX should be registered in register_tracks_scripts_styles_shared() then enqueued here, but it is not working
            wp_enqueue_script( 'wpsstm-track-admin', wpsstm()->plugin_url . '_inc/js/wpsstm-track-admin.js', array('jquery','jquery-ui-tabs'),wpsstm()->version, true );
            add_filter( 'body_class', array($this,'track_popup_body_classes'));
        }
        
        return $template;
    }
    
    function track_popup_body_classes($classes){
        $classes[] = 'wpsstm_track-template-admin';
        return $classes;
    }
    
    function track_save_admin_gui(){
        global $post;
        global $wp_query;

        $post_type = get_post_type();
        if ( $post_type != wpsstm()->post_type_track ) return;
        
        $track = new WP_SoundSystem_Track($post->ID);
        $popup_action = ( isset($_POST['wpsstm-admin-track-action']) ) ? $_POST['wpsstm-admin-track-action'] : null;
        if ( !$popup_action ) return;

        switch($popup_action){

            case 'edit':
                
                //nonce check
                if ( !isset($_POST['wpsstm_admin_track_gui_edit_nonce']) || !wp_verify_nonce($_POST['wpsstm_admin_track_gui_edit_nonce'], 'wpsstm_admin_track_gui_edit_'.$track->post_id ) ) {
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

                foreach((array)$sources_raw as $source_raw){
                    
                    if ( isset($source_raw['post_id']) ){
                        $source = new WP_SoundSystem_Source($source_raw['post_id']);
                    }else{
                        $source = new WP_SoundSystem_Source();
                        
                        $source_raw['track_id'] = $track->post_id;
                        $source->from_array( $source_raw );
                        if (!$source->url) continue;
                    }

                    if ($source->post_id){ //confirm a source by updating its author
                        
                        wp_update_post(array(
                            'ID' =>             $source->post_id,
                            'post_author' =>    get_current_user_id()
                        ));
                        
                    }else{ //add source
                        
                        $source->add_source();
                        
                    }
                    
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
        if ( $query->get('subtracks_exclude') ) return $query;
        
        //option enabled ?
        if ($this->subtracks_hide){
            $query->set('subtracks_exclude',true);
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

            'supports' => array( 'author','title','thumbnail', 'comments' ),
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
        $qvars[] = $this->qvar_track_admin;
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

        // Attributes
        $default = array(
            'post_id'       => $post->ID 
        );
        $atts = shortcode_atts($default,$atts);

        setup_postdata($atts['post_id']); //this will populate the $wpsstm_tracklist
        $output = $wpsstm_tracklist->get_tracklist_html();
        
        wp_reset_postdata();
        
        return $output;

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
    
    /*
    Include or exclude subtracks from tracks queries.
    Subtrack type can be 'static', 'live' or true (both).
    
    include & true : returns all subtracks
    include & live|static : returns live|static subtracks
    
    exclude & true : return all tracks that are not subtracks
    exclude & live|static : return all tracks that are not live|static subtracks
.   */
    
    function pre_get_posts_subtracks( $query ) {

        //only for tracks
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;

        $type = true;
        $include = null;

        $include_type = $query->get('subtracks_include');
        $exclude_type = $query->get('subtracks_exclude');

        if($include_type){
            $type = $include_type;
            $include = true;
        }elseif($exclude_type){
            $type = $exclude_type;
            $include = false;
        }else{ //cannot process
            return;
        }

        //get all subtracks; optionnally for a tracklist ID
        $tracklist_id = $query->get('tracklist_id');
        $subtrack_ids = wpsstm_get_raw_subtrack_ids($type,$tracklist_id);

        if ($include){
            
            //if we want to include subtracks and that there is none, force return nothing
            //https://core.trac.wordpress.org/ticket/28099
            //https://wordpress.stackexchange.com/a/140727/70449
            if (!$subtrack_ids){ 
                $subtrack_ids[] = 0;
            }
            
            $query->set('post__in',(array)$subtrack_ids);
        }else{
            
            //if we want to exclude subtracks and that there is none, abord
            if (!$subtrack_ids){ 
                return $query;
            }
            
            $query->set('post__not_in',(array)$subtrack_ids);
        }

        return $query;
    }
    
    /*
    By default, Wordpress will sort the subtracks by date.
    If we have a subtracks query with a tracklist ID set; and that no orderby is defined, rather sort by tracklist position.
    */
    
    function sort_subtracks_by_position($orderby_sql, $query){
        $tracklist_id = $query->get('tracklist_id');
        $orderby = $query->get('orderby');
        $include_type = $query->get('subtracks_include');
        
        if ( !$include_type || !$tracklist_id || ($orderby != 'subtrack_position') ) return $orderby_sql;

        $subtrack_ids = wpsstm_get_raw_subtrack_ids($include_type,$tracklist_id);
        if (!$subtrack_ids) return $orderby_sql;
        
        $ordered_ids = implode(' ,',$subtrack_ids);

        return sprintf('FIELD(ID, %s)',$ordered_ids);

    }
    
}

function wpsstm_tracks() {
	return WP_SoundSystem_Core_Tracks::instance();
}

wpsstm_tracks();