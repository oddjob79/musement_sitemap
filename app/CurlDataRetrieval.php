<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

/**
 * cURL Functions
 */
class CurlDataRetrieval {

  // Uses API URL and locale to retrieve data from the API and send back an array of json elements
  // @param string $apiurl
  // @param string $locale
  // @return array $output containing data retrieved from the API
  public function getAPIData($apiurl, $locale) {
    // convert $locale to correct format. From "es" to "es-ES" for example
    $locale = $locale.'-'.strtoupper($locale);
    // $url = 'https://api.musement.com/api/v3/cities';
    $ch = curl_init($apiurl);
    // allows a string to be set to the result of curl_exec
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // set locale and content type in header
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Accept-Language: '.$locale.'\'',
      'Content-Type: application/json'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    // move the results into an array
    $output = json_decode($res, true);

    return $output;

  }

  // use curl to retrieve page content and information for specified url
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
    $output = curl_exec($ch);
    // use curl_getinfo to get information about the resource
    $info = curl_getinfo($ch);
    // close curl resource to free up system resources
    curl_close($ch);

    return array('info'=>$info, 'content'=>$output);
  }

}
