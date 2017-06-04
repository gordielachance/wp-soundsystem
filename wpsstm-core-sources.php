<?php

class WP_SoundSytem_Core_Sources{

    var $providers = array();

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
        
        //ajax : sources manager : suggest
        add_action('wp_ajax_wpsstm_suggest_editable_sources', array($this,'ajax_suggest_editable_sources'));

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
        
        echo $this->get_metabox_sources_manager($post->ID);
        
        wp_nonce_field( 'wpsstm_sources_meta_box', 'wpsstm_sources_meta_box_nonce' );
    }
    
    function get_metabox_sources_manager( $post_id, $field_name = 'wpsstm_sources' ){
        
        $track = new WP_SoundSystem_Track( array('post_id'=>$post_id) );
        $sources = ($track->sources) ? $track->sources : array();
        $field_name_attr = null;

        $default = new WP_SoundSytem_Source();
        array_unshift($sources,$default); //add blank line
        $sources_inputs = $this->get_sources_inputs($sources, $field_name);

        $desc = __('Add sources to this track.  It could be a local audio file or a link to a music service.  Hover the provider icon to view the source title (when available)','wpsstm');

        $field_name_attr = sprintf('data-wpsstm-autosources-field-name="%s"',$field_name);
        
        $suggest_link = sprintf('<a class="wpsstm-suggest-sources-link" href="#" %s>%s</a>',$field_name_attr,__('Suggest sources','wpsstm'));

        return sprintf('<div class="wpsstm-manage-sources-wrapper" data-wpsstm-track-artist="%s" data-wpsstm-track-album="%s" data-wpsstm-track-title="%s"><p>%s</p><div class="wpsstm-sources-section-user wpsstm-sources-section">%s</div><div class="wpsstm-sources-section-auto wpsstm-sources-section">%s</div></div>',$track->artist,$track->album,$track->title,$desc,$sources_inputs,$suggest_link);

    }
    
    function get_sources_inputs($sources,$field_name){
        
        $rows = array();

        foreach ( $sources as $key=>$source_raw ){
            
            $source = new WP_SoundSytem_Source($source_raw);

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
            $icon_link = $source->get_provider_icon_link();
            
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
            
            $source_title_el = sprintf('<input type="hidden" class="wpsstm-source-title" %s/>',wpsstm_get_html_attr($source_title_attr_arr));
            $source_url_el = sprintf('<input type="text" class="wpsstm-source-url" %s %s %s/>',wpsstm_get_html_attr($source_url_attr_arr),$disabled_str,$readonly_str);
            
            $content_url = sprintf('<span class="wpsstm-source-icon">%s</span>',$icon_link);
            
            $content_url .= sprintf('<span class="wpsstm-source-fields">%s%s</span>',$source_title_el,$source_url_el);

            $icon_plus = '<i class="fa fa-plus-circle wpsstm-source-icon-add wpsstm-source-icon" aria-hidden="true"></i>';
            $icon_minus = '<i class="fa fa-minus-circle wpsstm-source-icon-delete wpsstm-source-icon" aria-hidden="true"></i>';
            
            $content_url .= sprintf('<span class="wpsstm-source-action">%s%s</span>',$icon_plus,$icon_minus);
            
            $attr_arr = array(
                'class'                     => implode(' ',$source_classes),
                'data-wpsstm-source-origin' => $source->origin
            );

            $rows[] = sprintf('<div %s>%s</div>',wpsstm_get_html_attr($attr_arr),$content_url);
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

        $track = new WP_SoundSystem_Track( array('post_id'=>$post_id) );
        $sources_raw = ( isset($_POST[ 'wpsstm_sources' ]) ) ? $_POST[ 'wpsstm_sources' ] : array();
        $sources = array();

        $track->update_track_sources($sources_raw);
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
        $sources = array();
        
        $sources = array();
        foreach((array)$track->sources as $source_raw){
            $source = new WP_SoundSytem_Source($source_raw);
            if (!$source->src) continue;
            $sources[] = $source;
        }

        foreach($sources as $key=>$source){
            
            $source_icon = $source_type = $source_title = null;
            
            //get provider icon

            $source_title = sprintf('<span class="wpsstm-source-title">%s</span>',$source->title);
            $icon_link = $source->get_provider_icon_link();
            
            $li_classes = array();
            
            $attr_arr = array(
                'class' =>                          implode(' ',$li_classes),
                'data-wpsstm-source-idx' =>         $key,
                'data-wpsstm-source-type'   =>      $source->type,
                'data-wpsstm-source-src'   =>       $source->src,
                'data-wpsstm-source-origin'   =>    $source->origin,
            );
            
            $li_classes = null;
            $lis[] = sprintf('<li %s>%s %s <i class="wpsstm-source-error fa fa-exclamation-triangle" aria-hidden="true"></i></li>',wpsstm_get_html_attr($attr_arr),$icon_link,$source_title);
            
        }
        if ( !empty($lis) ){
            return sprintf('<ul class="wpsstm-player-sources-list">%s</ul>',implode("",$lis));
        }
    }
    
    function sanitize_sources($sources){
        
        if ( empty($sources) ) return;
        
        $new_sources = array();

        //TO FIX correct source urls when possible
        foreach((array)$sources as $key=>$source){
            $source = wp_parse_args($source,WP_SoundSytem_Source::$defaults);
            if ( !$source['url'] ) continue;
            if ( !filter_var($source['url'], FILTER_VALIDATE_URL) ) continue;
            $new_sources[] = array_filter($source);
        }
        
        $new_sources = array_unique($new_sources, SORT_REGULAR);
        $new_sources = wpsstm_array_unique_by_subkey($new_sources,'url');

        return $new_sources;
    }
    
    function ajax_suggest_editable_sources($field_name){
        $result = array(
            'input'     => $_POST,
            'message'   => null,
            'new_html'  => null,
            'success'   => false
        );

        $args = $result['args'] = array(
            'title'     => ( isset($_POST['track']['title']) ) ? $_POST['track']['title'] : null,
            'artist'    => ( isset($_POST['track']['artist']) ) ? $_POST['track']['artist'] : null,
            'album'     => ( isset($_POST['track']['album']) ) ? $_POST['track']['album'] : null
        );
        
        $field_name = $result['field_name'] = ( isset($_POST['field_name']) ) ? $_POST['field_name'] : null;

        $track = new WP_SoundSystem_Track($args);
        $sources = $track->get_track_sources_auto(false);

        $track = $result['track'] = $track;

        $result['new_html'] = wpsstm_sources()->get_sources_inputs($sources,$field_name);
        $result['success'] = true;

        header('Content-type: application/json');
        wp_send_json( $result ); 

    }

}

