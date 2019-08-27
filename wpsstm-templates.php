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
    $arr = array_filter($arr, function($value) { return $value !== ''; }); //remove empty strings

    //attributes with values
    if (!empty($arr) ){
        $arr = (array)$arr;
        $str .= join(' ', array_map(function($key) use ($arr){
           if(is_bool($arr[$key])){
              return $arr[$key]?$key:'';
           }
           return $key.'="'.esc_attr($arr[$key]).'"';
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

function wpsstm_get_post_image_url($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    //easier to use a meta like this than to upload the remote image if the track is imported
    
    $image_url = get_post_meta( $post_id, WPSSTM_Core_Tracks::$image_url_metakey, true ); //remote track
    
    //regular WP post
    if( has_post_thumbnail($post_id) ){
        $image = wp_get_attachment_image_src( get_post_thumbnail_id( $post_id ) );
        $image_url = $image[0];
    }
    
    return $image_url;
}

function wpsstm_get_post_length($post_id = null,$seconds = false){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $ms = $s = 0;

    if ( $ms = get_post_meta( $post_id, WPSSTM_Core_Tracks::$length_metakey, true ) ){
        $s = round($ms / 1000);
    }
    
    return ($seconds) ? $s : $ms;
}

function wpsstm_get_post_artist($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    $terms_list = get_the_term_list( $post_id, WPSSTM_Core_Tracks::$artist_taxonomy , null, ',' );
    if ( is_wp_error($terms_list) ) return false;
    
    return strip_tags($terms_list);
}

function wpsstm_get_post_track($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    $terms_list = get_the_term_list( $post_id, WPSSTM_Core_Tracks::$track_taxonomy , null, ',' );
    if ( is_wp_error($terms_list) ) return false;
    
    return strip_tags($terms_list);
}

function wpsstm_get_post_album($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    
    $terms_list = get_the_term_list( $post_id, WPSSTM_Core_Tracks::$album_taxonomy , null, ',' );
    if ( is_wp_error($terms_list) ) return false;
    
    return strip_tags($terms_list);
}

function wpsstm_get_blank_action(){
    return array(
        'text' =>           null,
        'desc' =>           null,
        'href' =>           '#',
        'classes' =>        array(),
        'target' =>         null,
        'rel' =>            'nofollow',
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
        $classes[] = sprintf('wpsstm-%s-action-%s',$prefix,$slug);
        $classes = array_unique($classes);
        
        $action['title'] =  ($action['desc']) ?$action['desc'] : $action['text'];
        $action['class'] =  implode(' ',$classes);
        
        //cleanup - TO FIX we should filter attributes within wpsstm_get_html_attr() ?
        unset($action['classes'],$action['desc']);

        $link = sprintf('<a %s><span>%s</span></a>',wpsstm_get_html_attr($action),$action['text']);
        $track_actions_list[] = $link;
    }

    if ( !empty($track_actions_list) ){
        return sprintf('<div class="wpsstm-%s-actions wpsstm-actions-list">%s</div>',$prefix,implode("\n",$track_actions_list));
    }
}

function wpsstm_get_datetime($timestamp){
    if (!$timestamp) return;

    $date = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'date_format' ) );
    $time = get_date_from_gmt( date( 'Y-m-d H:i:s', $timestamp ), get_option( 'time_format' ) );
    return sprintf(__('on %s - %s','wpsstm'),$date,$time);
}

//Check that a post is a bot post (created with the bot user)
function wpsstm_is_bot_post($post_id = null){
    global $post;
    
    if ( !$bot_id = wpsstm()->get_options('bot_user_id') ) return false;

    if (!$post_id && $post) $post_id = $post->ID;
    $post_author_id = get_post_field( 'post_author', $post_id );
    return ( $post_author_id == $bot_id );
}

function wpsstm_get_backend_form_input($options = null){

    $label_el = $input_el = $icon_el = null;

    //options
    $option_defaults = array(
        'id' => null,
        'name' => null,
        'value' => null,
        'icon' => null,
        'label' => null,
        'placeholder' => null
    );
    $options = wp_parse_args((array)$options,$option_defaults);

    //label
    $label_el = (isset($options['label'])) ? sprintf('<label for="%s">%s</label>',$options['id'],$options['label']) : null;

    //input
    $input_attr = array(
        'id' =>             isset($options['id']) ? $options['id'] : null,
        'type' =>           'text',
        'name' =>           isset($options['name']) ? $options['name'] : null,
        'class' =>          'wpsstm-fullwidth input-group-field',
        'value' =>          isset($options['value']) ? $options['value'] : null,
        'placeholder' =>    isset($options['placeholder']) ? $options['placeholder'] : null,

    );

    $attr_str = wpsstm_get_html_attr($input_attr);
    $input_el = sprintf('<input %s/>',$attr_str);

    //icon el
    $icon_el = (isset($options['icon'])) ? sprintf('<span class="input-group-icon">%s</span>',$options['icon']) : null;

    //output

    return sprintf('%s<div class="input-group">%s%s</div>',$label_el,$input_el,$icon_el);
}