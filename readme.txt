=== WP SoundSystem ===
Contributors: grosbouff
Donate link: http://bit.ly/gbreant
Tags: music,library,playlists,collection,artists,tracks,albums,MusicBrainz,xspf
Requires at least: 3.9
Tested up to: 4.8
Stable tag: trunk
License: GPLv2 or later

Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  The perfect fit for your music blog !

== Description ==

Manage a music library within Wordpress; including playlists, tracks, artists, albums and live playlists.  It's the perfect fit for your music blog !

= Several new post types =

Playlists, Live Playlists, Albums, Artists, Tracks and Track Sources each uses a custom post type, so you can easily manage/extend them.

= Playlists =

Managing the playlist tracks is a piece of cake using the *Tracklist metabox*:
Add or remove tracks on the fly, reorder them, and link one or several music sources to each track.

Import a tracklist from a file or a music service like Spotify using the *Remote Tracklist Manager* (see below).

= Audio player =

When viewing a post that contains a tracklist, an audio player will show up to play your tracks !

**Supported sources**: Youtube, Soundcloud, regular audio files.

**Attention:** The audio player uses the native [MediaElement.js](http://www.mediaelementjs.com/) media framework.
The current version that is shipped with Wordpress is obsolete; so you'll need to upgrade it manually (see [ticket#39686](https://core.trac.wordpress.org/ticket/39686)) until it is merged in Wordpress (planned for 4.9).

Once you patched Wordpress, add this line to your config.php file to ignore Wordpress auto-updates (or your patch will be erased) :
`define( 'WP_AUTO_UPDATE_CORE', false );`

= Track Sources =

If you didn't set sources for your tracks (see below) and that the **autosource** option is enabled; the audio player will try to find an online source automatically (Youtube, Soundcloud, ...) based on the track informations.

Those links will be used by the audio player (see above) to play the track if the source URL is supported.

= Remote Tracklist Manager Metabox =

Enter the URL of a tracklist (eg. a local XSPF file, a Spotify Playlist, a radio station page...) to scrape its data.

Popular services like Spotify or Radionomy are automated through presets; and you will not need to do anything to retrieve their tracklists.

But if the URL is not recognized, the advanced wizard will show up and you will need to enter some extra informations to get the tracklist data.

This requires to be somewhat familiar with [jQuery selectors](http://www.w3schools.com/jquery/jquery_ref_selectors.asp).

**Native presets**: Last.FM, Spotify, Radionomy, Deezer, SomaFM, BBC, Slacker, Soundcloud, Twitter, Soundsgood, Hype Machine, Reddit, Indie Shuffle, RadioKing, Online Radio Box.

You may also propose a **Frontend Tracklist Importer** to your visitors: just create a blank page and set its ID for the *Frontend wizard page ID* field in the plugin settings page.

Demo on [spiff-radio.org](http://www.spiff-radio.org/?p=213).

= Live Playlists =

Live Playlists lets you grab a tracklist from a remote URL and remains **synchronised** with it.  
For example, you can load a radio station page and the plugin will keep its tracklist up-to-date automatically !

**How does it work ?**  Each time the tracklist refreshes; old tracks are flushed and the new ones are inserted.  If one of the old tracks appears in other playlists or is liked by some users; it will not be trashed.

Demo on [spiff-radio.org](http://www.spiff-radio.org/?post_type=wpsstm_live_playlist).

= MusicBrainz =

When managing a track, artist or album, the plugin can search for its **MusicBrainz ID**.
It makes it easier to identify the items, and loads various metadatas from [MusicBrainz](https://musicbrainz.org/) — an open data music database.
For example, when creating an album post, you can load its tracklist from the MusicBrainz datas; so you don't need to enter each track manually.

= Last.FM =

The audio player can **scrobble** tracks to your Last.fm account; or add tracks to your Last.fm favorites.

= Shortcodes =

`[wpsstm-track post_id="150"]`

To embed the single track #150.
Optional arguments: *post_id*.

`[wpsstm-tracklist post_id="160"]`

To embed the tracklist from the post #160.  
Works for albums, playlists and live playlists.
Optional arguments: *post_id*,*max_rows*.

= Donate! =

It truly took me a LOT of time to code this plugin, and I decided to release it for free - without any "Premium" plans.
If you like it, please consider [making a donation](http://bit.ly/gbreant).
This would be very appreciated — Thanks !

= Dependencies =

* [phpQuery](https://github.com/punkave/phpQuery) - a PHP port of jQuery selectors
* [PHP Last.FM API](https://github.com/matt-oakes/PHP-Last.fm-API) - Last.fm scrobbling
* [forceutf8](https://github.com/neitanod/forceutf8) - fixes mixed encoded strings

= Contributors =

Contributors [are listed here](https://github.com/gordielachance/wp-soundsystem/contributors)

= Notes =

For feature request and bug reports, please use the [Github Issues Tracker](https://github.com/gordielachance/wp-soundsystem/issues).

If you are a plugin developer, [we would like to hear from you](https://github.com/gordielachance/wp-soundsystem). Any contribution would be very welcome.

== Installation ==

This plugin requires PHP Version 5.4 or later.

1. Upload the plugin to your blog and Activate it.
2.  Go to the settings page and setup the plugin.

== Frequently Asked Questions ==

= The audio player does not go to the next track ! =

This only happens with Chrome: due to a restriction of this browser, the player will not be able to load the next track if it is opened in a background tab. 
Here's [how to fix it](https://github.com/gordielachance/wp-soundsystem/issues/18).

= How can I display the tracklist of a post in my templates ? =

Use the tracklist shortcode **[wpsstm-tracklist]** in your post content (see the *shortcodes* section above), or use those functions directly in your templates:

`<?php
$tracklist = wpsstm_get_post_tracklist(); //optionally accepts a post_id as argument
echo $tracklist->get_tracklist_html();
?>`

= How does live playlists handle tracks ? =

Each time the tracklist refreshes, old tracks are flushed and replaced by the new ones.
A track that belongs to another playlist or that has been favorited by a user will only be removed from the live playlist without being trashed.

== Screenshots ==

1. Settings page
2. Artists menu
3. Tracks menu
4. Albums menu
5. Playlists menu
6. Tracklist metabox
7. Tracklist parser metabox
8. Music sources metabox

== Changelog ==

= 1.7.1 =
* option to disable default styling
* improved CSS
* JS: pass tracklist options in get_tracklist_request()

= 1.7.0 =
* wizard : share / export works properly + better way of populating wizard
* wizard : notice if using a preset and that the scraper options do not match the preset scraper options
* some bug fixes & improvements

= 1.6.9 =
* fix typo when including reddit preset

= 1.6.8 =
* better way to include presets
* more tracklist settings

= 1.6.7 =
* fix broken JS player navigation
* fix previous_tracklist_jump() / next_tracklist_jump() when repeat is enabled and that no other playlist is available to play
* tracklist option to hide columns (JS) when all of its cells have the same value (or are empty)

= 1.6.5 =
* new filter 'wpsstm_input_tracks'
* upgraded validate_tracks()
* ability to load tracklist and sources without storing the tracklist neither the sources; this is a huge thing!
* lots of improvements on the Wizard; including that it doesn't store things anymore when previewing a tracklist.
* Custom loop functions for tracks & sources
* new preset for Online Radio Box

= 1.6.1 =

* JS: better way to iterate promises in get_first_playable_tracklist() and get_first_playable_track()
* tracklists : fixed tracks reordering and tracks removing
* improved tracklists/tracks/sources classes HTML attribute
* load tracklist options as a json string in the 'data-wpsstm-tracklist-options' HTML attribute

= 1.6.0 =

* use templates files to display stuff (tracklists, wizard, etc); see /templates directory
* improved JS player
* improved capabilities
* javascript expiration : use UTC timestamp instead of remaining sec
* set auto source status to 'pending' if it seems unreliable

= 1.5.0 =

* tracks from live playlists are now saved as regular posts (with the community user as author) and flushed at each tracklist refresh
* track sources are now saved as regular posts (with the community user as author if they are auto-populated)
* new RadioKing preset
* better code structure (splitted into files) for tracklists / tracks / track sources (JS & CSS)
* new JS events: wpsstmTracklistDomReady,wpsstmTrackDomReady - wpsstmTrackSourcesDomReady
* Improved wizard backend & frontend
* Removed class 'WP_SoundSystem_Subtrack': cleaner to handle everything with WP_SoundSystem_Track
* removed WP_SoundSystem_TracksList_Admin_Table, now everything is handled by WP_SoundSystem_Tracklist_Table
* Abord auto_guess_mbid() for tracks when saving subtracks (too slow); or if post is trashed
* improved actions for tracks & tracklists; according to the logged user capabilities, and with popups.
* new option autosource_filter_ban_words (experimental)
* new option autosource_filter_requires_artist (experimental)
* more code cleanup & fixes

= 1.0.2.9 =

* player.js - refresh playlists: use timeout instead of interval
* wpsstm-shortenTables > jquery.toggleChildren
* scroll to playlist track when clicking the player's track number
* tracklists GUI fixes

= 1.0.2.6 =

* improved tracks GUI (buffering, active, etc)

= 1.0.2.5 =

* wp_unslash() ajax_data 
* improved wizard cache
* WP_SoundSystem_Preset_Radionomy_Playlists_API
* new dependency: forceutf8 + composer update
* wizard: better cache handling for wizard
* player.js: fix playlist no more refreshing
* fix refresh link not always displayed
* fix remove notices when playlist request failed
* play_or_skip: ignore action if we've skipped the track + small timeout to fix fast tracks skips

= 1.0.2 =

* Setting for Last.fm bot scrobbler (scrobbles every track listened by any user)
* new class WP_SoundSystem_LastFM_User()

= 1.0.1 =

* Improved sources / autosource code
* lastfm.js: fixed lastfm_auth_notice()
* removed 'autoredirect' option
* WP_SoundSystem_Core_Wizard: option to delete current cache
* fixed ignore cache in wizard
* bottom player: better GUI for source selection
* if the track has 'native' sources and that they cannot play, try to autosource
* improved WP_SoundSystem_Player_Provider subclasses
* new action hook 'init_playable_tracklist'
* fixed crash when Live Playlists are not enabled (always include wpsstm-core-playlists-live.php)
* tracklist.js: tableToggleColumns()
* track & tracklist: sources popup (thickbox) + ajaxed sources suggestions

= 1.0.0 =

* player.js: now uses javascript classes
* player.js: promises & deferred objects
* "random" button
* "loop" button
* new preset for indieshuffle.com

= 0.9.9.6 =

* Ajax for live playlists refresh
* Improved player / tracklists
* remove editor/author support for albums, artists & tracks
* started to implement user functions (favorite tracks / favorite playlists)

= 0.9.9.5 =

* remove WP_SoundSystem_Playlist_Scraper class, new class WP_SoundSystem_Remote_Tracklist instead (much simplier)
* improved saving wizard options
* tracklists pagination !
* live tracklists: new expiration_time var
* new Reddit preset (to be improved)
* improved validate_track() 
* last.FM scrobbling
* settings: last.FM client ID & secret
* Player: 'Choose a source'
* improved tracklist table (schema.org) 
* removed tracklist duration column

= 0.9.9.4 =

* WP_SoundSystem_Track:: get_unique_id(): use sanitize_title
* Tracklists Table: share link
* Tracklists Table: fixed time scraped
* Scraper: handle Dropbox links
* Scraper: Fix get_track_image() and get_track_source_urls()
* Scraper: fixed XSPF options when xpsf content is loaded
* Player: tracks sources preloads
* Player: track infos (track title, provider link)
* Player: sources switch
* Player: redirection notice with timer
* Player: confirmation popup when leaving the page with a media playing
* Player: track button: new 'has-played' class

= 0.9.9.3 =

* player buttons: previous/next track & previous/next page
* splitter presets into multiple files
* improved wpsstm-live-tracklist-class.php

= 0.9.9.2 =

* new "autosource" feature !  Try to find a track source online if none is set in the database (ajaxed).
* player: new settings: "enabled", "autoplay", "auto-redirect" and "autosource".

= 0.9.9.1 =

* sources: now an array (url,title,description) instead of just an url.

= 0.9.9.0 =

* new Deezer preset
* Youtube sources (if any) for Last.FM preset
* improved player
* improved sources

= 0.9.8.9 =

* scraper now able to get an array of sources urls for each track.
* get_track_node_content(): new argument '$single_value' (default to TRUE)

= 0.9.8.8 =

* improved wizard GUI
* scraper: now we can query an element's attribute (disabled if not HTML content)
* scraper: improved options
* scraper: now uses a dynamic selector for the tracklist title

= 0.9.8.7 =

* varioux fixes
* improved shortenTables
* mute unecessary columns in the backend listings
* improved frontend tracklists

= 0.9.8.6 =

* new "sources" metabox - don't use Post Bookmarks for this anymore.

= 0.9.8.5 =

* scraper: set tracklist informations only if not already defined - So tracklist that has been populated with a post ID has not its
informations overriden
* sanitize string at the end of WP_SoundSystem_Remote_Tracklist::get_track_node_content()
* new Hype Machine preset

= 0.9.8.4 =

* XSPF output: added title, author, timestamp, location and annotation nodes
* xspf urls: moved download argument at the end
* added rewrite rule for frontend wizard

= 0.9.8.3 =

* doc
* live playlists: when not displaying a single page, add a notice to load the tracklist
* scraper: cache only if several post are displayed (like an archive page)

= 0.9.8.2 =

* try to use parser without converting encoding
* fixed Spotify preset (now uses API)
* new API settings for Spotify
* post_tag taxonomy for albums & tracks
* frontend CSS fixes

= 0.9.8.1 =

* sort stations by health / trending / popular
* fixed ajaxed row actions for tracklist rows
* improved classes WP_SoundSystem_Subtrack() and WP_SoundSystem_Track()
* fix title comparaison check when updating artist/album/track
* fix Spiff plugin upgrade routine
* renamed Array2XML > WP_SoundSystem_Array2XML
* new function WP_SoundSystem::is_admin_page()
* no CSS background for tracklist table

= 0.9.8 =

* show player column only if at least one track has sources
* tracks: auto guess ID if not defined
* improved presets + fixed Soundsgood preset
* header admin notice (review / donations)
* abord update_title_artist() and update_title_album() if value missing
* improved fill_post_with_mbdatas()
* improved presets suggestions in the wizard
* changed live tracks transient name

= 0.9.7 =

* Better track & album auto title
* Hide title input as we set it automatically - but keep the feature since it outputs the permalink and 'view' link
* no more options for the "Fill with datas" button from the Musicbrainz Metabox
* improved how posts are automatically filled with MusicBrainz data
* improved wpsstm_get_raw_subtrack_ids() and WP_SoundSystem_Subtrack::get_parent_ids()

= 0.9.6 =

* Changed how subtracks are stored: now we store an array of subtrack IDs in the tracklist post; while before we were saving the tracklist ID in each track.
* Hide subtracks' filter now works - still some work to do on this

= 0.9.5 =

* live playlists disabled by default
* ajax: reorder tracklist tracks with ajax only
* include scraper metabox even if live playlists are disabled
* fixed bug when deleting subtracks
* populate track sources only once
* Add title support for albums, artists & tracks (wasn't showing the 'View Post' or permalink on the post page)
* tracklists: add 'data-tracks-count' attribute
* WIP: audio player
* Show a play button in tracklist tables
* Embed a tracklist for single tracks too
* WIP: frontend scraper

= 0.9.4 =

* improved presets - much cleaner code here.
* improved scraper notices; don't uses add_settings_error anymore.

= 0.9.3 =

* new 'Twitter' and 'XSPF' presets
* improved presets
* validate_tracks() in function content_append_tracklist_table() and file playlist-xspf.php
* Improved the saving routine for metaboxes

= 0.9.2 =

* New 'BBC Station', 'BBC Playlist', and 'Slacker.com station tops' scraper presets
* New 'wpsstm-track' and 'wpsstm-tracklist' shortcodes

= 0.9.1 =

* New Radionomy preset
* Improved scraper presets system
* Improved Post Bookmarks

= 0.9 =

* First release

== Upgrade Notice ==

== Localization ==
