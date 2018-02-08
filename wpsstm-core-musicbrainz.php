<?php

class WPSSTM_Core_MusicBrainz {
    
    static $mb_api_sleep = 1;
    static $mb_api_errors_meta_name = '_wpsstm_mb_api_error';
    static $mbid_metakey = '_wpsstm_mbid'; //to store the musicbrainz ID
    static $no_auto_mbid_metakey = '_wpsstm_no_auto_mbid';
    static $mbdata_metakey = '_wpsstm_mbdata'; //to store the musicbrainz datas
    static $mbdata_time_metakey = '_wpsstm_mbdata_time'; //to store the musicbrainz datas
    static $mb_data_by_url_transient_prefix = 'wpsstm_mb_by_url_'; //to cache the musicbrainz API results
    static $qvar_mbid = 'mbid';

    function __construct(){

        add_action( 'add_meta_boxes', array(__class__, 'metaboxes_mb_register'),50);
        add_action( 'save_post', array(__class__,'metabox_mbid_save'), 7);
        add_action( 'save_post', array(__class__,'auto_set_mbid'), 8);
        add_action( 'save_post', array(__class__,'metabox_mbdata_save'), 9);

        add_filter( 'pre_get_posts', array(__class__,'pre_get_posts_mbid') );
        
        //backend columns
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_artist), array(__class__,'mb_columns_register'), 10, 2 );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_track), array(__class__,'mb_columns_register'), 10, 2 );
        add_filter( sprintf('manage_%s_posts_columns',wpsstm()->post_type_album), array(__class__,'mb_columns_register'), 10, 2 );
        
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_artist), array(__class__,'mb_columns_content'), 10, 2 );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_track), array(__class__,'mb_columns_content'), 10, 2 );
        add_action( sprintf('manage_%s_posts_custom_column',wpsstm()->post_type_album), array(__class__,'mb_columns_content'), 10, 2 );
        

    }
    
    static function is_entries_switch(){
        return ( isset($_GET['mb-list-entries'])) ? true : false;
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
    
    public static function metaboxes_mb_register(){
        global $post;
        if (!$post) return;

        $post_link = get_edit_post_link($post->ID);
        $entries_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );

        //MBID Metabox
        $mbid_callback = array(__class__,'metabox_mbid_content');
        if ( self::is_entries_switch() ){  
            $mbid_callback = array(__class__,'metabox_mb_entries_content');
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
                array(__class__,'metabox_mbdata_content'),
                $entries_post_types,
                'after_title', 
                'high' 
            );
        }

        
        
        
    }
    
    static function get_edit_mbid_input($post_id = null){
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
        
        if ( wpsstm()->get_options('mb_auto_id') == "on" ){
            $is_ignore = ( get_post_meta( $post_id, self::$no_auto_mbid_metakey, true ) );
            $input_auto_mbid_el = sprintf('<input type="checkbox" value="on" name="wpsstm-ignore-auto-mbid" %s/>',checked($is_ignore,true,false));
            $desc_el .= sprintf(__("%s do not autoguess MBID.",'wpsstm'),$input_auto_mbid_el);
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
    
    public static function metabox_mbid_content($post){

        $mbid = wpsstm_get_post_mbid($post->ID);
        $mbdata = wpsstm_get_post_mbdatas($post->ID);
        $can_mb_search_entries = self::can_mb_search_entries($post->ID);

        ?>
        <p>
            <?php echo self::get_edit_mbid_input($post->ID);?>
        </p>
        <table class="form-table">
            <tbody>
                <?php 
                if ( $can_mb_search_entries ){
                    ?>
                    <tr valign="top">
                        <th scope="row">
                            <label><?php _e('MBID Lookup','wpsstm');?></label>
                        </th>
                        <td>
                            <?php
                            submit_button( __('MBID Lookup','wpsstm'), null, 'wpsstm-mb-mbid-lookup');
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
    
    public static function metabox_mbdata_content($post){

        $mbid = wpsstm_get_post_mbid($post->ID);
        $mbdata = wpsstm_get_post_mbdatas($post->ID);

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
                                MusicBrainz entry data 
                                */
                                if ( $mbid && ( $data = wpsstm_get_post_mbdatas($post->ID) ) ){
                                    $list = wpsstm_get_list_from_array($data);
                                    printf('<div id="wpsstm-mbdata">%s</div>',$list);
                                }
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
                            _e('Reload data from MusicBrainz.','wpsstm');
                    
                            if ( $then = get_post_meta( $post->ID, self::$mbdata_time_metakey, true ) ){
                                $now = current_time( 'timestamp' );
                                $refreshed = human_time_diff( $now, $then );
                                $refreshed = sprintf(__('It was last refreshed %s ago.','wpsstm'),$refreshed);
                                echo '  ' . $refreshed;
                            }
                            ?>
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
                            <label><?php _e('Fill post','wpsstm');?></label>
                        </th>
                        <td>
                            <p>
                            <?php
                            $fields = self::get_fillable_fields();
                            foreach ($fields as $slug=>$name){
                                $input_el = sprintf('<input type="checkbox" name="wpsstm-mb-fill-fields[]" value="%s"/> %s<br/>',$slug,$name);
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
    
    public static function metabox_mb_entries_content($post){
        self::load_api_errors($post);

        settings_errors('wpsstm_mb-entries');

        $entries = null;

        switch($post->post_type){
            case wpsstm()->post_type_artist:
                $entries = self::get_mb_entries_for_post($post->ID);
            break;
            case wpsstm()->post_type_track:
                $entries = self::get_mb_entries_for_post($post->ID);
            break;
            case wpsstm()->post_type_album:
                $entries = self::get_mb_entries_for_post($post->ID);
            break;
        }

        if ( is_wp_error($entries) ){
            add_settings_error('wpsstm_mb-entries', 'api_error', $entries->get_error_message(),'inline');
        }else{
            require_once wpsstm()->plugin_dir . 'classes/wpsstm-mb-entries-table.php';
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
        if ( isset($_POST['wpsstm-mb-mbid-lookup']) ){
            $action = 'autoguess-id';
        }elseif ( isset($_POST['wpsstm-mb-reload']) ){
            $action = 'reload';
        }elseif ( isset($_POST['wpsstm-mb-fill']) ){
            $action = 'fill';
        }

        //update MBID
        $old_mbid = wpsstm_get_post_mbid($post_id);
        $mbid = ( isset($_POST['wpsstm_mbid']) ) ? trim($_POST['wpsstm_mbid']) : null;
        $is_mbid_update = ($old_mbid != $mbid);

        if (!$mbid){
            delete_post_meta( $post_id, self::$mbid_metakey );
            delete_post_meta( $post_id, self::$mbdata_metakey ); //delete mdbatas too
            delete_post_meta( $post_id, self::$mbdata_time_metakey );
        }else{
            update_post_meta( $post_id, self::$mbid_metakey, $mbid );
            if ($is_mbid_update){
                self::reload_mb_datas($post_id);
            }
        }
        
        //ignore auto MBID
        if ( wpsstm()->get_options('mb_auto_id') == "on" ){
            $do_ignore = ( isset($_POST['wpsstm-ignore-auto-mbid']) ) ? true : false;
            if ($do_ignore){
                update_post_meta( $post_id, self::$no_auto_mbid_metakey, true );
            }else{
                delete_post_meta( $post_id, self::$no_auto_mbid_metakey );
            }
        }

        switch ($action){
            case 'autoguess-id':
                $mbid = self::guess_mbid( $post_id );
                if ( is_wp_error($mbid) ) break;
                update_post_meta( $post_id, self::$mbid_metakey, $mbid );
                self::reload_mb_datas($post_id);
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
                self::reload_mb_datas($post_id);
            break;
            case 'fill':
                $field_slugs = isset($_POST['wpsstm-mb-fill-fields']) ? $_POST['wpsstm-mb-fill-fields'] : array();
                $fields_success = array();

                if ( !empty($field_slugs) && $mbdatas = wpsstm_get_post_mbdatas($post_id) ){

                    //artist
                    if ( in_array('artist',$field_slugs) ){
                        if ( $artist = wpsstm_get_array_value(array('name'), $mbdatas) ){
                            $fields_success['artist'] = update_post_meta( $post_id, WPSSTM_Core_Artists::$artist_metakey, $artist );
                        }

                    }

                    //album
                    if ( in_array('album',$field_slugs) ){
                        if ( $album = wpsstm_get_array_value(array('title'), $mbdatas) ){
                            $fields_success['album'] = update_post_meta( $post_id, WPSSTM_Core_Albums::$album_metakey, $album );
                        }
                    }
                    //album tracklist
                    if ( in_array('album_tracklist',$field_slugs) ){
                        $fields_success['album_tracklist'] = $this->fill_post_tracklist_with_mbdatas($post_id);
                    }

                    //track artist
                    //album artist
                    if ( in_array('track_artist',$field_slugs) || in_array('album_artist',$field_slugs) ){
                        if ( $artist = wpsstm_get_array_value(array('artist-credit',0,'name'), $mbdatas) ){
                            $fields_success['track_artist'] = update_post_meta( $post_id, WPSSTM_Core_Artists::$artist_metakey, $artist );
                        }
                    }

                    //track title
                    if ( in_array('track',$field_slugs) ){
                        if ( $track = wpsstm_get_array_value(array('title'), $mbdatas) ){
                            $fields_success['track'] = update_post_meta( $post_id, WPSSTM_Core_Tracks::$title_metakey, $track );
                        }
                    }

                    //track album
                    if ( in_array('track_album',$field_slugs) ){
                        if ( $album = wpsstm_get_array_value(array('releases',0,'title'), $mbdatas) ){
                            $fields_success['track_album'] = update_post_meta( $post_id, WPSSTM_Core_Albums::$album_metakey, $album );
                        }
                    }
                    
                    //track length
                    if ( in_array('track_length',$field_slugs) ){
                        if ( $length_ms = wpsstm_get_array_value(array('length'), $mbdatas) ){
                            $length = round($length_ms / 1000);
                            $fields_success['track_length'] = update_post_meta( $post_id, WPSSTM_Core_Tracks::$length_metakey, $length );
                        }
                    }
                    
                    //log
                    $fields_success['post_id'] = $post_id;
                    wpsstm()->debug_log( json_encode($fields_success),"metabox_mbid_save() - filled post with MB datas" ); 
                    
                }

                break;
        }
        
    }
    
    function fill_post_tracklist_with_mbdatas($post_id){
        
        if ( get_post_type($post_id) != wpsstm()->post_type_album ) return;
        
        $tracks_arr = array();
        
        $mbdatas = wpsstm_get_post_mbdatas($post_id);

        //check MusicBrainz datas has media(s)
        if ( !isset($mbdatas['media']) ) return;
        $medias = (array)$mbdatas['media'];

        // Get array keys
        $media_keys = array_keys($medias);
        // Fetch last array key
        $media_last_key = array_pop($media_keys);

        foreach ($medias as $media_key => $media){
            if ( !isset($media['tracks']) ) continue;
            
            $media_tracks = $media['tracks'];
            
            // Get array keys
            $media_tracks_keys = array_keys($media_tracks);
            // Fetch last array key
            $media_tracks_last_key = array_pop($media_tracks_keys);

            foreach($media_tracks as $track_key=>$track){
                
                $tracks_arr[] = array(
                    'artist'    => $track['artist-credit'][0]['name'], //TO FIX what if multiple artists ?
                    'title'     => $track['title'],
                    'mbid'      => $track['id']
                );
                
                //add media separator
                /*
                if ( ($track_key == $media_tracks_last_key) && ($media_key != $media_last_key) ){
                    $tracks_arr[] = array(
                        'artist'    => '---',
                        'title'     => '---',
                        'mbid'      => '---'
                    );
                }
                */
            }
        }

        if (!$tracks_arr) return;
        
        $tracklist = wpsstm_get_post_tracklist($post_id);
        $tracklist->add_tracks($tracks_arr);
        return $tracklist->save_subtracks();
    }
    
    /*
    Reload MusicBrainz entry data for an MBID.
    If MBID is not set, try to guess it.
    */
    
    private static function reload_mb_datas($post_id){

        //delete existing
        if ( delete_post_meta( $post_id, self::$mbdata_metakey ) ){
            delete_post_meta( $post_id, self::$mbdata_time_metakey );
            wpsstm()->debug_log('WPSSTM_Core_MusicBrainz::reload_mb_datas() : deleted mb datas');
        }


        if ( $mbid = wpsstm_get_post_mbid($post_id) ){

            //get API data
            $mb_post_type = self::get_musicbrainz_type_by_post_id($post_id);
            $data = self::get_musicbrainz_api_entry($mb_post_type,$mbid);

            if ( is_wp_error($data) ) return $data;

            $now = current_time('timestamp');
            update_post_meta( $post_id, self::$mbdata_time_metakey, $now );
            return update_post_meta( $post_id, self::$mbdata_metakey, $data );
        }
    }

    /*
    When saving an artist / track / album and that no MBID exists, guess it - if option enabled
    */
    
    public static function auto_set_mbid( $post_id ){

        $is_autosave = ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_autosave($post_id) );
        $skip_status = in_array( get_post_status( $post_id ),array('auto-draft','trash') );
        $is_revision = wp_is_post_revision( $post_id );

        if ( $is_autosave || $skip_status || $is_revision ) return;

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;
        
        //ignore if global option disabled
        $auto_id = ( wpsstm()->get_options('mb_auto_id') == "on" );
        if (!$auto_id) return false;

        //ignore if option disabled
        $is_ignore = ( get_post_meta( $post_id, self::$no_auto_mbid_metakey, true ) );
        if ($is_ignore) return false;
        
        //ignore if value exists
        $value = wpsstm_get_post_mbid($post_id);
        if ($value) return;
        
        //get auto mbid
        $mbid = self::guess_mbid( $post_id );
        if ( is_wp_error($mbid) ) return $mbid;

        $mbid = update_post_meta( $post_id, self::$mbid_metakey, $mbid );
        self::reload_mb_datas($post_id);

        return $mbid;
    }
    
    /**
    Try to guess the MusicBrainz ID of a post, based on its artist / album / title.
    **/
    
    private static function guess_mbid( $post_id ){
        
        //TO FIX limit musicbrainz query to 1 entry max ?

        $mbid = null;
        $entries = array();

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return false;

        $entries = self::get_mb_entries_for_post($post_id);
        if (!$entries) return;
        if ( is_wp_error($entries) ) return $entries;
        
        //get MBID of first entry
        $mbid = wpsstm_get_array_value(array(0,'id'), $entries);

        wpsstm()->debug_log( array('post_id'=>$post_id,'mbid'=>$mbid),"WPSSTM_Core_MusicBrainz::guess_mbid()" ); 
        
        return $mbid;
        
    }
    
    private static function load_api_errors($post){
        $api_errors = get_post_meta( $post->ID,self::$mb_api_errors_meta_name);
                            
        foreach((array)$api_errors as $error){
            add_settings_error('wpsstm_musicbrainz', 'mb_api_error', $error,'inline');
        }
        
        delete_post_meta( $post->ID,self::$mb_api_errors_meta_name);
    }


    
    /**
    Fill current post with various informations from MusicBrainz
    **/
    
    private static function get_fillable_fields(){
        $items = array();
        $post_type = get_post_type();
        switch($post_type){
            //artist
            case wpsstm()->post_type_artist:
                $items['artist'] = __('Artist','wpsstm');
            break;
            //album
            case wpsstm()->post_type_album:
                $items['album'] = __('Album','wpsstm');
                $items['album_artist'] = __('Artist','wpsstm');
                $items['album_tracklist'] = __('Tracklist','wpsstm');
            break;
            //track
            case wpsstm()->post_type_track:
                $items['track'] = __('Title','wpsstm');
                $items['track_artist'] = __('Artist','wpsstm');
                $items['track_album'] = __('Album','wpsstm');
                $items['track_length'] = __('Length','wpsstm');
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
                    update_post_meta( $post->ID,self::$mb_api_errors_meta_name, $api_results['error'] ); //temporary store error
                    return new WP_Error('mb_api_error',$api_results['error'] );
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
    
    private static function get_mb_entries_for_post($post_id = null ){
        
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
                
            case WPSSTM_Core_Artists::$artist_mbtype: //artist
                
                if ( !$artist ) break;
                
                $api_query = '"'.rawurlencode($artist).'"';
                $result_keys = array('artists');
                
            break;
                
            case WPSSTM_Core_Tracks::$track_mbtype: //track
                
                if ( !$artist || !$track ) break;
                
                $api_query = '"'.rawurlencode($track).'"';
                $api_query .= rawurlencode(sprintf(' AND artist:%s',$artist));
                $result_keys = array('recordings');
                
            break;
                
            case WPSSTM_Core_Albums::$album_mbtype: //album
                
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

    
    static function get_mb_url($type,$mbid){
        return sprintf('https://musicbrainz.org/%s/%s',$type,$mbid);
    }
    
    public static function get_musicbrainz_type_by_post_id($post_id = null){
        global $post;
        if (!$post_id) $post_id = $post->ID;
        $post_type = get_post_type($post_id);
        
        $mbtype = null;
        switch( $post_type ){
            case wpsstm()->post_type_artist:
                $mbtype = WPSSTM_Core_Artists::$artist_mbtype;
            break;
            case wpsstm()->post_type_track:
                $mbtype = WPSSTM_Core_Tracks::$track_mbtype;
            break;
            case wpsstm()->post_type_album:
                $mbtype = WPSSTM_Core_Albums::$album_mbtype;
            break;
        }
        return $mbtype;
        
    }
}