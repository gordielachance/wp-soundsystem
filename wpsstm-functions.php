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

/*
Filter an array/object on one of its key/property value
Eg. filter sources array by url : if several values have the same url, keep only the first value met.
https://stackoverflow.com/a/6057401/782013
*/

function wpsstm_array_unique_by_subkey($array,$subkey){
    
    $temp = array();
    
    $unique = array_filter($array, function ($v) use (&$temp,$subkey) {
        
        if ( is_object($v) ) $v = (array)$v;
        
        if ( !array_key_exists($subkey,$v) ) return false;

        if ( in_array($v[$subkey], $temp) ) {
            return false;
        } else {
            array_push($temp, $v[$subkey]);
            return true;
        }
    });
    
    return $unique;
}

/*
Get the IDs of subtracks; by type (static|live) and optional tracklist id.
Used for subtrack queries; which will "filter" those subtracks (by post status, etc.); while this array will allow us to sort tracks.
*/

function wpsstm_get_raw_subtrack_ids($type='any',$tracklist_id=null){
    global $wpdb;
    
    $type_clauses = array();
    $subtrack_ids = array();
    $type_clauses_str = $parent_clause_str = null;
    
    if ($tracklist_id){
        $parent_clause_str = sprintf(" AND `post_id` = '%d'",$tracklist_id);
    }
    
    if ( ($type=='any') || ($type=='static') ) {
        $type_clauses[] = sprintf("`meta_key` = '%s'",wpsstm_playlists()->subtracks_static_metaname);
    }
    if ( ($type=='any') || ($type=='live') ) {
        $type_clauses[] = sprintf("`meta_key` = '%s'",wpsstm_live_playlists()->subtracks_live_metaname);
    }
    
    if ( $type_clauses ) $type_clauses_str = sprintf('(%s)',implode(' OR ',$type_clauses));
    
    $query = "SELECT meta_value FROM $wpdb->postmeta WHERE " . $type_clauses_str . $parent_clause_str;

    $tracklists_subtrack_ids = $wpdb->get_col( $query );
    
    //unserialize all those substracks lists
    foreach((array)$tracklists_subtrack_ids as $meta){
        $ids = maybe_unserialize($meta);
        $subtrack_ids = array_merge($subtrack_ids,(array)$ids);
    }
    
    return array_unique($subtrack_ids);
    
}

/**
* Make a nested HTML list from a multi-dimensionnal array.
*/

function wpsstm_get_list_from_array($input,$parent_slugs=array() ){
    
    $output = null;
    $output_classes = array("pure-tree");
    if ( empty($parent_slugs) ){
        $output_classes[] =  'main-tree';
    }
    
    
   foreach($input as $key=>$value){
        
       //if (!$value) continue; //ignore empty values
       
        $data_attr = $label = null;
        $checkbox_classes = array("checkbox-tree-checkbox");
        $item_classes = array("checkbox-tree-item");
       
        if( is_array($value) ){
            $parent_slugs[] = $key;
            $li_value = wpsstm_get_list_from_array($value,$parent_slugs);

            $item_classes[] = 'checkbox-tree-parent';
        }else{
            $li_value = $value;
        }
       
       if (!$li_value) continue;
       

       
        //$u_key = md5(uniqid(rand(), true));
        $u_key = implode('-',$parent_slugs);
        $data_attr = sprintf(' data-array-key="%s"',$key);

        $checkbox_classes_str = wpsstm_get_classes_attr($checkbox_classes);
        $item_classes_str = wpsstm_get_classes_attr($item_classes);
        $checkbox = sprintf('<input type="checkbox" %1$s id="%2$s"><label for="%2$s" class="checkbox-tree-icon">%3$s</label>',$checkbox_classes_str,$u_key,$key);

        $output.= sprintf('<li%1$s%2$s>%3$s%4$s</li>',$item_classes_str,$data_attr,$checkbox,$li_value);
    }
    
    if ($output){
        $output_classes_str = wpsstm_get_classes_attr($output_classes);
        return sprintf('<ul %s>%s</ul>',$output_classes_str,$output);
    }
    

}

