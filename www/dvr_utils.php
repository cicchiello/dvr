<?php

function deltaTimeStr($deltaTime)
{
   $deltaM = floor($deltaTime/60);
   $deltaH = floor($deltaM/60);
   $tstr = '';
   if ($deltaH == 0)
      $tstr .= $deltaM.' min';
   else {
      $tstr .= $deltaH.'hr'.($deltaH>1?'s':'');
      $remainder = $deltaM - 60*$deltaH;
      if ($remainder > 0) $tstr .= ', '.$remainder.' min';
   }

   return $tstr;
}


function realFileSize($path)
{
    $fp = fopen($path,"r");
    $pos = 0;
    $size = 1073741824;
    fseek($fp, 0, SEEK_SET);
    while ($size > 1) {
        fseek($fp, $size, SEEK_CUR);

        if (fgetc($fp) === false) {
            fseek($fp, -$size, SEEK_CUR);
            $size = (int)($size / 2);
        } else {
            fseek($fp, -1, SEEK_CUR);
            $pos += $size;
        }
    }

    while (fgetc($fp) !== false)  $pos++;

    return $pos;
}


/**
* Converts bytes into human readable file size.
*
* @param string $bytes
* @return string human readable file size (2,87 Мб)
* @author Mogilev Arseny
*/
function readableSize($bytes)
{
    $bytes = floatval($bytes);
    $arBytes = array(
        0 => array(
	    "UNIT" => "TB",
	    "VALUE" => pow(1024, 4)
	),
	1 => array(
	    "UNIT" => "GB",
	    "VALUE" => pow(1024, 3)
	),
	2 => array(
	    "UNIT" => "MB",
	    "VALUE" => pow(1024, 2)
	),
	3 => array(
	    "UNIT" => "KB",
	    "VALUE" => 1024
	),
	4 => array(
	    "UNIT" => "B",
	    "VALUE" => 1
	),
    );

    foreach($arBytes as $arItem) {
        if($bytes >= $arItem["VALUE"]) {
	    $result = strval(round($bytes / $arItem["VALUE"], 2))." ".$arItem["UNIT"];
	    break;
	}
    }
    return $result;
}


function renderLookAndFeel()
{
   $result = '';
   $result .= '<link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />';
   $result .= '<link href="./w3.css" media="all" rel="stylesheet">';
   $result .= '<link href="./style.css" media="all" rel="stylesheet">';
   $result .= '<link href="./menu2.css" media="all" rel="stylesheet">';
   return $result;
}


function getMenuCnts()
{
   $ini = parse_ini_file("./config.ini");
   $DbBase = $ini['couchbase'];
   $Db = "dvr";
   $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';

   $url = "http://ipv4-api.hdhomerun.com/discover";
   $devices = json_decode(file_get_contents($url), true);

   $numRecordings = 0;
   $numChannels = 0;
   $numScheduled = 0;
   foreach ($devices as $device) {
      $deviceUrl = $device['DiscoverURL'];
      $device_detail = json_decode(file_get_contents($deviceUrl), true);
      $lineupJsonUrl = $device_detail['LineupURL'];
      $recordingsUrl = $DbViewBase.'/recordings';
	     
      $numChannels += sizeof(json_decode(file_get_contents($lineupJsonUrl), true));
      $numRecordings += json_decode(file_get_contents($recordingsUrl), true)['total_rows'];

      $scheduledUrl = $DbViewBase.'/scheduled';
      $result = json_decode(file_get_contents($scheduledUrl), true);
      $scheduled = $result['rows'];
      $numScheduled = $result['total_rows'];
   }

   return array(
      "numChannels" => $numChannels,
      "numRecordings" => $numRecordings,
      "numScheduled" => $numScheduled
      );
}


function renderRecordingsTable($items, $actions)
{
   $result = '';
   $cnt = 0;
   foreach ($items as $item) {
      if ($cnt == 0) {
         $result .= '<table style="width:100%">';
      }

      if ($cnt%2 == 0) 
         $result .= '<tr style="background-color:#b8d7b0">';
      else
         $result .= '<tr style="background-color:#e2f4dd">';

      $id = $item['value']['_id'];
      $start = $item['value']['record-start'];
      $delta = $item['value']['record-end'] - $start;
      $channel = $item['value']['channel'];
      
      $result .= '  <th rowspan="1" style="text-align:left">'.$item['value']['description'].'</th>';
      $result .= '  <td>';
      $result .= '              <div class="thumbs">';

      $q = "'";
      foreach ($actions as $action) {
	 $result .= '              <img onclick="'.$action['onclick'].'('.$q.$id.$q.')"';
         $result .= '                     src="'.$action['src'].'" class="Btn"';
         $result .= '                     width="32" height="32" title="'.$action['title'].'">';
      }

      $result .= '                 <span class="stretch"></span>';
      $result .= '              </div>';
      $result .= '  </td>';
      $result .= '</tr>';
      
      if ($cnt%2 == 0) 
         $result .= '<tr style="background-color:#b8d7b0">';
      else
         $result .= '<tr style="background-color:#e2f4dd">';
      $result .= '  <td>Ch '.$channel.' for '.deltaTimeStr($delta).'</td>';
      $result .= '  <td>'.date(" @h:i a \o\\n D M j, 'y",$start).'</td>';
      $result .= '</tr>';
      $cnt += 1;
   }
   if ($cnt == 0) {
      $result .= "<p>No Recordings available.</p>";
   } else {
      $result .= '</table>';
   }
   return $result;
}

