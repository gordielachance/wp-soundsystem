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

/**
Tracks are referenced in playlists using a track post meta.
The little trick here is that the playlist ID is contained in the post meta name (eg. wpsstm_tracklist_19).
This is kind of a hack but avoid us to use a specific table for playlist tracks.
The meta value will be either the track order (for regular playlists) or a timestamp (for live playlists)

!!! $playlist_id value could be set to 'only' or 'exclude', so do NOT check for numeric value or for existing post here.
**/

function wpsstm_get_tracklist_entry_metakey($tracklist_id){
    return sprintf('wpsstm_tracklist_%s',$tracklist_id);
}



/*
Get IDs of the parent tracklists (albums / playlists / live playlists) of a track.
*/

function wpsstm_get_tracklist_ids_for_track($post_id){
    global $wpdb;
    $query = $wpdb->prepare( "SELECT meta_key FROM $wpdb->postmeta WHERE `post_id`='%s' AND `meta_key` LIKE '%s'", $post_id, 'wpsstm_tracklist_%' );
	$meta_keys = $wpdb->get_col( $query );
    
    $ids = array();
    
    foreach ((array)$meta_keys as $meta_key){
        $str_split = explode('wpsstm_tracklist_',$meta_key);
        if( isset($str_split[1]) ) $ids[] = $str_split[1];
    }

    return $ids;
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


/**
 * Locate template.
 *
 * Locate the called template.
 * Search Order:
 * 1. /themes/CURRENT_THEME/wpsstm/$template_name
 * 2. /themes/CURRENT_THEME/$template_name
 * 3. /plugins/wpsstm/_inc/templates/$template_name.
 *
 * @since 1.0.0
 *
 * @param 	string 	$template_name			Template to load.
 * @param 	string 	$string $template_path	Path to templates.
 * @param 	string	$default_path			Default path to template files.
 * @return 	string 							Path to the template file.
 */
function wpsstm_locate_template( $template_name, $template_path = '', $default_path = '' ) {
	// Set variable to search in wpsstm folder of theme.
	if ( ! $template_path ) :
		$template_path = 'wpsstm/';
	endif;
	// Set default plugin templates path.
	if ( ! $default_path ) :
		$default_path = wpsstm()->plugin_dir . 'templates/'; // Path to the template folder
	endif;
	// Search template file in theme folder.
	$template = locate_template( array(
		$template_path . $template_name,
		$template_name
	) );
	// Get plugins template file.
	if ( ! $template ) :
		$template = $default_path . $template_name;
	endif;
	return apply_filters( 'wpsstm_locate_template', $template, $template_name, $template_path, $default_path );
}

function wpsstm_get_player($post_id = false){
    $provider = null;
    $sources = wpsstm_get_sources($post_id);
    foreach ((array)$sources as $source){
        if ($provider = wpsstm_get_provider($source->link_url) ){
            if ($player = $provider->get_player() ){
                return $player;
            }
        }
    }    
}

/**
Returns the class instance for a wp music post id
Requires a post_id, global $post is not always available here
**/
function wpsstm_get_class_instance($post_id){
    $post_type = get_post_type($post_id);

    switch($post_type){

        case wpsstm()->post_type_artist:
            return wpsstm_artists();
        break;

        case wpsstm()->post_type_track:
            return wpsstm_tracks();
        break;

        case wpsstm()->post_type_album:
            return wpsstm_albums();
        break;

        case wpsstm()->post_type_playlist:
            return wpsstm_playlists();
        break;

        case wpsstm()->post_type_live_playlist:
            return wpsstm_live_playlists();
        break;

    }
}

function wpsstm_get_user_input_url(){
    $form_qvar = wpsstm_live_playlists()->qvar_url_input;
    $form_url = ( isset($_REQUEST[$form_qvar]) ) ? $_REQUEST[$form_qvar] : null;
    return $form_url;
}
