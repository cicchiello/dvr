<!DOCTYPE html>
<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
  
  <link href="./w3.css" media="all" rel="stylesheet">
  
  <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.common-material.min.css" />
  <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.material.min.css" />
  <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.dataviz.min.css" />
  <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.dataviz.material.min.css" />

  <script src="http://cdn.kendostatic.com/2015.1.429/js/jquery.min.js"></script>
  <script src="http://cdn.kendostatic.com/2015.1.429/js/kendo.all.min.js"></script>
  
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>

  <link href="./style.css" media="all" rel="stylesheet" />
  <link href="./table.css" media="all" rel="stylesheet" />
  <link href="./menu.css" media="all" rel="stylesheet" />
  <link href="./loader.css" media="all" rel="stylesheet" />
  
  <style>
  </style>

  <script>
    "use strict";

    function sleep(ms) {
       return new Promise(resolve => setTimeout(resolve, ms));
    }

    async function init() {
       //console.log('Taking a break...');
       await sleep(2000);
       //console.log('Two second later');
       open('./schedules.php',"_self");
    }
    
  </script>

  </head>
  
         <?php
	    $ini = parse_ini_file("./config.ini");
	    $DbBase = $ini['couchbase'];
	    $Db = "dvr";
	    $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';
	    $WriteDb = $DbBase.'/'.$Db;
	    
	    $url = "http://ipv4-api.hdhomerun.com/discover";
	    $devices = json_decode(file_get_contents($url), true);
	    
	    $numRecordings = 0;
	    $numChannels = 0;
	    $numScheduled = 0;
	    $deviceId = "TBD";
	    foreach ($devices as $device) {
	       $deviceUrl = $device['DiscoverURL'];
	       $device_detail = json_decode(file_get_contents($deviceUrl), true);
	       $deviceId = $device_detail['DeviceID'];
	       $lineupJsonUrl = $device_detail['LineupURL'];
	       $recordingsUrl = $DbViewBase.'/recordings';
	    
	       $lineupJson = json_decode(file_get_contents($lineupJsonUrl), true);
	       $numChannels += sizeof($lineupJson);
	       $numRecordings += json_decode(file_get_contents($recordingsUrl), true)['total_rows'];

	       $scheduledUrl = $DbViewBase.'/scheduled';
	       $result = json_decode(file_get_contents($scheduledUrl), true);
	       $scheduled = $result['rows'];
	       $numScheduled = $result['total_rows'];
	    }
	  ?>
	  
  <body class="bg" onload="init()">

    <div class="menuArea">
      <div id="menuItems" class="w3-show">
	<a class="_URL" href="./index.php">
	  <div class="menuLbl Btn" title="Home">
            <img src="img/home.png" width="64" height="64">
	    <span style="color:#7a9538"><p></p></span>
	  </div>
	</a>
	
	<div class="menuLbl Btn">
	    <img id="menu1" src="img/livetv2-gray.png" width="64" height="64">
	    <span style="color:#7a9538"><p><b><?php echo $numChannels; ?> Channels</b></p></span>
	</div>
	<div class="menuLbl Btn">
	    <img id="menu2" src="img/video-gray.png" width="64" height="64">
	    <span style="color:#7a9538"><p><b><?php echo $numRecordings; ?> Recordings</b></p></span>
	</div>
	<div class="menuLbl Btn">
	    <img id="menu3" src="img/schd.png" width="64" height="64">
	    <p><b><?php echo $numScheduled; ?> Scheduled</b></p>
	</div>
      </div>
    </div>


    <div class="w3-container w3-display-middle">
      
      <div id="scheduled" class="w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-show">

	<?php
	   $cnt = 0;
	   foreach ($scheduled as $item) {
	      $schedule = $item['value'];
	      if ($cnt == 0) {
	         //echo '<ul class="checklist">';
		 echo '<table style="width:100%">';
	      }
	      //echo "<li><b>".$schedule['description']."</b> and this isn't bold</li>";
	      echo '<tr>';
	      echo '  <th rowspan="2">'.$schedule['description'].'</th>';
	      echo '  <td>'.$schedule['record-start'].'</td>';
	      echo '</tr>';
	      echo '<tr>';
	      echo '  <td>'.$schedule['record-end'].'</td>';
	      echo '</tr>';
	      $cnt += 1;
	   }
	   if ($cnt == 0) {
	      echo "<p>Nothing scheduled.</p>";
	   } else {
	      //echo "</ul>";
	      echo '</table>';
	   }
	?>
      </div>
    </div>
      
    <div class="w3-container w3-display-middle">
      <div class="w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-show loader">
      </div>
    </div>

  </body>
  
	<?php
	   $chan = $_GET["chan"];
	   $url = "undefined";
	   foreach ($lineupJson as $d) {
	      if ($d['GuideNumber'] == $chan) {
	         $url = $d['URL'];
	      }
	   }
	   $otime = $_GET["overlap"]*60;
	   $stime = $_GET["startTime"] - $otime;
	   $etime = $_GET["duration"]*60 + $stime + $otime + $otime;

	   $couchUrl = $WriteDb;
	   $data = array(
	      'type' => "scheduled",
	      'device' => $_GET["device"],
              'channel' => $chan,
              'description' => $_GET["descr"],
              'record-start' => $stime,
              'record-end' => $etime,
              'url' => $url
           );
	   $dataStr = json_encode($data);

	   $ch = curl_init($couchUrl);
	   curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	   curl_setopt($ch, CURLOPT_POSTFIELDS, $dataStr);
	   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	   curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	      'Content-Type: application/json;charset=UTF-8',
	      'Content-Length: '.strlen($dataStr))
	   );
	   $resultStr = curl_exec($ch);
           var_dump($resultStr);
	   $result = json_decode($resultStr, true);

	   if ($result['ok']) {
	      echo "Success";
	   } else {
	      echo "Error!";
	   }
	?>
	
</html>
