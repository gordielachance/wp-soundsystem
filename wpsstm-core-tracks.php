<?php

class WPSSTM_Core_Tracks{

    static $title_metakey = '_wpsstm_track';
    static $length_metakey = '_wpsstm_length_ms';
    static $image_url_metakey = '_wpsstm_track_image_url';
    static $qvar_track_lookup = 'lookup_track';
    static $qvar_favorite_tracks = 'loved-tracks';

    function __construct() {
        global $wpsstm_track;
        
        //initialize global (blank) $wpsstm_track so plugin never breaks when calling it.
        $wpsstm_track = new WPSSTM_Track();

        add_action( 'init', array($this,'register_post_type_track' ));
        add_filter( 'query_vars', array($this,'add_query_vars_track') );
        add_action( 'parse_query', array($this,'populate_global_track'));
        //add_action( 'the_post', array($this,'populate_loop_track'),10,2); //TOUFIX if enabled, notices do not work anymore

        //rewrite rules
        add_action('init', array($this, 'tracks_rewrite_rules'), 100 );

        add_action( 'wp', array($this,'handle_track_action'), 8);
        add_filter( 'template_include', array($this,'handle_ajax_track_action'), 5);
        add_filter( 'template_include', array($this,'track_template'), 8);

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
        
        add_action( 'current_screen',  array($this, 'the_single_backend_track'));
        
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_by_track_title') );
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_by_subtrack_id') );
        //TO FIX add filters to exclude tracks if 'exclude_subtracks' query var is set
        
        add_filter( 'posts_join', array($this,'tracks_query_join_subtracks'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_exclude_subtracks'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_subtrack_id'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_subtrack_position'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_favorited'), 10, 2 );
        add_filter( 'posts_orderby', array($this,'tracks_query_sort_by_subtrack_position'), 10, 2 );

        add_filter( 'the_title', array($this, 'the_track_post_title'), 9, 2 );

        /*
        AJAX
        */

        add_action('wp_ajax_wpsstm_track_html', array($this,'ajax_track_html'));
        add_action('wp_ajax_nopriv_wpsstm_track_html', array($this,'ajax_track_html'));
        
        add_action('wp_ajax_wpsstm_track_autosource', array($this,'ajax_track_autosource'));
        add_action('wp_ajax_nopriv_wpsstm_track_autosource', array($this,'ajax_track_autosource'));
        //add_action('wp', array($this,'test_autosource_ajax') );

        add_action('wp_ajax_wpsstm_update_track_sources_order', array($this,'ajax_update_sources_order'));

        
        /*
        DB relationships
        */
        add_action( 'save_post', array($this,'set_subtrack_post_id'), 6);
        add_action( 'before_delete_post', array($this,'delete_subtrack_track_id') );
        add_action( 'wp_trash_post', array($this,'trash_track_sources') );
    }
    
    /*
    Set global $wpsstm_track 
    */
    function populate_global_track($query){

        global $wpsstm_track;
        
        $post_id = $query->get( 'p' );
        $post_type = $query->get( 'post_type' );
        
        
        /*
        Track ID
        */
            
        if( $post_id && ( $post_type == wpsstm()->post_type_track ) ){
            $wpsstm_track = new WPSSTM_Track($post_id);
        }
        
        /*
        Subtrack ID //TOUFIX should not this be in tracklists ? since subtrack doesn't always have a track but always have a tracklist...
        */
        
        if ( $subtrack_id = get_query_var( 'subtrack_id' ) ){
            $wpsstm_track->populate_subtrack($subtrack_id);
        }


    }
    
    function populate_loop_track($post,$query){
        global $wpsstm_track;
        if ( $query->get('post_type') == wpsstm()->post_type_track ){
            //set global $wpsstm_tracklist
            $wpsstm_track = new WPSSTM_Track($post->ID);
        }
    }

    function handle_track_action(){
        global $wpsstm_track;
        $success = null;
        $action_feedback = null;
        $redirect_url = null;

        if ( !$action = get_query_var( 'wpsstm_action' ) ) return; //action does not exist
        if ( get_query_var( 'post_type' ) != wpsstm()->post_type_track ) return;

        $success = $wpsstm_track->do_track_action($action);

        /*
        Redirect or notice
        */
        if ($redirect_url){
            
            $redirect_args = array(
                'wpsstm_did_action' =>  $action,
                'wpsstm_action_feedback' => ( is_wp_error($success) ) ? $success->get_error_code() : true,
            );
            
            $redirect_url = add_query_arg($redirect_args, $redirect_url);
            
            wp_safe_redirect($redirect_url);
            die($redirect_url);
            
        }else{
            
            if ( is_wp_error($success) ){
                $wpsstm_track->add_notice($success->get_error_code(),$success->get_error_message());
            }else{
                $wpsstm_track->add_notice('success',__('Success!','wpsstm'));
            }
            
        }

    }

