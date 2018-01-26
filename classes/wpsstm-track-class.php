<?php

class WPSSTM_Track{
    public $post_id = null;
    public $index = -1; //order in the playlist, if any //TO FIX this property should not exist. Order is related to the tracklist, not to the track ?
    
    public $title;
    public $artist;
    public $album;
    public $duration; //in seconds
    public $mbid = null; //set 'null' so we can check later (by setting it to false) if it has been requested
    
    public $image_url;
    public $location;

    public $parent_ids = array();

    private $did_post_id_lookup = false;
    private $did_parents_query = false;
    
    var $source;
    public $sources = null; //set 'null' so we can check later (by setting it to false) it has been populated
    var $current_source = -1;
    var $source_count = 0;
    var $in_source_loop = false;

    function __construct( $post_id = null ){

        //has track ID
        if ( $post_id && ( get_post_type($post_id) == wpsstm()->post_type_track ) ){

            $this->post_id = (int)$post_id;

            //populate datas if they are not set yet (eg. if we save a track, we could have set the values for track update)

            $this->title        = wpsstm_get_post_track($post_id);
            $this->artist       = wpsstm_get_post_artist($post_id);
            $this->album        = wpsstm_get_post_album($post_id);
            $this->mbid         = wpsstm_get_post_mbid($post_id);
            $this->image_url    = wpsstm_get_post_image_url($post_id);

        }

        
    }
    
    function from_array( $args = null ){

        $args_default = $this->get_default();
        $args = wp_parse_args((array)$args,$args_default);

        //set properties from args input
        foreach ($args as $key=>$value){
            
            switch($key){
                case 'source_urls':
                    $this->add_sources($value); //TO FIX we should not need this line, but it does not work without - this should be done in populate_sources()
                break;
                default:
                    if ( !array_key_exists($key,$args_default) ) continue;
                    if ( !isset($args[$key]) ) continue; //value has not been set
                    $this->$key = $args[$key];
                break;
            }

        }

        //populate post ID if track already exists in the DB
        //TO FIX check if this doesn't slow the page rendering
        $this->populate_track_post_auto();
    }
    
    /*
    Query tracks (IDs) that have the same artist + title (+album if set)
    */
    
    function get_track_duplicates(){
        
        if (!$this->artist || !$this->title) return;
        
        $query_args = array(
            'post_type' =>      wpsstm()->post_type_track,
            'post_status' =>    'any',
            'posts_per_page'=>  -1,
            'fields' =>         'ids',
            WPSSTM_Core_Artists::$qvar_artist_lookup => $this->artist,
            WPSSTM_Core_Tracks::$qvar_track_lookup =>   $this->title
        );

        if ($this->post_id){
            $query_args['post__not_in'] = array($this->post_id);
        }

        if ($this->album){
            $query_args[WPSSTM_Core_Albums::$qvar_album_lookup] = $this->album;
        }

        $query = new WP_Query( $query_args );

        return $query->posts;
    }
    
    /*
    Get the post ID for this track, if it already exists in the database; and populate its data
    */
    
    function populate_track_post_auto(){
        if ( $this->post_id || $this->did_post_id_lookup || (!$this->artist || !$this->title) ) return;

        if ( $duplicates = $this->get_track_duplicates() ){
            $this->__construct( $duplicates[0] );
            
            wpsstm()->debug_log(json_encode(array('track'=>sprintf('%s - %s - %s',$this->artist,$this->title,$this->album),'post_id'=>$this->post_id),JSON_UNESCAPED_UNICODE),'WPSSTM_Track::populate_track_post_auto()');
            
        }

        $this->did_post_id_lookup = true;
        return $this->post_id;

    }

    
    function get_default(){
        return array(
            'post_id'       =>null,
            'index'         => -1,
            'title'         =>null,
            'artist'        =>null,
            'album'         =>null,
            'image_url'     =>null,
            'location'      =>null,
            'mbid'          =>null,
            'duration'      =>null,
            'sources'       =>null,
        );
    }
    
