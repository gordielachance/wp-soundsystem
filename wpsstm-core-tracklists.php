<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/
class WPSSTM_Core_Tracklists{
    
    static $qvar_tracklist_admin = 'admin-tracklist';
    static $qvar_tracklist_action = 'tracklist-action';
    static $qvar_loved_tracklists = 'loved-tracklists';
    static $loved_tracklist_meta_key = '_wpsstm_user_favorite';

    function __construct() {
        global $wpsstm_tracklist;
        
        require_once(wpsstm()->plugin_dir . 'wpsstm-core-wizard.php');

        //initialize global (blank) $wpsstm_tracklist so plugin never breaks when calling it.
        $wpsstm_tracklist = wpsstm_get_tracklist();

        add_filter( 'query_vars', array($this,'add_tracklist_query_vars'));

        add_action( 'template_redirect', array($this,'handle_tracklist_action'));
        add_filter( 'template_include', array($this,'tracklist_xspf_template'));

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
        add_filter( 'posts_join', array($this,'subtrack_tracklists_join_query'), 10, 2 );
        add_filter( 'posts_where', array($this,'subtrack_tracklists_where_query'), 10, 2 );

        //post content
        add_filter( 'the_content', array($this,'content_append_tracklist_table') );
        add_filter( 'the_content', array($this,'tracklist_admin') );
        
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
        
        /*
        DB relationships
        */
        add_action( 'delete_post', array($this,'delete_subtracks_tracklist_entry') );

    }

