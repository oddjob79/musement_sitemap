<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use \DOMDocument as DOMDocument;

/**
 * Contains functions used for building XML file
 */
class BuildXML {

  /**
  * Array of links, sorted by number of slashes in the path of the URL
  * @var array
  */
  private $links;

  /**
  * Requires $links data gathered from the links database table as an array and uses the sortLinks method to prepare the links for the XML file creation
  * @param array $links Array of links returned from db
  */
  public function __construct($links) {
    $this->links = $this->sortLinks($links);
  }

  /**
  * Uses $this->links array, sorted via the sortLinks method, to loop through and generate XML in sitemap format, as specified
  * in the https://www.sitemaps.org/protocol.html#sitemapXMLExample webpage
  * As well as the URL, the createXMLFile method uses the page type to set the Priority, based on a city page requiring 0.7,
  * an activity (or event) requiring a 0.5 priority and any other page requiring a 1.0 priority. Last Modified Date information
  * was not available to be used.
  * Generates an XML file based on the filename given and removes the temp file
  * Used https://programmerblog.net/how-to-generate-xml-files-using-php/ to help build method
  * @param string $filename - name of the XML file to be generated
  */
  public function createXMLFile($filename) {
    // check filename
    if (!isset($filename)) {
      throw new \Exception(
        "No file name given. Please complete the form fully and resubmit."
      );
    }
    if (file_exists($filename)) {
      throw new \Exception(
        "File '$filename' already exists. Please select a different one, and resubmit."
      );
    }
    // instantiate DOMDocument class and specify xml version and encoding
    $dom = new DOMDocument('1.0', 'utf-8');
    // make sure output is formatted
    $dom->formatOutput=true;
    // set root element name
    $root = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');

    // loop through the $links array and add elements into the xml
    foreach ($this->links as $link) {
      if ($link['include'] == 1) {
        // set loc attribute per link
        $linkloc = $link['url'];
        // depending on page type, set priorty
        switch ($link['type']) {
          case 'city':
            $linkpriority = '0.7';
            break;
          case 'event':
            $linkpriority = '0.5';
            break;
          default:
            $linkpriority = '1.0';
        }

        // create url element
        $url = $dom->createElement('url');
          // create loc element and append it to the url element
          $loc = $dom->createElement('loc', $linkloc);
          $url->appendChild($loc);
          // create priority element and append it to the url element
          $priority = $dom->createElement('priority', $linkpriority);
          $url->appendChild($priority);
        // append url element to root (urlset)
        $root->appendChild($url);
      }
    }

    // add root element
    $dom->appendChild($root);
    // save to file
    $dir = './xml/';
    $dom->save($dir.$filename);
    // check if file has saved correctly
    if (!file_exists($dir.$filename)) {
      throw new \Exception(
        "XML file '$filename' was not created. Please try again."
      );
    }
    // remove the temp file
    unlink('./xml/'.substr($filename, 0, -3).'tmp');

  }

  /**
  * Called during class construction, takes the links array and sorts according to the number of slashes in the path, so you should get the root first
  * @param array $links - array of links returned from db
  * @return array $links - sorted list of links used to create XML file
  */
  private function sortLinks($links) {
    // sort array by number of slashes found in the URL path in order to prioritize cities to aid prefiltering
    usort($links, function($a, $b) {
        return substr_count(parse_url($a['url'], PHP_URL_PATH), '/') - substr_count(parse_url($b['url'], PHP_URL_PATH), '/');
    });

    return $links;
  }

}
