<?php
class WP_SoundSytem_Core_Sources{
    
    var $sources_metakey = '_wpsstm_sources';
    var $providers = array();
    
    var $source_blank = array(
        'url'           => null,
        'title'         => null
    );
    
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
        
        echo $this->get_sources_field_editable($post->ID,'wpsstm_sources');
        
        wp_nonce_field( 'wpsstm_sources_meta_box', 'wpsstm_sources_meta_box_nonce' );
    }
    
    function get_sources_field_editable( $post_id, $field_name ){
        
        $track = new WP_SoundSystem_Track( array('post_id'=>$post_id) );
        $sources = $track->sources;

        //find the sources that have been added by filters
        $sources_strict = $this->get_track_sources_db($post_id,false);

        //array diff can only compare strings so convert them
        $sources_filters = array_diff(array_map('serialize',(array)$sources),array_map('serialize',(array)$sources_strict));

        //suggested remote sources (cached only)
        $sources_remote = $this->get_track_sources_remote( $track,array('cache_only'=>true) );
        
        $sources = array_merge((array)$sources,(array)$sources_remote);
        $sources = wpsstm_sources()->sanitize_sources($sources);
        
        $sources[] = $this->source_blank; //add blank row

        $placeholder = __("Enter a track source URL",'wpsstm');
        
        $rows = array();

        foreach ( $sources as $key=>$source ){
            
            $disabled = false;

            $source_classes = array('wpsstm-source');
            if ($key==0){
                $source_classes[] = 'wpsstm-source-blank';
            }
            
            //filters
            if ( in_array($source,$sources_filters) ){
                $source_classes[] = 'wpsstm-source-auto';
                $disabled = true;
            }
            //remote
            if ( in_array($source,$sources_remote) ){
                $disabled = true;
                $source_classes[] = 'wpsstm-source-suggested';
            }
            
            $disabled_str = disabled( $disabled, true, false );
   
            $content_url = sprintf('<small>%s</small>',$source['title']);
            $content_url .= sprintf('<input type="text" name="%s[%s][url]"  value="%s" placeholder="%s" %s/>',$field_name,$key,$source['url'],$placeholder,$disabled_str);
            
            
            $icon_plus = '<i class="fa fa-plus-circle wpsstm-source-icon-add wpsstm-source-icon" aria-hidden="true"></i>';
            $icon_minus = '<i class="fa fa-minus-circle wpsstm-source-icon-delete wpsstm-source-icon" aria-hidden="true"></i>';
            
            $content_url .= sprintf('<span class="wpsstm-source-action">%s%s</span>',$icon_plus,$icon_minus);
            $content_url = sprintf('<p class="wpsstm-source-url">%s</p>',$content_url);

            $rows[] = sprintf('<div %s>%s</div>',wpsstm_get_classes_attr($source_classes),$content_url);
        }
        
        $rows = implode("\n",$rows);
        return sprintf('<div class="wpsstm-sources">%s</div>',$rows);

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

        $sources = ( isset($_POST[ 'wpsstm_sources' ]) ) ? $_POST[ 'wpsstm_sources' ] : array();

        $this->update_post_sources($post_id,$sources);
    }
    
    function sanitize_sources($sources){
        
        $new_sources = array();

        foreach((array)$sources as $key=>$source){
            $new_source = wp_parse_args($source,$this->source_blank);
            if ( !$new_source['url'] ) continue;
            $new_sources[] = $new_source;
        }
        
        $sources = array_unique($new_sources, SORT_REGULAR);

        return $new_sources;
    }
    
    function update_post_sources($post_id,$sources_input){
        
        $track = new WP_SoundSystem_Track( array('post_id'=>$post_id) );
        
        $sources_strict = $this->get_track_sources_db($post_id,false);
        $sources = array_merge((array)$sources_strict,(array)$sources_input);
        $sources = $this->sanitize_sources($sources_input);

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
                $track = new WP_SoundSystem_Track( array('post_id'=>$post_id) );
                echo count($track->sources);
            break;
        }
    }
    
    function get_track_sources_db($post_id,$filters=true){
        $sources = null;

        //stored in DB
        $sources = get_post_meta( $post_id, wpsstm_sources()->sources_metakey, true );

        if ($filters){
            $sources = apply_filters('wpsstm_get_track_sources_db',$sources,$post_id);
        }

        $sources = $this->sanitize_sources($sources);

        return $sources;
    }

    /*
    Those source will be suggested backend; user will need to confirm them.
    */

    function get_track_sources_remote($track,$args=null){

        $sources = array();

        foreach( (array)wpsstm_player()->providers as $provider ){
            if ( !$provider_sources = $provider->sources_lookup( $track,$args ) ) continue; //cannot play source
            $sources = array_merge($sources,(array)$provider_sources);
        }

        //allow plugins to filter this
        $sources = apply_filters('wpsstm_get_track_sources_remote',$sources,$track,$args);

        //cleanup
        $sources = $this->sanitize_sources($sources);

        return $sources;

    }
    
    function list_track_sources(WP_SoundSystem_Track $track,$database_only){
        if ( !$sources = wpsstm_player()->get_playable_sources($track,$database_only) ) return;
        $lis = array();
        foreach($sources as $key=>$source){
            $title = sprintf('<span class="wpsstm-source-title">%s</span>',$source['title']);

            //get provider
            $source_provider = $source_icon = null;
            $source_provider_slug = $source['provider'];
            foreach( (array)wpsstm_player()->providers as $provider ){
                if ($provider->slug != $source_provider_slug) continue;
                $source_icon = $provider->icon;
            }

            $provider_link = sprintf('<a class="wpsstm-trackinfo-provider-link" href="%s" target="_blank">%s</a>',$source['src'],$source_icon);

            $li_classes = null;
            if ($key==0) $li_classes= 'class="wpsstm-active-source"';
            $lis[] = sprintf('<li data-wpsstm-source-idx="%s" %s>%s %s</li>',$key,$li_classes,$title,$provider_link);
        }
        printf('<ul>%s</ul>',implode("",$lis));
    }
    
    
}


function wpsstm_sources() {
	return WP_SoundSytem_Core_Sources::instance();
}

wpsstm_sources();