    /*
    Get IDs of the parent tracklists (albums / playlists / live playlists) for a subtrack.
    */
    function get_parent_ids($args = null){
        global $wpdb;
        
        if ($this->did_parents_query) return $this->parent_ids;
        
        //track ID is required
        if ( !$this->post_id ) return;//track does not exists in DB

        $default_args = array(
            'post_type'         => wpsstm()->tracklist_post_types,
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids',
            'subtrack_id'       => $this->post_id,
        );

        $args = wp_parse_args((array)$args,$default_args);

        //wpsstm()->debug_log($args,'WPSSTM_Track::get_parent_ids()');

        $query = new WP_Query( $args );

        $this->parent_ids = $query->posts;
        $this->did_parents_query = true;

        return $this->parent_ids;
    }
    

    
    function get_parents_list(){

        $tracklist_ids = $this->get_parent_ids();
        $links = array();

        foreach((array)$tracklist_ids as $tracklist_id){

            $tracklist_post_type = get_post_type($tracklist_id);

            $playlist_url = get_permalink($tracklist_id);
            $playlist_name = ( $title = get_the_title($tracklist_id) ) ? $title : sprintf('#%s',$tracklist_id);

            $links[] = sprintf('<li><a href="%s">%s</a></li>',$playlist_url,$playlist_name);
        }
        
        if ($links){
            return sprintf('<ul class="wpsstm-track-parents">%s</ul>',implode("\n",$links));
        }
    }

    function to_array(){
        $defaults = $this->get_default();
        $export = array();
        foreach ((array)$defaults as $param=>$dummy){
            $export[$param] = $this->$param;
        }
        return array_filter($export);
    }
    
    /*
    Reduce track to the minimum; used to send data through URLs or ajax.
    */
    function to_ajax(){
        
        $allowed  = ['post_id','title','artist','album','mbid'];
        
        if ($this->post_id){
            $allowed  = ['post_id'];
        }
        
        $arr = $this->to_array();

        /*
        //requires PHP 5.6.0
        
        $filtered = array_filter(
            $arr,
            function ($key) use ($allowed) {
                return in_array($key, $allowed);
            },
            ARRAY_FILTER_USE_KEY
        );
        */
        
        $filtered = array_intersect_key($arr, array_flip($allowed));
        
        return $filtered;
    }
    
    /**
    http://www.xspf.org/xspf-v1.html#rfc.section.4.1.1.2.14.1.1
    */
    
    function to_xspf_array(){
        $xspf_track = array(
            'identifier'    => ( $this->mbid ) ? sprintf('https://musicbrainz.org/recording/%s',$this->mbid) : null,
            'title'         => $this->title,
            'creator'       => $this->artist,
            'album'         => $this->album
        );
        
        $xspf_track = array_filter($xspf_track);
        
        return apply_filters('wpsstm_get_track_xspf',$xspf_track,$this);
    }
    
    function validate_track($strict = true){

        if ($strict){
            if (!$this->artist){
                return new WP_Error( 'wpsstm_missing_track_artist', __("No artist found for this track.",'wpsstm') );
            }
            if (!$this->title){
                return new WP_Error( 'wpsstm_missing_track_title', __("No title found for this track.",'wpsstm') );
            }
        }else{ //wizard mode
            if ( !$this->artist || !$this->title ){
                return new WP_Error( 'wpsstm_missing_track_details', __("No artist or title found for this track.",'wpsstm') );
            }
        }
        return true;
    }

    function save_track($args = null){
        
        $valid = $this->validate_track();
        
        if ( is_wp_error( $valid ) ){
            return new WP_Error( 'wpsstm_cannot_validate_track', sprintf(__("Error while validating the track: %s",'wpsstm'),$valid->get_error_message() ) );
        }
        
        $post_id = null;

        $args_default = array(
            'post_status'   => 'publish',
            'post_author'   => get_current_user_id(),
        );
        
        $args = (!$args) ? $args_default : wp_parse_args($args,$args_default);
        
        $user_id = $args['post_author'];
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = $post_type_obj->cap->edit_posts;

        if ( !user_can($user_id,$required_cap) ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to create a new track.",'wpsstm') );
        }
        
        $meta_input = array(
            WPSSTM_Core_Artists::$artist_metakey    => $this->artist,
            WPSSTM_Core_Tracks::$title_metakey      => $this->title,
            WPSSTM_Core_Albums::$album_metakey      => $this->album,
            WPSSTM_Core_MusicBrainz::$mbid_metakey           => $this->mbid,
            WPSSTM_Core_Tracks::$image_url_metakey  => $this->image_url,
        );
        
        $meta_input = array_filter($meta_input);
        
        $required_args = array(
            'post_type'     => wpsstm()->post_type_track,
            'meta_input'    => $meta_input,
        );
        
        $args = wp_parse_args($required_args,$args);

        //check if this track already exists
        if (!$this->post_id){
            $this->populate_track_post_auto();
        }
        
        if (!$this->post_id){
            
            $success = wp_insert_post( $args, true );
            
        }else{ //is a track update
            
            $args['ID'] = $this->post_id;
            $success = wp_update_post( $args, true );
        }
        
        if ( is_wp_error($success) ) return $success;
        $this->post_id = $success;
        
        wpsstm()->debug_log( array('post_id'=>$this->post_id,'args'=>json_encode($args)), "WPSSTM_Track::save_track()" ); 

        //save sources if any set
        //TO FIX TO CHECK how often this runs; and when ?
        foreach ((array)$this->sources as $source_raw){
            
            $source = new WPSSTM_Source();
            $source->track = $this;
            
            $source->from_array($source_raw);         
        }

        return $this->post_id;
        
    }
    
