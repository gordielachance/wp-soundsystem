<?php

$services = WPSSTM_Core_Importer::get_import_services();

if ( is_wp_error($services) ){
    //TODOU?
}

if (!$services) return;

//wrap
$list_els = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $services
);
?>
<section id="wpsstm-importer-services">
    <h3><?php _e('Supported services','wpsstm');?></h3>
    <ul>
        <?php echo implode("\n",$list_els); ?>
    </ul>
</section>