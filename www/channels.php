<!DOCTYPE html>

<?php
    // intentionally place this before the html tag

    // Uncomment to see php errors
    //ini_set('display_errors', 1);
    //ini_set('display_startup_errors', 1);
    //error_reporting(E_ALL);

  ?>

<html>
  <head>
  
    <style>
      .bg {
	  height: 100%;
      }
      
      table, th, td {
      	  border: 1px solid black;
          border-collapse: collapse;
      }
     
      th, td {
          padding: 5px;
          text-align: left;
      }
     
    </style>
    
    <script>
       var prev = null;
       function channelClick(channelNum,channelName) {
          if (prev) {
	     document.getElementById(prev).style.backgroundColor = "white";
	     document.getElementById(prev).style.color = "black";
	  }
          var n = "row"+channelNum;
          if (parent) {
             var f = parent.document.getElementById("channelSelectorFrame");
             if (f) {
                f.callback(channelNum,channelName);
             } else {
                document.getElementById("result").innerHTML = "no channelSelectorFrame";
             }
          }
          document.getElementById(n).style.backgroundColor = "blue";
	  document.getElementById(n).style.color = "white";
	  prev = n;
       }
    </script>

  </head>

  <?php
     $url = "http://ipv4-api.hdhomerun.com/discover";
     $devices = json_decode(file_get_contents($url), true);

     $lineupJson = 0;
     foreach ($devices as $device) {
        $device_detail = json_decode(file_get_contents($device['DiscoverURL']), true);
        $lineupJsonUrl = $device_detail['LineupURL'];
	
        $lineupJson = json_decode(file_get_contents($lineupJsonUrl), true);
     }
  ?>

  <body class="bg">
    <p id="result"></p>
     <form action="channelClick()">
        <table style="width:100%; overflow:scroll">
           <?php
	      foreach ($lineupJson as $channel) {
	         $num = $channel['GuideNumber'];
		 $name = $channel['GuideName'];
		 $func = "if(this.checked){channelClick('".$num."','".$name."');}";
		 echo '<a onclick="'.$func.'">';
		 echo '<tr id="row'.$num.'" style="background-color:white; color=black" onclick="'.$func.'">';
		 echo '   <td><input type="radio" onclick="'.$func.'" name="channel" value="'.$num.'"></td>';
	         echo '   <td><b>'.$num.'</b></td>';
	         echo '   <td><b>'.$name.'</b></td>';
	         echo '</tr></a>';
	      }
           ?>
        </table>
     </form>
  </body>
  
</html>
