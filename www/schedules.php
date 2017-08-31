<!DOCTYPE html>
<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
  
  <link href="./w3.css" media="all" rel="stylesheet">
  <link href="./style.css" media="all" rel="stylesheet">
  
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
  
  <style>
     table, th, td {
        border: 1px solid black;
        border-collapse: collapse;
     }
     
     th, td {
        padding: 5px;
        text-align: left;
     }

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
        padding: 5px; /* Some padding */
        border-radius: 10px; /* Rounded corners */
     }

     #plus {
        display: block; /* block element by default */
        position: fixed; /* Fixed position */
        z-index: 99; /* Make sure it does not overlap */
        border: none; /* Remove borders */
        outline: none; /* Remove outline */
        background-color: #44661b; /* Set a background color */
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
    function toggleSchedule() {
       var sarea = document.getElementById("schedule");
       var btn = document.getElementById("plus");
       var btn_gray = document.getElementById("plus-gray");
       if (sarea.className.indexOf("w3-show") == -1) {
          sarea.className = sarea.className.replace(" w3-hide", " w3-show");
          btn.className = btn.className.replace(" w3-show", " w3-hide");
          btn_gray.className = btn_gray.className.replace(" w3-hide", " w3-show");
       } else {
          sarea.className = sarea.className.replace(" w3-show", " w3-hide");
          btn.className = btn.className.replace(" w3-hide", " w3-show");
          btn_gray.className = btn_gray.className.replace(" w3-show", " w3-hide");
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
	     }
	  ?>
  <body class="bg">

    <div id="menuArea">
      <div id="menuItems" class="w3-show">
	<a class="_URL" href="./index.php">
	  <div class="menuLbl Btn" title="Home">
            <img src="img/home.png" width="64" height="64" class="Btn">
	    <span style="color:#7a9538"><p></p></span>
	  </div>
	</a>
	
	<div class="menuLbl Btn">
	    <img id="menu1" src="img/livetv2-gray.png" width="64" height="64" class="Btn">
	    <span style="color:#7a9538"><p><b><?php echo $numChannels; ?> Channels</b></p></span>
	</div>
	<div class="menuLbl Btn">
	    <img id="menu2" src="img/video-gray.png" width="64" height="64" class="Btn">
	    <span style="color:#7a9538"><p><b><?php echo $numRecordings; ?> Recordings</b></p></span>
	</div>
	<div class="menuLbl Btn">
	    <img id="menu3" src="img/schd.png" width="64" height="64" class="Btn">
	    <p><b><?php echo $numScheduled; ?> Scheduled</b></p>
	</div>
      </div>
    </div>


    <div class="w3-container w3-display-middle">
      
      <div class="w3-panel w3-card w3-white w3-padding-16 w3-round-large">

	<?php
	   $url = "https://jfcenterprises.cloudant.com/dvr/_design/dvr/_view/recordings";
	   $json = json_decode(file_get_contents($url), true);
	   
	   $cnt = 0;
	   foreach ($json['rows'] as $recording) {
	      if ($cnt == 0) {
	         //echo '<ul class="checklist">';
		 echo '<table style="width:100%">';
	      }
	      //echo "<li><b>".$recording['value']['name']."</b> and this isn't bold</li>";
	      echo '<tr>';
	      echo '  <th rowspan="2">'.$recording['value']['name'].'</th>';
	      echo '  <td>55577854</td>';
	      echo '</tr>';
	      echo '<tr>';
	      echo '  <td>55577855</td>';
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
	
      <div id="schedule" class="w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-hide">
	<form>
	  <fieldset>
	    <legend>New Recording Schedule:</legend>
	    Description: <input type="text"><br>
	    Date: <input type="text"><br>
	    Start Time: <input type="text">
	    Duration (minutes): <input type="text">
	  </fieldset>
	  </form>
      </div>

      <div class="w3-container"><p></p></div>
      
      <div class="w3-panel w3-padding-16">
        <img id="plus" onclick="toggleSchedule()" src="img/plus.png"
	     class="w3-display-bottommiddle w3-show"
	     width="64" height="64" title="Schedule A Recording" class="Btn">
        <img id="plus-gray" src="img/plus-gray.png"
	     class="w3-display-bottommiddle w3-hide"
	     width="64" height="64" title="Schedule A Recording" class="Btn">
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
