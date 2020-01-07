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
    $info = curl_getinfo($ch);

    // close curl resource to free up system resources
    curl_close($ch);

    return array('info'=>$info, 'content'=>$output);
    // echo ($output);
  }

  function relativeToAbsoluteLinks($rellinks) {
    $abslinks = array();
    foreach ($rellinks as $link) {
      if (substr($link, 0, 4) == 'http') {
        array_push($abslinks, $link);
      } else {
        array_push($abslinks, 'https://www.musement.com'.$link);
      }
    }
    return $abslinks;
  }

  function getLinks($content) {            // adapted from example on PHP.NET/manual given by Jay Gilford

      // Create a new DOM Document to hold our webpage structure
      $xml = new DOMDocument();

      // set error level
      $internalErrors = libxml_use_internal_errors(true);

      // Load the url's contents into the DOM
      $xml->loadHTML($content);

      // Restore error level
      libxml_use_internal_errors($internalErrors);

      // Empty array to hold all links to return
      $links = array();

      //Loop through each <a> tag in the dom and add it to the link array
      foreach($xml->getElementsByTagName('a') as $link) {
        if (substr($link->getAttribute('href'), 0, 7) != 'mailto:' && substr($link->getAttribute('href'), 0, 4) != 'tel:') {
          // $links[] = array('url' => $link->getAttribute('href'));
          array_push($links, $link->getAttribute('href'));
          // $links[] = array('url' => $link->getAttribute('href'), 'text' => $link->nodeValue);
        }
      }

      $links = relativeToAbsoluteLinks($links);

      //Return the links
      return $links;
  }


  // call function to retrieve page data
  $target = "https://www.musement.com/es/";
  // $target = "https://www.php.net/manual/en/function.explode.php"; // example url with last modified time in header

  $res = getPageData($target);
  $pageinfo = $res['info'];
  $content = $res['content'];

  echo 'HTTP CODE = ', $pageinfo['http_code']; // $http_code;
  echo '<br />URL = ', $pageinfo['url']; // $url;
  if ($pageinfo['filetime'] != -1) {
    echo '<br />Filetime = ', date("Y-m-d H:i:s", $pageinfo['filetime']); // date("Y-m-d H:i:s", $filetime);
  } else {
    echo '<br />Filetime = None';
  }

  $linklist = getLinks($content);
  echo '<br />Links: ';
  var_dump($linklist);
  // echo '<br />Content = ', $content;



?>
