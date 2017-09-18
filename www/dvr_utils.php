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


function renderMenu()
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
   
   $result = '';
   $result .= ' <div id="menuArea">';
   $result .= '   <a class="_URL" href="./index.php">';
   $result .= '      <img src="img/home.png" width="64" height="64" title="Home" class="Btn">';
   $result .= '   </a>';
   $result .= '   <div id="menuItems" class="w3-show">';
   $result .= '      <div class="menuLbl Btn">';
   $result .= '         <img id="menu1" src="img/livetv2-gray.png" width="64" height="64">';
   $result .= ' 	   <span style="color:#7a9538"><p><b>'.$numChannels.' Channels</b></p></span>';
   $result .= '      </div>';
   $result .= '      <div class="menuLbl Btn" title="Recordings">';
   $result .= '         <img id="menu2" src="img/video.png" width="64" height="64">';
   $result .= '         <p><b>'.$numRecordings.' Recordings</b></p>';
   $result .= '      </div>';
   $result .= '      <div class="menuLbl Btn">';
   $result .= '         <img id="menu3" src="img/schd-gray.png" width="64" height="64">';
   $result .= '         <span style="color:#7a9538"><p><b>'.$numScheduled.' Scheduled</b></p></span>';
   $result .= '      </div>';
   $result .= '   </div>';
   $result .= '</div>';
   return $result;
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
   $startTime = date("h:i a",$recordStart);

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
      if ($num == $channel) $channelName = $name;
   }

   $result = '';
   $result .= '<fieldset>';
   $result .= '	  <legend>Recording Detail:</legend>';
   $result .= '	  <table>';
   $result .= '	    <tr>';
   $result .= '	      <td>Channel:</td>';
   $result .= '	      <td>';
   $result .= '	         <b style="color:blue" class="w3-right">'.$channel;
   $result .= '		 </b>';
   $result .= '	      </td>';
   $result .= '	      <td>';
   $result .= '	         <b style="color:blue" class="w3-right">'.$channelName;
   $result .= '		 </b>';
   $result .= '	      </td>';
   $result .= '	    </tr>';
   $result .= '	    <tr>';
   $result .= '	      <td>Description:</td>';
   $result .= '	      <td colspan="2">';
   $result .= '	         <b style="color:blue" class="w3-right">'.$description;
   $result .= '		 </b>';
   $result .= '	      </td>';
   $result .= '	    </tr>';
   $result .= '	    <tr>';
   $result .= '	      <td>Date:</td>';
   $result .= '	      <td colspan="2">';
   $result .= '	         <b style="color:blue" class="w3-right">'.$date;
   $result .= '		 </b>';
   $result .= '	      </td>';
   $result .= '	    </tr>';
   $result .= '	    <tr>';
   $result .= '	      <td>Start Time:</td>';
   $result .= '	      <td colspan="2">';
   $result .= '	         <b style="color:blue" class="w3-right">'.$startTime;
   $result .= '		 </b>';
   $result .= '	      </td>';
   $result .= '	    </tr>';
   $result .= '	    <tr>';
   $result .= '	      <td>Duration:</td>';
   $result .= '	      <td colspan="2">';
   $result .= '	         <b style="color:blue" class="w3-right">';
   $result .= deltaTimeStr($recordEnd-$recordStart);
   $result .= '		 </b>';
   $result .= '	      </td>';
   $result .= '	    </tr>';
   $result .= '	  </table>';
   $result .= '	</fieldset>';
   
   return $result;
}

?>
