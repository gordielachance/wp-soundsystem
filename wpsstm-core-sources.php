<?php

class WP_SoundSytem_Core_Sources{
    
    var $sources_metakey = '_wpsstm_source_urls';
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_Sources;
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
        
        //add_action( 'wp_enqueue_scripts', array($this,'sources_frontend_script_styles'));
        add_action( 'admin_enqueue_scripts', array($this,'sources_backend_script_styles'));

        add_action( 'add_meta_boxes', array($this, 'metabox_sources_register'));
        add_action( 'save_post', array($this,'metabox_sources_save'), 5); 

        add_filter('manage_posts_columns', array($this,'column_sources_register'), 10, 2 );
        add_action( 'manage_posts_custom_column', array($this,'column_sources_content'), 10, 2 );

    }

    function sources_backend_script_styles(){
        
        //CSS
        wp_enqueue_style( 'wpsstm-sources',  wpsstm()->plugin_url . '_inc/css/wpsstm-admin-metabox-sources.css', null, wpsstm()->version );
        
        //JS
        wp_enqueue_script( 'wpsstm-sources', wpsstm()->plugin_url . '_inc/js/wpsstm-admin-metabox-sources.js', array('jquery','wpsstm-shortenTables'),wpsstm()->version);
    }
    
    function metabox_sources_register(){
        
        $metabox_post_types = array(
            wpsstm()->post_type_track
        );

        add_meta_box( 
            'wpsstm-track-sources', 
            __('Sources','wpsstm'),
            array($this,'metabox_sources_content'),
            $metabox_post_types, 
            'normal', //context
            'default' //priority
        );
        
    }
    
    function metabox_sources_content( $post ){
        $desc = __('Add sources to this tracks.  It could be a local audio file or a link to a music service.','wpsstm');
        printf('<p>%s</p>',$desc);
        
        $links = $this->get_sources_field_editable($post->ID,'wpsstm_source_urls');
        printf('<div>%s</div>',$links);
        wp_nonce_field( 'wpsstm_sources_meta_box', 'wpsstm_sources_meta_box_nonce' );
    }
    
    function get_sources_field_editable( $post_id, $field_name ){

        $sources = wpsstm_get_post_sources($post_id, true, true); //include suggested sources

        if ( empty($sources) ) $sources = array(null); //blank
        
        $sources_auto = wpsstm_get_post_sources_auto($post_id);
        $sources_suggested = wpsstm_get_post_sources_suggested($post_id);
        
        $placeholder = __("Enter a track source URL",'wpsstm');
        
        $rows = array();

        foreach ( $sources as $key=>$source ){
            
            $disabled = false;

            $source_classes = array('wpsstm-source');
            if ($key==0){
                $source_classes[] = 'wpsstm-source-blank';
            }
            
            //auto
            if ( in_array($source,$sources_auto) ){
                $source_classes[] = 'wpsstm-source-auto';
                $disabled = true;
            }
            //suggested
            if ( in_array($source,$sources_suggested) ){
                $disabled = true;
                $source_classes[] = 'wpsstm-source-suggested';
            }
            
            $disabled_str = disabled( $disabled, true, false );
   
            $content = sprintf('<input type="text" name="%s[]"  value="%s" placeholder="%s" %s/>',$field_name,$source,$placeholder,$disabled_str);
            
            $icon_plus = '<i class="fa fa-plus-circle wpsstm-source-icon-add wpsstm-source-icon" aria-hidden="true"></i>';
            $icon_minus = '<i class="fa fa-minus-circle wpsstm-source-icon-delete wpsstm-source-icon" aria-hidden="true"></i>';
            
            $content .= sprintf('<span class="wpsstm-source-action">%s%s</span>',$icon_plus,$icon_minus);

            $rows[] = sprintf('<p %s>%s</p>',wpsstm_get_classes_attr($source_classes),$content);
        }
        
        return implode("\n",$rows);

    }
    
    /**
    Save track field for this post
    **/
    
    function metabox_sources_save( $post_id ) {

        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_sources_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_revision ) return;
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_track);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_sources_meta_box_nonce'], 'wpsstm_sources_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_sources_meta_box_nonce']);

        $sources = ( isset($_POST[ 'wpsstm_source_urls' ]) ) ? $_POST[ 'wpsstm_source_urls' ] : array();

        $this->update_post_sources($post_id,$sources);
    }
    
    function update_post_sources($post_id,$sources,$append=false){
        
        if ($append){
            $existing_sources = wpsstm_get_post_sources($post_id);
            $sources = array_merge((array)$existing_sources,$sources);
        }
        
        $sources = $this->sanitize_sources($sources);
        
        if (!$sources){
            return delete_post_meta( $post_id, $this->sources_metakey );
        }else{
            return update_post_meta( $post_id, $this->sources_metakey, $sources );
        }
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
                if ($sources = wpsstm_get_post_sources_list( $post_id, true ) ){
                    $output = count($sources);
                }
                echo $output;
            break;
        }
    }
    
    function sanitize_sources($sources){
        $sources = array_filter($sources);
        $sources = array_unique($sources);
        return $sources;
    }
    
}


function wpsstm_sources() {
	return WP_SoundSytem_Core_Sources::instance();
}

wpsstm_sources();