    function add_tracklist_query_vars($vars){
        $vars[] = self::$qvar_tracklist_admin;
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
            $wpsstm_tracklist = wpsstm_get_tracklist($post->ID);
            $wpsstm_tracklist->index = $query->current_post;
        }else{
            //reset blank $wpsstm_tracklist (this might be called within wp_reset_postdata and thus we should reset it)
            //TO FIX maybe that instead of this, we should have a fn wpsstm_reset_tracklistdata ?
            $wpsstm_tracklist = wpsstm_get_tracklist(); //TOFIXTOCHECK should it not be a regular tracklist ?
        }
    }
    
    function the_single_backend_tracklist(){
        global $post;
        global $wpsstm_tracklist;
        $screen = get_current_screen();
        
        if ( ( $screen->base == 'post' ) && in_array($screen->post_type,wpsstm()->tracklist_post_types)  ){
            $post_id = isset($_GET['post']) ? $_GET['post'] : null;
            //set global $wpsstm_source
            $wpsstm_tracklist = wpsstm_get_tracklist($post_id);
            $wpsstm_tracklist->options['autoplay'] = false;
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
    
    //load the admin template instead of regular content when 'admin-tracklist' is set
    function tracklist_admin($content){
        if ( $tracklist_admin = get_query_var( self::$qvar_tracklist_admin ) ){
            ob_start();
            wpsstm_locate_template( 'tracklist-admin.php', true, false );
            $content = ob_get_clean();
        }
        return $content;
    }

    function ajax_toggle_favorite_tracklist(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false
        );
        
        
        $tracklist_id = $result['post_id'] = ( isset($ajax_data['post_id']) ) ?     $ajax_data['post_id'] : null;
        $tracklist = wpsstm_get_tracklist($tracklist_id);
        $do_love = $result['do_love'] = ( isset($ajax_data['do_love']) ) ?          filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN) : null;

        if ( !get_current_user_id() ){
            
            $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
            if ($do_love){
                $action_link = $tracklist->get_tracklist_action_url('favorite');
            }else{
                $action_link = $tracklist->get_tracklist_action_url('unfavorite');
            }
            
            $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url($action_link),__('here','wpsstm'));
            $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
            $result['notice'] = sprintf('<p id="wpsstm-dialog-auth-notice">%s</p>',$wp_auth_text);

        }else{
            //ajax do send strings

            if ($tracklist->post_id && ($do_love!==null) ){
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
            $wpsstm_tracklist = wpsstm_get_tracklist($tracklist_id);
            $wpsstm_tracklist->is_expired = true; //will force tracklist refresh
            
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
            $wpsstm_tracklist = wpsstm_get_tracklist($atts['post_id']);
            
            $output = $wpsstm_tracklist->get_tracklist_html();
        }

        return $output;

    }


    function tracklist_xspf_template($template){
        if( !$admin_action = get_query_var( self::$qvar_tracklist_action ) ) return $template;
        if ( $admin_action != 'export' ) return $template;
        the_post();
        return wpsstm_locate_template( 'tracklist-xspf.php' );
    }
    
    function handle_tracklist_action(){
        global $post;
        if (!$post) return;
        
        if( !$action = get_query_var( self::$qvar_tracklist_action ) ) return;
        
        $tracklist = wpsstm_get_tracklist($post->ID);
        $success = null;

        switch($action){
                
            case 'new-subtrack':
                //create auto draft & assign tracklist
                $args = array(
                    'post_status' =>    'auto-draft',
                    'post_type' =>      wpsstm()->post_type_track
                );
                
                $track_id = wp_insert_post( $args, true );
                
                if ( !is_wp_error($track_id) ){
                    $success = $tracklist->append_subtrack_ids($track_id);

                    //$redirect_url = get_edit_post_link( $track_id ); //NOT WORKING; see https://stackoverflow.com/a/47038586/782013
                    $redirect_url = admin_url( '/post.php?post=' . $track_id . '&action=edit' );
                    
                    wp_safe_redirect($redirect_url);
                    exit();
                }
                
                
                //redirect to backend
            break;
            case 'refresh':
                //TOUFIX TOCHECK is working ?
                //maybe better to just delete the time updated meta so we force de tracklist update ?
                $tracklist->is_expired = true;
                $success = $tracklist->populate_subtracks();
            break;
            case 'favorite':
                $success = $tracklist->love_tracklist(true);
            break;
            case 'unfavorite':
                $success = $tracklist->love_tracklist(false);
            break;
            case 'export':
                //see tracklist_xspf_template
            break;
            case 'switch-status':
                $success = $tracklist->switch_status();
            break;
            case 'get-autorship':
                $success = $tracklist->get_autorship();
            break;
            case 'lock-tracklist':
                $success = $tracklist->convert_to_static_playlist();
            break;
            case 'unlock-tracklist':
                $success = $tracklist->convert_to_live_playlist();
            break;
            case 'trash':
                $success = $tracklist->trash_tracklist();
            break;
            case 'unlink':
                $track_id = isset($_GET['track_id']) ? $_GET['track_id'] : null;
                if ($track_id){
                    $track = new WPSSTM_Track($track_id);
                    $success = $tracklist->remove_subtrack_ids($track->post_id);
                }

            break;
        }
        
        if ($success){ //redirect with a success / error code
            $redirect_url = ( wpsstm_is_backend() ) ? get_edit_post_link( $tracklist->post_id ) : get_permalink($tracklist->post_id);

            if ( is_wp_error($success) ){
                $redirect_url = add_query_arg( array('wpsstm_error_code'=>$success->get_error_code()),$redirect_url );
            }else{
                $redirect_url = add_query_arg( array('wpsstm_success_code'=>$action),$redirect_url );
            }

            wp_safe_redirect($redirect_url);
            exit();
        }

    }
    
    function pre_get_posts_loved_tracklists( $query ) {

        if ( $user_id = $query->get( self::$qvar_loved_tracklists ) ){

            $meta_query = (array)$query->get('meta_query');

            $meta_query[] = array(
                'key'     => self::$loved_tracklist_meta_key,
                'value'   => $user_id,
            );

            $query->set( 'meta_query', $meta_query);
            
        }

        return $query;
    }

    function subtrack_tracklists_join_query($join,$query){
        global $wpdb;
        
        //TO FIX add post type check ?

        if ( $query->get('subtrack_id') ) {
            $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
            $join .= sprintf("INNER JOIN %s AS subtracks ON (%s.ID = subtracks.tracklist_id)",$subtracks_table,$wpdb->posts);
        }
        return $join;
    }
    
    function subtrack_tracklists_where_query($where,$query){
        
        //TO FIX add post type check ?

        if ( $subtrack_id = $query->get('subtrack_id') ) {
            $where .= sprintf(" AND subtracks.track_id = %s",$subtrack_id);
        }
        return $where;
    }
    
    /*
    Delete the tracklist related entries from the subtracks table when a tracklist post is deleted.
    */
    
    function delete_subtracks_tracklist_entry($post_id){
        global $wpdb;

        if ( !in_array(get_post_type($post_id),wpsstm()->tracklist_post_types) ) return;

        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;
        
        return $wpdb->delete( 
            $subtracks_table, //table
            array('tracklist_id'=>$post_id) //where
        );
    }

}