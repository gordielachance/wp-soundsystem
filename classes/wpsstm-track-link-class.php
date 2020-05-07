<?php
class WPSSTM_Track_Link{
    var $post_id;

    var $index = null;

    var $title; //link title (as they might appear on Youtube or Soundcloud, for example; when there is no artist + track title)
    var $title_artist; //if available, the artist name
    var $title_track; //if available, the track name

    var $is_bot; //TRUE if link was populated automatically
    var $url;
    var $stream_url;
    var $download_url;
    var $mime_type;
    var $duration; //in seconds
    var $track;

    function __construct($post = null ){
        $this->track = new WPSSTM_Track(); //default
        $this->populate_link_post($post);
    }

    function populate_link_post($post = null){

        $post = get_post($post);
        if ( get_post_type($post) != wpsstm()->post_type_track_link ) return;

        $this->post_id =    $post->ID;

        $this->title = get_the_title($this->post_id);
        $this->url = get_post_meta($this->post_id,WPSSTM_Core_Track_Links::$link_url_metakey,true);
        $this->index = get_post_field('menu_order', $this->post_id);
        if ( $track_id = wp_get_post_parent_id( $this->post_id ) ){
            $this->track = new WPSSTM_Track($track_id);
        }

    }

    function from_array( $args ){

        if ( !is_array($args) ) return;

        $allowed = array(
            'post_id',
            'index',
            'url',
            'title',
            'is_bot',
        );

        //set properties from args input
        foreach ($args as $key=>$value){

            if ( !in_array($key,$allowed) ) continue;

            switch($key){
                default:
                    if ( !isset($args[$key]) ) break; //value has not been set
                    $this->$key = $args[$key];
                break;
            }

        }

        //link
        if ( $this->post_id ){
            $this->populate_link_post();
        }

    }

    /*
    Get the links that have the same URL or track informations than the current one
    */
    private function get_link_duplicates_ids($args=null){

        $default = array(
            'post_status'       => 'any',
            'posts_per_page'    => -1,
            'fields'            => 'ids'
        );

        $args = wp_parse_args((array)$args,$default);

        $required = array(
            'post__not_in'      => ($this->post_id) ? array($this->post_id) : null, //exclude current link
            'parent_track'      => $this->track->post_id,
            'post_type'         => wpsstm()->post_type_track_link,

            'meta_query'        => array(
                //by link URL
                array(
                    'key'     => WPSSTM_Core_Track_Links::$link_url_metakey,
                    'value'   => $this->url
                )
            )
        );

        $args = wp_parse_args($required,$args);

        $query = new WP_Query( $args );
        return $query->posts;
    }

    function validate_link(){

        $this->url = trim($this->url);

        if (!$this->url){
            return new WP_Error( 'wpsstm_missing_url', __('Unable to validate link: missing URL','wpsstm') );
        }

        if ( !filter_var($this->url, FILTER_VALIDATE_URL) ){
            return new WP_Error( 'wpsstm_missing_valid_url', __('Unable to validate link: bad URL','wpsstm') );
        }

        $this->title = trim($this->title);
    }

    function create_track_link($track){

        $this->track = $track;

        if ($this->post_id){
            return new WP_Error( 'wpsstm_link_has_post_id', __('This link already exists.','wpsstm') );
        }

        if (!$this->track->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __('Unable to validate link: missing track ID','wpsstm') );
        }

        $validated = $this->validate_link();
        if ( is_wp_error($validated) ) return $validated;

        //exclude some hosts
        if ( $excluded_hosts = wpsstm()->get_options('excluded_track_link_hosts') ){
            foreach($excluded_hosts as $host){
                if (strpos($this->url,$host) !== false) {
                    return new WP_Error( 'wpsstm_excluded_host', sprintf(__('Host %s excluded','wpsstm'),$host), array('permalink'=>$this->url,'excluded_host'=>$host) );
                }
            }

        }

