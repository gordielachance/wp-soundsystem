<?php

/*
Requires the Custom Post Links plugin.
*/

class WP_SoundSytem_Post_Bookmarks {
    
    static $music_source_cat_slug = 'wpsstm-sources';
    
    function __construct(){
        add_filter('post_bkmarks_get_table_tabs', array( $this, 'register_links_tab' ) );
        add_filter('post_bkmarks_tab_links_args', array( $this, 'links_tab_music_sources_args' ), 10, 3 );
        add_filter('post_bkmarks_get_tab_links', array( $this, 'tab_musicbrainz_links' ), 10, 2 );
        add_filter('post_bkmarks_get_bulk_actions', array( $this, 'get_musicbrainz_bulk_actions' ) );
    }

    function register_links_tab($tabs){
        global $post;
        
        $allowed_post_types = array(
            wpsstm()->post_type_artist,
            wpsstm()->post_type_track,
            wpsstm()->post_type_album
        );
        
        if ( !in_array($post->post_type,$allowed_post_types) ) return $tabs;
        
        $link_musicbrainz_classes = $link_sources_classes = array();
        
        if (post_bkmarks()->links_tab == 'music_sources'){
            $link_sources_classes[] = 'current';
        }
        
        $link_sources_count = count( post_bkmarks_get_tab_links('music_sources') );
        $tabs['music_sources'] = sprintf(
            __('<a href="%1$s"%2$s>%3$s <span class="count">(<span class="imported-count">%4$s</span>)</span></a>'),
            add_query_arg(array('pbkm_tab'=>'music_sources'),get_edit_post_link()),
            wpsstm_get_classes_attr($link_sources_classes),
            __('Music sources','wpsstm'),
            $link_sources_count
        );
        
        if ( wpsstm()->get_options('musicbrainz_enabled') == 'on' ){
            
            if (post_bkmarks()->links_tab == 'musicbrainz'){
                $link_musicbrainz_classes[] = 'current';
            }

            $link_musicbrainz_count = count( post_bkmarks_get_tab_links('musicbrainz') );
            $tabs['musicbrainz'] = sprintf(
                __('<a href="%1$s"%2$s>%3$s <span class="count">(<span class="imported-count">%4$s</span>)</span></a>'),
                add_query_arg(array('pbkm_tab'=>'musicbrainz'),get_edit_post_link()),
                wpsstm_get_classes_attr($link_musicbrainz_classes),
                __('MusicBrainz','wpsstm'),
                $link_musicbrainz_count
            );
            
        }
        
        return $tabs;
    }
    
    /*
    Get our  music sources links category (based on slug) or create it if it does not exists
    */

    static function get_sources_category(){

        $cat_id = null;

        if ( $cat = get_term_by( 'slug', WP_SoundSytem_Post_Bookmarks::$music_source_cat_slug, 'link_category') ){
            $cat_id = $cat->term_id;
        }else{
            $cat_id = wp_insert_term( 
                __('Music sources','wpsstm'), 
                'link_category',
                 array(
                     'description'  => sprintf(__('Parent category for all the music sources links created by the %s plugin.','post_bkmarks'),'<a href="'.admin_url('admin.php?page=wpsstm').'" target="blank">'.__('WP SoundSystem','wpsstm').'</a>'),
                     'slug'         => WP_SoundSytem_Post_Bookmarks::$music_source_cat_slug
                 ) 
            );
        }
        return $cat_id;
    }
    
    function links_tab_music_sources_args($args,$tab,$post_id){
        if ( $tab != 'music_sources' ) return $args;

        $categories_arr = (isset($args['category']) ) ? explode(",", $args['category']) : array();
        $categories_arr[] = self::get_sources_category();
        $args['category'] = implode(',',$categories_arr);

        return $args;

    }
    
    function tab_musicbrainz_links($links, $tab){

        if ( $tab != 'musicbrainz' ) return $links;
        
        $mb_links = array();

        $mb_relations = wpsstm_get_post_mbdatas(null,'relations');
        if ( $mb_relations ){

            foreach((array)$mb_relations as $rel){
                
                $link_url = ( isset($rel['url']['resource']) ) ? $rel['url']['resource'] : null;
                if (!$link_url) continue;
                
                if ( $link_id = post_bkmarks_get_existing_link_id($link_url) ) continue; 
                    
                $mb_link = array(
                    'link_name'     => ( isset($rel['type']) ) ? $rel['type'] : null,
                    'link_url'      => $link_url
                );

                $mb_links[] = $mb_link;

            }
        }
        
        

        return $mb_links;
    }

    function get_musicbrainz_bulk_actions($actions){
        if (post_bkmarks()->links_tab == 'musicbrainz'){
            unset($actions['unlink'],$actions['delete']);
        }
        return $actions;
    }
}


new WP_SoundSytem_Post_Bookmarks();