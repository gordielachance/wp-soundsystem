<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/

class WPSSTM_Core_Tracklists{
    
    static $order_meta_name = 'wpsstm_order';
    static $playable_meta_name = 'wpsstm_playable';

    function __construct() {
        global $wpsstm_tracklist;
        
        /*
        populate single global tracklist.
        Be sure it works frontend, backend, and on post-new.php page
        */
        $wpsstm_tracklist = new WPSSTM_Post_Tracklist();
        add_action( 'wp',  array($this, 'populate_global_tracklist_frontend'),1 );
        add_action( 'admin_head',  array($this, 'populate_global_tracklist_backend'),1);
        add_action( 'the_post', array($this,'populate_global_tracklist_loop'),10,2);

        //rewrite rules
        add_action( 'init', array($this, 'tracklists_rewrite_rules') );
        add_filter( 'query_vars', array($this,'add_tracklist_query_vars') );
        add_filter( 'upload_mimes', array($this,'enable_xspf_uploads') );

        add_action( 'wp', array($this,'handle_tracklist_action'), 8);
        
        add_filter( 'template_include', array($this,'single_tracklist_template') );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracklists_scripts_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracklists_scripts_styles' ) );

        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_playlist), array(__class__,'tracks_count_column_register') );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_radio), array(__class__,'tracks_count_column_register') );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array(__class__,'tracks_count_column_register') );
        
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_playlist), array(__class__,'favorited_tracklist_column_register') );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_radio), array(__class__,'favorited_tracklist_column_register') );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array(__class__,'favorited_tracklist_column_register') );
        
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_playlist), array(__class__,'tracklists_columns_content') );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_radio), array(__class__,'tracklists_columns_content') );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_album), array(__class__,'tracklists_columns_content') );
        

        //tracklist queries
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_loved_tracklists') );
        //TOUFIX used ? not a duplicate of stuff in core tracks ?

        //post content
        add_filter( 'the_title', array($this, 'filter_imported_playlist_title'), 9, 2 );
        add_filter( 'the_title', array($this, 'filter_tracklist_empty_title'), 10, 2 );
        add_filter( 'the_content', array($this,'content_append_tracklist_html') );
        
        //tracklist shortcode
        add_shortcode( 'wpsstm-tracklist',  array($this, 'shortcode_tracklist'));
        
        /*
        AJAX
        */
        
        add_action('wp_ajax_wpsstm_reload_tracklist', array($this,'ajax_reload_tracklist'));
        add_action('wp_ajax_nopriv_wpsstm_reload_tracklist', array($this,'ajax_reload_tracklist'));
        add_action('wp_ajax_wpsstm_tracklist_toggle_favorite', array($this,'ajax_toggle_favorite'));

        //subtracks
        add_action('wp_ajax_wpsstm_tracklist_new_subtrack', array($this,'ajax_new_subtrack'));
        
        /*
        DB relationships
        */
        add_action( 'before_delete_post', array($this,'unset_fromtracklist_references') );
        add_action( 'before_delete_post', array($this,'delete_tracklist_subtracks') );
        
        /*
        Backend
        */
        add_action( 'add_meta_boxes', array($this, 'metabox_tracklist_register') );
        add_action( 'save_post', array($this,'metabox_save_tracklist_options') );
        
        
        //sitewide favorites
        add_filter( 'wpsstm_get_subtracks', array($this, 'get_sitewide_favorites'),10,2 );

    }

    function add_tracklist_query_vars($vars){
        $vars[] = 'tracklists-favorited-by';
        $vars[] = 'pulse-max';
        return $vars;
    }
    
    /*
    Allow those mime types to be uploaded in WP
    TOUFIX TOUCHECK The problem here is time, sometimes the mimetype is incorrectly guessed (eg. 'text/xml' on MacOS).
    https://wordpress.stackexchange.com/questions/346533/how-to-enable-xspf-files-on-upload-mime-types-issue
    */
    
    function enable_xspf_uploads($mime_types){
        $mime_types['xspf'] = 'application/xspf+xml';
        //$mime_types['xspf'] = 'text/xml';
        return $mime_types;
    }

    function register_tracklists_scripts_styles(){

        //JS
        wp_enqueue_script( 'wpsstm-tracklists', wpsstm()->plugin_url . '_inc/js/wpsstm-tracklists.js', array('jquery','wp-mediaelement','wpsstm-functions','wpsstm-tracks','wpsstm-links','jquery-ui-sortable','jquery-ui-dialog'),wpsstm()->version, true );

        wp_register_script( 'wpsstm-tracklist-manager', wpsstm()->plugin_url . '_inc/js/wpsstm-tracklist-manager.js', array('jquery'),wpsstm()->version, true );
        
        if ( did_action('wpsstm-tracklist-manager-popup') ) {
            wp_enqueue_script( 'wpsstm-tracklist-manager' );
        }
        
        //CSS
        wp_enqueue_style('wp-mediaelement');

    }
    
    function metabox_tracklist_register(){
        
        $screen = get_current_screen();
        $post_id = get_the_ID();
        $post_type = $screen->post_type;
        $post_status = get_post_status($post_id);
        $is_radio_autodraft = ( ($post_type === wpsstm()->post_type_radio) && ($post_status === 'auto-draft') );
        
        if (!$is_radio_autodraft) {
            add_meta_box( 
                'wpsstm-tracklist', 
                __('Tracklist','wpsstm'),
                array($this,'metabox_playlist_content'),
                wpsstm()->tracklist_post_types,
                'normal', 
                'high' //priority 
            );
        }    

        add_meta_box( 
            'wpsstm-tracklist-options', 
            __('Tracklist Settings','wpsstm'),
            array($this,'metabox_tracklist_options_content'),
            wpsstm()->tracklist_post_types,
            'side', //context
            'default' //priority
        );
        
    }
    
    function metabox_playlist_content( $post ){
        global $wpsstm_tracklist;
        $output = $wpsstm_tracklist->get_tracklist_html();
        echo $output;
    }
    
    function metabox_save_tracklist_options( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_tracklist_options_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        if( !in_array($post_type,wpsstm()->tracklist_post_types ) ){
            return new WP_Error('wpsstm_invalid_tracklist',__('Invalid tracklist','wpsstm'));
        }

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_tracklist_options_meta_box_nonce'], 'wpsstm_tracklist_options_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        if ( !$input_data = wpsstm_get_array_value('wpsstm_tracklist_options',$_POST) ) return;
        
        ////
        ////

        $tracklist = new WPSSTM_Post_Tracklist($post_id);
        
        //cache timeout
        $cache_min = wpsstm_get_array_value('cache_min',$input_data);

        if ( is_numeric($cache_min) ){
            update_post_meta( $post_id, WPSSTM_Core_Radios::$cache_timeout_meta_name,$cache_min * MINUTE_IN_SECONDS);
        }else{
            delete_post_meta( $post_id, WPSSTM_Core_Radios::$cache_timeout_meta_name);
        }
        
        //playable
        $playable = (bool)wpsstm_get_array_value('playable',$input_data);
        update_post_meta( $post_id, WPSSTM_Core_Tracklists::$playable_meta_name,$playable);
        
        //order
        $order = wpsstm_get_array_value('order',$input_data);
        if ($order){
            $order = strtoupper($order);
            update_post_meta( $post_id, WPSSTM_Core_Tracklists::$order_meta_name,$order );
        }else{
            delete_post_meta( $post_id, WPSSTM_Core_Tracklists::$order_meta_name );
        }

        //reload settings
        $tracklist->populate_tracklist_post();

    }

    
    function metabox_tracklist_options_content( $post ){
        
        global $wpsstm_tracklist;
        
        //playable
        $option = $wpsstm_tracklist->get_options('playable');

        $input = sprintf(
            '<input type="checkbox" name="%s[playable]" value="on" %s />',
            'wpsstm_tracklist_options',
            checked($option,true, false)
        );

        printf('<p>%s <label>%s</label></p>',$input,__('Player','wpsstm'));
        
        //sort
        $option = $wpsstm_tracklist->get_options('order');

        $input_asc = sprintf(
            '<input type="radio" name="%s[order]" value="ASC" %s /><label>%s</label>',
            'wpsstm_tracklist_options',
            checked($option,'ASC', false),
            'ASC'
        );
        $input_desc = sprintf(
            '<input type="radio" name="%s[order]" value="DESC" %s /><label>%s</label>',
            'wpsstm_tracklist_options',
            checked($option,'DESC', false),
            'DESC'
        );

        printf('<p><strong>%s</strong></br>%s</p>',__('Sort','wpsstm'),$input_asc . ' ' . $input_desc);

        if ($wpsstm_tracklist->tracklist_type === 'live' ) {

            //cache min
            if ( $cache_timeout = $wpsstm_tracklist->get_options('cache_timeout') ){
                $cache_min = $cache_timeout / MINUTE_IN_SECONDS;
            }else{
                $cache_min = 0;
            }

            $input = sprintf(
                '<input type="number" name="%s[cache_min]" size="4" min="0" value="%s" />',
                'wpsstm_tracklist_options',
                $cache_min
            );
            
            printf('<p><strong>%s</strong> <small>(%s)</small></br>%s</p>',__('Cache timeout','wpsstm'),__('minutes','wpsstm'),$input);
        }
        
        //edit tracks bt
        $post_links_url = $wpsstm_tracklist->get_backend_tracks_url();
        printf('<a href="%s" class="button">%s</a>',$post_links_url,__('Filter tracks','wpsstm'));
        
        wp_nonce_field( 'wpsstm_tracklist_options_meta_box', 'wpsstm_tracklist_options_meta_box_nonce' );
    }

    function ajax_reload_tracklist(){

        $ajax_data = wp_unslash($_POST);
        $post_id = wpsstm_get_array_value(array('tracklist','post_id'),$ajax_data);
        
        $tracklist = new WPSSTM_Post_Tracklist($post_id);
        $tracklist->remove_import_timestamp();
        
        $html = $tracklist->get_tracklist_html();

        $result = array(
            'success' =>    true,
            'message' =>    null,
            'input' =>      $ajax_data,
            'tracklist' =>  $tracklist->to_array(),
            'html' =>       $html,
        ); 

        header('Content-type: application/json');
        wp_send_json( $result ); 
        
    }
    
    function ajax_toggle_favorite(){
        $ajax_data = wp_unslash($_POST);
        $do_love = wpsstm_get_array_value('do_love',$ajax_data);
        $do_love = filter_var($do_love, FILTER_VALIDATE_BOOLEAN); //cast to bool

        $post_id = wpsstm_get_array_value(array('tracklist','post_id'),$ajax_data);
        $tracklist = new WPSSTM_Post_Tracklist($post_id);

        $result = array(
            'input'         => $ajax_data,
            'do_love'       => $do_love,
            'timestamp'     => current_time('timestamp'),
            'error_code'    => null,
            'message'       => null,
            'success'       => false,
            'tracklist'     => $tracklist->to_array(),
        );

        $success = $tracklist->love_tracklist($do_love);

        if ( is_wp_error($success) ){
            $result['error_code'] = $success->get_error_code();
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = true;
        }

        header('Content-type: application/json');
        wp_send_json( $result );
    }

    function ajax_new_subtrack(){
        $ajax_data = wp_unslash($_POST);
        
        $tracklist_id = wpsstm_get_array_value('tracklist_id',$ajax_data);
        
        $tracklist = new WPSSTM_Post_Tracklist($tracklist_id);

        $track = new WPSSTM_Track();
        $track->from_array($ajax_data['track']);
        
        $result = array(
            'input'         => $ajax_data,
            'timestamp'     => current_time('timestamp'),
            'error_code'    => null,
            'message'       => null,
            'success'       => false,
            'track'         => $track->to_array(),
            'tracklist'     => $tracklist->to_array()
        );

        $success = $tracklist->queue_track($track);

        if ( is_wp_error($success) ){
            $result['error_code'] = $success->get_error_code();
            $result['message'] = $success->get_error_message();
        }else{
            $result['success'] = $success;
            $result['html'] = $track->get_track_html();
        }

        header('Content-type: application/json');
        wp_send_json( $result );
    }

    public static function tracks_count_column_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['tracks-count'] = __('Tracks Count','wpsstm');
        
        return array_merge($before,$defaults,$after);
    }
    
    public static function favorited_tracklist_column_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['tracklist-favoritedby'] = __('Favorited','wpsstm');
        
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
            case 'tracklist-favoritedby':
                
                if ($list = $wpsstm_tracklist->get_favorited_by_list() ){
                    $output = $list;
                }

            break;
        }
        
         echo $output;

    }

    function filter_tracklist_empty_title( $title, $post_id = null ) {
        if ( !$title && in_array(get_post_type($post_id),wpsstm()->tracklist_post_types) ){
            $title = sprintf('(tracklist #%d)',$post_id);
        }
        return $title;
    }

    function content_append_tracklist_html($content){
        global $wpsstm_tracklist;

        if( !is_singular(wpsstm()->tracklist_post_types) ) return $content;
        if (!$wpsstm_tracklist) return $content;

        return  $content . $wpsstm_tracklist->get_tracklist_html();
    }
    
    function shortcode_tracklist( $atts ) {

        $output = null;

        // Attributes
        $default = array(
            'post_id'       => null,
        );
        $atts = shortcode_atts($default,$atts);

        if ( ( $post_type = get_post_type($atts['post_id']) ) && in_array($post_type,wpsstm()->tracklist_post_types) ){ //check that the post exists
            
            $tracklist = new WPSSTM_Post_Tracklist($atts['post_id']);
            $output = $tracklist->get_tracklist_html();
        }

        return $output;

    }
    
    function tracklists_rewrite_rules(){
        
        add_rewrite_tag(
            '%wpsstm_tracklist_data%',
            '([^&]+)'
        );

        foreach((array)wpsstm()->tracklist_post_types as $post_type){

            if ( !$post_type_obj = get_post_type_object( $post_type ) ) continue;

            //subtrack by tracklist position
            add_rewrite_rule( 
                sprintf('^%s/(\d+)/(\d+)/?',$post_type_obj->rewrite['slug']), // /music/TRACKLIST_TYPE/ID/POS
                sprintf('index.php?post_type=%s&tracklist_id=$matches[1]&subtrack_position=$matches[2]',wpsstm()->post_type_track),
                'top'
            );
            
            //tracklist ID action

            add_rewrite_rule( 
                sprintf('^%s/(\d+)/action/([^/]+)/?',$post_type_obj->rewrite['slug']), // /music/TRACKLIST_TYPE/ID/action/ACTION
                sprintf('index.php?post_type=%s&p=$matches[1]&wpsstm_action=$matches[2]',$post_type),
                'top'
            );

            //tracklist ID
            
            add_rewrite_rule( 
                sprintf('^%s/(\d+)/?',$post_type_obj->rewrite['slug']), // /music/TRACKLIST_TYPE/ID
                sprintf('index.php?post_type=%s&p=$matches[1]',$post_type),
                'top'
            );
            
        }
    }

    function populate_global_tracklist_frontend(){
        global $post;
        global $wpsstm_tracklist;

        $post_id = get_the_ID();
        $post_type = get_post_type($post_id);

        if ( !is_single() || !$post_id || !in_array($post_type,wpsstm()->tracklist_post_types) ) return;

        $wpsstm_tracklist = new WPSSTM_Post_Tracklist($post_id);

        $wpsstm_tracklist->tracklist_log("Populated global frontend tracklist");
        
    }
    
    function populate_global_tracklist_backend(){
        global $post;
        global $wpsstm_tracklist;
        
        //is posts.php or post-new.php ?
        $screen = get_current_screen();
        $is_tracklist_backend = in_array($screen->id,wpsstm()->tracklist_post_types);
        if ( !$is_tracklist_backend  ) return;

        $post_id = get_the_ID();
        $wpsstm_tracklist = new WPSSTM_Post_Tracklist($post_id);

        $wpsstm_tracklist->tracklist_log("Populated global backend tracklist");
        
    }

    /*
    Register the global within posts loop
    */
    
    function populate_global_tracklist_loop($post,$query){
        global $wpsstm_tracklist;
        if ( !in_array($query->get('post_type'),wpsstm()->tracklist_post_types) ) return;
        
        //set global $wpsstm_tracklist
        $is_already_populated = ($wpsstm_tracklist && ($wpsstm_tracklist->post_id == $post->ID) );
        if ($is_already_populated) return;

        $wpsstm_tracklist = new WPSSTM_Post_Tracklist($post->ID);
        $wpsstm_tracklist->index = $query->current_post;
    }

    function handle_tracklist_action(){
        global $wpsstm_tracklist;
        global $wp_query;
        
        $success = null;
        $redirect_url = null;
        $action_feedback = null;
        
        if ( !$action = get_query_var( 'wpsstm_action' ) ) return; //action does not exist
        if ( !in_array(get_query_var( 'post_type' ),wpsstm()->tracklist_post_types) ) return;
        $tracklist_data = get_query_var( 'wpsstm_tracklist_data' );

        switch($action){

            case 'queue': //add subtrack
                
                $track = new WPSSTM_Track();
                
                //build track from request
                if( $url_track = $wp_query->get( 'wpsstm_track_data' ) ){
                    $track->from_array($url_track);
                }

                $success = $wpsstm_tracklist->queue_track($track);
                
            break;
                
            case 'dequeue':
                
                $track = new WPSSTM_Track();
                
                //build track from request
                if( $url_track = $wp_query->get( 'wpsstm_track_data' ) ){
                    $track->from_array($url_track);
                }
                
                $success = $wpsstm_tracklist->dequeue_track($track);
                
                
            break;

            case 'favorite':
            case 'unfavorite':
                $do_love = ( $action == 'favorite');
                $success = $wpsstm_tracklist->love_tracklist($do_love);
            break;

            case 'trash':
                $success = $wpsstm_tracklist->trash_tracklist();
            break;

            case 'live':
            case 'static':
                $live = ( $action == 'live');
                $success = $wpsstm_tracklist->toggle_live($live);
                $redirect_url = get_permalink($wpsstm_tracklist->post_id);
            break;
            case 'refresh':
                //remove updated time
                $success = $wpsstm_tracklist->remove_import_timestamp();
            break;
            case 'get-autorship':
                $success = $wpsstm_tracklist->get_autorship();
                $redirect_url = get_permalink($wpsstm_tracklist->post_id);
            break;
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

    function single_tracklist_template($template){
        global $wpsstm_tracklist;

        //check query
        if ( is_404() ) return $template;
        if ( !is_single() ) return $template;
        $post_type = get_query_var( 'post_type' );
        if( !in_array($post_type,wpsstm()->tracklist_post_types) ) return $template; //post does not exists
        
        //check action
        $action = get_query_var( 'wpsstm_action' );
        if(!$action) return $template;

        switch($action){
            case 'export':
                $template = wpsstm_locate_template( 'tracklist-xspf.php' );
            break;
            case 'share':
                $template = wpsstm_locate_template( 'tracklist-share.php' );
            break;
        }

        return $template;
    }

    function pre_get_posts_loved_tracklists( $query ) {

        if ( !$user_id = $query->get( 'tracklists-favorited-by' ) ) return $query;
            
        if($user_id === true) $user_id = null; //no specific user ID set, get every favorited tracklists
        
        if ( $ids = WPSSTM_Core_User::get_favorited_tracklist_ids($user_id) ){
            $query->set ( 'post__in', $ids );
        }else{
            $query->set ( 'post__in', array(0) ); //force no results
        }
        
        

        return $query;
    }

    /*
    Remove references to this tracklist ID in the from_tracklist column of the subtracks table, when it is deleted.
    */
    
    function unset_fromtracklist_references($post_id){
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
    Delete the subtracks matching a tracklist ID when a tracklist post is deleted.
    */
    
    function delete_tracklist_subtracks($post_id){
        global $wpdb;
        $subtracks_table = $wpdb->prefix . wpsstm()->subtracks_table_name;

        if ( !in_array(get_post_type($post_id),wpsstm()->tracklist_post_types) ) return;

        return $wpdb->delete( 
            $subtracks_table, //table
            array('tracklist_id'=>$post_id) //where
        );
        
    }

    static function get_bot_tracklists_ids(){
        $bot_id = wpsstm()->get_options('bot_user_id');
        if ( !$bot_id ) return;

        //get bot tracks
        $args = array(
            'post_type' =>              wpsstm()->tracklist_post_types,
            'author' =>                 $bot_id,
            'post_status' =>            'any',
            'posts_per_page'=>          -1,
            'fields' =>                 'ids',
        );
        
        $query = new WP_Query( $args );
        return $query->posts;
    }

    //TOUFIX TOUIMPROVE we woudl like to have that title in the input title backend, too.
    function filter_imported_playlist_title( $title, $post_id = null ) {
        if ( in_array(get_post_type($post_id),wpsstm()->tracklist_post_types) ){
            $title = WPSSTM_Post_Tracklist::get_tracklist_title($post_id);
        }
        return $title;
    }
    
    function get_sitewide_favorites($tracks,$tracklist){
        $page_id = wpsstm()->get_options('sitewide_favorites_id');
        if ( $tracklist->post_id != $page_id ) return $tracks;
        
        $track_args = array(
            'posts_per_page'=>          50,
            'orderby'=>                 'subtrack_time',
            'order'=>                   'desc',
            'post_type' =>              wpsstm()->post_type_track,
            'subtrack_query' =>         true,
            'subtrack_favorites' =>     true,
            'fields' =>                 'subtrack=>track',
        );

        $query = new WP_Query( $track_args );
        $posts = $query->posts;

        $tracks = array();

        foreach($posts as $post){
            $subtrack = new WPSSTM_Track($post); //default
            $tracks[] = $subtrack;
        }

        return $tracks;
        
        
    }

}

function wpsstm_tracklists_init(){
    new WPSSTM_Core_Tracklists();
}



add_action('plugins_loaded','wpsstm_tracklists_init');