<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

abstract class WPSSTM_Data_Engine{
    
    public $slug;
    public $name;
    public $id_metakey;
    public $data_metakey;
    public $time_metakey = '_wpsstm_musicdetails_time'; //to store the musicbrainz datas
    
    public $url_action = null;
    
    function __construct(){
        
        $this->url_action = wpsstm_get_array_value(array('wpsstm-data',$this->slug,'action'),$_GET);
        
        $this->id_metakey =     sprintf('_wpsstm_details_%s_id',$this->slug);
        $this->data_metakey =   sprintf('_wpsstm_details_%s_data',$this->slug);
        $this->time_metakey =   sprintf('_wpsstm_details_%s_time',$this->slug);

    }
    
    function setup_actions(){
        
        add_action( 'current_screen', array($this, 'handle_data_actions'));
        add_action( 'add_meta_boxes', array($this, 'datas_metabox_register'),50);
        add_action( 'save_post', array($this,'music_datas_metabox_save'), 7);

        /*DB*/
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_music_data_id') );
        
        /*
        frontend
        */
        add_filter('wpsstm_get_track_xspf',array($this,'xspf_track_identifier'), 10, 2);
        
        /*
        AJAX TOUFIX TOUCHECK
        */
        add_action('wp_ajax_wpsstm_search_artists', array($this,'ajax_search_artists')); //for autocomplete
        add_action('wp_ajax_nopriv_wpsstm_search_artists', array($this,'ajax_search_artists')); //for autocomplete

    }

    abstract protected function get_supported_post_types();
    abstract protected function query_music_entries( $artist,$album = null,$track = null );
    abstract protected function get_item_auto_id( $artist,$album = null,$track = null );
    abstract public function get_music_item_url();

    /**
    Get the correspondence between the item & the service datas
    **/

    abstract protected function get_mapped_object_by_post($post_id);

    public function pre_get_posts_music_data_id( $query ) {

        if ( !in_array( $query->get( 'post_type' ),$this->get_supported_post_types() ) ) return $query;
        if ( $search = $query->get( 'mbid' ) ){
            
            $query->set( 'meta_key', $this->id_metakey );
            $query->set( 'meta_query', array(
                array(
                     'key'     => $this->id_metakey,
                     'value'   => $search,
                     'compare' => '='
                )
            ));
        }

        return $query;
    }
    

      
    //TOUFIX TOUCHECK is this right ?
    function xspf_track_identifier($output,$track){
        if ( $url = $this->get_music_item_url($track->post_id) ){
            $output['identifier'] = $url;
        }
        return $output;            
    }
      
    public function get_music_item_link($post_id){
        if ( !$url = $this->get_music_item_url($post_id) ) return false;
        $music_id = $this->get_post_music_id($post_id);
        return sprintf('<a class="wpsstm-music-id" href="%s" target="_blank">%s</a>',$url,$music_id);
    }
            
    public function get_post_music_id($post_id = null){
        global $post;
        if (!$post_id) $post_id = $post->ID;
        return get_post_meta( $post_id, $this->id_metakey, true );
    }
    public function get_post_music_data($post_id = null){
        global $post;
        if (!$post_id) $post_id = $post->ID;
        return get_post_meta( $post_id, $this->data_metakey, true );
    }
    
    public function datas_metabox_register(){
        global $post;
        if (!$post) return;
        
        $post_types = $this->get_supported_post_types();
        $music_id = $this->get_post_music_id($post->ID);
        $music_data = $this->get_post_music_data($post->ID);

        if ($this->url_action == 'list_entries'){ //Entries Metabox
            add_meta_box( 
                sprintf('wpsstm-data-%s-entries',$this->slug), 
                $this->name,
                array($this,'metabox_music_entries_content'),
                $post_types,
                'after_title', 
                'high' 
            );
        }else{ //regular metabox
            add_meta_box( 
                sprintf('wpsstm-data-%s-id',$this->slug), 
                $this->name,
                array($this,'metabox_music_id_content'),
                $post_types,
                'after_title', 
                'high' 
            );
        }

    }
    
