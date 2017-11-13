jQuery(document).ready(function($){
    
    $(document).on( "wpsstmTrackSourcesDomReady", function( event, track_obj ) {
        var track_el = track_obj.track_el;

        // sort track sources
        track_obj.track_el.find('.wpsstm-track-sources-list').sortable({
            handle: '.wpsstm-source-reorder-action',
            update: function(event, ui) {
                console.log('update: '+ui.item.index())

                //get source
                var source_el = $(ui.item);
                var source_idx = Number(source_el.attr('data-wpsstm-source-idx'));
                var source_obj = track_obj.get_source_obj(source_idx);

                //new position
                source_obj.index = ui.item.index();
                track_obj.update_source_index(source_obj);
            }
        });
        

    });

    $(document).on( "wpsstmTrackSingleSourceDomReady", function( event, source_obj ) {

        //click on source trigger
        source_obj.source_el.find('.wpsstm-source-title').click(function(e) {
            e.preventDefault();
            source_obj.track.tracklist.play_track(source_obj.track.index,source_obj.index);
        });
        
        //delete source
        source_obj.source_el.find('.wpsstm-source-delete-action').click(function(e) {
            
            e.preventDefault();
            var promise = source_obj.delete_source();
            
            promise.done(function(data) {
                var source_instances = source_obj.get_source_instances();
                source_instances.remove();
                
                if ( source_el.hasClass('source-playing') ){
                    //TO FIX TO DO skip to next source ? what if it is the last one ?
                }
                
            })
            
        });

    });
    
    //suggest sources
    $(document).on("click", 'input#wpsstm-suggest-sources-bt', function(e){
        e.preventDefault();

        var bt = $(this);
        var track = bt.closest('[data-wpsstm-track-id]');
        var track_id = track.attr('data-wpsstm-track-id');
        var form = bt.closest('form');
        var sources_wrapper = $(form).find('.wpsstm-sources-edit-list-user');

        var ajax_data = {
            action:     'wpsstm_autosources_form',
            post_id:    track_id //TO FIX we should send a track_obj here (see track_obj.to_ajax())
        };

        var existing_rows_count = $(form).find('.wpsstm-source').length;

        return $.ajax({

            type: "post",
            url: wpsstmL10n.ajaxurl,
            data:ajax_data,
            dataType: 'json',
            beforeSend: function() {
                $(form).addClass('loading');
            },
            success: function(data){
                if (data.success === false) {
                    console.log(data);
                }else{
                    
                    bt.remove();
                    
                    if (data.new_html){
                        var $rows = $(data.new_html);
                        $(sources_wrapper).append($rows);
                        $(sources_wrapper).toggleChildren({
                            childrenShowCount:  true,
                            childrenMax:        4,
                            moreText:           '<i class="fa fa-angle-down" aria-hidden="true"></i>',
                            lessText:           '<i class="fa fa-angle-up" aria-hidden="true"></i>',
                        });
                    }

                }
            },
            complete: function() {
                $(form).removeClass('loading');
            }
        });
    });

    //add source
    $(document).on("click", '.wpsstm-source-icon-add', function(){
        event.preventDefault();
        
        var row = $(this).closest('.wpsstm-source');
        //auto source
        if ( Number(row.attr('data-wpsstm-community-source')) == 1 ){
            row.attr('data-wpsstm-community-source', '0');
            row.find('input').prop("disabled", false);
            row.removeClass('wpsstm-source-auto');
            return;
        }
        
        var wrapper = row.parent();
        var rows_list = wrapper.find('.wpsstm-source');
        var row_blank = rows_list.first();
        
        var empty_row = null;

        rows_list.each(function() {
            var input_url = $(this).find('input.wpsstm-editable-source-url');
            if ( !input_url.val() ){
                empty_row = $(this);
                return;
            }
        });

        if ( empty_row !== null ){
            empty_row.find('input.wpsstm-editable-source-url').focus();
        }else{
            var new_row = row_blank.clone();
            new_row.insertAfter( row_blank );
            var row_blank_input = row_blank.find('input.wpsstm-editable-source-url');
            row_blank.attr('data-wpsstm-community-source', '0');
            row_blank_input.prop("disabled", false);
            row_blank_input.val(''); //clear form
            row_blank_input.focus();
        }

    });
    
    //delete source
    $(document).on("click", '.wpsstm-source-icon-delete', function(){
        var wrapper = $(this).closest('.wpsstm-manage-sources-wrapper');
        var first_row = wrapper.find('.wpsstm-source').first();
        var row = $(this).closest('.wpsstm-source');
        
        if ( row.is(first_row) ){
            row.find('input.wpsstm-editable-source-url').val('');
        }else{
            row.remove();
        }
        
        
    });
    
    //submit
    /*
    $(document).on("click", 'input#wpsstm-update-sources-bt', function(e){
        e.preventDefault();
        var bt = $(this);
        console.log(bt);
    });
    */
    
    //toggle expand
    $('.wpsstm-sources-edit-list').toggleChildren();

})

class WpsstmTrackSource {
    constructor(source_html,track) {

        this.track =            track;
        this.source_el =        $(source_html);
        
        this.index =            Number(this.source_el.attr('data-wpsstm-source-idx'));
        this.post_id =          Number(this.source_el.attr('data-wpsstm-source-id'));
        this.src =              this.source_el.attr('data-wpsstm-source-src');
        this.type =             this.source_el.attr('data-wpsstm-source-type');
        this.can_play =         undefined;
        this.media =            undefined;
        
        //this.debug("new WpsstmTrackSource");

    }
    
