<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use \DOMDocument as DOMDocument;

/**
 * Build XML Functions
 */
class BuildXML {

  // @parameter $links contains array of links from db
  // return xml file(name?)
  // used https://programmerblog.net/how-to-generate-xml-files-using-php/ for help
  public function createXMLFile($links) {
    // specify file (and path) to be generated
    $file = 'sitemap.xml';
    // instantiate DOMDocument class and specify xml version and encoding
    $dom = new DOMDocument('1.0', 'utf-8');
    // set root element name
    $root = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');

    // loop through the $links array and add elements into the xml
    foreach ($links as $link) {
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
}
