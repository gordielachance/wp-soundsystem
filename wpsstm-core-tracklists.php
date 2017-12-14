<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/

class WP_SoundSystem_Core_Tracklists{
    public $qvar_tracklist_action = 'tracklist-action';
    public $qvar_user_favorites = 'user-favorites';
    public $favorited_tracklist_meta_key = '_wpsstm_user_favorite';
    public $tracklist_post_types = array();
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Tracklists;
                    self::$instance->init();
            }
            return self::$instance;
    }

    private function __construct() { /* Do nothing here */ }

    function init(){
        
        require_once(wpsstm()->plugin_dir . 'wpsstm-core-wizard.php');
        
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
        
    }
    
    function setup_globals(){
        global $wpsstm_tracklist;
        $wpsstm_tracklist = new WP_SoundSystem_Remote_Tracklist(); //so we've got always it defined
        
        $this->tracklist_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );
        $this->static_tracklist_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist
        );
    }
    
    function setup_actions(){

        add_filter( 'query_vars', array($this,'add_tracklist_query_vars'));

        add_action( 'template_redirect', array($this,'handle_tracklist_action'));
        add_filter( 'template_include', array($this,'tracklist_xspf_template'));
        add_filter( 'template_include', array($this,'tracklist_popup_template'));
        
        add_action( 'template_redirect', array($this,'handle_tracklist_popup_form'));

        add_action( 'add_meta_boxes', array($this, 'metabox_tracklist_register'));
        
        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracklists_scripts_styles_shared' ), 9 );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracklists_scripts_styles_shared' ), 9 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracklists_scripts_styles_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tracklists_scripts_styles_backend' ) );

        add_filter( 'manage_posts_columns', array($this,'column_tracklist_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'column_tracklist_content'), 10, 2 );
        add_filter('manage_posts_columns', array($this,'tracks_column_playlist_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'tracks_column_playlist_content'), 10, 2 );
        add_filter( 'manage_posts_columns', array($this,'tracklist_column_lovedby_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'tracklist_column_lovedby_content'), 10, 2 );
        
        //tracklist queries
        add_action( 'the_post', array($this,'the_tracklist'),10,2);
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_user_favorites') );
        add_filter( 'posts_join', array($this,'subtrack_tracklists_join_query'), 10, 2 );
        add_filter( 'posts_where', array($this,'subtrack_tracklists_where_query'), 10, 2 );

        //post content
        add_filter( 'the_content', array($this,'content_append_tracklist_table'));
        
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
        


    }

    function add_tracklist_query_vars($vars){
        $vars[] = $this->qvar_tracklist_action;
        $vars[] = $this->qvar_user_favorites;
        return $vars;
    }

    function register_tracklists_scripts_styles_shared(){
        
        //JS
        wp_register_script( 'wpsstm-tracklists', wpsstm()->plugin_url . '_inc/js/wpsstm-tracklists.js', array('jquery','jquery-ui-sortable','jquery-ui-dialog','jquery.toggleChildren','wpsstm-tracks'),wpsstm()->version );
    }
    
    /*
    Register the global $wpsstm_tracklist obj (hooked on 'the_post' action)
    */
    
    function the_tracklist($post,$query){
        global $wpsstm_tracklist;
        
        $allowed_post_types = $this->tracklist_post_types;
        $allowed_post_types[] = wpsstm()->post_type_track;

        if ( in_array(get_post_type($post),$allowed_post_types) ){
            $wpsstm_tracklist = wpsstm_get_post_tracklist($post->ID);
            $wpsstm_tracklist->index = $query->current_post + 1;
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

    /**
    *    From http://codex.wordpress.org/Template_Hierarchy
    *
    *    Adds a custom template to the query queue.
    */
    function tracklist_popup_template($template){
        global $post;
        
        $tracklist_action = get_query_var( $this->qvar_tracklist_action );
        if( $tracklist_action != 'popup' ) return $template;
        
        $is_tracklist_post = in_array(get_post_type($post),wpsstm_tracklists()->tracklist_post_types );
        if ( !$is_tracklist_post ) return $template;

        //popup admin
        if ( $template = wpsstm_locate_template( 'tracklist-popup.php' ) ) {
            add_filter( 'body_class', array($this,'tracklist_popup_body_classes'));
        }

        return $template;
    }
    
    function tracklist_popup_body_classes($classes){
        $classes[] = 'wpsstm-tracklist-popup wpsstm-popup';
        return $classes;
    }
    
    function ajax_toggle_favorite_tracklist(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false
        );
        
        
        $tracklist_id = $result['post_id'] = ( isset($ajax_data['post_id']) ) ?     $ajax_data['post_id'] : null;
        $tracklist = wpsstm_get_post_tracklist($tracklist_id);
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
        
        wpsstm()->debug_log($ajax_data,"ajax_refresh_tracklist()");
        
        $tracklist_id = $result['post_id'] = ( isset($ajax_data['post_id']) ) ? $ajax_data['post_id'] : null;
        
        $ajax_options = (array)$ajax_data['options'];
        foreach($ajax_options as $key=>$option){
            //convert AJAX strings to bool
            if ($option === 'true') $ajax_options[$key] = true;
            if ($option === 'false') $ajax_options[$key] = false;
        }
        
        $options = $result['options'] =wp_parse_args($ajax_options,$wpsstm_tracklist->options);

        if ($tracklist_id){
            $wpsstm_tracklist = wpsstm_get_post_tracklist($tracklist_id);
            $wpsstm_tracklist->is_expired = true; //will force tracklist refresh
            $wpsstm_tracklist->options = $options;
            $result['new_html'] = $wpsstm_tracklist->get_tracklist_html();
            $result['success'] = true;
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function column_tracklist_register($defaults) {
        global $post;

        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$this->tracklist_post_types) ){
            $defaults['tracklist'] = __('Tracklist','wpsstm');
        }
        
        return $defaults;
    }
    
    
    function column_tracklist_content($column,$post_id){
        global $post;
        
        if ($column != 'tracklist') return;
        
        global $wpsstm_tracklist;
        $wpsstm_tracklist = wpsstm_get_post_tracklist($post_id);
        
        $wpsstm_tracklist->options['autoplay'] =    false;
        $wpsstm_tracklist->options['autosource'] =  false;
        $wpsstm_tracklist->options['can_play'] =    false;

        if ( !$output = $wpsstm_tracklist->get_tracklist_html() ){
            $output = '—';
        }
        
        echo $output;
    }
    
    function tracks_column_playlist_register($defaults) {
        global $post;
        global $wp_query;

        $post_types = array(
            wpsstm()->post_type_track
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$post_types) ){

        if ( !$wp_query->get('exclude_subtracks') ){
            $after['playlist'] = __('In playlists:','wpsstm');
        }
            
        }
        
        return array_merge($before,$defaults,$after);
    }
    

    
    function tracks_column_playlist_content($column,$post_id){
        global $post;

        switch ( $column ) {
            case 'playlist':
                
                $track = new WP_SoundSystem_Track($post_id);

                if ( $list = $track->get_parents_list() ){
                    echo $list;
                }else{
                    echo '—';
                }

                
            break;
        }
    }
    
    function tracklist_column_lovedby_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$this->tracklist_post_types) ){
            $after['tracklist-lovedby'] = __('Loved by:','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }
    
    function tracklist_column_lovedby_content($column,$post_id){
        global $post;

        switch ( $column ) {
            case 'tracklist-lovedby':
                $output = '—';
                $tracklist = wpsstm_get_post_tracklist( $post_id );
                $links = array();
                if ( $user_ids = $tracklist->get_tracklist_loved_by() ){
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

    function metabox_tracklist_register(){

        add_meta_box( 
            'wpsstm-tracklist', 
            __('Tracklist','wpsstm'),
            array($this,'metabox_tracklist_content'),
            $this->static_tracklist_post_types, 
            'normal', 
            'high' //priority 
        );
        
    }
    
    function metabox_tracklist_content( $post ){
        global $wpsstm_tracklist;
        
        $wpsstm_tracklist = wpsstm_get_post_tracklist($post->ID);
        $wpsstm_tracklist->options['autoplay'] = false;
        $output = $wpsstm_tracklist->get_tracklist_html();

        wp_reset_postdata();
        
        echo $output;
    }

    function content_append_tracklist_table($content){
        global $post;
        global $wpsstm_tracklist;
        
        if( !is_single() ) return $content;
        if (!$wpsstm_tracklist) return $content;
        
        //check post type
        $allowed_post_types = $this->tracklist_post_types;
        $allowed_post_types[] =  wpsstm()->post_type_track;

        $post_type = get_post_type($post->ID);
        if ( !in_array($post_type,$allowed_post_types) ) return $content;

        return $wpsstm_tracklist->get_tracklist_html() . $content;
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

        if ( ( $post_type = get_post_type($atts['post_id']) ) && in_array($post_type,$this->tracklist_post_types) ){ //check that the post exists
            $wpsstm_tracklist = wpsstm_get_post_tracklist($atts['post_id']);
            $output = $wpsstm_tracklist->get_tracklist_html();
            wp_reset_postdata();
        }

        return $output;

    }
    
    function handle_tracklist_popup_form(){
        global $post;
        $popup_action = ( isset($_POST['wpsstm-tracklist-popup-action']) ) ? $_POST['wpsstm-tracklist-popup-action'] : null;
        if (!$popup_action) return;
        
        $success = null;

        $tracklist = wpsstm_get_post_tracklist($post->ID);

        switch($popup_action){
                
            case 'new-subtrack':

                //nonce check
                if ( !isset($_POST['wpsstm_tracklist_new_track_nonce']) || !wp_verify_nonce($_POST['wpsstm_tracklist_new_track_nonce'], sprintf('wpsstm_tracklist_%s_new_track_nonce',$tracklist->post_id) ) ) {
                    wpsstm()->debug_log(array('track_id'=>$tracklist->post_id,'track_gui_action'=>$popup_action),"invalid nonce"); 
                    break;
                }
                
                $track = new WP_SoundSystem_Track();

                $track->artist = ( isset($_POST[ 'wpsstm_track_artist' ]) ) ? $_POST[ 'wpsstm_track_artist' ] : null;
                $track->title = ( isset($_POST[ 'wpsstm_track_title' ]) ) ? $_POST[ 'wpsstm_track_title' ] : null;
                $track->album = ( isset($_POST[ 'wpsstm_track_album' ]) ) ? $_POST[ 'wpsstm_track_album' ] : null;
                $track->mbid = ( isset($_POST[ 'wpsstm_track_mbid' ]) ) ? $_POST[ 'wpsstm_track_mbid' ] : null;

                $track_id = $success = $track->save_track();

                if ( !is_wp_error($track_id) ){
                    $success = $tracklist->append_subtrack_ids($track_id);
                    if ($success){
                        $track_admin_url = $track->get_track_popup_url('edit');
                        wp_redirect($track_admin_url);
                        exit();
                    }
                }

                
            break;
                
        }

        if ($success){ //redirect with a success / error code
            $redirect_url = $tracklist->get_tracklist_popup_url($popup_action);
            if ( is_wp_error($success) ){
                $redirect_url = add_query_arg( array('wpsstm_error_code'=>$success->get_error_code()),$redirect_url );
            }else{
                $redirect_url = add_query_arg( array('wpsstm_success_code'=>$popup_action),$redirect_url );
            }

            wp_redirect($redirect_url);
            exit();
        }

        
        
    }

    function tracklist_xspf_template($template){
        if( !$admin_action = get_query_var( $this->qvar_tracklist_action ) ) return $template;
        if ( $admin_action != 'export' ) return $template;
        the_post();
        return wpsstm_locate_template( 'tracklist-xspf.php' );
    }
    
    function handle_tracklist_action(){
        global $post;
        if (!$post) return;
        
        if( !$action = get_query_var( $this->qvar_tracklist_action ) ) return;
        
        $tracklist = wpsstm_get_post_tracklist($post->ID);
        $success = null;

        switch($action){
            case 'popup':
                //see tracklist_popup_template
            break;
            case 'refresh':
                $tracklist->is_expired = true; //will force tracklist refresh
                $success = $tracklist->populate_subtracks(); //TO FIX query args ?
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
                $success = $tracklist->convert_to_live_playlist($tracklist->post_id);
            break;
        }
        
        if ($success){ //redirect with a success / error code
            $redirect_url = ( wpsstm_is_backend() ) ? get_edit_post_link( $tracklist->post_id ) : get_permalink($tracklist->post_id);

            if ( is_wp_error($success) ){
                $redirect_url = add_query_arg( array('wpsstm_error_code'=>$success->get_error_code()),$redirect_url );
            }else{
                $redirect_url = add_query_arg( array('wpsstm_success_code'=>$action),$redirect_url );
            }

            wp_redirect($redirect_url);
            exit();
        }

    }
    
    function pre_get_posts_user_favorites( $query ) {

        if ( $user_id = $query->get( $this->qvar_user_favorites ) ){

            $meta_query = (array)$query->get('meta_query');

            $meta_query[] = array(
                'key'     => $this->favorited_tracklist_meta_key,
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
            $subtracks_table_name = $wpdb->prefix . wpsstm()->subtracks_table_name;
            $join .= sprintf("INNER JOIN %s AS subtracks ON (%s.ID = subtracks.tracklist_id)",$subtracks_table_name,$wpdb->posts);
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
    

}

function wpsstm_tracklists() {
	return WP_SoundSystem_Core_Tracklists::instance();
}

wpsstm_tracklists();