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

$arr = array(1, 2, 3, 4, 5);
var_dump($arr);
foreach ($arr as $key => $value) {
  $arr[$key]=$value+1;
  // code...
}
var_dump($arr);
exit();
$testarr = array();
array_push($testarr, array('url'=>'sitemap-p/', 'type'=>'other', 'include'=>1));
array_push($testarr, array('url'=>'test', 'type'=>'other', 'include'=>1));
array_push($testarr, array('url'=>'wibble', 'type'=>'wibble', 'include'=>0));

var_dump($testarr);

$activities = json_decode($res, true);

foreach ($activities['data'] as $activity) {
  echo $activity['url'].'</br>';
  echo substr($activity['url'], -2, 1);
}

var_dump($activities['data']);

?>
