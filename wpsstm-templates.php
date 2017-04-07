<?php

function wpsstm_classes($classes){
    echo wpsstm_get_classes_attr($classes);
}

function wpsstm_get_classes_attr($classes){
    if (empty($classes)) return;
    return' class="'.implode(' ',$classes).'"';
}

function wpsstm_percent_bar($percent){
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
    
    $query = new WP_Query( $query_args );
    if (!$query->posts) return;

    $first_post = $query->posts[0];
    return $first_post->ID;

}

/**
Get the permalink of the artist post by post ID (eg. for a track or an album).
If it does not exists, just returns the artist name.
**/
function wpsstm_get_post_artist_link_for_post($post_id){
    $artist = null;
    if ($artist = wpsstm_get_post_artist($post_id) ){
        $artist = wpsstm_get_post_artist_link_by_name($artist);
    }
    return $artist;
}

/**
Get the permalink of an artist post by name.
If it does not exists, just returns the artist name.
**/
function wpsstm_get_post_artist_link_by_name($artist,$is_edit=false){
    if ( $artistid_wp = wpsstm_get_post_id_by('artist',$artist) ){
        $link = ($is_edit) ? get_edit_post_link( $artistid_wp ) : get_permalink($artistid_wp);
        $artist = sprintf('<a href="%s">%s</a>',$link,$artist);
    }
    return $artist;
}

/**
Get the permalink of an album post post by name.
If it does not exists, just returns the album name.
**/
function wpsstm_get_post_album_link_by_name($album,$artist,$is_edit=false){
    if ( $artist && ( $albumid_wp = wpsstm_get_post_id_by('album',$artist,$album) ) ){
        $link = ($is_edit) ? get_edit_post_link( $albumid_wp ) : get_permalink($albumid_wp);
        $album = sprintf('<a href="%s">%s</a>',$link,$album);
    }
    return $album;
}

/**
Get the permalink of a track post post by name.
If it does not exists, just returns the track name.
**/
function wpsstm_get_post_track_link_by_name($artist,$track,$album=null,$is_edit=false){
    if ( $trackid_wp = wpsstm_get_post_id_by('track',$artist,$album,$track) ){
        $link = ($is_edit) ? get_edit_post_link( $trackid_wp ) : get_permalink($trackid_wp);
        $track = sprintf('<a href="%s">%s</a>',$link,$track);
    }
    return $track;
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

/*
Get a post tracklist
cache_only parameter is for live playlists.  If set to true, load only tracks from the cache, not from the remote page.
*/

function wpsstm_get_post_tracklist($post_id=null,$cache_only = false){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $post_type = get_post_type($post->ID);
    
    $tracklist = new WP_SoundSytem_Tracklist($post_id);

    if ($post_type == wpsstm()->post_type_live_playlist){
        $scraper = new WP_SoundSytem_Playlist_Scraper();
        $scraper->cache_only = $cache_only;
        $scraper->init_post($post_id);
        $tracklist = $scraper->tracklist;
    }else{
        $tracklist->load_subtracks();
    }
    
    return $tracklist;
    
}

function wpsstm_get_xspf_link($post_id=null,$download=true){
    global $post;
    if (!$post_id) $post_id = $post->ID;

    //check post type
    $post_type = get_post_type($post->ID);
    $allowed_post_types = array(
        wpsstm()->post_type_album,
        wpsstm()->post_type_playlist,
        wpsstm()->post_type_live_playlist
    );

    if ( !in_array($post_type,$allowed_post_types) ) return;
    
    $xspf_url = get_permalink($post_id) . wpsstm_tracklists()->qvar_xspf;

    if($download){
        $xspf_url = add_query_arg(array('download'=>true),$xspf_url);
    }
    return $xspf_url;

}

/*
Get our  music sources links for a post
*/

function wpsstm_get_post_player_sources($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    $links = array();
    
    if ( class_exists( 'WP_SoundSytem_Post_Bookmarks' ) ){
        $args = array(
            'category' => WP_SoundSytem_Post_Bookmarks::get_sources_category()
        );
        $links = post_bkmarks_get_post_links($post_id, $args);
    }

    return apply_filters('wpsstm_get_post_player_sources',$links,$post_id);
    
}