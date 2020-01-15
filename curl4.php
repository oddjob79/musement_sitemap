<?php

$url = 'https://api.musement.com/api/v3/cities/2/activities?limit=20';
$ch = curl_init($url);
$locale = 'es-ES';
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Accept-Language: '.$locale.'\'',
  'Content-Type: application/json'
]);
$res = curl_exec($ch);
curl_close($ch);

$activities = json_decode($res, true);

foreach ($activities['data'] as $activity) {
  echo $activity['url'].'</br>';
  echo substr($activity['url'], -2, 1);
}

var_dump($activities['data']);

?>
