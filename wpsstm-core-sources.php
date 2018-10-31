<?php

class WPSSTM_Core_Sources{

    var $providers = array();
    static $source_url_metakey = '_wpsstm_source_url';
    static $qvar_source_action = 'source-action';
    static $autosource_time_metakey = '_wpsstm_autosource_time'; //to store the musicbrainz datas

    function __construct() {
        global $wpsstm_source;

        //initialize global (blank) $wpsstm_source so plugin never breaks when calling it.
        $wpsstm_source = new WPSSTM_Source();
        
        add_action( 'init', array($this,'register_post_type_sources' ));

        add_filter( 'query_vars', array($this,'add_query_vars_source') );

        add_action( 'wpsstm_register_submenus', array( $this, 'backend_sources_submenu' ) );
        add_action( 'add_meta_boxes', array($this, 'metabox_source_register'));
        
        add_action( 'save_post', array($this,'metabox_parent_track_save')); 
        add_action( 'save_post', array($this,'metabox_source_url_save'));
        add_action( 'save_post', array($this,'metabox_save_track_sources') );

        add_action( 'wp_enqueue_scripts', array( $this, 'register_sources_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_sources_scripts_styles_shared' ) );

        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_source), array(__class__,'source_columns_register'), 10, 2 );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_source), array(__class__,'source_columns_content'), 10, 2 );

        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_source), array(wpsstm(),'register_community_view') );
        
        /*
        QUERIES
        */
        add_action( 'the_post', array($this,'the_source'),10,2);
        add_action( 'current_screen',  array($this, 'the_single_backend_source'));
        add_filter( 'pre_get_posts', array($this,'filter_backend_sources_by_track_id') );
        
        /*
        AJAX
        */
        
        //get track autosources
        add_action('wp_ajax_wpsstm_autosources_list', array($this,'ajax_track_autosource'));
        add_action('wp_ajax_nopriv_wpsstm_autosources_list', array($this,'ajax_track_autosource'));
        
        //delete source
        add_action('wp_ajax_wpsstm_trash_source', array($this,'ajax_trash_source'));

    }
    
    function add_query_vars_source( $qvars ) {
        $qvars[] = self::$qvar_source_action;
        return $qvars;
    }

    function register_post_type_sources() {

        $labels = array(
            'name'                  => _x( 'Track Sources', 'Track Sources General Name', 'wpsstm' ),
            'singular_name'         => _x( 'Track Source', 'Track Source Singular Name', 'wpsstm' ),
            'menu_name'             => __( 'Track Sources', 'wpsstm' ),
            'name_admin_bar'        => __( 'Track Source', 'wpsstm' ),
            'archives'              => __( 'Track Source Archives', 'wpsstm' ),
            'attributes'            => __( 'Track Source Attributes', 'wpsstm' ),
            'parent_item_colon'     => __( 'Parent Track:', 'wpsstm' ),
            'all_items'             => __( 'All Track Sources', 'wpsstm' ),
            'add_new_item'          => __( 'Add New Track Source', 'wpsstm' ),
            //'add_new'               => __( 'Add New', 'wpsstm' ),
            'new_item'              => __( 'New Track Source', 'wpsstm' ),
            'edit_item'             => __( 'Edit Track Source', 'wpsstm' ),
            'update_item'           => __( 'Update Track Source', 'wpsstm' ),
            'view_item'             => __( 'View Track Source', 'wpsstm' ),
            'view_items'            => __( 'View Track Sources', 'wpsstm' ),
            'search_items'          => __( 'Search Track Sources', 'wpsstm' ),
            //'not_found'             => __( 'Not found', 'wpsstm' ),
            //'not_found_in_trash'    => __( 'Not found in Trash', 'wpsstm' ),
            //'featured_image'        => __( 'Featured Image', 'wpsstm' ),
            //'set_featured_image'    => __( 'Set featured image', 'wpsstm' ),
            //'remove_featured_image' => __( 'Remove featured image', 'wpsstm' ),
            //'use_featured_image'    => __( 'Use as featured image', 'wpsstm' ),
            'insert_into_item'      => __( 'Insert into track source', 'wpsstm' ),
            'uploaded_to_this_item' => __( 'Uploaded to this track source', 'wpsstm' ),
            'items_list'            => __( 'Track Sources list', 'wpsstm' ),
            'items_list_navigation' => __( 'Track Sources list navigation', 'wpsstm' ),
            'filter_items_list'     => __( 'Filter track sources list', 'wpsstm' ),
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => true,
            'supports' => array( 'author','title'),
            'taxonomies' => array(),
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
            /**
             * A string used to build the edit, delete, and read capabilities for posts of this type. You 
             * can use a string or an array (for singular and plural forms).  The array is useful if the 
             * plural form can't be made by simply adding an 's' to the end of the word.  For example, 
             * array( 'box', 'boxes' ).
             */
            'capability_type'     => 'track', // string|array (defaults to 'post')

            /**
             * Whether WordPress should map the meta capabilities (edit_post, read_post, delete_post) for 
             * you.  If set to FALSE, you'll need to roll your own handling of this by filtering the 
             * 'map_meta_cap' hook.
             */
            'map_meta_cap'        => true, // bool (defaults to FALSE)

            /**
             * Provides more precise control over the capabilities than the defaults.  By default, WordPress 
             * will use the 'capability_type' argument to build these capabilities.  More often than not, 
             * this results in many extra capabilities that you probably don't need.  The following is how 
             * I set up capabilities for many post types, which only uses three basic capabilities you need 
             * to assign to roles: 'manage_examples', 'edit_examples', 'create_examples'.  Each post type 
             * is unique though, so you'll want to adjust it to fit your needs.
             */
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
            )
        );

        register_post_type( wpsstm()->post_type_source, $args );
    }
    
    /*
    Register the global $wpsstm_tracklist obj (hooked on 'the_post' action) for tracklists
    For single tracks, check the_track function in -core-tracks.php
    */
    
    function the_source($post,$query){
        global $wpsstm_source;
        if ( $query->get('post_type') == wpsstm()->post_type_source ){
            //set global $wpsstm_source
            $wpsstm_source = new WPSSTM_Source($post->ID);
        }
    }
    
    function the_single_backend_source(){
        global $post;
        global $wpsstm_source;
        $screen = get_current_screen();
        if ( ( $screen->base == 'post' ) && ( $screen->post_type == wpsstm()->post_type_source )  ){
            $post_id = isset($_GET['post']) ? $_GET['post'] : null;
            //set global $wpsstm_source
            $wpsstm_source = new WPSSTM_Source($post_id);
        }
    }
    
    //add custom admin submenu under WPSSTM
    function backend_sources_submenu($parent_slug){

        //capability check
        $post_type_slug = wpsstm()->post_type_source;
        $post_type_obj = get_post_type_object($post_type_slug);
        
         add_submenu_page(
                $parent_slug,
                $post_type_obj->labels->name, //page title - TO FIX TO CHECK what is the purpose of this ?
                $post_type_obj->labels->name, //submenu title
                $post_type_obj->cap->edit_posts, //cap required
                sprintf('edit.php?post_type=%s',$post_type_slug) //url or slug
         );
        
    }

    /*
    On backend sources listings, allow to filter by track ID
    */
    
    function filter_backend_sources_by_track_id($query){

        if ( !is_admin() ) return $query;
        if ( !$query->is_main_query() ) return $query;
        if ( $query->get('post_type') != wpsstm()->post_type_source ) return $query;

        $track_id = ( isset($_GET['post_parent']) ) ? $_GET['post_parent'] : null;
        
        if ( !$track_id ) return $query;
        
        $query->set('post_parent',$track_id);
    }
    
    function metabox_source_register(){
        global $post;

        $track_post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        
        add_meta_box(
            'wpsstm-parent-track', 
            $track_post_type_obj->labels->parent_item_colon, 
            array($this,'parent_track_content'),
            wpsstm()->post_type_source, 
            'side', 
            'core'
        );

        add_meta_box( 
            'wpsstm-source-urls', 
            __('Source URL','wpsstm'),
            array($this,'metabox_source_content'),
            wpsstm()->post_type_source, 
            'after_title', 
            'high' 
        );
        
        add_meta_box( 
            'wpsstm-track-sources', 
            __('Track sources','wpsstm'),
            array($this,'metabox_track_sources_content'),
            wpsstm()->post_type_track, 
            'normal', //context
            'default' //priority
        );
        
    }
    
    function parent_track_content( $post ){
        ?>
        <div style="text-align:center">
            <?php
                $track = new WPSSTM_Track($post->post_parent);
                if ($track->post_id){
                    printf('<p><strong>%s</strong> — %s</p>',$track->artist,$track->title);
                }
            ?>
        <label class="screen-reader-text" for="wpsstm_source_parent_id"><?php _e('Parent') ?></label>
        <input name="wpsstm_source_parent_id" type="number" value="<?php echo $post->post_parent;?>" />
        </div>
        <?php
        wp_nonce_field( 'wpsstm_track_parent_meta_box', 'wpsstm_track_parent_meta_box_nonce' );
    }
    
    /**
    Save source URL field for this post
    **/
    
    function metabox_parent_track_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset($_POST['wpsstm_track_parent_meta_box_nonce']) && wp_verify_nonce( $_POST['wpsstm_track_parent_meta_box_nonce'], 'wpsstm_track_parent_meta_box' ) );
        if ( !$is_valid_nonce || $is_autodraft || $is_autosave || $is_revision ) return;
        
        unset($_POST['wpsstm_track_parent_meta_box_nonce']); //so we avoid the infinite loop

        $parent_id = ( isset($_POST[ 'wpsstm_source_parent_id' ]) ) ? $_POST[ 'wpsstm_source_parent_id' ] : null;
        $parent_post_type = get_post_type($parent_id);
        if ( $parent_post_type != wpsstm()->post_type_track ) $parent_id = null;

        $success = wp_update_post(array(
            'ID' =>             $post_id,
            'post_parent' =>    $parent_id,
        ),true);

    }

    function metabox_source_content( $post ){
        
        $source = new WPSSTM_Source($post->ID);
        
        ?>
        <p>
            <h2><?php _e('URL','wpsstm');?></h2>
            <input type="text" name="wpsstm_source_url" class="wpsstm-fullwidth" value="<?php echo $source->permalink_url;?>" />
        </p>
        <?php
        wp_nonce_field( 'wpsstm_source_meta_box', 'wpsstm_source_meta_box_nonce' );

    }
    
    function metabox_track_sources_content( $post ){
        global $wpsstm_track;
        $track_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $can_edit_track = current_user_can($track_type_obj->cap->edit_post,$wpsstm_track->post_id);
        ?>

        <p>
            <?php _e('Add sources to this track.  It could be a local audio file or a link to a music service.','wpsstm');?>
        </p>
        <p>
            <?php _e("If no sources are set and that the 'Auto-Source' setting is enabled, We'll try to find a source automatically when the tracklist is played.",'wpsstm');?>
        </p>

        <?php
        
        if ( $then = get_post_meta( $post->ID, self::$autosource_time_metakey, true ) ){
            $now = current_time( 'timestamp' );
            $refreshed = human_time_diff( $now, $then );
            $refreshed = sprintf(__('Last autosource query: %s ago.','wpsstm'),$refreshed);
            echo '  ' . $refreshed;
        }

        //track sources
        $wpsstm_track->populate_sources();
        wpsstm_locate_template( 'track-sources.php', true, false );

        ?>
        <p class="wpsstm-new-track-sources-container">
            <?php
            $input_attr = array(
                'id' => 'wpsstm-new_track-sources',
                'name' => 'wpsstm_new_track_sources[]',
                'icon' => '<i class="fa fa-link" aria-hidden="true"></i>',
                'placeholder' => __("Enter a source URL",'wpsstm')
            );
            echo $input = wpsstm_get_backend_form_input($input_attr);
            ?>
        </p>
        <p class="wpsstm-submit-wrapper">
            <?php
            //autosource
            if ( ( wpsstm()->get_options('autosource') == 'on' ) && (WPSSTM_Core_Sources::can_autosource() === true) ){
                ?>
                <input id="wpsstm-autosource-bt" type="submit" name="wpsstm_track_autosource" class="button" value="<?php _e('Autosource','wpsstm');?>">
                <?php
            }
        
            //list sources
            $post_sources_url = admin_url(sprintf('edit.php?post_type=%s&post_parent=%s',wpsstm()->post_type_source,$wpsstm_track->post_id));
            printf('<a href="%s" class="button">%s</a>',$post_sources_url,__('Backend listing','wpsstm'));

            //add sources
            printf('<a id="wpsstm-add-source-url" href="#" class="button">%s</a>',__('Add source URL','wpsstm'));
            ?>
        </p>
        <?php
        wp_nonce_field( 'wpsstm_track_sources_meta_box', 'wpsstm_track_sources_meta_box_nonce' );
    }

    /**
    Save source URL field for this post
    **/
    
    function metabox_source_url_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset($_POST['wpsstm_source_meta_box_nonce']) && wp_verify_nonce( $_POST['wpsstm_source_meta_box_nonce'], 'wpsstm_source_meta_box' ) );
        if ( !$is_valid_nonce || $is_autodraft || $is_autosave || $is_revision ) return;

        $source_url = ( isset($_POST[ 'wpsstm_source_url' ]) ) ? $_POST[ 'wpsstm_source_url' ] : null;
        
        //TO FIX validate URL

        if (!$source_url){
            delete_post_meta( $post_id, self::$source_url_metakey );
        }else{
            update_post_meta( $post_id, self::$source_url_metakey, $source_url );
        }

    }
    
    function metabox_save_track_sources( $post_id ) {
        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_track_sources_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_track);
        if ( $post_type != wpsstm()->post_type_track ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_track_sources_meta_box_nonce'], 'wpsstm_track_sources_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        $track = new WPSSTM_Track($post_id);
        
        //new source URLs
        $source_urls = isset($_POST['wpsstm_new_track_sources']) ? $_POST['wpsstm_new_track_sources'] : array();
        $new_sources = array();

        foreach((array)$source_urls as $url){
            //TOFIXKKK where is track ?
            $source = new WPSSTM_Source(null,$track);
            $source->permalink_url = $url;
            $source->save_source();//save only if it does not exists yet
            $new_sources[] = $source;
        }

        //autosource & save
        if ( isset($_POST['wpsstm_track_autosource']) ){
            $success = $track->autosource();
        }
    }
    
    
    function register_sources_scripts_styles_shared(){
        //CSS
        //JS
        wp_register_script( 'wpsstm-track-sources', wpsstm()->plugin_url . '_inc/js/wpsstm-track-sources.js', array('jquery','jquery-core','jquery-ui-core','jquery-ui-sortable'),wpsstm()->version, true );
    }

    
    public static function source_columns_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $after['source_url'] = __('URL','wpsstm');
        $after['parent_track'] = __('Track','wpsstm');
        
        if ( isset($_GET['post_parent']) ){
            $after['order'] = __('Order','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }

    public static function source_columns_content($column,$post_id){
        global $post;
        global $wpsstm_source;
        
        switch ( $column ) {
            case 'source_url':
                
                $link  = sprintf('<a class="wpsstm-source-provider" href="%s" target="_blank" title="%s">%s</a>',$wpsstm_source->permalink_url,$wpsstm_source->title,$wpsstm_source->permalink_url);
                
                echo $link;
                
            break;
            case 'order':
                if ($wpsstm_source->index != -1){
                    echo $wpsstm_source->index;
                }else{
                    echo '—';
                }
            break;
            case 'parent_track':

                if ( $track = new WPSSTM_Track($post->post_parent) ){
                    
                    if ($track->artist && $track->artist){
                        
                        $sources_url = $track->get_backend_sources_url();
                        
                        $track_label = sprintf('<strong>%s</strong> — %s',$track->artist,$track->title);
                        
                        $track_edit_url = get_edit_post_link( $track->post_id );
                        $track_edit_link = sprintf('<a href="%s" alt="%s">%s</a>',$track_edit_url,__('Edit track','wpsstm'),$track_label);
                        
                        $track_sources_link = sprintf('<a href="%s" alt="%s">%s</a>',$sources_url,__('Filter sources','wpsstm'),'<i class="fa fa-filter" aria-hidden="true"></i>');

                        printf('<p>%s %s</p>',$track_edit_link,$track_sources_link);
                    }

                }else{
                    echo '—';
                }
            break;
        }
    }

    function ajax_track_autosource(){
        global $wpsstm_track;
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'timestamp' => current_time('timestamp'),
            'message'   => null,
            'new_html'  => null,
            'success'   => false
        );

        //set global $wpsstm_track
        $wpsstm_track = new WPSSTM_Track();
        $wpsstm_track->from_array($ajax_data['track']);        
        
        //autosource
        $success = $wpsstm_track->autosource();
        $result['track'] = $wpsstm_track;

        if ( is_wp_error($success) ){
            $result['message'] = $success->get_error_message();
        }elseif( $success ){
            ob_start();
            wpsstm_locate_template( 'track-sources.php', true, false );
            $sources_list = ob_get_clean();
            $result['new_html'] = $sources_list;
            $result['success'] = true;

        }

        header('Content-type: application/json');
        wp_send_json( $result ); 

    }
    
    function ajax_trash_source(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );

        $source = new WPSSTM_Source($ajax_data['post_id']);
        $success = $source->trash_source();
        
        if ( is_wp_error($success) ){
            
            $result['message'] = $success->get_error_message();
            
        }else{
            
            $result['success'] = true;
            
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
    
    static function can_autosource(){

        //community user
        $community_user_id = wpsstm()->get_options('community_user_id');
        if (!$community_user_id){
            return new WP_Error( 'wpsstm_autosource',__('Autosource requires a community user to be set.','wpsstm') );   
        }
        
        //spotify API
        $client_id = wpsstm()->get_options('spotify_client_id');
        $client_secret = wpsstm()->get_options('spotify_client_secret');
        if (!$client_id || !$client_secret){
            return new WP_Error( 'wpsstm_autosource',__('Autosource requires access to the Spotify API.','wpsstm') );   
        }

        //capability check
        $sources_post_type_obj = get_post_type_object(wpsstm()->post_type_source);
        if ($sources_post_type_obj){ //be sure post type is registered before doing that check - eg. it isn't when saving settings.
            $autosource_cap = $sources_post_type_obj->cap->edit_posts;
            if ( !$has_cap = user_can($community_user_id,$autosource_cap) ){
                $error = sprintf(__("Autosource requires the community user to have the %s capability granted.",'wpsstm'),'<em>'.$autosource_cap.'</em>');
                return new WP_Error( 'wpsstm_autosource',$error );
            }
        }
        
        //TOFIXKKK TO CHECK has sources providers ?

        return true;
        
    }
}