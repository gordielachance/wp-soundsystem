<?php

global $wpsstm_source;
global $wpsstm_track;

$wpsstm_source->populate_source_provider();
$source = $wpsstm_source;

if(!$wpsstm_track){
    $wpsstm_track = new WP_SoundSystem_Track($wpsstm_source->track_id);
}

?>

<a class="<?php echo implode( ' ',$source->get_source_class() );?>" data-wpsstm-source-id="<?php echo $source->post_id; ?>" data-wpsstm-source-idx="<?php echo $wpsstm_track->current_source;?>" data-wpsstm-source-type="<?php echo $source->type;?>" data-wpsstm-source-src="<?php echo $source->stream_url;?>" data-wpsstm-auto-source="<?php echo (int)$source->is_community;?>" href="<?php echo $source->url;?>" target="_blank" title="<?php echo $source->title;?>">
    <span class="wpsstm-provider-icon"><?php echo $source->provider->icon;?></span>
    <span class="wpsstm-source-title"><?php echo $source->title;?></span>
</a>