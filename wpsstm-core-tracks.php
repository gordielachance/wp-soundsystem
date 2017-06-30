<?php

class WP_SoundSytem_Core_Tracks{

    public $metakey = '_wpsstm_track';
    public $qvar_track = 'lookup_track';
    public $qvar_subtracks_hide = 'hide_subtracks';
    public $mbtype = 'recording'; //musicbrainz type, for lookups
    
    public $subtracks_hide = true; //default hide subtracks in track listings
    public $favorited_track_meta_key = '_wpsstm_user_favorite';
    public $sources_metakey = '_wpsstm_sources';

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_Tracks;
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
        if ( $subtracks_hide_db = get_option('wpsstm_subtracks_hide') ){
            $this->subtracks_hide = ($subtracks_hide_db == 'on') ? true : false;
        }
    }

    function setup_actions(){

        add_action( 'init', array($this,'register_post_type_track' ));
        add_filter( 'query_vars', array($this,'add_query_var_track') );
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_track') );
        add_action( 'save_post', array($this,'update_title_track'), 99);

        add_action( 'add_meta_boxes', array($this, 'metabox_track_register'));
        add_action( 'save_post', array($this,'metabox_track_title_save'), 5); 
        
        //add_filter('manage_posts_columns', array($this,'column_track_register'), 10, 2 );
        //add_action( 'manage_posts_custom_column', array($this,'column_track_content'), 10, 2 );
        
        //tracklist shortcode
        add_shortcode( 'wpsstm-track',  array($this, 'shortcode_track'));
        
        //subtracks
        add_action( 'admin_notices',  array($this, 'toggle_subtracks_notice') );
        add_action( 'current_screen',  array($this, 'toggle_subtracks_store_option') );
        add_filter( 'pre_get_posts', array($this,'default_exclude_subtracks') );
        add_filter( 'pre_get_posts', array($this,'exclude_subtracks') );
        
        //ajax : toggle love tracklist
        add_action('wp_ajax_wpsstm_love_unlove_track', array($this,'ajax_love_unlove_track'));
        
        //ajax : get tracks source auto
        add_action('wp_ajax_wpsstm_player_get_track_sources_auto', array($this,'ajax_get_track_sources_auto'));
        add_action('wp_ajax_nopriv_wpsstm_player_get_track_sources_auto', array($this,'ajax_get_track_sources_auto'));
        
        //ajax : playlist selector
        add_action('wp_ajax_wpsstm_track_playlists_selector', array($this,'ajax_popup_track_playlists'));
        
        //ajax : sources manager
        add_action('wp_ajax_wpsstm_track_sources_manager', array($this,'ajax_popup_track_sources'));
        
        //ajax : add new tracklist
        add_action('wp_ajax_wpsstm_create_playlist', array($this,'ajax_create_playlist'));
        
        //ajax : add/remove playlist track
        add_action('wp_ajax_wpsstm_add_playlist_track', array($this,'ajax_add_playlist_track'));
        add_action('wp_ajax_wpsstm_remove_playlist_track', array($this,'ajax_remove_playlist_track'));
        
        
    }
    
    /*
    Display a notice (and link) to toggle view subtracks
    */
    
    function toggle_subtracks_notice(){
        $screen = get_current_screen();
        if ( $screen->post_type != wpsstm()->post_type_track ) return;
        if ( $screen->base != 'edit' ) return;
        
        $toggle_value = ($this->subtracks_hide) ? 'off' : 'on';
        
        $link = admin_url('edit.php');
        $post_status = ( isset($_REQUEST['post_status']) ) ? $_REQUEST['post_status'] : null;
        
        if ( $post_status ){
            $link = add_query_arg(array('post_status'=>$post_status),$link);
        }
        
        $link = add_query_arg(array('post_type'=>wpsstm()->post_type_track,'wpsstm_subtracks_hide'=>$toggle_value),$link);
        


        $notice_link = sprintf( '<a href="%s">%s</a>',$link,__('here','wpsstm') );
        
        $notice = null;
        
        if ($this->subtracks_hide){
            $notice = sprintf(__('Click %s if you want to include tracks belonging to albums and playlists in this listing.','wpsstm'),$notice_link);
        }else{
            $notice = sprintf(__('Click %s if you want to exclude tracks belonging to albums and playlists of this listing.','wpsstm'),$notice_link);
        }

        printf('<div class="notice notice-warning"><p>%s</p></div>',$notice);

    }
    
    /*
    Toggle view subtracks : store option then redirect
    */
    
    function toggle_subtracks_store_option(){
        $screen = get_current_screen();
        if ( $screen->post_type != wpsstm()->post_type_track ) return;
        if ( $screen->base != 'edit' ) return;
        if ( !isset($_REQUEST['wpsstm_subtracks_hide']) ) return;
        
        $value = $_REQUEST['wpsstm_subtracks_hide'];

        update_option( 'wpsstm_subtracks_hide', $value );
        
        $this->subtracks_hide = ($value == 'on') ? true : false;

    }
    
    function default_exclude_subtracks( $query ) {
        
        //only for main query
        if ( !$query->is_main_query() ) return $query;
        
        //only for tracks
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;
        
        //already defined
        if ( $query->get($this->qvar_subtracks_hide) ) return $query;
        
        //option enabled ?
        if ($this->subtracks_hide){
            $query->set($this->qvar_subtracks_hide,true);
        }

        return $query;
    }
    
    
    /**
    If query var 'hide_subtracks' is set,
    Filter tracks queries so tracks belonging to tracklists (albums/playlists/live playlists)) are not listed.
    TO FIX should update the post count too. see wp_count_posts
    **/
    
    function exclude_subtracks( $query ) {
        
        //only for main query
        if ( !$query->is_main_query() ) return $query;
        
        //only for tracks
        if ( $query->get('post_type') != wpsstm()->post_type_track ) return $query;
        
        //hide subtracks ?
        
        if ( $query->get($this->qvar_subtracks_hide) ){
            if ( $subtrack_ids = wpsstm_get_all_subtrack_ids() ) {
                $query->set('post__not_in',$subtrack_ids);
            }
        }

        return $query;
    }
    
    function column_track_register($defaults) {
        global $post;

        $allowed_post_types = array(
            wpsstm()->post_type_track
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$allowed_post_types) ){
            $after['track'] = __('Track','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }
    
    function column_track_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
            case 'track':
                $output = '—';
                if ($track = wpsstm_get_post_track($post_id) ){
                    $output = $track;
                }
                echo $output;
            break;
        }
    }

    function pre_get_posts_track( $query ) {

        if ( ($artist = $query->get( wpsstm_artists()->qvar_artist )) && ($track = $query->get( $this->qvar_track )) ){

            $query->set( 'meta_query', array(
                array(
                     'key'     => $this->metakey,
                     'value'   => $track,
                     'compare' => '='
                ),
                array(
                     'key'     => wpsstm_artists()->metakey,
                     'value'   => $artist,
                     'compare' => '='
                )
            ));
        }

        return $query;
    }
    
    /**
    Update the post title to match the artist/album/track, so we still have a nice post permalink
    **/
    
    function update_title_track( $post_id ) {
        
        //only for tracks
        if (get_post_type($post_id) != wpsstm()->post_type_track) return;

        //check capabilities
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $has_cap = current_user_can('edit_post', $post_id);
        if ( $is_autosave || $is_autodraft || $is_revision || !$has_cap ) return;

        $title = wpsstm_get_post_track($post_id);
        $artist = wpsstm_get_post_artist($post_id);
        $post_title = sanitize_text_field( sprintf('<span itemprop="byArtist">%s</span> <span itemprop="inAlbum">%s</span>',$artist,$title) );

        //use get_post_field here instead of get_the_title() so title is not filtered
        if ( $post_title == get_post_field('post_title',$post_id) ) return;

        //log
        wpsstm()->debug_log(array('post_id'=>$post_id,'post_title'=>$post_title),"update_title_track()"); 

        $args = array(
            'ID'            => $post_id,
            'post_title'    => $post_title
        );

        remove_action( 'save_post',array($this,'update_title_track'), 99 ); //avoid infinite loop - ! hook priorities
        wp_update_post( $args );
        add_action( 'save_post',array($this,'update_title_track'), 99 );

    }

    function register_post_type_track() {

        $labels = array( 
            'name' => _x( 'Tracks', 'wpsstm' ),
            'singular_name' => _x( 'Track', 'wpsstm' )
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'title','thumbnail', 'comments' ),
            'taxonomies' => array( 'post_tag' ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            //http://justintadlock.com/archives/2013/09/13/register-post-type-cheat-sheet
            'capability_type' => 'post', //track
            //'map_meta_cap'        => true,
            /*
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_track',
                'read_post'              => 'read_track',
                'delete_post'            => 'delete_track',

                // primitive/meta caps
                'create_posts'           => 'create_tracks',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_tracks',
                'edit_others_posts'      => 'manage_tracks',
                'publish_posts'          => 'manage_tracks',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_tracks',
                'delete_private_posts'   => 'manage_tracks',
                'delete_published_posts' => 'manage_tracks',
                'delete_others_posts'    => 'manage_tracks',
                'edit_private_posts'     => 'edit_tracks',
                'edit_published_posts'   => 'edit_tracks'
            ),
            */
        );

        register_post_type( wpsstm()->post_type_track, $args );
    }
    
    function add_query_var_track( $qvars ) {
        $qvars[] = $this->qvar_track;
        $qvars[] = $this->qvar_subtracks_hide;
        return $qvars;
    }
    
    function metabox_track_register(){
        
        $metabox_post_types = array(
            wpsstm()->post_type_track
        );

        add_meta_box( 
            'wpsstm-track', 
            __('Track','wpsstm'),
            array($this,'metabox_track_content'),
            $metabox_post_types, 
            'after_title', 
            'high' 
        );
        
    }
    
    function metabox_track_content( $post ){

        $track_title = get_post_meta( $post->ID, $this->metakey, true );
        
        ?>
        <input type="text" name="wpsstm_track" class="wpsstm-fullwidth" value="<?php echo $track_title;?>" placeholder="<?php printf("Enter track title here",'wpsstm');?>"/>
        <?php
        wp_nonce_field( 'wpsstm_track_meta_box', 'wpsstm_track_meta_box_nonce' );

    }
    

    
    function mb_populate_trackid( $post_id ) {
        
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        if ( $is_autosave || $is_autodraft || $is_revision ) return;
        
        //already had an MBID
        //$trackid = wpsstm_get_post_mbid($post_id);
        //if ($trackid) return;

        //requires a title
        $track = wpsstm_get_post_track($post_id);
        if (!$track) return;
        
        //requires an artist
        $artist = wpsstm_get_post_artist($post_id);
        if (!$artist) return;

        
    }
    
    /**
    Save track field for this post
    **/
    
    function metabox_track_title_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_track_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_track);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_track_meta_box_nonce'], 'wpsstm_track_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_track_meta_box_nonce']);

        $track = ( isset($_POST[ 'wpsstm_track' ]) ) ? $_POST[ 'wpsstm_track' ] : null;

        if (!$track){
            delete_post_meta( $post_id, $this->metakey );
        }else{
            update_post_meta( $post_id, $this->metakey, $track );
        }

    }
    
    function shortcode_track( $atts ) {
        global $post;

        // Attributes
        $default = array(
            'post_id'       => $post->ID 
        );
        $atts = shortcode_atts($default,$atts);
        
        //check post type
        $this->allowed_post_types = array(
            wpsstm()->post_type_track
        );
        $post_type = get_post_type($atts['post_id']);
        if ( !in_array($post_type,$this->allowed_post_types) ) return;
        
        $track = new WP_SoundSystem_Track(  array('post_id'=>$atts['post_id']) );
        $tracks = array($track);
        $tracklist = new WP_SoundSytem_Tracklist( $atts['post_id'] );

        $tracklist->add($tracks);
        return $tracklist->get_tracklist_table();

    }
    
    function ajax_love_unlove_track(){

        $ajax_data = wp_unslash($_POST);

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );

        $do_love = $result['do_love'] = ( isset($ajax_data['do_love']) ) ? filter_var($ajax_data['do_love'], FILTER_VALIDATE_BOOLEAN) : null; //ajax do send strings
        
        $track_args = $result['track_args'] = $ajax_data['track'];

        $track = $result['track'] = new WP_SoundSystem_Track($track_args);
        
        wpsstm()->debug_log( json_encode($track,JSON_UNESCAPED_UNICODE), "ajax_love_unlove_track()"); 
        
        if ( ($do_love!==null) ){

            if ( $success = $track->love_track($do_love) ){
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
    
    function ajax_get_track_sources_auto(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'new_html'  => null,
            'success'   => false
        );

        $args = $result['args'] = array(
            'title'     => ( isset($ajax_data['track']['title']) ) ?    $ajax_data['track']['title'] : null,
            'artist'    => ( isset($ajax_data['track']['artist']) ) ?   $ajax_data['track']['artist'] : null,
            'album'     => ( isset($ajax_data['track']['album']) ) ?    $ajax_data['track']['album'] : null
        );

        $track = new WP_SoundSystem_Track($args);
        $track->sources = $track->get_track_sources_auto();

        $track = $result['track'] = $track;

        $result['new_html'] = wpsstm_sources()->get_track_sources_list($track);
        $result['success'] = true;

        header('Content-type: application/json');
        wp_send_json( $result ); 

    }
    
    function ajax_popup_track_playlists(){

        /*
        Track block
        */
        
        $ajax_data = $_REQUEST;
        $track_args = isset($ajax_data['track']) ? $ajax_data['track'] : null;
        $track = new WP_SoundSystem_Track($track_args);
        $tracks = array($track);
        $tracklist = new WP_SoundSytem_Tracklist( $atts['post_id'] );
        $tracklist->add($tracks);
        $tracklist_table = $tracklist->get_tracklist_table(array('can_play'=>false));

        /*
        Playlists list
        */
        
        $filter_playlists_input = sprintf('<p><input id="wpsstm-playlists-filter" type="text" placeholder="%s" /></p>',__('Type to filter playlists or create a new one','wpsstm'));
        
        $list_all = wpsstm_get_user_playlists_list();
        
        $post_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
        $labels = get_post_type_labels($post_type_obj);
        $submit = sprintf('<input type="submit" value="%s"/>',$labels->add_new);
        $new_playlist_bt = sprintf('<p id="wpsstm-new-playlist-add">%s</p>',$submit);
        
        $existing_playlists_wrapper = sprintf('<div id="wpsstm-filter-playlists"%s%s%s</div>',$filter_playlists_input,$list_all,$new_playlist_bt);

        printf('<div id="wpsstm-tracklist-chooser-list" class="wpsstm-popup-content">%s%s</div>',$tracklist_table,$existing_playlists_wrapper);
        die();
    }
    
    function ajax_popup_track_sources(){
        $ajax_data = wp_unslash($_REQUEST);
        $track_args = $ajax_data['track'];
        $track = new WP_SoundSystem_Track($track_args);
        
        echo $track->get_track_sources_wizard(); //TO FIX check is not redundent with metabox_sources_content()
    }

    function ajax_create_playlist(){
        $ajax_data = wp_unslash($_POST);
        
        wpsstm()->debug_log($ajax_data,"ajax_create_playlist()"); 

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false,
            'new_html'  => null
        );

        //create tracklist
        $tracklist_title = $result['tracklist_title'] = ( isset($ajax_data['playlist_title']) ) ? trim($ajax_data['playlist_title']) : null;

        $playlist = new WP_SoundSytem_Tracklist();
        $playlist->title = $tracklist_title;
        
        $tracklist_id = $playlist->save_playlist();
        
        if ( is_wp_error($tracklist_id) ){
            
            $code = $tracklist_id->get_error_code();
            $result['message'] = $tracklist_id->get_error_message($code);
            
        }else{
            
            $result['playlist_id'] = $tracklist_id;
            $result['success'] = true;
            $result['new_html'] = wpsstm_get_user_playlists_list();

        }

        header('Content-type: application/json');
        wp_send_json( $result ); 
        
    }
    
    function ajax_add_playlist_track(){
        $ajax_data = wp_unslash($_POST);
        
        wpsstm()->debug_log($ajax_data,"ajax_create_playlist()"); 

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false,
            'new_html'  => null
        );
        
        $track_args = isset($ajax_data['track']) ? $ajax_data['track'] : null;
        $playlist_id = $result['playlist_id'] = isset($ajax_data['playlist_id']) ? $ajax_data['playlist_id'] : null;

        //create|get subtrack
        $track_args['tracklist_id'] = $playlist_id;
        $subtrack = new WP_SoundSystem_Subtrack($track_args);
        
        wpsstm()->debug_log($subtrack,"ajax_add_playlist_track()");
        
        $track_id = $subtrack->save_track();

        if ( is_wp_error($track_id) ){
            $code = $track_id->get_error_code();
            $result['message'] = $track_id->get_error_message($code);
        }else{
            $result['success'] = true;
            $result['track_id'] = $track_id;
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    function ajax_remove_playlist_track(){
        $ajax_data = wp_unslash($_POST);
        
        wpsstm()->debug_log($ajax_data,"ajax_create_playlist()"); 

        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false,
            'new_html'  => null
        );
        
        $track_args = isset($ajax_data['track']) ? $ajax_data['track'] : null;
        $playlist_id = $result['playlist_id'] = isset($ajax_data['playlist_id']) ? $ajax_data['playlist_id'] : null;
        
        //create|get subtrack
        $track_args['tracklist_id'] = $playlist_id;
        $subtrack = new WP_SoundSystem_Subtrack($track_args);
        
        wpsstm()->debug_log($subtrack,"ajax_remove_playlist_track()");
        
        if ( $success = $subtrack->remove_subtrack() ){
            $result['success'] = true;
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
}

function wpsstm_tracks() {
	return WP_SoundSytem_Core_Tracks::instance();
}

wpsstm_tracks();