<?php

global $wpsstm_source;
$source = $wpsstm_source;
?>

<a class="<?php echo implode( ' ',$source->get_source_class() );?>" data-wpsstm-source-id="<?php the_ID(); ?>" data-wpsstm-source-idx="<?php echo ($source->position - 1);?>" data-wpsstm-source-type="<?php echo $source->type;?>" data-wpsstm-source-src="<?php echo $source->stream_url;?>" data-wpsstm-auto-source="<?php echo (int)$source->is_community;?>" href="<?php echo $source->url;?>" target="_blank" title="<?php the_title();?>">
    <span class="wpsstm-provider-icon"><?php echo $source->provider->icon;?></span>
    <span class="wpsstm-source-title"><?php the_title();?></span>
</a>