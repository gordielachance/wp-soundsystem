<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/

class WP_SoundSystem_Core_Tracklists{
    
    public $qvar_xspf = 'xspf';
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

        add_filter( 'query_vars', array($this,'add_query_var_xspf'));
        add_action( 'init', array($this,'xspf_register_endpoint' ));
        add_filter( 'template_include', array($this,'xspf_template_loader'));
        
        add_action( 'add_meta_boxes', array($this, 'metabox_tracklist_register'));
        add_action( 'save_post', array($this,'metabox_tracklist_save')); 
        
        add_action( 'wp_enqueue_scripts', array( $this, 'frontend_script_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'metabox_tracklist_scripts_styles' ) );

        add_filter( 'manage_posts_columns', array($this,'column_tracklist_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'column_tracklist_content'), 10, 2 );
        add_filter( 'manage_posts_columns', array($this,'tracklist_column_lovedby_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'tracklist_column_lovedby_content'), 10, 2 );

        add_action( 'post_submitbox_start', array($this,'publish_metabox_download_link') );

        //post content
        add_filter( 'the_content', array($this,'content_append_tracklist_table'));
        
        //tracklist shortcode
        add_shortcode( 'wpsstm-tracklist',  array($this, 'shortcode_tracklist'));
        
        //ajax : toggle love tracklist
        add_action('wp_ajax_wpsstm_love_unlove_tracklist', array($this,'ajax_love_unlove_tracklist'));
        
        //ajax : load tracklist
        add_action('wp_ajax_wpsstm_load_tracklist', array($this,'ajax_load_tracklist'));
        add_action('wp_ajax_nopriv_wpsstm_load_tracklist', array($this,'ajax_load_tracklist'));
        
        //ajax : tracklist row actions
        add_action('wp_ajax_wpsstm_tracklist_row_action', array($this,'ajax_tracklist_row_action'));
        //add_action('wp_ajax_nopriv_wpsstm_tracklist_row_action', array($this,'ajax_tracklist_row_action'));
        
        //ajax : tracklist reorder
        //add_action('wp_ajax_wpsstm_tracklist_update_order', array($this,'ajax_tracklist_reorder'));
        //add_action('wp_ajax_nopriv_wpsstm_tracklist_update_order', array($this,'ajax_tracklist_reorder'));

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

    function publish_metabox_download_link(){

        //check post type
        $allowed_post_types = array(
            wpsstm()->post_type_album,
            wpsstm()->post_type_playlist,
            wpsstm()->post_type_live_playlist
        );
        
        $post_type = get_post_type();
        if ( !in_array($post_type,$allowed_post_types) ) return;

        if ( !$xpsf_link = wpsstm_get_tracklist_link(null,'xspf',true) ) return;
        
        ?>
        <div id="export-xspf">
            <a class="submit export" href="<?php echo $xpsf_link; ?>"><?php printf('Download XSPF','wpsstm'); ?></a>
        </div>
        <?php

    }
    
    /**
    *   Add the 'xspf' query variable so Wordpress
    *   won't mangle it.
    */
    function add_query_var_xspf($vars){
        $vars[] = $this->qvar_xspf;
        return $vars;
    }
    
    /**
     * Add endpoint for the "/xspf" posts links 
     */

    function xspf_register_endpoint(){
        add_rewrite_endpoint($this->qvar_xspf, EP_PERMALINK );
    }
    
    function frontend_script_styles(){
        //TO FIX load only if we have a tracklist displayed
        wp_enqueue_script( 'thickbox' );
        wp_enqueue_style( 'thickbox' );
    }

    function metabox_tracklist_scripts_styles(){
        
        //check post type
        $screen = get_current_screen();
        $post_type = $screen->post_type;
        if ( !in_array($post_type,$this->allowed_post_types) ) return;
        
        //check post screen
        if ($screen->base != 'post') return;

        // CSS
        wp_enqueue_style( 'wpsstm-admin-metabox-tracklist',  wpsstm()->plugin_url . '_inc/css/wpsstm-admin-metabox-tracklist.css',null,wpsstm()->version );
        
        // JS
        wp_enqueue_script( 'wpsstm-admin-metabox-tracklist', wpsstm()->plugin_url . '_inc/js/wpsstm-admin-metabox-tracklist.js', array('jquery-core', 'jquery-ui-core', 'jquery-ui-sortable'),wpsstm()->version);
    }

    /**
    *    From http://codex.wordpress.org/Template_Hierarchy
    *
    *    Adds a custom template to the query queue.
    */
    function xspf_template_loader($template){
        global $wp_query;
        global $post;

        if( isset( $wp_query->query_vars[$this->qvar_xspf] ) ){ //don't use $wp_query->get() here
            $file = 'playlist-xspf.php';
            if ( file_exists( wpsstm_locate_template( $file ) ) ){
                $template = wpsstm_locate_template( $file );
            }
            
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
        ?>
        <div id="wpsstm-subtracks-list" data-wpsstm-tracklist-id="<?php echo $post->ID;?>">
            
            <?php
                $tracklist = wpsstm_get_post_tracklist($post->ID);
                echo $tracklist->get_tracklist_admin_table(true);
            ?>
        </div>

        <?php

        wp_nonce_field( 'wpsstm_tracklist_meta_box', 'wpsstm_tracklist_meta_box_nonce' );

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

    function metabox_tracklist_save( $post_id ) {
        
        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_tracklist_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        if ( !in_array($post_type,$this->allowed_post_types) ) return;
        
        //check nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_tracklist_meta_box_nonce'], 'wpsstm_tracklist_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //$post_bkmarks_ids = ( isset($form_data['ids']) ) ? $form_data['ids'] : null; //existing links to attach to post
        
        $form_tracks = ( isset($_POST['wpsstm']['tracklist']['tracks']) ) ? $_POST['wpsstm']['tracklist']['tracks'] : array();
        if ( empty($form_tracks) ) return;
        
        $bulk_action = $this->metabox_tracklist_get_current_ation();
        if (!$bulk_action) return;

        //strip slashes for $_POST args if any
        $form_tracks = wp_unslash($form_tracks); 
        
        //keep only the checked links
        $form_tracks = array_filter(
            $form_tracks,
            function ($track){
            return ( isset($track['selected']) );
            }
        );

        //populate a tracklist with the selected tracks
        $tracklist = new WP_SoundSystem_Tracklist($post_id);
        $tracklist->add($form_tracks);

        //if parent post is an album, set album for every track
        //TO FIX should be a filter ?
        if ($post_type == wpsstm()->post_type_album){
            $album = wpsstm_get_post_album($post_id);
            foreach($tracklist->tracks as $track){
                $track->album = $album;
            }
        }
        
        //do tracks actions
        switch($bulk_action){
            case 'save':
                $tracklist->save_subtracks();
            break;
            case 'mbid':
                $tracklist->set_subtracks_auto_mbid();
            break;
            case 'remove':
                $tracklist->remove_subtracks();
            break;
            case 'delete':
                $tracklist->delete_subtracks();
            break;
        }

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
    
    //TO FIX is duplicate of ajax functions from WP_SoundSystem_Core_Tracks ?
    function ajax_tracklist_row_action(){

        $result = array(
            'input'     => $_REQUEST,
            'message'   => null,
            'new_html'  => null,
            'success'   => false
        );

        $tracklist_id =     isset($_REQUEST['tracklist_id']) ? (int)$_REQUEST['tracklist_id'] : null;
        $track_args =       isset($_REQUEST['track']) ? $_REQUEST['track'] : null;
        $track_action =     isset($_REQUEST['track_action']) ? $_REQUEST['track_action'] : null;
        $track_order =      isset($_REQUEST['track_order']) ? $_REQUEST['track_order'] : null;

        $tracklist = new WP_SoundSystem_Tracklist($tracklist_id);
        $track = new WP_SoundSystem_Track($track_args);
        
        wpsstm()->debug_log($tracklist,"ajax_tracklist_row_action() - tracklist#");

        $success = false;

        switch($track_action){
            case 'save':
                
                //get track ID (insert / update track)
                $post_id = $track->save_track();

                if ( is_wp_error($post_id) ){
                    
                    $result['message'] = $post_id->get_error_message();
                    
                }elseif($post_id){
                    
                    $result['post_id'] = $post_id;
                    wpsstm()->debug_log($post_id,"ajax_tracklist_row_action() - track#");

                    //TO FIX check is already a children ?
                    $success = $tracklist->append_subtrack_ids($post_id);
                    
                    if ( is_wp_error($success) ){
                        
                        $result['message'] = $success->get_error_message();
                        
                    }else{
                        
                        $result['success'] = true;
                        
                        //populate tracklist as the global post as it won't always be (eg. ajax requests)
                        global $post;
                        $post = get_post($tracklist_id);
                        
                        require wpsstm()->plugin_dir . 'classes/wpsstm-tracklist-admin-table.php';
                        $entries_table = new WP_SoundSystem_TracksList_Admin_Table();
                        $entries_table->items = array($track);
                        $entries_table->prepare_items();

                        ob_start();
                        $item = end($entries_table->items);
                        $item->order = $track_order;

                        $entries_table->single_row( $item );
                        $result['new_html'] = ob_get_clean();
                    }

                }
            break;
            case 'remove':
                $success = $tracklist->remove_subtrack_ids($track->post_id);
                if ( is_wp_error($success) ){
                    $result['message'] = $success->get_error_message();
                }else{
                    $result['success'] = $success;
                }
            break;
            case 'delete':
                $success = $track->delete_track();
                if ( is_wp_error($success) ){
                    $result['message'] = $success->get_error_message();
                }else{
                    $result['success'] = $success;
                }
            break;
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 

    }
    
    function ajax_tracklist_reorder(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'message'   => null,
            'success'   => false,
            'input'     => $ajax_data
        );

        $result['post_id']  =           $post_id =          ( isset($ajax_data['post_id']) ) ?          $ajax_data['post_id'] : null;
        $result['subtracks_order']   =  $subtracks_order =  ( isset($ajax_data['subtracks_order']) ) ?  $ajax_data['subtracks_order'] : null;

        if ( $subtracks_order && $post_id ){

            //populate a tracklist with the selected tracks
            $tracklist = new WP_SoundSystem_Tracklist($post_id);
            $tracklist->load_subtracks();
            $result['tracklist'] = $tracklist;
            
            $success = $tracklist->set_subtrack_ids($subtracks_order);
            
            if ( is_wp_error($success) ){
                $result['message'] = $success->get_error_message();
            }else{
                $result['success'] = $success;
            }
        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
}

function wpsstm_tracklists() {
	return WP_SoundSystem_Core_Tracklists::instance();
}

wpsstm_tracklists();
