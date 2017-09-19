<!DOCTYPE html>
<html>
  
  <head>
    
    <?php
       include ('dvr_utils.php');
       
       echo renderLookAndFeel();
       ?>

  <style>
  </style>

  <script>
    function reload() {
       window.location.replace('./recordings.php', "", "", true);
    }
    
    function init() {
       var f = document.getElementById("recordingsFrame");
       f.callback = function onChannel(url) {
          window.location.replace(url, "", "", true);
       };
    }

  </script>
  
  </head>
  
  <body class="bg" onload="init()">

    <?php
       $enabled = array(
          'live' => false,
          'recordings' => true,
          'scheduled' => false
       );
       echo renderMenu($enabled);

       $id = $_GET['id'];
       
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
       
       ?>

    <div id="detail"
	 class="w3-container w3-display-topmiddle w3-panel w3-card w3-white w3-padding-16 w3-round-large">
      <form id="newRecording" action="./commit_del.php" method="GET">

	<fieldset>
	  <legend>Really Delete?</legend>
	  <?php echo renderEntryInfo($_GET['id']); ?>
	</fieldset>
	  
	  <input id="id" type="hidden" name="id" value=<?php echo '"'.$id.'"';?> >
	  <br>
	  <img id="cancelSchedule" onclick="reload()" src="img/cancel.png"
	       width="64" height="64" title="Cancel" class="popupBtn">
	  <input id="commitDelete" type="image" src="img/ok.png" alt="Submit" title="Submit"
		 align="right" width="64" height="64" class="popupBtn">
      </form>
    </div>

</body>
</html>
