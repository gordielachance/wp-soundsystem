<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/

class WPSSTM_Core_Tracklists{

    static $qvar_tracklist_action = 'tracklist-action';
    static $qvar_loved_tracklists = 'loved-tracklists';
    static $favorites_tracklist_usermeta_key = 'wpsstm_favorites_tracklist_id';
    static $loved_tracklist_meta_key = 'wpsstm_user_favorite';

    function __construct() {
        global $wpsstm_tracklist;
        
        require_once(wpsstm()->plugin_dir . 'wpsstm-core-wizard.php');

        //initialize global (blank) $wpsstm_tracklist so plugin never breaks when calling it.
        $wpsstm_tracklist = new WPSSTM_Post_Tracklist();

        add_filter( 'query_vars', array($this,'add_tracklist_query_vars'));

        add_action( 'template_redirect', array($this,'handle_tracklist_action'));
        add_filter( 'template_include', array($this,'tracklist_template'));

        add_action( 'add_meta_boxes', array($this, 'metabox_tracklist_register'));
        
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
        add_action( 'the_post', array($this,'the_tracklist'),10,2);
        add_action( 'current_screen',  array($this, 'the_single_backend_tracklist'));
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_loved_tracklists') );
        add_filter( 'posts_join', array($this,'tracklist_query_join_subtracks_table'), 10, 2 );
        add_filter( 'posts_where', array($this,'tracklist_query_where_tracklist_id'), 10, 2 );

        //post content
        add_filter( 'the_content', array($this,'content_append_tracklist_table') );
        
        //tracklist shortcode
        add_shortcode( 'wpsstm-tracklist',  array($this, 'shortcode_tracklist'));
        
        /*
        AJAX
        */

        //refresh tracklist
        add_action('wp_ajax_wpsstm_refresh_tracklist', array($this,'ajax_refresh_tracklist'));
        add_action('wp_ajax_nopriv_wpsstm_refresh_tracklist', array($this,'ajax_refresh_tracklist'));
        
        //toggle favorite tracklist
        add_action('wp_ajax_wpsstm_toggle_favorite_tracklist', array($this,'ajax_toggle_favorite_tracklist'));
        add_action('wp_ajax_nopriv_wpsstm_toggle_favorite_tracklist', array($this,'ajax_toggle_favorite_tracklist')); //so we can output the non-logged user notice
        
        //subtracks
        add_action('wp_ajax_wpsstm_update_subtrack_position', array($this,'ajax_update_subtrack_position'));
        add_action('wp_ajax_wpsstm_unlink_subtrack', array($this,'ajax_unlink_subtrack'));
        //check/uncheck tracklist as parent
        add_action('wp_ajax_wpsstm_toggle_playlist_subtrack', array($this,'ajax_toggle_playlist_subtrack'));
        
        /*
        DB relationships
        */
        add_action( 'before_delete_post', array($this,'delete_subtrack_tracklist_id') );
        add_action( 'delete_post', array($this,'delete_tracklist_subtracks') );
        

    }

    function add_tracklist_query_vars($vars){
        $vars[] = self::$qvar_tracklist_action;
        $vars[] = self::$qvar_loved_tracklists;
        return $vars;
    }

    function register_tracklists_scripts_styles_shared(){
        
        //JS
        wp_register_script( 'wpsstm-tracklists', wpsstm()->plugin_url . '_inc/js/wpsstm-tracklists.js', array('jquery','jquery-ui-sortable','jquery-ui-dialog','jquery.toggleChildren','wpsstm-tracks'),wpsstm()->version, true );
        
    }
    
    /*
    Register the global $wpsstm_tracklist obj (hooked on 'the_post' action) for tracklists
    For single tracks, check the_track function in -core-tracks.php
    */
    
    function the_tracklist($post,$query){
        global $wpsstm_tracklist;

        if ( in_array($query->get('post_type'),wpsstm()->tracklist_post_types) ){
            //set global $wpsstm_tracklist
            $wpsstm_tracklist = new WPSSTM_Post_Tracklist($post->ID);
            $wpsstm_tracklist->index = $query->current_post;
        }
    }
    
    function the_single_backend_tracklist(){
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

    function ajax_toggle_favorite_tracklist(){
        
        $ajax_data = wp_unslash($_POST);

        $tracklist_id = wpsstm_get_array_value(array('tracklist','post_id'),$ajax_data);
        $tracklist = new WPSSTM_Post_Tracklist($tracklist_id);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false,
            'tracklist' => $tracklist->to_array(),
            'do_love'   => null,
        );

        if ( !get_current_user_id() ){
            
            $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
            $action_link = $tracklist->get_tracklist_action_url('toggle-favorite');
            
            $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($action_link),__('here','wpsstm'));
            $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
            $result['notice'] = sprintf('<p id="wpsstm-dialog-auth-notice">%s</p>',$wp_auth_text);

        }elseif ($tracklist->post_id ){
            
            $is_loved = $tracklist->is_tracklist_loved_by();
            $result['do_love'] = $do_love = !$is_loved;
            $success = $tracklist->love_tracklist($do_love);
            
            if ( $success ){
                if( is_wp_error($success) ){
                    $code = $success->get_error_code();
                    $result['message'] = $success->get_error_message($code); 
                }else{
                   $result['success'] = true; 
                }
            }
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
    
    function ajax_unlink_subtrack(){
        $ajax_data = wp_unslash($_POST);

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );
        
        $result['subtrack_id'] = $subtrack_id = wpsstm_get_array_value(array('track','subtrack_id'),$ajax_data);
        
        $track = new WPSSTM_Track();
        $track->populate_subtrack($subtrack_id);
        $result['track'] = $track->to_array();
        
        $success = $track->tracklist->unlink_subtrack($track);
        
        if ( is_wp_error($success) ){
            $code = $success->get_error_code();
            $result['message'] = $success->get_error_message($code);
        }else{
            $result['success'] = $success;
        }
   
        header('Content-type: application/json');
        wp_send_json( $result ); 
        
    }
    
