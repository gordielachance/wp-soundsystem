<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/

class WP_SoundSystem_Core_Tracklists{
    
    public $qvar_xspf = 'xspf';
    public $qvar_tracklist_admin = 'admin';
    public $favorited_tracklist_meta_key = '_wpsstm_user_favorite';
    public $time_updated_substracks_meta_name = 'wpsstm_remote_query_time';
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
        
        require_once(wpsstm()->plugin_dir . 'classes/wpsstm-scraper-wizard.php');
        
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
        
    }
    
    function setup_globals(){
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
        add_action( 'init', array($this,'register_tracklist_endpoints' ));
        add_filter( 'template_include', array($this,'xspf_template_filter'));
        
        
        add_action( 'wp', array($this,'tracklist_save_admin_gui'));
        add_action( 'wp', array($this,'tracklist_append_new_track'));
        add_filter( 'template_include', array($this,'tracklist_admin_template_filter'));
        

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

        //post content
        add_filter( 'the_content', array($this,'content_append_tracklist_table'));
        
        //tracklist shortcode
        add_shortcode( 'wpsstm-tracklist',  array($this, 'shortcode_tracklist'));

        add_action( 'wp_trash_post', array($this,'trash_tracklist_orphans') );
        
        //ajax : toggle love tracklist
        add_action('wp_ajax_wpsstm_love_unlove_tracklist', array($this,'ajax_love_unlove_tracklist'));
        
        //ajax : load tracklist
        add_action('wp_ajax_wpsstm_refresh_tracklist', array($this,'ajax_refresh_tracklist'));
        add_action('wp_ajax_nopriv_wpsstm_refresh_tracklist', array($this,'ajax_refresh_tracklist'));

        //ajax : row actions
        add_action('wp_ajax_wpsstm_playlist_update_track_position', array($this,'ajax_update_tracklist_track_position'));
        add_action('wp_ajax_wpsstm_playlist_remove_track', array($this,'ajax_remove_tracklist_track'));
        add_action('wp_ajax_wpsstm_playlist_trash_track', array($this,'ajax_trash_tracklist_track'));

    }
    
    /**
    *   Add the 'xspf' query variable so Wordpress
    *   won't mangle it.
    */
    function add_tracklist_query_vars($vars){
        $vars[] = $this->qvar_tracklist_admin;
        $vars[] = $this->qvar_xspf;
        return $vars;
    }

    /**
     * Add endpoint for the "/xspf" posts links 
     */

    function register_tracklist_endpoints(){
        add_rewrite_endpoint($this->qvar_tracklist_admin, EP_PERMALINK ); // /admin
        add_rewrite_endpoint($this->qvar_xspf, EP_PERMALINK ); // /xspf
    }
    
    function register_tracklists_scripts_styles_shared(){
        wp_register_style( 'wpsstm-tracklists', wpsstm()->plugin_url . '_inc/css/wpsstm-tracklists.css',array('font-awesome','thickbox','wpsstm-tracks'),wpsstm()->version );
        //JS
        
        wp_register_script( 'wpsstm-tracklists', wpsstm()->plugin_url . '_inc/js/wpsstm-tracklists.js', array('jquery','jquery-core', 'jquery-ui-core', 'jquery-ui-sortable','thickbox','jquery.toggleChildren','wpsstm-tracks'),wpsstm()->version );
    }
    
    function get_tracklist_action(){
        global $wp_query;
        return $wp_query->get($this->qvar_tracklist_admin);
    }

    function enqueue_tracklists_scripts_styles_frontend(){
        //TO FIX load only when tracklist is displayed
        wp_enqueue_script( 'wpsstm-tracklists' );
        wp_enqueue_style( 'wpsstm-tracklists' );
    }

    function enqueue_tracklists_scripts_styles_backend(){
        
        if ( !wpsstm()->is_admin_page() ) return;
        
        wp_enqueue_script( 'wpsstm-tracklists' );
        wp_enqueue_style( 'wpsstm-tracklists' );

    }

    /**
    *    From http://codex.wordpress.org/Template_Hierarchy
    *
    *    Adds a custom template to the query queue.
    */
    function tracklist_admin_template_filter($template){
        global $post;
        
        if( !$admin_action = $this->get_tracklist_action() ) return $template;

        if ( $admin_action == 'new-subtrack' ){ //this will be handled by track_admin_template_filter()
            set_query_var( wpsstm_tracks()->qvar_track_admin, 'new-subtrack' );
            return $template;
        }  

        if ( !in_array(get_post_type($post),wpsstm_tracklists()->tracklist_post_types ) ) return $template;

        $file = 'tracklist-admin.php';
        if ( file_exists( wpsstm_locate_template( $file ) ) ){
            $template = wpsstm_locate_template( $file );
            add_filter( 'body_class', array($this,'tracklist_popup_body_classes'));
        }
        
        return $template;
    }
    
    function tracklist_popup_body_classes($classes){
        $classes[] = 'wpsstm_tracklist-template-admin';
        return $classes;
    }
    
    function ajax_love_unlove_tracklist(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false
        );
        
        $tracklist_id = $result['post_id'] = ( isset($ajax_data['post_id']) ) ?     $ajax_data['post_id'] : null;
        $do_love = $result['do_love'] = ( isset($ajax_data['do_love']) ) ?          filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN) : null; //ajax do send strings
        
        if ($tracklist_id && ($do_love!==null) ){
            $tracklist = wpsstm_get_post_tracklist($tracklist_id);
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
    
    function ajax_refresh_tracklist(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false,
            'new_html'  => null
        );
        
        $tracklist_id = $result['post_id'] = ( isset($ajax_data['post_id']) ) ? $ajax_data['post_id'] : null;

        if ($tracklist_id){
            if ( $tracklist = wpsstm_get_post_tracklist($tracklist_id) ){
                $tracklist->can_remote_request = true;
                $tracklist->load_subtracks();
                if ( $tracklist->tracks ){
                    $result['success'] = true;
                    $result['new_html'] = $tracklist->get_tracklist_table(); 
                }else{
                    $result['message'] = 'No remote tracks found';
                }

            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }

    /**
    *    From http://codex.wordpress.org/Template_Hierarchy
    *
    *    Adds a custom template to the query queue.
    */
    function xspf_template_filter($template){
        global $wp_query;
        global $post;

        if( !isset( $wp_query->query_vars[$this->qvar_xspf] ) ) return $template; //don't use $wp_query->get() here
        
        $file = 'tracklist-xspf.php';
        if ( file_exists( wpsstm_locate_template( $file ) ) ){
            $template = wpsstm_locate_template( $file );
        }
        
        return $template;
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
        
        $output = '—';

        $tracklist = wpsstm_get_post_tracklist($post_id);
        $output = $tracklist->get_tracklist_table(array('display_type'=>'list','can_play'=>false));
        
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

            if ( !$wp_query->get('subtracks_exclude') ){
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
        $tracklist = wpsstm_get_post_tracklist($post->ID);
        
        echo $tracklist->get_tracklist_table(array('can_play'=>false));

    }

    function content_append_tracklist_table($content){
        global $post;
        
        //check post type
        $allowed_post_types = $this->tracklist_post_types;
        $allowed_post_types[] =  wpsstm()->post_type_track;

        $post_type = get_post_type($post->ID);
        if ( !in_array($post_type,$allowed_post_types) ) return $content;
        
        $tracklist = wpsstm_get_post_tracklist($post->ID);

        return $tracklist->get_tracklist_table() . $content;
    }
    
    function shortcode_tracklist( $atts ) {

        global $post;

        // Attributes
        $default = array(
            'post_id'       => $post->ID,
            'max_rows'      => -1    
        );
        $atts = shortcode_atts($default,$atts);
        
        //check post type
        $post_type = get_post_type($atts['post_id']);
        if ( !in_array($post_type,$this->tracklist_post_types) ) return;
        
        $tracklist = wpsstm_get_post_tracklist($atts['post_id']);
        return $tracklist->get_tracklist_table();

    }

    function ajax_update_tracklist_track_position(){
        $ajax_data = $_POST;
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );

        $result['track_id']  =          $track_id =         ( isset($ajax_data['track_id']) ) ? $ajax_data['track_id'] : null;
        $result['tracklist_id']  =      $tracklist_id =     ( isset($ajax_data['tracklist_id']) ) ? $ajax_data['tracklist_id'] : null;
        $result['position']  =          $position =         ( isset($ajax_data['position']) ) ? $ajax_data['position'] : -1;

        if ( $track_id && $tracklist_id && ($position != -1) ){

            //populate a tracklist with the selected tracks
            $tracklist = wpsstm_get_post_tracklist($tracklist_id);
            $tracklist->load_subtracks();
            $result['tracklist'] = $tracklist;
            
            $success = $tracklist->save_track_position($track_id,$position);
            
            if ( is_wp_error($success) ){
                $result['message'] = $success->get_error_message();
            }else{
                $result['success'] = $success;
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_remove_tracklist_track(){
        $ajax_data = $_POST;
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );

        $result['track_id']  =          $track_id =         ( isset($ajax_data['track_id']) ) ? $ajax_data['track_id'] : null;
        $result['tracklist_id']  =      $tracklist_id =     ( isset($ajax_data['tracklist_id']) ) ? $ajax_data['tracklist_id'] : null;

        $tracklist = wpsstm_get_post_tracklist($tracklist_id);

        if ( $track_id && $tracklist_id ){

            $success = $tracklist->remove_subtrack_ids($track_id);
            
            if ( is_wp_error($success) ){
                $result['message'] = $success->get_error_message();
            }else{
                $result['success'] = $success;
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_trash_tracklist_track(){
        $ajax_data = $_POST;
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );

        $track_id = ( isset($ajax_data['track_id']) ) ? $ajax_data['track_id'] : null;

        if ( $track_id ){

            $track = $result['track'] = new WP_SoundSystem_Track($track_id);
            $success = $track->trash_track();
            
            if ( is_wp_error($success) ){
                $result['message'] = $success->get_error_message();
            }else{
                $result['success'] = $success;
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function tracklist_append_new_track(){
        $popup_action = ( isset($_POST['wpsstm-admin-tracklist-action']) ) ? $_POST['wpsstm-admin-tracklist-action'] : null;
        if ( !$popup_action || ($popup_action != 'new-subtrack') ) return;
        
        $tracklist_id = isset($_POST['tracklist_id']) ? $_POST['tracklist_id'] : null;
        if (!$tracklist_id) return;

        //nonce check
        if ( !isset($_POST['wpsstm_admin_new_tracklist_subtrack_nonce']) || !wp_verify_nonce($_POST['wpsstm_admin_new_tracklist_subtrack_nonce'], 'wpsstm_admin_new_tracklist_subtrack_'.$tracklist_id ) ) {
            wpsstm()->debug_log($tracklist_id,"tracklist_append_new_track():invalid nonce"); 
            return;
        }
        
        $tracklist = wpsstm_get_post_tracklist($tracklist_id);
        $new_track = new WP_SoundSystem_Track();

        $new_track->artist = ( isset($_POST[ 'wpsstm_track_artist' ]) ) ? $_POST[ 'wpsstm_track_artist' ] : null;
        $new_track->title = ( isset($_POST[ 'wpsstm_track_title' ]) ) ? $_POST[ 'wpsstm_track_title' ] : null;
        $new_track->album = ( isset($_POST[ 'wpsstm_track_album' ]) ) ? $_POST[ 'wpsstm_track_album' ] : null;
        $new_track->mbid = ( isset($_POST[ 'wpsstm_track_mbid' ]) ) ? $_POST[ 'wpsstm_track_mbid' ] : null;

        $new_track_id = $new_track->save_track();
        
        if ( is_wp_error($new_track_id) ){
            //TO FIX do something ?
        }elseif($new_track_id){
            
            $tracklist->append_subtrack_ids($new_track_id);
            $track_admin_url = $new_track->get_track_admin_gui_url('edit',$tracklist->post_id);
            wp_redirect($track_admin_url);
            exit();
            
        }
        
    }
    
    function tracklist_save_admin_gui(){
        global $post;
        
        if (!$post) return;
        
        $tracklist = wpsstm_get_post_tracklist($post->ID);

        $post_type = get_post_type($tracklist->post_id);
        
        if( !$admin_action = $this->get_tracklist_action() ) return;
        if (!$tracklist->post_id) return;

        switch($admin_action){
            case 'switch-status':

                //capability check
                $post_type_obj =    get_post_type_object(wpsstm()->post_type_live_playlist);
                
                $can_edit_cap =     $post_type_obj->cap->edit_post;
                $can_add =          current_user_can($can_edit_cap,$post->ID);
                if (!$can_add) return;
                
                $can_publish_cap =  $post_type_obj->cap->publish_posts;
                $can_publish =      current_user_can($can_publish_cap);
                
                //TO FIX validate status regarding user's caps
                $new_status = ( isset($_REQUEST['frontend-wizard-status']) ) ? $_REQUEST['frontend-wizard-status'] : null;

                $updated_post = array(
                    'ID'            => $post->ID,
                    'post_status'   => $new_status
                );

                if ( wp_update_post( $updated_post ) ){
                    $redirect_url = ( wpsstm_is_backend() ) ? get_edit_post_link( $tracklist->post_id ) : get_permalink($tracklist->post_id);
                    wp_redirect($redirect_url);
                    exit();
                }
            break;
            case 'lock-tracklist':
                if ( $post_type == wpsstm()->post_type_live_playlist ){
                    
                    $tracklist->convert_to_static_playlist(true);
                    
                    $redirect_url = ( wpsstm_is_backend() ) ? get_edit_post_link( $tracklist->post_id ) : get_permalink($tracklist->post_id);
                    wp_redirect($redirect_url);
                    exit();
                }
            break;
            case 'unlock-tracklist':
                
                if ( $post_type == wpsstm()->post_type_playlist ){
                    
                    $tracklist->convert_to_live_playlist($post->ID);
                    
                    $redirect_url = ( wpsstm_is_backend() ) ? get_edit_post_link( $tracklist->post_id ) : get_permalink($tracklist->post_id);
                    wp_redirect($redirect_url);
                    exit();
                }
            break;
        }
    }
    
    function trash_tracklist_orphans($post_id){

        if ( !in_array(get_post_type($post_id),$this->tracklist_post_types) ) return;
        
        $tracklist = wpsstm_get_post_tracklist($post_id);
        $tracklist->flush_subtracks();

    }
    
    function get_subtracks_update_time($post = null){
        return get_post_meta($post,$this->time_updated_substracks_meta_name,true);
    }
    
    function get_human_tracklist_time($time){
        if (!$time) return;
        
        $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $time ), get_option( 'date_format' ) );
        $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $time ), get_option( 'time_format' ) );
        return sprintf(__('on %s - %s','wpsstm'),$date,$time);
    }

}

function wpsstm_tracklists() {
	return WP_SoundSystem_Core_Tracklists::instance();
}

wpsstm_tracklists();