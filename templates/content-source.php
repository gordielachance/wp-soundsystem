<?php
global $wpsstm_source;

$source = $wpsstm_source;
$provider = wpsstm_get_source_provider();

?>
<li class="wpsstm-source" data-wpsstm-source-id="<?php the_ID(); ?>" data-wpsstm-source-idx="<?php echo ($source->position - 1);?>" data-wpsstm-source-type="<?php echo wpsstm_get_source_mime();?>" data-wpsstm-source-src="<?php echo wpsstm_get_source_url();?>" data-wpsstm-auto-source="1">
    <i class="wpsstm-source-error fa fa-exclamation-triangle" aria-hidden="true"></i> 
    <a class="wpsstm-source-provider-link wpsstm-icon-link" href="<?php echo wpsstm_get_source_url(true);?>" target="_blank" title="Youtube">
        <?php echo $provider->icon;?>
        <span class="wpsstm-source-title"><?php the_title();?></span>
    </a>
</li>