<?php

  // use curl to retrieve page content and information for specified url
  function getPageData($url) {
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

  // convert relative to absolute links
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

  // Uses API URL and locale to retrieve data from the API and send back an array of json elements
  function getAPIData($apiurl, $locale) {
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

  function getCityData($locale) {
    // retrieve array containing all city data for that locale
    $cities = getAPIData('https://api.musement.com/api/v3/cities?limit=20', $locale);

    // create a new array containing all the city urls only
    $cityurls = array();
    $cityids = array();
    foreach ($cities as $city) {
      // $citarr = array('id'=>$city['id'], 'url'=>$city['url']);
      array_push($cityurls, $city['url']);
      array_push($cityids, $city['id']);
    }
    return array('urls'=>$cityurls, 'ids'=>$cityids);
  }

  function skipURLs($url, $workedurls) {
    // set $scanit variable to default
    $skipit = false;
    // check to see if url has already been 'worked' (scanned or discarded)
    if (in_array($url, $workedurls)) {
      $skipit = true;
    }

    // build a list of urls to skip
    $skiplist = array();
    // is it in robots? - skip and add to $workedurls
    // $robots = 'https://www.musement.com/robots.txt';
    // $res = getPageData($robots);
    //
    // is it related to a non-top 20 city? - skip it and add to $workedurls


    // is it a non-top 20 activity? - skip it and add to $workedurls

    // return boolean to see whether to scan the page or not
    return $skipit;
  }

  function filterURLs($url, $workedurls) {
    $writeit = true;
    // TO DO - set locale (testing only, will need to be set properly elsewhere)
    $locale = 'es-ES';
    // retrieve city list
    $citylist = getCityURLs($locale);

    // remove everything after the second element in the url path to see if it is a city page or the child of city page
    $path = parse_url($url, PHP_URL_PATH);
    $city = strstr(substr($path, 4), '/', true);
    // rebuild the url using the schema, the host, and the parsed path
    $cityurl = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).substr($path, 0,  4).$city.'/';
    // Compare stem of URL with list of cities retrieved from the API to see if the url is a city page or the child of a city page
    // we only want to exclude 'city related' pages if they are not in the 'top 20' cities
    if (in_array($cityurl, $citylist)) {
      // It's a city. Is it in the 'top 20' cities?
      if (!in_array($cityurl, array_slice($citylist, 0, 20))) {
        // if not, add it to the list of worked urls and don't waste processing by scanning it
        array_push($workedurls, $url);
        $writeit = false;
      }
    }

  }

  // pass html content from web page and return an array of links
  function parseContent($content) {            // adapted from example on PHP.NET/manual given by Jay Gilford
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
    // convert all relative links to absolute (do this now for comparison purposes)
    $pagelinks = relativeToAbsoluteLinks($pagelinks);

    // Retrieve the page 'type'
    // locate the window.__INITIAL_STATE__ script which contains page details
    foreach($xml->getElementsByTagName('script') as $script) {
      if (substr($script->textContent, 0, 24) == 'window.__INITIAL_STATE__') {
        // remove beginning and end of string so you are left with json only
        $state = substr($script->textContent, 25, -122);
        continue;
      }
    }

    // decode json string
    $stateinfo = json_decode($state);
    // returns the view value (contains the page type)
    $view = $stateinfo->state->router->view;

    //Return the links
    return array('view'=>$view, 'links'=>$pagelinks);
  }

  // specifies how the returned data will be output
  function writeData($pageinfo) {
    // echo sitemap information

    // echo '<br />HTTP Code = ', $pageinfo['http_code'];
    echo '<br />URL = ', $pageinfo['url']; // $url;
    // $url = $pageinfo['url'];
    // $prefix = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST);
    // $path = parse_url($url, PHP_URL_PATH);
    // $city = strstr(substr($path, 4), '/', true);
    // echo '<br />PATH = ', $prefix.substr($path, 0,  4).$city.'/';
    // echo '<br />Redirect URL = ', $pageinfo['redirect_url'];
    // if ($pageinfo['filetime'] != -1) {
    //   echo '<br />Filetime = ', date("Y-m-d H:i:s", $pageinfo['filetime']); // date("Y-m-d H:i:s", $filetime);
    // } else {
    //   echo '<br />Filetime = None';
    // }
    echo '<br />';

    // return $pageinfo['url'];
  }



  // uses a list of links found and a list of previously scanned urls to determine which urls to scan for new links
  // three levels of filtering:
  // pages you should not scan at all (already been found and scanned)
  // pages you have discarded after scanning (non http 200s)
  // pages that should be written but not searched for additional links (non-musemennt.com pages)
  function scanURLs($linksfound, $workedurls) {
    // limit $linksfound for testing
    $linksfound = array_slice($linksfound, 0, 50);
    // for each url in masterlinks
    foreach ($linksfound as $url) {
      // FILTER URLs before scanning
      if (skipURLs($url, $workedurls) == false) {
        // TO DO - depending on if we add to skip urls, maybe move this around
        // if we're not skipping it, we're working it. Update $workedurls TO DO - change to $link? this is what is being sent to getPageData
        array_push($workedurls, $url);

        // use curl to get page data
        $res = getPageData($url);
        // separate into page content (for links) and page info (for sitemap)
        $pageinfo = $res['info'];
        $pagecontent = $res['content'];

        // Only evaluate links which are valid http codes (filter after scanning & before writing)
        if ($pageinfo['http_code'] != 200) {
          // array_push($workedurls, $pageinfo['url']);
          continue;
        }

        // Only evaluate musement.com links
        if (substr(parse_url($url, PHP_URL_HOST), -12) != 'musement.com') {
          continue;
        }

        // Send page content to function to return only information which will be used
        $parsedcontent = parseContent($pagecontent);
        // generate list of links from page content
        $linklist = $parsedcontent['links'];
        // merge list of links returned from page with "master" list of links
        $linksfound = array_merge($linksfound, $linklist);
        // remove dupes
        $linksfound = array_unique($linksfound);

        // locate page type from page content
        $viewtype = $parsedcontent['view'];

        echo '<br />View type = ', $viewtype;

        // city, event, attraction, editorial

        // Now we have the links, check to see if it's a city-related page. Then to see if it's (related to) a top 20 city.
        if (in_array($viewtype, array('city', 'event', 'attraction', 'editorial'))) {
          // it's a city-related page. Find the city it relates to.
          // remove everything after the second element in the url path to see if it is a city page or the child of city page
          $path = parse_url($url, PHP_URL_PATH);
          $city = strstr(substr($path, 4), '/', true);
          // rebuild the url using the schema, the host, and the parsed path
          $cityurl = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).substr($path, 0,  4).$city.'/';
          // TO DO - set locale (testing only, will need to be set properly elsewhere)
          $locale = 'es-ES';
          // retrieve top 20 city list from API
          $citylist = getCityData($locale);

          // is the city one of the top 20 cities from the API?
          if (!in_array($cityurl, $citylist['urls'])) {
            echo '<br />This url is not in the top 20 cities - ', $cityurl;
            continue;
          }
        }

        // Write to sitemap
        writeData($pageinfo);


      }
    }
    return array('newlinks'=>$linksfound, 'written'=>$workedurls);
  }


  // START PROGRAM

  $target = "https://www.musement.com/es/";
  // $target = "http://books.toscrape.com/";
  // $target = "https://www.php.net/manual/en/function.explode.php"; // example url with last modified time in header

  // // TEST getting page data
  // $res = getPageData($target);
  // echo $res['content'];
  // exit;

  $linksfound = array($target);
  $workedurls = array();
  $i=0;

  // while there is a difference between the $linksfound list and the $workedurls list (i.e. there are more links to scan), continue searching for more links
  while (!empty(array_diff($linksfound, $workedurls)) && $i<2) {
    echo '<br />Loop number '.$i.'<br />';
    $output = scanURLs($linksfound, $workedurls);
    $linksfound = $output['newlinks'];
    $workedurls = $output['written'];
    $i++;
  }

  echo '<br />Located Links:<br />';
  var_dump($linksfound);

  echo '<br />Scanned URLs:<br />';
  var_dump($workedurls);


?>
