=== WP SoundSystem ===
Contributors: grosbouff
Donate link: https://api.spiff-radio.org?p=31
Tags: music,audio player,playlist,importer,stream,MusicBrainz,Spotify,XSPF,artists,albums,tracks
Requires at least: 4.9
Tested up to: 5.2.2
Stable tag: trunk
License: GPLv2 or later

WP SoundSystem is a complete solution to manage a music library within WordPress.  Use it to build or import playlists, manage tracks and track links, albums, artists, and play them with our audio player.

== Description ==

*WP SoundSystem* is a complete solution to manage a music library within WordPress.

Use it to build or import playlists, manage tracks and track links, albums, artists, and play them with our audio player.

Several new post types will be available : Playlists, Radios, Artists, Albums, Tracks and Tracks Links.

[See it working on Spiff Radio](https://www.spiff-radio.org/)

= Tracklists =

Creating and editing playlists is a piece of cake (not to mention the *Tracklist Importer*): 
Add or remove tracks on the fly, reorder them, favorite a track or a tracklist, export…

= Tracks =

When editing a track, you can query details from music services like [MusicBrainz](https://musicbrainz.org/) (The Open Music Encyclopedia) or Spotify. 
Tracks can be favorited by your users frontend, or added to any new playlist on-the-fly. 

= Tracks Links =

You can attach a bunch of links to any track; including links that can stream audio (Youtube, Soundcloud, audio files...) directly to our player!

= Autolink (requires an API key) =

If you don't attach links to your track manually, you can enable our *autolink* module.  
It will search for remote links and attach them to your tracks automatically.

= Radios =

Radios are how we call *live playlists*. 
Those playlists are synced with remote webpages or services, and are refreshed seamlessly after a short delay.

[Check some Radios on Spiff Radio](http://spiff-radio.org/?post_type=wpsstm_live_playlist&tag=editors-pick&author=1)

= Tracklist Importer =

Import [XSPF playlists](http://xspf.org/) using the *Tracklist Importer*.

If you have an [API key](https://api.spiff-radio.org/?p=10), you could also import playlists from various services: *Last.fm, Spotify, SoundCloud, Deezer, Musicbrainz, Radionomy, Hypem, 8tracks, BBC, indieshuffle, Online Radio Box, radio.fr, RadioKing, Reddit, SomaFM, Soundsgood,...*
Custom setups are also available, if you are somewhat familiar with [CSS selectors](https://www.w3schools.com/cssref/css_selectors.asp).

[Frontend Importer on Spiff Radio](https://www.spiff-radio.org/?p=213)

= Player =

Our player uses of the [MediaElement.js](https://www.mediaelementjs.com) library, which is native in WordPress. It supports audio (& video) files, but also links from various services like Youtube or Soundcloud.
It has been extended with various features built on top of it, like a tracks queue or a Last.fm scrobbler.

= Social =

**Last.fm**
In addition of being able to scrobble on [Last.fm](https://www.last.fm/), every track favorited by a user connected to his account will also be loved on that service.

**BuddyPress**
Users profiles will get a new music section that lists the user favorite tracks, tracklists, and the ones he created. 
It will also fire new *BuddyPress activity* items.

= Contribute =

WP SoundSystem is dev friendly, and has been designed to be extendable.
Wanna give a hand as developer ? Check the [Github](https://github.com/gordielachance/wp-soundsystem) repo.

= WP SoundSystem API =

Get more out of this plugin by [registering an API key](https://api.spiff-radio.org/?p=10); which will enable

* Direct import from a lot of music services
* the Autolink module

Those are optionals, but are nice features to a solid plugin.
Consider getting one as a nice way to support the work done – hundred of hours – , and to ensure its durability.

= Donate =
Whatever, if you like this plugin, please also consider [making a donation](https://api.spiff-radio.org/?p=31).

This would be very appreciated — Thanks !

= Dependencies =

* [PHP Last.fm API](https://github.com/matt-oakes/PHP-Last.fm-API) - Last.fm scrobbling

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

== How can I embed a track or a playlist ? =

Using shortcodes:

`[wpsstm-tracklist post_id="160"]`

`[wpsstm-track post_id="150"]`
`[wpsstm-track artist="Patrick Sébastien" album="Pochette surprise" title="Les Sardines"]`

Or directly with PHP functions:

`<?php
$tracklist = new WPSSTM_Post_Tracklist($post_id);
echo $tracklist->get_tracklist_html();
?>`

`<?php
$track = new WPSSTM_Track($post_id);
echo $track->get_track_html();
?>`

! There is currently a Wordpress bug that breaks our shortcodes.  See [the issue on Github](https://github.com/gordielachance/wp-soundsystem/issues/82)..

= What is the community user ? =

The community user is a Wordpress user you need to create, and that will be assigned as author to the content created automatically by the plugin, for instance imported tracks or links.

== Screenshots ==

1. Tracklist playing frontend
2. Radio (live) tracklist
3. Plugin settings page & menu
4. Tracklist Importer metabox
5. Tracks Links metabox
6. Tracklists manager popup (when favoriting a track)
7. Frontend Tracklist Importer
8. Music menu on a BuddyPress profile

== Changelog ==

= 2.7.9 =
* better player JS
* allow XSPF tracklist import without the need of an API key
* fixed tracklist share
* better tracklist header
* improve lightbox
* remove settings 'importer_enabled' & 'radios_enabled'
* fixes regex not passing to API

= 2.7.5 =
* Moved all the import stuff to the API.  Now uses rest route 'wp-json/wpsstmapi/v1/import/url/?url=...' (on the API)

= 2.7.4 =
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

= 2.6.3 =
* "Sources" renamed to "Track Links" (post type, some metas, some filters renamed)
* Fixed toggle playable track link
* Fixed sort track links

= 2.6.2 =
* fixed OnlineRadioBox service
* fixed bugfix XSPF export
* track links JS/CSS improvements

= 2.6.1 =
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

= 2.5.3 =
* new class WPSSTM_Music_Data

= 2.5.0 =
* So much improvements that they cannot even be listed !  More than one year of developpement and 754 commits !
* now compatible with the [Autoplay Policy Change](https://developers.google.com/web/updates/2017/09/autoplay-policy-changes) from Chrome
* Code entirely rewritten
* HTML : uses HTML5 custom elements
* REST API
* ...

== Upgrade Notice ==

== Localization ==
