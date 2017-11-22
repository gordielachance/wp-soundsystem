(function($){
    
    $(document).on( "wpsstmTrackSourcesDomReady", function( event, track_obj ) {

        var track_el = track_obj.track_el;

        // sort track sources
        track_obj.track_el.find('.wpsstm-track-sources-list').sortable({
            axis: "y",
            handle: '#wpsstm-source-action-move a',
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
            source_obj.track.play_track(source_obj.index);
        });

        //delete source
        source_obj.source_el.find('#wpsstm-source-action-delete a').click(function(e) {

            e.preventDefault();
            var promise = source_obj.delete_source();

            promise.done(function(data) {
                var source_instances = source_obj.get_source_instances();
                source_instances.remove();

                if ( source_obj.source_el.hasClass('source-playing') ){
                    //TO FIX TO DO skip to next source ? what if it is the last one ?
                }

            })

        });

    });
})(jQuery);



class WpsstmTrackSource {
    constructor(source_html,track) {

        this.track =            track;
        this.source_el =        $(source_html);
        
        this.index =            Number(this.source_el.attr('data-wpsstm-source-idx'));
        this.post_id =          Number(this.source_el.attr('data-wpsstm-source-id'));
        this.src =              this.source_el.attr('data-wpsstm-source-src');
        this.type =             this.source_el.attr('data-wpsstm-source-type');
        this.can_play =         Boolean(this.type);
        this.media =            undefined;
        
        //this.debug("new WpsstmTrackSource");
        
        if (!this.can_play){
            this.source_el.addClass('source-error');
        }
        

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
                success.reject(error_msg);
            }

        )

        return success.promise();

    }

}


