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

$url_patterns = array_filter($all_patterns, function($pattern) {return $pattern['type']==='url'; }); //only URLs

$url_items = array();
foreach((array)$url_patterns as $pattern){
    $importer_idx = $pattern['importer'];
    $importer = $importers[$importer_idx];
    $name = wpsstm_get_array_value(array('infos','name'),$importer);
    $url_items[] = sprintf('<span>%s</span> <code>%s: %s</code>',$name,__('eg.','wpsstm'),$pattern['helper']);
}

//sort alphabetically //TOUFIX
//usort($url_items, function ($a, $b) { return strcasecmp($a["name"], $b["name"]); });


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

$bang_patterns = array_filter($all_patterns, function($pattern) {return $pattern['type']==='bang'; }); //only URLs

$bang_items = array();
foreach((array)$bang_patterns as $pattern){
    $importer_idx = $pattern['importer'];
    $importer = $importers[$importer_idx];
    $name = wpsstm_get_array_value(array('infos','name'),$importer);
    $bang_items[] = sprintf('<code>%s</code> <span>%s</span>',$pattern['helper'],$name);
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
$list_bangs[] = sprintf('<li>%s%s</li>',sprintf(__('...Or type any %s!','wpsstm'),$urls_el),$list_bangs);

/*
services
*/
$domains = WPSSTM_Core_Importer::get_importers_by_domain();

if ( $domains && !is_wp_error($domains) ){

    foreach($domains as $domain){

        $item = sprintf('<img src="%s" title="%s"/>',$domain['image'],$domain['name']);
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
