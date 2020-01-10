<?php

namespace App;

/**
 * cURL Functions
 */
class CurlDataRetrieval {

  // Uses API URL and locale to retrieve data from the API and send back an array of json elements
  // @param string $apiurl
  // @param string $locale
  // @return array $output containing data retrieved from the API
  public function getAPIData($apiurl, $locale) {
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

}
