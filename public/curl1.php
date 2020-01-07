<?php

  function getPageData($url) {

    // create curl resource
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, $url);

    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // include headers in output - probably not needed
    // curl_setopt($ch, CURLOPT_HEADER, true);

    // Retrieve last modified file time
    curl_setopt($ch, CURLOPT_FILETIME, true);

    // $output contains the output string
    $output = curl_exec($ch);

    // use curl_getinfo to get information about the resource
    // $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    // $filetime = curl_getinfo($ch,  CURLINFO_FILETIME);
    $pageinfo = curl_getinfo($ch);

    // close curl resource to free up system resources
    curl_close($ch);

    return $pageinfo;
    // echo ($output);
  }

  // call function to retrieve page data
  $target = "https://www.musement.com/es/";
  // $target = "https://www.php.net/manual/en/function.explode.php"; // example url with last modified time in header

  $pageinfo = getPageData($target);

  echo 'HTTP CODE = ', $pageinfo['http_code']; // $http_code;
  echo '<br />URL = ', $pageinfo['url']; // $url;
  if ($pageinfo['filetime'] != -1) {
    echo '<br />Filetime = ', date("Y-m-d H:i:s", $pageinfo['filetime']); // date("Y-m-d H:i:s", $filetime);
  } else {
    echo '<br />Filetime = None';
  }



?>
