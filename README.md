Musement.com SiteMap
-------------------------------------------------------------------------------
This is an application built in response to a request to build a sitemap for musement.com, as per the link:
https://gist.github.com/hpatoio/dff49e528feaea3f98bf57399d03da63

The application attempts to scan all web pages on the musement.com site (within certain criteria expanded below), locate links and then build a sitemap xml file based on those links. The sitemap is intended to follow the protocol as laid out on the following site: https://www.sitemaps.org/protocol.html.

The restrictions / limitations that were used for this were as follows:
<ul>
<li>Only pages following the selected locale are included.</li>
<li>Only pages which return an http 200 code are included (standard version only)</li>
<li>Pages which relate to a city are only included if the city is returned by the following API request - https://api.musement.com/api/v3/cities?limit=20</li>
<li>Activity pages are only included if they are returned by the following API request (per city) - https://api.musement.com/api/v3/cities/{city_id}/activities?limit=20</li>
<li>The pages were found directly on one of the "top 20 city" or "top 20 activity" pages, as defined above (lite version only)</li>
</ul>

Thank you for taking the time to review the project, I hope you find it agreeable. Please don't hesitate to contact me with any questions.

How to use
-------------------------------------------------------------------------------
The application has been containerized and uploaded to Heroku, and is available here - https://musement-sitemap.herokuapp.com/sitemap.php
To use, complete the form on the homepage. Select the locale you would like to generate the sitemap for, followed by the scan version (lite or standard). Finally, select the filename for the sitemap and click 'Scan Now' to begin scanning. You should see a list of all previously builit sitemap files on the homepage as well as the current file which is being run.

Database
-------------------------------------------------------------------------------
I used a SQLite database to manage the data involved in this application. Each time you run the application, the database is deleted and re-created.
If you choose to download the repo and run it locally, you will need to copy the App\SampleConfig.php file to App\Config.php and change the PATH_TO_SQLITE_FILE constant to your database location.

Testing
-------------------------------------------------------------------------------
I have used phpunit as a testing platform to run some basic tests against the XML output and some of the custom exceptions thrown by the application.
To run the tests, you will need to have the repo running locally and execute the script using $phpunit SMTest.php from the project folder. The test is run against the current database and the latest xml file that is located in the xml folder.

Documentation
-------------------------------------------------------------------------------
Technical documentation has been generated using phpdocumentor. Please browse to https://musement-sitemap.herokuapp.com/docs for access to this information.

Things to know
-------------------------------------------------------------------------------
Performance
-----------
I struggled with the performance of the app more than anything, and this led me to make a number of compromises when putting the app together.

First and foremost, I decided to leave out the below scrapeView() method for detecting the page type via the "window.__INITIAL_STATE__", in favour of using the file naming convention to tell what the page type was. Unfortunately, the app is no longer as robust as I would have liked, although it does reduce the number of times it has to parse the web page for information, so did improve the time taken to run the scan dramatically.

The performance issues also led me to include a "lite" version of the scan, which pre-populated the links table with all city and activity data from the API, and simply scanned these pages for other links. The retrieved links were not then scanned themselves, so were not checked to see if there were http redirects.
<?php
private function scrapeView($xml) {
  // define $state and $view  as empty strings
  $state = ''; $view = '';

  // locate the window.__INITIAL_STATE__ script which contains page details
  foreach($xml->getElementsByTagName('script') as $script) {
    $view = '';
    if (substr($script->textContent, 0, 24) == 'window.__INITIAL_STATE__') {
      // remove beginning and end of string so you are left with json only - this is the "state" of the page
      $state = substr($script->textContent, 25, -122);
      continue;
    }
  }
  // if $state exists then set $view
  if ($state != '') {
    // decode json string
    $stateinfo = json_decode($state);
    // returns the view value (contains the page type)
    $view = $stateinfo->state->router->view;
  }

  return $view;
}
?>

Background Processes
--------------------
Due to the length of time it takes to run the website scanning process, having come to the end of the project, I feel that a more elegant way to handle the background process is needed. I have not implemented this code, as I feel that the project has to stop somewhere, but this would be my next step if I were to take it further. I would be looking firstly at something along the lines of this post: https://stackoverflow.com/questions/45953/php-execute-a-background-process or the proc_open function to try and run the scan in the background only. Also providing a way for the user to either stop the current process or restart the apache service might be desirable.

Last Mod Date
-------------
The only other point of note would be to say that I deliberately left out the <lastmod> tag from the XML, as I was not able to find the Date Modified in any of the web page header information by using curl_getinfo($curl,  CURLINFO_FILETIME)
