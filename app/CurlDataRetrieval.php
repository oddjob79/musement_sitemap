<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

/**
 * Collection of functions used to gather data from external servers
 */
class CurlDataRetrieval {

  /**
  * Takes the API URL and locale, and uses cURL request to retrieve data from the API, then return an array of json elements
  * @param string $apiurl
  * @param string $locale
  * @return array $output containing data retrieved from the API
  */
  public function getAPIData($apiurl, $locale) {
    // convert $locale to correct format. From "es" to "es-ES" for example
    $locale = $locale.'-'.strtoupper($locale);
    // initialize curl request
    $ch = curl_init($apiurl);
    // allows a string to be set to the result of curl_exec
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // set locale and content type in header
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Accept-Language: '.$locale.'\'',
      'Content-Type: application/json'
    ]);
    if (($res = curl_exec($ch)) == null) {
      throw new \Exception(
        "Unable to retrieve data from API for '$apiurl'"
      );
    }
    curl_close($ch);

    // move the results into an array
    $output = json_decode($res, true);

    return $output;

  }

  /**
  * Takes a url, then uses cURL to retrieve page information and the actual page data from the given URL
  * @param string $url
  * @return array contains an array of page info, gathered using the curl_getinfo command, and page content, gathered using the curl_exec command
  */
  public function getPageData($url) {
    // create curl resource
    $ch = curl_init();
    // set url
    curl_setopt($ch, CURLOPT_URL, $url);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // Retrieve last modified file time
    curl_setopt($ch, CURLOPT_FILETIME, true);

    // $output contains the output string
    if (($output = curl_exec($ch)) == null) {
      throw new \Exception (
        "Unable to retrieve web page '$url'"
      );
    }

    // use curl_getinfo to get information about the resource
    $info = curl_getinfo($ch);

    // close curl resource to free up system resources
    curl_close($ch);

    return array('info'=>$info, 'content'=>$output);
  }

}
