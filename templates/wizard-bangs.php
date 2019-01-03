<?php
$links = apply_filters('wpsstm_wizard_bang_links',array());
if ( empty($links) ) return;

//wrap
$list_els = array_map(
   function ($el) {
      return "<li>{$el}</li>";
   },
   $links
);
?>
<section id="wpsstm-wizard-shortcuts">
    <h3><?php _e('Bangs','wpsstm');?> (<small><?php _e('shortcuts','wpsstm');?>)</small></h3>
    <p>
    <?php _e('You can type those shortcuts in the input box above. Click them for more information.','wpsstm');?>
    </p>
    <ul>
        <?php echo implode("\n",$list_els); ?>
    </ul>
</section>