function wpsstm_sources() {
	return WP_SoundSytem_Core_Sources::instance();
}

wpsstm_sources();

class WP_SoundSytem_Source {
    
    var $url; //input URL
    var $title;
    var $origin = null; //origin of the source (auto, scraper,user...)
    
    var $src; //URL used in the 'source' tag (which could be not the same)
    var $provider;
    var $type;

    static $defaults = array(
        'url'           => null,
        'title'         => null,
        'origin'        => null, 
    );
    
    function __construct($args = null){

        //set properties from args input

        $args = wp_parse_args((array)$args,self::$defaults);
        foreach($args as $key=>$value){
            if ( !array_key_exists($key,self::$defaults) ) continue;
            if ( !isset($args[$key]) ) continue; //value has not been set
            $this->$key = $value;
        }

        $this->url = trim($this->url);
        $this->populate_url();
        
        $this->title = trim($this->title);

    }
    
    function populate_url(){

        foreach( (array)wpsstm_player()->providers as $provider ){

            if ( !$src_url = $provider->format_source_src($this->url) ) continue;
            
            $this->provider =       $provider;
            $this->type =           $provider->get_source_type($src_url);
            $this->src =            $src_url;
                
            break;
            
        }

    }

    function get_provider_icon_link(){
        if ( !$this->provider ) return;
        
        $title = ($this->title) ? $this->title : null;
        return sprintf('<a class="wpsstm-source-provider-link" href="%s" target="_blank" title="%s">%s</a>',$this->url,$title,$this->provider->icon);
    }
}