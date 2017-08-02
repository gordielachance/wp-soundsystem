<?php

class WP_SoundSystem_Track{
    public $post_id = null;
    public $position = -1; //order in the playlist, if any //TO FIX this property should not exist. Order is related to the tracklist, not to the track ?

    public $title;
    public $artist;
    public $album;
    public $duration; //in seconds
    public $mbid = null; //set 'null' so we can check later (by setting it to false) if it has been requested
    
    public $image;
    public $location;

    public $parent_ids = array();

    public $sources = null; //set 'null' so we can check later (by setting it to false) it has been populated
    private $did_post_id_lookup = false;
    private $did_parents_query = false;

    function __construct( $post_id = null ){
        //has track ID
        if ( $post_id && ($post = get_post($post_id) ) ){

            $this->post_id = $post_id;

            //populate datas if they are not set yet (eg. if we save a track, we could have set the values for track update)

            $this->title = wpsstm_get_post_track($post_id);
            $this->artist = wpsstm_get_post_artist($post_id);
            $this->album = wpsstm_get_post_album($post_id);
            $this->mbid = wpsstm_get_post_mbid($post_id);

        }

        
    }
    
    function from_array( $args = null ){

        $args_default = $this->get_default();
        $args = wp_parse_args((array)$args,$args_default);

        //set properties from args input
        foreach ($args as $key=>$value){
            if ( !array_key_exists($key,$args_default) ) continue;
            if ( !isset($args[$key]) ) continue; //value has not been set
            $this->$key = $args[$key];
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
            wpsstm_artists()->qvar_artist_lookup => $this->artist,
            wpsstm_tracks()->qvar_track_lookup =>   $this->title
        );

        if ($this->post_id){
            $query_args['post__not_in'] = array($this->post_id);
        }

        if ($this->album){
            $query_args[wpsstm_albums()->qvar_album_lookup] = $this->album;
        }

        $query = new WP_Query( $query_args );

        return $query->posts;
    }
    
    /*
    Get the post ID for this track, if it already exists in the database; and populate its data
    */
    
