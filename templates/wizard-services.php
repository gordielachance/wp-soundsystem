<?php
$services = array();
$links = apply_filters('wpsstm_wizard_service_links',array());

if ( empty($links) ) return;

//wrap
$list_els = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $links
);
?>
<section id="wpsstm-wizard-services">
    <h3><?php _e('Supported services','wpsstm');?></h3>
    <ul>
        <?php echo implode("\n",$list_els); ?>
    </ul>
</section>