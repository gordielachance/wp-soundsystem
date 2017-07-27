<?php

class WP_SoundSystem_Core_Sources{

    var $providers = array();
    var $url_metakey = '_wpsstm_source';

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
        
        add_action( 'add_meta_boxes', array($this, 'metabox_source_register'));
        add_action( 'save_post', array($this,'metabox_source_save'), 5); 
        
        add_action( 'wp_enqueue_scripts', array( $this, 'register_sources_scripts_styles_shared' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'register_sources_scripts_styles_shared' ) );

        add_action( 'add_meta_boxes', array($this, 'metabox_sources_register'));

        add_filter('manage_posts_columns', array($this,'column_sources_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'column_sources_content'), 10, 2 );
        
        add_filter( 'manage_posts_columns', array($this,'column_source_url_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'column_source_url_content'), 10, 2 );
        
        //ajax : sources manager : suggest
        add_action('wp_ajax_wpsstm_suggest_editable_sources', array($this,'ajax_suggest_editable_sources'));

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
            'hierarchical' => true, //TO FIX not working
            'supports' => array( 'title'),
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

        register_post_type( wpsstm()->post_type_source, $args );
    }
    
    function metabox_source_register(){

        add_meta_box( 
            'wpsstm-artist', 
            __('Source URL','wpsstm'),
            array($this,'metabox_source_content'),
            wpsstm()->post_type_source, 
            'after_title', 
            'high' 
        );
        
    }

    function metabox_source_content( $post ){

        $source_url = get_post_meta( $post->ID, $this->url_metakey, true );
        
        ?>
        <input type="text" name="wpsstm_source" class="wpsstm-fullwidth" value="<?php echo $source_url;?>" placeholder="<?php printf("Enter source URL here",'wpsstm');?>"/>
        <?php
        wp_nonce_field( 'wpsstm_source_meta_box', 'wpsstm_source_meta_box_nonce' );

    }
    
    /**
    Save artist field for this post
    **/
    
    function metabox_source_save( $post_id ) {

        //check save status
        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_source_meta_box_nonce']);
        if ( !$is_metabox || $is_autodraft || $is_autosave || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        if ( $post_type != wpsstm()->post_type_source ) return;

        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_source_meta_box_nonce'], 'wpsstm_source_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_source_meta_box_nonce']);

        $source = ( isset($_POST[ 'wpsstm_source' ]) ) ? $_POST[ 'wpsstm_source' ] : null;
        
        //TO FIX validate URL

        if (!$source){
            delete_post_meta( $post_id, $this->url_metakey );
        }else{
            update_post_meta( $post_id, $this->url_metakey, $source );
        }

    }
    
    
    function register_sources_scripts_styles_shared(){
        //CSS
        wp_register_style( 'wpsstm-track-sources', wpsstm()->plugin_url . '_inc/css/wpsstm-track-sources.css', null,wpsstm()->version );
        //JS
        wp_register_script( 'wpsstm-track-sources', wpsstm()->plugin_url . '_inc/js/wpsstm-track-sources.js', array('jquery'),wpsstm()->version );
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

        $track = new WP_SoundSystem_Track($post->ID);
        $list = wpsstm_sources()->get_track_sources_list($track);
        
        $sources_url = $track->get_track_admin_gui_url('sources');
        $sources_url = add_query_arg(array('TB_iframe'=>true),$sources_url);

        $manager_link = sprintf('<a class="thickbox button" href="%s">%s</a>',$sources_url,__('Sources manager','wpsstm'));
        
        printf('<p>%s</p><p>%s</p>',$list,$manager_link);
        
    }
    
    function get_sources_inputs($source_ids = null){
        
        if (!$source_ids) $source_ids = array();

        $field_name = 'wpsstm_track_sources';

        array_unshift($source_ids,null); //add blank line
        
        $rows = array();

        foreach ( $source_ids as $key=>$source_id ){

            $source = new WP_SoundSystem_Source($source_id);

            $disabled = $readonly = false;
            $source_title_el = $source_url_el = null;

            $source_classes = array('wpsstm-source');

            //origin
            if ( $source->origin == 'auto' ){
                $disabled = $readonly = true;
                $source_classes[] = 'wpsstm-source-auto';
            }
            
            $disabled_str = disabled( $disabled, true, false );
            $readonly_str = wpsstm_readonly( $readonly, true, false );

            //icon
            $icon_link = $source->get_provider_link();
            
            //title
            $source_title_attr_arr = array(
                'name'          => sprintf('%s[%s][title]',$field_name,$key),
                'value'         => $source->title
            );
            
            //url
            $source_url_attr_arr = array(
                'name'          => sprintf('%s[%s][url]',$field_name,$key),
                'value'         => $source->url,
                'placeholder'   => __("Source URL",'wpsstm'),
            );
            
            $source_title_el = sprintf('<input type="hidden" class="wpsstm-source-title" %s %s/>',wpsstm_get_html_attr($source_title_attr_arr),$disabled_str);
            $source_url_el = sprintf('<input type="text" class="wpsstm-editable-source-url" %s %s %s/>',wpsstm_get_html_attr($source_url_attr_arr),$disabled_str,$readonly_str);
            
            $content_url = sprintf('<span class="wpsstm-source-icon">%s</span>',$icon_link);
            
            $content_url .= sprintf('<span class="wpsstm-source-fields">%s%s</span>',$source_title_el,$source_url_el);

            $icon_plus = '<i class="fa fa-plus-circle wpsstm-source-icon-add wpsstm-source-icon" aria-hidden="true"></i>';
            $icon_minus = '<i class="fa fa-minus-circle wpsstm-source-icon-delete wpsstm-source-icon" aria-hidden="true"></i>';
            
            $content_url .= sprintf('<span class="wpsstm-source-action">%s%s</span>',$icon_plus,$icon_minus);

            $attr_arr = array(
                'class'                     => implode(' ',$source_classes),
                'data-wpsstm-source-origin' => $source->origin,
                'data-wpsstm-source-id'     => $source->post_id,
            );

            $rows[] = sprintf('<div %s>%s</div>',wpsstm_get_html_attr($attr_arr),$content_url);
        }
        return implode("\n",$rows);
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
                $output = '—';
                $track = new WP_SoundSystem_Track($post_id);
                echo count($track->source_ids);
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
            $after['source_url'] = __('URL','wpsstm');
        }
        
        return array_merge($before,$defaults,$after);
    }

    //TO FIX NOT WORKING
    function column_source_url_content($column,$post_id){
        global $post;

        switch ( $column ) {
            case 'source_url':
                $output = '—';
                if( $source_url = get_post_meta( $post_id, $this->url_metakey, true ) ){
                    $output = $source_url;
                }
                echo $output;
            break;
        }
    }
    
    function get_track_sources_list(WP_SoundSystem_Track $track){

        $lis = array();
        $sources = array();

        foreach((array)$track->source_ids as $source_id){
            $source = new WP_SoundSystem_Source($source_id);
            if (!$source->src) continue;
            $sources[] = $source;
        }

        $sources = $track->sort_sources_by_similarity($sources);

        foreach($sources as $key=>$source){
            
            $source_icon = $source_type = $source_title = null;            
            $link = $source->get_provider_link();
            
            $li_classes = array('wpsstm-source');
            
            $attr_arr = array(
                'class' =>                          implode(' ',$li_classes),
                'data-wpsstm-source-id' =>          $source->post_id,
                'data-wpsstm-source-idx' =>         $key,
                'data-wpsstm-source-type'   =>      $source->type,
                'data-wpsstm-source-src'   =>       $source->src,
                'data-wpsstm-source-origin'   =>    $source->origin,
            );
            
            $li_classes = null;
            $error_icon = '<i class="wpsstm-source-error fa fa-exclamation-triangle" aria-hidden="true"></i>';
            $lis[] = sprintf('<li %s>%s %s</li>',wpsstm_get_html_attr($attr_arr),$error_icon,$link);
            
        }
        if ( !empty($lis) ){
            return sprintf('<ul class="wpsstm-track-sources-list">%s</ul>',implode("",$lis));
        }
    }
    
    function ajax_suggest_editable_sources(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input'     => $ajax_data,
            'message'   => null,
            'new_html'  => null,
            'success'   => false
        );

        $track = new WP_SoundSystem_Track($ajax_data['post_id']);
        $track->populate_auto_sources();

        $track = $result['track'] = $track;

        $result['new_html'] = wpsstm_sources()->get_sources_inputs($track->source_ids);
        $result['success'] = true;

        header('Content-type: application/json');
        wp_send_json( $result ); 

    }
    


}

function wpsstm_sources() {
	return WP_SoundSystem_Core_Sources::instance();
}

wpsstm_sources();