    function trash_track(){

        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $can_delete_track = current_user_can($post_type_obj->cap->delete_post,$this->post_id);

        if ( !$can_delete_track ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to delete this track.",'wpsstm') );
        }
        
        $success = wp_trash_post($this->post_id);
        
        //wpsstm()->debug_log( array('post_id',$this->post_id,'success'=>$success), "WPSSTM_Track::trash_track()"); 
        
        return $success;
        
    }

    
    function musicbrainz(){
        //abord
        if( !$this->artist || !$this->title ) return;
        if( $this->mbid !== null ) return;
        
        //query
        $mzb_args = '"'.rawurlencode($this->title).'"';
        $mzb_args .= rawurlencode(' AND artist:');
        $mzb_args .= '"'.rawurlencode($this->artist).'"';

        //TO FIX album is ignored for the moment.
        /*
        if(!empty($this->album)){
            $mzb_args .= rawurlencode(' AND release:');
            $mzb_args .= '"'.rawurlencode($this->album).'"';
        }
        */
        $api_type = WPSSTM_Core_Tracks::$track_mbtype;
        $api_response = WPSSTM_Core_Musicbrainz::get_musicbrainz_api_entry($api_type,null,$mzb_args);

        if (is_wp_error($api_response)) return;

        if ( $api_response['count'] && ($match = wpsstm_get_array_value(array('recordings',0),$api_response) ) ){ //WE'VE GOT A MATCH !!!

            //check score is high enough
            if($match['score']=70){

                $this->mbid = $match['id'];
                $this->title = $match['title'];
                $this->duration = wpsstm_get_array_value('length',$match);

                //artist
                $artists = wpsstm_get_array_value('artist-credit',$match);
                $artists_names_arr = array();

                foreach((array)$artists as $artist){
                    $obj = $artist['artist'];
                    $artists_names_arr[]=$obj['name'];
                }
                $this->artist = implode(' & ',$artists_names_arr);

                //album
                if ( $album = wpsstm_get_array_value('releases',0,$match) ){
                    $this->album = $album['title'];
                }

            }

        }
        
        return $api_response;
        
    }

    function love_track($do_love){
        
        if ( !$this->artist || !$this->title ) return new WP_Error('missing_love_track_data',__("Required track information missing",'wpsstm'));
        if ( !$user_id = get_current_user_id() ) return new WP_Error('no_user_id',__("User is not logged",'wpsstm'));

        //track does not exists yet, create it
        if ( !$this->post_id ){
            $success = $this->save_track();
            if ( is_wp_error($success) ) return $success;
        }

        //set post status to 'publish' if it is not done yet (it could be a temporary post)
        $track_post_type = get_post_status($this->post_id);
        if ($track_post_type != 'publish'){
            $success = wp_update_post(array(
                'ID' =>             $this->post_id,
                'post_status' =>    'publish'
            ), true);
            if( is_wp_error($success) ) return $success;
        }
        
        //capability check
        //TO FIX we should add a meta to the user rather than to the track, and check for another capability here ?
        /*
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = $post_type_obj->cap->edit_posts;
        if ( !current_user_can($required_cap) ){
            return new WP_Error( 'wpsstm_track_no_edit_cap', __("You don't have the capability required to edit this track.",'wpsstm') );
        }
        */

        if ($do_love){
            $success = update_post_meta( $this->post_id, WPSSTM_Core_Tracks::$loved_track_meta_key, $user_id );
            do_action('wpsstm_love_track',$this->post_id,$this);
        }else{
            $success = delete_post_meta( $this->post_id, WPSSTM_Core_Tracks::$loved_track_meta_key, $user_id );
            do_action('wpsstm_unlove_track',$this->post_id,$this);
        }

        return $success;
        
    }
    
