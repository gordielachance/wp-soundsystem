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

function wpsstm_get_post_artist($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;

    $terms = get_the_terms($post_id,WPSSTM_Core_Tracks::$artist_taxonomy);
    if ( is_wp_error($terms) ) return false;
    if ( !isset($terms[0]) ) return false;

    return $terms[0]->name;
}

function wpsstm_get_post_track($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;

    $terms = get_the_terms($post_id,WPSSTM_Core_Tracks::$track_taxonomy);
    if ( is_wp_error($terms) ) return false;
    if ( !isset($terms[0]) ) return false;

    return $terms[0]->name;
}

function wpsstm_get_post_album($post_id = null){
    global $post;
    if (!$post_id) $post_id = $post->ID;

    $terms = get_the_terms($post_id,WPSSTM_Core_Tracks::$album_taxonomy);
    if ( is_wp_error($terms) ) return false;
    if ( !isset($terms[0]) ) return false;

    return $terms[0]->name;
}

/*
milliseconds
*/
function wpsstm_get_post_duration($post_id = null,$seconds = false){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $ms = $s = 0;

    return get_post_meta( $post_id, WPSSTM_Core_Tracks::$duration_metakey, true );
}

function get_context_menu($items,$prefix){

    //wrap
    $items = array_map(
       function ($el) {
          return "<li>{$el}</li>";
       },
       (array)$items
    );

    $container_attr = array(
      'class'=> implode(' ',array(
        'wpsstm-actions-list',
        'wpsstm-collapsable-actions-list',
        sprintf('wpsstm-%s-actions',$prefix),
      ))
    );

    $handle= sprintf('<a class="wpsstm-actions-list-handle" href="#"><i class="fa fa-ellipsis-v" aria-hidden="true"></i></a>');
    return sprintf('<div %s>%s<ul>%s</ul></div>',wpsstm_get_html_attr($container_attr),$handle,implode("\n",$items));

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
        'placeholder' => null,
        'class' => null,
    );
    $options = wp_parse_args((array)$options,$option_defaults);

    //label
    $label_el = (isset($options['label'])) ? sprintf('<label for="%s">%s</label>',$options['id'],$options['label']) : null;

    //class
    $class_str = 'wpsstm-fullwidth input-group-field ';
    if ( isset($options['class']) ){
        $class_str .= $options['class'];
    }

    //input
    $input_attr = array(
        'id' =>             isset($options['id']) ? $options['id'] : null,
        'type' =>           'text',
        'name' =>           isset($options['name']) ? $options['name'] : null,
        'class' =>          $class_str,
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
