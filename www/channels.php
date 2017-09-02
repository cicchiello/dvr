<!DOCTYPE html>
<html>
  <head>
  
    <style>
      .bg {
	  background-color: #00ff00;
	  height: 100%;
      }
    </style>
    
    <script>
    </script>

  </head>

  <?php
     $url = "http://ipv4-api.hdhomerun.com/discover";
     $devices = json_decode(file_get_contents($url), true);

     $numChannels = 0;
     $lineupJson = 0;
     foreach ($devices as $device) {
        $device_detail = json_decode(file_get_contents($device['DiscoverURL']), true);
        $lineupJsonUrl = $device_detail['LineupURL'];
	
        $lineupJson = json_decode(file_get_contents($lineupJsonUrl), true);
        $numChannels += sizeof(json_decode(file_get_contents($lineupJson), true));
     }
  ?>

  <body class="bg">
     <table style="width:90%">
        <?php
	   foreach ($lineupJson as $channel) {
	      echo '<tr>';
	      echo '   <td>'.$channel['GuideNumber'].'</td>';
	      echo '</tr>';
	   }
        ?>
     </table>
  </body>
  
</html>