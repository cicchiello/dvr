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
  
  <style>
     table, th, td {
        border: 1px solid black;
        border-collapse: collapse;
     }
     
     th, td {
        padding: 5px;
        text-align: left;
     }
     
     .icon, .menuArea {
        display: block; /* block element by default */
        z-index: 99; /* Make sure it does not overlap */
        border: none; /* Remove borders */
        outline: none; /* Remove outline */
        background-color: #44661b; /* Set a background color */
        padding: 5px; /* Some padding */
        border-radius: 10px; /* Rounded corners */
     }
     
     .menuArea {
        position: fixed; /* Fixed position */
        top: 20px; /* Place the button at the top of the page */
        left: 30px; /* Place the button 30px from the left */
        color: white; /* Text color */
     }

     .Btn:hover {
        background-color: #465702; /* Add a dark-grey background on hover */
        outline: none; /* Remove outline */
        cursor: pointer; /* Add a mouse pointer on hover */
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
     
     input[type=text], select {
        padding: 5px 5px;
        margin: 4px 0;
        display: inline-block;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        cursor: pointer; /* Add a mouse pointer on hover */
     }

     input[type=time] {
        padding: 0px 5px;
        margin: 4px 0;
        display: inline-block;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        cursor: pointer; /* Add a mouse pointer on hover */
     }

     iframe {
	overflow: scroll !important;
	height: 100%;
	border: 4px solid blue;
     }
     
  </style>

  <script>
    function toggleSchedule() {
       var newSchdArea = document.getElementById("newSchedule");
       if (newSchdArea.className.indexOf("w3-show") == -1) {
          var schdArea = document.getElementById("scheduled");
          var btn = document.getElementById("plus");
          var cal = document.getElementById("calendar");
          var chSelector = document.getElementById("channelSelector");
          schdArea.className = schdArea.className.replace(" w3-show", " w3-hide");
          newSchdArea.className = newSchdArea.className.replace(" w3-hide", " w3-show");
          cal.className = cal.className.replace(" w3-hide", " w3-show");
          btn.className = btn.className.replace(" w3-show", " w3-hide");
          chSelector.className = chSelector.className.replace("w3-hide", "w3-show");
       } else {
          location.reload();
       }
    }

    function init() {
       var f = document.getElementById("channelSelectorFrame");
       f.callback = function onChannel(channel) {
          document.getElementById("theChannel").innerHTML=channel;
       };
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
	
      <div class="w3-panel w3-padding-16">
	<br>
        <img id="plus" onclick="toggleSchedule()" src="img/plus.png"
	     class="icon w3-display-bottommiddle Btn w3-show"
	     width="64" height="64" title="Schedule A Recording">
      </div>
    </div>
      
    <div id="newSchedule"
	 class="w3-container w3-display-topmiddle w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-hide">
      <form id="newRecording">
	<fieldset>
	  <legend>Schedule Recording:</legend>
	  <table>
	    <tr>
	      <td>Channel:</td>
	      <td><b id="theChannel" style="color:blue" class="w3-right">TBD</b></td>
	    </tr>
	    <tr>
	      <td>Description:</td>
	      <td>
		<?php
		   $s = 'Recording on '.date('d-M-y H:i');
		   echo '<input type="text" dir="rtl" style="color:blue" size="30" placeholder="'.$s.'">';
		   ?>
	      </td>
	    </tr>
	    <tr>
	      <td>Date:</td>
	      <td><b id="theDate" style="color:blue" class="w3-right">TBD</b></td>
	    </tr>
	    <tr>
	      <td>Start Time:</td>
	      <td><input type="time" style="color:blue" class="w3-right"
			 name="startTime"></td>
	    </tr>
	    <tr>
	      <td>Duration:</td>
	      <td>
		<select id="duration" dir="rtl" style="color:blue" class="w3-right" form="newRecording">
		  <option value="30">30 minutes</option>
		  <option value="60">1 hour</option>
		  <option value="90">90 minutes</option>
		  <option value="120">2 hours</option>
		  <option value="180">3 hours</option>
		  <option value="240">4 hours</option>
		</select>
	      </td>
	    </tr>
	    <tr>
	      <td>Start Early?:</td>
	      <td>
		<select id="overlap" dir="rtl" style="color:blue" class="w3-right" form="newRecording">
		  <option value="0">none</option>
		  <option value="5">5 minutes</option>
		  <option value="10">10 minutes</option>
		  <option value="15">15 minutes</option>
		</select>
	      </td>
	    </tr>
	  </table>
	  <br>
	  <img id="cancelSchedule" onclick="toggleSchedule()" src="img/cancel.png"
	       width="64" height="64" title="Cancel" class="Btn">
	  <img id="commitSchedule" onclick="commitSchedule()" src="img/ok.png"
	       align="right" width="64" height="64" title="Submit" class="Btn">
	</fieldset>
      </form>
    </div>

    <div id="calendar" class="w3-display-bottomleft w3-hide">      
      <div class="demo-section k-header" style="width:300px; text-align:center;">
	<div id="cal"></div>
      </div>
      
      <script>
	$(document).ready(function() {
	   // create Calendar from div HTML element
	   $("#cal").kendoCalendar({
	      value: new Date(),
	      change: function() {
	         var v = this.value();
	         document.getElementById("theDate").innerHTML = v.toDateString();
	      },
	      footer: false
	   });
	   document.getElementById("theDate").innerHTML = new Date().toDateString();
	});
      </script>
    </div>

    <div id="channelSelector" style="height:100%; padding:20px; z-index:999" class="w3-hide">
      <iframe id="channelSelectorFrame" src="./channels.php"
	      height="90%" frameborder="1" style="float:right; z-index:999">
	<p>Your browser does not support iframes.</p>
      </iframe>
    </div>
    
  </body>
</html>
