<?php

global $post;
global $wpsstm_tracklist;
$wpsstm_tracklist->populate_tracks(array('posts_per_page'=>-1)); //we must have the tracks populated before we output the notices

?>

<div id="wizard-wrapper" <?php echo wpsstm_get_classes_attr('wizard-wrapper-frontend');?>>

    <?php

    //wizard notices
    if ( $notices_el = $wpsstm_tracklist->get_notices_output('wizard-header') ){
        echo $notices_el;
    }

    ?>

    <div id="wpsstm-wizard-step-source-content" class="wpsstm-wizard-step-content">
        <?php 
        wpsstm_wizard()->feed_url_callback();
        ?>
    </div>

    <?php

    
    

    //save settings
    
    //post ID
    if ($wpsstm_tracklist->post_id){
        ?>
        <input type="hidden" name="wpsstm_wizard[post_id]" value="<?php echo $wpsstm_tracklist->post_id;?>" />
        <?php
    }

    wp_nonce_field('wpsstm_save_scraper_wizard','wpsstm_scraper_wizard_nonce',false);
    
    ?>
</div>