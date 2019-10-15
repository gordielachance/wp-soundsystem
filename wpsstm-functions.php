<?php

/**
 * Get a value in a multidimensional array
 * http://stackoverflow.com/questions/1677099/how-to-use-a-string-as-an-array-index-path-to-retrieve-a-value
 * @param type $keys
 * @param type $array
 * @return type
 */
function wpsstm_get_array_value($keys = null, $array){
    if (!$keys) return $array;
    
    $keys = (array)$keys;
    $first_key = $keys[0];
    if(count($keys) > 1) {
        if ( isset($array[$keys[0]]) ){
            return wpsstm_get_array_value(array_slice($keys, 1), $array[$keys[0]]);
        }
    }elseif (isset($array[$first_key])){
        return $array[$first_key];
    }
    
    return false;
}

function wpsstm_is_associative_array(array $arr){
    if (array() === $arr) return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

/**
 * Outputs the html readonly attribute.  Inspired by core function disabled().
 *
 * Compares the first two arguments and if identical marks as readonly
 *
 * @since 3.0.0
 *
 * @param mixed $readonly One of the values to compare
 * @param mixed $current  (true) The other value to compare if not just true
 * @param bool  $echo     Whether to echo or just return the string
 * @return string html attribute or empty string
 */
function wpsstm_readonly( $readonly, $current = true, $echo = true ) {
	return __checked_selected_helper( $readonly, $current, $echo, 'readonly' );
}


/*
Locate a template & fallback in plugin's folder
*/
function wpsstm_locate_template( $template_name, $load = false, $require_once = true ) {
    
    if ( !$located = locate_template( 'wpsstm/' . $template_name ) ) {
        // Template not found in theme's folder, use plugin's template as a fallback
        $located = wpsstm()->plugin_dir . 'templates/' . $template_name;
    }
    
    if ( $load && ('' != $located) ){
        load_template( $located, $require_once );
    }
    
    return $located;
}

function wpsstm_get_url_domain($url){
    //https://stackoverflow.com/a/16027164/782013
    $pieces = parse_url($url);
    $domain = isset($pieces['host']) ? $pieces['host'] : $pieces['path'];
    if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
        return $regs['domain'];
    }
    return false;
}

function wpsstm_is_local_file($url){
    $domain = wpsstm_get_url_domain( get_site_url() );
    $file_domain = wpsstm_get_url_domain($url);
    return ($domain == $file_domain);
}

/*
Simple check for XSPF urls; by URL extension.
//TOUFIX TOUCHECK should we rather check for a mime? But sometimes xspf mimetype is set wrongly.
*/
function wpsstm_is_xpsf_url($url){

    $ext = null;

    //we don't want url parameters
    if ( ( $url = parse_url($url) ) && isset($url['path']) ) {
       $ext = pathinfo($url['path'], PATHINFO_EXTENSION);
    }

    $bool = ($ext === 'xspf');

    return apply_filters('wpsstm_xpsf_url',$bool);
}

//https://gist.github.com/boonebgorges/5510970
function wpsstm_recursive_parse_args( &$a, $b ) {
    $a = (array) $a;
    $b = (array) $b;
    $r = $b;
    foreach ( $a as $k => &$v ) {
        if ( is_array( $v ) && isset( $r[ $k ] ) ) {
            $r[ $k ] = wpsstm_recursive_parse_args( $v, $r[ $k ] );
        } else {
            $r[ $k ] = $v;
        }
    }
    return $r;
}


function wpsstm_is_backend(){
    return ( is_admin() && !wp_doing_ajax() );
}

function wpsstm_shorten_text($str,$skiptext = ' ... '){
    $length = strlen($str);
    if($length > 45){
        $length = $length - 30;
        $first = substr($str, 0, -$length);
        $last = substr($str, -15);
        $new = $first.$skiptext.$last;
        return $new;
    }else{
        return $str;
    }
}

function wpsstm_get_notice($msg){
    return sprintf('<div class="wpsstm-block-notice"><span>%s</span><a href="#" class="wpsstm-close-notice"><i class="fa fa-close"></i></a></div>',$msg);
}