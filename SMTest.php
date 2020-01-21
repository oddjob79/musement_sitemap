<?php
// enable use of namespaces
require 'vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use App\CurlDataRetrieval as CurlDataRetrieval;
use App\ScanOptions as ScanOptions;
use App\BuildXML as BuildXML;
use \DOMDocument as DOMDocument;
use App\SQLiteRead as SQLiteRead;
use App\FilterManipulateData as FilterManipulateData;
use App\SQLiteConnection as SQLiteConnection;

/**
* Collection of unit tests for sitemap
*/
class SMTest extends TestCase {

  /**
  * Instantiate new DOMDocument object into $xml in test setUp function
  * @var object
  */
  private $xml;

/**
* For each test, instantiate the DOMDocument class for the selected xml file into $this->xml
*/
  public function setUp() :void {
    // Declare $file var - location of the xml file
    // $file = 'sitemap_lite_es.xml';
    $file = 'sitemap_std_it.xml';
    // instantiate the DOM
    $this->xml = new DOMDocument();
    // Load the url's contents into the DOM
    $this->xml->load($file);
  }

  /**
  * Loops through the <loc> tags in the given XML file and returns the nodeValue (url) into the $arr array. Then returns it.
  */
  private function retrieveLocLinks() {
    // use $file to collect an array of all <loc> urls - complete
      $arr = array();
      // for each <loc> element
      foreach($this->xml->getElementsByTagName('loc') as $loc) {
        // add url from <loc> tag into $arr
        array_push($arr, $loc->nodeValue);
      }
      return $arr;
  }

  /**
  * Beginning of methods to test XML output
  * Test that each URL in XML file is valid (does not return http 4x or 5x code). Run against only selection of URLs to save time
  */
  public function testDoAllURLsResolve() {
    // retrieve array of urls from xml file
    $arr = $this->retrieveLocLinks();
    // select top 30 urls for speed purposes
    $arr = array_slice($arr, 0, 1);
    // loop through urls
    foreach ($arr as $url) {
      // create curl resource
      $ch = curl_init($url);
      // set option to return output to variable
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      // execute curl reuqest
      $out = curl_exec($ch);
      // set $http var as the web page info
      $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      // close connection
      curl_close($ch);
      // check the http code does not equal 4xx or 5xx
      $this->assertFalse(substr($http, 0, 1) == '4' || substr($http, 0, 1) == '5');
    }
  }

  /**
  * Test to ensure only one locale is included in the xml file
  */
  public function testIncorrectLocaleIncluded() {
    // use $file to collect an array of all <loc> urls
    $arr = $this->retrieveLocLinks();
    // set empty array
    $localearr = array();
    // loop through all urls from XML file
    foreach ($arr as $url) {
      // parse_url to locate the locale, and add to the $localearr
      array_push($localearr, substr(parse_url($url, PHP_URL_PATH), 1, 2));
    }
    // remove duplicates from $localearr, and check the count is always 1. This indicates only one locale is present in XML
    $this->assertTrue(count(array_unique($localearr)) == 1);
  }

  /**
  * Test to ensure all cities are set to priority 0.7
  */
  public function testAllCitiesHaveCorrectPriority() {
    // Retrieve all city urls
    $cityurls = array_column((new SQLiteRead())->retrieveCities(), 'url');
    // use $file to collect an array of all <loc> urls and corresponding <priority> tags
    $priorityarr = array();
    // for each <loc> element
    foreach($this->xml->getElementsByTagName('url') as $url) {
      // set $urloc as the value of the <loc> tag
      $urlloc = $url->getElementsByTagName('loc')->item(0)->nodeValue;
      // if the <loc> value is in the list of top 20 cities
      if (in_array($urlloc, $cityurls)) {
        // add the priority value to the array
        array_push($priorityarr, $url->getElementsByTagName('priority')->item(0)->nodeValue);
        // assert the priority values are all the same, and the value of the first iteration is 0.7
        $this->assertTrue(count(array_unique($priorityarr)) == 1 && $priorityarr[0]=='0.7');
      }
    }
  }

  /**
  * Test to ensure all activities are set to priority 0.5
  */
  public function testAllActivitiesHaveCorrectPriority() {
    // Retrieve all activity urls
    $eventurls = array_column((new SQLiteRead())->retrieveEvents(), 'url');
    // use $file to collect an array of all <loc> urls and corresponding <priority> tags
    $priorityarr = array();
    // for each <loc> element
    foreach($this->xml->getElementsByTagName('url') as $url) {
      // set $urloc as the value of the <loc> tag
      $urlloc = $url->getElementsByTagName('loc')->item(0)->nodeValue;
      // if the <loc> value is in the list of top 20 activities for the top 20 cities
      if (in_array($urlloc, $eventurls)) {
        // add the priority value to the array
        array_push($priorityarr, $url->getElementsByTagName('priority')->item(0)->nodeValue);
        // assert the priority values are all the same, and the value of the first iteration is 0.5
        $this->assertTrue(count(array_unique($priorityarr)) == 1 && $priorityarr[0]=='0.5');
      }
    }
  }

