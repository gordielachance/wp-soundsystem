<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/

class WPSSTM_Core_Tracklists{

    static $qvar_loved_tracklists = 'loved-tracklists';
    static $favorites_tracklist_usermeta_key = 'wpsstm_favorites_tracklist_id';
    static $loved_tracklist_meta_key = 'wpsstm_user_favorite';
    static $qvar_tracklist_id = 'tracklist-id';

    function __construct() {
        global $wpsstm_tracklist;
        
        require_once(wpsstm()->plugin_dir . 'wpsstm-core-wizard.php');

        //initialize global (blank) $wpsstm_tracklist so plugin never breaks when calling it.
        $wpsstm_tracklist = new WPSSTM_Post_Tracklist();
        
        //rewrite rules
        add_action( 'init', array($this, 'tracklists_rewrite_rules') );
        add_filter( 'query_vars', array($this,'add_tracklist_query_vars') );
        //add_action( 'parse_query', array($this,'populate_global_tracklist'));
        add_action( 'the_post', array($this,'populate_loop_tracklist'),10,2);
        add_action( 'wp', array($this,'handle_tracklist_action'), 8);
        add_filter('template_include', array($this,'tracklist_template') );
        add_filter( 'post_link', array($this, 'filter_tracklist_link'), 10, 3 );

        add_action( 'add_meta_boxes', array($this, 'metabox_tracklist_register') );
        
        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracklists_scripts_styles_shared' ), 9 );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracklists_scripts_styles_shared' ), 9 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracklists_scripts_styles_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tracklists_scripts_styles_backend' ) );

        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_playlist), array(__class__,'tracks_count_column_register') );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array(__class__,'tracks_count_column_register') );

        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_playlist), array(__class__,'favorited_tracklist_column_register') );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_live_playlist), array(__class__,'favorited_tracklist_column_register') );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array(__class__,'favorited_tracklist_column_register') );

        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_playlist), array(__class__,'tracklists_columns_content') );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_live_playlist), array(__class__,'tracklists_columns_content') );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_album), array(__class__,'tracklists_columns_content') );

        //tracklist queries
        add_action( 'current_screen',  array($this, 'the_single_backend_tracklist'));
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_loved_tracklists') );
        add_filter( 'posts_join', array($this,'tracklist_query_join_subtracks_table'), 10, 2 );
        add_filter( 'posts_where', array($this,'tracklist_query_where_tracklist_id'), 10, 2 );
        add_filter( 'posts_where', array($this,'tracklist_query_where_track_id'), 10, 2 );

        //post content
        add_filter( 'the_content', array($this,'content_append_tracklist_table') );
        
        //tracklist shortcode
        add_shortcode( 'wpsstm-tracklist',  array($this, 'shortcode_tracklist'));
        
        /*
        AJAX
        */

        //subtracks
        add_action('wp_ajax_wpsstm_update_subtrack_position', array($this,'ajax_update_subtrack_position'));
        
        /*
        DB relationships
        */
        add_action( 'before_delete_post', array($this,'delete_subtrack_tracklist_id') );
        add_action( 'delete_post', array($this,'delete_tracklist_subtracks') );
        

    }

    function add_tracklist_query_vars($vars){
        $vars[] = self::$qvar_loved_tracklists;
        $vars[] = self::$qvar_tracklist_id;
        return $vars;
    }

    function register_tracklists_scripts_styles_shared(){
        
        //JS
        wp_register_script( 'wpsstm-tracklists', wpsstm()->plugin_url . '_inc/js/wpsstm-tracklists.js', array('jquery','jquery-ui-sortable','jquery-ui-dialog','jquery.toggleChildren','wpsstm-tracks'),wpsstm()->version, true );
        
    }
    
    function the_single_backend_tracklist(){ //TOUFIX TOUCHECK TOUREMOVE ?
        global $post;
        global $wpsstm_tracklist;
        $screen = get_current_screen();
        
        if ( ( $screen->base == 'post' ) && in_array($screen->post_type,wpsstm()->tracklist_post_types)  ){
            $post_id = isset($_GET['post']) ? $_GET['post'] : null;
            //set global $wpsstm_source
            $wpsstm_tracklist = new WPSSTM_Post_Tracklist($post_id);
        }
    }

    function enqueue_tracklists_scripts_styles_frontend(){
        //TO FIX TO CHECK post types ?
        wp_enqueue_script( 'wpsstm-tracklists' );
    }

    function enqueue_tracklists_scripts_styles_backend(){
        
        if ( !wpsstm()->is_admin_page() ) return;
        
        wp_enqueue_script( 'wpsstm-tracklists' );

    }

    
    function ajax_update_subtrack_position(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );
        
        $result['subtrack_id'] = $subtrack_id = wpsstm_get_array_value(array('track','subtrack_id'),$ajax_data);
        $new_pos = wpsstm_get_array_value('new_pos',$ajax_data);
        $result['new_pos'] = $new_pos;
        
        $track = new WPSSTM_Track();
        $track->populate_subtrack($subtrack_id);
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

    public static function tracks_count_column_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['tracks-count'] = __('Tracks','wpsstm');
        
        return array_merge($before,$defaults,$after);
    }
    
    public static function favorited_tracklist_column_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['tracklist-favorited'] = __('Favorited','wpsstm');
        
        return array_merge($before,$defaults,$after);
    }
    
    public static function tracklists_columns_content($column){
        global $wpsstm_tracklist;
        
        $output = '—';

        switch ( $column ) {
            case 'tracks-count':
                if ($tracks_count = $wpsstm_tracklist->get_subtracks_count() ){
                    $output = $tracks_count;
                }
            break;
            case 'tracklist-favorited':
                
                if ($list = $wpsstm_tracklist->get_loved_by_list() ){
                    $output = $list;
                }

            break;
        }
        
         echo $output;

    }

    function metabox_tracklist_register(){

        add_meta_box( 
            'wpsstm-tracklist', 
            __('Tracklist','wpsstm'),
            array($this,'metabox_tracklist_content'),
            wpsstm()->static_tracklist_post_types, 
            'normal', 
            'high' //priority 
        );
        
    }
    
    function metabox_tracklist_content( $post ){
        global $wpsstm_tracklist;
        
        $output = $wpsstm_tracklist->get_tracklist_html();
        echo $output;
    }

    function content_append_tracklist_table($content){
        global $post;
        global $wpsstm_tracklist;

        if( !is_singular(wpsstm()->tracklist_post_types) ) return $content;
        if (!$wpsstm_tracklist) return $content;

        return  $content . $wpsstm_tracklist->get_tracklist_html();
    }
    
    function shortcode_tracklist( $atts ) {

        global $post;
        global $wpsstm_tracklist;
        $output = null;

        // Attributes
        $default = array(
            'post_id'       => $post->ID,
            'max_rows'      => -1    
        );
        $atts = shortcode_atts($default,$atts);

        if ( ( $post_type = get_post_type($atts['post_id']) ) && in_array($post_type,wpsstm()->tracklist_post_types) ){ //check that the post exists
            
            //set global $wpsstm_tracklist
            $wpsstm_tracklist = new WPSSTM_Post_Tracklist($atts['post_id']);
            
            $output = $wpsstm_tracklist->get_tracklist_html();
        }

        return $output;

    }
    
    function tracklists_rewrite_rules(){
        
        flush_rewrite_rules(); //TOUFIX elsewhere
        
        add_rewrite_tag(
            '%wpsstm_action%',
            '([^&]+)',
            sprintf('%s=','wpsstm_action')
        );
        
        //all tracklists (static,live,album)
        foreach((array)wpsstm()->tracklist_post_types as $post_type){
            /* 
            < /music/TYPE/ID/ACTION - music/playlist/59086/?wpsstm_action=delete
            > /index.php?post_type=TYPE&p=ID&wpsstm_action=ACTION
            */
            
            $obj = get_post_type_object( $post_type );
            
            //tracklist ID action

            add_rewrite_rule( 
                sprintf('^%s/(\d+)/([^/]+)/?',$obj->rewrite['slug']), // /music/playlists/ID/ACTION
                sprintf('index.php?post_type=%s&p=$matches[1]&%s=$matches[2]',$post_type,WP_SoundSystem::$qvar_action),
                'top'
            );
            
             //tracklist ID
            
            add_rewrite_rule( 
                sprintf('^%s/(\d+)/?',$obj->rewrite['slug']), // /music/playlists/ID/ACTION
                sprintf('index.php?post_type=%s&p=$matches[1]',$post_type),
                'top'
            );
            
        }
    }
    
    function populate_global_tracklist($query){
        global $wpsstm_tracklist;
        
        $wpsstm_tracklist = new WPSSTM_Post_Tracklist();

        if( !$post_id = $query->get( 'p' ) ) return;
        $post_type = get_post_type($post_id);
        if( !in_array($post_type,wpsstm()->tracklist_post_types) ) return;
        
        $wpsstm_tracklist = new WPSSTM_Post_Tracklist($post_id);

    }
    
    /*
    Register the global $wpsstm_tracklist obj (hooked on 'the_post' action) for tracklists
    For single tracks, check the_track function in -core-tracks.php
    */
    
    function populate_loop_tracklist($post,$query){
        global $wpsstm_tracklist;
        if ( in_array($query->get('post_type'),wpsstm()->tracklist_post_types) ){
            //set global $wpsstm_tracklist
            $wpsstm_tracklist = new WPSSTM_Post_Tracklist($post->ID);
            $wpsstm_tracklist->index = $query->current_post;
        }
    }

    function handle_tracklist_action(){
        global $wpsstm_tracklist;
        global $wpsstm_track;
        
        //check post
        $id = get_query_var( 'p' );
        $post_type = get_post_type($id);
        if( !in_array($post_type,wpsstm()->tracklist_post_types) ) return; //post does not exists
        
        //check action
        $action = get_query_var( WP_SoundSystem::$qvar_action ); //WP_SoundSystem::$qvar_action
        if(!$action) return;
        
        //populate stuff
        $redirect_url = get_permalink($id);
        $subtrack_arr = get_query_var( WPSSTM_Core_Tracks::$qvar_subtrack );

        //action
        switch($action){

            case 'queue': //add subtrack
                $track = new WPSSTM_Track();
                $track->from_array($subtrack_arr);
                $success = $wpsstm_tracklist->save_subtrack($track);
            break;

            case 'favorite':
            case 'unfavorite':
                $do_love = ( $action == 'favorite');
                $success = $wpsstm_tracklist->love_tracklist($do_love);
            break;

            case 'trash':
                $success = $tracklist->trash_tracklist();
            break;

            case 'lock':
            case 'unlock':
                $live = ( $action == 'unlock');
                $success = $wpsstm_tracklist->toggle_live($live);
            break;
            case 'refresh':
                //remove updated time
                $success = delete_post_meta($wpsstm_tracklist->post_id,WPSSTM_Core_Live_Playlists::$time_updated_meta_name);
            break;
            case 'get-autorship':
                $success = $tracklist->get_autorship();
            break;
        }
        
        /*
        Redirection
        */
        /*
        if (!$success){
            $redirect_url = add_query_arg( array('wpsstm_error_code'=>$action),$redirect_url );
        }
        if ( is_wp_error($success) ){
            $redirect_url = add_query_arg( array('wpsstm_error_code'=>$success->get_error_code()),$redirect_url );
        }else{
            $redirect_url = add_query_arg( array('wpsstm_success_code'=>$action),$redirect_url );
        }

        wp_safe_redirect($redirect_url);
        exit();

        if($redirect){
            print_r($redirect);
            die();
        }
        */

        /*
        Ajax
        */
        $is_ajax = wpsstm_is_ajax();
        if ( $is_ajax ){
            $result = array(
                'input'     => $_REQUEST,
                //'track'     => $wpsstm_track->to_array(),
                'message'   => "couki",
                'notice'    => null,
                'success'   => false,
            );
            
            wpsstm()->debug_log("HANDLE TRACKLIST ACTION: " . $action);
            wpsstm()->debug_log($result);
            
            header('Content-type: application/json');
            wp_send_json( $result );   
        }

    }

    function tracklist_template($template){
        global $wpsstm_tracklist;

        //check query
        $post_type = get_query_var( 'post_type' );
        if( !in_array($post_type,wpsstm()->tracklist_post_types) ) return $template; //post does not exists
        
        //check action
        $action = get_query_var( WP_SoundSystem::$qvar_action ); //WP_SoundSystem::$qvar_action
        if(!$action) return $template;
        
        switch($action){
            case 'export':
                $template = wpsstm_locate_template( 'tracklist-xspf.php' );
            break;
            default:
                $template = wpsstm_locate_template( 'tracklist.php' );
            break;
        }
        return $template;
        
    }
    
    function filter_tracklist_link($url, $post, $leavename=false){
          //if ( false === strpos( $link, '%wpsstm_action%') ) return $link;
        
        return $url;
    }
    
    static function get_all_favorite_tracklist_ids(){
        global $wpdb;
        //get all subtracks metas
        $querystr = $wpdb->prepare( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = '%s'", 'wp_' . self::$favorites_tracklist_usermeta_key );
        $ids = $wpdb->get_col( $querystr);
        return $ids;
    }

    function pre_get_posts_loved_tracklists( $query ) {

        if ( $query->get( self::$qvar_loved_tracklists ) ){
            
            $ids = get_all_favorite_tracklist_ids();
            
            $query->set ( 'post__in', $ids ); 
            
        }

        return $query;
    }

    function tracklist_query_join_subtracks_table($join,$query){
        global $wpdb;
        
        //check this is a tracklist query
        //https://stackoverflow.com/a/7542708/782013
        $post_types = $query->get('post_type');
        $has_tracklist_type = (count(array_intersect((array)$post_types, wpsstm()->tracklist_post_types)) > 0);
        if ( !$has_tracklist_type ) return $join;

        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $tracklist_id = $query->get(self::$qvar_tracklist_id);
        $subtrack_id = $query->get(WPSSTM_Core_Tracks::$qvar_subtrack_id);
        
        if ( $tracklist_id || $subtrack_id ) {
            $join .= sprintf(" INNER JOIN %s AS subtracks ON (%s.ID = subtracks.tracklist_id)",$subtracks_table,$wpdb->posts);
        }

        return $join;
    }
    
    function tracklist_query_where_tracklist_id($where,$query){
        global $wpdb;
        
        //check this is a tracklist query
        //https://stackoverflow.com/a/7542708/782013
        $post_types = $query->get('post_type');
        $has_tracklist_type = (count(array_intersect((array)$post_types, wpsstm()->tracklist_post_types)) > 0);
        if ( !$has_tracklist_type ) return $where;

        if ( $tracklist_id = $query->get(self::$qvar_tracklist_id) ) {
            $where .= sprintf(" AND subtracks.tracklist_id = %s",$tracklist_id);
        }
        return $where;
    }
    
    function tracklist_query_where_track_id($where,$query){
        
        //check this is a tracklist query
        //https://stackoverflow.com/a/7542708/782013
        $post_types = $query->get('post_type');
        $has_tracklist_type = (count(array_intersect((array)$post_types, wpsstm()->tracklist_post_types)) > 0);
        if ( !$has_tracklist_type ) return $where;

        if ( !$subtrack_id = $query->get(WPSSTM_Core_Tracks::$qvar_subtrack_id) ) return $where;
        
        $where.= sprintf(" AND subtracks.track_id = %s",$subtrack_id);
        return $where;
    }
    
    /*
    Unset tracklist occurences out of the subtracks table when it is deleted
    */
    
    function delete_subtrack_tracklist_id($post_id){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        if ( !in_array(get_post_type($post_id),wpsstm()->tracklist_post_types) ) return;

        return $wpdb->update( 
            $subtracks_table, //table
            array('from_tracklist'=>''), //data
            array('from_tracklist'=>$post_id) //where
        );
    }
    
    /*
    Delete the tracklist related entries from the subtracks table when a tracklist post is deleted.
    */
    
    function delete_tracklist_subtracks($post_id){
        global $wpdb;

        if ( !in_array(get_post_type($post_id),wpsstm()->tracklist_post_types) ) return;

        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $success = $wpdb->delete( 
            $subtracks_table, //table
            array('tracklist_id'=>$post_id) //where
        );
        
        //pinned from... ID
        $success = $wpdb->update( 
            $subtracks_table, //table
            array('from_tracklist'=>''), //data
            array('from_tracklist'=>$post_id) //where
        );
        
    }
    
    /*
    Get the ID of the favorites tracklist for a user, or create it
    */
    
    static function get_user_favorites_id($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;

        $love_id = get_user_option( WPSSTM_Core_Tracklists::$favorites_tracklist_usermeta_key, $user_id );
        
        //tracklist doesn't exists
        if ( $love_id && !get_post_type($love_id) ){
            delete_user_option( $user_id, WPSSTM_Core_Tracklists::$favorites_tracklist_usermeta_key );
            $love_id = null;
        }
        
        if (!$love_id){
            
            /*
            create new tracklist
            */
            
            //capability check
            $post_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
            $required_cap = $post_type_obj->cap->create_posts;
            if ( !current_user_can($required_cap) ){
                return new WP_Error( 'wpsstm_track_no_edit_cap', __("You don't have the capability required to create tracklists.",'wpsstm') );
            }
            
            //user
            $user_info = get_userdata($user_id);
            
            $playlist_new_args = array(
                'post_type'     => wpsstm()->post_type_playlist,
                'post_status'   => 'publish',
                'post_author'   => $user_id,
                'post_title'    => sprintf(__("%s's favorites tracks",'wpsstm'),$user_info->user_login)
            );

            $success = wp_insert_post( $playlist_new_args, true );
            if ( is_wp_error($success) ) return $success;
            $love_id = $success;
            $meta = update_user_option( $user_id, WPSSTM_Core_Tracklists::$favorites_tracklist_usermeta_key, $love_id);
            
            wpsstm()->debug_log(array('user_id'=>$user_id,'post_id'=>$love_id,'meta'=>$meta),'created favorites tracklist');
            
        }
        
        return $love_id;
        
    }


}