    function populate_track_post_auto(){
        if ( $this->post_id || $this->did_post_id_lookup || (!$this->artist || !$this->title) ) return;
            
        wpsstm()->debug_log(json_encode(array('track'=>sprintf('%s - %s - %s',$this->artist,$this->title,$this->album)),JSON_UNESCAPED_UNICODE),'WP_SoundSystem_Track::populate_track_post_auto()');

        if ( $duplicates = $this->get_track_duplicates() ){
            $this->__construct( $duplicates[0] );
        } 

        $this->did_post_id_lookup = true;
        return $this->post_id;

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
            'sources'       =>null,
        );
    }
    
    /*
    Get IDs of the parent tracklists (albums / playlists / live playlists) for a subtrack.
    $type = null/'static'/'live'
    */
    function get_parent_ids($type=null,$args = null){
        global $wpdb;
        
        if ($this->did_parents_query) return $this->parent_ids;
        
        //track ID is required
        if ( !$this->post_id ) return;//track does not exists in DB

        $default_args = array(
            'post_type'         => wpsstm_tracklists()->tracklist_post_types,
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids'
        );
        
        $args = wp_parse_args((array)$args,$default_args);

        $parents_meta_query = array(
            'relation' => 'OR',
        );
        
        if ( !$type || ($type=='static') ) {
            $parents_meta_query[] = array(
                'key'     => wpsstm_playlists()->subtracks_static_metaname,
                'value'   => serialize( $this->post_id ), //https://wordpress.stackexchange.com/a/224987/70449
                'compare' => 'LIKE'
            );
        }
        
        if ( !$type || ($type=='live') ) {
            $parents_meta_query[] = array(
                'key'     => wpsstm_live_playlists()->subtracks_live_metaname,
                'value'   => serialize( $this->post_id ), //https://wordpress.stackexchange.com/a/224987/70449
                'compare' => 'LIKE'
            );
        }
        
        $args['meta_query'][] = $parents_meta_query;

        //wpsstm()->debug_log($args,'WP_SoundSystem_Track::get_parent_ids()');

        $query = new WP_Query( $args );
        
        $this->parent_ids = $query->posts;
        $this->did_parents_query = true;
        
        return $this->parent_ids;
    }
    
    function get_parents_list($type = null){

        $tracklist_ids = $this->get_parent_ids($type);
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

    //TO FIX do we need this ?
    function to_array(){
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

    function save_track($args = null){
        
        if ( !$this->validate_track() ){
            return new WP_Error( 'wpsstm_cannot_validate_track', __("Error while validating the track.",'wpsstm') );
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
            return new WP_Error( 'wpsstm_track_cap_missing', __("You don't have the capability required to create a new track.",'wpsstm') );
        }
        
        $meta_input = array(
            wpsstm_artists()->artist_metakey    => $this->artist,
            wpsstm_tracks()->title_metakey      => $this->title,
            wpsstm_albums()->album_metakey      => $this->album,
            wpsstm_mb()->mbid_metakey           => $this->mbid,
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
            
            $post_id = wp_insert_post( $args );
            
        }else{ //is a track update
            
            $args['ID'] = $this->post_id;
            $post_id = wp_update_post( $args );
        }
        
        wpsstm()->debug_log( array('post_id'=>$post_id,'args'=>json_encode($args)), "WP_SoundSystem_Track::save_track()" ); 

        if ( is_wp_error($post_id) ) return $post_id;

        $this->post_id = $post_id;
        
        //save sources if any set
        foreach ((array)$this->sources as $source_raw){
            
            $source = new WP_SoundSystem_Source();
            $source->track_id = $this->post_id;
            
            $source->from_array($source_raw);
            if ( !$duplicate_ids = $source->get_source_duplicates() ){
                $this->add_community_source($source);
            }
            
        }

        return $this->post_id;
        
    }
    
    function trash_track(){
        
        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track);
        $required_cap = $post_type_obj->cap->delete_posts;

        if ( !current_user_can($required_cap) ){
            return new WP_Error( 'wpsstm_track_cap_missing', __("You don't have the capability required to create a new track.",'wpsstm') );
        }
        
        $success = wp_trash_post($this->post_id);
        
        wpsstm()->debug_log( array('post_id',$this->post_id,'success'=>$success), "WP_SoundSystem_Track::trash_track()"); 
        
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

    function love_track($do_love){
        
        if ( !$this->artist || !$this->title ) return new WP_Error('missing_love_track_data',__("Required track information missing",'wpsstm'));
        if ( !$user_id = get_current_user_id() ) return new WP_Error('no_user_id',__("User is not logged",'wpsstm'));
 
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
        if ( !$this->post_id  ) return;//track does not exists in DB
        
        return get_post_meta($this->post_id, wpsstm_tracks()->favorited_track_meta_key);
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
    
    function get_track_source_ids($args = null){
        $default_args = array(
            'post_status'=>     'any',
            'posts_per_page' => -1,
        );

        if (!$args){
            $args = $default_args;
        }else{
            $args = wp_parse_args($args,$default_args);
        }

        $required_args = array(
            'post_type' =>      wpsstm()->post_type_source,
            'post_parent' =>    $this->post_id,
            'fields'  =>        'ids',
        );

        $args = wp_parse_args($required_args,$args);

        $query = new WP_Query( $args );
        return $query->posts; //ids
    }
    
    function get_track_sources(){
        if ($this->sources === null) {
            $sources = array();
            $source_ids = $this->get_track_source_ids();
            foreach((array)$source_ids as $source_id){
                $sources[] = new WP_SoundSystem_Source($source_id);
            }
            $this->sources = $sources;
        }
        return $this->sources;
    }

    function save_auto_sources(){

        if (!$this->artist || !$this->title) return;

        $auto_sources = array();
        $new_source_ids = array();

        foreach( (array)wpsstm_player()->providers as $provider ){
            if ( !$provider_sources = $provider->sources_lookup( $this ) ) continue; //cannot play source

            $auto_sources = array_merge($auto_sources,(array)$provider_sources);
        }

        //allow plugins to filter this
        $auto_sources = apply_filters('wpsstm_get_track_sources_auto',$auto_sources,$this);

        if ( wpsstm()->get_options('autosource_filter_ban_words') == 'on' ){
            $auto_sources = $this->autosource_filter_ban_words($auto_sources);
        }
        if ( wpsstm()->get_options('autosource_filter_requires_artist') == 'on' ){
            $auto_sources = $this->autosource_filter_title_requires_artist($auto_sources);
        }

        foreach((array)$auto_sources as $source){
            
            $post_id = $this->add_community_source($source);
            
            if ( is_wp_error($post_id) ){
                $code = $post_id->get_error_code();
                $error_msg = $post_id->get_error_message($code);
                wpsstm()->debug_log( $error_msg, "WP_SoundSystem_Track::save_auto_sources() - unable to save source");
                continue;
            }
            
            $new_source_ids[] = $post_id;
        }
        
        //reload sources
        if ($new_source_ids){
            $this->sources = $this->get_track_sources();
        }

        return $new_source_ids;

    }
    
    function add_community_source($source){
        $args = array(
            'post_author'   => wpsstm()->get_options('community_user_id'),
            'post_status'   => 'publish',
        );

        return $source->save_source($args);
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
                $source_has_word = stripos($source->title, $word);//case insensitive
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
            $source_sanitized = sanitize_title($source->title);
            $artist_sanitized = sanitize_title($this->artist);

            if (strpos($source_sanitized, $artist_sanitized) === false) {
                wpsstm()->debug_log( json_encode( array('artist'=>$this->artist,'artist_sanitized'=>$artist_sanitized,'title'=>$this->title,'source_title'=>$source->title,'source_title_sanitized'=>$source_sanitized),JSON_UNESCAPED_UNICODE ), "WP_SoundSystem_Track::autosource_filter_title_requires_artist() - source ignored as artist is not contained in its title");
                unset($sources[$key]);
            }
        }

        return $sources;

    }
    
    function sort_sources_by_similarity(){
        
        $artist_sanitized = sanitize_title($this->artist);
        $title_sanitized = sanitize_title($this->title);
        
        if (!$this->sources) return;

        //compute similarity
        foreach($this->sources as $key=>$source){
            
            //sanitize data so it is easier to compare
            $source_title_sanitized = sanitize_title($source->title);
            
            //remove artist from source title so the string to compare is shorter
            $source_title_sanitized = str_replace($artist_sanitized,"", $source_title_sanitized); 
            
            similar_text($source_title_sanitized, $title_sanitized, $similarity_pc);
            $this->sources[$key]->similarity = $similarity_pc;
        }
        
        //reorder by similarity
        usort($this->sources, function ($a, $b){
            return $a->similarity === $b->similarity ? 0 : ($a->similarity > $b->similarity ? -1 : 1);
        });
        
    }

    function get_track_admin_gui_url($track_action = null,$tracklist_id = null){

        $url = get_permalink($this->post_id);
        $url = add_query_arg(array(wpsstm_tracklists()->qvar_tracklist_admin=>$track_action),$url);

        return $url;
    }
    
    function get_track_actions($tracklist,$context = null){
        
        $tracklist_id = $tracklist->post_id;

        /*
        Capability check
        */

       //tracklist
        $post_type_playlist =       $tracklist_id ? get_post_type($tracklist_id) : null;
        $tracklist_obj =            $post_type_playlist ? get_post_type_object($post_type_playlist) : null;
        $track_type_obj =           get_post_type_object(wpsstm()->post_type_track);

        $can_edit_tracklist =       ( $tracklist_obj && current_user_can($tracklist_obj->cap->edit_post,$tracklist_id) );
        $can_track_details =        ($this->title && $this->artist);
        $can_edit_track =           current_user_can($track_type_obj->cap->edit_post,$this->post_id);
        $can_delete_tracks =        current_user_can($track_type_obj->cap->delete_posts);
        $can_favorite_track =       true;//call to action
        $can_playlists_manager =    true;//call to action
        $can_move_track =           ( $can_edit_tracklist && $tracklist_id && ($tracklist->tracklist_type == 'static') );
        $can_remove_track =         ( $can_edit_tracklist && $tracklist_id && ($tracklist->tracklist_type == 'static') );

        $actions = array();

        //track details
        if ($can_track_details){
            $actions['about'] = array(
                'icon' =>       '<i class="fa fa-info-circle" aria-hidden="true"></i>',
                'text' =>      __('About', 'wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('about',$tracklist_id),
            );
        }

        //track edit
        if ($can_edit_track){
            $actions['edit'] = array(
                'icon' =>       '<i class="fa fa-pencil" aria-hidden="true"></i>',
                'text' =>      __('Track details', 'wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('edit',$tracklist_id),
            );
        }

        //playlists manager
        if ($can_playlists_manager){
            $actions['playlists'] = array(
                'icon' =>       '<i class="fa fa-list" aria-hidden="true"></i>',
                'text' =>      __('Playlists manager','wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('playlists',$tracklist_id),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-track-action'),
            );
        }

        //favorite
        if ($can_favorite_track){
            $actions['favorite'] = array(
                'icon'=>        '<i class="fa fa-heart-o" aria-hidden="true"></i>',
                'text' =>      __('Favorite','wpsstm'),
                'desc' =>       __('Add track to favorites','wpsstm'),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-track-action','wpsstm-action-toggle-favorite'),
            );
            if ( !$this->is_track_loved_by() ) $actions['favorite']['classes'][] = 'wpsstm-toggle-favorite-active';
        }

        //unfavorite
        if ($can_favorite_track){
            $actions['unfavorite'] = array(
                'icon'=>        '<i class="fa fa-heart" aria-hidden="true"></i>',
                'text' =>      __('Unfavorite','wpsstm'),
                'desc' =>       __('Remove track from favorites','wpsstm'),
                'classes' =>    array('wpsstm-requires-auth','wpsstm-track-action','wpsstm-action-toggle-favorite'),
            );
            if ( $this->is_track_loved_by() ) $actions['unfavorite']['classes'][] = 'wpsstm-toggle-favorite-active';
        }
        
        //(playlist) track move
        /*
        if ($can_move_track){
            $actions['move'] = array(
                'icon' =>       '<i class="fa fa-arrows-v" aria-hidden="true"></i>',
                'text' =>      __('Move', 'wpsstm'),
                'desc' =>       __('Drag to move track in tracklist', 'wpsstm'),
            );
        }
        */

        //(playlist) track remove
        if ($can_remove_track){
            $actions['remove'] = array(
                'icon' =>       '<i class="fa fa-chain-broken" aria-hidden="true"></i>',
                'text' =>      __('Remove', 'wpsstm'),
                'desc' =>       __('Remove from tracklist', 'wpsstm'),
            );
        }
        
        //sources manager
        if ($can_edit_track){
            $actions['sources'] = array(
                'icon' =>       '<i class="fa fa-cloud" aria-hidden="true"></i>',
                'text' =>      __('Sources','wpsstm'),
                'desc' =>       __('Sources manager','wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('sources',$tracklist_id),
            );
        }

        //delete track
        if ($can_delete_tracks){
            $actions['trash'] = array(
                'icon' =>       '<i class="fa fa-trash" aria-hidden="true"></i>',
                'text' =>      __('Trash'),
                'desc' =>       __('trash track','wpsstm'),
                'href' =>       $this->get_track_admin_gui_url('trash',$tracklist_id),
            );
        }
        
        //context
        switch($context){
            case 'page':
                
                unset($actions['edit'],$actions['playlists'],$actions['sources'],$actions['trash']);
                
                if ($can_edit_tracklist){
                    $actions['advanced'] = array(
                        'icon' =>       '<i class="fa fa-cog" aria-hidden="true"></i>',
                        'text' =>      __('Advanced', 'wpsstm'),
                        'href' =>       $this->get_track_admin_gui_url('about',$tracklist->post_id),
                    );
                }
                
                $popup_action_slugs = array('about','details','edit','playlists','sources','trash','advanced');
                
                //set popup
                foreach ($actions as $slug=>$action){
                    if ( !in_array($slug,$popup_action_slugs) ) continue;
                    $actions[$slug]['popup'] = true;
                }

            break;
            case 'admin':
                
            break;
        }
        
        $actions = apply_filters('wpsstm_track_actions',$actions,$context);
        
        $default_action = wpsstm_get_blank_action();
        $default_action['classes'][] = 'wpsstm-track-action';
        
        foreach((array)$actions as $slug=>$action){
            $actions[$slug] = wp_parse_args($action,$default_action);
        }
        return $actions;

    }
    
}