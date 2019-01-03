<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPSSTM_MusicBrainz {
    
    static $mb_api_sleep = 1;
    static $mbz_options_meta_name = 'wpsstm_musicbrainz_options';
    static $mbid_metakey = '_wpsstm_mbid'; //to store the musicbrainz ID
    static $no_auto_mbid_metakey = '_wpsstm_no_auto_mbid';
    static $mbdata_metakey = '_wpsstm_mbdata'; //to store the musicbrainz datas
    static $mbdata_time_metakey = '_wpsstm_mbdata_time'; //to store the musicbrainz datas
    static $mb_data_by_url_transient_prefix = 'wpsstm_mb_by_url_'; //to cache the musicbrainz API results
    static $qvar_mbid = 'mbid';
    
    public $options = array();

    function __construct(){
        
        $options_default = array(
            'enabled' =>    true,
            'auto_mbid' =>  true,
        );
        
        $this->options = wp_parse_args(get_option( self::$mbz_options_meta_name),$options_default);
        
        add_filter('wpsstm_remote_presets',array($this,'register_musicbrainz_preset'));
        
        add_filter('wpsstm_wizard_service_links',array($this,'register_musicbrainz_service_links'), 8);
        
        add_action( 'add_meta_boxes', array($this, 'metaboxes_mb_register'),50);
        add_action( 'save_post', array($this,'metabox_mbid_save'), 7);
        add_action( 'save_post', array($this,'auto_mbid_on_post_save'), 8);
        add_action( 'save_post', array($this,'metabox_mbdata_save'), 9);

        add_filter( 'pre_get_posts', array($this,'pre_get_posts_mbid') );
        
        //backend
        add_action( 'admin_init', array( $this, 'mbz_settings_init' ) );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_artist), array($this,'mb_columns_register'), 10, 2 );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_track), array($this,'mb_columns_register'), 10, 2 );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array($this,'mb_columns_register'), 10, 2 );
        
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_artist), array($this,'mb_columns_content'), 10, 2 );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_track), array($this,'mb_columns_content'), 10, 2 );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_album), array($this,'mb_columns_content'), 10, 2 );
        

    }
    
    function get_options($keys = null){
        return wpsstm_get_array_value($keys,$this->options);
    }
    
    function register_musicbrainz_preset($presets){
        $presets[] = new WPSSTM_Musicbrainz_Release_ID_Preset();
        return $presets;
    }
    
    static function is_entries_switch(){
        return ( isset($_GET['mb-list-entries'])) ? true : false;
    }
    
    function mbz_settings_init(){
        register_setting(
            'wpsstm_option_group', // Option group
            self::$mbz_options_meta_name, // Option name
            array( $this, 'mbz_settings_sanitize' ) // Sanitize
         );
        
        add_settings_section(
            'mbz_service', // ID
            'MusicBrainz', // Title
            array( $this, 'mbz_settings_desc' ), // Callback
            'wpsstm-settings-page' // Page
        );
        
        add_settings_field(
            'enabled', 
            __('Enabled','wpsstm'), 
            array( $this, 'mbz_enabled_callback' ), 
            'wpsstm-settings-page', // Page
            'mbz_service'//section
        );
        
        add_settings_field(
            'auto-mbid', 
            __('Auto lookup','wpsstm'), 
            array( $this, 'mbz_auto_id_callback' ), 
            'wpsstm-settings-page', // Page
            'mbz_service'//section
        );
        
    }
    
    function mbz_settings_sanitize($input){
        if ( WPSSTM_Settings::is_settings_reset() ) return;
        
        //MusicBrainz
        $new_input['enabled'] = isset($input['enabled']);
        $new_input['auto_mbid'] = isset($input['auto_mbid']);
        
        return $new_input;
    }
    
    function mbz_settings_desc(){
        $mb_link = '<a href="https://musicbrainz.org/" target="_blank">MusicBrainz</a>';
        printf(__('%s is an open data music database.  By enabling it, the plugin will fetch various informations about the tracks, artists and albums you post.','wpsstm'),$mb_link);
    }

    function mbz_enabled_callback(){
        $option = $this->get_options('enabled');
        
        $el = sprintf(
            '<input type="checkbox" name="%s[enabled]" value="on" %s /> %s',
            self::$mbz_options_meta_name,
            checked( $option,true, false ),
            __("Enable MusicBrainz.","wpsstm")
        );
        printf('<p>%s</p>',$el);
    }

    function mbz_auto_id_callback(){
        $option = $this->get_options('auto_mbid');
        
        $el = sprintf(
            '<input type="checkbox" name="%s[auto_mbid]" value="on" %s /> %s',
            self::$mbz_options_meta_name,
            checked( $option,true, false ),
            __("Auto-lookup MusicBrainz IDs.","wpsstm")
        );
        printf('<p>%s</p>',$el);
    }

    public static function pre_get_posts_mbid( $query ) {

        if ( $search = $query->get( self::$qvar_mbid ) ){
            
            $query->set( 'meta_key', self::$mbid_metakey );
            $query->set( 'meta_query', array(
                array(
                     'key'     => self::$mbid_metakey,
                     'value'   => $search,
                     'compare' => '='
                )
            ));
        }

        return $query;
    }

    public static function mb_columns_register($defaults) {
        $defaults['mbid'] = __('MBID','wpsstm');
        return $defaults;
    }
    
    public static function mb_columns_content($column,$post_id){
        global $post;
        
        switch ( $column ) {
            case 'mbid':
                $mbid = '—';
                if (!$mbid = wpsstm_get_post_mb_link_for_post($post_id) ){
                    $mbid = '—';
                }
                
                echo $mbid;
                
            break;
        }
    }
    
    public function metaboxes_mb_register(){
        global $post;
        if (!$post) return;

        $entries_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );

        //MBID Metabox
        $mbid_callback = array($this,'metabox_mbid_content');
        if ( self::is_entries_switch() ){  
            $mbid_callback = array($this,'metabox_mb_entries_content');
        }

        add_meta_box( 
            'wpsstm-mbid', 
            __('MusicBrainz ID','wpsstm'),
            $mbid_callback,
            $entries_post_types,
            'after_title', 
            'high' 
        );
        
        //MB datas Metabox
        if ( $mbid = wpsstm_get_post_mbid($post->ID) ){
            add_meta_box( 
                'wpsstm-mbdata', 
                __('MusicBrainz Data','wpsstm'),
                array($this,'metabox_mbdata_content'),
                $entries_post_types,
                'after_title', 
                'high' 
            );
        }

    }
    
    function get_edit_mbid_input($post_id = null){
        global $post;
        if (!$post) $post_id = $post->ID;
        
        $input_el = $desc_el = null;
        
        $input_attr = array(
            'id' => 'wpsstm-mbid',
            'name' => 'wpsstm_mbid',
            'value' => wpsstm_get_post_mbid($post_id),
            'icon' => '<i class="fa fa-key" aria-hidden="true"></i>',
            'label' => __("MusicBrainz ID",'wpsstm'),
            'placeholder' => __("Enter MusicBrainz ID here",'wpsstm')
        );
        
        $input_el = wpsstm_get_backend_form_input($input_attr);
        
        if ( $this->get_options('auto_mbid') ){
            $is_ignore = ( get_post_meta( $post_id, self::$no_auto_mbid_metakey, true ) );
            $input_auto_id_el = sprintf('<input type="checkbox" value="on" name="wpsstm-ignore-auto-mbid" %s/>',checked($is_ignore,true,false));
            $desc_el .= $input_auto_id_el . ' ' .__("Do not auto-identify",'wpsstm');
        }
        
        return $input_el . $desc_el;
    }
    
    /*
    Checks if the post contains enough information to do an API lookup
    */

    static function can_mb_search_entries($post_id){
        $post_type = get_post_type($post_id);
        
        $mbid = wpsstm_get_post_mbid($post_id);
        $artist = wpsstm_get_post_artist($post_id);
        $track = wpsstm_get_post_track($post_id);
        $album = wpsstm_get_post_album($post_id);
        
        $can = false;

        switch($post_type){
            case wpsstm()->post_type_artist:
                $can = ($mbid || $artist);
            break;
            case wpsstm()->post_type_track:
                $can = ($mbid || ($artist && $track) );
            break;
            case wpsstm()->post_type_album:
                $can = ($mbid || ($artist && $album) );
            break;
        }
        
        return $can;

    }
    
    public function metabox_mbid_content($post){

        $mbid = wpsstm_get_post_mbid($post->ID);
        $can_mb_search_entries = self::can_mb_search_entries($post->ID);

        ?>
        <p>
            <?php echo $this->get_edit_mbid_input($post->ID);?>
        </p>
        <table class="form-table">
            <tbody>
                <?php 
                if ( $can_mb_search_entries ){
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('MusicBrainz Lookup','wpsstm');?></label>
                        </th>
                        <td>
                            <?php
                            submit_button( __('MusicBrainz Lookup','wpsstm'), null, 'wpsstm-mb-id-lookup');
                            _e('Search ID from track title, artist & album.','wpsstm');
                            ?>

                        </td>
                    </tr>
                    <?php
                }
                ?>
                <?php 
                if ($can_mb_search_entries && $mbid) {
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Switch entry','wpsstm');?></label>
                        </th>
                        <td>
                            <?php
                            $entries_url = get_edit_post_link();
                            $entries_url = add_query_arg(array('mb-list-entries'=>true),$entries_url);
                            printf('<a class="button" href="%s">%s</a>',$entries_url,__('Switch entry','wpsstm'));
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

        wp_nonce_field( 'wpsstm_mbid_meta_box', 'wpsstm_mbid_meta_box_nonce' );
        
        
    }
    
    public function metabox_mbdata_content($post){

        $mbid = wpsstm_get_post_mbid($post->ID);
        $mbdata = $this->get_post_mbdatas($post->ID);

        ?>
        <table class="form-table">
            <tbody>
                <?php 
                if ($mbdata) {
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
                                $list = wpsstm_get_list_from_array($mbdata);
                                printf('<div id="wpsstm-mbdata">%s</div>',$list);
                                ?>
                            </p>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                <?php 
                if ($mbdata) {
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Refresh data','wpsstm');?></label>
                        </th>
                        <td>
                            <?php
                            submit_button( __('Refresh data','wpsstm'), null, 'wpsstm-mb-reload');
                    
                            if ( $then = get_post_meta( $post->ID, self::$mbdata_time_metakey, true ) ){
                                $now = current_time( 'timestamp' );
                                $refreshed = human_time_diff( $now, $then );
                                printf(__('Data was refreshed %s ago.','wpsstm'),$refreshed);
                            }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('Fill post','wpsstm');?></label>
                        </th>
                        <td>
                            <p>
                            <?php
                            $fields = self::get_fillable_fields($post->ID);
                    
                            foreach ($fields as $slug=>$field){
                                
                                //mismatch check
                                $mismatch  = $db = $mb = null;
                                
                                $meta = get_post_meta($post->ID,$field['metaname'],true);
                                $mb = wpsstm_get_array_value($field['mbpath'], $mbdata);

                                $mismatch_icon = ($meta != $mb) ? '<i class="fa fa-exclamation-triangle" aria-hidden="true"></i> ' : null;

                                $input_el = sprintf('<input type="checkbox" name="wpsstm-mb-fill-fields[]" value="%s"/> %s<label>%s</label><br/>',$slug,$mismatch_icon,$field['name']);
                                echo $input_el;
                            }
                            submit_button( __('Fill with data','wpsstm'), null, 'wpsstm-mb-fill');
                            ?>
                            </p>
                            <?php
                            _e('Fill post with various datas from MusicBrainz (eg. artist, length, ...).','wpsstm');
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

        wp_nonce_field( 'wpsstm_mbdata_meta_box', 'wpsstm_mbdata_meta_box_nonce' );
        
        
    }
    
    public function metabox_mb_entries_content($post){

        settings_errors('wpsstm-mb-entries');

        $entries = self::search_mb_entries_for_post($post->ID);
        
        if ( is_wp_error($entries) ){
            add_settings_error('wpsstm-mb-entries', 'api_error', $entries->get_error_message(),'inline');
        }else{
            $entries_table = new WPSSTM_MB_Entries();
            $entries_table->items = $entries;
            $entries_table->prepare_items();
            $entries_table->display();
        }

        //same nonce than in metabox_mbid_content()
        wp_nonce_field( 'wpsstm_mbid_meta_box', 'wpsstm_mbid_meta_box_nonce' );
    }

    public static function metabox_mbid_save( $post_id ){

        $mbid = null;
        $mbdata = null;

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );
        
        $is_metabox = isset($_POST['wpsstm_mbid_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_mbid_meta_box_nonce'], 'wpsstm_mbid_meta_box' ) );
        if ( !$is_valid_nonce ) return;
        
        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_mbid_meta_box_nonce']);

        //clicked a musicbrainz action button
        $action = null;
        if ( isset($_POST['wpsstm-mb-id-lookup']) ){
            $action = 'autoguess-id';
        }

        //update MBID
        $old_mbid = wpsstm_get_post_mbid($post_id);
        $mbid = ( isset($_POST['wpsstm_mbid']) ) ? trim($_POST['wpsstm_mbid']) : null;
        $is_mbid_update = ($old_mbid != $mbid);

        if (!$mbid){
            delete_post_meta( $post_id, self::$mbid_metakey );
            delete_post_meta( $post_id, self::$mbdata_metakey ); //delete mdbatas
            delete_post_meta( $post_id, self::$mbdata_time_metakey ); //delete mdbatas timestamp
        }else{
            update_post_meta( $post_id, self::$mbid_metakey, $mbid );
            if ($is_mbid_update){
                $this->reload_mb_datas($post_id);
            }
        }
        
        //ignore auto MBID
        if ( $this->get_options('auto_mbid') ){
            $do_ignore = ( isset($_POST['wpsstm-ignore-auto-mbid']) ) ? true : false;
            if ($do_ignore){
                update_post_meta( $post_id, self::$no_auto_mbid_metakey, true );
            }else{
                delete_post_meta( $post_id, self::$no_auto_mbid_metakey );
            }
        }

        switch ($action){
            case 'autoguess-id':
                $mbid = $this->auto_mbid( $post_id );
                if ( is_wp_error($mbid) ) break;
            break;
        }
        
    }
    
    public static function metabox_mbdata_save( $post_id ){

        $mbid = null;
        $mbdata = null;

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $is_autodraft = ( get_post_status( $post_id ) == 'auto-draft' );
        $is_revision = wp_is_post_revision( $post_id );

        $is_metabox = isset($_POST['wpsstm_mbdata_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_autodraft || $is_revision ) return;

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        //nonce
        $is_valid_nonce = ( wp_verify_nonce( $_POST['wpsstm_mbdata_meta_box_nonce'], 'wpsstm_mbdata_meta_box' ) );
        if ( !$is_valid_nonce ) return;

        //this should run only once (for the main post); so unset meta box nonce.
        //without this the function would be called for every subtrack if there was some.
        unset($_POST['wpsstm_mbdata_meta_box_nonce']);

        //clicked a musicbrainz action button
        $action = null;
        if ( isset($_POST['wpsstm-mb-reload']) ){
            $action = 'reload';
        }elseif ( isset($_POST['wpsstm-mb-fill']) ){
            $action = 'fill';
        }

        switch ($action){

            case 'reload':
                $this->reload_mb_datas($post_id);
            break;
            case 'fill':
                $field_slugs = isset($_POST['wpsstm-mb-fill-fields']) ? $_POST['wpsstm-mb-fill-fields'] : array();

                if ( !empty($field_slugs) ){
                    $this->fill_with_mbdatas($post_id,$field_slugs,true);
                }

            break;
        }
        
    }
    
    //TO FIX TO CHECK
    //Do not override basic informations ? (eg. for a track, artist & title)
    private function fill_with_mbdatas($post_id=null,$field_slugs=null,$override=false){
        $mbdatas = $this->get_post_mbdatas($post_id);
        $fields = self::get_fillable_fields($post_id);
        
        if (!$mbdatas) return;
        
        $fields_success = array();
        
        //which fields to fill ?
        if ($field_slugs === null){
            $field_slugs = array_keys($fields);
        }

        foreach($field_slugs as $slug){
            
            $field = ( isset($fields[$slug]) ) ? $fields[$slug] : null;
            if (!$field) continue;
            
            $meta_value = get_post_meta($post_id,$field['metaname'],true);
            $mb_value = wpsstm_get_array_value($field['mbpath'], $mbdatas);

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
        wpsstm()->debug_log( json_encode($fields_success),"metabox_mbid_save() - filled post with MB datas" ); 

    }
    
    /*
    Reload MusicBrainz entry data for an MBID.
    */
    
    private function reload_mb_datas($post_id){

        //delete existing
        if ( delete_post_meta( $post_id, self::$mbdata_metakey ) ){
            delete_post_meta( $post_id, self::$mbdata_time_metakey ); //delete timestamp
            wpsstm()->debug_log('WPSSTM_MusicBrainz::reload_mb_datas() : deleted mb datas');
        }


        if ( !$mbid = wpsstm_get_post_mbid($post_id) ) return;

        //get API data
        $mb_post_type = self::get_musicbrainz_type_by_post_id($post_id);
        $data = self::get_musicbrainz_api_entry($mb_post_type,$mbid);

        if ( is_wp_error($data) ) return $data;

        if ( $success = update_post_meta( $post_id, self::$mbdata_metakey, $data ) ){
            
            //fill empty fields with mb datas
            $this->fill_with_mbdatas($post_id);
            
            //save timestamp
            $now = current_time('timestamp');
            update_post_meta( $post_id, self::$mbdata_time_metakey, $now );
        }
        
        return $success;
        
    }

    /*
    When saving an artist / track / album and that no MBID exists, guess it - if option enabled
    */
    
    public function auto_mbid_on_post_save( $post_id ){

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $skip_status = in_array( get_post_status( $post_id ),array('auto-draft','trash') );
        $is_revision = wp_is_post_revision( $post_id );

        if ( $is_autosave || $skip_status || $is_revision ) return;

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;
        
        //ignore if global option disabled
        $auto_id = ( $this->get_options('auto_mbid') );
        if (!$auto_id) return false;

        //ignore if option disabled
        $is_ignore = ( get_post_meta( $post_id, self::$no_auto_mbid_metakey, true ) );
        if ($is_ignore) return false;
        
        //ignore if value exists
        $value = wpsstm_get_post_mbid($post_id);
        if ($value) return;
        
        //get auto mbid
        $mbid = $this->auto_mbid( $post_id );
        if ( is_wp_error($mbid) ) return $mbid;
        
        if($mbid){
            add_filter( 'redirect_post_location', array(__class__,'after_auto_mbid_redirect') );
        }

        return $mbid;
    }
    
    public static function after_auto_mbid_redirect($location){
        $location = add_query_arg(array('mb-list-entries'=>true),$location);
        return $location;
    }
    
    /**
    Try to guess the MusicBrainz ID of a post, based on its artist / album / title.
    **/

    public function auto_mbid( $post_id ){
        
        //TO FIX limit musicbrainz query to 1 entry max ?

        $mbid = null;
        $entries = array();

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return false;

        $entries = self::search_mb_entries_for_post($post_id);
        if ( is_wp_error($entries) ) return $entries;
        if (!$entries) return;
        
        $first_entry = $entries[0];
        $score = $first_entry['score'];

        //only if we got a minimum score
        if ($score < 90) return;

        //get MBID of first entry
        $mbid = $first_entry['id'];

        if ($mbid){
            if ( $success = update_post_meta( $post_id, self::$mbid_metakey, $mbid ) ){
                wpsstm()->debug_log( json_encode(array('post_id'=>$post_id,'mbid'=>$mbid)),"Updated MBID" ); 
                $this->reload_mb_datas($post_id);
            }
            
            return $mbid;
        }
        
    }

    /**
    Fill current post with various informations from MusicBrainz
    **/
    
    private static function get_fillable_fields($post_id = null){
        $items = array();
        $post_type = get_post_type($post_id);
        switch($post_type){
            //artist
            case wpsstm()->post_type_artist:
                $items['artist'] = array(
                    'name' =>       __('Artist','wpsstm'),
                    'metaname' =>   WPSSTM_Core_Artists::$artist_metakey,
                    'mbpath' =>     array('name')
                );
            break;
            //album
            case wpsstm()->post_type_album:
                $items['album'] = array(
                    'name'=>__('Album','wpsstm'),
                    'metaname' =>   WPSSTM_Core_Albums::$album_metakey,
                    'mbpath' =>     array('title')
                );
                $items['album_artist'] = array(
                    'name'=>__('Artist','wpsstm'),
                    'metaname' =>   WPSSTM_Core_Artists::$artist_metakey,
                    'mbpath' =>     array('artist-credit',0,'name')
                );
                $items['album_tracklist'] = array(
                    'name'=>__('Tracklist','wpsstm') 
                );
            break;
            //track
            case wpsstm()->post_type_track:
                $items['track'] = array(
                    'name'=>__('Title','wpsstm'),
                    'metaname' =>   WPSSTM_Core_Tracks::$title_metakey,
                    'mbpath' =>     array('title')
                );
                $items['track_artist'] = array(
                    'name'=>__('Artist','wpsstm'),
                    'metaname' =>   WPSSTM_Core_Artists::$artist_metakey,
                    'mbpath' =>     array('artist-credit',0,'name')
                );
                $items['track_album'] = array(
                    'name'=>__('Album','wpsstm'),
                    'metaname' =>   WPSSTM_Core_Albums::$album_metakey,
                    'mbpath' =>     array('releases',0,'title')
                );
                $items['track_length'] = array(
                    'name'=>__('Length','wpsstm'),
                    'metaname' =>   WPSSTM_Core_Tracks::$length_metakey,
                    'mbpath' =>     array('length') 
                );
            break;
        }

        return $items;
    }

    static function get_musicbrainz_api_entry($type,$mbid = null,$query = null,$offset = null){
        global $post;

        $api_results = null;
        $cached_results = null;

        $allowed_types = array('artist', 'release', 'release-group', 'recording', 'work', 'label');
        
        if ( !in_array($type,$allowed_types) ) 
            return new WP_Error('mb_invalid_type',__("invalid MusicBrainz type",'wpsstm'));

        $inc = array('url-rels'); //https://musicbrainz.org/doc/Development/XML_Web_Service/Version_2
        
        switch($type){
            case 'artist':
                $inc[] = 'tags';
            break;
            case 'recording':
                $inc[] = 'artist-credits';
                $inc[] = 'releases';
            break;
            case 'release':
                $inc[] = 'artist-credits';
                //$inc[] = 'collections';
                $inc[] = 'labels';
                $inc[] = 'recordings';
                $inc[] = 'release-groups';

            break;
        }
        
        
        if ($mbid){
            $url = sprintf('http://musicbrainz.org/ws/2/%1s/%2s',$type,$mbid);
        }elseif($query){
            $url = sprintf('http://musicbrainz.org/ws/2/%1s/',$type);
            $url = add_query_arg(array('query'=>$query),$url);
        }else{
            return;
        }

        if ($inc) $url = add_query_arg(array('inc'=>implode('+',$inc)),$url);
        $url = add_query_arg(array('fmt'=>'json'),$url);

        //define the transient name for this MB url
        $transient_url_name = str_replace('http://musicbrainz.org/ws/2/','',$url);
        $transient_url_name = self::$mb_data_by_url_transient_prefix.md5($transient_url_name); //WARNING should be 172 characters or less or less !  md5 returns 32 chars.
        
        // check if we should try to load cached data
        if ( $days_cache = wpsstm()->get_options('cache_api_results') ){
            $api_results = $cached_results = get_transient( $transient_url_name );
        }
        
        if ( !$api_results ){
            
            //TO FIX TO CHECK : delay API call ?
            if ($wait_api_time = self::$mb_api_sleep ){
                sleep($wait_api_time);
            }

            //do request
            $request_args = array(
              'timeout' => 20,
              'User-Agent' => sprintf('wpsstm/%s',wpsstm()->version)
            );

            $request = wp_remote_get($url,$request_args);
            if (is_wp_error($request)) return $request;

            $response = wp_remote_retrieve_body( $request );
            if (is_wp_error($response)) return $response;

            if ( $api_results = json_decode($response, true) ){
                //api error
                if ( isset($api_results['error']) ){
                    $error = $api_results['error'];
                    return new WP_Error('mb_api_error',$error );
                }elseif ( $days_cache ){
                    set_transient( $transient_url_name, $api_results, $days_cache * DAY_IN_SECONDS );
                }
            }

        }
        
        //debug
        $cached = ($cached_results) ? '(loaded from transient)' : null;
        wpsstm()->debug_log(sprintf("get_musicbrainz_api_entry():%s %s",$url,$cached)); 

        return $api_results;

    }
    
    private static function search_mb_entries_for_post($post_id = null ){
        
        global $post;
        if (!$post_id) $post_id = $post->ID;
        if (!$post_id) return;

        $api_type = self::get_musicbrainz_type_by_post_id($post_id);
        $artist = wpsstm_get_post_artist($post_id);
        $track = wpsstm_get_post_track($post_id);
        $album = wpsstm_get_post_album($post_id);

        $api_lookup = null;
        $api_query = null;
        $result_keys = null;

        switch($api_type){
                
            case 'artist':
                
                if ( !$artist ) break;
                
                $api_query = '"'.rawurlencode($artist).'"';
                $result_keys = array('artists');
                
            break;
                
            case 'recording': //track
                
                if ( !$artist || !$track ) break;
                
                $api_query = '"'.rawurlencode($track).'"';
                $api_query .= rawurlencode(sprintf(' AND artist:%s',$artist));
                $result_keys = array('recordings');
                
            break;
                
            case 'release': //album
                
                if ( !$artist || !$album ) break;
                
                $api_query = '"'.rawurlencode($album).'"';
                $api_query .= rawurlencode(sprintf(' AND artist:%s',$artist));
                $result_keys = array('releases');
                
            break;

        }

        if (!$api_query) return;
        
        $data = self::get_musicbrainz_api_entry($api_type,null,$api_query);
        if ( is_wp_error($data) ) return $data;
        return wpsstm_get_array_value($result_keys, $data);
        
    }
    
    public static function get_musicbrainz_type_by_post_id($post_id = null){
        global $post;
        if (!$post_id) $post_id = $post->ID;
        $post_type = get_post_type($post_id);
        
        $mbtype = null;
        switch( $post_type ){
            case wpsstm()->post_type_artist:
                $mbtype = 'artist';
            break;
            case wpsstm()->post_type_track:
                $mbtype = 'recording';
            break;
            case wpsstm()->post_type_album:
                $mbtype = 'release';
            break;
        }
        return $mbtype;
        
    }
    
    function get_post_mbdatas($post_id = null, $keys=null){

        if ( !$this->get_options('enabled') ) return false;

        global $post;
        if (!$post_id) $post_id = $post->ID;
        $data = get_post_meta( $post_id, self::$mbdata_metakey, true );

        if ($keys){
            return wpsstm_get_array_value($keys, $data);
        }else{
            return $data;
        }

    }
    
    static function register_musicbrainz_service_links($links){
        $item = sprintf('<a href="https://www.musicbrainz.org" target="_blank" title="%s"><img src="%s" /></a>','Musicbrainz',wpsstm()->plugin_url . '_inc/img/musicbrainz-icon.png');
        $links[] = $item;
        return $links;
    }
    
}

class WPSSTM_MB_Entries extends WP_List_Table {
    
    function display_tablenav($which){
        
    }
    
    function prepare_items() {
        
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array($columns, $hidden, $sortable);
        
    }
    
    function get_columns(){
        global $post;
        
        $columns = array(
            'cb'            => '',//<input type="checkbox" />', //Render a checkbox instead of text
        );
        
        switch($post->post_type){
                
            case wpsstm()->post_type_artist:
                $columns['mbitem_artist'] = __('Artist','wpsstm');
            break;
                
            case wpsstm()->post_type_track:
                $columns['mbitem_track'] = __('Track','wpsstm');
                $columns['mbitem_artist'] = __('Artist','wpsstm');
                $columns['mbitem_album'] = __('Album','wpsstm');
            break;
                
            case wpsstm()->post_type_album:
                $columns['mbitem_album'] = __('Album','wpsstm');
                $columns['mbitem_artist'] = __('Artist','wpsstm');
                
            break;

        }
        
        //mbid
        $columns['mbitem_mbid'] = __('MusicBrainz ID','wpsstm');
        
        if ( WPSSTM_MusicBrainz::is_entries_switch() ){
            $columns['mbitem_score'] = __('Score','wpsstm');
        }

        return $columns;
    }

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
        
        if ( !WPSSTM_MusicBrainz::is_entries_switch() ) return;

        $mbid = wpsstm_get_post_mbid($post->ID);

		?>
		<input type="radio" name="wpsstm_mbid" id="cb-select-<?php echo $item['id']; ?>" value="<?php echo esc_attr( $item['id'] ); ?>" <?php checked($item['id'], $mbid );?> />
		<?php
	}
    
	public function column_mbitem_artist( $item ) {
        global $post;
        
        $output = '—';
        $artist = null;

        switch($post->post_type){
                
            case wpsstm()->post_type_artist:
                $artist = $item;
            break;
                
            case wpsstm()->post_type_track:
            case wpsstm()->post_type_album:
                $artist = $item['artist-credit'][0]['artist'];
            break;

        }
        
        if (!$artist) return $output;
        
        $output = $artist['name'];

        if ( isset($artist['disambiguation']) ){
            $output.=' '.sprintf('<small>%s</small>',$artist['disambiguation']);
        }

        return $output;
    }
    
	public function column_mbitem_track( $item ) {
        global $post;
        
        $output = '—';
        
        switch($post->post_type){
                
            case wpsstm()->post_type_track:
                $output = $item['title'];
            break;

        }
        
        return $output;
    }
    
	public function column_mbitem_album( $item ) {
        global $post;
        
        $album = null;
        $output = '—';

        switch($post->post_type){
                
            case wpsstm()->post_type_track:
                 $album = wpsstm_get_array_value(array('releases',0),$item);
            break;
                
            case wpsstm()->post_type_album:
                $album = $item;
            break;

        }
        
        if (!$album) return $output;

        $output = $album['title'];
        $small_title_arr = array();
        
        
        //date
        if ( isset($album['date']) ){
            $small_classes = array('item-info-title');
            $small_classes_str = wpsstm_get_classes_attr($small_classes);
            $small_title_arr[]=' '.sprintf('<small %s>%s</small>',$small_classes_str,$album['date']);
        }
        
        $output .= implode("",$small_title_arr);

        return $output;
    }
    
	/**
	 * Handles the link URL column output.
	 *
	 * @since 4.3.0
	 * @access public
	 *
	 * @param object $item The current link object.
	 */
	public function column_mbitem_mbid( $item ) {
        global $post;
        
        $mbid = $item['id'];
        $url = null;
        
        $mbtype = WPSSTM_MusicBrainz::get_musicbrainz_type_by_post_id($post->ID);
        $url = sprintf('https://musicbrainz.org/%s/%s',$mbtype,$mbid);
        
        printf('<a href="%1s" target="_blank">%2s</a>',$url,$mbid);
	}
    
	public function column_mbitem_score( $item ) {
        echo wpsstm_get_percent_bar($item['score']);
	}
    
}

class WPSSTM_Musicbrainz_Release_ID_Preset extends WPSSTM_Remote_Tracklist{
    
    var $mbid;
    var $mbdatas;
    
    function __construct($url = null,$options = null) {
        
        parent::__construct($url,$options);
        
        $this->options['selectors'] = array(
            'tracks'           => array('path'=>'track'),
            'track_artist'     => array('path'=>'artist > name'),
            'track_title'      => array('path'=>'recording > title'),
            'track_album'      => array('path'=>'/ release > title'),
        );
    }

    function init_url($url){
        global $wpsstm_musicbrainz;
        
        if ( $this->mbid = self::get_release_mbid($url) ){
            $this->mbdatas = $wpsstm_musicbrainz->get_musicbrainz_api_entry('release',$this->mbid);
        }
        
        return $this->mbdatas;
    }
    
    function get_remote_request_url(){
        $url = sprintf('http://musicbrainz.org/ws/2/release/%s',$this->mbid);
        $inc = array('artist-credits','recordings');
        $url = add_query_arg(array('inc'=>implode('+',$inc)),$url);
        return $url;
    }

    static function get_release_mbid($url){
        $pattern = '~^https?://(?:www\.)?musicbrainz.org/release/([\w\d-]+)~i';
        preg_match($pattern,$url, $matches);

        $mbid =  isset($matches[1]) ? $matches[1] : null;
        
        return $mbid;
        
    }
    
}

function wpsstm_musicbrainz_init(){
    global $wpsstm_musicbrainz;
    $wpsstm_musicbrainz = new WPSSTM_MusicBrainz();
}

add_action('wpsstm_init','wpsstm_musicbrainz_init');