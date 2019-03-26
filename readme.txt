=== WP SoundSystem ===
Contributors: grosbouff
Donate link: https://api.spiff-radio.org?p=31
Tags: music,player,audio,playlist,importer,stream,MusicBrainz,Spotify,XSPF,artists,albums,tracks,sources
Requires at least: 4.9
Tested up to: 5.1.1
Stable tag: trunk
License: GPLv2 or later

WP Soundsystem is a complete solution to manage a music library within WordPress.  Use it to build or import playlists, manage tracks and audio sources, albums, artists, and play them with our audio player.

== Description ==

*If you are updating the plugin from < version 2.5, please BACKUP your database first, as many, many things have been updated.*

*WP Soundsystem* is a complete solution to manage a music library within WordPress.

Use it to build or import playlists, manage tracks and audio sources, albums, artists, and play them with our audio player.

Several new post types will be available : Playlists, Radios, Artists, Albums, Tracks and Sources.

[See it working on Spiff Radio](https://www.spiff-radio.org/)

= Tracklists =

Creating and editing playlists is a piece of cake (not to mention the *Tracklist Importer*): 
Add or remove tracks on the fly, reorder them, favorite a track or a tracklist, export…

= Tracks =

When editing a track, you can query details from music services like [MusicBrainz](https://musicbrainz.org/) (The Open Music Encyclopedia) or Spotify. 
Audio sources can be linked to your tracks with the *Track Sources* metabox.

= Sources =

You can link several audio sources to any track.  
It can be a the path of an audio file or even music services links like Youtube, or Soundcloud!

= Autosource (requires an API key) =

If you don't link audio sources to your track manually, you can enable our *autosource* module.  
It will search for remote sources and attach them to your tracks. 

= Radios =

Radios are how we call *live playlists*. 
Those playlists are synced with remote webpages or services, and are refreshed seamlessly after a short delay.

[Check some Radios on Spiff Radio](http://spiff-radio.org/?post_type=wpsstm_live_playlist)

= Tracklist Importer (requires an API key) =

Backup your playlists using the Tracklist Importer. 
Popular services (Spotify, Last.fm, Radionomy, Deezer, BBC, Soundcloud, Soundsgood, Hype Machine, Indie Shuffle, RadioKing,…) are available out-of-the-box, just by pasting a playlist link.
More advanced setups are also available, if you are somewhat familiar with [CSS selectors](https://www.w3schools.com/cssref/css_selectors.asp).

[Frontend Importer on Spiff Radio](https://www.spiff-radio.org/?p=213)

= Player =

Our player uses of the [MediaElement.js](https://www.mediaelementjs.com) library, which is native in WordPress. It supports audio (& video) files, but also links from various services like Youtube or Soundcloud.
It has been extended with various features built on top of it, like a tracks queue or a Last.fm scrobbler.

In addition of being able to scrobble on [Last.fm](https://www.last.fm/), every track favorited by a user connected to his account will also be loved on that service.

= BuddyPress =

Users profiles will get a new music section that lists the user favorite tracks, tracklists, and the ones he created. 
It will also fire new *BuddyPress activity* items.

= Contribute =

WP Soundsystem is dev friendly. 
Wanna give a hand as developer ? Check the [Github](https://github.com/gordielachance/wp-soundsystem) repo.

= WP SoundSystem API =

Get more out of this plugin by [registering an API key](https://api.spiff-radio.org/?p=10); which will enable

* the Tracklist Importer
* the Autosource module
* the Radios post type

Those are optionals, but are nice features to a solid plugin.
Consider getting one as a nice way to support the work done – hundred of hours – , and to ensure its durability.

= Donate =
Whatever, if you like this plugin, please also consider [making a donation](https://api.spiff-radio.org/?p=31).
This would be very appreciated !

This would be very appreciated — Thanks !

= Dependencies =

* [phpQuery](https://github.com/punkave/phpQuery) - a PHP port of jQuery selectors
* [PHP Last.fm API](https://github.com/matt-oakes/PHP-Last.fm-API) - Last.fm scrobbling
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

== How can I embed a track or a playlist in a post ? =

Using shortcodes:

`[wpsstm-track post_id="150"]`

`[wpsstm-tracklist post_id="160"]`

Or directly with PHP functions:

`<?php
$tracklist = new WPSSTM_Post_Tracklist(); //optionally accepts a post_id as argument
echo $tracklist->get_tracklist_html();
?>`

= What is the community user ? =

The community user is a Wordpress user you need to create, and that will be assigned as author to the content created automatically by the plugin, for instance imported tracks or sources.

== Screenshots ==

1. Tracklist playing frontend
2. Radio (live) tracklist
3. Plugin settings page & menu
4. Tracklist Importer metabox
5. Track Sources metabox
6. Tracklists manager popup (when favoriting a track)
7. Frontend Tracklist Importer
8. Music menu on a BuddyPress profile

== Changelog ==

= 2.5.7 =
* fix url encoding & regexes for Last.fm presets
* tracklist expiration bugfix
* improved importer and importer settings
* importer debug GUI
* bugfix populate sources/autosources

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
