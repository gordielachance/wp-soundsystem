<?php

$items = array();
$api_items = WPSSTM_Core_Importer::get_import_bangs();

if ( $api_items && !is_wp_error($api_items) ){
    foreach($api_items as $api_item){

        $items[] = sprintf('<strong>%s</strong> <code>%s</code>',$api_item['name'],$api_item['code']);
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
<section id="wpsstm-importer-bangs">
    <h3><?php _e('Bangs','wpsstm');?> (<small><?php _e('shortcuts','wpsstm');?>)</small></h3>
    <p>
    <?php _e('You can type those shortcuts in the input box above.','wpsstm');?>
    </p>
    <ul>
        <?php echo implode("\n",$list_items); ?>
    </ul>
</section>