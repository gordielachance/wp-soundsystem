<?php

class WP_SoundSytem_Source {
    
    var $url; //input URL
    var $title;
    var $auto = false; //has this source been populated automatically ?
    
    var $src; //URL used in the 'source' tag (which could be not the same)
    var $provider;
    var $type;

    static $defaults = array(
        'url'           => null,
        'title'         => null,
        'auto'          => false,
    );
    
    function __construct($track,$args = array()){
        
        //keep only values for keys contained in $defaults
        if ($args){
            $args = wp_parse_args((array)$args,self::$defaults);
            foreach($args as $key=>$value){
                if ( !array_key_exists($key,self::$defaults) ) continue;
                $this->$key = $value;
            }
        }

        $this->url = trim($this->url);
        $this->populate_url();
        
        $this->title = trim($this->title);

    }
    
    function populate_url(){

        foreach( (array)wpsstm_player()->providers as $provider ){
            
            if ( !$type = $provider->get_source_type($this->url) ) continue;
            
            $this->provider =   $provider->slug;
            $this->type =       $type;
            $this->src =        $provider->format_source_url($this->url);
                
            break;
            
        }
        
    }
    
    function get_provider(){
        foreach( (array)wpsstm_player()->providers as $provider ){
            if ($provider->slug == $this->provider){
                return $provider;
            }
        }
    }
    
    //format it as an array to store it in the DB
    function format_source_for_db(){
        
        if (!$this->url) return false;
        
        $store = array(
            'title' => $this->title,
            'url'   => $this->url,
            'auto'  => $this->auto
        );
        $store = array_filter($store);
        return $store;
    }

}

class WP_SoundSytem_Core_Sources{

    var $providers = array();
    
    var $source_blank = array(
        'url'           => null,
        'title'         => null,
        'auto'          => false,
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
        $desc = __('Add sources to this track.  It could be a local audio file or a link to a music service.','wpsstm');
        printf('<p>%s</p>',$desc);
        
        echo $this->get_sources_field_editable($post->ID,'wpsstm_sources');
        
        wp_nonce_field( 'wpsstm_sources_meta_box', 'wpsstm_sources_meta_box_nonce' );
    }
    
    function get_sources_field_editable( $post_id, $field_name ){
        
        $track = new WP_SoundSystem_Track( array('post_id'=>$post_id) );
        $track->sources = $track->get_track_sources(false); //not only DB sources

        $blank = new WP_SoundSytem_Source($track); //add blank row
        array_unshift($sources,$blank);

        $rows = array();

        foreach ( (array)$track->sources as $key=>$source ){
            
            $disabled = false;

            $source_classes = array('wpsstm-source');
            if ($key==0){
                $source_classes[] = 'wpsstm-source-blank';
            }
            
            //auto
            if ( $source->auto ){
                $source_classes[] = 'wpsstm-source-auto';
                $disabled = true;
            }
            
            $disabled_str = disabled( $disabled, true, false );
   
            $placeholder_title = __("Enter a title for this source",'wpsstm');
            $input_title = sprintf('<input type="text" name="%s[%s][title]"  value="%s" placeholder="%s" %s/>',$field_name,$key,$source->title,$placeholder_title,$disabled_str);
            
            $placeholder_url = __("Enter an URL for this source",'wpsstm');
            $input_url = sprintf('<input type="text" name="%s[%s][url]"  value="%s" placeholder="%s" %s/>',$field_name,$key,$source->url,$placeholder_url,$disabled_str);
            
            $content_url = sprintf('<span class="wpsstm-source-inputs">%s %s</span>',$input_title,$input_url);

            $icon_plus = '<i class="fa fa-plus-circle wpsstm-source-icon-add wpsstm-source-icon" aria-hidden="true"></i>';
            $icon_minus = '<i class="fa fa-minus-circle wpsstm-source-icon-delete wpsstm-source-icon" aria-hidden="true"></i>';
            
            $content_url .= sprintf('<span class="wpsstm-source-action">%s%s</span>',$icon_plus,$icon_minus);

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

        $track = new WP_SoundSystem_Track( array('post_id'=>$post_id) );
        $sources_raw = ( isset($_POST[ 'wpsstm_sources' ]) ) ? $_POST[ 'wpsstm_sources' ] : array();
        $sources = array();
        
        foreach((array)$sources_raw as $source_raw){
            $source = new WP_SoundSytem_Source($track,$source_raw);
            $sources[] = $source;
        }
        
        $track->update_track_sources($sources);
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
    
    function get_track_sources_list(WP_SoundSystem_Track $track){

        $lis = array();
        
        foreach((array)$track->sources as $key=>$source){
            
            $provider = $source_icon = $source_type = $source_title = null;
            
            //get provider
            $provider = $source->get_provider();

            $source_title = sprintf('<span class="wpsstm-source-title">%s</span>',$source->title);
            $provider_link = sprintf('<a class="wpsstm-trackinfo-provider-link" href="%s" target="_blank">%s</a>',$source->src,$provider->icon);
            
            $li_classes = array();
            if ($key==0) $li_classes[]= 'wpsstm-active-source';
            
            $attr_arr = array(
                'class' =>                      implode(' ',$li_classes),
                'data-wpsstm-source-idx' =>     $key,
                'data-wpsstm-source-type'   =>  $source->type,
            );
            
            $li_classes = null;
            $lis[] = sprintf('<li %s>%s %s</li>',wpsstm_get_html_attr($attr_arr),$provider_link,$source_title);
            
        }
        if ( !empty($lis) ){
            return sprintf('<ul class="wpsstm-player-sources-list">%s</ul>',implode("",$lis));
        }
    }
    
    function sanitize_sources($sources){
        
        if ( empty($sources) ) return;
        
        $new_sources = array();

        foreach((array)$sources as $key=>$source){
            if ( !$source->url ) continue;
            $new_sources[] = $source;
        }
        
        $new_sources = array_unique($new_sources, SORT_REGULAR);

        //TO FIX array unique based on source URL

        return $new_sources;
    }
    
    function format_sources_for_db($sources){
        $new_sources = array();
        $sources = $this->sanitize_sources($sources);
        foreach ((array)$sources as $source){
            $new_sources[] = $source->format_source_for_db();
        }
        return $new_sources;
    }

}

function wpsstm_sources() {
	return WP_SoundSytem_Core_Sources::instance();
}

wpsstm_sources();