<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use \DOMDocument as DOMDocument;

/**
 * Contains function for building XML file
 */
class BuildXML {

  /**
  * File path and name
  * Path to file for storing XML
  * @var string
  */
    private $file;

  /**
  * DOMDocument Object
  * @var object
  */
    private $dom;

  /**
  * XML Root Element
  * String for storing the XML root element
  * @var string
  */
    private $root;

  /**
  * Links information
  * Array used for storing links information gathered from the links table
  * @var array
  */
    private $links;

  /**
  * Link information
  * Contains Value from the $links array, used in foreach loop
  * @var string
  */
    private $link;

  /**
  * The link's URL
  * Used for storing the URL for the link
  * @var string
  */
    private $linkloc;

  /**
  * The Link's Priority
  * Used for storing the Priority of the Link (0.5, 0.7 or 1.0), depending on the type of link
  * @var string
  */
    private $linkpriority;

  /**
  * The URL XML element
  * Object used to store the <url> data element
  * @var object
  */
    private $url;

  /**
  * The loc XML element
  * Object used to store the <loc> data element
  * @var object
  */
    private $loc;

  /**
  * The priority XML element
  * Object used to store the <priority> data element
  * @var object
  */
    private $priority;

  /**
  * Output as XML string
  * Used to store the XML so it can be output to HTML
  * @var string
  */
    private $xmloutput;


  /**
  * Uses the $links data gathered from the links database table to loop through and generate XML in sitemap format, as specified
  * in the https://www.sitemaps.org/protocol.html#sitemapXMLExample webpage
  * As well as the URL, the createXMLFile method uses the page type to set the Priority, based on a city page requiring 0.7,
  * an activity (or event) requiring a 0.5 priority and any other page requiring a 1.0 priority. Last Modified Date information
  * was not available to be used.
  * Used https://programmerblog.net/how-to-generate-xml-files-using-php/ to help build method
  * @param $links Array of links from db
  * @return string $xmloutput - contains XML for HTML rendering
  */
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
