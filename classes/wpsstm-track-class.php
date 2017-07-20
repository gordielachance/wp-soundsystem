<?php

class WP_SoundSystem_Track{
    public $post_id = 0;
    public $position = -1; //order in the playlist, if any //TO FIX this property should not exist. Order is related to the tracklist, not to the track ?

    public $title;
    public $artist;
    public $album;
    public $duration; //in seconds
    public $mbid = null; //set 'null' so we can check later (by setting it to false) if it has been requested
    
    public $image;
    public $location;

    public $sources = null; //set 'null' so we can check later (by setting it to false) it has been populated
    public $did_post_id_lookup = false;

    function __construct( $args = array() ){
        
        //wpsstm()->debug_log(json_encode($args), "WP_SoundSystem_Track::__construct()"); 
        
        $args_default = $this->get_default();
        $args = wp_parse_args($args,$args_default);

        //set properties from args input
        foreach ($args as $key=>$value){
            if ( !array_key_exists($key,$args_default) ) continue;
            if ( !isset($args[$key]) ) continue; //value has not been set
            $this->$key = $args[$key];
        }
        
        //has track ID
        if ( $this->post_id ){
            $this->populate_track_post($this->post_id);
        }else{
            //populate post ID if track already exists in the DB
            //TO FIX check if this doesn't slow the page rendering
            $this->populate_track_post_auto();
        }
    }
    
    /*
    Get the post ID for this track, if it already exists in the database; and populate its data
    */
    
    function populate_track_post_auto(){
        if ( $this->post_id || $this->did_post_id_lookup || (!$this->artist || !$this->title) ) return;
            
        wpsstm()->debug_log(json_encode(array('track'=>sprintf('%s - %s - %s',$this->artist,$this->title,$this->album)),JSON_UNESCAPED_UNICODE),'WP_SoundSystem_Track::populate_track_post_auto()');

        if ( $auto_post_id = wpsstm_get_post_id_by('track',$this->artist,$this->album,$this->title) ){
            $this->populate_track_post($auto_post_id);
        }
        $this->did_post_id_lookup = true;
        return $this->post_id;

    }
    
    function populate_track_post($post_id){
        
        if ( !$post = get_post($post_id) ){
            $this->post_id = null; //if it was set but the post do not exists
            return;
        }
        
        $this->post_id = $post_id;
        
        //populate datas if they are not set yet (eg. if we save a track, we could have set the values for track update)
        
        if (!$this->title)      $this->title = wpsstm_get_post_track($post_id);
        if (!$this->artist)     $this->artist = wpsstm_get_post_artist($post_id);
        if (!$this->album)      $this->album = wpsstm_get_post_album($post_id);
        if (!$this->mbid)       $this->mbid = wpsstm_get_post_mbid($post_id);
        if (!$this->sources)    $this->sources = wpsstm_get_post_sources($post_id);
    }
    
    function get_default(){
        return array(
            'post_id'       =>null,
            'title'         =>null,
            'artist'        =>null,
            'album'         =>null,
            'image'         =>null,
            'location'      =>null,
            'mbid'          =>null,
            'duration'      =>null,
            'sources'       =>null
        );
    }
    
    /*
    Get IDs of the parent tracklists (albums / playlists) for a subtrack.
    */
    function get_parent_ids($args = null){
        global $wpdb;
        
        //track ID is required
        if ( !$this->post_id && !$this->populate_track_post_auto() ) return;//track does not exists in DB

        $meta_query = array();
        $meta_query[] = array(
            'key'     => 'wpsstm_subtrack_ids',
            'value'   => serialize( $this->post_id ), //https://wordpress.stackexchange.com/a/224987/70449
            'compare' => 'LIKE'
        );

        $default_args = array(
            'post_type'         => array(wpsstm()->post_type_album,wpsstm()->post_type_playlist,wpsstm()->post_type_live_playlist),
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids',
            'meta_query'        => $meta_query
        );

        $args = wp_parse_args((array)$args,$default_args);
        
        //wpsstm()->debug_log($args,'WP_SoundSystem_Track::get_parent_ids()');

        $query = new WP_Query( $args );
        $ids = $query->posts;
        return $ids;
    }