  /**
  * Test to ensure all other links are set to priority 1.0
  */
  public function testAllOtherLinksHaveCorrectPriority() {
    // Retrieve all city urls
    $cityurls = array_column((new SQLiteRead())->retrieveCities(), 'url');
    // Retrieve all activity urls
    $eventurls = array_column((new SQLiteRead())->retrieveEvents(), 'url');
    // use $file to collect an array of all <loc> urls and corresponding <priority> tags
    $priorityarr = array();
    // for each <loc> element
    foreach($this->xml->getElementsByTagName('url') as $url) {
      // set $urloc as the value of the <loc> tag
      $urlloc = $url->getElementsByTagName('loc')->item(0)->nodeValue;
      // if the <loc> value is not in either top 20 list
      if (!in_array($urlloc, $cityurls) && !in_array($urlloc, $eventurls)) {
        // troubleshooting
        // if ($url->getElementsByTagName('priority')->item(0)->nodeValue != '1.0') {
        //   echo "/r/n"." LOCATION ".$urlloc . " - " . $url->getElementsByTagName('priority')->item(0)->nodeValue;
        // }
        // add the priority value to the array
        array_push($priorityarr, $url->getElementsByTagName('priority')->item(0)->nodeValue);
        // assert the priority values are all the same, and the value of the first iteration is 1.0
        $this->assertTrue(count(array_unique($priorityarr)) == 1 && $priorityarr[0]=='1.0');
      }
    }
  }

  /**
  * Test all url 'stems' relate to cities in the top 20 cities
  */
  public function testAllCitiesInTop20() {
    // Retrieve all city urls
    $cityurls = array_column((new SQLiteRead())->retrieveCities(), 'url');
    // use $file to collect an array of all <loc> urls
    $arr = $this->retrieveLocLinks();
    $notintop20 = array();
    // loop through every loc
    foreach ($arr as $url) {
      // every url which has 4 slashes in the path
      if (substr_count(parse_url($url, PHP_URL_PATH), '/') == 4) {
        // find the 'stem' of the url
        $stem = (new FilterManipulateData())->buildCityURL($url);
        // if the city stem of the url is in the list of top 20 cities
        if (!in_array($stem, $cityurls)) {
          // add to the array
          array_push($notintop20, $stem);
        }
      }
    }
    // troubleshooting
    // var_dump($notintop20);
    // test to see if the there is anything in the array
    $this->assertTrue(empty($notintop20));
  }

/**
* Test to check all events in XML file are in top 20
*/
  public function testAllEventsInTop20() {
    // Retrieve all city urls
    $eventurls = array_column((new SQLiteRead())->retrieveEvents(), 'url');
    // use $file to collect an array of all <loc> urls
    $arr = $this->retrieveLocLinks();
    $notintop20 = array();
    // loop through every loc
    foreach ($arr as $url) {
      // set $path of url
      $path = parse_url($url, PHP_URL_PATH);
      // every url which has 4 slashes in the path and ends with a number (definition of event without scanning)
      if (substr_count($path, '/') == 4 && is_numeric(substr($path, -2, 1))) {
        // if the city stem of the url is in the list of top 20 cities
        if (!in_array($url, $eventurls)) {
          // add to the array
          array_push($notintop20, $url);
        }
      }
    }
    // test to see if the there is anything in the array
    $this->assertTrue(empty($notintop20));
  }

  /**
  * Test to see if there are any duplicate urls in XML
  */
  public function testDuplicateLocs() {
    // set $dupes var = 0;
    $dupes = 0;
    // use $file to collect an array of all <loc> urls
    $arr = $this->retrieveLocLinks();
    // test for dupes
    // set $uqarr as unique links only
    $uqarr = array_unique($arr);
    if (count($arr) != count($uqarr)) {
      $dupes = 1;
    }
    $this->assertTrue($dupes===0);
  }



// Section for Exception Testing
  /**
  * Test exception thrown for incorrect URL used for API request
  */
  public function testGetAPIDataInvalidURL() {
    // Feed invalid URL into getAPIData function
    $this->expectException('Exception');
    $apiurl = 'THISAINTGOINGTOWORK';
    $locale = 'es';
    $testdata = (new CurlDataRetrieval())->getAPIData($apiurl, $locale);
  }

  /**
  * Test exception thrown for incorrect URL used for getPageData request
  */
  public function testGetPageData() {
    // Feed invalid URL into getAPIData function
    $this->expectException('Exception');
    $url = 'THISAINTGOINGTOWORK';
    $testdata = (new CurlDataRetrieval())->getPageData($url);
  }

  public function testBuildXMLWithExistingFileName() {
    // execute createXMLFile method with existing filename
    $this->expectException('Exception');
    $filename = 'test.xml';
    fopen($filename, 'w');
    $testarr = array();
    (new BuildXML($testarr))->createXMLFile($filename);
  }

}

?>
