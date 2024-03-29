=== WP SoundSystem ===
Contributors: grosbouff
Donate link: https://www.spiff-radio.org/?p=287871
Tags: music,audio player,playlist,importer,stream,MusicBrainz,Spotify,XSPF,artists,albums,tracks
Requires at least: 4.9
Tested up to: 5.8.2
Stable tag: trunk
License: GPLv2 or later

WP SoundSystem is a complete solution to manage music in WordPress.  Import, create and manage tracklists, play tracks, connect to popular music services, and more !

== Description ==

*WP SoundSystem* is a complete solution to manage music in WordPress.

Import, create and manage tracklists, play tracks, connect to popular music services, and more !

[See it working on Spiff Radio](https://www.spiff-radio.org/)

Unlike most plugins, WP SoundSystem is *not* embedding third-party widgets in Wordpress.
It goes *further* by using its own **customizable player** : no brands, no logos, but **your own data & design** !

Playing tracks does not rely on a single service, since multiple links can be associated to a track (Youtube, Soundcloud, Spotify, audio files, anything!).

= Tracklists =

We have two types of tracklists : Playlists, and Radios.

= But what the hell are Radios ? =

Unlike Playlists, you cannot add, remove or reorder the tracks of a Radio : it is synced with an external URL, and updates seamlessly after a user-defined delay.  This is something very specific to this plugin.

[Check some Radios on Spiff Radio](http://spiff-radio.org/?post_type=wpsstm_radio&tag=editors-pick&author=1)

For example, a Radio might be

* Synced to a station website "now playing" page, cached for only 15 minutes since it is updated constantly on the remote side.
* Synced to a Spotify playlist, cached for 1 day (or more) since there is only a few chances that its author might have updated it.

A Radio synced with a Spotify playlist of yours would bring an always up-to-date playlist to your visitors, without having to update your website.

You can also create a Radio to fetch a tracklist you like, then convert it to a (static) Playlist so you have a backup of it.

Any tracklists can be exported to the standard [XSPF](http://xspf.org/) file format.

= Tracks =

Tracks can be favorited and shared by your users frontend, or added to a new playlist on-the-fly.
Backend, you can query details from music services like [MusicBrainz](https://musicbrainz.org/) (The Open Music Encyclopedia) or Spotify.

= Tracks Links =

You can attach a bunch of links to any track; including links that can stream audio (Youtube, Soundcloud, audio files...) directly to our player!

= Autolink (requires an API key) =

If you don't attach links to your track manually, you can enable our *autolink* module.
It will search for remote links and attach them to your tracks automatically.

= Tracklist Importer =

Import [XSPF playlists](http://xspf.org/) using the *Tracklist Importer*.

If you have an [API key](https://github.com/gordielachance/wp-soundsystem/wiki/SoundSystem-API), you could also import playlists from various services: *Last.fm, Spotify, SoundCloud, Deezer, Musicbrainz, Radionomy, Hypem, 8tracks, BBC, indieshuffle, Online Radio Box, radio.fr, RadioKing, Reddit, SomaFM, Soundsgood,...*

Custom setups are also available, if you are somewhat familiar with [CSS selectors](https://www.w3schools.com/cssref/css_selectors.asp).

[Frontend Importer on Spiff Radio](https://www.spiff-radio.org/?p=213)

= Player =

Our player uses of the [MediaElement.js](https://www.mediaelementjs.com) library, which is native in WordPress. It supports audio (& video) files, but also links from various services like Youtube or Soundcloud.
It has been extended with various features built on top of it, like a tracks queue or a Last.fm scrobbler.
You can customize the design of this player the way you like, and extend its functions.

= Social =

**Last.fm**
In addition of being able to scrobble on [Last.fm](https://www.last.fm/), every track favorited by a user connected to his account will also be loved on that service.

**BuddyPress**
Users profiles will get a new music section that lists the user favorite tracks, tracklists, and the ones he created.
It will also fire new *BuddyPress activity* items.

= Contribute =

WP SoundSystem is dev friendly, and has been designed to be extendable.
Wanna give a hand as developer ? Check the [Github](https://github.com/gordielachance/wp-soundsystem) repo.

= SoundSystem API =

The [SoundSystem API](https://github.com/gordielachance/wp-soundsystem/wiki/SoundSystem-API) has been developed in Ruby in parallel of this plugin.
Get extra features by registering an API key !

Some of its feature are:

* Playlist import from a lot of music services
* the Autolink module, which will get audio sources for your tracks automatically.

Those are optionals, but are nice features to a solid plugin.
Consider getting one as a nice way to support the work done –hundred of hours– , and to ensure its durability.

Since the API is independent of the plugin, it might also be used by other developers.

More informations [here](https://github.com/gordielachance/wp-soundsystem/wiki/SoundSystem-API).

= Donate =
Whatever, if you like this plugin, please also consider [making a donation](https://www.spiff-radio.org/?p=287871).

This would be very appreciated — Thanks !

= Dependencies =

* [PHP Last.fm API](https://github.com/matt-oakes/PHP-Last.fm-API) - Last.fm scrobbling
* [jQuery json-viewer](https://github.com/abodelot/jquery.json-viewer) - jQuery plugin for displaying JSON data

= Contributors =

Contributors [are listed here](https://github.com/gordielachance/wp-soundsystem/contributors)

= Bugs/Development =

For feature request and bug reports, please use the [Github Issues Tracker](https://github.com/gordielachance/wp-soundsystem/issues).

If you are a plugin developer, [we would like to hear from you](https://github.com/gordielachance/wp-soundsystem). Any contribution would be very welcome.

== Installation ==

This plugin requires PHP Version 5.4 or later.

1. Upload the plugin to your blog and Activate it.
2.  Go to the settings page and setup the plugin.

== Frequently Asked Questions ==

See the [Frequently Asked Questions](https://github.com/gordielachance/wp-soundsystem/wiki/Frequently-Asked-Questions) of the Github wiki.

== Screenshots ==

1. Tracklist playing frontend
2. Radio (live) tracklist
3. Plugin settings page & menu
4. Tracklist Importer metabox
5. MusicBrainz metabox
6. Tracks Links metabox
7. Tracklists manager popup (when favoriting a track)
8. Frontend Tracklist Importer
9. Music menu on a BuddyPress profile

== Changelog ==

= 3.4.1 =
* WPSSTM_Post_Tracklist: to_jspf() - new method

= 3.4.0 =
* XSPF file import bugfix

= 3.3.9 =
* API update

= 3.3.7 =
* match updated API

= 3.3.6 =
* tracklists : fixed sort by tracks count / sort by favorited count
* removed Compass dependencies
* some parts of the code cleaned (queuing/dequeuing tracks, track template)
* hook 'wpsstm_love_track' splitted into 'wpsstm_love_track' & 'wpsstm_unlove_track'

= 3.3.5 =

* MAJOR UPDATE !
* switch to the [SoundSystem API](https://github.com/gordielachance/wp-soundsystem/wiki/SoundSystem-API)
* new Importer wizard
* new view for playlists : 'Favorite Tracks'
* sort radios by Importer name
* autolink: limit autolinks to X links --> limit autolinks to X links per host * batch delete autolink timelocks metas
* database upgrade: v217
* bugfix subtracks table was not created at installation (#153)
* bugfix playlist manager was broken (#152)
* bugfix add new row to empty tracklist (#148)
* bugfix rewrite rules

= 3.2.5 =
* improved plugin settings : maintenance
* track links :improved excluded hosts queries
* do not delete links alongside with tracks, just remove the post_parent value (faster)
* better way to add tracks to a Playlist
* added 'wpsstm_populated_tracklist' action hook
* improved 'Now Playing' and 'Sitewide favorites'
* better code for tracklist options / time stuff
* database upgrade: v214
* fix click on player's track position
* fix styles on track displayed in data engines metaboxes
* fix get_tracklist_html (cache) when displaying several playlists
* fix standalone track shortcode
* removed hooks 'wpsstm_init', 'wpsstm_init_post_types', 'wpsstm_init_rewrite'
* WPSSTM_Post_Tracklist : new $id property (unique_id)

= 3.2.0 =
* get_static_subtracks() about 4 times faster !
* audio player is now a a child node of its tracklist (better for customisation)
* JS: a lot of improvements !
* new 'tracklist' view in the tracks backend
* DO NOT queue a track if it is already part of the tracklist
* Radios: better JSON feedback
* tracklist/track/links actions for unlogged users : use wp_login_url()
* new fn batch_delete_duplicate_subtracks()
* new fn batch_reindex_subtracks_by()
* fixed fn reset_subtrack_order()
* use WP_Object_Cache for get_tracklist_html()
* new class Wpsstm_Subtrack_List_Table [WIP]
* database upgrade: v213

= 3.1.5 =
* removed 'delete_duplicate_links' setting
* SQL queries improvements
* subtracks : save subtrack author (pinner) in database - will be bot if tracklist is imported
* creates a'sitewide favorites' tracklist post
* creates a 'now playing' tracklist post with the last tracks played on the website
* BP profile metas : now playing + last favorite
* Improved importers code
* New filter 'wpsstm_get_subtracks'
* database upgrade: v212
* faster REST import

= 3.1.0 =
* Migrated BANGS stuff to wpsstmapi
* Improved importer GUI
* Improved music datas engines
* playlist settings metabox : add 'filter tracks' button

= 3.0.5 =
* musicbrainz/spotify : new 'Post fill' metabox (only for tracks)
* playlists : share track link (autoplay)
* enable .xspf file upload in the media library
* allow 0-minutes cache for radios
* improved rest & import
* new fn import_xspf() - extracted out of sync_radio()

= 3.0.0 =
* fix links imported not properly created
* JS: do not autolink a track that has playable links
* REST route 'import/?input=…' now returns an import ID, that we then use to get the cached XSPF file API side.
* Tracklist importer : new 'Debug log' tab
* fix warning notices with PHP 7.3 (#126)

= 2.9.5 =
* renamed API route : /'import/url/?url=...' -> import/?input=...
* fix unable to add new tracklist tracks on post-new.php
* Soundcloud : fix settings not saving
* Autolink : better GUI
* cache (transient) for excluded track links
* Removed setting 'Trash excluded hosts links' + new 'Excluded hosts' track links view
* Removed setting 'Trash orphan links' + new'Orphan' track links view
* Removed setting 'Trash orphan tracks' + new'Orphan' tracks view
* Removed setting 'Trash temporary tracklists'
* Programmatically create bot user at installation + migrate old setting
* Programmatically create import page at installation + migrate old setting
* When updating track artist/album/title, remove old music term if unique
* When deleting a post, remove the music terms attached to it if unique
* GUI: jsons now displayed using [jQuery json-viewer](https://github.com/abodelot/jquery.json-viewer)

= 2.9.0 =
* Removed registration call to action (should be handled by a theme, not by this plugin)
* JS: Abord tracklist reload when pressing ESC
* JS: Ability to add more than one track at once when editing a tracklist
* Queue subtracks bug fix
* Improve bot user code

= 2.8.5 =
* WPSSTM_Spotify_Endpoints & WPSSTM_Musicbrainz_Endpoints moved from API so we don't need two requests
* new metabox 'Tracklist options'
* ignore bot tracklists unless author is set in query
* JS bugfixes

= 2.8.0 =
* better player JS
* allow XSPF tracklist import without the need of an API key
* fixed tracklist share
* better tracklist header
* improve lightbox
* remove settings 'importer_enabled' & 'radios_enabled'
* fixes regex not passing to API

= 2.7.5 =
* Moved all the import stuff to the API.  Now uses rest route 'wp-json/wpsstmapi/v1/import/?input=...' (on the API)
* store artist,track & album metas as taxonomies instead of post metas; for performance issues (see https://wordpress.stackexchange.com/a/276315/70449 and https://wordpress.stackexchange.com/a/159012/70449)
* trash orphan links option
* trash duplicate links option
* improved JS
* fix Last.fm scrobbler not working except for first track

= 2.7.0 =
* radios : improved how they are updated
* improved REDDIT service
* Improved settings page
* Improved WPSSTM_Core_User logic
* Register Radios post type even if no API key set; but filter post content to display a notice (avoids a 404 error)
* Create user favorite tracks playlist only when he tries to favorite a track for the first time
* Do not store track data within the subtracks table, always create a track post (fixes issues #88)
* Various improvements & bugfixes

= 2.6.5 =
* New track links setting 'Exclude hosts'
* JS link/track/tracklist reclick fix
* Tracklist template : featured image
* "Sources" renamed to "Track Links" (post type, some metas, some filters renamed)
* Fixed toggle playable track link
* Fixed sort track links
* fixed OnlineRadioBox service
* fixed bugfix XSPF export
* track links JS/CSS improvements
* BuddyPress - improved queue track/ love tracklist activities
* Radio.fr - new service
* Spotify: improved playlists regex
* new image assets
* new 'pre_get_tracklist_by_pulse' function hooked on 'pre_get_posts'
* migrate 'remote_delay_min' option from 'scraper option' post meta -> 'wpsstm_cache_min' post meta
* database --> 202

= 2.6.0 =
* tracklist expiration bugfix
* improved importer and its settings
* improved shortcodes
* importer debug GUI
* bugfix populate links/autolinks
* fix files dependencies when no API key
* Last.fm : fix url encoding / fix regexes / fix importer / scrobbler disabled by default
* abord autolink if 'wpsstm_autolink_input' returns an error
* + various bug fixes
* new class WPSSTM_Data_Engine

= 2.5.0 =
* So much improvements that they cannot even be listed !  More than one year of developpement and 754 commits !
* now compatible with the [Autoplay Policy Change](https://developers.google.com/web/updates/2017/09/autoplay-policy-changes) from Chrome
* Code entirely rewritten
* HTML : uses HTML5 custom elements
* REST API
* ...

== Upgrade Notice ==

== Localization ==