    function get_track_loved_by(){
        //track ID is required
        if ( !$this->post_id  ) return;//track does not exists in DB
        
        return get_post_meta($this->post_id, WPSSTM_Core_Tracks::$loved_track_meta_key);
    }
    
    function is_track_loved_by($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;
        
        $loved_by = $this->get_track_loved_by();
        return in_array($user_id,(array)$loved_by);
    }
    
    
    function get_loved_by_list(){
        $list = null;
        if ( !$user_ids = $this->get_track_loved_by() ) return;
        
        foreach($user_ids as $user_id){
            $user_info = get_userdata($user_id);
            $link = sprintf('<li><a href="%s" target="_blank">%s</a></li>',get_author_posts_url($user_id),$user_info->user_login);
            $links[] = $link;
        }

        $list = sprintf('<ul class="wpsstm-track-loved-by-list">%s</ul>',implode("\n",$links));

        return $list;
    }
    
    function query_sources($args=null){
        $default_args = array(
            'post_status'       => 'publish',
            'posts_per_page'    => -1,
            'orderby'           => 'menu_order',
            'order'             => 'ASC',
        );

        $required_args = array(
            'post_type'     => wpsstm()->post_type_source,
            'post_parent'   => $this->post_id,
        );
        
        //we need a parent track or it will return all sources; so force return nothing
        if(!$this->post_id){
            $required_args['post__in'] = array(0);
        }
        
        
        $args = wp_parse_args((array)$args,$default_args);
        $args = wp_parse_args($required_args,$args);
        return new WP_Query($args);
    }
    
    function populate_sources($args=null){
        if ($this->post_id){
            $query = $this->query_sources(array('fields'=>'ids'));
            $source_ids = $query->posts;
            $this->add_sources($source_ids);
        }else{
            $this->add_sources($this->sources); //so we're sure the sources count is set
        }
    }
    
    protected function get_remote_sources(){

        if ( !$this->artist ){
            return new WP_Error( 'wpsstm_track_no_artist', __('Required track artist missing.','wpsstm') );
        }
        
        if ( !$this->title ){
            return new WP_Error( 'wpsstm_track_no_title', __('Required track title missing.','wpsstm') );
        }

        $auto_sources = array();
        $tuneefy_providers = array();

        //tuneefy providers slugs
        foreach( (array)WPSSTM_Core_Player::get_providers() as $provider ){
            $tuneefy_providers[] = $provider->tuneefy_slug;
        }
        
        $tuneefy_providers = array_filter($tuneefy_providers);
        
        $tuneefy_args = array(
            'q' =>          urlencode($this->artist . ' ' . $this->title),
            'mode' =>       'lazy',
            'aggressive' => 'false',
            'include' =>    implode(',',$tuneefy_providers),
        );

        $api = WPSSTM_Core_Sources::tuneefy_api_aggregate('track',$tuneefy_args);
        if ( is_wp_error($api) ) return $api;

        $items = wpsstm_get_array_value(array('results'),$api);
        
        //wpsstm()->debug_log( json_encode($items), "get_remote_sources");

        //TO FIX have a more consistent extraction of datas ?
        foreach( (array)$items as $item ){
            
            $links_by_providers =   wpsstm_get_array_value(array('musical_entity','links'),$item);
            $first_provider =       reset($links_by_providers);
            $first_link =           reset($first_provider);

            $raw = array(
                'url'   =>      $first_link,
                'title'   =>     wpsstm_get_array_value(array('musical_entity','title'),$item),
            );
            
            $auto_sources[] = $raw;
            
        }

        //allow plugins to filter this
        $auto_sources = apply_filters('wpsstm_get_track_sources_auto',$auto_sources,$this);
        $new_sources = $this->add_sources($auto_sources);
        
        return $new_sources;
        
    }