    public function metabox_music_id_content($post){

        $music_id = $this->get_post_music_id($post->ID);
        $music_data = $this->get_post_music_data($post->ID);
        $can_query = $this->can_query_music_entries($post->ID);

        $this->map_post_datas_notice();
        $this->mapped_item_header();

        ?>
        <div class="wpsstm-data-metabox">
            <div class="wpsstm-data-section-id">
                <?php 

                /*
                ID input
                */

                $input_el = $desc_el = null;

                $input_attr = array(
                    'name' =>           sprintf('wpsstm-data[%s][id]',$this->slug),
                    'class' =>          'wpsstm-data-id',
                    'value' =>          $this->get_post_music_id($post->ID),
                    'icon' =>           '<i class="fa fa-key" aria-hidden="true"></i>',
                    'label' =>          __('Item ID','wpsstm'),
                    'placeholder' =>    __("Enter ID here - or try the Lookup button",'wpsstm')
                );

                $input_el = wpsstm_get_backend_form_input($input_attr);

                echo $input_el . $desc_el;
        
                ?>
                <p>
                    <?php

                    /*
                    ID actions
                    */

                    //lookup bt
                    $lookup_url = add_query_arg(array('wpsstm-data'=>array($this->slug=>array('action'=>'id_lookup'))),get_edit_post_link());

                    $bt_lookup_attr = array(
                        'class'=>   'wpsstm-data-id-lookup-bt button',
                        'href'=>    $lookup_url,
                    );
                    if ( !$can_query || $music_id ){
                       $bt_lookup_attr['disabled'] = 'disabled'; 
                    }
                    $bt_lookup_attr = wpsstm_get_html_attr($bt_lookup_attr);
                    printf('<a %s>%s</a>',$bt_lookup_attr,__('Lookup','wpsstm'));  

                    //switch entries

                    $entries_url = add_query_arg(array('wpsstm-data'=>array($this->slug=>array('action'=>'list_entries'))),get_edit_post_link());

                    $bt_switch_attr = array(
                        'class'=>   'wpsstm-data-switch-bt button',
                        'href'=>    $entries_url,
                    );
                    if ( !$can_query || !$music_id || ($this->slug !== 'musicbrainz') ){//TOUFIX should be available for any service
                       $bt_switch_attr['disabled'] = 'disabled'; 
                    }
                    $bt_switch_attr = wpsstm_get_html_attr($bt_switch_attr);
                    printf('<a %s>%s</a>',$bt_switch_attr,__('Switch entry','wpsstm'));

                    //refresh

                    $entries_url = add_query_arg(array('wpsstm-data'=>array($this->slug=>array('action'=>'reload_data'))),get_edit_post_link());

                    $bt_reload_attr = array(
                        'class'=>   'wpsstm-data-reload-bt button',
                        'href'=>    $entries_url,
                    );
                    if ( !$can_query || !$music_id ){
                       $bt_reload_attr['disabled'] = 'disabled'; 
                    }
                    $bt_reload_attr = wpsstm_get_html_attr($bt_reload_attr);
                    printf('<a %s>%s</a>',$bt_reload_attr,__('Reload datas','wpsstm'));

                    ?>
                </p>
            </div>
            <div id="wpsstm-music-data">
                <?php 
                echo wpsstm_get_json_viewer($music_data);
                ?>
            </div>
        </div>
        <?php

        /*
        form
        */

        wp_nonce_field( sprintf('wpsstm_data_%s_id_meta_box',$this->slug), sprintf('wpsstm_data_%s_id_meta_box_nonce',$this->slug) );
        
        
    }
            
    public function metabox_music_entries_content($post){

        settings_errors('wpsstm-music-entries');
        
        $artist =   wpsstm_get_post_artist($post->ID);
        $album =    wpsstm_get_post_album($post->ID);
        $track =    wpsstm_get_post_track($post->ID);

        $entries = $this->query_music_entries($artist,$album,$track);
        
        if ( is_wp_error($entries) ){
            add_settings_error('wpsstm-music-entries', 'api_error', $entries->get_error_message(),'inline');
        }else{
            $entries_table = new $this->entries_table_classname();
            $entries_table->current_id = $this->get_post_music_id($post->ID);
            $entries_table->items = $entries;
            $entries_table->prepare_items();
            $entries_table->display();
        }
        
        /*
        form
        */

        wp_nonce_field( sprintf('wpsstm_data_%s_entries_meta_box',$this->slug), sprintf('wpsstm_data_%s_entries_meta_box_nonce',$this->slug) );
    }
    
    public function mapped_item_header(){
        global $post;
        
        if ( !$music_data = $this->get_post_music_data($post->ID) ) return;

        $header = null;
        $mapped_item = $this->get_mapped_object_by_post( $post->ID );
        if (!$mapped_item) return;
        
        $classname = get_class($mapped_item);

        /*
        Item header
        */

        switch($classname){
            case 'WPSSTM_Track':
                $header = $mapped_item->get_track_html();
            break;
        }
        if (!$header) return;
        
        printf('<p class="">%s</p>',$header);
    }
    
