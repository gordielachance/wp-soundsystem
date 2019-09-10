<?php

//services

$items = array();
$api_items = WPSSTM_Core_Importer::get_import_services();

if ( $api_items && !is_wp_error($api_items) ){
    foreach($api_items as $api_item){
        $item = sprintf('<img src="%s" title="%s"/>',$api_item['image'],$api_item['name']);
        if ($url = $api_item['url']){
            $item = sprintf('<a href="%s" target="_blank">%s</a>',$url,$item);
        }

        $items[] = $item;
    }
}

if (!$items) return;

//wrap
$list_items = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $items
);

?>
<section id="wpsstm-importer-services">
    <h3><?php _e('Supported services','wpsstm');?></h3>
    <ul>
        <?php echo implode("\n",$list_items); ?>
    </ul>
</section>