    function ajax_toggle_playlist_subtrack(){
        
        $ajax_data = wp_unslash($_POST);

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );
        
        $tracklist_id  = isset($ajax_data['tracklist_id']) ? $ajax_data['tracklist_id'] : null;
        $track_id = isset($ajax_data['track_id']) ? $ajax_data['track_id'] : null;
        $track_action = isset($ajax_data['track_action']) ? $ajax_data['track_action'] : null;
        
        $tracklist = $result['tracklist'] = new WPSSTM_Post_Tracklist($tracklist_id);
        $result['tracklist'] = $tracklist->to_array();
        
        $track = new WPSSTM_Track($track_id);
        $result['track'] = $track->to_array();
        
        $result['message'] = $track_action;
        
        $success = false;

        if ($track_id && $tracklist->post_id && $track_action){

            switch($track_action){
                case 'append':
                    $success = $tracklist->save_subtrack($track);
                break;
                case 'unlink':
                    $success = $tracklist->unlink_subtrack($track);
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
        
    function ajax_refresh_tracklist(){
        global $wpsstm_tracklist;
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false,
            'new_html'  => null
        );
        
        $wpsstm_tracklist->tracklist_log($ajax_data,"ajax_refresh_tracklist()");
        
        $tracklist_id = $result['post_id'] = ( isset($ajax_data['post_id']) ) ? $ajax_data['post_id'] : null;

        if ($tracklist_id){
            
            //set global $wpsstm_tracklist
            $wpsstm_tracklist = new WPSSTM_Post_Tracklist($tracklist_id);
            $wpsstm_tracklist->options['remote_delay_min'] = 0; //will force tracklist refresh
            
            $result['new_html'] = $wpsstm_tracklist->get_tracklist_html();
            $result['success'] = true;
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


    function tracklist_template($template){
        global $wpsstm_tracklist;
        if( !$admin_action = get_query_var( self::$qvar_tracklist_action ) ) return $template;
        switch($admin_action){
            case 'export':
                the_post();//TOUFIX TOUCHECK useful ?
                $template = wpsstm_locate_template( 'tracklist-xspf.php' );
            break;
            default:
                the_post();//TOUFIX TOUCHECK useful ?
                $template = wpsstm_locate_template( 'tracklist.php' );
            break;
        }
        return $template;
        
    }

    
    function handle_tracklist_action(){
        global $post;
        
        //TOUFIX not working when posting the new-subtrack form,only working with URLS currently.
        
        $action = isset($_REQUEST[self::$qvar_tracklist_action]) ? $_REQUEST[self::$qvar_tracklist_action] : null;
        if (!$action) return;
        if (!$post) return;
        
        $tracklist = new WPSSTM_Post_Tracklist($post->ID);
        $success = null;
        
        switch($action){
            case 'toggle-favorite':
                $do_love = !$tracklist->is_tracklist_loved_by();
                $success = $tracklist->love_tracklist($do_love);
            case 'get-autorship':
                $success = $tracklist->get_autorship();
            break;
            case 'make-live':
                $success = $tracklist->toggle_playlist_type();
            break;
            case 'make-static':
                $success = $tracklist->toggle_playlist_type();
            break;
            case 'trash':
                $success = $tracklist->trash_tracklist();
            break;
            case 'unlink': //TOUFIX useful here ? not only JS ?
                $track_id = isset($_GET['track_id']) ? $_GET['track_id'] : null;
                if ($track_id){
                    $track = new WPSSTM_Track($track_id);
                    $success = $tracklist->unlink_subtrack($track);
                }
            break;
            case 'append-subtrack':
                $track_arr = isset($_REQUEST['wpsstm-new-subtrack']) ? $_REQUEST['wpsstm-new-subtrack'] : null;

                if ($track_arr){
                    $track = new WPSSTM_Track();
                    $track->from_array($track_arr);
                    $success = $tracklist->save_subtrack($track);
                }

            break;
        }
        
        if ($success){ //redirect with a success / error code
            
            $redirect_url = $tracklist->get_tracklist_action_url('render');

            if ( is_wp_error($success) ){
                $redirect_url = add_query_arg( array('wpsstm_error_code'=>$success->get_error_code()),$redirect_url );
            }else{
                $redirect_url = add_query_arg( array('wpsstm_success_code'=>$action),$redirect_url );
            }

            wp_safe_redirect($redirect_url);
            exit();
        }

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
        
        if ( !in_array($query->get('post_type'),wpsstm()->tracklist_post_types) ) return $join;

        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        if ( $tracklist_id = $query->get('tracklist_id') ) {
            $join .= sprintf(" INNER JOIN %s AS subtracks ON (%s.ID = subtracks.tracklist_id)",$subtracks_table,$wpdb->posts);
        }

        return $join;
    }
    
    function tracklist_query_where_tracklist_id($where,$query){
        global $wpdb;
        
        if ( !in_array($query->get('post_type'),wpsstm()->tracklist_post_types) ) return $where;

        if ( $tracklist_id = $query->get('tracklist_id') ) {
            $where .= sprintf(" AND subtracks.tracklist_id = %s",$subtrack_id);
        }
        return $where;
    }
    
    /*
    Unset tracklist occurences out of the subtracks table when it is deleted
    */
    
    function delete_subtrack_tracklist_id($post_id){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        $tracklist_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist,
        );

        if ( !in_array(get_post_type($post_id),$tracklist_post_types) ) return;

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