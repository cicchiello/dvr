<!DOCTYPE html>
<html>
  
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
  
  <link href="./w3.css" media="all" rel="stylesheet">
  <link href="./style.css" media="all" rel="stylesheet">
  
  <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.common-material.min.css" />
  <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.material.min.css" />
  <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.dataviz.min.css" />
  <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.dataviz.material.min.css" />

  <script src="http://cdn.kendostatic.com/2015.1.429/js/jquery.min.js"></script>
  <script src="http://cdn.kendostatic.com/2015.1.429/js/kendo.all.min.js"></script>
  
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
     
     #plus:hover {
        background-color: #465702; /* Add a dark-grey background on hover */
        outline: none; /* Remove outline */
     }
     
     .ScheduleBtn:hover {
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

  </style>

  <script>
    function toggleSchedule() {
       var newSchdArea = document.getElementById("newSchedule");
       if (newSchdArea.className.indexOf("w3-show") == -1) {
          var schdArea = document.getElementById("scheduled");
          var btn = document.getElementById("plus");
          var cal = document.getElementById("calendar");
          schdArea.className = schdArea.className.replace(" w3-show", " w3-hide");
          newSchdArea.className = newSchdArea.className.replace(" w3-hide", " w3-show");
          cal.className = cal.className.replace(" w3-hide", " w3-show");
          btn.className = btn.className.replace(" w3-show", " w3-hide");
       } else {
          location.reload();
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
	
      <div id="newSchedule" class="w3-panel w3-card w3-white w3-padding-16 w3-round-large w3-hide">
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
		 width="64" height="64" title="Cancel" class="ScheduleBtn">
	    <img id="commitSchedule" onclick="commitSchedule()" src="img/ok.png"
		 align="right" width="64" height="64" title="Submit" class="ScheduleBtn">
	  </fieldset>
	</form>
      </div>

      <div class="w3-panel w3-padding-16">
	<br>
        <img id="plus" onclick="toggleSchedule()" src="img/plus.png"
	     class="w3-display-bottommiddle w3-show"
	     width="64" height="64" title="Schedule A Recording">
      </div>
      
    </div>
    
    <div id="calendar" class="w3-display-bottomright w3-hide">
      
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
    
    
  </body>
</html>
