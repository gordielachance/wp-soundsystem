<?php

/**
Handle posts that have a tracklist, like albums and playlists.
**/

class WP_SoundSytem_Core_Tracklists{
    
    public $qvar_xspf = 'xspf';
    public $allowed_post_types = array();
    public $favorited_tracklist_meta_key = '_wpsstm_user_favorite';
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_Tracklists;
                    self::$instance->init();
            }
            return self::$instance;
    }

    private function __construct() { /* Do nothing here */ }

    function init(){
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
        
        add_action( 'wp_enqueue_scripts', array( $this, 'tracklists_script_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'metabox_tracklist_scripts_styles' ) );

        add_filter('manage_posts_columns', array($this,'column_tracklist_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'column_tracklist_content'), 10, 2 );

        add_action( 'post_submitbox_start', array($this,'publish_metabox_download_link') );
        
        //scraper
        add_action( 'admin_init', array($this,'scraper_wizard_init') );
        add_action( 'save_post',  array($this, 'scraper_wizard_save'));
        
        //post content
        add_filter( 'the_content', array($this,'content_append_tracklist_table'));
        
        //tracklist shortcode
        add_shortcode( 'wpsstm-tracklist',  array($this, 'shortcode_tracklist'));
        
        //ajax : toggle love tracklist
        add_action('wp_ajax_wpsstm_love_unlove_tracklist', array($this,'ajax_love_unlove_tracklist'));
        
        //ajax : load tracklist
        add_action('wp_ajax_wpsstm_load_tracklist', array($this,'ajax_load_tracklist'));
        

    }
    
    function ajax_love_unlove_tracklist(){
        
        $result = array(
            'input'     => $_POST,
            'success'   => false
        );
        
        $tracklist_id = $result['post_id'] = ( isset($_POST['post_id']) ) ? $_POST['post_id'] : null;
        $do_love = $result['do_love'] = ( isset($_POST['do_love']) ) ? filter_var($_POST['do_love'], FILTER_VALIDATE_BOOLEAN) : null; //ajax do send strings
        
        if ($tracklist_id && ($do_love!==null) ){
            $tracklist = new WP_SoundSytem_Tracklist($tracklist_id);
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
        $result = array(
            'input'     => $_POST,
            'success'   => false,
            'new_html'  => null
        );
        
        $tracklist_id = $result['post_id'] = ( isset($_POST['post_id']) ) ? $_POST['post_id'] : null;

        if ($tracklist_id){
            if ( $tracklist = wpsstm_get_post_tracklist($tracklist_id,false) ){
                $result['success'] = true;
                $result['new_html'] = $tracklist->get_tracklist_table(); 
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

    function tracklists_script_styles(){
        //TO FIX limitations ?
        wp_enqueue_style( 'wpsstm-tracklist',  wpsstm()->plugin_url . '_inc/css/wpsstm-tracklist.css',null,wpsstm()->version );
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
        
        /* 
        URI.js
        https://github.com/medialize/URI.js
        required to check / validate / work with URIs within jQuery
        */
        wp_register_script( 'uri', wpsstm()->plugin_url . '_inc/js/URI.min.js', null, '1.18.10');
        wp_register_script( 'jquery-uri', wpsstm()->plugin_url . '_inc/js/jquery.URI.min.js', array('uri'), '1.18.10');
        
        wp_enqueue_script( 'wpsstm-admin-metabox-tracklist', wpsstm()->plugin_url . '_inc/js/wpsstm-admin-metabox-tracklist.js', array('jquery-core', 'jquery-ui-core', 'jquery-ui-sortable','jquery-uri'),wpsstm()->version);
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

        foreach($tracklist->tracks as $entry){
            $artist = $entry->artist; //wpsstm_get_post_artist_link_by_name($entry->artist);
            $track = $entry->title; //wpsstm_get_post_track_link_by_name($entry->artist,$entry->title,null);
            $track_title_artist = sprintf(__('<span itemprop="byArtist">%s</span> <span itemprop="name">%s</span>','wpsstm'),$artist,$track);
            $entry_html[] =  sprintf('<li>%s</li>',$track_title_artist);
        }

        $output = sprintf('<ol class="wpsstm-tracklist-list">%s</ol>',implode("\n",$entry_html));
        echo $output;

        if (!$output){
            echo '—';
        }
    }

    function metabox_tracklist_register(){

        add_meta_box( 
            'wpsstm-tracklist', 
            __('Tracklist','wpsstm'),
            array($this,'metabox_tracklist_content'),
            $this->allowed_post_types, 
            'normal', 
            'high' 
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
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_tracklist_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_revision ) return;
        
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
        $form_tracks = stripslashes_deep($form_tracks); 
        
        //keep only the checked links
        $form_tracks = array_filter(
            $form_tracks,
            function ($track){
            return ( isset($track['selected']) );
            }
        );

        //populate a tracklist with the selected tracks
        $tracklist = new WP_SoundSytem_Tracklist($post_id);
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
            case 'remove':
                $tracklist->remove_subtracks();
            break;
            case 'delete':
                $tracklist->delete_subtracks();
            break;
        }

    }

    function scraper_wizard_init(){
        $post_id = (isset($_REQUEST['post'])) ? $_REQUEST['post'] : null;
        if (!$post_id) return;
        
        $post_type = get_post_type($post_id);
        if( !in_array($post_type,$this->scraper_post_types ) ) return;
        
        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-wizard.php');
        $wizard = new WP_SoundSytem_Playlist_Scraper_Wizard($post_id);

    }
    
    function scraper_wizard_save($post_id){
        $post_type = get_post_type($post_id);
        if( !in_array($post_type,$this->scraper_post_types ) ) return;

        require_once(wpsstm()->plugin_dir . 'scraper/wpsstm-scraper-wizard.php');
        $wizard = new WP_SoundSytem_Playlist_Scraper_Wizard($post_id);
        $wizard->save_wizard($post_id);
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
    
}

function wpsstm_tracklists() {
	return WP_SoundSytem_Core_Tracklists::instance();
}

wpsstm_tracklists();
