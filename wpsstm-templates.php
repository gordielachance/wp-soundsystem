<?php

function wpsstm_classes($classes){
    echo wpsstm_get_classes_attr($classes);
}

function wpsstm_get_classes_attr($classes){
    if (empty($classes)) return;
    return' class="'.implode(' ',$classes).'"';
}

//https://stackoverflow.com/questions/18081625/how-do-i-map-an-associative-array-to-html-element-attributes
function wpsstm_get_html_attr($array){
    $array = (array)$array;
    $str = join(' ', array_map(function($key) use ($array){
       if(is_bool($array[$key])){
          return $array[$key]?$key:'';
       }
       return $key.'="'.$array[$key].'"';
    }, array_keys($array)));
    return $str;
}

function wpsstm_get_percent_bar($percent){
        $pc_status_classes = array('wpsstm-pc-bar');
        $text_bar = $bar_width = null;
        $text_bar = $bar_width = $percent;

        if ($percent<50){
            $pc_status_classes[] = 'color-light';
        }

        $pc_status_classes = wpsstm_get_classes_attr($pc_status_classes);
        $red_opacity = (100 - $percent) / 100;

        return sprintf('<span %1$s><span class="wpsstm-pc-bar-fill" style="width:%2$s"><span class="wpsstm-pc-bar-fill-color wpsstm-pc-bar-fill-yellow"></span><span class="wpsstm-pc-bar-fill-color wpsstm-pc-bar-fill-red" style="opacity:%3$s"></span><span class="wpsstm-pc-bar-text">%4$s</span></span>',$pc_status_classes,$bar_width.'%',$red_opacity,$text_bar);

}

function wpsstm_get_post_mbid($post_id = null){
    
    if ( wpsstm()->get_options('musicbrainz_enabled') != 'on' ) return false;
    
    global $post;
    if (!$post_id) $post_id = $post->ID;
    return get_post_meta( $post_id, wpsstm_mb()->mbid_metakey, true );
}

function wpsstm_get_post_mbdatas($post_id = null, $keys=null){
    
    if ( wpsstm()->get_options('musicbrainz_enabled') != 'on' ) return false;
    
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $data = get_post_meta( $post_id, wpsstm_mb()->mbdata_metakey, true );
    
    if ($keys){
        return wpsstm_get_array_value($keys, $data);
    }else{
        return $data;
    }
    
}

function wpsstm_get_post_artist($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, wpsstm_artists()->artist_metakey, true );
}

function wpsstm_get_post_track($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, wpsstm_tracks()->title_metakey, true );
}

function wpsstm_get_post_album($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, wpsstm_albums()->album_metakey, true );
}

/**
Get the MusicBrainz link of an item (artist/track/album).
**/
function wpsstm_get_post_mb_link_for_post($post_id){
    $mbid = null;
    if ($mbid = wpsstm_get_post_mbid($post_id) ){

        $post_type = get_post_type($post_id);
        $mbtype = null;
        
        switch($post_type){

            case wpsstm()->post_type_artist:
                $mbtype = wpsstm_artists()->artist_mbtype;
            break;

            case wpsstm()->post_type_track:
                $mbtype = wpsstm_tracks()->track_mbtype;
            break;

            case wpsstm()->post_type_album:
                $mbtype = wpsstm_albums()->album_mbtype;
            break;

        }

        if ( $url = wpsstm_mb()->get_mb_url($mbtype,$mbid) ){
            $mbid = sprintf('<a class="mbid %s-mbid" href="%s" target="_blank">%s</a>',$mbtype,$url,$mbid);
        }
    }
    return $mbid;
}

function wpsstm_get_tracklist_link($post_id=null,$pagenum=1,$download=false){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    $url = get_permalink($post_id);
    
    if ($pagenum == 'export'){
        $url = get_permalink($post_id) . wpsstm_tracklists()->qvar_xspf;
        $url = add_query_arg(array('dl'=>(int)($download)),$url);
    }else{
        $pagenum = (int) $pagenum;
        if ($pagenum > 1){
            $url = add_query_arg( array(WP_SoundSystem_Tracklist::$paged_var => $pagenum) );
        }
    }

    $url = apply_filters('wpsstm_get_tracklist_link',$url,$post_id,$pagenum,$download);

    return $url;

}



function wpsstm_get_playlists_ids_for_author($user_id = null, $args=array() ){
    
    if ( !$user_id ) $user_id =  get_current_user_id();
    if ( !$user_id ) return;
    
    //get user playlists
    $default = array(
        'posts_per_page'    => -1,
        'orderby'=>'title',
        'order'=>'ASC'
    );
    
    $args = wp_parse_args((array)$args,$default);
    
    $forced = array(
        'post_type'         => wpsstm()->post_type_playlist,
        'author'            => $user_id,
        'fields'            => 'ids'
    );
    
    $args = wp_parse_args($forced,$args);

    $query = new WP_Query( $args );
    $post_ids = $query->posts;
    
    return $post_ids;
}