    function autosource(){

        if ( wpsstm()->get_options('autosource') != 'on' ){
            return new WP_Error( 'wpsstm_autosource_disabled', __("Track autosource is disabled.",'wpsstm') );
        }
        
        $can_autosource = WPSSTM_Core_Sources::can_autosource();
        if ( is_wp_error($can_autosource) ){
            return $can_autosource;
        }

        //track does not exists yet, create it
        if ( !$this->post_id ){
            $success = $this->save_track();
            if ( is_wp_error($success) ) return $success;
        }

        $new_source_ids = array();
        
        $new_sources = $this->get_remote_sources();

        if ( is_wp_error($new_sources) ) return $new_sources;

        foreach((array)$new_sources as $source){

            $source_args = array(
                'post_author'   => wpsstm()->get_options('community_user_id')
            );
            
            $source_id = $source->add_source($source_args);
            
            if ( is_wp_error($source_id) ){
                $code = $source_id->get_error_code();
                $error_msg = $source_id->get_error_message($code);
                wpsstm()->debug_log( $error_msg, "WPSSTM_Track::autosource - unable to save source");
                continue;
            }
            
            $new_source_ids[] = $source_id;
        }

        return $this->post_id;

    }
    
    private function get_new_track_url(){
        global $wp;

        $args = array(
            WPSSTM_Core_Tracks::$qvar_track_action =>   'new-track',
            'track' =>                              urlencode( json_encode($this->to_ajax()) ),
        );
        
        $url = get_post_type_archive_link( wpsstm()->post_type_track ); //'tracks' archive
        $url = add_query_arg($args,$url);
        return $url;
    }

    function get_track_action_url($track_action = null){
        
        $args = array(WPSSTM_Core_Tracks::$qvar_track_action=>$track_action);

        if ($this->post_id){
            $url = get_permalink($this->post_id);
            $url = add_query_arg($args,$url);
        }else{
            $url = $this->get_new_track_url();
            $url = add_query_arg(array('wpsstm-redirect'=>$args),$url);
        }

        return $url;
    }
    
    function get_track_admin_url($tab = null){
        $args = array(WPSSTM_Core_Tracks::$qvar_track_admin=>$tab);

        if ($this->post_id){
            $url = get_permalink($this->post_id);
            $url = add_query_arg($args,$url);
        }else{
            $url = $this->get_new_track_url();
            $url = add_query_arg(array('wpsstm-redirect'=>$args),$url);
        }

        return $url;
    }
    
