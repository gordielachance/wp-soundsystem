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
    return get_post_meta( $post_id, wpsstm_mb()->mb_id_meta_name, true );
}

function wpsstm_get_post_sources($post_id = null){

    global $post;
    if (!$post_id) $post_id = $post->ID;
    return get_post_meta( $post_id, wpsstm_tracks()->sources_metakey, true );
}

function wpsstm_get_post_mbdatas($post_id = null, $keys=null){
    
    if ( wpsstm()->get_options('musicbrainz_enabled') != 'on' ) return false;
    
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $data = get_post_meta( $post_id, wpsstm_mb()->mb_data_meta_name, true );
    
    if ($keys){
        return wpsstm_get_array_value($keys, $data);
    }else{
        return $data;
    }
    
}

function wpsstm_get_post_artist($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, wpsstm_artists()->metakey, true );
}

function wpsstm_get_post_track($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, wpsstm_tracks()->metakey, true );
}

function wpsstm_get_post_album($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, wpsstm_albums()->metakey, true );
}

/**
Get a post ID by artist, album or track
By artist : artist required
By album : artist and album required
By track : artist and track required, album optional
**/

function wpsstm_get_post_id_by($slug,$artist=null,$album=null,$track=null){
    
    $allowed_slugs = array('artist','album','track');
    if ( !in_array($slug,$allowed_slugs) ) return;
    
    $post_id = null;
    $query_args = null;
    
    switch($slug){
        case 'artist':
            
            if (!$artist) return;
            
            $query_args = array(
                wpsstm_artists()->qvar_artist   => $artist,
                'post_type'                     => wpsstm()->post_type_artist,
            );
            
        break;
        case 'album':
            
            if (!$artist || !$album) return;
            
            $query_args = array(
                wpsstm_artists()->qvar_artist    => $artist,
                wpsstm_albums()->qvar_album     => $album,
                'post_type'                     => wpsstm()->post_type_album,
            );
        break;
        case 'track':
            
            if (!$artist || !$track) return;
            
            $query_args = array(
                wpsstm_artists()->qvar_artist   => $artist,
                wpsstm_tracks()->qvar_track     => $track,
                'post_type'                     => wpsstm()->post_type_track,
            );

            if ($album){
                $query_args[wpsstm_albums()->qvar_album] = $album;
            }

        break;
    }

    if (!$query_args) return;
    
    //wpsstm()->debug_log( json_encode($query_args,JSON_UNESCAPED_UNICODE), "wpsstm_get_post_id_by()"); 

    $query = new WP_Query( $query_args );
    if (!$query->posts) return;

    $first_post = $query->posts[0];
    return $first_post->ID;

}

