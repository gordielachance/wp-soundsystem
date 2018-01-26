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
    return get_post_meta( $post_id, WP_SoundSystem_Core_MusicBrainz::$mbid_metakey, true );
}

function wpsstm_get_post_image_url($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    //easier to use a meta like this than to upload the remote image if the track is imported
    
    $image_url = get_post_meta( $post_id, WP_SoundSystem_Core_Tracks::$image_url_metakey, true ); //remote track
    
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
    $data = get_post_meta( $post_id, WP_SoundSystem_Core_MusicBrainz::$mbdata_metakey, true );
    
    if ($keys){
        return wpsstm_get_array_value($keys, $data);
    }else{
        return $data;
    }
    
}

function wpsstm_get_post_artist($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, WP_SoundSystem_Core_Artists::$artist_metakey, true );
}

function wpsstm_get_post_track($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, WP_SoundSystem_Core_Tracks::$title_metakey, true );
}

function wpsstm_get_post_album($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    return get_post_meta( $post_id, WP_SoundSystem_Core_Albums::$album_metakey, true );
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
                $mbtype = WP_SoundSystem_Core_Artists::$artist_mbtype;
            break;

            case wpsstm()->post_type_track:
                $mbtype = WP_SoundSystem_Core_Tracks::$track_mbtype;
            break;

            case wpsstm()->post_type_album:
                $mbtype = WP_SoundSystem_Core_Albums::$album_mbtype;
            break;

        }

        if ( $url = WP_SoundSystem_Core_Musicbrainz::get_mb_url($mbtype,$mbid) ){
            $mbid = sprintf('<a class="mbid %s-mbid" href="%s" target="_blank">%s</a>',$mbtype,$url,$mbid);
        }
    }
    return $mbid;
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
    $feed_url = get_post_meta($post_id, WP_SoundSystem_Core_Live_Playlists::$feed_url_meta_name, true );
    return apply_filters('wpsstm_live_tracklist_raw_url',$feed_url); //filter input URL with this hook - several occurences in the code
}

function wpsstm_get_datetime($timestamp){
    if (!$timestamp) return;

    $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) );
    $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'time_format' ) );
    return sprintf(__('on %s - %s','wpsstm'),$date,$time);
}

//Check that a post is a community post (created with the bot user)
function wpsstm_is_community_post($post_id = null){
    global $post;
    if (!$post_id && $post) $post_id = $post->ID;
    $post_author_id = get_post_field( 'post_author', $post_id );
    return ( $post_author_id == wpsstm()->get_options('community_user_id') );
}