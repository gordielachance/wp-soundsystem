<?php
//TO FIX rather extend WP_SoundSystem_Remote_Tracklist ?
class WP_SoundSystem_Core_Wizard{

    var $tracklist;
    
    var $is_advanced = false; //is advanced wizard ?

    var $wizard_sections  = array();
    var $wizard_fields = array();
    
    public $frontend_wizard_page_id = null;
    public $qvar_feed_url = 'wpsstm_feed_url'; // ! should match the wizard form input name
    public $qvar_wizard_posts = 'wpsstm_wizard_posts';
    public $frontend_feed_url = null;
    public $frontend_wizard = null;
    public $frontend_wizard_meta_key = '_wpsstm_is_frontend_wizard';
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Wizard;
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
        $this->frontend_wizard_page_id = (int)wpsstm()->get_options('frontend_scraper_page_id');

        //blank tracklist for now (avoid undefined methods)
        $this->tracklist = new WP_SoundSystem_Remote_Tracklist();
    }

    function setup_actions(){
        
        //frontend
        add_action( 'wp', array($this,'frontend_wizard_populate' ), 9 );

        add_filter( 'query_vars', array($this,'add_wizard_query_vars'));
        add_filter( 'page_rewrite_rules', array($this,'frontend_wizard_rewrite') );

        //add_action( 'pre_get_posts', array($this, 'is_single_wizard_post'));
        //add_action( 'pre_get_posts', array($this, 'filter_wizard_posts'));
        //add_action( 'pre_get_posts', array($this, 'include_wizard_drafts'));
        
        add_filter( 'the_content', array($this,'wizard_status_notice'));
        add_filter( 'the_content', array($this,'frontend_wizard_display'));
        add_filter( 'wpsstm_get_tracklist_link', array($this,'frontend_wizard_get_tracklist_link'), 10, 2);

        //backend
        add_action( 'admin_init', array($this,'populate_backend_wizard_tracklist') );
        add_action( 'add_meta_boxes', array($this, 'metabox_scraper_wizard_register'), 11 ); //do register it AFTER the tracklist metabox
        add_action( 'save_post',  array($this, 'wizard_save_metabox'));

        //scripts & styles
        add_action( 'wp_enqueue_scripts', array( $this, 'wizard_register_scripts_style_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'wizard_register_scripts_style_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'wizard_scripts_styles_backend' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'wizard_scripts_styles_frontend' ) );

    }
    
    /**
    *   Add the query variables for the Wizard
    */
    function add_wizard_query_vars($vars){
        $vars[] = $this->qvar_feed_url;
        $vars[] = $this->qvar_wizard_posts;
        return $vars;
    }
    
    /*
    Handle the XSPF endpoint for the frontend wizard page
    */
    
    function frontend_wizard_rewrite($rules){
        global $wp_rewrite;
        if ( !$this->frontend_wizard_page_id ) return $rules;
        
        $page_slug = get_post_field( 'post_name', $this->frontend_wizard_page_id );

        $wizard_rule = array(
            $page_slug . '/xspf/?' => sprintf('index.php?pagename=%s&%s=true',$page_slug,wpsstm_tracklists()->qvar_xspf)
        );

        return array_merge($wizard_rule, $rules);
    }

    function frontend_wizard_display($content){
        
        if ( !is_page($this->frontend_wizard_page_id) ) return $content;

        //guest ID
        if ( !$user_id = get_current_user_id() ){
            $user_id = $guest_user_id = wpsstm()->get_options('guest_user_id');
        }

        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
        $required_cap = $post_type_obj->cap->edit_posts;

        if ( !user_can($user_id,$required_cap) ){
            
            $auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
            $auth_text = sprintf(__('Loading a tracklist requires you to be logged.  You can login or subscribe %s.','wpsstm'),$auth_link);
            $notice = sprintf('<p class="wpsstm-notice wpsstm-bottom-notice">%s</p>',$auth_text);
            
            return $notice;
        }

        ob_start();
        $this->wizard_display();
        $output = ob_get_clean();
        
        $form = sprintf('<form method="post" action="%s">%s</form>',get_permalink(),$output);
        $last_entries = $this->frontend_wizard_last_entries();
        
        return $content . $form . $last_entries;
        
    }
    
    function frontend_wizard_get_tracklist_link($link,$post_id){
        global $wp_query;
        
        if ( $post_id != $this->frontend_wizard_page_id ) return $link;
        
        $frontend_feed_url = $wp_query->get($this->qvar_feed_url);

        if ( $frontend_feed_url ) {
            $link = add_query_arg(array($this->qvar_feed_url=>$frontend_feed_url),$link);
        }
        
        return $link;
    }
    
    /*
    For posts that have the 'wpsstm-wizard' status, notice the author that it is a temporary playlist.
    */
    
    function wizard_status_notice($content){
        global $post;
        
        
        if ( get_post_status($post->ID) != wpsstm()->temp_status ) return $content;

        //TO FIX hook action to delete posts after one day as specified below
        
        $trash_time_secs = 1440 * MINUTE_IN_SECONDS;
        $trash_time_human = human_time_diff( 0, $trash_time_secs );
        
        if ( get_current_user_id() == $post->post_author ){
            $save_text = sprintf(__('This is a tempory playlist.  Unless you change its status, it will be deleted in %s.','wpsstm'),$trash_time_human);
        }else{
            $auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
            $save_text = sprintf(__('This is a tempory playlist.  It will be deleted in %s.  You can login or subscribe %s if you want to save playlists to your profile.','wpsstm'),$trash_time_human,$auth_link);
        }

        $notice = sprintf('<p class="wpsstm-notice wpsstm-bottom-notice">%s</p>',$save_text);
        
        return $notice . $content;
        
    }
    
    /*
    If a single post created with the wizard is requested (eg. Tracklist Wizard redirection), set the query as wizard query so the filters below can run
    */
    
    function is_single_wizard_post( $query ) {
        
        if ( $query->get('post_type') !=wpsstm()->post_type_live_playlist ) return $query;
        
        $post_id = $query->get( 'p' );
        
        if (!$post_id){
            $post_name = $query->get( 'name' );
            if ( $post = get_page_by_path($post_name,OBJECT,wpsstm()->post_type_live_playlist) ){
                $post_id = $post->ID;
            }
        }

        if ( !$post_id ) return $query;
        
        $post_status = get_post_status($post_id);

        print_r($post_status);die();

        $is_wizard = get_post_meta($post_id,$this->frontend_wizard_meta_key,true);
        if (!$is_wizard) return $query;

        $query->set($this->qvar_wizard_posts,true);
        return $query;
    }
    
    function filter_wizard_posts( $query ) {
        if ( $query->get('post_type')!=wpsstm()->post_type_live_playlist ) return $query;
        
        $meta_query = $query->get('meta_query');
        
        if ( !$query->get($this->qvar_wizard_posts) ){ //exclude wizard posts
            $meta_query[] = array(
                'key'       => $this->frontend_wizard_meta_key,
                'compare'   => 'NOT EXISTS'
            );
        }else{ //explicitly requested
            $meta_query[] = array(
                'key'   => $this->frontend_wizard_meta_key,
                'compare'   => 'EXISTS'
            );
        }

        $query->set('meta_query',$meta_query);

        return $query;

    }
    
    /*
    If wizard posts are explicitly requested, include drafts
    */
    
    function include_wizard_drafts( $query ) {
        
        if ( $query->get('post_type')!=wpsstm()->post_type_live_playlist ) return $query;
        if ( !$query->get($this->qvar_wizard_posts) ) return $query;
        
        //allow drafts for guest user queries
        $post_statii = $query->get( 'post_status' );
        $post_statii = array_filter((array)$post_statii);
        $post_statii = array_merge($post_statii,array('publish',wpsstm()->temp_status));
        $query->set( 'post_status', $post_statii );
        $query->set( $this->qvar_wizard_posts, true );

        return $query;
        
    }
    
    function frontend_wizard_last_entries(){
        
        $li_items = array();
        
        $query_args = array(
            'post_type'     => wpsstm()->post_type_live_playlist,
            'post_status'   => wpsstm()->temp_status,
            /*
            'meta_query' => array(
                array(
                    'key'       => $this->frontend_wizard_meta_key,
                    'compare'   => 'EXISTS'
                )
            )
            */
        );

        $query = new WP_Query($query_args);
        
        foreach($query->posts as $post){
            $feed_url = wpsstm_get_live_tracklist_url($post->ID);
            $li_items[] = sprintf('<li><a href="%s">%s</a> <small>%s</small></li>',get_permalink($post->ID),get_post_field('post_title',$post->ID),$feed_url);
        }
        if ($li_items){
            return sprintf('<div id="wpsstm-wizard-last-entries"><h2>%s</h2><ul>%s</ul></div>',__('Last requests','wpsstm'),implode("\n",$li_items));
        }
    }

    function wizard_register_scripts_style_shared(){
        // CSS
        wp_register_style( 'wpsstm-scraper-wizard',  wpsstm()->plugin_url . 'scraper/_inc/css/wpsstm-scraper-wizard.css',null,wpsstm()->version );
        
        // JS
        wp_register_script( 'wpsstm-scraper-wizard', wpsstm()->plugin_url . 'scraper/_inc/js/wpsstm-scraper-wizard.js', array('jquery','jquery-ui-tabs'),wpsstm()->version);
    }
    
    function wizard_scripts_styles_backend(){
        wp_enqueue_style('wpsstm-scraper-wizard');
        wp_enqueue_script('wpsstm-scraper-wizard');
    }
    function wizard_scripts_styles_frontend(){
        wp_enqueue_style('wpsstm-scraper-wizard');
        wp_enqueue_script('wpsstm-scraper-wizard');
    }
    
    function metabox_scraper_wizard_register(){

        add_meta_box( 
            'wpsstm-metabox-scraper-wizard', 
            __('Tracklist Importer','wpsstm'),
            array($this,'wizard_display'),
            wpsstm_tracklists()->scraper_post_types, 
            'normal', //context
            'high' //priority
        );

    }

    function frontend_wizard_populate(){
        global $wp_query;

        if ( !is_page($this->frontend_wizard_page_id) ) return;
        
        $wizard_post_id = ( isset($_REQUEST[ 'wpsstm_wizard' ]['post_id']) ) ? $_REQUEST[ 'wpsstm_wizard' ]['post_id'] : null;
        $wizard_url = $wp_query->get($this->qvar_feed_url);
        
        if (!$wizard_post_id && !$wizard_url) return;

        if ( $wizard_post_id ){
            $wizard_id = $_REQUEST[ 'wpsstm_wizard' ]['post_id'];
            $this->setup_wizard_tracklist($wizard_id);
        }elseif( $wizard_url ){
            $this->setup_wizard_tracklist($wizard_url);
        }

        //TO FIX saving should be moved in its own function ?
        $post_id = $this->save_frontend_wizard();

        if ( is_wp_error($post_id) ){
            $this->tracklist->add_notice( 'wizard-header', 'preset_loaded', __('There has been a problem while loading this playlist:','wpsstm') );
        }elseif($post_id){
            wp_redirect( get_permalink($post_id) );
        }
    }
    
    //TO FIX to improve
    function populate_backend_wizard_tracklist(){
        $post_id = (isset($_REQUEST['post'])) ? $_REQUEST['post'] : null;
        if (!$post_id) return;
        
        $post_type = get_post_type($post_id);
        if( !in_array($post_type,wpsstm_tracklists()->scraper_post_types ) ) return;

        $this->setup_wizard_tracklist($post_id);
        
        //re-populate settings
        $this->wizard_settings_init();

    }
    
    function setup_wizard_tracklist($post_id = null){
        $this->tracklist = wpsstm_get_post_tracklist($post_id);
        $this->tracklist->ignore_cache = ( wpsstm_is_backend() && isset($_REQUEST['advanced_wizard']) );
        $this->tracklist->tracks_strict = false;
        $this->tracklist->load_subtracks();
        $this->is_advanced = ( wpsstm_is_backend() && ( $this->tracklist->ignore_cache || ( $this->tracklist->feed_url && !$this->tracklist->tracks ) ) );
    }
    
    //TO FIX to improve
    function wizard_save_metabox($post_id){
        
        $post_type = get_post_type($post_id);
        if( !in_array($post_type,wpsstm_tracklists()->scraper_post_types ) ) return;
        
        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        
        //TO FIX nonce is not validated, TO FIX !
        $is_valid_nonce = ( isset($_POST[ 'wpsstm_scraper_wizard_nonce' ]) && wp_verify_nonce( $_POST['wpsstm_scraper_wizard_nonce'], 'wpsstm_save_scraper_wizard'));

        if ($is_autosave || $is_autodraft || $is_revision || !$is_valid_nonce) return;

        $this->setup_wizard_tracklist($post_id);
        
        if ( !$post_id = $this->tracklist->post_id ) return;

        $wizard_url = ( isset($_REQUEST[ 'wpsstm_feed_url' ]) ) ? trim($_REQUEST[ 'wpsstm_feed_url' ]) : null;
        $reset = ( isset($_REQUEST[ 'wpsstm_wizard' ]['reset']) || !$wizard_url );

        if ( isset($_REQUEST['import-tracks'])){
            $this->convert_to_static_playlist();
        }

        if($reset){
            $this->delete_wizard_datas();
        }else{
            $this->save_feed_url($wizard_url);
            if ( $this->is_advanced ){
                    $wizard_settings = ( isset($_REQUEST['save-scraper-settings']) ) ? $_REQUEST['save-scraper-settings'] : null;
                    $this->save_wizard_settings($wizard_settings);
            }
        }

        return $post_id;

    }
    
    function convert_to_static_playlist(){

        if ( !$tracklist_id = $this->tracklist->post_id ) return;
        $post_type = get_post_type($tracklist_id);
        
        //convert to static playlist if needed
        if ( $post_type == wpsstm()->post_type_live_playlist ){
            if ( !set_post_type( $tracklist_id, wpsstm()->post_type_playlist ) ) {
                return new WP_Error( 'switched_live_playlist_status', __("Error while converting the live tracklist status",'wpsstm') );
            }
        }
        
        if ($this->tracklist->tracks){
            $subtracks_success = $this->tracklist->save_subtracks();
            if ( is_wp_error($subtracks_success) ) return $subtracks_success;
        }
        return $this->delete_wizard_datas(true);

    }
    
    function convert_to_live_playlist(){

        if ( !$tracklist_id = $this->tracklist->post_id ) return;
        $post_type = get_post_type($tracklist_id);

        $this->tracklist->load_subtracks();
        
        if ($this->tracklist->tracks){
            $subtracks_success = $this->tracklist->remove_subtracks();
        }
        
        //convert to live playlist if needed
        if ( $post_type == wpsstm()->post_type_playlist ){
            if ( !set_post_type( $tracklist_id, wpsstm()->post_type_live_playlist ) ) {
                return new WP_Error( 'switched_live_playlist_status', __("Error while reverting the live tracklist status",'wpsstm') );
            }
        }
        
        return $this->restore_wizard_datas();
    }
    
    function delete_wizard_datas($backup = false){
        if ( !$tracklist_id = $this->tracklist->post_id ) return;
        
        if ($backup){
            $feed_url = wpsstm_get_live_tracklist_url($tracklist_id);
            $options = get_post_meta($tracklist_id,wpsstm_live_playlists()->scraper_meta_name,true);
            
            //backup wizard datas
            if($feed_url) update_post_meta($tracklist_id, wpsstm_live_playlists()->feed_url_meta_name.'_old', $feed_url );
            if($options) update_post_meta($tracklist_id,wpsstm_live_playlists()->scraper_meta_name.'_old',$options);
        }
        
        delete_post_meta( $tracklist_id, wpsstm_live_playlists()->feed_url_meta_name );
        delete_post_meta( $tracklist_id, wpsstm_live_playlists()->scraper_meta_name );
        
        return true;
    }
    
    function restore_wizard_datas(){
        if ( !$tracklist_id = $this->tracklist->post_id ) return;
        
        $feed_url = get_post_meta($tracklist_id, wpsstm_live_playlists()->feed_url_meta_name.'_old',true);
        $options = get_post_meta($tracklist_id,wpsstm_live_playlists()->scraper_meta_name.'_old',true);

        //restore wizard datas
        if($feed_url) update_post_meta($tracklist_id, wpsstm_live_playlists()->feed_url_meta_name, $feed_url );
        if($options) update_post_meta($tracklist_id,wpsstm_live_playlists()->scraper_meta_name,$options);
        
        //delete backup
        delete_post_meta( $tracklist_id, wpsstm_live_playlists()->feed_url_meta_name.'_old' );
        delete_post_meta( $tracklist_id, wpsstm_live_playlists()->scraper_meta_name.'_old' );
    }
    
    function save_frontend_wizard(){

        if ( !$post_id = $this->tracklist->post_id ){

            //TO FIX limit for post creations ? (spam/bots, etc.)

            //user check - guest user
            $user_id = $guest_user_id = null;
            if ( !$user_id = get_current_user_id() ){
                $user_id = $guest_user_id = wpsstm()->get_options('guest_user_id');
            }

            //capability check
            $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
            $required_cap = $post_type_obj->cap->edit_posts;

            if ( !user_can($user_id,$required_cap) ){
                return new WP_Error( 'wpsstm_wizard_cap_missing', __("You don't have the capability required to create a new live playlist.",'wpsstm') );
            }else{

                if( !$this->tracklist->tracks ){
                    return new WP_Error( 'wpsstm_wizard_empty_tracks', __('No remote tracks found, abord creating live playlist','wpsstm') );
                }

                $post_args = array(
                    'post_title'    => $this->tracklist->title,
                    'post_type'     => wpsstm()->post_type_live_playlist,
                    'post_status'   => wpsstm()->temp_status,
                    'post_author'   => $user_id,
                    'meta_input'   => array(
                    $this->frontend_wizard_meta_key => true
                    )
                );

                $new_post_id = wp_insert_post( $post_args );
                if ( !is_wp_error($new_post_id) ){
                    $this->tracklist->post_id = $new_post_id;
                }
            }
        }
        
        if ( !$post_id = $this->tracklist->post_id ) return;
        $wizard_url = ( isset($_REQUEST[ 'wpsstm_feed_url' ]) ) ? $_REQUEST[ 'wpsstm_feed_url' ] : null;
        $this->save_feed_url($wizard_url);
        
        //TO FIX
        if ( isset($_REQUEST[ 'save-playlist']) ){
            $post_status = 'publish';
        }

        return $post_id;

    }
                   
    function save_feed_url($feed_url){
            
        if ( !$post_id = $this->tracklist->post_id ) return;
            
        //save feed url
        $feed_url = trim($feed_url);

        if (!$feed_url){
            return delete_post_meta( $post_id, wpsstm_live_playlists()->feed_url_meta_name );
        }else{
            return update_post_meta( $post_id, wpsstm_live_playlists()->feed_url_meta_name, $feed_url );
        }
    }
                        
    function save_wizard_settings($wizard_data){

        if ( !$wizard_data ) return;
        if ( !$post_id = $this->tracklist->post_id ) return;

        //save wizard settings
        $wizard_settings = ( isset($wizard_data[ 'wpsstm_wizard' ]) ) ? $wizard_data[ 'wpsstm_wizard' ] : null;

        $this->tracklist->flush_subtracks(); //TO FIX is this correct / at the right place ?

        //while updating the live tracklist settings, ignore caching
        if ( !$wizard_settings ) return;

        $wizard_settings = $this->sanitize_wizard_settings($wizard_settings);

        //keep only NOT default values
        $default_args = $this->tracklist->options_default;
        $wizard_settings = wpsstm_array_recursive_diff($wizard_settings,$default_args);

        if (!$wizard_settings){
            delete_post_meta($post_id, wpsstm_live_playlists()->scraper_meta_name);
        }else{
            update_post_meta( $post_id, wpsstm_live_playlists()->scraper_meta_name, $wizard_settings );
        }

        do_action('spiff_save_wizard_settings', $wizard_settings, $post_id);

    }

    function wizard_settings_init(){

        /*
        Source
        */

        $this->add_wizard_section(
             'wizard_section_source', //id
             __('Source','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-source' //page
        );

        $this->add_wizard_field(
            'feed_url', //id
            __('URL','wpsstm'), //title
            array( $this, 'feed_url_callback' ), //callback
            'wpsstm-wizard-step-source', //page
            'wizard_section_source', //section
            null //args
        );
        
        /*
        Source feedback
        */

        $this->add_wizard_section(
             'wizard_section_source_feedback', //id
             __('Feedback','wpsstm'), //title
             array( $this, 'section_desc_empty' ), //callback
             'wpsstm-wizard-step-source' //page
        );
        
        if ($this->tracklist->tracks){
            $this->add_wizard_field(
                'feedback_tracklist_content', 
                __('Tracklist','wpsstm'), 
                array( $this, 'feedback_tracklist_callback' ), 
                'wpsstm-wizard-step-source', 
                'wizard_section_source_feedback'
            );
        }
        
        if ( $this->is_advanced ){
            
            /*
            Source feedback
            */

            if ( $this->tracklist->variables ){
                $this->add_wizard_field(
                    'regex_matches', 
                    __('Regex matches','wpsstm'), 
                    array( $this, 'feedback_regex_matches_callback' ), 
                    'wpsstm-wizard-step-source', 
                    'wizard_section_source_feedback'
                );
            }

            /*
            Tracks
            */

            $this->add_wizard_section(
                'wizard_section_tracks', //id
                __('Tracks','wpsstm'), //title
                array( $this, 'section_tracks_desc' ), //callback
                'wpsstm-wizard-step-tracks' //page
            );
            
            $this->add_wizard_field(
                'feedback_data_type', 
                __('Input type','wpsstm'), 
                array( $this, 'feedback_data_type_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );
            
            $this->add_wizard_field(
                'feedback_source_content', 
                __('Input','wpsstm'), 
                array( $this, 'feedback_source_content_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            $this->add_wizard_field(
                'tracks_selector', 
                __('Tracks Selector','wpsstm'), 
                array( $this, 'selector_tracks_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            $this->add_wizard_field(
                'tracks_order', 
                __('Tracks Order','wpsstm'), 
                array( $this, 'tracks_order_callback' ), 
                'wpsstm-wizard-step-tracks', 
                'wizard_section_tracks'
            );

            /*
            Tracks feedback
            */

            $this->add_wizard_section(
                 'wizard_section_tracks_feedback', //id
                 __('Feedback','wpsstm'), //title
                 array( $this, 'section_desc_empty' ), //callback
                 'wpsstm-wizard-step-tracks' //page
            );

            /*
            Single track
            */

            $this->add_wizard_section(
                'wizard-section-single-track', //id
                __('Track details','wpsstm'),
                array( $this, 'section_single_track_desc' ),
                'wpsstm-wizard-step-single-track' //page
            );
            
            $this->add_wizard_field(
                'feedback_tracklist_content', 
                __('Input','wpsstm'), 
                array( $this, 'feedback_tracks_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_artist_selector', 
                __('Artist Selector','wpsstm').'* '.$this->regex_link(),
                array( $this, 'track_artist_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_title_selector', 
                __('Title Selector','wpsstm').'* '.$this->regex_link(), 
                array( $this, 'track_title_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_album_selector', 
                __('Album Selector','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_album_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );
            
            $this->add_wizard_field(
                'track_image_selector', 
                __('Image Selector','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_image_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            $this->add_wizard_field(
                'track_source_urls', 
                __('Source URL','wpsstm').' '.$this->regex_link(), 
                array( $this, 'track_sources_selector_callback' ), 
                'wpsstm-wizard-step-single-track',
                'wizard-section-single-track'
            );

            /*
            Single track feedback
            */

            $this->add_wizard_section(
                 'wizard_section_single_track_feedback', //id
                 __('Feedback','wpsstm'), //title
                 array( $this, 'section_desc_empty' ), //callback
                 'wpsstm-wizard-step-single-track' //page
            );

            /*
            Options
            */

            $this->add_wizard_section(
                'wizard-section-options', //id
                __('Options','wpsstm'),
                array( $this, 'section_desc_empty' ),
                'wpsstm-wizard-step-options' //page
            );

            $this->add_wizard_field(
                'datas_cache_min', 
                __('Enable tracks cache','wpsstm'), 
                array( $this, 'cache_callback' ), 
                'wpsstm-wizard-step-options',
                'wizard-section-options'
            );

            $this->add_wizard_field(
                'enable_musicbrainz', 
                __('Use MusicBrainz','wpsstm'), 
                array( $this, 'musicbrainz_callback' ), 
                'wpsstm-wizard-step-options',
                'wizard-section-options'
            );

        }
        

    }
    
    /*
     * Sanitize wizard data
     */
    
    function sanitize_wizard_settings($input){

        $previous_values = $this->tracklist->get_options();
        $new_input = $previous_values;
        
        //TO FIX isset() check for boolean option - have a hidden field to know that settings are enabled ?

        //cache
        if ( isset($input['datas_cache_min']) && ctype_digit($input['datas_cache_min']) ){
            $new_input['datas_cache_min'] = $input['datas_cache_min'];
        }

        //selectors 

        foreach ((array)$input['selectors'] as $selector_slug=>$value){

            //path
            if ( isset($value['path']) ) {
                $value['path'] = trim($value['path']);
            }
            
            //attr
            if ( isset($value['attr']) ) {
                $value['attr'] = trim($value['attr']);
            }

            //regex
            if ( isset($value['regex']) ) {
                $value['regex'] = trim($value['regex']);
            }
            
            $new_input['selectors'][$selector_slug] = array_filter($value);
            
            
        }

        //order
        $new_input['tracks_order'] = ( isset($input['tracks_order']) ) ? $input['tracks_order'] : null;

        //musicbrainz
        $new_input['musicbrainz'] = ( isset($input['musicbrainz']) ) ? $input['musicbrainz'] : null;
        
        $default_args = $default_args = $this->tracklist->options_default;
        $new_input = array_replace_recursive($default_args,$new_input); //last one has priority

        return $new_input;
    }
    
    function regex_link(){
        return sprintf(
            '<a href="#" title="%1$s" class="wpsstm-wizard-selector-toggle-advanced"><i class="fa fa-cog" aria-hidden="true"></i></a>',
            __('Use Regular Expression','wpsstm')
        );
    }
    
    function css_selector_block($selector){
        
        ?>
        <div class="wpsstm-wizard-selector">
            <?php

            //path
            $path = $this->tracklist->get_options( array('selectors',$selector,'path') );
            $path = ( $path ? htmlentities($path) : null);

            //regex
            $regex = $this->tracklist->get_options( array('selectors',$selector,'regex') );
            $regex = ( $regex ? htmlentities($regex) : null);
        
            //attr
            $attr_disabled = ( $this->tracklist->response_type != 'text/html');
            $attr = $this->tracklist->get_options( array('selectors',$selector,'attr') );
            $attr = ( $attr ? htmlentities($attr) : null);
            

            //build info
        
            $info = null;

            switch($selector){
                    case 'track_artist':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>h4 .artist strong</code>'
                        );
                    break;
                    case 'track_title':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>span.track</code>'
                        );
                    break;
                    case 'track_album':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>span.album</code>'
                        );
                    break;
                    case 'track_image':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>a.album-art img</code> '. sprintf( __('(set %s for attribute)','wpsstm'),'<code>src</code>') . ' ' . __('or an url','wpsstm')
                        );
                    break;
                    case 'track_source_urls':
                        $info = sprintf(
                            __('eg. %s','wpsstm'),
                            '<code>audio source</code> '. sprintf( __('(set %s for attribute)','wpsstm'),'<code>src</code>') . ' ' . __('or an url','wpsstm')
                        );
                    break;
            }
            
            if ($selector!='tracks'){
                echo $this->get_track_detail_selector_prefix();
            }
            
            printf(
                '<input type="text" class="wpsstm-wizard-selector-jquery" name="%1$s[selectors][%2$s][path]" value="%3$s" />',
                'wpsstm_wizard',
                $selector,
                $path
            );

            //regex
            //uses a table so the style matches with the global form (WP-core styling)
            ?>
            <div class="wpsstm-wizard-selector-advanced">
                <?php
                if ($info){
                    printf('<p class="wpsstm-wizard-track-selector-desc">%s</p>',$info);
                }
                ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php _e('Tag attribute','wpsstm');?></th>
                            <td>        
                                <div>
                                    <?php

                                    printf(
                                        '<span class="wpsstm-wizard-selector-attr"><input class="regex" name="%s[selectors][%s][attr]" type="text" value="%s" %s/></span>',
                                        'wpsstm_wizard',
                                        $selector,
                                        $attr,
                                        disabled( $attr_disabled, true, false )
                                    );
                                    ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Regex pattern','wpsstm');?></th>
                            <td>        
                                <div>
                                    <?php

                                    printf(
                                        '<span class="wpsstm-wizard-selector-regex"><input class="regex" name="%1$s[selectors][%2$s][regex]" type="text" value="%3$s"/></span>',
                                        'wpsstm_wizard',
                                        $selector,
                                        $regex
                                    );
                                    ?>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        
    }
    
    function section_desc_empty(){
    }


    function feed_url_callback(){

        $option = $this->tracklist->feed_url;

        printf(
            '<input type="text" name="wpsstm_feed_url" value="%s" class="fullwidth" placeholder="%s" />',
            $option,
            __('URL of the tracklist you would like to get','wpsstm')
        );
        
        //presets
        $presets_list = array();
        $presets_list_str = null;
        foreach ((array)wpsstm_live_playlists()->get_available_presets() as $preset){
            if ( !$preset->wizard_suggest ) continue;
            $preset_str = $preset->preset_name;
            if ($preset->preset_url){
                $preset_str = sprintf('<a href="%s" title="%s" target="_blank">%s</a>',$preset->preset_url,$preset->preset_desc,$preset_str);
            }
            $presets_list[] = $preset_str;
        }

        if ( !empty($presets_list) ){
            $presets_list_str = implode(', ',$presets_list);
            printf('<p id="wpsstm-available-presets"><small><strong>%s</strong> %s</small></p>',__('Available presets:','wpsstm'),$presets_list_str);
        }
        

        

    }

    function feedback_data_type_callback(){

        $output = "—";

        if ( $this->tracklist->response_type ){
            $output = $this->tracklist->response_type;
        }
        
        echo $output;

    }
    
    function feedback_regex_matches_callback(){

        foreach($this->tracklist->variables as $variable_slug => $variable){
            $value_str = ( $variable ) ? sprintf('<code>%s</code>',$variable) : '—';
            printf('<p><strong>%s :</strong> %s',$variable_slug,$value_str);
        }
    }   

    function feedback_source_content_callback(){

        $output = "—";
        
        if ( $body_node = $this->tracklist->body_node ){
            
            $content = $body_node->html();

            //force UTF8
            $content = iconv("ISO-8859-1", "UTF-8", $content); //ISO-8859-1 is from QueryPath

            $content = esc_html($content);
            $output = '<pre class="spiff-raw"><code class="language-markup">'.$content.'</code></pre>';

        }
        
        echo $output;
        

    }
    
    function section_tracks_desc(){

        printf(
            __('Enter a <a href="%s" target="_blank">jQuery selector</a> to target each track item from the tracklist page, for example: %s.','wpsstm'),
            'http://www.w3schools.com/jquery/jquery_ref_selectors.asp',
            '<code>#content #tracklist .track</code>'
        );
        
        $this->tracklist->display_notices('wizard-step-tracks');
    }
    
    function selector_tracks_callback(){  
        $this->css_selector_block('tracks');
    }
    
    function feedback_tracks_callback(){

        $output = "—"; //none
        $tracks_output = array();
        
        if ( $track_nodes = $this->tracklist->track_nodes ){

            foreach ($track_nodes as $single_track_node){
                
                $single_track_html = $single_track_node->innerHTML();

                //force UTF8
                $single_track_html = iconv("ISO-8859-1", "UTF-8", $single_track_html); //ISO-8859-1 is from QueryPath

                $tracks_output[] = sprintf( '<pre class="spiff-raw xspf-track-raw"><code class="language-markup">%s</code></pre>',esc_html($single_track_html) );

            }
            if ($tracks_output){
                
                //reverse
                if ( $this->tracklist->get_options('tracks_order') == 'asc' ){
                    $tracks_output = array_reverse($tracks_output);
                }
                
                $output = sprintf('<div id="spiff-station-tracks-raw">%s</div>',implode(PHP_EOL,$tracks_output));
            }

            
        }


        echo $output;

    }

    function section_single_track_desc(){
        
        $jquery_selectors_link = sprintf('<a href="http://www.w3schools.com/jquery/jquery_ref_selectors.asp" target="_blank">%s</a>',__('jQuery selectors','wpsstm'));
        $regexes_link = sprintf('<a href="http://regex101.com" target="_blank">%s</a>',__('regular expressions','wpsstm'));

        printf(__('Enter a %s to extract the data for each track.','wpsstm'),$jquery_selectors_link);
        echo"<br/>";
        printf(__('It is also possible to target the attribute of an element or to filter the data with a %s by using %s advanced settings for each item.','wpsstm'),$regexes_link,'<i class="fa fa-cog" aria-hidden="true"></i>');
        
        $this->tracklist->display_notices('wizard-step-single-track');
        
    }
    
    function get_track_detail_selector_prefix(){

        $selector = $this->tracklist->get_options(array('selectors','tracks','path'));

        if (!$selector) return;
        return sprintf(
            '<span class="tracks-selector-prefix">%1$s</span>',
            $selector
        );
    }

    function track_artist_selector_callback(){
        $this->css_selector_block('track_artist');
    }

    function track_title_selector_callback(){
        $this->css_selector_block('track_title');
    }

    function track_album_selector_callback(){
        $this->css_selector_block('track_album');
    }
    
    function track_image_selector_callback(){
        $this->css_selector_block('track_image');
    }
    
    function track_sources_selector_callback(){
        $this->css_selector_block('track_source_urls');
    }
    
    function feedback_tracklist_callback(){
        echo $this->tracklist->get_tracklist_table(array('can_play'=>false));
    }

    function cache_callback(){
        $option = $this->tracklist->get_options('datas_cache_min');

        printf(
            '<input type="number" name="%1$s[datas_cache_min]" size="4" min="0" value="%2$s" /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            $option,
            __('Time the remote tracks should be cached (in minutes).','spiff')
        );

        
    }

    function musicbrainz_callback(){
        
        $option = $this->tracklist->get_options('musicbrainz');
        
        printf(
            '<input type="checkbox" name="%1$s[musicbrainz]" value="on" %2$s /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            checked((bool)$option, true, false),
            sprintf(
                __('Try to fix tracks information using <a href="%1$s" target="_blank">MusicBrainz</a>.'),
                'http://musicbrainz.org/').'  <small>'.__('This makes the station render slower : each track takes about ~1 second to be checked!').'</small>'
        );

        
    }
    
    function tracks_order_callback(){
        
        $option = $this->tracklist->get_options('tracks_order');
        
        $desc_text = sprintf(
            '<input type="radio" name="%1$s[tracks_order]" value="desc" %2$s /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            checked($option, 'desc', false),
            __('Descending','spiff')
        );
        
        $asc_text = sprintf(
            '<input type="radio" name="%1$s[tracks_order]" value="asc" %2$s /><span class="wizard-field-desc">%3$s</span>',
            'wpsstm_wizard',
            checked($option, 'asc', false),
            __('Ascending','spiff')
        );
        
        echo $desc_text." ".$asc_text;

        
    }
    
    function wizard_display(){
        $classes = array();
        $classes[]  = ( $this->is_advanced ) ? 'wizard-wrapper-advanced' : 'wizard-wrapper-simple';
        $classes[]  = ( is_admin() ) ? 'wizard-wrapper-backend' : 'wizard-wrapper-frontend';
        
        ?>
        <div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr($classes);?>>
            <?php

            $reset_checked = false;

            $this->tracklist->display_notices('wizard-header');

            if ( !$this->is_advanced ){
                $this->wizard_simple();
            }else{

                $this->tracklist->display_notices('wizard-header-advanced');

                $this->wizard_advanced();
            }

            if ( wpsstm_is_backend() ){
                $post_type = get_post_type();
                if ( ($post_type != wpsstm()->post_type_live_playlist ) && ($this->tracklist->tracks) ){
                    $reset_checked = true;
                    $this->submit_button(__('Import Tracks','wpsstm'),'primary','import-tracks');

                }
            }elseif( $this->tracklist->tracks ){
                
                //user check - guest user
                $user_id = $guest_user_id = null;
                if ( !$user_id = get_current_user_id() ){
                    $user_id = $guest_user_id = wpsstm()->get_options('guest_user_id');
                }
                
                if ( $user_id ){
                    
                    //capability check
                    $post_type_obj = get_post_type_object(wpsstm()->post_type_live_playlist);
                    $required_cap = $post_type_obj->cap->edit_posts;
                    
                    if ( current_user_can($required_cap) ){
                        $this->submit_button(__('Save Playlist','wpsstm'),'primary','save-playlist');
                    }
                    
                }

            }

            $submit_bt_txt = ( !$this->is_advanced ) ? __('Load URL','wpsstm') : __('Save Changes');
            $this->submit_button($submit_bt_txt,'primary','save-scraper-settings');

            if ( $this->tracklist->feed_url && wpsstm_is_backend() ){

                printf(
                    '<small><input type="checkbox" name="%1$s[reset]" value="on" %2$s /><span class="wizard-field-desc">%3$s</span></small>',
                    'wpsstm_wizard',
                    checked($reset_checked, true, false),
                    __('Clear wizard','wpsstm')
                );
            }
        
            if ( $this->is_advanced ){
                ?>
                <input type="hidden" name="advanced_wizard" value="1" />
                <?php
            }
            if ( $this->tracklist->post_id ){
                printf('<input type="hidden" name="%s[post_id]" value="%s" />','wpsstm_wizard',$this->tracklist->post_id);
            }

            wp_nonce_field('wpsstm_save_scraper_wizard','wpsstm_scraper_wizard_nonce',false);
            ?>
        </div>
        <?php
        
    }
    
    private function wizard_simple(){
        ?>

        <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
            <?php $this->do_wizard_sections( 'wpsstm-wizard-step-source' );?>
        </div>
        <?php
        
        if ( wpsstm_is_backend() ){
            if ( $this->tracklist->feed_url && !isset($_REQUEST['advanced_wizard']) ){
                $advanced_wizard_url = get_edit_post_link();
                $advanced_wizard_url = add_query_arg(array('advanced_wizard'=>true),$advanced_wizard_url);
                echo '<p><a href="'.$advanced_wizard_url.'">' . __('Advanced Settings','wpsstm') . '</a></p>';
            }
        }
    }
    
    private function wizard_advanced(){

        ?>
        <div id="wpsstm-wizard-tabs">

            <ul id="wpsstm-wizard-tabs-header">
                <?php $this->wizard_tabs(); ?>
            </ul>

            <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
                <?php $this->do_wizard_sections( 'wpsstm-wizard-step-source' );?>
            </div>

            <div id="wpsstm-wizard-step-tracks-content" class="wpsstm-wizard-step-content">
                <?php $this->do_wizard_sections( 'wpsstm-wizard-step-tracks' );?>
            </div>

            <div id="wpsstm-wizard-step-single-track-content" class="wpsstm-wizard-step-content">
                <?php $this->do_wizard_sections( 'wpsstm-wizard-step-single-track' );?>
            </div>

            <div id="wpsstm-wizard-step-options" class="wpsstm-wizard-step-content">
                <?php $this->do_wizard_sections( 'wpsstm-wizard-step-options' );?>
            </div>

        </div>
        <?php
    }
    
    function wizard_tabs( $active_tab = '' ) {

        $tabs_html    = '';
        $idle_class   = 'nav-tab';
        $active_class = 'nav-tab nav-tab-active';
        
        $source_tab = $tracks_selector_tab = $track_details_tab = $options_tab = $tracklist_tab = array();
        
        $status_icons = array(
            '<i class="fa fa-times-circle" aria-hidden="true"></i>',
            '<i class="fa fa-check-circle" aria-hidden="true"></i>'
        );

        $icon_source_tab = $status_icons[0];
        if ( $this->tracklist->body_node ){
            $icon_source_tab = $status_icons[1];
        }

        $source_tab = array(
            'icon'    => $icon_source_tab,
            'title'     => __('Source','spiff'),
            'href'      => '#wpsstm-wizard-step-source-content'
        );

        $icon_tracks_tab = $status_icons[0];
        if ( $this->tracklist->track_nodes ){
            $icon_tracks_tab = $status_icons[1];
        }

        $tracks_selector_tab = array(
            'icon'    => $icon_tracks_tab,
            'title'  => __('Tracks','spiff'),
            'href'  => '#wpsstm-wizard-step-tracks-content'
        );

        $icon_track_details_tab = $status_icons[0];

        if ( $this->tracklist->tracks ){
            $icon_track_details_tab = $status_icons[1];
        }

        $track_details_tab = array(
            'icon'    => $icon_track_details_tab,
            'title'  => __('Track details','spiff'),
            'href'  => '#wpsstm-wizard-step-single-track-content'
        );

        $options_tab = array(
            'title'  => __('Options','spiff'),
            'href'  => '#wpsstm-wizard-step-options'
        );

        $tabs = array(
            $source_tab,
            $tracks_selector_tab,
            $track_details_tab,
            $options_tab
        );
        
        $tabs = array_filter($tabs);

        // Loop through tabs and build navigation
        foreach ( array_values( $tabs ) as $key=>$tab_data ) {

                $is_current = (bool) ( $key == $active_tab );
                $tab_class  = $is_current ? $active_class : $idle_class;
                $tab_icon =  ( isset($tab_data['icon']) ) ? $tab_data['icon'] : null;
            
                $tabs_html .= sprintf('<li><a href="%s" class="%s">%s %s</a></li>',
                    $tab_data['href'],
                    esc_attr( $tab_class ),
                    $tab_icon,
                    esc_html( $tab_data['title'] )
                );
        }

        echo $tabs_html;
    }

    /*
    Inspired by WP function add_settings_section()
    */
    
    function add_wizard_section($id, $title, $callback, $page) {
        $this->wizard_sections[$page][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback);
    }
    
    /*
    Inspired by WP function add_settings_field()
    */
    
    function add_wizard_field($id, $title, $callback, $page, $section = 'default', $args = array()) {
        $this->wizard_fields[$page][$section][$id] = array('id' => $id, 'title' => $title, 'callback' => $callback, 'args' => $args);
    }
    
    /*
    Inspired by WP function do_settings_sections()
    */
    
    function do_wizard_sections( $page ) {

        if ( ! isset( $this->wizard_sections[$page] ) )
            return;

        foreach ( (array) $this->wizard_sections[$page] as $section ) {
            if ( $section['title'] )
                echo "<h2>{$section['title']}</h2>\n";

            if ( $section['callback'] )
                call_user_func( $section['callback'], $section );

            if ( ! isset( $this->wizard_fields ) || !isset( $this->wizard_fields[$page] ) || !isset( $this->wizard_fields[$page][$section['id']] ) )
                continue;
            echo '<table class="form-table wizard-section-table">';
            $this->do_wizard_fields( $page, $section['id'] );
            echo '</table>';
        }
    }
    
    /*
    Inspired by WP function do_settings_fields()
    */
    
    function do_wizard_fields($page, $section) {

        if ( ! isset( $this->wizard_fields[$page][$section] ) )
            return;

        foreach ( (array) $this->wizard_fields[$page][$section] as $field ) {
            $class = '';

            if ( ! empty( $field['args']['class'] ) ) {
                $class = ' class="' . esc_attr( $field['args']['class'] ) . '"';
            }

            echo "<tr{$class}>";

            if ( ! empty( $field['args']['label_for'] ) ) {
                echo '<th scope="row"><label for="' . esc_attr( $field['args']['label_for'] ) . '">' . $field['title'] . '</label></th>';
            } else {
                echo '<th scope="row">' . $field['title'] . '</th>';
            }

            echo '<td>';
            call_user_func($field['callback'], $field['args']);
            echo '</td>';
            echo '</tr>';
        }
    }
    
    /*
    Inspired by WP function submit_button()
    */
    
    function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
        echo $this->get_submit_button( $text, $type, $name, $wrap, $other_attributes );
    }
    
    /*
    Inspired by WP function get_submit_button()
    */
    
    function get_submit_button( $text = '', $type = 'primary large', $name = 'submit', $wrap = true, $other_attributes = '' ) {
        if ( ! is_array( $type ) )
            $type = explode( ' ', $type );

        $button_shorthand = array( 'primary', 'small', 'large' );
        $classes = array( 'button' );
        foreach ( $type as $t ) {
            if ( 'secondary' === $t || 'button-secondary' === $t )
                continue;
            $classes[] = in_array( $t, $button_shorthand ) ? 'button-' . $t : $t;
        }
        // Remove empty items, remove duplicate items, and finally build a string.
        $class = implode( ' ', array_unique( array_filter( $classes ) ) );

        $text = $text ? $text : __( 'Save Changes' );

        // Default the id attribute to $name unless an id was specifically provided in $other_attributes
        $id = $name;
        if ( is_array( $other_attributes ) && isset( $other_attributes['id'] ) ) {
            $id = $other_attributes['id'];
            unset( $other_attributes['id'] );
        }

        $attributes = '';
        if ( is_array( $other_attributes ) ) {
            foreach ( $other_attributes as $attribute => $value ) {
                $attributes .= $attribute . '="' . esc_attr( $value ) . '" '; // Trailing space is important
            }
        } elseif ( ! empty( $other_attributes ) ) { // Attributes provided as a string
            $attributes = $other_attributes;
        }

        // Don't output empty name and id attributes.
        $name_attr = $name ? ' name="' . esc_attr( $name ) . '"' : '';
        $id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';

        $button = '<input type="submit"' . $name_attr . $id_attr . ' class="' . esc_attr( $class );
        $button .= '" value="' . esc_attr( $text ) . '" ' . $attributes . ' />';

        if ( $wrap ) {
            $button = '<p class="submit">' . $button . '</p>';
        }

        return $button;
    }
}

function wpsstm_wizard() {
	return WP_SoundSystem_Core_Wizard::instance();
}

wpsstm_wizard();