function wpsstm_get_user_playlists_list($args = null,$user_id = false){

    if(!$user_id) $user_id = get_current_user_id();
    if(!$user_id) return false;

    $list = null;
    $li_els = array();
    
    $defaults = array(
        'post_status' =>    array('publish','private','future','pending','draft'),
        'posts_per_page' => -1
    );
    $args = wp_parse_args($args,$defaults);

    if ( $playlists_ids = wpsstm_get_playlists_ids_for_author($user_id,$args) ){
        foreach($playlists_ids as $playlist_id){
            $li_title = ( $title = get_the_title($playlist_id) ) ? $title : __('(no title)');
            $status = get_post_status($playlist_id);
            $li_classes = array($status);
            $attr['id'] = sprintf('wpsstm-playlist-%s',$playlist_id);
            $attr['classes'] = implode(' ',$li_classes);
            
            $attr_str = wpsstm_get_html_attr($attr);

            $status_str = '';
            switch ( $status ) {
                case 'publish' :
                    break;
                case 'private' :
                    $status_str = __('Private');
                    break;
                case 'future' :
                    $status_str = __('Scheduled');
                    break;
                case 'pending' :
                    $status_str = __('Pending Review');
                    break;
                case 'draft' :
                    $status_str = __('Draft');
                    break;
            }
            $status_str = ($status_str) ? sprintf(' <strong>— %s</strong>',$status_str) : null;
            
            //checked
            $checked = ( isset($args['checked_ids']) && in_array($playlist_id,$args['checked_ids']) );
            $checked_str = checked($checked,true,false);
            
            $li_els[] = sprintf('<li %s><input type="checkbox" value="%s" %s /> <label>%s%s</label></li>',$attr_str,$playlist_id,$checked_str,$li_title,$status_str);
        }
        $list = sprintf('<ul>%s</ul>',implode("\n",$li_els) );
    }

    return $list;
}

function wpsstm_get_blank_action(){
    return array(
        'text' =>           null,
        'desc' =>           null,
        'href' =>           '#',
        'icon' =>           null,
        'classes' =>        array('wpsstm-action'),
        'link_classes' =>   array(),
        'link_before' =>    null,
        'link_after' =>     null,
        'has_cap' =>        true,
        'popup' =>          false,
    );
}

function wpsstm_get_actions_list($actions,$prefix){
    $track_actions_lis = array();

    foreach($actions as $slug => $action){
        //$loading = '<i class="fa fa-circle-o-notch fa-fw fa-spin"></i>';

        if ( $action['popup'] ){
            $action['link_classes'][] = 'thickbox';
            $action['href'] = add_query_arg(array('TB_iframe'=>true),$action['href']);
        }

        $actions[$slug] = $action;

        $action_attr = array(
            'id'        => sprintf('wpsstm-%s-action-%s',$prefix,$slug),
            'class'     => implode("\n",$action['classes'])
        );

        $link_attr = array(
            'title'     => ($action['desc']) ?$action['desc'] : $action['text'],
            'href'      => $action['href'],
            'class'     => implode("\n",$action['link_classes'])
        );
        $link = sprintf('<a %s>%s<label>%s</label></a>',wpsstm_get_html_attr($link_attr),$action['icon'],$action['text']);
        $link = $action['link_before'].$link.$action['link_after'];

        $track_actions_lis[] = sprintf('<li %s>%s</li>',wpsstm_get_html_attr($action_attr),$link);
    }

    if ( !empty($track_actions_lis) ){
        return sprintf('<ul id="wpsstm-%s-actions" class="wpsstm-actions-list">%s</ul>',$prefix,implode("\n",$track_actions_lis));
    }
}

function wpsstm_get_live_tracklist_url($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    return get_post_meta($post_id, wpsstm_live_playlists()->feed_url_meta_name, true );
}


///NEW
function wpsstm_get_tracklist_class(){
    global $wpsstm_tracklist;
    $tracklist = $wpsstm_tracklist;
    
    $classes = array(
        'wpsstm-tracklist',
        'wpsstm-playable-tracklist',
        'wpsstm-tracklist-table',
    );
    
    if ( $tracklist->get_options('can_play') ){
        $classes[] = 'wpsstm-playable-tracklist';
    }
    
    if ( ($tracklist->tracklist_type == 'live') && $tracklist->is_expired ){
        $classes[] = 'wpsstm-expired-tracklist';
    }
    
    return sprintf('class="%s"',implode(' ',$classes));
}

function wpsstm_tracklist_class(){
    echo wpsstm_get_tracklist_class();
}

function wpsstm_get_tracklist_type(){
    global $wpsstm_tracklist;
    $tracklist = $wpsstm_tracklist;

    return $wpsstm_tracklist->tracklist_type;
}

function wpsstm_tracklist_type(){
    echo wpsstm_get_tracklist_type();
}

function wpsstm_get_tracklist_expire_time(){
    global $wpsstm_tracklist;
    $tracklist = $wpsstm_tracklist;

    if ( wpsstm_get_tracklist_type() != 'live' ) return;
    
    return (int)$tracklist->expiration_time;
}

function wpsstm_get_tracklist_refresh_rate(){
    global $wpsstm_tracklist;
    $tracklist = $wpsstm_tracklist;
    
    if ( wpsstm_get_tracklist_type() != 'live' ) return;
    
    $freq = $tracklist->get_options('datas_cache_min');

    $freq_secs = $freq * MINUTE_IN_SECONDS;
    
    return $refresh_time_human = human_time_diff( 0, $freq_secs );
}

function wpsstm_get_datetime($timestamp){
    if (!$timestamp) return;

    $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) );
    $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'time_format' ) );
    return sprintf(__('on %s - %s','wpsstm'),$date,$time);
}

function wpsstm_get_source_provider(){
    global $wpsstm_source;
    $source = $wpsstm_source;
    
    return $source->provider;
}

function wpsstm_get_source_mime(){
    global $wpsstm_source;
    $source = $wpsstm_source;
    
    return $source->type;
}

function wpsstm_get_source_url($raw = false){
    global $wpsstm_source;
    $source = $wpsstm_source;
    
    if (!$raw){ //playable url
        return $source->src;
    }else{
        return $source->url;
    }
}