    //TO FIX do we need this ?
    function array_export(){
        $defaults = $this->get_default();
        $export = array();
        foreach ((array)$defaults as $param=>$dummy){
            $export[$param] = $this->$param;
        }
        return array_filter($export);
    }
    
    /**
    http://www.xspf.org/xspf-v1.html#rfc.section.4.1.1.2.14.1.1
    */
    
    function get_array_for_xspf(){
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
            //keep only tracks having artist AND title
            $valid = ($this->artist && $this->title);
            //artists & title would probably not be equal
            if ( $valid && ($this->artist == $this->title) ) {
                $valid = false;
            }
        }else{
            //keep only tracks having artist OR title (Wizard)
            $valid = ($this->artist || $this->title);
        }

        return $valid;

    }
    
    function save_temp_track(){
        $post_id = null;
        
        $meta_input = array(
            wpsstm_artists()->artist_metakey    => $this->artist,
            wpsstm_tracks()->title_metakey      => $this->title,
            wpsstm_albums()->album_metakey      => $this->album,
            wpsstm_mb()->mbid_metakey           => $this->mbid,
            //sources is more specific, will be saved below
        );

        $meta_input = array_filter($meta_input);

        $post_track_args = array('meta_input' => $meta_input);
        
        $post_track_new_args = array(
            'post_type'     => wpsstm()->post_type_track,
            'post_status'   => wpsstm()->temp_status,
            'post_author'   => get_current_user_id(), //TO FIX guest if user is not logged ?
        );

        $post_track_new_args = wp_parse_args($post_track_new_args,$post_track_args);

        $post_id = wp_insert_post( $post_track_new_args );
        wpsstm()->debug_log( array('post_id'=>$post_id,'args'=>json_encode($post_track_new_args)), "WP_SoundSystem_Track::save_temp_track()"); 
        
        if ( is_wp_error($post_id) ) return $post_id;
        
        $this->post_id = $post_id;
        return $this->post_id;
        
    }

    function save_track(){
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = $post_type_obj->cap->edit_posts;

        if ( !current_user_can($required_cap) ){
            return new WP_Error( 'wpsstm_track_cap_missing', __("You don't have the capability required to create a new track.",'wpsstm') );
        }
        
        if ( !$this->validate_track() ) return;

        $post_id = null;
        
        $meta_input = array(
            wpsstm_artists()->artist_metakey           => $this->artist,
            wpsstm_tracks()->title_metakey            => $this->title,
            wpsstm_albums()->album_metakey            => $this->album,
            wpsstm_mb()->mbid_metakey        => $this->mbid,
            //sources is more specific, will be saved below
        );

        $meta_input = array_filter($meta_input);

        $post_track_args = array('meta_input' => $meta_input);
        
        //check if this track already exists
        if (!$this->post_id){
            $this->populate_track_post_auto();
        }

        if (!$this->post_id){ //not a track update

            $post_track_new_args = array(
                'post_type'     => wpsstm()->post_type_track,
                'post_status'   => 'publish',
                'post_author'   => get_current_user_id()
            );

            $post_track_new_args = wp_parse_args($post_track_new_args,$post_track_args);

            $post_id = wp_insert_post( $post_track_new_args );
            wpsstm()->debug_log( array('post_id'=>$post_id,'args'=>json_encode($post_track_new_args)), "WP_SoundSystem_Track::save_track() - post track inserted"); 

        }else{ //is a track update
            
            $post_track_update_args = array(
                'ID' =>             $this->post_id,
                'post_status' =>    'publish', //previous status may be the temporary one so be sure we publish it here.
            );
            
            $post_track_update_args = wp_parse_args($post_track_update_args,$post_track_args);
            
            $post_id = wp_update_post( $post_track_update_args );
            
            wpsstm()->debug_log( array('post_id'=>$post_id,'args'=>json_encode($post_track_update_args)), "WP_SoundSystem_Track::save_track() - post track updated"); 
        }

        if ( is_wp_error($post_id) ) return $post_id;

        //sources is quite specific
        $this->update_track_sources($this->sources);
        
        $this->post_id = $post_id;

        return $this->post_id;
        
    }
    
    function delete_track($force_delete = false){
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = $post_type_obj->cap->delete_posts;

        if ( !current_user_can($required_cap) ){
            return new WP_Error( 'wpsstm_track_cap_missing', __("You don't have the capability required to create a new track.",'wpsstm') );
        }
        
        wpsstm()->debug_log( array('post_id',$this->post_id,'force_delete'=>$force_delete), "WP_SoundSystem_Track::delete_track()"); 
        
        return wp_delete_post( $this->post_id, $force_delete );
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
        $api_type = wpsstm_tracks()->track_mbtype;
        $api_response = wpsstm_mb()->get_musicbrainz_api_entry($api_type,null,$mzb_args);

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
    
    //useful eg. for transients
    function get_unique_id($prefix = null){
        $title = sanitize_title($prefix . $this->artist . $this->title . $this->album);
        return md5( $title );
    }
    
    function love_track($do_love){
        
        if ( !$this->artist || !$this->title ) return new WP_Error('missing_love_track_data',__("Required track information missing",'wpsstm'));
        if ( !$user_id = get_current_user_id() ) return new WP_Error('no_user_id',__("User is not logged",'wpsstm'));
        
        if ( !$this->post_id && !$this->populate_track_post_auto() ){//track does not exists in DB
            $created = $this->save_track();
            if ( is_wp_error($created) ) return $created;
        }
            
        if ( !$this->post_id ){
            return new WP_Error('no_track_id',__("This track does not exists in the database",'wpsstm'));
        }
        
        //set post status to 'publish' if it is not done yet (it could be a temporary post)
        $track_post_type = get_post_status($this->post_id);
        if ($track_post_type != 'publish'){
            wp_update_post(array(
                'ID' =>             $this->post_id,
                'post_status' =>    'publish'
            ));
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
            return update_post_meta( $this->post_id, wpsstm_tracks()->favorited_track_meta_key, $user_id );
        }else{
            return delete_post_meta( $this->post_id, wpsstm_tracks()->favorited_track_meta_key, $user_id );
        }
        
    }
    
    function get_track_loved_by(){
        //track ID is required
        if ( !$this->post_id && !$this->populate_track_post_auto() ) return;//track does not exists in DB
        
        return get_post_meta($this->post_id, wpsstm_tracks()->favorited_track_meta_key);
    }
    
    function is_track_loved_by($user_id = null){
        if (!$user_id) $user_id = get_current_user_id();
        if (!$user_id) return;
        
        $loved_by = $this->get_track_loved_by();
        return in_array($user_id,(array)$loved_by);
    }
    
    function populate_track_sources_auto( $args = null ){

        if (!$this->artist || !$this->title) return;

        $sources = array();

        foreach( (array)wpsstm_player()->providers as $provider ){
            if ( !$provider_sources = $provider->sources_lookup( $this,$args ) ) continue; //cannot play source

            $sources = array_merge($sources,(array)$provider_sources);
            
        }

        //allow plugins to filter this
        $sources = apply_filters('wpsstm_populate_track_sources_auto',$sources,$this,$args);
        
        $sources = $this->sanitize_track_sources($sources);
        
        if ( wpsstm()->get_options('autosource_filter_ban_words') == 'on' ){
            $sources = $this->autosource_filter_ban_words($sources);
        }
        if ( wpsstm()->get_options('autosource_filter_requires_artist') == 'on' ){
            $sources = $this->autosource_filter_title_requires_artist($sources);
        }
        
        $this->sources = $sources;
        return $sources;

    }
    
    /*
    Exclude sources that have one word of the banned words list in their titles (eg 'cover').
    */
    
    function autosource_filter_ban_words($sources){
        
        $ban_words = wpsstm()->get_options('autosource_filter_ban_words');

        foreach((array)$ban_words as $word){
            
            //this track HAS the word in its title; (the cover IS a cover), abord
            $ignore_this_word = stripos($this->title, $word);//case insensitive
            if ( $ignore_this_word ) continue;
            
            //check sources for the word
            foreach((array)$sources as $key=>$source){
                $source_has_word = stripos($source['title'], $word);//case insensitive
                if ( !$source_has_word ) continue;
                unset($source[$key]);
            }
        }

        return $sources;
    }
    
    /*
    Remove sources where that the track artist is not contained in the source title
    https://stackoverflow.com/questions/44791191/how-to-use-similar-text-in-a-difficult-context
    */
    
    function autosource_filter_title_requires_artist($sources){

        foreach((array)$sources as $key=>$source){
            
            /*TO FIX check if it works when artist has special characters like / or &
            What if the artist is written a little differently ?
            We should compare text somehow here and accept a certain percent match.
            */
            
            $remove_sources = array();
            
            //sanitize data so it is easier to compare
            $source_sanitized = sanitize_title($source['title']);
            $artist_sanitized = sanitize_title($this->artist);

            if (strpos($source_sanitized, $artist_sanitized) === false) {
                wpsstm()->debug_log( json_encode( array('artist'=>$this->artist,'artist_sanitized'=>$artist_sanitized,'title'=>$this->title,'source_title'=>$source['title'],'source_title_sanitized'=>$source_sanitized),JSON_UNESCAPED_UNICODE ), "WP_SoundSystem_Track::autosource_filter_title_requires_artist() - source ignored as artist is not contained in its title");
                unset($sources[$key]);
            }
        }

        return $sources;

    }
    
    function sort_sources_by_similarity($sources){
        
        $artist_sanitized = sanitize_title($this->artist);
        $title_sanitized = sanitize_title($this->title);
        
        //compute similarity
        foreach((array)$sources as $key=>$source){
            
            //sanitize data so it is easier to compare
            $source_title_sanitized = sanitize_title($source->title);
            
            //remove artist from source title so the string to compare is shorter
            $source_title_sanitized = str_replace($artist_sanitized,"", $source_title_sanitized); 
            
            similar_text($source_title_sanitized, $title_sanitized, $similarity_pc);
            $sources[$key]->similarity = $similarity_pc;
        }
        
        //reorder by similarity
        usort($sources, function ($a, $b){
            return $a->similarity === $b->similarity ? 0 : ($a->similarity > $b->similarity ? -1 : 1);
        });
        
        return $sources;
    }
    
    function sanitize_track_sources($sources){
        
        if ( empty($sources) ) return;

        foreach((array)$sources as $key=>$source){
            //url is not valid
            if ( !$source['url'] || ( !filter_var($source['url'], FILTER_VALIDATE_URL) ) ) unset($sources[$key]); 
        }

        foreach((array)$sources as $key=>$source){
            $source = wp_parse_args($source,WP_SoundSystem_Source::$defaults);
            $source[$key] = array_filter($source);
        }

        $sources = array_unique($sources, SORT_REGULAR);
        $sources = wpsstm_array_unique_by_subkey($sources,'url');

        return $sources;
    }

    function update_track_sources($sources){
        
        if (!$this->artist || !$this->title) return;

        $sources = $this->sanitize_track_sources($sources);

        if (!$sources){
            return delete_post_meta( $this->post_id, wpsstm_tracks()->sources_metakey );
        }else{
            return update_post_meta( $this->post_id, wpsstm_tracks()->sources_metakey, $sources );
        }
    }
    
    function get_new_track_url(){
        $url = get_post_type_archive_link(wpsstm()->post_type_track);
        
        $args = array(
            wpsstm_tracks()->qvar_new_track =>  true
        );
        
        $track_args = array(
            'track_artist' =>     urlencode($this->artist),
            'track_title' =>      urlencode($this->title),
            'track_album' =>      urlencode($this->album)
        );
        
        $args = array_merge($args,$track_args);
        $args = array_filter($args);
        
        return add_query_arg($args,$url);
    }

    function get_track_admin_gui_url($track_action = null,$tracklist_id = null){
        
        $url = null;
        
        if ($this->post_id){ //track already exists
            
            $url = get_permalink($this->post_id);
            $url = add_query_arg(array(wpsstm_tracklists()->qvar_tracklist_admin=>$track_action),$url);
            
        }else{ //new track
            $url = $this->get_new_track_url();
        }
        return $url;
    }
    
    function get_track_row_actions($tracklist_id = null){
        
        $actions = $this->get_track_actions($tracklist_id = null);
        $popup_slugs = array('details','edit','playlists','sources','delete');
        
        foreach((array)$actions as $slug=>$action){
            if ( !in_array($slug,$popup_slugs) ) continue;
            
            if ( $action['tab_id'] ){
                $action['href'] .= sprintf('#%s',$action['tab_id']);
            }
            
            $action['link_classes'][] = 'thickbox';
            $action['href'] = add_query_arg(array('TB_iframe'=>true),$action['href']);
            $actions[$slug] = $action;
        }
        return $actions;
    }
    
    function get_track_popup_actions($tracklist_id = null){
        $actions = $this->get_track_actions($tracklist_id = null);
        
        foreach((array)$actions as $slug=>$action){
            
            if ( $action['tab_id'] ){
                $action['href'] = sprintf('#%s',$action['tab_id']);
            }
            
            $actions[$slug] = $action;
        }

        return $actions;
    }
    
    function get_track_actions($tracklist_id = null){
        
        /*
        Capability check
        */
        
       //tracklist
        $post_type_playlist = null;
        $can_edit_tracklist = false;
        if ($tracklist_id){
            $post_type_playlist =   get_post_type($tracklist_id);
            $tracklist_obj =        get_post_type_object($post_type_playlist);
        }
        $can_edit_tracklist =   ( $tracklist_obj && current_user_can($tracklist_obj->cap->edit_post,$tracklist_id) );

        $track_type_obj =           get_post_type_object(wpsstm()->post_type_track);
        $can_track_details =        ($this->title && $this->artist);
        $can_edit_track =           current_user_can($track_type_obj->cap->edit_post,$this->post_id);
        $can_delete_tracks =        current_user_can($post_type_obj->cap->delete_posts);
        $can_favorite_track =       true;//call to action
        $can_playlists_manager =    true;//call to action
        $can_move_track =           ( $can_edit_tracklist && ( $post_type_playlist==wpsstm()->post_type_playlist ) );
        $can_remove_track =         ( $can_edit_tracklist && ( $post_type_playlist==wpsstm()->post_type_playlist ) );

        $track_actions = array();

        //track details
        if ($can_track_details){
            $track_actions['details'] = array(
                'icon' =>       '<i class="fa fa-address-card-o" aria-hidden="true"></i>',
                'title' =>      __('Track infos', 'wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('details',$tracklist_id),
                'tab_id' =>      'tab-content-details',
            );
        }

        //track edit
        if ($can_edit_track){
            $track_actions['edit'] = array(
                'icon' =>       '<i class="fa fa-address-card-o" aria-hidden="true"></i>',
                'title' =>      __('Track details', 'wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('edit',$tracklist_id),
                'tab_id' =>      'tab-content-edit',
            );
        }

        //playlists manager
        if ($can_playlists_manager){
            $track_actions['playlists'] = array(
                'icon' =>       '<i class="fa fa-list" aria-hidden="true"></i>',
                'title' =>      __('Playlists manager','wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('playlists',$tracklist_id),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-track-action'),
                'tab_id' =>      'tab-content-playlists',
            );
        }

        //favorite
        if ($can_favorite_track){
            $track_actions['favorite'] = array(
                'icon'=>        '<i class="fa fa-heart-o" aria-hidden="true"></i>',
                'title' =>      __('Favorite','wpsstm'),
                'desc' =>       __('Add track to favorites','wpsstm'),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-track-action','wpsstm-action-toggle-favorite'),
            );
            if ( !$this->is_track_loved_by() ) $track_actions['favorite']['classes'][] = 'wpsstm-toggle-favorite-active';
        }

        //unfavorite
        if ($can_favorite_track){
            $track_actions['unfavorite'] = array(
                'icon'=>        '<i class="fa fa-heart" aria-hidden="true"></i>',
                'title' =>      __('Unfavorite','wpsstm'),
                'desc' =>       __('Remove track from favorites','wpsstm'),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-track-action','wpsstm-action-toggle-favorite'),
            );
            if ( $this->is_track_loved_by() ) $track_actions['unfavorite']['classes'][] = 'wpsstm-toggle-favorite-active';
        }
        
        //(playlist) track move
        if ($can_move_track){
            $track_actions['move'] = array(
                'icon' =>       '<i class="fa fa-arrows-v" aria-hidden="true"></i>',
                'title' =>      __('Move', 'wpsstm'),
                'desc' =>       __('Drag to move track in tracklist', 'wpsstm'),
            );
        }

        //(playlist) track remove
        if ($can_remove_track){
            $track_actions['remove'] = array(
                'icon' =>       '<i class="fa fa-chain-broken" aria-hidden="true"></i>',
                'title' =>      __('Remove', 'wpsstm'),
                'desc' =>       __('Remove from tracklist', 'wpsstm'),
            );
        }
        
        //sources manager
        if ($can_edit_track){
            $track_actions['sources'] = array(
                'icon' =>       '<i class="fa fa-cloud" aria-hidden="true"></i>',
                'title' =>      __('Sources','wpsstm'),
                'desc' =>       __('Sources manager','wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('sources',$tracklist_id),
                'tab_id' =>      'tab-content-sources',
            );
        }

        //delete track
        if ($can_edit_track){
            $track_actions['delete'] = array(
                'icon' =>       '<i class="fa fa-trash" aria-hidden="true"></i>',
                'title' =>      __('Delete'),
                'desc' =>       __('Delete track','wpsstm'),
                'tab_id' =>      'tab-content-delete',
            );
        }
        
        return apply_filters('wpsstm_track_actions',$track_actions);
    }

    function track_admin_details(){
        
        //artist
        $artist_input_attr = array(
            'name'  => 'wpsstm_track_artist',
            'value' => ($this->artist) ? $this->artist : null,
            'class' => 'wpsstm-fullwidth'
        );
        $artist_input = sprintf('<input %s />',wpsstm_get_html_attr($artist_input_attr));
        $artist_el = sprintf('<div id="track-admin-artist"><h3>%s</h3>%s</div>',__('Artist','wpsstm'),$artist_input);
        
        //title
        $title_input_attr = array(
            'name'  => 'wpsstm_track_title',
            'value' => ($this->title) ? $this->title : null,
            'class' => 'wpsstm-fullwidth'
        );
        $title_input = sprintf('<input %s />',wpsstm_get_html_attr($title_input_attr));
        $title_el = sprintf('<div id="track-admin-title"><h3>%s</h3>%s</div>',__('Title'),$title_input);
        
        //album
        $album_input_attr = array(
            'name'  => 'wpsstm_track_album',
            'value' => ($this->album) ? $this->album : null,
            'class' => 'wpsstm-fullwidth'
        );
        $album_input = sprintf('<input %s />',wpsstm_get_html_attr($album_input_attr));
        $album_el = sprintf('<div id="track-admin-album"><h3>%s</h3>%s</div>',__('Album','wpsstm'),$album_input);
        
        //mbid
        $mbid_input_attr = array(
            'name'  => 'wpsstm_track_mbid',
            'value' => ($this->mbid) ? $this->mbid : null,
            'class' => 'wpsstm-fullwidth'
        );
        $mbid_input = sprintf('<input %s />',wpsstm_get_html_attr($mbid_input_attr));
        $mbid_el = sprintf('<div id="track-admin-mbid"><h3>%s</h3>%s</div>',__('Musicbrainz ID','wpsstm'),$mbid_input);
        
        $submit_bt_el = sprintf('<input id="wpsstm-update-track-bt" type="submit" value="%s" />',__('Save'));
        $submit_block = sprintf('<p class="wpsstm-submit-wrapper">%s</p>',$submit_bt_el);
        
        $form_content = $artist_el.$title_el.$album_el.$mbid_el.$submit_block;
        //
        $popup_action = 'edit';
        $popup_action_el = sprintf('<input type="hidden" name="wpsstm-admin-track-action" value="%s">',$popup_action);
        $form_action = $this->get_track_admin_gui_url($popup_action);
        $nonce_el = wp_nonce_field( 'wpsstm_admin_track_gui_details_'.$this->post_id, 'wpsstm_admin_track_gui_details_nonce', true, false );
        return sprintf('<form action="%s" method="post">%s%s%s</form>',$form_action,$form_content,$nonce_el,$popup_action_el);

    }
    
    function track_admin_playlists(){
        
        if ( !get_current_user_id() ){
            $wp_auth_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
            $wp_auth_link = sprintf('<a href="%s">%s</a>',wp_login_url(),__('here','wpsstm'));
            $wp_auth_text = sprintf(__('This requires you to be logged.  You can login or subscribe %s.','wpsstm'),$wp_auth_link);
            return sprintf('<p class="wpsstm-notice">%s %s</p>',$wp_auth_icon,$wp_auth_text);
        }
        
        /*
        if ( !current_user_can($create_playlist_cap) ){
            $wp_cap_icon = '<i class="fa fa-wordpress" aria-hidden="true"></i>';
            $missing_cap_text = __("You don't have the capability required to create a new playlist.",'wpsstm');
            return sprintf('<p class="wpsstm-notice">%s %s</p>',$wp_cap_icon,$missing_cap_text); 
        }
        */

        $filter_playlists_input = sprintf('<p><input id="wpsstm-playlists-filter" type="text" placeholder="%s" /></p>',__('Type to filter playlists or to create a new one','wpsstm'));

        $list_all = wpsstm_get_user_playlists_list(array('checked_ids'=>$this->get_parent_ids()));
        
        $playlist_type_obj = get_post_type_object(wpsstm()->post_type_playlist);
        $labels = get_post_type_labels($playlist_type_obj);
        
        $submit = sprintf('<input type="submit" value="%s"/>',$labels->add_new_item);
        $nonce_el = wp_nonce_field( 'wpsstm_admin_track_gui_playlists_'.$this->post_id, 'wpsstm_admin_track_gui_playlists_nonce', true, false );
        $new_playlist_bt = sprintf('<p id="wpsstm-new-playlist-add">%s%s</p>',$submit,$nonce_el);
        
        $existing_playlists_wrapper = sprintf('<div id="wpsstm-filter-playlists"%s%s%s</div>',$filter_playlists_input,$list_all,$new_playlist_bt);

        return sprintf('<div id="wpsstm-tracklist-chooser-list" class="wpsstm-popup-content">%s</div>',$existing_playlists_wrapper);
    }

    function track_admin_sources(){
        $popup_action = 'sources';
        $sources = ($this->sources) ? $this->sources : array();

        $default = new WP_SoundSystem_Source();
        array_unshift($sources,$default); //add blank line
        $sources_inputs = wpsstm_sources()->get_sources_inputs($sources);

        $desc = array();
        $desc[]= __('Add sources to this track.  It could be a local audio file or a link to a music service.','wpsstm');
        
        $desc[]= __('Hover the provider icon to view the source title (when available).','wpsstm');
        
        $desc[]= __("If no sources are set and that the 'Auto-Source' setting is enabled, We'll try to find a source automatically when the tracklist is played.",'wpsstm');
        
        //wrap
        $desc = array_map(
           function ($el) {
              return "<p>{$el}</p>";
           },
           $desc
        );
        
        $desc = implode("\n",$desc);

        $suggest_bt = sprintf('<p class="wpsstm-submit-wrapper"><input id="wpsstm-suggest-sources-bt" type="submit" value="%s" /></p>',__('Suggest sources','wpsstm'));
        $sources_auto = sprintf('<div id="wpsstm-sources-edit-list-auto" class="wpsstm-sources-edit-list">%s</div>',$suggest_bt);
        
        $sources_user = sprintf('<div class="wpsstm-sources-edit-list-user wpsstm-sources-edit-list">%s</div>',$sources_inputs);

        $popup_action_el = sprintf('<input type="hidden" name="wpsstm-admin-track-action" value="%s">',$popup_action);
        $popup_track_id_el = sprintf('<input type="hidden" name="wpsstm-admin-track-id" value="%s">',$this->post_id);
        
        $submit_bt_el = sprintf('<input id="wpsstm-update-sources-bt" type="submit" value="%s" />',__('Save'));
        $nonce_el = wp_nonce_field( 'wpsstm_admin_track_gui_sources_'.$this->post_id, 'wpsstm_admin_track_gui_sources_nonce', true, false );
        $submit_block = sprintf('<p class="wpsstm-submit-wrapper">%s%s%s</p>',$popup_action_el.$popup_track_id_el,$submit_bt_el,$nonce_el);

        $form_action = $this->get_track_admin_gui_url($popup_action);

        return $desc.sprintf('<form action="%s" method="post">%s</form>',$form_action,$sources_user.$sources_auto.$submit_block);

    }
    
}