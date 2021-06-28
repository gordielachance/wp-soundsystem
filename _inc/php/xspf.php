<?php

namespace mptre;

/**
 * Xspf
 *
 * @package Xspf
 * @author Anton Lindqvist <anton@qvister.se>
 * @copyright 2010 Anton Lindqvist <anton@qvister.se>
 * @license http://www.opensource.org/licenses/mit-license.html
 * @link http://github.com/mptre/Xspf
 */
class Xspf {

    const VERSION = '2.0.1';

    private $_xml;
    private $_playlist;
    private $_tracklist;

    /**
     * Class constructor.
     *
     * @param array $playlistInfo Optional playlist metadata.
     * @return null
     */
    function __construct($playlistInfo = null) {
        $this->_xml = new \DOMDocument();

        $this->_playlist = $this->_xml->createElement('playlist');
        $this->_playlist->setAttribute('version', 1);
        $this->_playlist->setAttribute('xmlns', 'http://xspf.org/ns/0/');

        if (is_array($playlistInfo)) {
            foreach ($playlistInfo as $key => $val) {
                $this->addPlaylistInfo($key, $val);
            }
        }

        $this->_tracklist = $this->_xml->createElement('trackList');
    }

    /**
     * Add optional playlist metadata.
     *
     * @param string $key Name of the metadata element.
     * @param string $val Metadata value.
     * @return null
     */
    function addPlaylistInfo($key, $val) {
        $info = $this->createDOMTextElement($key, $val);
        $this->_playlist->appendChild($info);
    }

    /**
     * Add a track to the playlist.
     *
     * @param array $trackInfo Info about the track.
     * @return null
     */
    function addTrack($trackInfo) {
        $track = $this->_xml->createElement('track');

        foreach ($trackInfo as $key => $val) {
            //we might have several values (location,link...), so consider everything like an array
            foreach ((array)$val as $childVal) {
              $info = $this->createCDATAElement($key, $childVal);
              $track->appendChild($info);
            }
        }

        $this->_tracklist->appendChild($track);
    }

    /**
     * Generate the actual playlist.
     *
     * @param bool $prettyPrint Indent the xml output.
     * @return string
     */
    function output($prettyPrint = false) {
        $this->_xml->formatOutput = $prettyPrint;
        $this->_playlist->appendChild($this->_tracklist);
        $this->_xml->appendChild($this->_playlist);

        return $this->_xml->saveXML();
    }

	/**
	 * Creates and returns an element containing a DOMText value
	 * @param string $name the element name
	 * @param string $val the element value
	 * @return \DOMElement the new element
	 */
	private function createDOMTextElement($name, $val) {
		$element = $this->_xml->createElement($name);
    $element->appendChild(new \DOMText($val));

		return $element;
	}

	/**
	 * Creates and returns an element containing CDATA
	 * @param string $name the element name
	 * @param string $val the element value
	 * @return \DOMElement the new element
	 */
	private function createCDATAElement($name, $val) {
		$element = $this->_xml->createElement($name);
    $element->appendChild(new \DOMCdataSection($val));

		return $element;
	}

}
