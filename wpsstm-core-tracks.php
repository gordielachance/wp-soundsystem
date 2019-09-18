<?php

class WPSSTM_Core_Tracks{
    
    static $artist_taxonomy = 'wpsstm_artist';
    static $track_taxonomy = 'wpsstm_track';
    static $album_taxonomy = 'wpsstm_album';

    static $duration_metakey = '_wpsstm_length_ms';
    static $image_url_metakey = '_wpsstm_track_image_url';

    function __construct() {
        global $wpsstm_track;
        
        add_action( 'wpsstm_init_post_types', array($this,'register_track_post_type' ));
        add_action( 'wpsstm_init_post_types', array($this,'register_track_taxonomy' ));
        
        /*
        populate single global track.
        Be sure it works frontend, backend, and on post-new.php page
        */
        $wpsstm_track = new WPSSTM_Track();
        add_action( 'parse_query', array($this,'populate_global_subtrack'));
        add_action( 'wp',  array($this, 'populate_global_track_frontend'),1 );
        add_action( 'admin_head',  array($this, 'populate_global_track_backend'),1);
        add_action( 'the_post', array($this,'populate_global_track_loop'),10,2);

        
        add_filter( 'query_vars', array($this,'add_query_vars_track') );
        add_action( 'wp', array($this,'handle_track_action'), 8);

        //rewrite rules
        add_action('wpsstm_init_rewrite', array($this, 'tracks_rewrite_rules') );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracks_scripts_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracks_scripts_styles' ) );
        
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_tracks_submenu' ) );

        add_action( 'add_meta_boxes', array($this, 'metabox_track_register'));
        add_action( 'save_post', array($this,'metabox_save_music_details'), 5); //TOUFIX should NOT be within the track class ?
        
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_track), array(__class__,'tracks_columns_register') );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_track), array(__class__,'tracks_columns_content') );
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track), array(__class__,'register_orphan_tracks_view') );
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_track), array(wpsstm(),'register_imported_view'), 5 );

        //track shortcode
        add_shortcode( 'wpsstm-track',  array($this, 'shortcode_track'));
        
        /* manager */
        add_action( 'wp', array($this,'handle_manager_action'), 8);
        add_filter( 'template_include', array($this,'manager_template'));

        add_filter( 'posts_join', array($this,'include_subtracks_query_join'), 10, 2 );
        add_filter( 'posts_join', array($this,'exclude_subtracks_query_join'), 10, 2 );
        add_filter( 'posts_fields', array($this,'tracks_query_subtrack_ids'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_exclude_subtracks'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_tracklist_id'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_subtrack_id'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_subtrack_in'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_subtrack_position'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_subtrack_author'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_playing'), 10, 2 );
        add_filter( 'posts_where', array($this,'track_query_where_favorited'), 10, 2 );
        add_filter( 'posts_orderby', array($this,'tracks_query_sort_by_subtrack_position'), 10, 2 );
        add_filter( 'posts_orderby', array($this,'tracks_query_sort_by_subtrack_time'), 10, 2 );

        add_filter( 'the_title', array($this, 'the_track_post_title'), 9, 2 );

        /*
        AJAX
        */

        add_action('wp_ajax_wpsstm_get_track_links', array($this,'ajax_track_get_links'));
        add_action('wp_ajax_nopriv_wpsstm_get_track_links', array($this,'ajax_track_get_links'));
        
        add_action('wp_ajax_wpsstm_track_start', array($this,'ajax_track_start'));
        add_action('wp_ajax_nopriv_wpsstm_track_start', array($this,'ajax_track_start'));

        add_action('wp_ajax_nopriv_wpsstm_update_subtrack_position', array($this,'ajax_update_subtrack_position'));
        add_action('wp_ajax_wpsstm_update_subtrack_position', array($this,'ajax_update_subtrack_position'));
        
        add_action('wp_ajax_wpsstm_track_toggle_favorite', array($this,'ajax_track_toggle_favorite'));
        add_action('wp_ajax_wpsstm_subtrack_dequeue', array($this,'ajax_subtrack_dequeue'));
        add_action('wp_ajax_wpsstm_track_trash', array($this,'ajax_track_trash'));

        //add_action('wp', array($this,'test_autolink_ajax') );

        add_action('wp_ajax_wpsstm_update_track_links_order', array($this,'ajax_update_track_links_order'));

        
        /*
        DB relationships
        */
        add_action( 'before_delete_post', array($this,'delete_track_links') );
        add_action( 'before_delete_post', array($this,'delete_subtracks') );
        add_action( 'before_delete_post', array($this,'delete_empty_music_terms') );

    }

    /*
    Set global $wpsstm_track 
    */
    function populate_global_subtrack($query){
        global $wpsstm_track;

        if ( !$query->is_main_query() ) return;
        if ( !$subtrack_id = $query->get( 'subtrack_id' ) ) return;
        
        $subtrack_post = WPSSTM_Core_Tracks::get_subtrack_post($subtrack_id);
        $wpsstm_track = new WPSSTM_Track($subtrack_post);
        
        if ( !$wpsstm_track->post_id ){
            $error_msg = $success->get_error_message();
            $wpsstm_track->track_log($error_msg,'error populating subtrack');
            ///
            $query->set_404();
            status_header( 404 );
            nocache_headers();
        }

    }
    
    function populate_global_track_frontend(){
        global $post;
        global $wpsstm_track;

        if ( !is_single() || ( get_post_type() != wpsstm()->post_type_track ) ) return;

        $wpsstm_track = new WPSSTM_Track($post);
        $wpsstm_track->track_log("Populated global frontend track");
        
    }
    
    function populate_global_track_backend(){
        global $post;
        global $wpsstm_track;
        
        //is posts.php or post-new.php ?
        $screen = get_current_screen();
        $is_track_backend = ( $screen->id == wpsstm()->post_type_track );
        if ( !$is_track_backend  ) return;

        $wpsstm_track = new WPSSTM_Track($post);
        $wpsstm_track->track_log("Populated global backend track");
        
    }
    
    /*
    Register the global within posts loop
    */
    
    function populate_global_track_loop($post,$query){
        global $wpsstm_track;
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return;
        
        //set global $wpsstm_tracklist
        $is_already_populated = ($wpsstm_track && ($wpsstm_track->post_id == $post->ID) );
        if ($is_already_populated) return;

        $wpsstm_track = new WPSSTM_Track($post);
    }

    //TOUFIX needed ?
    function handle_track_action(){
        global $wpsstm_track;
        global $wp_query;
        
        $success = null;
        $redirect_url = null;
        $action_feedback = null;
        
        if ( !$action = get_query_var( 'wpsstm_action' ) ) return; //action does not exist
        if ( get_query_var('post_type') !== wpsstm()->post_type_track ) return;

        switch($action){

        }

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
            exit;
            
        }else{
            if ( is_wp_error($success) ){
                $wpsstm_tracklist->add_notice($success->get_error_code(),$success->get_error_message());
            }
            
        }
        
    }

    function handle_manager_action(){
        global $wpsstm_track;
        $success = null;

        if ( !$subtrack_id = get_query_var( 'subtrack_id' ) ) return;
        if ( 'manage' !== get_query_var( 'wpsstm_action' ) ) return; //action does not exist

        $manager_action = wpsstm_get_array_value(array('wpsstm_manager_action'),$_REQUEST);
        $manager_data = wpsstm_get_array_value(array('wpsstm_manager_data'),$_REQUEST);
        
        
        switch ($manager_action){
            case 'toggle_tracklists':

                $checked_tracklists = wpsstm_get_array_value(array('new_tids'),$manager_data);
                $previous_values = wpsstm_get_array_value(array('old_tids'),$manager_data);
                $edit_values = array();

                if (!$previous_values) break; //no range to compare to

                //use bool values instead of strings
                foreach((array)$previous_values as $key => $value){
                    $previous_values[$key] = ($value === '1') ? true : false;
                }

                foreach((array)$checked_tracklists as $key => $value){
                    $checked_tracklists[$key] = true;
                }

                //build an array containing the tracklists IDs that have been updated
                foreach((array)$previous_values as $key => $value){
                    if ( $value && !array_key_exists($key,$checked_tracklists) ){//item has been unchecked
                        $edit_values[$key] = false;
                    }elseif ( !$value && array_key_exists($key,$checked_tracklists) ){//item has been checked
                        $edit_values[$key] = true;
                    }
                }

                //process changed values
                if ($edit_values){

                    foreach($edit_values as $tracklist_id => $is_child){

                        $tracklist = new WPSSTM_Post_Tracklist($tracklist_id);

                        if ($is_child){
                            $success = $tracklist->queue_track($wpsstm_track);
                        }else{
                            $success = $tracklist->dequeue_track($wpsstm_track);
                        }

                        if ( is_wp_error($success) ){
                            break; //break at first error
                        }
                    }

                }
                
                

            break;

            case 'new_tracklist':
                $tracklist_title = wpsstm_get_array_value(array('new_tracklist_title'),$manager_data);
                if (!$tracklist_title){
                    $success = new WP_Error('wpsstm_missing_tracklist_title',__('Missing tracklist title','wpsstm'));
                }else{

                    //create new tracklist
                    $tracklist = new WPSSTM_Post_Tracklist();
                    $tracklist->title = $tracklist_title;

                    $success = $tracklist->save_tracklist();

                    //append subtrack if any
                    if ( !is_wp_error($success) ){
                        $tracklist_id = $success;
                        $tracklist = new WPSSTM_Post_Tracklist($tracklist_id);
                        $success = $tracklist->queue_track($wpsstm_track);
                    }
                }
                
                
                
            break;
        }
        
        if ($success){
            if ( is_wp_error($success) ){
                //TOUFIX we should remove that track notice function.
                $wpsstm_track->add_notice($success->get_error_code(),$success->get_error_message());
            }else{
                $wpsstm_track->add_notice('success',__('Track action success!','wpsstm'));
            }
        }

    }

    function manager_template($template){
        global $wpsstm_track;
        if ( is_404() ) return $template;
        if ( !is_single() ) return $template;
        $post_type = get_query_var( 'post_type' );
        if ($post_type !== wpsstm()->post_type_track ) return $template;

        if ( !$wpsstm_track->subtrack_id && !$wpsstm_track->post_id ) return $template;

        //check action
        $action = get_query_var( 'wpsstm_action' );
        if($action != 'manage') return $template;

        return wpsstm_locate_template( 'tracklist-manager.php' );
        
        
    }

    function tracks_rewrite_rules(){

        $track_post_type_obj = get_post_type_object( wpsstm()->post_type_track );

        //single NEW subtrack action
        //TOUFIX TOUCHECK used ?
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

    function register_tracks_scripts_styles(){
        wp_register_script( 'wpsstm-tracks', wpsstm()->plugin_url . '_inc/js/wpsstm-tracks.js', array('jquery','wpsstm-functions','jquery-ui-tabs','wpsstm-links'),wpsstm()->version, true );
        
    }

    static public function tracks_columns_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['track-links'] = __('Links','wpsstm');
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
            case 'track-links':
                
                $published_str = $pending_str = null;

                $links_published_query = $wpsstm_track->query_links();
                $links_pending_query = $wpsstm_track->query_links(array('post_status'=>'pending'));

                $url = admin_url('edit.php');
                $url = add_query_arg( array('post_type'=>wpsstm()->post_type_track_link,'parent_track'=>$wpsstm_track->post_id,'post_status'=>'publish'),$url );
                $published_str = sprintf('<a href="%s">%d</a>',$url,$links_published_query->post_count);
                
                if ($links_pending_query->post_count){
                    $url = admin_url('edit.php');
                    $url = add_query_arg( array('post_type'=>wpsstm()->post_type_track_link,'parent_track'=>$wpsstm_track->post_id,'post_status'=>'pending'),$url );
                    $pending_link = sprintf('<a href="%s">%d</a>',$url,$links_pending_query->post_count);
                    $pending_str = sprintf('<small> +%s</small>',$pending_link);
                }
                
                echo $published_str . $pending_str;
                
            break;
        }
    }
    
    static function register_orphan_tracks_view($views){

        $screen =                   get_current_screen();
        $post_type =                $screen->post_type;
        $subtracks_exclude =        get_query_var('subtrack_exclude');

        $link = add_query_arg( array('post_type'=>$post_type,'subtrack_exclude'=>true),admin_url('edit.php') );
        $count = count(WPSSTM_Core_Tracks::get_orphan_track_ids());
        
        $attr = array(
            'href' =>   $link,
        );
        
        if ($subtracks_exclude){
            $attr['class'] = 'current';
        }

        $views['orphan'] = sprintf('<a %s>%s <span class="count">(%d)</span></a>',wpsstm_get_html_attr($attr),__('Orphan','wpsstm'),$count);
        
        return $views;
    }
    
    private function is_subtracks_query($query){

        return ( ( $query->get('post_type') == wpsstm()->post_type_track ) && $query->get('subtrack_query') );

    }

    function include_subtracks_query_join($join,$query){
        global $wpdb;
        if ( !$this->is_subtracks_query($query) ) return $join;

        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        $join .= sprintf("INNER JOIN %s AS subtracks ON %s.ID = subtracks.track_id",$subtracks_table,$wpdb->posts);
        return $join;
    }
    
    function exclude_subtracks_query_join($join,$query){
        global $wpdb;
        if ( !$query->get('subtrack_exclude') ) return $join;

        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        $join .= sprintf("LEFT JOIN %s AS subtracks ON %s.ID = subtracks.track_id",$subtracks_table,$wpdb->posts); //so we can rely on subtracks.subtrack_id = null
        return $join;
    }
    
    /*
    Include the subtrack_id when fetching subtracks
    */

    function tracks_query_subtrack_ids($fields,$query) {
        global $wpdb;
        
        if ( !$this->is_subtracks_query($query) ) return $fields;

        if ( $query->get('fields') === 'ids' ) return $fields;//when requesting ids, we don't want several fields returned.

        $fields = (array)$fields;
        $fields[] = sprintf('%s.*','subtracks');
        return implode(', ',$fields);

    }
    
    function track_query_exclude_subtracks($where,$query){
        global $wpdb;

        if ( !$query->get('subtrack_exclude') ) return $where;
        
        $where .= sprintf(" AND subtracks.track_id IS NULL");
        return $where;
    }

    function track_query_where_subtrack_position($where,$query){
        if ( !$this->is_subtracks_query($query) ) return $where;
        if ( !$subtrack_position = $query->get('subtrack_position') ) return $where;
        if ( !$tracklist_id = $query->get('tracklist_id') ) return $where;

        $where.= sprintf(" AND subtracks.tracklist_id = %s AND subtracks.subtrack_order = %s",$tracklist_id,$subtrack_position);

        //so single template is shown, instead of search results
        //TOUFIX this is maybe quite hackish, should be improved ? eg. setting $query->is_singular = true crashes wordpress.
        $query->is_single = true; 

        return $where;
    }
    
    function track_query_where_subtrack_author($where,$query){
        if ( !$this->is_subtracks_query($query) ) return $where;
        if ( !$subtrack_author = $query->get('subtrack_author') ) return $where;

        $where.= sprintf(" AND subtracks.subtrack_author = %s",$subtrack_author);
        
        return $where;   
    }
    
    /*
    Get recents subtracks added in the 'now playing' tracklist
    */
    
    function track_query_where_playing($where,$query){
        if ( !$this->is_subtracks_query($query) ) return $where;
        if ( !$query->get('subtrack_playing') ) return $where;
        if ( !$nowplaying_id = wpsstm()->get_options('nowplaying_id') ) return $where;
        
        $seconds = wpsstm()->get_options('playing_timeout');

        $where.= sprintf(" AND subtracks.tracklist_id = %s AND subtrack_time > DATE_SUB(NOW(), INTERVAL %s SECOND)",$nowplaying_id,$seconds);

        return $where;   
    }
    
    function track_query_where_tracklist_id($where,$query){
        if ( !$this->is_subtracks_query($query) ) return $where;
        if ( !$tracklist_id = $query->get('tracklist_id') ) return $where;
        
        $where.= sprintf(" AND subtracks.tracklist_id = %s",$tracklist_id);

        //so single template is shown, instead of search results
        //TOUFIX this is maybe quite hackish, should be improved ? eg. setting $query->is_singular = true crashes wordpress.
        $query->is_single = true; 

        return $where;
    }
    
    function track_query_where_subtrack_id($where,$query){
        if ( !$this->is_subtracks_query($query) ) return $where;
        if ( !$subtrack_id = $query->get('subtrack_id') ) return $where;
        
        $where.= sprintf(" AND subtracks.subtrack_id = %s",$subtrack_id);

        //so single template is shown, instead of search results
        //TOUFIX this is maybe quite hackish, should be improved ? eg. setting $query->is_singular = true crashes wordpress.
        $query->is_single = true; 

        return $where;
    }
    
    function track_query_where_subtrack_in($where,$query){
        if ( !$this->is_subtracks_query($query) ) return $where;
        if ( !$ids_str = $query->get('subtrack__in') ) return $where;
        
        if ( is_array($ids_str) ){
            $ids_str = implode(',',$ids_str);
        }
        
        $where .= sprintf(" AND subtracks.subtrack_id IN (%s)",$ids_str);

        return $where;
    }

    function track_query_where_favorited($where,$query){
        
        if ( !$this->is_subtracks_query($query) ) return $where;
        if ( !$query->get( 'subtrack_favorites' ) ) return $where;

        //get all favorited tracklists
        if ( !$ids = WPSSTM_Core_User::get_sitewide_favorites_tracklist_ids() ){
            $ids = array(0);//so won't fetch anything
        }

        $ids_str = implode(',',$ids);
        $where .= sprintf(" AND subtracks.tracklist_id IN (%s)",$ids_str);

        return $where;
    }

    function tracks_query_sort_by_subtrack_position($orderby_sql, $query){

        if ( !$this->is_subtracks_query($query) ) return $orderby_sql;
        if ( $query->get('orderby') != 'subtrack_position' ) return $orderby_sql;

        $orderby_sql = 'subtracks.subtrack_order ' . $query->get('order');

        return $orderby_sql;

    }    
    
    function tracks_query_sort_by_subtrack_time($orderby_sql, $query){

        if ( !$this->is_subtracks_query($query) ) return $orderby_sql;
        if ( $query->get('orderby') != 'subtrack_time' ) return $orderby_sql;
        
        $orderby_sql = 'subtracks.subtrack_time ' . $query->get('order');
        
        return $orderby_sql;

    }    

    function register_track_post_type() {

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
    
    function register_track_taxonomy(){

        $labels = array(
            'name'                       => _x( 'Track Titles', 'Taxonomy General Name', 'wpsstm' ),
            'singular_name'              => _x( 'Track Title', 'Taxonomy Singular Name', 'wpsstm' ),
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
            'show_ui'                    => true,
            'show_admin_column'          => false,
            'show_in_nav_menus'          => false,
            'show_tagcloud'              => false,
            'capabilities'               => $capabilities,
        );
        register_taxonomy(
            WPSSTM_Core_Tracks::$track_taxonomy,
            array( wpsstm()->post_type_track ),
            $args
        );

    }
    
    function add_query_vars_track( $qvars ) {
        $qvars[] = 'wpsstm_track_data';
        $qvars[] = 'tracklist_id';
        $qvars[] = 'subtrack_query';
        $qvars[] = 'subtrack_playing';
        $qvars[] = 'subtrack_author';
        $qvars[] = 'subtrack_favorites';
        $qvars[] = 'subtrack_id';
        $qvars[] = 'subtrack__in';
        $qvars[] = 'subtrack_position';
        $qvars[] = 'subtrack_exclude';
        
        return $qvars;
    }
    
    function metabox_track_register(){

        add_meta_box( 
            'wpsstm-track-info', 
            __('Track','wpsstm'),
            array(__class__,'metabox_music_infos_content'),
            wpsstm()->post_type_track, 
            'after_title', 
            'high' 
        );

        add_meta_box( 
            'wpsstm-track-options', 
            __('Track Settings','wpsstm'),
            array($this,'metabox_track_options_content'),
            wpsstm()->post_type_track, 
            'side', //context
            'default' //priority
        );

    }
    
    static function metabox_music_infos_content( $post ){
        
        $post_type = get_post_type($post);
        
        switch($post_type){
                
            case wpsstm()->post_type_artist:
                
                //artist
                echo self::get_edit_artist_input($post->ID);
                
            break;
                
                
            case wpsstm()->post_type_album:
                
                //artist
                echo self::get_edit_artist_input($post->ID);
                //album
                echo self::get_edit_album_input($post->ID);
                
            break;
                
                
            case wpsstm()->post_type_track:
                
                //artist
                echo self::get_edit_artist_input($post->ID);
                //album
                echo self::get_edit_album_input($post->ID);
                //title
                echo self::get_edit_track_title_input($post->ID);
                //length
                echo self::get_edit_track_length_input($post->ID);
                
            break;
                
        }

        wp_nonce_field( 'wpsstm_music_details_meta_box', 'wpsstm_music_details_meta_box_nonce' );

    }
    
    static function get_edit_track_title_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;
        $input_attr = array(
            'id' => 'wpsstm-track-title',
            'name' => 'wpsstm_track_title',
            'value' => wpsstm_get_post_track($post_id),
            'icon' => '<i class="fa fa-music" aria-hidden="true"></i>',
            'label' => __("Track title",'wpsstm'),
            'placeholder' => __("Enter track title here",'wpsstm')
        );
        return wpsstm_get_backend_form_input($input_attr);
    }
    
    static function get_edit_artist_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;

        $input_attr = array(
            'id' => 'wpsstm-artist',
            'name' => 'wpsstm_artist',
            'value' => wpsstm_get_post_artist($post_id),
            'icon' => '<i class="fa fa-user-o" aria-hidden="true"></i>',
            'label' => __("Artist",'wpsstm'),
            'placeholder' => __("Enter artist here",'wpsstm')
        );
        return wpsstm_get_backend_form_input($input_attr);
    }
    
    static function get_edit_album_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;
        
        $input_attr = array(
            'id' => 'wpsstm-album',
            'name' => 'wpsstm_album',
            'value' => wpsstm_get_post_album($post_id),
            'icon' => '<i class="fa fa-bars" aria-hidden="true"></i>',
            'label' => __("Album",'wpsstm'),
            'placeholder' => __("Enter album here",'wpsstm')
        );
        return wpsstm_get_backend_form_input($input_attr);
    }
    
    static function get_edit_track_length_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;

        $input_attr = array(
            'id' => 'wpsstm-length',
            'name' => 'wpsstm_length',
            'value' => wpsstm_get_post_duration($post_id,true),
            'icon' => '<i class="fa fa-music" aria-hidden="true"></i>',
            'label' => __("Duration (milliseconds)",'wpsstm'),
            'placeholder' => __("Enter length here",'wpsstm')
        );
        return wpsstm_get_backend_form_input($input_attr);
    }
    
    function metabox_track_options_content( $post ){
        global $wpsstm_track;
        
        $classes =  array('wpsstm-action-popup button');

        $attr = array(
            'href' =>       $wpsstm_track->get_track_action_url('manage'),
            'class' =>      implode(' ',$classes),
            'target' =>     '_blank'
        );
        
        $attr_str = wpsstm_get_html_attr($attr);
        printf('<a %s>%s</a>',$attr_str,__('Playlists manager','wpsstm'));
    }
    
    /**
    Save track field for this post
    **/
    
    function metabox_save_music_details( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_music_details_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_music_details_meta_box_nonce'], 'wpsstm_music_details_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_music_details_meta_box_nonce']);

        //sanitize datas
        $artist = ( isset($_POST[ 'wpsstm_artist' ]) ) ? $_POST[ 'wpsstm_artist' ] : null;
        $album = ( isset($_POST[ 'wpsstm_album' ]) ) ? $_POST[ 'wpsstm_album' ] : null;
        $title = ( isset($_POST[ 'wpsstm_track_title' ]) ) ? $_POST[ 'wpsstm_track_title' ] : null;
        $length = ( isset($_POST[ 'wpsstm_length' ]) && ctype_digit($_POST[ 'wpsstm_length' ]) ) ? ( (int)$_POST[ 'wpsstm_length' ] ) : null; //ms
        
        
        $post_type = get_post_type($post_id);
        
        switch($post_type){
                
            case wpsstm()->post_type_artist:
                
                //artist
                self::save_track_artist($post_id, $artist);

            break;
                
                
            case wpsstm()->post_type_album:
                
                //artist
                self::save_track_artist($post_id, $artist);
                //album
                self::save_track_album($post_id, $album);
                
            break;
                
                
            case wpsstm()->post_type_track:
                
                //artist
                self::save_track_artist($post_id, $artist);
                //album
                self::save_track_album($post_id, $album);
                //title
                self::save_track_title($post_id, $title);
                //length
                self::save_track_duration($post_id, $length);

            break;
        }

    }

    private static function save_music_term($post_id, $taxonomy, $value = null){

        if ( $old_terms = wp_get_post_terms( $post_id, $taxonomy ) ){
            
            foreach ($old_terms as $old_term){

                //delete previous terms if unique
                if ( $old_term && ($value !== $old_term->name) && $old_term->count <= 1 ){
                    wp_delete_term( $old_term->term_id, $taxonomy );
                }
            }

        }

        return wp_set_post_terms( $post_id,$value, $taxonomy, false);

    }
    
    static function save_track_title($post_id, $value = null){
        return self::save_music_term($post_id,WPSSTM_Core_Tracks::$track_taxonomy,$value);
    }
    
    static function save_track_artist($post_id, $value = null){
        return self::save_music_term($post_id,WPSSTM_Core_Tracks::$artist_taxonomy,$value);
    }
    
    static function save_track_album($post_id, $value = null){
        return self::save_music_term($post_id,WPSSTM_Core_Tracks::$album_taxonomy,$value);
    }
    
    static function save_track_duration($post_id, $value = null){
        $value = filter_var($value, FILTER_VALIDATE_INT); //cast to int
        if (!$value){
            delete_post_meta( $post_id, self::$duration_metakey );
        }else{
            update_post_meta( $post_id, self::$duration_metakey, $value );
        }
    }
    
    static function save_image_url($post_id, $value = null){
        $value = filter_var($value, FILTER_VALIDATE_URL);
        if (!$value){
            delete_post_meta( $post_id, self::$image_url_metakey );
        }else{
            update_post_meta( $post_id, self::$image_url_metakey, $value );
        }
    }

    function shortcode_track( $atts ) {
        $output = null;

        // Attributes
        $default = array(
            'post_id'   => null,
            'title'     => null,
            'artist'    => null,
            'album'     => null,
        );
        
        $atts = shortcode_atts($default,$atts);
        $track = new WPSSTM_Track();
        $track->from_array($atts);

        if ( $track->validate_track() === true ){
            $output = $track->get_track_html();
            $output = sprintf('<div class="wpsstm-standalone-track">%s</div>',$output);
        }

        return $output;

    }
    
    function ajax_update_track_links_order(){
        $ajax_data = wp_unslash($_POST);

        $track_id = wpsstm_get_array_value(array('track_id'),$ajax_data);
        $track = new WPSSTM_Track($track_id);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data,
            'track'     => $track->to_array(),
        );
        
        $link_ids = isset($ajax_data['link_ids']) ? $ajax_data['link_ids'] : null;
        $success = $track->update_links_order($link_ids);

        if ( is_wp_error($success) ){
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = $success;
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }

    function ajax_track_get_links(){
        
        global $wpsstm_track;

        $ajax_data = wp_unslash($_POST);

        $wpsstm_track = new WPSSTM_Track();
        $wpsstm_track->from_array($ajax_data['track']);
        
        $result = array(
            'input'         => $ajax_data,
            'timestamp'     => current_time('timestamp'),
            'error_code'    => null,
            'message'       => null,
            'track'         => $wpsstm_track,
            'success'       => false,
        );
   
        ob_start();
        wpsstm_locate_template( 'content-track-links.php', true, false );
        $content = ob_get_clean();

        $result['html'] = $content;
        $result['success'] = true;
        
        $result['track'] = $wpsstm_track->to_array(); //maybe we have a new post ID here, if the track has been created

        header('Content-type: application/json');
        wp_send_json( $result );

    }
    
    function ajax_track_start(){

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
        
        
        $success = $track->insert_now_playing();
        
        if ( is_wp_error($success) ){
            $result['error_code'] = $success->get_error_code();
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = $success;
        }
        
        header('Content-type: application/json');
        wp_send_json( $result );
        
    }
    
    function ajax_track_toggle_favorite(){
        $ajax_data = wp_unslash($_POST);
        $do_love = wpsstm_get_array_value('do_love',$ajax_data);
        $do_love = filter_var($do_love, FILTER_VALIDATE_BOOLEAN); //cast to bool

        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);
        
        $result = array(
            'input'         => $ajax_data,
            'do_love'       => $do_love,
            'timestamp'     => current_time('timestamp'),
            'error_code'    => null,
            'message'       => null,
            'success'       => false,
            'track'         => $track->to_array(),
        );

        $success = $track->toggle_favorite($do_love);

        if ( is_wp_error($success) ){
            $result['error_code'] = $success->get_error_code();
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = true;
        }

        header('Content-type: application/json');
        wp_send_json( $result );
    }
    
    function ajax_subtrack_dequeue(){
        $ajax_data = wp_unslash($_POST);

        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);
        $tracklist = $track->tracklist;
        
        $result = array(
            'input'         => $ajax_data,
            'timestamp'     => current_time('timestamp'),
            'error_code'    => null,
            'message'       => null,
            'success'       => false,
            'track'         => $track->to_array(),
        );

        $success = $tracklist->dequeue_track($track);
        
        if ( is_wp_error($success) ){
            $result['error_code'] = $success->get_error_code();
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = true;
        }

        header('Content-type: application/json');
        wp_send_json( $result );
        
    }
    
    function ajax_update_subtrack_position(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );
        
        $subtrack_id = wpsstm_get_array_value(array('track','subtrack_id'),$ajax_data);
        $result['subtrack_id'] = $subtrack_id;
        
        $new_pos = wpsstm_get_array_value('new_pos',$ajax_data);
        $result['new_pos'] = $new_pos;
        
        $subtrack_post = WPSSTM_Core_Tracks::get_subtrack_post($subtrack_id);
        $track = new WPSSTM_Track($subtrack_post);
        
        $result['track'] = $track->to_array();

        $success = $track->move_subtrack($new_pos);

        if ( is_wp_error($success) ){
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = $success;
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_track_trash(){
        $ajax_data = wp_unslash($_POST);

        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);
        
        $result = array(
            'input'         => $ajax_data,
            'timestamp'     => current_time('timestamp'),
            'error_code'    => null,
            'message'       => null,
            'success'       => false,
            'track'         => $track->to_array(),
        );

        $success = $track->trash_track();

        if ( is_wp_error($success) ){
            $result['error_code'] = $success->get_error_code();
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = true;
        }

        header('Content-type: application/json');
        wp_send_json( $result );
    }

    function test_autolink_ajax(){
        
        if ( is_admin() ) return;
    
        $_POST = array(
            'track' => array('artist'=>'U2','title'=>'Sunday Bloody Sunday')
        );
        
        WP_SoundSystem::debug_log($_POST,'testing autolink AJAX');
        
        $this->ajax_track_get_links();
    }

    function delete_track_links($post_id){
        
        if ( get_post_type($post_id) != wpsstm()->post_type_track ) return;
        
        //get all links
        $track = new WPSSTM_Track($post_id);
        
        $link_args = array(
            'posts_per_page' => -1,
            'fields'  =>        'ids',
            'post_status'=>     'any',
        );
        
        $links_query = $track->query_links($link_args);
        $deleted = 0;
        
        foreach($links_query->posts as $link_id){
            if ( $success = wp_delete_post($link_id,true) ){
                $deleted ++;
            }
        }

        if ($deleted){
            //$track->track_log( json_encode(array('post_id'=>$post_id,'links'=>$links_query->post_count,'trashed'=>$deleted)),"WPSSTM_Post_Tracklist::delete_track_links()");
        }

    }
    
    /*
    Delete subtracks when a track is trashed
    */
    
    function delete_subtracks($post_id){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        if ( get_post_type($post_id) != wpsstm()->post_type_track ) return;
        $rowquerystr = $wpdb->prepare( "DELETE FROM `$subtracks_table` WHERE track_id = '%s'",$post_id );
        
        return $wpdb->get_results ( $rowquerystr );
    }
    
    /*
    When deleting a post, remove the terms attached to it if they are attached only to this post.
    */
    
    function delete_empty_music_terms($post_id){
        global $wpdb;
        
        $allowed_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_album,
            wpsstm()->post_type_track,
        );
        
        $taxonomies = array(
            WPSSTM_Core_Tracks::$artist_taxonomy,
            WPSSTM_Core_Tracks::$track_taxonomy,
            WPSSTM_Core_Tracks::$album_taxonomy
        );
        
        if ( !in_array(get_post_type($post_id),$allowed_types ) ) return;

        $args = array();
        $terms = wp_get_post_terms( $post_id, $taxonomies, $args );
        
        foreach((array)$terms as $term){
            if ( $term->count <= 0 ){
                //WP_SoundSystem::debug_log($term,'delete unique term');
                wp_delete_term( $term->term_id, $term->taxonomy );
            }
        }
    }

    /*
    Get tracks that do not belong to any playlists
    //TOUFIX very slow query, freezes de settings page when there is a lot of tracks.
    //store in transient ? Do in it two steps (query links - delete links) ?
    */
    static function get_orphan_track_ids(){
        global $wpdb;
        $bot_id = wpsstm()->get_options('bot_user_id');
        if ( !$bot_id ) return;

        //get bot tracks
        $orphan_tracks_args = array(
            'post_type' =>              wpsstm()->post_type_track,
            'post_status' =>            'any',
            'posts_per_page'=>          -1,
            'fields' =>                 'ids',
            'subtrack_exclude' =>       true,
        );
        
        $query = new WP_Query( $orphan_tracks_args );

        return $query->posts;
        
    }
    
    function the_track_post_title($title,$post_id){

        //post type check
        $post_type = get_post_type($post_id);
        if ( $post_type !== wpsstm()->post_type_track ) return $title;
        
        $track = new WPSSTM_Track($post_id);

        return (string)$track; // = __toString()
    }
    
    static function get_user_now_playing($user_id = null){
        
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;
        
        if ( !$nowplaying_id = wpsstm()->get_options('nowplaying_id') ) return;

        $track_args = array(
            'posts_per_page'=>          1,
            'post_type' =>              wpsstm()->post_type_track,
            'subtrack_query' =>         true,
            'subtrack_playing' =>       true,
            'subtrack_author' =>        $user_id,
            'orderby'=>                 'subtrack_time',
            'order'=>                   'DESC',
        );

        $query = new WP_Query( $track_args );

        $post = isset($query->posts[0]) ? $query->posts[0] : null;
        if ( !$post ) return;
        
        $track = new WPSSTM_Track($post);

        return $track;
    }
    
    static function get_last_user_favorite($user_id = null){

        if ( !$love_id = WPSSTM_Core_User::get_user_favorites_tracklist_id( $user_id ) ) return;

        $track_args = array(
            'post_type' =>              wpsstm()->post_type_track,
            'posts_per_page'=>          1,
            'subtrack_query' =>         true,
            'tracklist_id' =>           $love_id,
            'orderby'=>                 'subtrack_time',
            'order'=>                   'DESC',
        );

        $query = new WP_Query( $track_args );

        $post = isset($query->posts[0]) ? $query->posts[0] : null;
        if ( !$post ) return;
        
        $track = new WPSSTM_Track($post);

        return $track;
    }
    
    static function get_subtrack_post($subtrack_id){
        
        $track_args = array(
            'posts_per_page'=>          1,
            'post_type' =>              wpsstm()->post_type_track,
            'subtrack_query' =>         true,
            'subtrack_id' =>            $subtrack_id
        );

        $query = new WP_Query( $track_args );
        $posts = $query->posts;
        
        $post = isset($posts[0]) ? $posts[0] : null;
        
        return $post;
    }
    
}

function wpsstm_tracks_init(){
    new WPSSTM_Core_Tracks();
}

add_action('wpsstm_init','wpsstm_tracks_init');