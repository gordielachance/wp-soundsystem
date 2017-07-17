<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/

class WP_SoundSystem_Core_Tracklists{
    
    public $qvar_xspf = 'xspf';
    public $qvar_admin = 'admin';
    public $allowed_post_types = array();
    public $favorited_tracklist_meta_key = '_wpsstm_user_favorite';
    
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
        
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-wizard.php');
        
        add_action( 'wpsstm_loaded',array($this,'setup_globals') );
        add_action( 'wpsstm_loaded',array($this,'setup_actions') );
        
    }
    
    function setup_globals(){
        $this->allowed_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist
        );
        
        $this->scraper_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );

    }
    
    function setup_actions(){

        add_filter( 'query_vars', array($this,'add_tracklist_query_vars'));
        add_action( 'init', array($this,'register_tracklist_endpoints' ));
        add_filter( 'template_include', array($this,'xspf_template_filter'));
        
        add_action( 'wp', array($this,'tracklist_save_admin_gui'));
        add_filter( 'template_include', array($this,'tracklist_admin_template_filter'));

        add_action( 'add_meta_boxes', array($this, 'metabox_tracklist_register'));
        
        add_action( 'wp_enqueue_scripts', array( $this, 'register_tracklists_scripts_styles_shared' ), 9 );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_tracklists_scripts_styles_shared' ), 9 );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracklists_scripts_styles_frontend' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tracklists_scripts_styles_backend' ) );
        

        add_filter( 'manage_posts_columns', array($this,'column_tracklist_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'column_tracklist_content'), 10, 2 );
        add_filter( 'manage_posts_columns', array($this,'tracklist_column_lovedby_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'tracklist_column_lovedby_content'), 10, 2 );

        //post content
        add_filter( 'the_content', array($this,'content_append_tracklist_table'));
        
        //tracklist shortcode
        add_shortcode( 'wpsstm-tracklist',  array($this, 'shortcode_tracklist'));
        
        //ajax : toggle love tracklist
        add_action('wp_ajax_wpsstm_love_unlove_tracklist', array($this,'ajax_love_unlove_tracklist'));
        
        //ajax : load tracklist
        add_action('wp_ajax_wpsstm_load_tracklist', array($this,'ajax_load_tracklist'));
        add_action('wp_ajax_nopriv_wpsstm_load_tracklist', array($this,'ajax_load_tracklist'));

        //ajax : row actions
        add_action('wp_ajax_wpsstm_playlist_update_track_position', array($this,'ajax_update_tracklist_track_position'));
        add_action('wp_ajax_wpsstm_playlist_remove_track', array($this,'ajax_remove_tracklist_track'));
        add_action('wp_ajax_wpsstm_playlist_delete_track', array($this,'ajax_delete_tracklist_track'));

    }
    
    /**
    *    From http://codex.wordpress.org/Template_Hierarchy
    *
    *    Adds a custom template to the query queue.
    */
    function tracklist_admin_template_filter($template){
        global $wp_query;
        global $post;

        if( !isset( $wp_query->query_vars[wpsstm_tracklists()->qvar_admin] ) ) return $template; //don't use $wp_query->get() here
        if ( !in_array(get_post_type($post),array(wpsstm()->post_type_playlist,wpsstm()->post_type_live_playlist) ) ) return $template;
        
        $file = 'tracklist-admin.php';
        if ( file_exists( wpsstm_locate_template( $file ) ) ){
            $template = wpsstm_locate_template( $file );
            add_filter( 'body_class', array($this,'tracklist_popup_body_classes'));
        }
        
        return $template;
    }
    
    function tracklist_popup_body_classes($classes){
        //remove default
        if(($key = array_search('wpsstm_playlist-template-default', $classes)) !== false) {
            unset($classes[$key]);
            $classes[] = 'wpsstm_playlist-template-admin';
        }
        //remove default
        if(($key = array_search('wpsstm_live_playlist-template-default', $classes)) !== false) {
            unset($classes[$key]);
            $classes[] = 'wpsstm_live_playlist-template-admin';
        }
        //
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
            $tracklist = new WP_SoundSystem_Tracklist($tracklist_id);
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
    
    function ajax_load_tracklist(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'success'   => false,
            'new_html'  => null
        );
        
        $tracklist_id = $result['post_id'] = ( isset($ajax_data['post_id']) ) ? $ajax_data['post_id'] : null;

        if ($tracklist_id){
            if ( $tracklist = wpsstm_get_post_tracklist($tracklist_id) ){
                $tracklist->load_remote_tracks(true);
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
    *   Add the 'xspf' query variable so Wordpress
    *   won't mangle it.
    */
    function add_tracklist_query_vars($vars){
        $vars[] = $this->qvar_admin;
        $vars[] = $this->qvar_xspf;
        return $vars;
    }

    /**
     * Add endpoint for the "/xspf" posts links 
     */

    function register_tracklist_endpoints(){
        add_rewrite_endpoint($this->qvar_admin, EP_PERMALINK ); // /admin
        add_rewrite_endpoint($this->qvar_xspf, EP_PERMALINK ); // /xspf
    }
    
    function register_tracklists_scripts_styles_shared(){
        wp_register_style( 'wpsstm-tracklists', wpsstm()->plugin_url . '_inc/css/wpsstm-tracklists.css',array('font-awesome','thickbox','wpsstm-tracks'),wpsstm()->version );
        //JS
        
        wp_register_script( 'wpsstm-tracklists', wpsstm()->plugin_url . '_inc/js/wpsstm-tracklists.js', array('jquery','jquery-core', 'jquery-ui-core', 'jquery-ui-sortable','thickbox','jquery.toggleChildren','wpsstm-tracks'),wpsstm()->version );
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
        
        $allowed_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );

        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$allowed_post_types) ){
            $defaults['tracklist'] = __('Tracklist','wpsstm');
        }
        
        return $defaults;
    }
    
    function column_tracklist_content($column,$post_id){
        global $post;
        
        if ($column != 'tracklist') return;
        
        $output = null;

        $tracklist = wpsstm_get_post_tracklist($post_id);

        $entry_html = array();

        foreach($tracklist->tracks as $item){
            $artist = $item->artist;
            $track = $item->title;
            $track_title_artist = sprintf(__('<span itemprop="byArtist">%s</span> <span itemprop="name">%s</span>','wpsstm'),$artist,$track);
            
            $item_classes = array();
            if ( !$item->validate_track() ) $item_classes[] = 'wpsstm-invalid-track';
        
            $item_attr_arr = array(
                'class' =>                      implode(' ',$item_classes),
                'data-wpsstm-track-id' =>       $item->post_id,
                'itemtype' =>                   'http://schema.org/MusicRecording',
                'itemprop' =>                   'track',
            );
            
            
            $entry_html[] =  sprintf('<li %s>%s</li>',wpsstm_get_html_attr($item_attr_arr),$track_title_artist);
        }
        
        $list_classes = array('wpsstm-tracklist');
        
        $list_attr_arr = array(
            'class'           =>            implode(' ',$list_classes),
            'data-wpsstm-tracklist-id' =>   $tracklist->post_id,
            'data-tracks-count' =>          $tracklist->pagination['total_items'],
            'itemtype' =>                   'http://schema.org/MusicPlaylist',
        );

        $output = sprintf('<div itemscope %s><ol class="wpsstm-tracklist-entries">%s</ol></div>',wpsstm_get_html_attr($list_attr_arr),implode("\n",$entry_html));
        echo $output;

        if (!$output){
            echo '—';
        }
    }
    
    function tracklist_column_lovedby_register($defaults) {
        global $post;

        $allowed_post_types = array(
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$allowed_post_types) ){
            $after['tracklist-lovedby'] = __('Loved by','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }
    
    function tracklist_column_lovedby_content($column,$post_id){
        global $post;

        switch ( $column ) {
            case 'tracklist-lovedby':
                $output = '—';
                $tracklist = new WP_SoundSystem_Tracklist( $post_id );
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
            $this->allowed_post_types, 
            'normal', 
            'high' //priority 
        );
        
    }
    
    function metabox_tracklist_content( $post ){
        
        $tracklist = wpsstm_get_post_tracklist($post->ID);
        echo $tracklist->get_tracklist_table();

    }
    
	/**
	 * Get the current action selected from the bulk actions dropdown.
	 *
	 * @return string|false The action name or False if no action was selected
	 */
	public function metabox_tracklist_get_current_ation() {
        //TO FIX TO CHECK
		if ( isset( $_REQUEST['filter_action'] ) && ! empty( $_REQUEST['filter_action'] ) )
			return false;

		if ( isset( $_REQUEST['wpsstm-tracklist-action'] ) && -1 != $_REQUEST['wpsstm-tracklist-action'] )
			return $_REQUEST['wpsstm-tracklist-action'];

		if ( isset( $_REQUEST['wpsstm-tracklist-action2'] ) && -1 != $_REQUEST['wpsstm-tracklist-action2'] )
			return $_REQUEST['wpsstm-tracklist-action2'];

		return false;
	}

    function content_append_tracklist_table($content){
        global $post;
        
        //check post type
        $this->allowed_post_types = array(
            wpsstm()->post_type_track,
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );
        $post_type = get_post_type($post->ID);
        if ( !in_array($post_type,$this->allowed_post_types) ) return $content;
        
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
        $this->allowed_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );
        $post_type = get_post_type($atts['post_id']);

        if ( !in_array($post_type,$this->allowed_post_types) ) return;
        
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
            $tracklist = new WP_SoundSystem_Tracklist($tracklist_id);
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

        $tracklist = new WP_SoundSystem_Tracklist($tracklist_id);

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
    
    function ajax_delete_tracklist_track(){
        $ajax_data = $_POST;
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );

        $track_id = ( isset($ajax_data['track_id']) ) ? $ajax_data['track_id'] : null;

        if ( $track_id ){

            $track = $result['track'] = new WP_SoundSystem_Track( array('post_id'=>$track_id) );
            $success = $track->delete_track();
            
            if ( is_wp_error($success) ){
                $result['message'] = $success->get_error_message();
            }else{
                $result['success'] = $success;
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function tracklist_save_admin_gui(){
        global $post;

        $post_type = get_post_type();
        if ( $post_type != wpsstm()->post_type_playlist ) return;
        
        $tracklist = new WP_SoundSystem_Tracklist($post->ID);
        $admin_action = ( isset($_REQUEST['wpsstm-admin-tracklist-action']) ) ? $_REQUEST['wpsstm-admin-tracklist-action'] : null;
        if (!$admin_action || !$tracklist->post_id) return;

        switch($admin_action){
            case 'add_track':
                
                //nonce check
                if ( !isset($_POST['wpsstm_admin_tracklist_add_track_nonce']) || !wp_verify_nonce($_POST['wpsstm_admin_tracklist_add_track_nonce'], 'wpsstm_admin_tracklist_add_track_'.$tracklist->post_id ) ) {
                    wpsstm()->debug_log(array('playlist_id'=>$tracklist->post_id,'track_gui_action'=>$admin_action),"invalid nonce"); 
                    break;
                }
                
                $track_args = array(
                    'artist' => ( isset($_POST[ 'wpsstm_track_artist' ]) ) ? $_POST[ 'wpsstm_track_artist' ] : null,
                    'title' =>  ( isset($_POST[ 'wpsstm_track_title' ]) ) ? $_POST[ 'wpsstm_track_title' ] : null,
                    'album' =>  ( isset($_POST[ 'wpsstm_track_album' ]) ) ? $_POST[ 'wpsstm_track_album' ] : null,
                    'mbid' =>   ( isset($_POST[ 'wpsstm_track_mbid' ]) ) ? $_POST[ 'wpsstm_track_mbid' ] : null
                );

                $track = new WP_SoundSystem_Track( $track_args );
                
                if ( $track->save_track() ){
                    if ( $tracklist->append_subtrack_ids($track->post_id) ){
                        $redirect_url = $tracklist->get_tracklist_admin_gui_url($admin_action);
                        $redirect_url = add_query_arg(array('success'=>true),$redirect_url);
                        wp_redirect($redirect_url);
                        die();
                    }
                }

            break;
        }
    }
    
}

function wpsstm_tracklists() {
	return WP_SoundSystem_Core_Tracklists::instance();
}

wpsstm_tracklists();
