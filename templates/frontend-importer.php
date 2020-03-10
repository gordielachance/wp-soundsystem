<div id="frontend-importer">

    <form action="<?php the_permalink();?>" method="POST">

        <div id="wpsstm-importer-step-profile-content" class="wpsstm-importer-section">
            <?php

            $text_input = sprintf(
                '<input type="text" name="wpsstm_frontend_wizard_url" class="wpsstm-fullwidth" placeholder="%s" />',
                __('Enter a tracklist URL or type a bang (eg. artist:Gorillaz)','wpsstm')
            );

            $submit_input = '<button type="submit" name="wpsstm_importer[action][load-url]" id="wpsstm_importer[action][load-url]" class="button button-primary wpsstm-icon-button"><i class="fa fa-search" aria-hidden="true"></i></button>';

            printf('<p class="wpsstm-icon-input" id="wpsstm-importer-search">%s%s</p>',$text_input,$submit_input);
            ?>
        </div>

        <?php
        //save settings

        wp_nonce_field('wpsstm_save_scraper_wizard','wpsstm_scraper_wizard_nonce',false);

        ?>
    </form>

    <?php
    //importers
    wpsstm_locate_template( 'importers-list.php', true, false); //we need $require_once = false here or Jetpack will fuck up

    //recent
    if ( wpsstm()->get_options('recent_wizard_entries') ) {
        wpsstm_locate_template( 'importer-entries.php', true, false );  //we need $require_once = false here or Jetpack will fuck up
    }

    ?>

</div>