    function handle_ajax_track_action($template){
        global $wp_query;
        global $wpsstm_track;
        
        $success = null;

        if ( !$action = get_query_var( 'wpsstm_ajax_action' ) ) return $template; //action does not exist
        if ( get_query_var( 'post_type' ) != wpsstm()->post_type_track ) return $template;

        wpsstm()->debug_log($action,"handle_ajax_track_action");

        $result = array(
            'input' =>  $_REQUEST,
            'message'=> null,
            'success'=> null,
            'item' =>   $wpsstm_track->to_array(),
        );

        $success = $wpsstm_track->do_track_action($action);

        if ( is_wp_error($success) ){
            $result['success'] = false;
            $result['message'] = $success->get_error_message();
            
        }else{
            $result['success'] = $success;
        }
        
        $wpsstm_track->track_log($result);

        header('Content-type: application/json');
        send_nosniff_header();
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');
        
        wp_send_json( $result );  
        
    }

    function track_template($template){
        global $wpsstm_track;

        //check query
        if ( !is_single() ) return $template;
        $post_type = get_query_var( 'post_type' );
        if( $post_type != wpsstm()->post_type_track ) return $template; //post does not exists
        
        //check action
        $action = get_query_var( 'wpsstm_action' );
        if(!$action) return $template;

        switch($action){
            default:
                $template = wpsstm_locate_template( 'track.php' );
            break;
        }
        return $template;
    }

    function tracks_rewrite_rules(){

        $track_post_type_obj = get_post_type_object( wpsstm()->post_type_track );

        add_rewrite_tag(
            '%wpsstm_track_data%',
            '([^&]+)'
        );
        
        add_rewrite_tag(
            '%subtrack_id%', //TOUFIX TOUCHECK
            '(\d+)'
        );
        
        //single NEW subtrack action
        add_rewrite_rule(
            sprintf('^%s/%s/%s/([^/]+)/([^/]+)/([^/]+)/action/([^/]+)/?',WPSSTM_BASE_SLUG,WPSSTM_SUBTRACKS_SLUG,WPSSTM_NEW_ITEM_SLUG), // = /music/subtracks/ID/action/ACTION
            sprintf('index.php?post_type=%s&wpsstm_track_data[artist]=$matches[1]&wpsstm_track_data[album]=$matches[2]&wpsstm_track_data[title]=$matches[3]&wpsstm_action=$matches[4]',wpsstm()->post_type_track), // = /index.php?post_type=wpsstm_track&wpsstm_track_data[artist]=ARTIST&wpsstm_track_data[album]=ALBUM&wpsstm_track_data[title]=TITLE&wpsstm_action=dequeue
            'top'
        );

        //single ID subtrack action
        add_rewrite_rule(
            sprintf('^%s/%s/(\d+)/action/([^/]+)/?',WPSSTM_BASE_SLUG,WPSSTM_SUBTRACKS_SLUG), // = /music/subtracks/ID/action/ACTION
            sprintf('index.php?post_type=%s&subtrack_id=$matches[1]&wpsstm_action=$matches[2]',wpsstm()->post_type_track), // = /index.php?post_type=wpsstm_track&subtrack-id=251&wpsstm_action=dequeue
            'top'
        );
        
        add_rewrite_rule(
            sprintf('^%s/%s/(\d+)/ajax/([^/]+)/?',WPSSTM_BASE_SLUG,WPSSTM_SUBTRACKS_SLUG), // = /music/subtracks/ID/ajax/ACTION
            sprintf('index.php?post_type=%s&subtrack_id=$matches[1]&wpsstm_ajax_action=$matches[2]',wpsstm()->post_type_track), // = /index.php?post_type=wpsstm_track&subtrack-id=251&wpsstm_action=dequeue
            'top'
        );
        
        //single ID subtrack
        add_rewrite_rule(
            sprintf('^%s/%s/(\d+)/?',WPSSTM_BASE_SLUG,WPSSTM_SUBTRACKS_SLUG), // = /music/subtracks/ID
            sprintf('index.php?post_type=%s&subtrack_id=$matches[1]',wpsstm()->post_type_track), // = /index.php?post_type=wpsstm_track&subtrack-id=251
            'top'
        );
        
        
        //single track action
        add_rewrite_rule(
            sprintf('^%s/(\d+)/action/([^/]+)/?',$track_post_type_obj->rewrite['slug']), // = /music/tracks/ID/action/ACTION
            sprintf('index.php?post_type=%s&p=$matches[1]&wpsstm_action=$matches[2]',wpsstm()->post_type_track), // = /index.php?post_type=wpsstm_track&subtrack-id=251&wpsstm_action=dequeue
            'top'
        );
        add_rewrite_rule(
            sprintf('^%s/(\d+)/ajax/([^/]+)/?',$track_post_type_obj->rewrite['slug']), // = /music/tracks/ID/ajax/ACTION
            sprintf('index.php?post_type=%s&p=$matches[1]&wpsstm_ajax_action=$matches[2]',wpsstm()->post_type_track), // = /index.php?post_type=wpsstm_track&subtrack-id=251&wpsstm_action=dequeue
            'top'
        );

        //single track
        add_rewrite_rule(
            sprintf('^%s/(\d+)/?',$track_post_type_obj->rewrite['slug']), // = /music/tracks/ID
            sprintf('index.php?post_type=%s&p=$matches[1]',wpsstm()->post_type_track), // = /index.php?post_type=wpsstm_trackp=251
            'top'
        );

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
        wp_register_script( 'wpsstm-tracks', wpsstm()->plugin_url . '_inc/js/wpsstm-tracks.js', array('jquery','jquery-ui-tabs','wpsstm-sources'),wpsstm()->version, true );
        
    }

