<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

abstract class WPSSTM_Music_Data{
    
    public $slug;
    public $name;
    public $id_metakey;
    public $data_metakey;
    public $time_metakey = '_wpsstm_musicdetails_time'; //to store the musicbrainz datas
    
    function __construct(){
        
        $this->id_metakey =     sprintf('_wpsstm_details_%s_id',$this->slug);
        $this->data_metakey =   sprintf('_wpsstm_details_%s_data',$this->slug);
        $this->time_metakey =   sprintf('_wpsstm_details_%s_time',$this->slug);

    }
    
    function setup_actions(){
        /*
        backend
        */
        add_action( 'add_meta_boxes', array($this, 'music_datas_metabox_register'),50);
        add_action( 'save_post', array($this,'music_datas_metabox_save'), 7);
        
        //items list
        foreach( (array)$this->get_supported_post_types() as $post_type ){
            add_filter( sprintf('manage_%s_posts_columns',$post_type), array($this,'music_datas_columns_register'), 10, 2 );
            add_action( sprintf('manage_%s_posts_custom_column',$post_type), array($this,'music_datas_columns_content'), 10, 2 );
        }

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
    abstract protected function get_fillable_details_map();
    
    public static function is_entries_switch(){
        return ( isset($_GET['list-music-items'])) ? true : false;
    }

    public function music_datas_metabox_register(){
        global $post;
        if (!$post) return;
        
        $post_types = $this->get_supported_post_types();
        $music_id = $this->get_post_music_id($post->ID);
        $music_data = $this->get_post_music_data($post->ID);

        /*
        Entries Metabox
        */
        $id_callback = array($this,'metabox_music_id_content');
        $is_entry_switch = self::is_entries_switch();
        if ( $is_entry_switch ){  
            $id_callback = array($this,'metabox_music_entries_content');
        }

        /*
        ID Metabox
        */
        add_meta_box( 
            'wpsstm-music-id', 
            __('Music ID','wpsstm'),
            $id_callback,
            $post_types,
            'after_title', 
            'high' 
        );
        
        /*
        Datas Metabox
        */
        if ( $music_data && !$is_entry_switch ){
            add_meta_box( 
                'wpsstm-music-data', 
                sprintf(__('Music Data (%s)','wpsstm'),$this->name),
                array($this,'metabox_music_data_content'),
                $post_types,
                'after_title', 
                'high' 
            );
        }

    }
    
    public static function pre_get_posts_music_data_id( $query ) {

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
    
    public function music_datas_metabox_save( $post_id ){

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        
        $is_metabox = isset($_POST['wpsstm_music_id_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;

        //check post type
        $post_type = get_post_type($post_id);
        if ( !in_array($post_type,$this->get_supported_post_types()) ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_music_id_meta_box_nonce'], 'wpsstm_music_id_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_music_id_meta_box_nonce']);

        //clicked a musicbrainz action button
        $action = null;
        if ( isset($_POST['wpsstm-mb-id-lookup']) ){
            $action = 'autoguess-id';
        }elseif ( isset($_POST['wpsstm-mb-reload']) ){
            $action = 'reload';
        }elseif ( isset($_POST['wpsstm-mb-fill']) ){
            $action = 'fill';
        }

        /*
        Handle ID
        */
        $old_id = $this->get_post_music_id($post_id);
        $id = ( isset($_POST['wpsstm_music_id']) ) ? trim($_POST['wpsstm_music_id']) : null;
        $is_id_update = ($old_id != $id);
        
        if (!$id){
            delete_post_meta( $post_id, $this->id_metakey );
            delete_post_meta( $post_id, $this->data_metakey ); //delete music datas
            delete_post_meta( $post_id, $this->time_metakey ); //delete music datas timestamp
        }else{
            update_post_meta( $post_id, $this->id_metakey, $id );
            if ($is_id_update){
                $this->save_music_details($post_id);
            }
        }
        
        /*
        Handle action
        */

        switch ($action){
            case 'autoguess-id':
                $id = $this->auto_music_id( $post_id );
                if ( is_wp_error($id) ) break;
            break;
            case 'reload':
                $this->save_music_details($post_id);
            break;
            case 'fill':
                $field_slugs = isset($_POST['wpsstm-mb-fill-fields']) ? $_POST['wpsstm-mb-fill-fields'] : array();

                if ( !empty($field_slugs) ){
                    $this->fill_post_with_details($post_id,$field_slugs,true);
                }
            break;
        }
    }
    
    public static function music_datas_columns_register($defaults) {
        $defaults['music-id'] = __('ID','wpsstm');
        return $defaults;
    }
    
    public static function music_datas_columns_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
            case 'music-id':
                
                if (!$link = $this->get_music_item_link($post_id) ){
                    $link = '—';
                }
                
                echo $link;
                
            break;
        }
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
            
    public function metabox_music_entries_content($post){

        settings_errors('wpsstm-music-entries');
        
        $artist = wpsstm_get_post_artist($post->ID);
        $album = wpsstm_get_post_album($post->ID);
        $track = wpsstm_get_post_track($post->ID);

        $entries = $this->query_music_entries($artist,$album,$track);
        
        if ( is_wp_error($entries) ){
            add_settings_error('wpsstm-music-entries', 'api_error', $entries->get_error_message(),'inline');
        }else{
            $entries_table = new $this->entries_table_classname();
            $entries_table->items = $entries;
            $entries_table->prepare_items();
            $entries_table->display();
        }

        wp_nonce_field( 'wpsstm_music_id_meta_box', 'wpsstm_music_id_meta_box_nonce' );
    }
            
    public function metabox_music_id_content($post){

        $music_id = $this->get_post_music_id($post->ID);
        $music_data = $this->get_post_music_data($post->ID);
        $can_query = $this->can_query_music_entries($post->ID);

        ?>
        <p>
            <?php echo $this->get_music_id_input($post->ID);?>
        </p>
        <table class="form-table">
            <tbody>
                <?php 
                if ( $can_query ){
                    ?>
                    <tr valign="top">
                        <td>
                            <?php
                    
                            if (!$music_id){
                                submit_button( __('Search','wpsstm'), null, 'wpsstm-mb-id-lookup');
                            }
                            if ($music_id &&  ($this->slug === 'musicbrainz')) { //TOUFIX should be available for any service
                                $entries_url = get_edit_post_link();
                                $entries_url = add_query_arg(array('list-music-items'=>true),$entries_url);
                                printf('<p><a class="button" href="%s">%s</a></p>',$entries_url,__('Switch entry','wpsstm'));
                            }
                    
                            if ($music_data){
                                submit_button( __('Refresh data','wpsstm'), null, 'wpsstm-mb-reload');
                            }
                    
                            ?>

                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php

        /*
        form
        */

        wp_nonce_field( 'wpsstm_music_id_meta_box', 'wpsstm_music_id_meta_box_nonce' );
        
        
    }
    
    public function metabox_music_data_content($post){
        $music_data = $this->get_post_music_data($post->ID);
        ?>
        <table class="form-table">
            <tbody>
                <?php 
                if ($music_data) {
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Data','wpsstm');?></label>
                        </th>
                        <td>
                            <p>
                                <?php
                                /* 
                                Entry data 
                                */
                                $list = wpsstm_get_list_from_array($music_data);
                                printf('<div id="wpsstm-music-data">%s</div>',$list);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
        <?php
        /*
        form
        */
        wp_nonce_field( 'wpsstm_mbdata_meta_box', 'wpsstm_mbdata_meta_box_nonce' );
        
        
    }
            
    /*
    Checks if the post contains enough information to do an API lookup
    */

    private function can_query_music_entries($post_id){
        $post_type = get_post_type($post_id);
        
        $music_id = $this->get_post_music_id($post_id);
        $artist = wpsstm_get_post_artist($post_id);
        $track = wpsstm_get_post_track($post_id);
        $album = wpsstm_get_post_album($post_id);
        
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
            
    function get_music_id_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;
        
        $input_el = $desc_el = null;
        
        $input_attr = array(
            'name' =>           'wpsstm_music_id',
            'value' =>          $this->get_post_music_id($post_id),
            'icon' =>           '<i class="fa fa-key" aria-hidden="true"></i>',
            'label' =>          $this->name,
            'placeholder' =>    __("Enter ID here - or try the Search button",'wpsstm')
        );
        
        $input_el = wpsstm_get_backend_form_input($input_attr);

        return $input_el . $desc_el;
    }
            
    /**
    Try to guess the music ID of a post, based on its artist / album / title.
    **/

    public function auto_music_id( $post_id ){
        
        //TO FIX limit musicbrainz query to 1 entry max ?

        $id = null;

        //check post type
        $post_type = get_post_type($post_id);
        if ( !in_array($post_type,$this->get_supported_post_types()) ) return false;
        
        $artist = wpsstm_get_post_artist($post_id);
        $album = wpsstm_get_post_album($post_id);
        $track = wpsstm_get_post_track($post_id);
        
        $id = $this->get_item_auto_id($artist,$album,$track);

        if ( is_wp_error($id) ) $id = null;

        if ( $success = update_post_meta( $post_id, $this->id_metakey, $id ) ){
            wpsstm()->debug_log( json_encode(array('post_id'=>$post_id,'id'=>$id,'engine'=>$this->slug)),"Updated Music ID" ); 
            $this->save_music_details($post_id);
        }

        return $id;
        
    }
            
    /*
    Reload music datas for an item ID
    */
    
    protected function save_music_details($post_id){
        
        $api_url = null;

        if ( !$post_type = get_post_type($post_id) ) return false;

        //delete existing
        if ( delete_post_meta( $post_id, $this->data_metakey ) ){
            delete_post_meta( $post_id, $this->time_metakey ); //delete timestamp
            wpsstm()->debug_log('deleted music details datas','reload music details');
        }

        $data = $this->get_details_for_post($post_id);

        if ( is_wp_error($data) ) $data = null;

        if ( $success = update_post_meta( $post_id, $this->data_metakey, $data ) ){
            
            //fill empty fields with mb datas
            $this->fill_post_with_details($post_id);
            
            //save timestamp
            $now = current_time('timestamp');
            update_post_meta( $post_id, $this->time_metakey, $now );
   
        }

        return $success;
        
    }
            
    //TO FIX TO CHECK
    //Do not override basic informations ? (eg. for a track, artist & title)
    private function fill_post_with_details($post_id=null,$field_slugs=null,$override=false){
        $data = $this->get_post_music_data($post_id);
        if ( !$data ) return;
        $fields = $this->get_fillable_details_map($post_id);

        $fields_success = array();
        
        //which fields to fill ?
        if ($field_slugs === null){
            $field_slugs = array_keys($fields);
        }

        foreach($field_slugs as $slug){
            
            $field = ( isset($fields[$slug]) ) ? $fields[$slug] : null;
            if (!$field) continue;

            $meta_value =   get_post_meta($post_id,$field['metaname'],true);
            $mb_value =     wpsstm_get_array_value($field['mbpath'], $data);

            if ( !$meta_value || $override ){
                if ($mb_value){
                    $fields_success[$slug] = update_post_meta( $post_id,$field['metaname'],$mb_value);
                }else{
                    $fields_success[$slug] = delete_post_meta( $post_id,$field['metaname']);
                }
                
            }
            
        }

        //log
        $fields_success['post_id'] = $post_id;
        wpsstm()->debug_log( json_encode($fields_success),"filled post with music datas" ); 

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
        global $music_entry_index;
        
        if ( !WPSSTM_Music_Data::is_entries_switch() ) return;
        
        $entry_idx = ( isset($music_entry_index) ) ? $music_entry_index : 0;

        if ( $music_id = wpsstm()->details_engine->get_post_music_id($post->ID) ){
            $checked_str = checked($music_id,$item['id'],false);
        }else{
            $checked_str = checked($entry_idx,0,false);
        }

        printf('<input type="radio" name="wpsstm_music_id" id="cb-select-%s" value="%s" %s />',$entry_idx,esc_attr( $item['id'] ),$checked_str);

        $entry_idx++;
	}
}