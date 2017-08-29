<!DOCTYPE html>
<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
    
  <link href="./w3.css" media="all" rel="stylesheet">
  <link href="./style.css" media="all" rel="stylesheet">

  </head>
  
  <body class="bg">
  
    <div class="row">
      
      <div class="box col-sm-4">
	<div class="w3-panel w3-card w3-white w3-round-large w3-display-bottommiddle">
	  <?php
	     $url = "http://ipv4-api.hdhomerun.com/discover";
	     $devices = json_decode(file_get_contents($url), true);

	     $cnt = 0;
	     foreach ($devices as $device) {
	        $cnt += 1;
	        $deviceUrl = $device['DiscoverURL'];
	        $device_detail = json_decode(file_get_contents($deviceUrl), true);
	        $lineupJsonUrl = $device_detail['LineupURL'];
	        $lineupHtmlUrl = $device['BaseURL'].'/lineup.html';
	        $cableCardUrl = $device['BaseURL'].'/cc.html';
	        $recordingsUrl = "https://jfcenterprises.cloudant.com/dvr/_design/dvr/_view/recordings";
	     
	        $localIP = $device['LocalIP'];
	        $numChannels = sizeof(json_decode(file_get_contents($lineupJsonUrl), true));
	        $numRecordings = json_decode(file_get_contents($recordingsUrl), true)['total_rows'];
	        $schedulesUrl = "";
	        $numScheduled = 0;
	     
	        echo '<div>'.$device_detail['FriendlyName'].' '.$device_detail['DeviceID'].'</div>';
	        echo '	<ul class="checklist">';
	        echo '    <li class="_FwStatus warn">Version '.$device_detail['FirmwareVersion'].'</li>';
	        echo '    <li><a class="_URL" href="'.$lineupHtmlUrl.'">'.$numChannels.' Live Channels</a></li>';
	        echo '	  <li><a class="_URL" href="./recordings.php">'.$numRecordings.' Recordings</a></li>';
	        echo '	  <li><a class="_URL" href="./schedules.php">'.$numScheduled.' Scheduled</a></li>';
	        echo '    <li><a class="_URL" href="'.$cableCardUrl.'">CableCARD&trade; Menu</a></li>';
	        echo '  </ul>';
	     }

	     if ($cnt == 0) {
	        echo '    <p>No HDHomeRun detected.</p>';
	        echo '    <p>Please connect the HDHomeRun to your router and refresh the page.</p>';
	        echo '    <p>HDHomeRun PRIME: Please remove the CableCard to allow detection to complete.</p>';
	     }
	     
	  ?>
	</div>
      </div>
      
    </div>
    </div>
    
  </body>
</html>