function renderEntryInfo($id)
{
   $ini = parse_ini_file("./config.ini");
   $DbBase = $ini['couchbase'];
   $Db = "dvr";
   $detailUrl = $DbBase.'/'.$Db.'/'.$id;

   $detail = json_decode(file_get_contents($detailUrl), true);
       
   $channel = $detail['channel'];
   $description = $detail['description'];
   $recordStart = $detail['record-start'];
   $recordEnd = $detail['record-end'];
   $date = date("D M j, 'y",$recordStart);
   $startTimeStr = date("h:i a",$recordStart);
   $actualStart = $detail['capture-start-timestamp'];
   $actualStartStr = date("h:i a",$actualStart);
   $actualEnd = $detail['capture-stop-timestamp'];
   $file = $detail['file'];
   $isCompressed = $detail['is-compressed'];

   $url = "http://ipv4-api.hdhomerun.com/discover";
   $devices = json_decode(file_get_contents($url), true);
   foreach ($devices as $device) {
      $deviceUrl = $device['DiscoverURL'];
      $device_detail = json_decode(file_get_contents($deviceUrl), true);
      $deviceId = $device_detail['DeviceID'];
      $lineupJsonUrl = $device_detail['LineupURL'];
      $lineup = json_decode(file_get_contents($lineupJsonUrl), true);
   }

   $channelName = 'unknown';
   foreach ($lineup as $c) {
      $num = $c['GuideNumber'];
      $name = $c['GuideName'];
      if ($num === $channel) $channelName = $name;
   }

   $showDbId = false;
   
   $result = '';
   $result .= '<table>';
   $result .= '  <tr>';
   $result .= '    <td>Channel:</td>';
   $result .= '    <td>';
   $result .= '       <b style="color:blue" class="w3-right">'.$channel.'</b>';
   $result .= '    </td>';
   $result .= '    <td>';
   $result .= '       <b style="color:blue" class="w3-right">'.$channelName.'</b>';
   $result .= '	   </td>';
   $result .= '	 </tr>';
   $result .= '	 <tr>';
   $result .= '	   <td>Description:</td>';
   $result .= '	   <td colspan="2">';
   $result .= '	      <b style="color:blue" class="w3-right">'.$description.'</b>';
   $result .= '	   </td>';
   $result .= '	 </tr>';
   $result .= '	 <tr>';
   $result .= '	   <td>Date:</td>';
   $result .= '	   <td colspan="2">';
   $result .= '	      <b style="color:blue" class="w3-right">'.$date.'</b>';
   $result .= '	   </td>';
   $result .= '	 </tr>';

   $startDiscrepancy = abs($recordStart - $actualStart) > 60;
   $result .= '	 <tr>';
   $result .= '	   <td>'.($startDiscrepancy ? 'Scheduled Start':'Start Time').':</td>';
   $result .= '	   <td colspan="2">';
   $result .= '	      <b style="color:blue" class="w3-right">'.$startTimeStr.'</b>';
   $result .= '	   </td>';
   $result .= '	 </tr>';
   if ($startDiscrepancy) {
      $result .= '	 <tr>';
      $result .= '	   <td>Actual Start:</td>';
      $result .= '	   <td colspan="2">';
      $result .= '	      <b style="color:red" class="w3-right">'.$actualStartStr.'</b>';
      $result .= '	   </td>';
      $result .= '	 </tr>';
      $showDbId = true;
   }

   $scheduledDuration = deltaTimeStr($recordEnd-$recordStart);
   $actualDuration = deltaTimeStr($actualEnd-$actualStart);
   $durationDiscrepancy = abs($scheduledDuration - $actualDuration) > 60;
   $result .= '	 <tr>';
   $result .= '	   <td>'.($durationDiscrepancy ? 'Scheduled Duration':'Duration').':</td>';
   $result .= '	   <td colspan="2">';
   $result .= '	      <b style="color:blue" class="w3-right">'.$scheduledDuration.'</b>';
   $result .= '	   </td>';
   $result .= '	 </tr>';
   if ($durationDiscrepancy) {
      $result .= '	 <tr>';
      $result .= '	   <td>Actual Duration:</td>';
      $result .= '	   <td colspan="2">';
      $result .= '	      <b style="color:red" class="w3-right">'.$actualDuration.'</b>';
      $result .= '	   </td>';
      $result .= '	 </tr>';
      $showDbId = true;
   }

   $fileExists = file_exists($file);
   $result .= '	 <tr>';
   if ($fileExists) {
      $result .= '	   <td>'.($isCompressed ? 'Compressed ':'').'File size:</td>';
      $result .= '	   <td colspan="2">';
      $result .= '<b style="color:blue" class="w3-right">'.readableSize(realFileSize($file));
   } else {
      $result .= '	   <td>File not found:</td>';
      $result .= '	   <td colspan="2">';
      $result .= '<b style="color:red; font-size:80%;" class="w3-right">'.$file;
      $showDbId = true;
   }
   $result .= '       </b>';
   $result .= '	   </td>';
   $result .= '	 </tr>';

   $showDbId = true;
   if ($showDbId) {
      $result .= '  <tr>';
      $result .= '    <td>Db Id:</td>';
      $result .= '    <td colspan="2">';
      $result .= '       <p style="color:blue; font-size:80%" class="w3-right">'.$id.'</p>';
      $result .= '    </td>';
      $result .= '  </tr>';
      $result .= '</table>';
   }
   
   return $result;
}


