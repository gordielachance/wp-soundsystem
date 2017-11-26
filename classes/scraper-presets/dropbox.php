<?php

function wpsstm_filter_dropbox_url($url){

    $domain = wpsstm_get_url_domain($url );

    //dropbox : convert to raw link
    if ($domain=='dropbox'){
        $url_no_args = strtok($url, '?');
        $url = add_query_arg(array('raw'=>1),$url_no_args); //http://stackoverflow.com/a/11846251/782013
    }

    return $url;
}

add_filter('wpsstm_live_tracklist_url','wpsstm_filter_dropbox_url', 5); //priority before presets