    public function map_post_datas_notice(){
        global $post;

        if ( !$music_data = $this->get_post_music_data($post->ID) ) return;

        $music_id = $this->get_post_music_id($post->ID);
        $mapped_item = $this->get_mapped_object_by_post( $post->ID );
        if (!$mapped_item) return;
        
        $classname = get_class($mapped_item);

        $post_type = get_post_type($post->ID);
        $mapped_item = $this->get_mapped_object_by_post( $post->ID );
        $mapped_item = $mapped_item->to_array();
        
        $checkboxes = array();
        
        //get item
        $item = null;
        switch($classname){
            case 'WPSSTM_Track':
                global $wpsstm_track;
                $item = $wpsstm_track->to_array(false);
            break;
        }
        
        if (!$item) return;
        
        $can_map = array_diff($mapped_item,$item);
        if ( empty($can_map) ) return;
        
        
        //get notice
        switch($classname){

            case 'WPSSTM_Track':
                //artist
                $checkboxes[] = $this->get_map_music_checkbox('artist',__('artist','wpsstm'),$item['artist'],$mapped_item['artist']);
                //album
                $checkboxes[] = $this->get_map_music_checkbox('album',__('album','wpsstm'),$item['album'],$mapped_item['album']);
                //title
                $checkboxes[] = $this->get_map_music_checkbox('title',__('title','wpsstm'),$item['title'],$mapped_item['title']);
                //duration
                $checkboxes[] = $this->get_map_music_checkbox('duration',__('duration','wpsstm'),$item['duration'],$mapped_item['duration']);


            break;
        }
        
        if ( empty($checkboxes) ) return;
        $checkboxes_str = implode ('',$checkboxes);
        
        $notice  = sprintf('<strong>%s</strong>',__('Merge data ?','wpsstm'));
        $notice .= '  '. $checkboxes_str;
        
        printf('<div class="notice notice-warning is-dismissible inline"><p>%s</p></div>',$notice);

        
    }
    
    private function get_map_music_checkbox($prop,$label,$before,$after){

        $no_update = ($before==$after);

        $input_attr = array(
            'type'=>        'checkbox',
            'name'=>        sprintf('wpsstm-data[map][%s]',$prop),
            'value'=>       $after,
            'disabled'=>    $no_update ? 'disabled' :null,
            'checked'=>     $no_update ? 'checked' :null,
        );
        
        $input_attr = array_filter($input_attr);
        $input_attr = wpsstm_get_html_attr($input_attr);

        return sprintf('<input %s /><span title="%s">%s</span> ',$input_attr,$after,$label);

    }
    
    /*
    Checks if the post contains enough information to do an API lookup
    */

    private function can_query_music_entries($post_id){
        $post_type = get_post_type($post_id);
        
        $music_id = $this->get_post_music_id($post_id);

        $artist =   wpsstm_get_post_artist($post_id);
        $track =    wpsstm_get_post_track($post_id);
        $album =    wpsstm_get_post_album($post_id);
        
        
        
        $can = false;

        switch($post_type){
            case wpsstm()->post_type_artist:
                $can = ($music_id || $artist);
            break;
            case wpsstm()->post_type_track:
                $can = ($music_id || ($artist && $track) );
            break;
            case wpsstm()->post_type_album:
                $can = ($music_id || ($artist && $album) );
            break;
        }
        
        
        
        return $can;

    }
      
    /**
    Try to guess the music ID of a post, based on its artist / album / title.
    **/

    public function auto_music_id( $post_id ){
        
        //TO FIX limit musicbrainz query to 1 entry max ?

        $music_id = null;

        //check post type
        $post_type = get_post_type($post_id);
        if ( !in_array($post_type,$this->get_supported_post_types()) ) return false;

        $artist =   wpsstm_get_post_artist($post_id);
        $album =    wpsstm_get_post_album($post_id);
        $track =    wpsstm_get_post_track($post_id);

        $music_id = $this->get_item_auto_id($artist,$album,$track);

        if ( is_wp_error($music_id) ) $music_id = null;

        $success = $this->save_music_id($post_id,$music_id);
        if ( is_wp_error($success) ) return $success;

        return $music_id;
        
    }
    
    public function handle_data_actions($screen){
        $post_id = wpsstm_get_array_value('post',$_GET);
        if (!$post_id) return;

        switch($this->url_action){
            case 'id_lookup':
                $music_id = $this->auto_music_id( $post_id );
            break;
            case 'reload_data':
                $this->cache_music_data($post_id);
            break;
        }
        

    }
    
    public function music_datas_metabox_save( $post_id ){

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );

