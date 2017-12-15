<?php

class WP_SoundSystem_Core_Sources{

    var $providers = array();
    var $source_url_metakey = '_wpsstm_source_url';
    var $source_stream_metakey = '_wpsstm_source_stream';
    var $source_provider_metakey = '_wpsstm_source_provider';
    var $qvar_source_action = 'source-action';

    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSystem_Core_Sources;
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
    }

    function setup_actions(){
        
        add_action( 'init', array($this,'register_post_type_sources' ));
        add_filter( 'query_vars', array($this,'add_query_vars_source') );

        add_filter( 'pre_get_posts', array($this,'filter_backend_sources_by_track_id') );
        
        add_action( 'wpsstm_register_submenus', array( $this, 'backend_sources_submenu' ) );
        add_action( 'add_meta_boxes', array($this, 'metabox_source_register'));
        
        add_action( 'save_post', array($this,'metabox_parent_track_save')); 
        add_action( 'save_post', array($this,'metabox_source_urls_save')); 

        add_action( 'wp_enqueue_scripts', array( $this, 'register_sources_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_sources_scripts_styles_shared' ) );

        add_action( 'add_meta_boxes', array($this, 'metabox_sources_register'));

        add_filter('manage_posts_columns', array($this,'column_sources_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'column_sources_content'), 10, 2 );
        
        add_filter( 'manage_posts_columns', array($this,'column_source_url_register'), 10, 2 ); 
        add_action( 'manage_pages_custom_column', array($this,'column_source_url_content'), 10, 2 );
        
        add_filter( 'manage_posts_columns', array($this,'column_track_match_register'), 10, 2 ); 
        add_filter( 'manage_posts_columns', array($this,'column_order_register'), 10, 2 ); 
        add_action( 'manage_pages_custom_column', array($this,'column_track_match_content'), 10, 2 );
        
        add_filter( sprintf("views_edit-%s",wpsstm()->post_type_source), array(wpsstm(),'register_community_view') );
        
        /*
        AJAX
        */
        
        //get track autosources
        add_action('wp_ajax_wpsstm_autosources_list', array($this,'ajax_track_autosource'));
        add_action('wp_ajax_nopriv_wpsstm_autosources_list', array($this,'ajax_track_autosource'));
        
        //delete source
        add_action('wp_ajax_wpsstm_delete_source', array($this,'ajax_delete_source'));

    }
    
    function add_query_vars_source( $qvars ) {
        $qvars[] = $this->qvar_source_action;
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
        global $wpsstm_source;
        
        //TO FIX move elsewhere ? the_post filter ?
        $wpsstm_source = new WP_SoundSystem_Source($post->ID);
        
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
        
    }
    
    function parent_track_content( $post ){
        ?>
        <div style="text-align:center">
            <?php
                $track = new WP_SoundSystem_Track($post->post_parent);
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
        
        $source = new WP_SoundSystem_Source($post->ID);
        
        ?>
        <p>
            <h2><?php _e('URL','wpsstm');?></h2>
            <input type="text" name="wpsstm_source_url" class="wpsstm-fullwidth" value="<?php echo $source->url;?>" />
        </p>
        <?php
        wp_nonce_field( 'wpsstm_source_meta_box', 'wpsstm_source_meta_box_nonce' );

    }

    /**
    Save source URL field for this post
    **/
    
    function metabox_source_urls_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = ( isset($_POST['wpsstm_source_meta_box_nonce']) && wp_verify_nonce( $_POST['wpsstm_source_meta_box_nonce'], 'wpsstm_source_meta_box' ) );
        if ( !$is_valid_nonce || $is_autodraft || $is_autosave || $is_revision ) return;

        $source_url = ( isset($_POST[ 'wpsstm_source_url' ]) ) ? $_POST[ 'wpsstm_source_url' ] : null;
        
        //TO FIX validate URL

        if (!$source_url){
            delete_post_meta( $post_id, $this->source_url_metakey );
        }else{
            update_post_meta( $post_id, $this->source_url_metakey, $source_url );
        }

    }
    
    
    function register_sources_scripts_styles_shared(){
        //CSS
        //JS
        wp_register_script( 'wpsstm-track-sources', wpsstm()->plugin_url . '_inc/js/wpsstm-track-sources.js', array('jquery','jquery-core','jquery-ui-core','jquery-ui-sortable'),wpsstm()->version );
    }

    
    function metabox_sources_register(){
        
        $metabox_post_types = array(
            wpsstm()->post_type_track
        );

        add_meta_box( 
            'wpsstm-track-sources', 
            __('Track sources','wpsstm'),
            array($this,'metabox_sources_content'),
            $metabox_post_types, 
            'normal', //context
            'default' //priority
        );
        
    }
    
    function metabox_sources_content( $post ){
        global $wpsstm_track;

        $wpsstm_track = new WP_SoundSystem_Track($post->ID);

        $sources_url = $wpsstm_track->get_track_popup_url('sources-manager');
        $sources_url = add_query_arg(array('TB_iframe'=>true),$sources_url);

        $manager_link = sprintf('<a class="wpsstm-link-popup button" href="%s">%s</a>',$sources_url,__('Sources manager','wpsstm'));
        $backend_link = sprintf('<a class="button" href="%s">%s</a>',$wpsstm_track->get_backend_sources_url(),__('Listing','wpsstm'));
        
        printf('<p>%s%s</p>',$manager_link,$backend_link);
        
    }

    function column_sources_register($defaults) {
        global $post;

        $post_types = array(
            wpsstm()->post_type_track
        );
        
        $before = array();
        $after = array();
        
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$post_types) ){
            $after['sources'] = __('Sources','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }

    function column_sources_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
            case 'sources':
                
                $published_str = $pending_str = null;

                $track = new WP_SoundSystem_Track($post_id);
                $sources_published_query = $track->query_sources();
                $sources_pending_query = $track->query_sources(array('post_status'=>'pending'));

                $url = admin_url('edit.php');
                $url = add_query_arg( array('post_type'=>wpsstm()->post_type_source,'post_parent'=>$post_id,'post_status'=>'publish'),$url );
                $published_str = sprintf('<a href="%s">%d</a>',$url,$sources_published_query->post_count);
                
                if ($sources_pending_query->post_count){
                    $url = admin_url('edit.php');
                    $url = add_query_arg( array('post_type'=>wpsstm()->post_type_source,'post_parent'=>$post_id,'post_status'=>'pending'),$url );
                    $pending_link = sprintf('<a href="%s">%d</a>',$url,$sources_pending_query->post_count);
                    $pending_str = sprintf('<small> +%s</small>',$pending_link);
                }
                
                echo $published_str . $pending_str;
                
            break;
        }
    }
    
    function column_source_url_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : null;
        if ( $post_type != wpsstm()->post_type_source ) return $defaults;
        
        if ( $post_type == wpsstm()->post_type_source ){
            $after['sources_list'] = __('URL','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }

    function column_source_url_content($column,$post_id){
        global $post;
        global $wpsstm_source;
        
        switch ( $column ) {
            case 'sources_list':
                
                $wpsstm_source = new WP_SoundSystem_Source($post_id);
                
                $link  = sprintf('<a class="wpsstm-source-provider" href="%s" target="_blank" title="%s">%s</a>',$wpsstm_source->url,$wpsstm_source->title,$wpsstm_source->url);
                
                echo $link;
                
            break;
        }
    }
    
    function column_track_match_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : null;
        if ( $post_type != wpsstm()->post_type_source ) return $defaults;
        
        if ( $post_type == wpsstm()->post_type_source ){
            $after['track_match'] = __('Match','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }
    
    function column_order_register($defaults) {
        global $post;

        $before = array();
        $after = array();
        
        $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : null;
        if ( $post_type != wpsstm()->post_type_source ) return $defaults;
        
        if ( $post_type == wpsstm()->post_type_source ){
            $after['order'] = __('Order','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }

    function column_track_match_content($column,$post_id){
        global $post;
        global $wpsstm_source;
        
        $wpsstm_source = new WP_SoundSystem_Source($post_id);
        
        switch ( $column ) {
            case 'order':
                if ($wpsstm_source->index != -1){
                    echo $wpsstm_source->index;
                }else{
                    echo '—';
                }
            break;
            case 'track_match':

                if ( $match = $wpsstm_source->match ){
                    
                    $track = new WP_SoundSystem_Track($post->post_parent);
                    if ($track->artist && $track->artist){
                        
                        $sources_url = $track->get_backend_sources_url();
                        
                        $track_label = sprintf('<strong>%s</strong> — %s',$track->artist,$track->title);
                        
                        $track_edit_url = get_edit_post_link( $track->post_id );
                        $track_edit_link = sprintf('<a href="%s" alt="%s">%s</a>',$track_edit_url,__('Edit track','wpsstm'),$track_label);
                        
                        $track_sources_link = sprintf('<a href="%s" alt="%s">%s</a>',$sources_url,__('Filter sources','wpsstm'),'<i class="fa fa-filter" aria-hidden="true"></i>');

                        printf('<p>%s %s</p>',$track_edit_link,$track_sources_link);
                    }
                    
                    $percent_bar = wpsstm_get_percent_bar($match);
                    printf('<p>%s</p>',$percent_bar);
                    
                    
                }else{
                    echo '—';
                }
            break;
        }
    }
    
    //TO FIX TO enable somewhere
    function sort_sources_by_track_match($sources,WP_SoundSystem_Track $track){

        //reorder by similarity
        usort($sources, function ($a, $b){
            return $a->match === $b->match ? 0 : ($a->match > $b->match ? -1 : 1);
        });
        
        return $sources;
        
    }
    
    function ajax_track_autosource(){
        global $wpsstm_track;
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'new_html'  => null,
            'success'   => false
        );

        $track = new WP_SoundSystem_Track();
        $track->from_array($ajax_data['track']);
        $success = $track->autosource();
        
        $result['track'] = $wpsstm_track = $track;

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
    
    function ajax_delete_source(){
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'success'   => false
        );

        $source = new WP_SoundSystem_Source($ajax_data['post_id']);
        $success = $source->delete_source();
        
        if ( is_wp_error($success) ){
            
            $result['message'] = $success->get_error_message();
            
        }else{
            
            $result['success'] = true;
            
        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }

    
    function can_autosource(){
        $community_user_id = wpsstm()->get_options('community_user_id');
        if (!$community_user_id) return;

        $sources_post_type_obj = get_post_type_object(wpsstm()->post_type_source);
        $autosource_cap = $sources_post_type_obj->cap->edit_posts;
        return user_can($community_user_id,$autosource_cap);
    }

}

function wpsstm_sources() {
	return WP_SoundSystem_Core_Sources::instance();
}
wpsstm_sources();