    function get_track_links($tracklist,$context = null){
        
        $actions = array();

        /*
        Tracklist
        //TO FIX this should be reworked. Either tracks should have a ->in_playlist_id property, either filter the track actions, either have a tracklist_track_links() fn instead of this one, something like that.
        */
        $tracklist_id = $tracklist->post_id;
        $post_type_playlist =       $tracklist_id ? get_post_type($tracklist_id) : null;
        $tracklist_post_type_obj =  $post_type_playlist ? get_post_type_object($post_type_playlist) : null;
        $can_edit_tracklist =       ( $tracklist_post_type_obj && current_user_can($tracklist_post_type_obj->cap->edit_post,$tracklist_id) );
        $can_move_track =           ( $can_edit_tracklist && $tracklist_id && ($tracklist->tracklist_type == 'static') ); //TO FIX if tracklist has tracks
        $can_remove_track =         ( $can_edit_tracklist && $tracklist_id && ($tracklist->tracklist_type == 'static') );
        
        /*
        Track
        */
        $track_type_obj =           get_post_type_object(wpsstm()->post_type_track);
        $can_track_details =        ($this->title && $this->artist);
        $can_edit_track =           current_user_can($track_type_obj->cap->edit_post,$this->post_id);
        $can_delete_tracks =        current_user_can($track_type_obj->cap->delete_posts);
        $can_favorite_track =       true;//call to action
        $can_playlists_manager =    true;//call to action

        //share
        /*
        //TO FIX in playlist, etc.
        $actions['share'] = array(
            'text' =>       __('Share', 'wpsstm'),
            'href' =>       $this->get_track_action_url('share'),
        );
        */

        //favorite
        if ($can_favorite_track){
            $actions['favorite'] = array(
                'text' =>      __('Favorite','wpsstm'),
                'href' =>       $this->get_track_action_url('favorite'),
                'desc' =>       __('Add to favorites','wpsstm'),
            );
        }

        //unfavorite
        if ($can_favorite_track){
            $actions['unfavorite'] = array(
                'text' =>      __('Unfavorite','wpsstm'),
                'href' =>       $this->get_track_action_url('unfavorite'),
                'desc' =>       __('Remove track from favorites','wpsstm'),
            );
        }
        
        //track details
        if ($can_track_details){
            $actions['about'] = array(
                'text' =>      __('About', 'wpsstm'),
                'href' =>       $this->get_track_admin_url('about'),
                'classes' =>    array('wpsstm-link-popup'),
            );
        }

        //track edit
        if ($can_edit_track){
            $actions['edit'] = array(
                'text' =>      __('Track Details', 'wpsstm'),
                'href' =>       $this->get_track_admin_url('edit'),
                'classes' =>    array('wpsstm-link-popup'),
            );
        }

        //playlists manager
        if ($can_playlists_manager){
            $actions['playlists'] = array(
                'text' =>      __('Playlists manager','wpsstm'),
                'href' =>       $this->get_track_admin_url('playlists'),
                'classes' =>    array('wpsstm-link-popup'),
            );
        }

        if ($can_move_track){
            $actions['move'] = array(
                'text' =>      __('Move', 'wpsstm'),
                'desc' =>       __('Drag to move track in tracklist', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
            );
        }

        //sources manager
        if ($can_edit_track){
            $actions['sources'] = array(
                'text' =>       __('Sources manager','wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Sources manager','wpsstm'),
                'href' =>       $this->get_track_admin_url('sources-manager'),
            );
        }

        //delete track
        if ($can_delete_tracks){
            $actions['trash'] = array(
                'text' =>      __('Trash'),
                'classes' =>    array('wpsstm-advanced-action'),
                'desc' =>       __('Trash this track','wpsstm'),
                'href' =>       $this->get_track_action_url('trash'),
            );
        }
        
        //backend
        if ($can_edit_track){
            $actions['edit-backend'] = array(
                'text' =>      __('Edit backend', 'wpsstm'),
                'classes' =>    array('wpsstm-advanced-action'),
                'href' =>       get_edit_post_link( $this->post_id ),
            );
        }
        
        //context
        switch($context){
            case 'page':
                unset($actions['edit'],$actions['sources'],$actions['trash'],$actions['edit-backend']);
            break;
        }
        
        return apply_filters('wpsstm_track_actions',$actions,$context);

    }
    
    function get_track_attr($args=array()){
        global $wpsstm_tracklist;

        $attr = array(
            'itemscope' =>                      true,
            'itemtype' =>                       "http://schema.org/MusicRecording",
            'itemprop' =>                       'track',
            'data-wpsstm-track-id' =>           $this->post_id,
            'data-wpsstm-sources-count' =>      $this->source_count,
        );
        
        if ($wpsstm_tracklist){
            $attr['data-wpsstm-track-idx'] = $wpsstm_tracklist->current_track;
        }

        return wpsstm_get_html_attr($attr);
    }
    
    function get_track_class(){

        $classes = array(
            'wpsstm-track',
        );
        
        $classes[] = is_wp_error( $this->validate_track() ) ? 'wpsstm-invalid-track' : null;
        $classes[] = ( $this->is_track_loved_by() ) ? 'wpsstm-loved-track' : null;

        $classes = apply_filters('wpsstm_track_classes',$classes,$this);
        return array_filter(array_unique($classes));
    }
    
    /*
    $input_sources = array of sources objects or array of source IDs
    */
    
    function add_sources($input_sources){
        
        $add_sources = array();

        //force array
        if ( !is_array($input_sources) ) $input_sources = array($input_sources);

        foreach ($input_sources as $source){

            if ( !is_a($source, 'WPSSTM_Source') ){
                
                if ( is_array($source) ){
                    $source_args = $source;
                    $source = new WPSSTM_Source();
                    $source->from_array($source_args);
                }else{ //source ID
                    $source_id = $source;
                    //TO FIX check for int ?
                    $source = new WPSSTM_Source($source_id);
                }
            }
            
            $source->track_id = $this->post_id;
            $add_sources[] = $source;
        }

        //allow users to alter the input sources.
        $add_sources = apply_filters('wpsstm_input_sources',$add_sources,$this);
        
        $this->sources = $add_sources; //$this->sources = $this->validate_sources($add_sources);
        $this->source_count = count($this->sources);
        
        return $add_sources;
    }
    
    /**
	 * Set up the next source and iterate current source index.
	 * @return WP_Post Next source.
	 */
	public function next_source() {

		$this->current_source++;

		$this->source = $this->sources[$this->current_source];
		return $this->source;
	}

	/**
	 * Sets up the current source.
	 * Retrieves the next source, sets up the source, sets the 'in the loop'
	 * property to true.
	 * @global WP_Post $wpsstm_source
	 */
	public function the_source() {
		global $wpsstm_source;
		$this->in_source_loop = true;

		if ( $this->current_source == -1 ) // loop has just started
			/**
			 * Fires once the loop is started.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_sources_loop_start', array( &$this ) );

		$wpsstm_source = $this->next_source();
		//$this->setup_sourcedata( $wpsstm_source );
	}

	/**
	 * Determines whether there are more sources available in the loop.
	 * Calls the {@see 'wpsstm_sources_loop_end'} action when the loop is complete.
	 * @return bool True if sources are available, false if end of loop.
	 */
	public function have_sources() {
		if ( $this->current_source + 1 < $this->source_count ) {
			return true;
		} elseif ( $this->current_source + 1 == $this->source_count && $this->source_count > 0 ) {
			/**
			 * Fires once the loop has ended.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_Query &$this The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( 'wpsstm_sources_loop_end', array( &$this ) );
			// Do some cleaning up after the loop
			$this->rewind_sources();
		}

		$this->in_source_loop = false;
		return false;
	}
    


	/**
	 * Rewind the sources and reset source index.
	 * @access public
	 */
	public function rewind_sources() {
		$this->current_source = -1;
		if ( $this->source_count > 0 ) {
			$this->source = $this->sources[0];
		}
	}
    
    function user_can_reorder_sources(){
        $track_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = $track_type_obj->cap->edit_posts;
        $can_edit_track = current_user_can($required_cap,$this->post_id);

        $source_type_obj = get_post_type_object(wpsstm()->post_type_source);
        $can_edit_sources = current_user_can($source_type_obj->cap->edit_posts);
        
        return ($can_edit_track && $can_edit_sources);
    }
    
    function get_backend_sources_url(){
        $sources_url = admin_url('edit.php');
        $sources_url = add_query_arg( 
            array(
                'post_type'     => wpsstm()->post_type_source,
                'post_parent'   => $this->post_id,
                //'post_status' => 'publish'
            ),$sources_url 
        );
        return $sources_url;
    }

    function get_subtrack_playlist_manager_list(){
        global $tracklist_manager_query;
        
        //handle checkbox
        add_filter('wpsstm_before_tracklist_row',array($this,'playlists_manager_append_track_checkbox'));
        
        ob_start();
        //get logged user static playlists
        $args = array(
            'post_type' =>      wpsstm()->post_type_playlist,
            'author' =>         get_current_user_id(), //TOFIX TO CHECK WHAT IF NOT LOGGED ?
            'post_status' =>    array('publish','private','future','pending','draft'),
            'posts_per_page' => -1,
            'orderby' =>        'title',
            'order'=>           'ASC'
        );

        $tracklist_manager_query = new WP_Query( $args );
        wpsstm_locate_template( 'list-tracklists.php', true, false );
        $output = ob_get_clean();
        return $output;
    }
    
    /*
    Add a checkbox in front of every tracklist row to append/remove track
    */
    function playlists_manager_append_track_checkbox($tracklist){
        $checked_playlist_ids = $this->get_parent_ids();

        ?>
        <span class="tracklist-row-action">
            <?php
            //checked
            $checked = in_array($tracklist->post_id,$checked_playlist_ids);
            $checked_str = checked($checked,true,false);
            printf('<input name="wpsstm_playlist_id" type="checkbox" value="%s" %s />',$tracklist->post_id,$checked_str);
            ?>
        </span>
        <?php
    }

    /*
    Check that a track can be flushed; which means it is a community post that does not belong to any playlist or user's likes
    */
    
    function can_be_flushed(){
        $parent_ids = (array)$this->get_parent_ids();
        $loved_by = $this->get_track_loved_by();
        $can_be_flushed = ( wpsstm_is_community_post($this->post_id) && empty($parent_ids) && empty($loved_by) );
        
        return apply_filters('wpsstm_track_can_be_flushed',$can_be_flushed,$this);
    }
    
    function update_sources_order($source_ids){
        global $wpdb;
        
        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __("Missing track ID.",'wpsstm') );
        }

        if ( !$this->user_can_reorder_sources() ){
            return new WP_Error( 'wpsstm_missing_cap', __("You don't have the capability required to reorder sources.",'wpsstm') );
        }

        foreach((array)$source_ids as $order=>$post_id){
            
            $wpdb->update( 
                $wpdb->posts, //table
                array('menu_order'=>$order), //data
                array('ID'=>$post_id) //where
            );

        }

        return true;
    }
    
}