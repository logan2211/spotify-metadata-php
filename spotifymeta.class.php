<?php
class Spotify {
	const URL_SEARCH = "http://ws.spotify.com/search/1/";
	const URL_LOOKUP = "http://ws.spotify.com/lookup/1/";
	
	public $api_retries = 10;
	
	private $c; //curl instance
	private $response; //raw response from spotify
	
	public function __construct() {
		$this->init_curl();
	}
	
	public function init_curl() {
		$this->c = curl_init();
		$c =& $this->c;
		
		curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
	}
	
	public function searchTrack($search, $page=1) {
		$xml = $this->perform_search('track', $search, $page);
		$tracks = array();
		foreach ($xml as $e) {
			//loop through and create Track objects for the search results.
			$tracks[] = $this->populateTrack($e);
		}
		
		return $tracks;
	}
	
	public function searchArtist($search, $page=1) {
		$xml = $this->perform_search('artist', $search, $page);
		$artists = array();
		foreach ($xml as $e) {
			$artists[] = $this->populateArtist($e);
		}
		return $artists;
	}
	
	public function searchAlbum($search, $page=1) {
		$xml = $this->perform_search('album', $search, $page);
		$albums = array();
		foreach ($xml as $e) {
			$albums[] = $this->populateAlbum($e);
		}
		return $albums;
	}
	
	private function perform_search($type, $search, $page) {
		$url = $this->getSearchURL($type, $search, $page);
		$xml = $this->perform_apicall($url);
		return $xml;
	}
	
	public function lookup($uri) {
		$split = explode(':', $uri);
		$type = $split[1];
		switch ($type) {
			case 'track':
				return $this->lookupTrack($uri);
			case 'artist':
				return $this->lookupArtist($uri);
			case 'album':
				return $this->lookupAlbum($uri);
			default:
				throw new Exception('Lookup failed. Invalid URI.');
		}
	}
	
	public function lookupTrack($uri) {
		$xml = $this->perform_lookup($uri);
		return $this->populateTrack($xml);
	}
	
	public function lookupArtist($uri, $detail=true) {
		if ($detail == true) $extras = 'albumdetail';
		else $extras = null;
		$xml = $this->perform_lookup($uri, $extras);
		return $this->populateArtist($xml);
	}
	
	public function lookupAlbum($uri, $detail=true) {
		if ($detail == true) $extras = 'trackdetail';
		else $extras = null;
		$xml = $this->perform_lookup($uri, $extras);
		return $this->populateAlbum($xml);
	}
	
	private function perform_lookup($uri, $extras=null) {
		$url = $this->getLookupURL($uri, $extras);
		$xml = $this->perform_apicall($url);
		return $xml;
	}
	
	private function perform_apicall($url) {
		$c =& $this->c;
		curl_setopt($c, CURLOPT_URL, $url);
		
		$i=0;
		while (1) {
			$i++;
			if ($i >= $this->api_retries) throw new Exception('Too many API retries');
			
			$output = curl_exec($c);
			$last_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
			if ($last_code == 200) break;
			else if ($last_code == 403) sleep(10);
		}
		$xml = new SimpleXMLElement($output);
		return $xml;
	}
		
	public function getSearchURL($type, $search, $page=1) {
		return self::URL_SEARCH.$type.'?q='.$this->translateString($search).$this->addPageSuffix($page);
	}
	
	public function getLookupURL($uri, $extras=null) {
		if (empty($extras)) $get = '?uri='.$uri;
		else $get = '?uri='.$uri.'&extras='.$extras;
		
		return self::URL_LOOKUP.$get;
	}
	
	private function translateString($string) {
		// Replace "-" in regular search but leave it on "tag"-searches
		// such as "genre:brit-pop" or "label:deutsche-grammophon"
		$string = preg_replace("/(^[^a-z\:]+\-|[\_\(\)])/ui", " ", (trim($string)));
		
		// replace multiple whitespaces with a single one.
		$string = preg_replace("/\s{2,}/", " ", ($string));
		return urlencode((trim($string)));
	}
	