    static public function tracks_columns_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['sources'] = __('Sources','wpsstm');
        $after['track-playlists'] = __('Playlists','wpsstm');
        $after['track-favoritedby'] = __('Favorited','wpsstm');
        
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
            case 'track-favoritedby':
                $output = '—';
                
                if ( $list = $wpsstm_track->get_favorited_by_list() ){
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
    
    function pre_get_posts_by_subtrack_id( $query ){
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;
        if ( !$subtrack_id = $query->get('subtrack_id') ) return $query;
    }

    function tracks_query_join_subtracks($join,$query){
        global $wpdb;
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $join;
        
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $subtracks_query =              $query->get('subtracks');
        $subtrack_id_query =            $query->get('subtrack_id');
        $subtrack_position_query =      $query->get('subtrack_position');
        $subtrack_sort_query =          ($query->get('orderby') == 'subtracks');

        $join_subtracks = ( $subtracks_query || $subtrack_id_query || $subtrack_sort_query || $subtrack_position_query  );
        
        if ($join_subtracks){
            $join .= sprintf("INNER JOIN %s AS subtracks ON (%s.ID = subtracks.track_id)",$subtracks_table,$wpdb->posts);
        }

        return $join;
    }
    
    function track_query_exclude_subtracks($where,$query){
        global $wpdb;
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $where;
        
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $no_subtracks = ( $query->get('subtracks') === 0);
        
        if ($no_subtracks){
            $where .= sprintf(" AND ID NOT IN (SELECT track_id FROM %s)",$subtracks_table);
        }

        return $where;
    }

    function track_query_where_subtrack_position($where,$query){
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $where;
        if ( !$subtrack_position = $query->get('subtrack_position') ) return $where;
        if ( !$tracklist_id = $query->get('tracklist_id') ) return $where;

        $where.= sprintf(" AND subtracks.tracklist_id = %s AND subtracks.track_order = %s",$tracklist_id,$subtrack_position);

        //so single template is shown, instead of search results
        //TOUFIX this is maybe quite hackish, should be improved ? eg. setting $query->is_singular = true crashes wordpress.
        $query->is_single = true; 

        return $where;
    }
    function track_query_where_subtrack_id($where,$query){
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $where;
        if ( !$subtrack_id = $query->get('subtrack_id') ) return $where;
        
        $where.= sprintf(" AND subtracks.ID = %s",$subtrack_id);
        
        //so single template is shown, instead of search results
        //TOUFIX this is maybe quite hackish, should be improved ? eg. setting $query->is_singular = true crashes wordpress.
        $query->is_single = true; 

        return $where;
    }
    
    /*
    function pre_get_posts_by_subtrack_id( $query ){
        global $wpdb;
        
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;
        if ( !$subtrack_id = $query->get('subtrack_id') ) return $query;
        
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $subtrack_query = "SELECT * FROM `$subtracks_table` WHERE ID = $subtrack_id";
        $subtrack = $wpdb->get_row($subtrack_query);
        $track_id = $subtrack->track_id;
        
        $query->set('p',$track_id);
        $query->is_single = true;
        
        return $query;
    }
    */
    function track_query_where_favorited($where,$query){
        
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $where;

        if ( !$query->get( self::$qvar_favorite_tracks ) ) return $where;
        
        
        //get all favorites tracklists
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            'posts_per_page' => -1,
            'post_status' =>    array('publish','private','future','pending','draft'),
            'fields' =>         'ids',
            WPSSTM_Core_Tracklists::$qvar_loved_tracklists => true,
        );
        $query = new WP_Query( $query_args );
        $ids = $query->posts;
        
        $ids_str = implode(',',$ids);
        $where .= sprintf(" AND subtracks.tracklist_id IN (%s)",$ids_str);

        return $where;
    }

