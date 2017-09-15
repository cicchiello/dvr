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
  <link href="./search.css" media="all" rel="stylesheet" />
  
  <style>
     .popupBtn:hover {
        outline: none; /* Remove outline */
        cursor: pointer; /* Add a mouse pointer on hover */
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
    "use strict";

    var startYear, startMonth, startDay, startHour, startMinute;
    var timestamp_s = null;
    
    var hasDescription = false;
    var hasStarttime = false;
    var hasAllInputs = false;
    
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
    
    function checkForAllInputs() {
       hasAllInputs = hasDescription && hasStarttime;
       if (hasAllInputs) {
          //alert("Year: "+startYear+"\nMonth: "+startMonth+"\nDay: "+startDay+
          //      "\nHour: "+startHour+"\nMinute: "+startMinute);
          var d = new Date(startYear, startMonth, startDay, startHour, startMinute, 0, 0);
          timestamp_s = d.getTime()/1000;
          document.getElementById("formStarttime").value = timestamp_s;
          //alert(timestamp_s);
       }
       var src = hasAllInputs ? "img/ok.png" : "img/ok-gray.png";
       document.getElementById("commitSchedule").src = src;
    }

    function setDescription() {
       hasDescription = document.getElementById("description").value ? true : false;
       checkForAllInputs();
    }

    function clearDescription() {
       document.getElementById("description").value = null;
       setDescription();
    }
    
    function setDate(v) {
       startYear = v.getFullYear();
       startMonth = v.getMonth();
       startDay = v.getDate();
       checkForAllInputs();
    }
    
    function setStarttime(v) {
       hasStarttime = v ? true : false;
       if (hasStarttime) {
          var tokens = v.split(":");
          startHour = tokens[0];
          startMinute = tokens[1];
       }
       checkForAllInputs();
    }
    
    function init() {
       var f = document.getElementById("channelSelectorFrame");
       f.callback = function onChannel(channelNum,channelName) {
          document.getElementById("theChannel").innerHTML=channelNum;
          document.getElementById("formChannelNum").value=channelNum;
          document.getElementById("theCallSign").innerHTML=channelName;
       };
    }
    
  </script>

  </head>
  
	  <?php
	     $ini = parse_ini_file("./config.ini");
	     $DbBase = $ini['couchbase'];
	     $Db = "dvr";
	     $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';
	     
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
	     
	        $lineup = json_decode(file_get_contents($lineupJsonUrl), true);
	        $numChannels += sizeof($lineup);
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
	
      <div class="w3-panel w3-padding-16">
	<br>
        <img id="plus" onclick="toggleSchedule()" src="img/plus.png"
	     class="menuIcon w3-display-bottommiddle Btn w3-show"
	     width="64" height="64" title="Schedule A Recording">
      </div>
    </div>
      
    <div id="newSchedule"
	 class="w3-container w3-display-topmiddle w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-hide">
      <form id="newRecording" action="./commit.php" method="GET">
	<fieldset>
	  <legend>Schedule Recording:</legend>
	  <input type="hidden" name="device" value=<?php echo '"'.$deviceId.'"';?> >
	  <input id="formChannelNum" type="hidden" name="chan" value="2">
	  <input id="formStarttime" type="hidden" name="startTime">
	  <input id="formOverlap" type="hidden" name="overlap" value="5">
	  <table>
	    <tr>
	      <td>Channel:</td>
	      <td><b id="theChannel" style="color:blue" class="w3-right">2</b></td>
	      <td><b id="theCallSign" style="color:blue" class="w3-right">CBS</b></td>
	    </tr>
	    <tr>
	      <td>Description:</td>
	      <td colspan="2">
		<div class="search-wrapper">
		  <?php
		     $place = 'Enter descriptive name...';
		     echo '<input id="description" required class="search-box" name="descr" type="text"
				  onchange="setDescription()"
				  style="color:blue" size="30" placeholder="'.$place.'">';
		     echo '<button class="close-icon" onclick="clearDescription()"';
		   ?>
		</div>
	      </td>
	    </tr>
	    <tr>
	      <td>Date:</td>
	      <td colspan="2"><b id="theDate" style="color:blue" class="w3-right">TBD</b></td>
	    </tr>
	    <tr>
	      <td>Start Time:</td>
	      <td colspan="2">
		<input type="time" style="color:blue" onchange="setStarttime(this.value)"
		       class="w3-right">
	      </td>
	    </tr>
	    <tr>
	      <td>Duration:</td>
	      <td colspan="2">
		<select name="duration" style="color:blue" class="w3-right" form="newRecording">
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
	      <td colspan="2">
		<select id="overlap" style="color:blue"
			onchange="document.getElementById('formOverlap').value=this.value"
			class="w3-right" form="newRecording">
		  <option value="5">5 minutes</option>
		  <option value="10">10 minutes</option>
		  <option value="15">15 minutes</option>
		  <option value="0">none</option>
		</select>
	      </td>
	    </tr>
	  </table>
	  <br>
	  <img id="cancelSchedule" onclick="toggleSchedule()" src="img/cancel.png"
	       width="64" height="64" title="Cancel" class="popupBtn">
	  <input id="commitSchedule" type="image" src="img/ok-gray.png" alt="Submit" title="Submit"
		 align="right" width="64" height="64" class="popupBtn">
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
	         setDate(v);
	      },
	      footer: false
	   });
	   var now = new Date();
	   document.getElementById("theDate").innerHTML = now.toDateString();
	   setDate(now);
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
