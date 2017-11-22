<?php

function wpsstm_classes($classes){
    echo wpsstm_get_classes_attr($classes);
}

function wpsstm_get_classes_attr($classes){
    if (empty($classes)) return;
    return' class="'.implode(' ',(array)$classes).'"';
}

//https://stackoverflow.com/questions/18081625/how-do-i-map-an-associative-array-to-html-element-attributes
function wpsstm_get_html_attr($arr=null){
    $str = null;
    $arr = (array)$arr;
    
    //attributes with values
    if (!empty($arr) ){
        $arr = (array)$arr;
        $str .= join(' ', array_map(function($key) use ($arr){
           if(is_bool($arr[$key])){
              return $arr[$key]?$key:'';
           }
           return $key.'="'.$arr[$key].'"';
        }, array_keys($arr)));
    }

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

function wpsstm_get_post_image_url($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    //easier to use a meta like this than to upload the remote image if the track is imported
    
    $image_url = get_post_meta( $post_id, wpsstm_tracks()->image_url_metakey, true ); //remote track
    
    //regular WP post
    if( has_post_thumbnail($post_id) ){
        $image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ) );
        $image_url = $image[0];
    }
    
    return $image_url;
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
        'classes' =>        array(),
        'link_before' =>    null,
        'link_after' =>     null,
        'has_cap' =>        true,
        'target' =>         null,
    );
}

function get_actions_list($actions,$prefix){
    $track_actions_list = array();
    
    $default_action = wpsstm_get_blank_action();

    foreach($actions as $slug => $action){
        //$loading = '<i class="fa fa-circle-o-notch fa-fw fa-spin"></i>';

        $action = wp_parse_args($action,$default_action);
        $classes = $action['classes'];
        $classes[] = 'wpsstm-action';
        $classes[] = sprintf('wpsstm-%s-action',$prefix);
        $classes = array_unique($classes);

        $action_attr = array(
            'id'        => sprintf('wpsstm-%s-action-%s',$prefix,$slug),
            'class'     => implode(" ",$classes),
        );

        $link_attr = array(
            'title'     => ($action['desc']) ?$action['desc'] : $action['text'],
            'href'      => $action['href'],
            'target'    => $action['target'],
        );
        $link = sprintf('<a %s><span>%s</span></a>',wpsstm_get_html_attr($link_attr),$action['text']);
        $link = $action['link_before'].$link.$action['link_after'];
        
        $list_item = sprintf('<li %s>%s</li>',wpsstm_get_html_attr($action_attr),$link);
        $track_actions_list[] = $list_item;
    }

    if ( !empty($track_actions_list) ){
        return sprintf('<ul id="wpsstm-%s-actions" class="wpsstm-actions-list">%s</ul>',$prefix,implode("\n",$track_actions_list));
    }
}

function wpsstm_get_live_tracklist_url($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $feed_url = get_post_meta($post_id, wpsstm_live_playlists()->feed_url_meta_name, true );
    return apply_filters('wpsstm_live_tracklist_url',$feed_url); //filter input URL with this hook - several occurences in the code
}

function wpsstm_get_datetime($timestamp){
    if (!$timestamp) return;

    $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) );
    $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'time_format' ) );
    return sprintf(__('on %s - %s','wpsstm'),$date,$time);
}