function wpsstm_get_transients_by_prefix( $prefix ) {
	global $wpdb;
    
    $names = array();
    
	// Add our prefix after concating our prefix with the _transient prefix
	$name = sprintf('_transient_%s_',$prefix);
	// Build up our SQL query
	$sql = "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE '%s'";
	// Execute our query
	$transients = $wpdb->get_col( $wpdb->prepare( $sql, $name . '%' ) );

	// If if looks good, pass it back
	if ( $transients && ! is_wp_error( $transients ) ) {
        
        foreach((array)$transients as $real_key){
            $names[] = str_replace( '_transient_', '', $real_key );
        }
        
		return $names;
	}
	// Otherise return false
	return false;
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
    $url_parsed = parse_url($url);
    if ( !isset($url_parsed['host']) ) return;

    $host_with_subdomain = $url_parsed['host'];
    $host_split = explode(".", $host_with_subdomain);
    $domain = (array_key_exists(count($host_split) - 2, $host_split)) ? $host_split[count($host_split) - 2] : $host_split[count($host_split) - 1];
    
    return $domain;
}

function wpsstm_array_recursive_diff($aArray1, $aArray2) {
  $aReturn = array();

  foreach ($aArray1 as $mKey => $mValue) {
    if (array_key_exists($mKey, $aArray2)) {
      if (is_array($mValue)) {
        $aRecursiveDiff = wpsstm_array_recursive_diff($mValue, $aArray2[$mKey]);
        if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
      } else {
        if ($mValue != $aArray2[$mKey]) {
          $aReturn[$mKey] = $mValue;
        }
      }
    } else {
      $aReturn[$mKey] = $mValue;
    }
  }
  return $aReturn;
} 

function wpsstm_is_backend(){
    return ( is_admin() && !wpsstm_is_ajax() );
}
function wpsstm_is_ajax(){
    return ( defined( 'DOING_AJAX' ) && DOING_AJAX );
}

/*
Get a post tracklist.
Use this instead of 'new WP_SoundSystem_Tracklist' or 'new WP_SoundSystem_Remote_Tracklist' since it will load the right class (preset, live tracklist, etc.)
*/
function wpsstm_get_post_tracklist($post_id=null){
    global $post;

    if (!$post_id && $post) $post_id = $post->ID;
    
    $tracklist = new WP_SoundSystem_Tracklist(); //default
    $post_type = get_post_type($post_id);

    switch ($post_type){
        case wpsstm()->post_type_track:
            $tracklist = new WP_SoundSystem_Single_Track_Tracklist($post_id);
        break;
        case wpsstm()->post_type_playlist:
        case wpsstm()->post_type_album:
            $tracklist = new WP_SoundSystem_Tracklist($post_id);
            
        break;
        case wpsstm()->post_type_live_playlist:
            $tracklist = wpsstm_get_post_live_tracklist($post_id);
        break;
    }

    //wpsstm()->debug_log( $tracklist, "wpsstm_get_post_tracklist()");
    return $tracklist;
    
}

function wpsstm_get_post_live_tracklist($post_id=null){

    /*
    Check if one of the available presets (which are extending WP_SoundSystem_Remote_Tracklist) can load the tracklist url
    If yes, use this preset instead of WP_SoundSystem_Remote_Tracklist; 
    and repopulate post id since it is not populated in the registered preset.
    */
    
    $tracklist = new WP_SoundSystem_Remote_Tracklist($post_id);

    if ( $feed_url = wpsstm_get_live_tracklist_url($post_id) ){

        foreach((array)wpsstm_live_playlists()->get_available_presets() as $preset){

            if ( $preset->can_load_tracklist_url($feed_url) ){
                $tracklist = $preset;
                $tracklist->__construct($post_id);
                wpsstm()->debug_log( json_encode(array('post_id'=>$post_id,'feed_url'=>$feed_url,'preset_name'=>$tracklist->preset_name)), "wpsstm_get_post_live_tracklist()");
                break;
            }
        }
    }
    
    return $tracklist;
}