function renderMainMenu()
{
   $d = getMenuCnts();
   $enabled = array(
      'live' => true,
      'recordings' => true,
      'scheduled' => true
   );
   $refs = array(
      'live' => './live.php',
      'recordings' => './recordings.php',
      'scheduled' => './schedules.php'
   );

   $result = '';
   $result .= ' <div id="menuArea">';
   $result .= '   <input onclick="menuAction()" type="image" src="img/showmenu.png"';
   $result .= '          width="64" height="64" title="Menu" class="Btn">';
   $result .= '   <div id="menuItems" class="w3-hide">';
   $result .= renderMenuItems($enabled,$refs);
   $result .= '   </div>';
   
   return $result;
}


function renderMenuItems($enabled,$refs)
{
   $d = getMenuCnts();

   $imgs = array(
      'live' => 'img/livetv2.png',
      'recordings' => 'img/video.png',
      'scheduled' => 'img/schd.png'
   );
   $imgs_gray = array(
      'live' => 'img/livetv2-gray.png',
      'recordings' => 'img/video-gray.png',
      'scheduled' => 'img/schd-gray.png'
   );
   $lbl = array(
      'live' => 'Channels',
      'recordings' => 'Recordings',
      'scheduled' => 'Scheduled'
   );
   $lbl_val = array(
      'live' => $d['numChannels'],
      'recordings' => $d['numRecordings'],
      'scheduled' => $d['numScheduled']
   );
   
   $result = '';

   $cnt = 1;
   foreach(array_keys($enabled) as $key) {
      if ($refs[$key]) {
         $result .= '<a class="_URL" href="'.$refs[$key].'">';
      }
      $result .= '     <div class="menuLbl Btn" title="'.$key.'">';
      if ($enabled[$key]) {
         $result .= '<img id="menu'.$cnt.'" src="'.$imgs[$key].'" width="64" height="64" class="Btn">';
         $result .= '<p><b>'.$lbl_val[$key].' '.$lbl[$key].'</b></p>';
      } else {
         $result .= '<img id="menu'.$cnt.'" src="'.$imgs_gray[$key].'" width="64" height="64">';
         $result .= '<span style="color:#7a9538"><p><b>'.$lbl_val[$key].' '.$lbl[$key].'</b></p></span>';
      }
      $cnt += 1;
      $result .= '     </div>';
      if ($refs[$key]) {
         $result .= '</a>';
      }
   }
   
   $result .= '   </div>';

   return $result;
}


function renderMenu($enabled)
{
   $result = '';
   $result .= ' <div id="menuArea">';
   $result .= '   <a class="_URL" href="./index.php">';
   $result .= '     <img src="img/home.png" width="64" height="64" title="Home" class="Btn">';
   $result .= '   </a>';
   $result .= '   <div id="menuItems" class="w3-show">';
   $result .= renderMenuItems($enabled);
   $result .= '   </div>';
   $result .= ' </div>';
   return $result;
}


?>
