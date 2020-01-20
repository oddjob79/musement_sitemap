<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use \DOMDocument as DOMDocument;

/**
 * Contains function for building XML file
 */
class BuildXML {

  public function __construct($links) {
    $this->links = $this->sortLinks($links);
  }

  /**
  * Uses the $links data gathered from the links database table to loop through and generate XML in sitemap format, as specified
  * in the https://www.sitemaps.org/protocol.html#sitemapXMLExample webpage
  * As well as the URL, the createXMLFile method uses the page type to set the Priority, based on a city page requiring 0.7,
  * an activity (or event) requiring a 0.5 priority and any other page requiring a 1.0 priority. Last Modified Date information
  * was not available to be used.
  * Used https://programmerblog.net/how-to-generate-xml-files-using-php/ to help build method
  * @param $links Array of links returned from db
  * @return string $xmloutput - contains XML for HTML rendering
  */
  public function createXMLFile() {
    // specify file (and path) to be generated
    $file = 'sitemap.xml';
    // instantiate DOMDocument class and specify xml version and encoding
    $dom = new DOMDocument('1.0', 'utf-8');
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
    $dom->save($file);

    // format XML as HTML ready output string
    $xmloutput = $dom->saveXML();
    return $xmloutput;
  }

  private function sortLinks($links) {
    // sort array by number of slashes found in the URL path in order to prioritize cities to aid prefiltering
    usort($links, function($a, $b) {
        return substr_count(parse_url($a['url'], PHP_URL_PATH), '/') - substr_count(parse_url($b['url'], PHP_URL_PATH), '/');
    });

    return $links;
  }

}
