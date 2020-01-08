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
      // if absolute link then leave as is
      if (substr($link, 0, 4) == 'http') {
        array_push($abslinks, $link);
      // if relative link then add protocol and domain
      } else {
        array_push($abslinks, 'https://www.musement.com'.$link);
        // array_push($abslinks, 'http://books.toscrape.com/'.$link);
      }
    }
    return $abslinks;
  }

  // pass html content from web page and return an array of links
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
      $pagelinks = array();

      //Loop through each <a> tag in the dom and add it to the link array
      foreach($xml->getElementsByTagName('a') as $link) {
        // if link is a mailto link or a tel link - ignore it
        if (substr($link->getAttribute('href'), 0, 7) != 'mailto:' && substr($link->getAttribute('href'), 0, 4) != 'tel:') {
          // $links[] = array('url' => $link->getAttribute('href'));
          array_push($pagelinks, $link->getAttribute('href'));
          // $links[] = array('url' => $link->getAttribute('href'), 'text' => $link->nodeValue);
        }
      }

      // convert all relative links to absolute
      $pagelinks = relativeToAbsoluteLinks($pagelinks);

      //Return the links
      return $pagelinks;
  }

  function writeData($pageinfo) {
    // echo sitemap information

    echo '<br />HTTP Code = ', $pageinfo['http_code'];
    echo '<br />URL = ', $pageinfo['url']; // $url;
    // echo '<br />Redirect URL = ', $pageinfo['redirect_url'];
    if ($pageinfo['filetime'] != -1) {
      echo '<br />Filetime = ', date("Y-m-d H:i:s", $pageinfo['filetime']); // date("Y-m-d H:i:s", $filetime);
    } else {
      echo '<br />Filetime = None';
    }
    echo '<br />';

    // return $pageinfo['url'];
  }

  function scanURLs($linksfound, $scannedurls) {
    // limit $linksfound for testing
    $linksfound = array_slice($linksfound, 0, 50);
    // for each url in masterlinks
    foreach ($linksfound as $link) {
      if (!in_array($link, $scannedurls)) {
        // use curl to get pgae data
        $res = getPageData($link);
        // separate into page content (for links) and page info (for sitemap)
        $pageinfo = $res['info'];
        $pagecontent = $res['content'];

        // Only evaluate links which are valid http codes
        if ($pageinfo['http_code'] != 200) {
          array_push($scannedurls, $pageinfo['url']);
          continue;
        }

        // Write to sitemap
        writeData($pageinfo);

        // GET NEW LINKS
        // Only scan musement pages for more links
        if (substr(parse_url($link, PHP_URL_HOST), -12) == 'musement.com') {
          // generate list of links from page content
          $linklist = getLinks($pagecontent);
          // merge list of links returned from page with "master" list of links
          $linksfound = array_merge($linksfound, $linklist);
          // remove dupes
          $linksfound = array_unique($linksfound);
        }

        //update $scannedurls
        array_push($scannedurls, $pageinfo['url']);

      }
    }
    return array('newlinks'=>$linksfound, 'written'=>$scannedurls);
  }

  // call function to retrieve page data
  $target = "https://www.musement.com/es/";
  // $target = "http://books.toscrape.com/";
  // $target = "https://www.php.net/manual/en/function.explode.php"; // example url with last modified time in header

  // // TEST getting page data
  // $res = getPageData($target);
  // echo $res['content'];
  // exit;

  $linksfound = array($target);
  $scannedurls = array();
  $i=0;

  while (!empty(array_diff($linksfound, $scannedurls)) && $i<2) {
    echo '<br />Loop number '.$i.'<br />';
    $output = scanURLs($linksfound, $scannedurls);
    $linksfound = $output['newlinks'];
    $scannedurls = $output['written'];
    $i++;
  }

  echo '<br />Located Links:<br />';
  var_dump($linksfound);

  echo '<br />Scanned URLs:<br />';
  var_dump($scannedurls);


?>
