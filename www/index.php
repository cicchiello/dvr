<!DOCTYPE html>
<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
    
  <link href="./w3.css" media="all" rel="stylesheet">
  <link href="./style.css" media="all" rel="stylesheet">

  <style>
     #menuArea {
        display: block; /* block element by default */
        position: fixed; /* Fixed position */
        top: 20px; /* Place the button at the top of the page */
        left: 30px; /* Place the button 30px from the left */
        z-index: 99; /* Make sure it does not overlap */
        border: none; /* Remove borders */
        outline: none; /* Remove outline */
        background-color: #44661b; /* Set a background color */
        color: white; /* Text color */
        cursor: pointer; /* Add a mouse pointer on hover */
        padding: 5px; /* Some padding */
        border-radius: 10px; /* Rounded corners */
     }

     .Btn:hover {
        background-color: #465702; /* Add a dark-grey background on hover */
        outline: none; /* Remove outline */
     }
     
     .menuLbl {
        height: 64px;
        width: 200px;
        position: relative;
        border: none;
        outline: none; /* Remove outline */
     }
     
     .menuLbl p {
        margin: 0;
        position: absolute;
        top: 50%;
        left: 128px;
        white-space: nowrap;
        -ms-transform: translate(-50%, -50%);
        transform: translate(-50%, -50%);
     }
     
  </style>

  <script>
    function menuAction() {
      var x = document.getElementById("menuItems");
      if (x.className.indexOf("w3-show") == -1) {
         x.className += " w3-show";
      } else {
         x.className = x.className.replace(" w3-show", "");
      }
    }
  </script>
  
  </head>
  
	  <?php
	     $url = "http://ipv4-api.hdhomerun.com/discover";
	     $devices = json_decode(file_get_contents($url), true);

	     $numRecordings = 0;
	     $numChannels = 0;
	     $numScheduled = 0;
	     foreach ($devices as $device) {
	        $deviceUrl = $device['DiscoverURL'];
	        $device_detail = json_decode(file_get_contents($deviceUrl), true);
	        $lineupJsonUrl = $device_detail['LineupURL'];
	        $recordingsUrl = "https://jfcenterprises.cloudant.com/dvr/_design/dvr/_view/recordings";
	     
	        $numChannels += sizeof(json_decode(file_get_contents($lineupJsonUrl), true));
	        $numRecordings += json_decode(file_get_contents($recordingsUrl), true)['total_rows'];

	        $scheduledUrl = "https://jfcenterprises.cloudant.com/dvr/_design/dvr/_view/scheduled";
	        $result = json_decode(file_get_contents($scheduledUrl), true);
	        $scheduled = $result['rows'];
	        $numScheduled = $result['total_rows'];
	     }
	  ?>
  <body class="bg">

    <div id="menuArea">
      <input onclick="menuAction()" type="image" src="img/showmenu.png"
	     width="64" height="64" title="Menu" class="Btn">
      
      <div id="menuItems" class="w3-hide">
	<a class="_URL" href="./live.php">
	  <div class="menuLbl Btn" title="Live TV">
	    <img id="menu1" src="img/livetv2.png" width="64" height="64" class="Btn">
	    <p><b><?php echo $numChannels; ?> Channels</b></p>
	  </div>
	</a>
	<a class="_URL" href="./recordings.php">
	  <div class="menuLbl Btn" title="Recordings">
	    <img id="menu2" src="img/video.png" width="64" height="64" class="Btn">
	    <p><b><?php echo $numRecordings; ?> Recordings</b></p>
	  </div>
	</a>
	<a class="_URL" href="./schedules.php">
	  <div class="menuLbl Btn" title="Schedule">
	    <img id="menu3" src="img/schd.png" width="64" height="64" class="Btn">
	    <p><b><?php echo $numScheduled; ?> Scheduled</b></p>
	  </div>
	</a>
      </div>
    </div>

    <div class="row">
      
      <div class="box col-sm-4">
	<div class="w3-panel w3-card w3-white w3-round-large w3-display-bottomright">
	  <?php
	     $url = "http://ipv4-api.hdhomerun.com/discover";
	     $devices = json_decode(file_get_contents($url), true);

	     $cnt = 0;
	     foreach ($devices as $device) {
	        $cnt += 1;
	        $deviceUrl = $device['DiscoverURL'];
	        $device_detail = json_decode(file_get_contents($deviceUrl), true);
	        $cableCardUrl = $device['BaseURL'].'/cc.html';
	     
	        echo '<div>'.$device_detail['FriendlyName'].' '.$device_detail['DeviceID'].'</div>';
	        echo '	<ul class="checklist">';
	        echo '    <li class="_FwStatus warn">FW Version '.$device_detail['FirmwareVersion'].'</li>';
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