/**
Get the MusicBrainz link of an item (artist/track/album).
**/
function wpsstm_get_post_mb_link_for_post($post_id){
    $mbid = null;
    if ($mbid = wpsstm_get_post_mbid($post_id) ){

        $post_type = get_post_type($post_id);
        $class_instance = wpsstm_get_class_instance($post_id);
        $mbtype = $class_instance->mbtype;
        
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
    
    if ($pagenum == 'xspf'){
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

/*
Get playlist love/unlove icons.
*/

function wpsstm_get_tracklist_loveunlove_icons($tracklist_id){
    
    $tracklist = new WP_SoundSystem_Tracklist($tracklist_id);

    $wrapper_classes = array(
        'wpsstm-tracklist-action-love-unlove'
    );
    
    if ( $tracklist->is_tracklist_loved_by() ){
        $wrapper_classes[] = 'wpsstm-is-loved';
    }
    
    $loading = '<i class="fa fa-circle-o-notch fa-fw fa-spin"></i>';
    $love_link = sprintf('<a href="#" title="%1$s" class="wpsstm-requires-auth wpsstm-tracklist-action wpsstm-tracklist-love"><i class="fa fa-heart-o" aria-hidden="true"></i><span> %1$s</span></a>',__('Add playlist to favorites','wpsstm'));
    $unlove_link = sprintf('<a href="#" title="%1$s" class="wpsstm-requires-auth wpsstm-tracklist-action wpsstm-tracklist-unlove"><i class="fa fa-heart" aria-hidden="true"></i><span> %1$s</span></a>',__('Remove playlist from favorites','wpsstm'));
    return sprintf('<span %s>%s%s%s</span>',wpsstm_get_classes_attr($wrapper_classes),$loading,$love_link,$unlove_link);
}

function wpsstm_get_tracklist_refresh_frequency_human($post_id = null){
    if (!$post_id) $post_id = get_the_ID();
    
    $post_type = get_post_type($post_id);
    $is_live_tracklist = ( $post_type == wpsstm()->post_type_live_playlist  );
    
    if (!$is_live_tracklist) return;
    $tracklist = wpsstm_get_post_tracklist($post_id);
    $freq = $tracklist->get_options('datas_cache_min');

    $freq_secs = $freq * MINUTE_IN_SECONDS;
    
    $refresh_time_human = human_time_diff( 0, $freq_secs );
    $refresh_time_human = sprintf('every %s',$refresh_time_human);
    $refresh_time_el = sprintf('<time class="wpsstm-tracklist-refresh-time"><i class="fa fa-rss" aria-hidden="true"></i></i> %s</time>',$refresh_time_human);

    return $refresh_time_el;
}
/*
Get track love/unlove icons.
*/

function wpsstm_get_track_loveunlove_icons(WP_SoundSystem_Track $track = null){
    
    //no capability check here since we want to display the link even if the user is not logged ( = call to action - register)

    $wrapper_classes = array(
        'wpsstm-track-action',
        'wpsstm-track-action-love-unlove',
        'wpsstm-track-action-wp-love-unlove'
    );
    
    if ( $track && $track->is_track_loved_by() ){
        $wrapper_classes[] = 'wpsstm-is-loved';
    }

    $loading = '<i class="fa fa-circle-o-notch fa-fw fa-spin"></i>';
    $love_link = sprintf('<a href="#" title="%1$s" class="wpsstm-icon-link wpsstm-requires-auth wpsstm-track-love wpsstm-track-action"><i class="fa fa-heart-o" aria-hidden="true"></i><span> %1$s</span></a>',__('Add track to favorites','wpsstm'));
    $unlove_link = sprintf('<a href="#" title="%1$s" class="wpsstm-icon-link wpsstm-requires-auth wpsstm-track-unlove wpsstm-track-action"><i class="fa fa-heart" aria-hidden="true"></i><span> %1$s</span></a>',__('Remove track from favorites','wpsstm'));
    return sprintf('<span %s>%s%s%s</span>',wpsstm_get_classes_attr($wrapper_classes),$loading,$love_link,$unlove_link);
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

function wpsstm_get_track_playlists_selector_link(WP_SoundSystem_Track $track = null){

    //no capability check here since we want to display the link even if the user is not logged ( = call to action - register)
    
    $icon = '<i class="fa fa-list" aria-hidden="true"></i>';
    $loading = '<i class="fa fa-circle-o-notch fa-fw fa-spin"></i>';
    
    $wrapper_classes = array(
        'wpsstm-track-action',
        'wpsstm-track-action-playlists-selector'
    );
    
    $ajax_url = add_query_arg( 
        array( 
            'action'        => 'wpsstm_track_playlists_selector',
            'track'         => array('artist'=>$track->artist,'title'=>$track->title,'album'=>$track->album),
            //'width'         => '600', 
            //'height'        => '550' 
        ), 
        admin_url( 'admin-ajax.php' )
    );
    
    $text = __('Add track to playlist','wpsstm');
    
    $tracklists_link = sprintf('<a title="%s" href="%s" class="thickbox wpsstm-icon-link wpsstm-requires-auth wpsstm-track-action wpsstm-tracklist-chooser">%s <span>%s</span></a>',$text,$ajax_url,$icon,$text);

    return sprintf('<span %s>%s%s</span>',wpsstm_get_classes_attr($wrapper_classes),$loading,$tracklists_link);
    
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