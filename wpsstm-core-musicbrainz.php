<?php

class WP_SoundSytem_Core_MusicBrainz {
    
    var $mb_api_sleep = 1;

    var $mb_api_errors_meta_name = '_wpsstm_mb_api_error';
    var $mb_id_meta_name = '_wpsstm_mbid'; //to store the musicbrainz ID
    var $mb_data_meta_name = '_wpsstm_mbdata'; //to store the musicbrainz datas

    var $mb_data_by_url_transient_prefix = 'wpsstm_mb_by_url_'; //to cache the musicbrainz API results

    var $qvar_mbid = 'mbid';
    
    var $is_switch_entries = null;
    
    /**
    * @var The one true Instance
    */
    private static $instance;

    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new WP_SoundSytem_Core_MusicBrainz;
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
        $this->is_switch_entries = ( isset($_GET['mb-switch-entries'])) ? true : false;
    }
    
    function setup_actions(){
        add_action( 'add_meta_boxes', array($this, 'metaboxes_mb_register'),50);
        add_action( 'save_post', array($this,'metabox_mbid_save'), 5);
        add_action( 'save_post', array($this,'auto_guess_mbid'), 6);
        add_action( 'save_post', array($this,'metabox_mbdata_save'), 7 ); //requires MBID to be set so be careful to hooks priorities
        
        add_action( 'wpsstm_updated_mbid', array($this,'update_mb_datas'), 9 );
        add_action( 'wpsstm_updated_mbdatas', array($this,'fill_post_with_mbdatas'), 9);
        
        add_filter( 'pre_get_posts', array($this,'pre_get_posts_mbid') );
        
        add_filter('manage_posts_columns', array($this,'column_mbid_register'), 10, 2 ); 
        add_action( 'manage_posts_custom_column', array($this,'column_mbid_content'), 10, 2 );
        
        add_filter('wpsstm_column_artist',array($this,'column_mb_artist'),10,3);
        add_filter('wpsstm_column_track',array($this,'column_mb_track'),10,4);
        add_filter('wpsstm_column_album',array($this,'column_mb_album'),10,4);
        
        add_filter( 'redirect_post_location', array($this,'redirect_to_switch_entries') );

    }

    
    function pre_get_posts_mbid( $query ) {

        if ( $search = $query->get( $this->qvar_mbid ) ){
            
            $query->set( 'meta_key', $this->mb_id_meta_name );
            $query->set( 'meta_query', array(
                array(
                     'key'     => $this->mb_id_meta_name,
                     'value'   => $search,
                     'compare' => '='
                )
            ));
        }

        return $query;
    }

    function column_mbid_register($defaults) {
        $post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );
        if ( isset($_GET['post_type']) && in_array($_GET['post_type'],$post_types) ){
            $defaults['mbid'] = __('MBID','wpsstm');
        }
        return $defaults;
    }
    
    function column_mbid_content($column,$post_id){
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
    
    function metaboxes_mb_register(){
            global $post;
            if (!$post) return;

            $entries_post_types = array(
                wpsstm()->post_type_artist,
                wpsstm()->post_type_track,
                wpsstm()->post_type_album
            );

            add_meta_box( 
                'wpsstm-mbid', 
                __('MusicBrainz ID','wpsstm'),
                array($this,'metabox_mbid_content'),
                $entries_post_types,
                'after_title', 
                'high' 
            );

        if ( $mbid = wpsstm_get_post_mbid($post->ID) ){
            
            add_meta_box( 
                'wpsstm_mbdata', 
                __('MusicBrainz Data','wpsstm'),
                array($this,'metabox_mbdata_content'),
                $entries_post_types,
                'side'
            );
            
        }

        
    }
    
    function metabox_mbid_content( $post ){

        $mbid = wpsstm_get_post_mbid($post->ID);
        
        ?>
        <div>
            <?php
        
                if ($this->is_switch_entries){
                    $this->metabox_mbid_content_list_entries($post);
                }else{
                    printf('<input type="text" name="wpsstm_mbid" class="wpsstm-fullwidth" value="%s" placeholder="%s"/>',$mbid,__("Enter MusicBrainz ID here",'wpsstm'));
                    if ( wpsstm()->get_options('mb_auto_id') == "on" ){
                        printf('<small>%s</small>',sprintf(__("If left empty, we'll try to guess it.  Set it to %s to disable auto ID.",'wpsstm'),'<code>-</code>'));
                    }
                }
        

                
            ?>
        </div>

        <?php settings_errors('wpsstm_mbid');
        wp_nonce_field( 'wpsstm_mbid_meta_box', 'wpsstm_mbid_meta_box_nonce' );

    }

    static function can_lookup($post_id){
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

    function metabox_mbid_content_list_entries(){
        global $post;

        $this->load_api_errors($post);
        
        $summary_url = get_edit_post_link($post->ID);
        $summary_classes = $entries_classes = array("nav-tab");
        $tablewrapper_classes = array('table-mbz');
        $entries_url = add_query_arg(array('mb_metabox_action'=>'entries'),get_edit_post_link($post->ID));
        
        if ($this->is_switch_entries){
            $entries_classes[] = 'nav-tab-active';
            $tablewrapper_classes[] = 'table-mbz-entries';
        }else{
            $summary_classes[] = 'nav-tab-active';
            $tablewrapper_classes[] = 'table-mbz-summary';
        }

        settings_errors('wpsstm_musicbrainz');
        
        ?>
        <div id="wpsstm-metabox-content"<?php wpsstm_classes($tablewrapper_classes);?>>
            <?php

            switch($post->post_type){
                case wpsstm()->post_type_artist:
                    $this->metabox_mb_entries_artist($post);
                break;
                case wpsstm()->post_type_track:
                    $this->metabox_mb_entries_track($post);
                break;
                case wpsstm()->post_type_album:
                    $this->metabox_mb_entries_release($post);
                break;

            }

        
            ?>
        </div>
        <?php
    }
    
    function metabox_mbdata_content($post){

        if ( $data = wpsstm_get_post_mbdatas($post->ID) ){
            $list = wpsstm_get_list_from_array($data);
            printf('<div>%s</div>',$list);
        }
        
        ?>
            <div id="wpsstm-metabox-mbdata-actions">
                <?php 
                if ( self::can_lookup($post->ID) ){
                    submit_button( __('Refresh data','wpsstm'), null, 'wpsstm-mbdata-refresh');
                    submit_button( __('Switch entry','wpsstm'), null, 'wpsstm-mbdata-switch');
                    submit_button( __('Fill with data','wpsstm'), null, 'wpsstm-mbdata-fill');
                }
                ?>
            </div>
        <?php
        
        //nonce
        wp_nonce_field( 'wpsstm_mbdata_meta_box', 'wpsstm_mbdata_meta_box_nonce' );
        
    }
    
    function metabox_mb_entries_artist($post){

        $entries = $this->get_mb_entries_for_post($post->ID);
        if ( !$entries || is_wp_error($entries) ) return;

        ?>

        <div id="wpsstm-metabox-content">
            
            <?php

            require_once wpsstm()->plugin_dir . 'classes/wpsstm-mb-entries-table.php';
            $entries_table = new WP_SoundSytem_MB_Entries();

            if ( is_wp_error($entries) ){
                add_settings_error('wpsstm_musicbrainz', 'api_error', $entries->get_error_message(),'inline');
            }else{
                $entries_table->items = $entries;
                $entries_table->prepare_items();
                $entries_table->display();
            }

            ?>
        </div>
        <?php
    }
        
    function metabox_mb_entries_track($post){
        
        $entries = $this->get_mb_entries_for_post($post->ID);
        if ( !$entries || is_wp_error($entries) ) return;

        require_once wpsstm()->plugin_dir . 'classes/wpsstm-mb-entries-table.php';
        $entries_table = new WP_SoundSytem_MB_Entries();

        if ( is_wp_error($entries) ){
            add_settings_error('wpsstm_musicbrainz', 'api_error', $entries->get_error_message(),'inline');
        }else{
            $entries_table->items = $entries;
            $entries_table->prepare_items();
            $entries_table->display();
        }

    }
    
    function metabox_mb_entries_release($post){
        
        $entries = $this->get_mb_entries_for_post($post->ID);
        if ( !$entries || is_wp_error($entries) ) return;

        require_once wpsstm()->plugin_dir . 'classes/wpsstm-mb-entries-table.php';
        $entries_table = new WP_SoundSytem_MB_Entries();

        if ( is_wp_error($entries) ){
            add_settings_error('wpsstm_musicbrainz', 'api_error', $entries->get_error_message(),'inline');
        }else{
            $entries_table->items = $entries;
            $entries_table->prepare_items();
            $entries_table->display();
        }
    }

    function metabox_mbid_save( $post_id ){

        $mbid = null;
        $mbdata = null;

        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_mbid_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_revision ) return;
        
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

        //save MBID input
        $mbid_db = wpsstm_get_post_mbid($post_id);
        $mbid = ( isset($_POST['wpsstm_mbid']) ) ? $_POST['wpsstm_mbid'] : null;
        
        $this->do_update_mbid($post_id,$mbid);

    }
    
    function do_update_mbid($post_id,$mbid=null){
        
        $mbid = trim($mbid);
        
        if (!$mbid){
            delete_post_meta( $post_id, $this->mb_id_meta_name );
        }else{
            update_post_meta( $post_id, $this->mb_id_meta_name, $mbid );
        }
        do_action('wpsstm_updated_mbid',$post_id);
    }
    
    /*
    When saving an artist / track / album and that no MBID exists, guess it - if option enabled
    */
    
    function auto_guess_mbid( $post_id ){

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;

        $mbid = wpsstm_get_post_mbid($post_id);        
        if ($mbid) return;
        
        $auto_id = ( wpsstm()->get_options('mb_auto_id') == "on" );
        if (!$auto_id) return;

        if ( ( !$mbid = $this->guess_mbid( $post_id ) ) || is_wp_error($mbid) ) return;
        
        $this->do_update_mbid($post_id,$mbid);

        //TO FIX should this filter be here ?
        add_filter( 'redirect_post_location', array($this,'redirect_to_switch_entries_after_new_mbid') );
    }
    
    /**
    Try to guess the MusicBrainz ID of a post, based on its artist / album / title.
    **/
    
    function guess_mbid( $post_id ){
        
        //TO FIX limit musicbrainz query to 1 entry max ?

        $mbid = null;
        $entries = array();

        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return false;

        $entries = $this->get_mb_entries_for_post($post_id);
        if (!$entries) return;
        if ( is_wp_error($entries) ) return $entries;
        
        //get MBID of first entry
        $mbid = wpsstm_get_array_value(array(0,'id'), $entries);

        wpsstm()->debug_log( array('post_id'=>$post_id,'mbid'=>$mbid),"WP_SoundSytem_Core_MusicBrainz::guess_mbid()" ); 
        
        return $mbid;
        
    }
    
    function update_mb_datas( $post_id ){
        
        //check post type
        $post_type = get_post_type($post_id);
        $allowed_post_types = array(wpsstm()->post_type_artist,wpsstm()->post_type_track,wpsstm()->post_type_album);
        if ( !in_array($post_type,$allowed_post_types) ) return;
        
        $mbid = wpsstm_get_post_mbid($post_id);
        $mbdatas = wpsstm_get_post_mbdatas($post_id);
        if ($mbid == '-') $mbid = null;
        
        if (!$mbid){
            if ($mbdatas){
                delete_post_meta( $post_id, $this->mb_data_meta_name );
                wpsstm()->debug_log('WP_SoundSytem_Core_MusicBrainz::update_mb_datas() : deleted mb datas'); 
            }
        }else{
            
            $mbdatas_id = wpsstm_get_post_mbdatas($post_id,'id');
            if ($mbid == $mbdatas_id ) return; //nothing to update

            //get API data
            $mb_post_type = $this->get_musicbrainz_type_by_post_id($post_id);
            $data = $this->get_musicbrainz_api_entry($mb_post_type,$mbid);

            if ( is_wp_error($data) ){
                wpsstm()->debug_log($data->get_error_message(),'WP_SoundSytem_Core_MusicBrainz::update_mb_datas() error'); 
                add_settings_error('wpsstm_musicbrainz', 'api_lookup', $data->get_error_message(),'inline');
                return;
            }else{
                update_post_meta( $post_id, $this->mb_data_meta_name, $data );
                wpsstm()->debug_log(json_encode($data),"WP_SoundSytem_Core_MusicBrainz::update_mb_datas()" ); 
            }
            
        }
        
        do_action('wpsstm_updated_mbdatas',$post_id);

    }
    
    function metabox_mbdata_save( $post_id ){

        $mbid = null;
        $mbdata = null;
        $mbdatas_action = null;

        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_metabox = isset($_POST['wpsstm_mbdata_meta_box_nonce']);
        if ( !$is_metabox || $is_autosave || $is_revision ) return;
        
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
        
        //get mbdatas action
        if ( isset($_REQUEST['wpsstm-mbdata-refresh']) ){
            $mbdatas_action = 'refresh';
        }elseif ( isset($_REQUEST['wpsstm-mbdata-fill']) ){
            $mbdatas_action = 'fill';
        }elseif ( isset($_REQUEST['wpsstm-mbdata-switch']) ){
            $mbdatas_action = 'switch';
        }
        
        if (!$mbdatas_action) return;

        wpsstm()->debug_log( array('post_id'=>$post_id,'action'=>$mbdatas_action),"metabox_mbdata_save()" ); 

        if ( $mbid = wpsstm_get_post_mbid($post_id) ){

            if ( $mbdatas = wpsstm_get_post_mbdatas($post_id) ){

                switch ($mbdatas_action){
                    case 'refresh':
                        if ( delete_post_meta( $post_id, $this->mb_data_meta_name ) ){
                            wpsstm()->debug_log('WP_SoundSytem_Core_MusicBrainz::metabox_mbdata_save() : deleted mb datas'); 
                            $this->update_mb_datas( $post_id );
                        }

                    break;
                    case 'fill':
                        $this->fill_post_with_mbdatas($post_id);
                    break;
                    case 'switch':
                        $this->is_switch_entries = true;
                    break;
                }
                
            }

        }else{
            //delete mdbata option 
            delete_post_meta( $post_id, $this->mb_data_meta_name );
        }
    }
    
    function load_api_errors($post){
        $api_errors = get_post_meta( $post->ID,$this->mb_api_errors_meta_name);
                            
        foreach((array)$api_errors as $error){
            add_settings_error('wpsstm_musicbrainz', 'mb_api_error', $error,'inline');
        }
        
        delete_post_meta( $post->ID,$this->mb_api_errors_meta_name);
    }


    
    /**
    Fill current post with various informations from MusicBrainz
    **/

    function fill_post_with_mbdatas($post_id,$items=array()){

        //get potential updatable items by post type
        $post_type = get_post_type($post_id);
        $post_type_items = array();
        
        switch($post_type){
            //artist
            case wpsstm()->post_type_artist:
                $post_type_items = array('artist');
            break;
            //album
            case wpsstm()->post_type_album:
                $post_type_items = array('artist','album','tracklist');
            break;
            //track
            case wpsstm()->post_type_track:
                $post_type_items = array('artist','album','track');
            break;

        }
        
        if ( empty($post_type_items) ) return;

        //what to update ?
        if ( empty($items) ){ //not defined, auto guess what to update

            if ( in_array('artist',$post_type_items) && ( !$artist = wpsstm_get_post_artist($post_id) ) ) $items[] = 'artist';
            if ( in_array('album',$post_type_items) && ( !$album = wpsstm_get_post_album($post_id) ) ) $items[] = 'album';
            if ( in_array('track',$post_type_items) && ( !$track = wpsstm_get_post_track($post_id) ) ) $items[] = 'track';

            if ( in_array('tracklist',$post_type_items) ){
                $tracklist = new WP_SoundSytem_Tracklist($post_id);
                $tracklist->load_subtracks();
                if ( empty($tracklist->tracks) ) $items[] = 'tracklist';
            }

        }
        
        //filter only what is allowed by post type
        $items = array_intersect($items, $post_type_items);
        if ( empty($items) ) return;

        wpsstm()->debug_log( array( 'post_id'=>$post_id,'post_type'=>get_post_type($post_id),'items'=>$items ), "fill_post_with_mbdatas()"); 
        
        if ( in_array('artist',$items) ){
            $this->fill_post_artist_with_mbdatas($post_id);
        }
        
        if ( in_array('album',$items) ){
            $this->fill_post_album_with_mbdatas($post_id);
        }
        
        if ( in_array('track',$items) ){
            $this->fill_post_track_with_mbdatas($post_id);
        }
        
        if ( in_array('tracklist',$items) ){
            $this->fill_post_tracklist_with_mbdatas($post_id);
        }

        do_action('mb_filled_post_with_mbdatas',$post_id);

    }
    
    function fill_post_artist_with_mbdatas($post_id){
        $post_type = get_post_type($post_id);
        $mbdatas = wpsstm_get_post_mbdatas($post_id);
        $artist = null;
        
        switch($post_type){
            //artist
            case wpsstm()->post_type_artist:
                $artist = wpsstm_get_array_value(array('name'), $mbdatas);
            break;
            //track & album
            case wpsstm()->post_type_track:
            case wpsstm()->post_type_album:
                $artist = wpsstm_get_array_value(array('artist-credit',0,'name'), $mbdatas);
            break;
        }
        
        if ($artist){
            update_post_meta( $post_id, wpsstm_artists()->metakey, $artist );
            wpsstm()->debug_log( $artist, "fill_post_artist_with_mbdatas()"); 
        }

    }
    
    function fill_post_album_with_mbdatas($post_id){
        $post_type = get_post_type($post_id);
        $mbdatas = wpsstm_get_post_mbdatas($post_id);
        $album = null;
        
        switch($post_type){
                case wpsstm()->post_type_track:
                    $album = wpsstm_get_array_value(array('releases',0,'title'), $mbdatas);
                break;
                case wpsstm()->post_type_album:
                    $album = wpsstm_get_array_value(array('title'), $mbdatas);
                break;
        }
        
        if ($album){
            update_post_meta( $post_id, wpsstm_albums()->metakey, $album );
            wpsstm()->debug_log( $album, "fill_post_album_with_mbdatas()"); 
        }
            
    }
    
    function fill_post_track_with_mbdatas($post_id){
        $post_type = get_post_type($post_id);
        $mbdatas = wpsstm_get_post_mbdatas($post_id);
        $track = null;
        
        switch($post_type){
                case wpsstm()->post_type_track:
                    $track = wpsstm_get_array_value(array('title'), $mbdatas);
                break;
        }
        
        if ($track){
            update_post_meta( $post_id, wpsstm_tracks()->metakey, $track );
            wpsstm()->debug_log( $track, "fill_post_track_with_mbdatas()"); 
        }
            
    }

    function fill_post_tracklist_with_mbdatas($post_id){
        
        if ( get_post_type($post_id) != wpsstm()->post_type_album ) return;
        
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
                
                $save_tracks[] = array(
                    'artist'    => $track['artist-credit'][0]['name'], //TO FIX what if multiple artists ?
                    'title'     => $track['title'],
                    'mbid'      => $track['id']
                );
                
                //add media separator
                /*
                if ( ($track_key == $media_tracks_last_key) && ($media_key != $media_last_key) ){
                    $save_tracks[] = array(
                        'artist'    => '---',
                        'title'     => '---',
                        'mbid'      => '---'
                    );
                }
                */
            }
        }

        if (!$save_tracks) return;
        
        $tracklist = new WP_SoundSytem_Tracklist($post_id);
        $tracklist->add($save_tracks);
        $tracklist->save_subtracks();

    }

    function redirect_to_switch_entries($location){
        if ( isset( $_POST['wpsstm-mbdata-switch'] ) ){
            $location = add_query_arg(array('mb-switch-entries'=>true),$location);
        }
        return $location;
    }
    
    function redirect_to_switch_entries_after_new_mbid($location){
        if ( isset( $_POST['save'] ) ){
            $location = add_query_arg(array('mb-switch-entries'=>true),$location);
        }
        return $location;
    }

    function get_musicbrainz_api_entry($type,$mbid = null,$query = null,$limit = null,$offset = null){
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
        $transient_url_name = $this->mb_data_by_url_transient_prefix.md5($transient_url_name); //WARNING should be 172 characters or less or less !  md5 returns 32 chars.
        
        // check if we should try to load cached data
        if ( $days_cache = wpsstm()->get_options('cache_api_results') ){
            $api_results = $cached_results = get_transient( $transient_url_name );
        }
        
        if ( !$api_results ){
            
            //TO FIX TO CHECK : delay API call ?
            if ($wait_api_time = $this->mb_api_sleep ){
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
                    update_post_meta( $post->ID,$this->mb_api_errors_meta_name, $api_results['error'] ); //temporary store error
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
    
    function get_mb_entries_for_post($post_id = null ){
        
        global $post;
        if (!$post_id) $post_id = $post->ID;
        if (!$post_id) return;

        $api_type = $this->get_musicbrainz_type_by_post_id($post_id);
        $artist = wpsstm_get_post_artist($post_id);
        $track = wpsstm_get_post_track($post_id);
        $album = wpsstm_get_post_album($post_id);

        $api_lookup = null;
        $api_query = null;
        $result_keys = null;

        switch($api_type){
                
            case wpsstm_artists()->mbtype: //artist
                
                if ( !$artist ) break;
                
                $api_query = '"'.rawurlencode($artist).'"';
                $result_keys = array('artists');
                
            break;
                
            case wpsstm_tracks()->mbtype: //track
                
                if ( !$artist || !$track ) break;
                
                $api_query = '"'.rawurlencode($track).'"';
                $api_query .= rawurlencode(sprintf(' AND artist:%s',$artist));
                $result_keys = array('recordings');
                
            break;
                
            case wpsstm_albums()->mbtype: //album
                
                if ( !$artist || !$album ) break;
                
                $api_query = '"'.rawurlencode($album).'"';
                $api_query .= rawurlencode(sprintf(' AND artist:%s',$artist));
                $result_keys = array('releases');
                
            break;

        }

        if (!$api_query) return;
        
        $data = $this->get_musicbrainz_api_entry($api_type,null,$api_query);
        if ( is_wp_error($data) ) return $data;
        return wpsstm_get_array_value($result_keys, $data);
        
    }

    
    function get_mb_url($type,$mbid){
        return sprintf('https://musicbrainz.org/%s/%s',$type,$mbid);
    }
    
    function get_musicbrainz_type_by_post_id($post_id = null){
        global $post;
        if (!$post_id) $post_id = $post->ID;
        $post_type = get_post_type($post_id);
        
        $mbtype = null;
        switch( $post_type ){
            case wpsstm()->post_type_artist:
                $mbtype = wpsstm_artists()->mbtype;
            break;
            case wpsstm()->post_type_track:
                $mbtype = wpsstm_tracks()->mbtype;
            break;
            case wpsstm()->post_type_album:
                $mbtype = wpsstm_albums()->mbtype;
            break;
        }
        return $mbtype;
        
    }
    

}

function wpsstm_mb() {
	return WP_SoundSytem_Core_MusicBrainz::instance();
}

wpsstm_mb();