    function tracks_query_sort_by_subtrack_position($orderby_sql, $query){

        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $orderby_sql;
        if ( $query->get('orderby') != 'subtracks' ) return $orderby_sql;

        if ( $query_orderby == 'track_order'){
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
            'rewrite' => array(
                'slug' => sprintf('%s/%s',WPSSTM_BASE_SLUG,WPSSTM_TRACKS_SLUG), // = /music/tracks
                'with_front' => FALSE
            ),
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
        $qvars[] = self::$qvar_favorite_tracks;
        $qvars[] = 'subtracks';
        $qvars[] = 'subtrack_id';
        
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
        wpsstm_locate_template( 'append-track.php',true );
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
        
        $track_id = isset($ajax_data['track_id']) ? $ajax_data['track_id'] : null;
        $track = new WPSSTM_Track($track_id);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data,
            'track'     => $track->to_array(),
        );
        
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

    function ajax_track_html(){
        global $wpsstm_track;
        
        $ajax_data = wp_unslash($_POST);
        $subtrack_id = wpsstm_get_array_value(array('track','subtrack_id'),$ajax_data);
        $track = new WPSSTM_Track();
        $track->populate_subtrack($subtrack_id);

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'html'      => null,
            'success'   => false,
            'track'     => $track->to_array(),
        );
        
        //define global
        $wpsstm_track = $track;
        
        ob_start();
        wpsstm_locate_template( 'content-track.php', true, false );
        $updated_track = ob_get_clean();
        $result['html'] = $updated_track;
        $result['success'] = true;

        header('Content-type: application/json');
        wp_send_json( $result );

    }

    function ajax_track_autosource(){

        $ajax_data = wp_unslash($_POST);

        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);
        
        $result = array(
            'input'     => $ajax_data,
            'timestamp' => current_time('timestamp'),
            'message'   => null,
            'success'   => false,
            'track'     => $track->to_array(),
        );
   
        //autosource
        $new_ids = array();
        
        $new_ids = $track->autosource();

        if ( is_wp_error($new_ids) ){
            $result['message'] = $new_ids->get_error_message();
        }else{
            $result['success'] = true;
        }

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

    /*
    Get tracks that have been created by the community user and that do not belong to any playlists
    */
    static function get_orphan_track_ids(){
        $community_user_id = wpsstm()->get_options('community_user_id');
        if ( !$community_user_id ) return;

        //get community tracks
        $orphan_tracks_args = array(
            'post_type' =>              wpsstm()->post_type_track,
            'author' =>                 $community_user_id,
            'post_status' =>            'any',
            'posts_per_page'=>          -1,
            'fields' =>                 'ids',
            'subtracks' =>              0,
        );
        
        $query = new WP_Query( $orphan_tracks_args );
        return $query->posts;
        
    }
    
    /*
    Flush community tracks
    */
    static function trash_orphan_tracks(){

        $flushed_ids = array();
        
        if ( $flushable_ids = self::get_orphan_track_ids() ){

            foreach( (array)$flushable_ids as $track_id ){
                $success = wp_trash_post($track_id);
                if ( !is_wp_error($success) ) $flushed_ids[] = $track_id;
            }
        }

        wpsstm()->debug_log( json_encode(array('flushable'=>count($flushable_ids),'flushed'=>count($flushed_ids))),"Deleted orphan tracks");

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