        //check for duplicates
        $duplicates = $this->get_link_duplicates_ids();
        if ( !empty($duplicates) ){
            $link_id = $duplicates[0];
            return new WP_Error( 'wpsstm_link_exists', __('This link already exists, do not create it','wpsstm'), array('url'=>$this->url,'existing'=>$link_id) );
        }else{

            if ($this->is_bot){

                //check bot user
                $bot_ready = wpsstm()->is_bot_ready();
                if ( is_wp_error($bot_ready) ) return $bot_ready;
                $post_author = wpsstm()->get_options('bot_user_id');

            }else{

                $post_author = get_current_user_id();

                if ( !user_can($post_author,'create_tracks') ){
                    return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to create track links.",'wpsstm') );
                }

            }

            $args = array(
                'post_author' =>    $post_author,
                'post_status' =>    'publish',
            );

            $required_args = array(
                'post_title' =>     $this->title,
                'post_type' =>      wpsstm()->post_type_track_link,
                'post_parent' =>    $this->track->post_id,
                'meta_input' =>     array(
                    WPSSTM_Core_Track_Links::$link_url_metakey => $this->url
                )
            );

            $args = wp_parse_args($required_args,$args);
            $success = wp_insert_post( $args, true );

            if ( is_wp_error($success) ) return $success;
            $this->post_id = $success;
            return $this->post_id;

        }
    }



    function trash_link(){

        if (!$this->post_id){
            return new WP_Error( 'wpsstm_missing_post_id', __("Missing link ID.",'wpsstm') );
        }

        //capability check
        $post_type_obj = get_post_type_object(wpsstm()->post_type_track_link);
        $can_delete_link = current_user_can($post_type_obj->cap->delete_post,$this->post_id);

        if (!$can_delete_link){
            return new WP_Error( 'wpsstm_missing_capability', __("You don't have the capability required to delete this link.",'wpsstm') );
        }

        return wp_trash_post( $this->post_id );
    }

    function get_single_link_attributes(){

        $attr = array(
            'data-wpsstm-link-id' =>            $this->post_id,
            'data-wpsstm-link-domain' =>        wpsstm_get_url_domain($this->url),
            'data-wpsstm-link-idx' =>           $this->track->current_link,
            'class' =>                          implode( ' ',$this->get_link_class() ),
            'wpsstm-playable' =>                $this->is_playable_link(),
        );

        if ( $this->is_playable_link() ){
            $attr_stream = array(
                'data-wpsstm-stream-src' =>         $this->get_stream_url(),
                'data-wpsstm-stream-type' =>        $this->get_link_mimetype(),
            );
            $attr = array_merge($attr,$attr_stream);
        }

        return $attr;
    }

    function get_link_class(){
        $classes = array();
        $classes = apply_filters('wpsstm_link_classes',$classes,$this);
        return array_filter(array_unique($classes));
    }

    function get_link_action_url($action = null){

        $url = null;

        $args = array(
            'wpsstm_action'=>$action
        );

        if ($this->post_id){
            $url = get_permalink($this->post_id);
            $url = add_query_arg($args,$url);
        }

        return $url;
    }

    function get_link_actions($context = null){
        $actions = array();

        $link_type_obj = get_post_type_object(wpsstm()->post_type_track_link);

        //caps
        $can_edit_link = current_user_can($link_type_obj->cap->edit_post,$this->post_id);
        $can_delete_link = current_user_can($link_type_obj->cap->delete_post,$this->post_id);
        $can_reorder_links = $this->track->user_can_reorder_links();

        $actions['provider'] = array(
            'text' =>       wpsstm_get_url_domain($this->url),
            'href' =>       $this->url,
            'target' =>     '_blank',
        );

        if ($can_delete_link){
            $actions['trash'] = array(
                'text' =>       __('Trash'),
                'desc' =>       __('Trash this link','wpsstm'),
                'href' =>       $this->get_link_action_url('trash'),
            );

            $is_trashed = ( get_post_type($this->post_id) === 'trash' );
            if ($is_trashed){
                $actions['trash']['classes'][] = 'wpsstm-freeze';
            }

        }

        if ( $can_reorder_links ){
            $actions['move'] = array(
                'text' =>       __('Move', 'wpsstm'),
                'desc' =>       __('Change link position','wpsstm'),
            );
        }

        if ( $can_edit_link ){
            $actions['edit-backend'] = array(
                'text' =>      __('Edit'),
                'classes' =>    array('wpsstm-advanced-action'),
                'href' =>       get_edit_post_link( $this->post_id ),
            );
        }

        if ( $this->is_playable_link() ){
            $actions['play'] = array(
                'text' =>       __('Play', 'wpsstm'),
                'href' =>       '#',
            );
            ?>
            <?php
        }


        return apply_filters('wpsstm_link_actions',$actions,$context);
    }

    function is_playable_link(){
        if ( !wpsstm()->get_options('player_enabled') ) return;
        return (bool)$this->get_link_mimetype();
    }

    function get_stream_url(){
        if ($this->stream_url) return $this->stream_url;
        return $this->stream_url = apply_filters('wpsstm_get_stream_url',$this->url,$this);
    }

    private function get_link_mimetype(){
        if ( !$stream_url = $this->get_stream_url() ) return;
        if ( $this->mime_type !== null ) return $this->mime_type; //already populated
        $mime = false;

        //audio file
        //TOUFIX TOUCHECK maybe this is slowing down the page rendering; and we should check this with ajax
        $filetype = wp_check_filetype($stream_url);
        if ( $ext = $filetype['ext'] ){
            $audio_extensions = wp_get_audio_extensions();
            if ( in_array($ext,$audio_extensions) ){
                $mime = sprintf('audio/%s',$ext);
            }
        }

        $this->mime_type = apply_filters('wpsstm_get_link_mimetype',$mime,$this);
        return $this->mime_type;
    }

    function get_link_title(){
        $title = $this->title;
        if (!$title){
            $title = wpsstm_get_url_domain($this->url);
        }
        return $title;
    }

    function get_link_icon(){
        if ($this->icon !== null) return $this->icon;
        $icon = '<i class="fa fa-file-audio-o" aria-hidden="true"></i>';
        $this->icon = apply_filters('wpsstm_get_link_icon',$icon,$this);
        return $this->icon;
    }

    function link_log($data,$title = null){

        if ($this->post_id){
            $title = sprintf('[link:%s] ',$this->post_id) . $title;
        }

        $this->track->track_log($data,$title);

    }

    /*
    Return one level array
    */

    function to_array(){

        $arr = array(
            'track_id' =>       $this->track->post_id,
            'post_id' =>        $this->post_id,
            'index' =>          $this->index,

            'title' =>          $this->title,
            'title_artist' =>   $this->title_artist,
            'title_track' =>    $this->title_track,

            'is_bot' =>         $this->is_bot,
            'url' =>            $this->url,
            'stream_url' =>     $this->stream_url,
            'download_url' =>   $this->download_url,
            'mime_type' =>      $this->mime_type,
            'duration' =>       $this->duration,
        );

        return array_filter($arr);

    }

}