    debug(msg){
        var prefix = "WpsstmTracklist #"+ this.track.tracklist.index +" - WpsstmTrack #" + this.track.index+" - WpsstmTrackSource #" + this.index + ": ";
        wpsstm_debug(msg,prefix);
    }

    get_track_el(){
        var self = this;
        return self.track_el.closest('[data-wpsstm-track-idx="'+self.track.index+'"]');
    }

    get_source_instances(ancestor){
        var self = this;
        var selector = '[data-wpsstm-tracklist-idx="'+self.track.tracklist.index+'"] [itemprop="track"][data-wpsstm-track-idx="'+self.track.index+'"] [data-wpsstm-source-idx="'+self.index+'"]';
        
        if (ancestor !== undefined){
            return $(ancestor).find(selector);
        }else{
            return $(selector);
        }
    }

    delete_source(){
        
        var self = this;
        var deferredObject = $.Deferred();
        var source_instances = self.get_source_instances();
        
        var ajax_data = {
            action:         'wpsstm_delete_source',
            post_id:        self.post_id
        };
        
        source_instances.addClass('source-loading');

        var ajax_request = $.ajax({

            type:       "post",
            url:        wpsstmL10n.ajaxurl,
            data:       ajax_data,
            dataType:   'json'
        })
        
        ajax_request.done(function(data){
            if (data.success === true){
                deferredObject.resolve();
            }else{
                console.log(data);
                deferredObject.reject(data.message);
            }
        });

        ajax_request.fail(function(jqXHR, textStatus, errorThrown) {
            deferredObject.reject();
        })

        ajax_request.always(function(data, textStatus, jqXHR) {
            source_instances.removeClass('source-loading');
        })
        
        return deferredObject.promise();
        
    }
    
    //reduce object for communication between JS & PHP
    to_ajax(){
        var self = this;
        var allowed = ['index','post_id'];
        var filtered = Object.keys(self)
        .filter(key => allowed.includes(key))
        .reduce((obj, key) => {
        obj[key] = self[key];
        return obj;
        }, {});
        return filtered;
    }

    init_source(){

        var self = this;
        var success = $.Deferred();
        

        var new_source = { src: self.src, 'type': self.type };
        
        self.debug("init_source: " + new_source.src);
        
        var audio_el = $('#wpsstm-player-audio');
        var source_instances = self.get_source_instances();

        $(audio_el).mediaelementplayer({
            classPrefix: 'mejs-',
            // All the config related to HLS
            hls: {
                debug:          wpsstmL10n.debug,
                autoStartLoad:  true
            },
            // Do not forget to put a final slash (/)
            pluginPath: 'https://cdnjs.com/libraries/mediaelement/',
            //audioWidth: '100%',
            stretching: 'responsive',
            features: ['playpause','loop','progress','current','duration','volume'],
            loop: false,
            success: function(mediaElement, originalNode, player) {

                self.media = mediaElement;
                wpsstm.current_media = self.media;

                $(self.media).on('loadeddata', function() {
                    $(document).trigger( "wpsstmMediaLoaded",[self.media,self] ); //custom event
                    self.can_play = true;

                    self.debug('media - loadeddata');
                    success.resolve();
                });

            },error(mediaElement) {
                // Your action when mediaElement had an error loading
                //TO FIX is this required ?
                console.log("mediaElement error");
                var source_instances = self.get_source_instances();
                source_instances.addClass('source-error');
                success.reject();
            }
        });

        //player
        self.media.pause();
        self.media.setSrc(new_source.src);
        self.media.load();
        
        return success.promise();

    }
    
    play_source(){

        var self = this;
        var success = $.Deferred();

        self.track.set_bottom_audio_el(); //build <audio/> el
        var tracklist_instances = self.track.tracklist.get_tracklist_instances();
        var track_instances = self.track.get_track_instances();
        var source_instances = self.get_source_instances();

        //TO FIX check if same source playing already ?
        //if (self.track.current_source_idx === self.index){
        //}

        var promise = self.init_source();

        promise.then(
            function(success_msg){

                $(self.media).on('error', function(error) {

                    self.can_play = false;

                    var source_instances = self.get_source_instances();

                    self.debug('media - error');

                    source_instances.addClass('source-error');

                    success.reject(error);

                });

                $(self.media).on('play', function() {


                    var trackinfo_sources = track_instances.find('[data-wpsstm-source-idx]');
                    $(trackinfo_sources).removeClass('source-playing');
                    
                    track_instances.removeClass('track-error track-loading');

                    tracklist_instances.addClass('tracklist-playing tracklist-has-played');
                    track_instances.addClass('track-playing track-has-played');
                    source_instances.addClass('source-playing source-has-played');

                    self.debug('media - play');



                    self.track.current_source_idx = self.index;

                    success.resolve(self);
                });

                $(self.media).on('pause', function() {
                    self.debug('player - pause');
                    tracklist_instances.removeClass('tracklist-playing');
                    track_instances.removeClass('track-playing');
                    source_instances.removeClass('source-playing');
                });

                $(self.media).on('ended', function() {
                    self.debug('media - ended');
                    tracklist_instances.removeClass('tracklist-playing');
                    track_instances.removeClass('track-playing track-active');
                    source_instances.removeClass('source-playing track-active');

                    //Play next song if any
                    self.track.tracklist.next_track_jump();
                });

                self.media.play();

            },
            function(error_msg){
                alert(error_msg);
                success.reject(error_msg);
            }

        )

        return success.promise();

    }

}