	private function addPageSuffix($page) {
		if ($page <= 1 || !is_numeric($page)) return "";
		return "&page=" . (int) $page;
	}
	
	private function populateTrack($xml) {
		//populate the track basics
		$track = new Track;
		$track->href = (string)$xml->attributes()->href;
		$track->name = (string)$xml->name;
		$track->track_number = (int)$xml->{'track-number'};
		$track->length = (double)$xml->length;
		$track->popularity = (double)$xml->popularity;
		$track->available_countries = $this->available_countries((string)$xml->availability->territories);
		
		//populate the artist
		$track->artist = new Artist;
		$artist =& $track->artist;
		$artist->name = (string)$xml->artist->name;
		$artist->href = (string)$xml->artist->attributes()->href;
		
		//populate the album info
		if (!empty($xml->album)) {
			$track->album = new Album;
			$album =& $track->album;
			$album->name = (string)$xml->album->name;
			$album->href = (string)$xml->album->attributes()->href;
			$album->released = (string)$xml->album->released;
			$album->available_countries = $this->available_countries((string)$xml->album->availability->territories);
		}
		
		return $track;
	}
	
	private function populateArtist($xml) {
		//populate the artist
		$artist = new Artist;
		$artist->name = (string)$xml->name;
		$artist->href = (string)$xml->attributes()->href;
		$artist->popularity = (double)$xml->popularity;
		
		//populate albums if we have some
		if (sizeOf($xml->albums->album) > 0) {
			foreach ($xml->albums->album as $a) {
				$artist->albums[] = $this->populateAlbum($a);
			}
		}
		
		return $artist;
	}
	
	private function populateAlbum($xml) {
		//populate the album
		$album = new Album;
		$album->name = (string)$xml->name;
		$album->href = (string)$xml->attributes()->href;
		$album->released = (string)$xml->released;
		$album->popularity = (double)$xml->popularity;
		$album->available_countries = $this->available_countries((string)$xml->availability->territories);
		
		//populate artist data
		$album->artist = new Artist;
		$artist =& $album->artist;
		$artist->name = (string)$xml->artist->name;
		$artist->href = (string)$xml->artist->attributes()->href;
		
		//if we have some tracks populate them.
		if (sizeOf($xml->tracks->track) > 0) {
			foreach ($xml->tracks->track as $t) {
				$track = $this->populateTrack($t);
				$track->album = clone $album;
				$track->album->tracks = null;
				$album->tracks[] = $track;
			}
		}
		
		return $album;
	}
	
	public function available_countries($string) {
		$string = trim($string);
		$countries = explode(' ', $string);
		$return = array();
		foreach($countries as $c) {
			$return[$c] = true;
		}
		if (empty($string)) $return = null;
		return $return;
	}
}

class Track {
	public $href = NULL;
	public $name = NULL;
	public $track_number = NULL;
	public $length = NULL;
	public $popularity = NULL;
	
	public $album = NULL;
	public $artist = NULL;
	
	public $available_countries  = array();
	
	public function __toString() {
		$string = '';
		
		$string = $this->artist->name;
		
		$string .= ' - '.$this->album->name;
		if (!empty($this->album->released)) $string .= " ({$this->album->released})";
		
		if (!empty($this->track_number)) $string .= " - ({$this->track_number}) {$this->name}";
		else $string .= ' - '.$this->name;

		return $string;
	}
}

class Album {
	public $name = NULL;
	public $href = NULL;
	public $released = NULL;
	public $popularity = NULL;
	
	public $artist = NULL;
	public $tracks = array();
	
	public $available_countries  = array();
	
	public function __toString() {
		$string = $this->artist->name.' - '.$this->name;
		if (!empty($this->released)) $string .= " ({$this->released})";
		return $string;
	}
}

class Artist {
	public $name = NULL;
	public $href = NULL;
	public $popularity = NULL;
	
	public $albums = array();
}
?>