        $is_metabox = isset($_POST['wpsstm-data'][$this->slug]['id']);
        
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( sprintf('wpsstm_data_%s_id_meta_box_nonce',$this->slug), sprintf('wpsstm_data_%s_id_meta_box',$this->slug) ) );
        //TOUFIX URGENT if ( !$is_valid_nonce ) return;

        //check post type
        $post_type = get_post_type($post_id);
        if ( !in_array($post_type,$this->get_supported_post_types()) ) return;
        
        //music ID
        $music_id = wpsstm_get_array_value(array('wpsstm-data',$this->slug,'id'),$_POST);
        $this->save_music_id($post_id,$music_id);
        
        //map
        if ( $mapdatas = wpsstm_get_array_value(array('wpsstm-data','map'),$_POST) ){
            $this->map_datas($post_id,$mapdatas);
        }

    }
       
    /*
    Handle music date for a post
    */
    
    private function save_music_id($post_id,$id = null){

        $api_url = null;
        $id = trim($id);

        if ( !$post_type = get_post_type($post_id) ) return false;

        $old_id = $this->get_post_music_id($post_id);
        $is_id_update = ($old_id != $id);
        $cache_data = ($id && $is_id_update);
        
        /*ID*/
        
        if ($is_id_update){
            
            WP_SoundSystem::debug_log( "delete music datas..." );
            delete_post_meta( $post_id, $this->data_metakey );
            delete_post_meta( $post_id, $this->time_metakey );
            
            if (!$id){

                delete_post_meta( $post_id, $this->id_metakey );
            }else{
                
                WP_SoundSystem::debug_log( "update music ID..." );
                
                update_post_meta( $post_id, $this->id_metakey, $id );
            }
        }
        
        /*data*/
        if ($cache_data){
            $this->cache_music_data($post_id);
        }

        return $id;
        
    }
    
    private function map_datas($post_id,$datas){
        
        $post_type = get_post_type($post_id);
        switch($post_type){
            
            case wpsstm()->post_type_artist:
            break;
                
            case wpsstm()->post_type_track:

                if ( $artist = wpsstm_get_array_value('artist',$datas) ){
                    WPSSTM_Core_Tracks::save_track_artist($post_id,$artist);
                }
                if ( $title = wpsstm_get_array_value('title',$datas) ){
                    WPSSTM_Core_Tracks::save_track_title($post_id,$title);
                }
                
                if ( $album = wpsstm_get_array_value('album',$datas) ){
                    WPSSTM_Core_Tracks::save_track_album($post_id,$album);
                }
                
                if ( $duration = wpsstm_get_array_value('duration',$datas) ){
                    WPSSTM_Core_Tracks::save_track_duration($post_id,$duration);
                }
                
                if ( $image_url = wpsstm_get_array_value('image_url',$datas) ){
                    WPSSTM_Core_Tracks::save_image_url($post_id,$image_url);
                }

            break;
                
            case wpsstm()->post_type_album:
            break;
        }
    }
    
    private function cache_music_data($post_id){
        $data = $this->get_music_data_for_post($post_id);

        if ( !is_wp_error($data) ){

            WP_SoundSystem::debug_log( "cache music datas..." );
            
            if ( $success = update_post_meta( $post_id, $this->data_metakey, $data ) ){

                //save timestamp
                $now = current_time('timestamp');
                update_post_meta( $post_id, $this->time_metakey, $now );

            }
        }
    }
    
    /*
    Use MusicBrainz API to search artists
    WARNING for partial search, you'll need a wildcard * !
    */
    
    public static function ajax_search_artists(){
        
        $ajax_data = wp_unslash($_POST);
        
        $result = array(
            'input' =>              $ajax_data,
            'message' =>            null,
            'success' =>            false
        );
        
        $artist = $result['search'] = wpsstm_get_array_value('search',$ajax_data);
        
        //urlencode
        $artist = urlencode($artist);
        
        if ($artist){
            
            $entries = $this->query_music_entries($artist);
            if ( is_wp_error($entries) ){
                $result['message'] = $api_results->get_error_message();
            }else{
                $result['data'] = $api_results;
                $result['success'] = true;
            }

        }
        
        header('Content-type: application/json');
        wp_send_json( $result ); 
    }
}
            
class WPSSTM_Music_Entries extends WP_List_Table {
    
    var $current_id = null;
    var $engine_slug = null; //to override in child class
    
	/**
	 * Handles the checkbox column output.
	 *
	 * @since 4.3.0
	 * @access public
	 *
	 * @param object $item The current link object.
	 */
	public function column_cb( $item ) {
        global $post;
        
        $checked = ($this->current_id == $item['id']);

        $input_attr = array(
            'type'=>        'radio',
            'name'=>        sprintf('wpsstm-data[%s][id]',$this->engine_slug),
            'value'=>       esc_attr( $item['id'] ),
            'checked'=>     $checked ? 'checked' :null,
        );
        
        $input_attr = array_filter($input_attr);
        $input_attr = wpsstm_get_html_attr($input_attr);


        printf('<input %s />',$input_attr);

	}
}