<?php

$bang_items = $url_items = $service_items = array();
$list_services = $list_urls = $list_bangs = array();
$importers = WPSSTM_Core_Importer::get_importers();

if (!$importers || is_wp_error($importers) ) return;

/*
Bangs
*/

$bangs = array_filter($importers, function($importer) {return $importer['type']==='bang'; }); //only bangs

foreach((array)$bangs as $bang){
    $bang_items[] = sprintf('<code>%s</code> <span>%s</span>',$bang['label'],$bang['name']);
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
$list_bangs[] = sprintf('<li>%s</li>',sprintf(__('...Or type any %s!','wpsstm'),$urls_el));


/*
URLs
*/

$url_importers = array_filter($importers, function($importer) {return $importer['type']==='url'; }); //only URLs

foreach((array)$url_importers as $importer){
    $url_items[] = sprintf('<span>%s</span> <small>%s: %s</small>',$importer['name'],__('eg.','wpsstm'),$importer['label']);
}
//wrap
$list_urls = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $url_items
);

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
    if ($list_urls){
        ?>
        <div id="wpsstm-importer-urls">
            <h4><?php _e('Supported URLs','wpsstm');?></h4>
            <ul>
                <?php echo implode("\n",$list_urls); ?>
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