# spotify-metadata-php
This class is used for searches and lookups from the Spotify metadata API.

You can view the Spotify metadata API usage docs at http://developer.spotify.com/en/metadata-api/overview/
The terms and conditions for this API are available at http://developer.spotify.com/en/metadata-api/terms-of-use/

Some of the code in this class was derived from the metatune library available at http://code.google.com/p/metatune/

## Prerequisites

This class requires the following PHP modules to operate: SimpleXML and cURL

## Usage

The API has search and lookup functions for artists, albums, and tracks.

### Lookup

To use the lookup features of this class, call:

	Spotify::lookupArtist(uri)
	Spotify::lookupAlbum(uri)
	Spotify::lookupTrack(uri)
	
	You can also use the generic lookup function:
	Spotify::lookup(uri)
	
Example script:

	include('spotify.class.php');
	$spotify = new Spotify;
	$artist = $spotify->lookup('spotify:artist:4YrKBkKSVeqDamzBPWVnSJ');
	print_r($artist);
	
Another example to list all tracks on an album. The DAO for track and album both support toString()

	include('spotify.class.php');
	$spotify = new Spotify;
	$album = $spotify->lookup('spotify:album:4JKPD5CoWpBbzf2WwokRSp');
	foreach ($album->tracks as $track) {
		echo $track."\n";
	}
	
### Search

The search features are accessed via the following functions:

	Spotify::searchArtist(searchquery)
	Spotify::searchAlbum(searchquery)
	Spotify::searchTrack(searchquery)