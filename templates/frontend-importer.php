<?php
global $wpsstm_tracklist;
?>

<div id="frontend-importer">

    <form action="<?php the_permalink();?>" method="POST">

        <div id="wpsstm-importer-step-profile-content" class="wpsstm-importer-section">
            <?php
            global $wpsstm_tracklist;

            $option = null;

            $text_input = sprintf(
                '<input type="text" name="wpsstm_frontend_wizard_url" value="%s" class="wpsstm-fullwidth" placeholder="%s" />',
                $option,
                __('Enter a tracklist URL or type a bang (eg. artist:Gorillaz)','wpsstm')
            );


            $submit_input = '<button type="submit" name="wpsstm_importer[action][load-url]" id="wpsstm_importer[action][load-url]" class="button button-primary wpsstm-icon-button"><i class="fa fa-search" aria-hidden="true"></i></button>';


            printf('<p class="wpsstm-icon-input" id="wpsstm-importer-search">%s%s</p>',$text_input,$submit_input);
            ?>
        </div>

        <?php
        //save settings

        //post ID
        if ($wpsstm_tracklist->post_id){
            ?>
            <input type="hidden" name="wpsstm_importer[post_id]" value="<?php echo $wpsstm_tracklist->post_id;?>" />
            <?php
        }

        wp_nonce_field('wpsstm_save_scraper_wizard','wpsstm_scraper_wizard_nonce',false);

        ?>
    </form>
    
    <?php
    
    //services
    wpsstm_locate_template( 'frontend-importer-services.php', true, false); //we need $require_once = false here or Jetpack will fuck up

    //bangs
    wpsstm_locate_template( 'frontend-importer-bangs.php', true, false); //we need $require_once = false here or Jetpack will fuck up

    //recent
    if ( wpsstm()->get_options('recent_wizard_entries') ) {
        wpsstm_locate_template( 'frontend-importer-entries.php', true, false );  //we need $require_once = false here or Jetpack will fuck up
    }
    
    ?>

</div>