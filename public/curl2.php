<?php

  $url = 'https://api.musement.com/api/v3/cities';
  $ch = curl_init($url);
  $locale = 'es-ES';
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept-Language: '.$locale.'\'',
    'Content-Type: application/json'
  ]);
  $res = curl_exec($ch);
  curl_close($ch);

  $cities = json_decode($res, true);

  $citydata = array();
  // var_dump($cities);
  foreach ($cities as $city) {
    $citarr = array('id'=>$city['id'], 'url'=>$city['url']);
    array_push($citydata, $citarr);
    // echo '<br />URL: ', $city['url'];
  }

  var_dump($citydata);

?>
