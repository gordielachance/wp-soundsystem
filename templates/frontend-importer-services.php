<?php

$items = array();
$services = WPSSTM_Core_Importer::get_import_services();

if ( $services && !is_wp_error($services) ){
    foreach($services as $service){
        $item = sprintf('<img src="%s" title="%s"/>',$service['image'],$service['name']);
        if ($url = $service['url']){
            $item = sprintf('<a href="%s" target="_blank">%s</a>',$url,$item);
        }

        $items[] = $item;
    }
}

if (!$items) return;

//wrap
$list_els = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $items
);
?>
<section id="wpsstm-importer-services">
    <h3><?php _e('Supported services','wpsstm');?></h3>
    <ul>
        <?php echo implode("\n",$list_els); ?>
    </ul>
</section>