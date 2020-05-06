<?php

$bang_items = $url_items = $service_items = array();
$list_services = $list_urls = $list_bangs = array();
$importers = WPSSTM_Core_Importer::get_importers();

if (!$importers || is_wp_error($importers) ) return;

$all_patterns = array();

foreach($importers as $importer_idx=>$importer){
  $patterns = wpsstm_get_array_value(array('infos','patterns'),$importer);
  foreach((array)$patterns as $pattern){
    $pattern['importer'] = $importer_idx;
    $all_patterns[] = $pattern;
  }
}

/*
URLs
*/

function isUrlPattern($regex){
  return ( strpos($regex, '^http') === 0 );
}

function replaceCaptureGroup($string){
  return strtr($string, array('<' => '<em>', '>' => '</em>'));
}

$url_patterns = array_filter($all_patterns, function($pattern) {return isUrlPattern($pattern['regex']); }); //only URLs

foreach((array)$url_patterns as $key=>$pattern){
  $importer_idx = $pattern['importer'];
  $importer = $importers[$importer_idx];
  $url_patterns[$key]['service_name'] = wpsstm_get_array_value(array('infos','name'),$importer);
}


//sort alphabetically
usort($url_patterns, function ($a, $b) { return strcasecmp($a["service_name"], $b["service_name"]); });

$url_items = array();
foreach((array)$url_patterns as $pattern){
    $url_items[] = sprintf('<span>%s</span> <code>%s: %s</code>',$pattern['service_name'],__('eg.','wpsstm'),replaceCaptureGroup($pattern['helper']));
}


//wrap
$list_urls = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $url_items
);
$list_urls = sprintf('<ul id="wpsstm-importer-urls">%s</ul>',implode("\n",$list_urls));

/*
Bangs
*/

$bang_patterns = array_filter($all_patterns, function($pattern) {return !isUrlPattern($pattern['regex']); }); //only URLs

foreach((array)$bang_patterns as $key=>$pattern){
    $importer_idx = $pattern['importer'];
    $importer = $importers[$importer_idx];
    $bang_patterns[$key]['importer_name'] = wpsstm_get_array_value(array('infos','name'),$importer);
}

//sort alphabetically
usort($bang_patterns, function ($a, $b) { return strcasecmp($b["importer_name"], $a["importer_name"]); });

$bang_items = array();
foreach((array)$bang_patterns as $pattern){
    $bang_items[] = sprintf('<code>%s</code> <span>%s</span>',replaceCaptureGroup($pattern['helper']),$pattern['importer_name']);
}



//wrap
$list_bangs = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $bang_items
);

//supported URL shortcut
$urls_el = sprintf('<a id="wpsstm-list-urls-bt" href="#">%s</a>',__('supported URL','wpsstm'));
$list_bangs[] = sprintf('<li>%s%s</li>',sprintf(__('...Or type any %s!','wpsstm'),$urls_el),$list_urls);

/*
services
*/
$domains = WPSSTM_Core_Importer::get_importers_by_domain();

if ( $domains && !is_wp_error($domains) ){

    foreach($domains as $domain){

        $item = sprintf('<a href="%s" title="%s" target="_blank"><img src="%s" title="%s"/></a>',$domain['service_url'],$domain['name'],$domain['image'],$domain['name']);
        $service_items[] = $item;
    }
    //wrap
    $list_services = array_map(
       function ($el) {
          return "<li>{$el}</li>";
       },
       $service_items
    );
}

?>
<section id="wpsstm-importers">
    <?php
    if ($list_bangs){
        ?>
        <div id="wpsstm-importer-bangs">
            <h4><?php _e('Bangs','wpsstm');?> (<small><?php _e('shortcuts','wpsstm');?>)</small></h4>
            <ul>
                <?php echo implode("\n",$list_bangs); ?>
            </ul>
        </div>
        <?php
    }
    if ($list_services){
        ?>
        <div id="wpsstm-importer-services">
            <h4><?php _e('Supported services','wpsstm');?></h4>
            <ul>
                <?php echo implode("\n",$list_services); ?>
            </ul>
        </div>
        <?php
    }
    ?>
</section>
