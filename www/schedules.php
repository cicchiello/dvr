<!DOCTYPE html>
<html>
  
  <head>
    
    <?php
       include('dvr_utils.php');

       echo renderLookAndFeel();
       ?>
    
    <link href="./table.css" media="all" rel="stylesheet" />
    <link href="./search.css" media="all" rel="stylesheet" />
    <link href="./thumbs.css" media="all" rel="stylesheet" />
    
    <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.common-material.min.css" />
    <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.material.min.css" />
    <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.dataviz.min.css" />
    <link rel="stylesheet" href="http://cdn.kendostatic.com/2015.1.429/styles/kendo.dataviz.material.min.css" />
    
  
    <script src="http://cdn.kendostatic.com/2015.1.429/js/jquery.min.js"></script>
    <script src="http://cdn.kendostatic.com/2015.1.429/js/kendo.all.min.js"></script>
    
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>

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

     .menuIcon {
        display: block; /* block element by default */
        z-index: 99; /* Make sure it does not overlap */
        border: none; /* Remove borders */
        outline: none; /* Remove outline */
        background-color: #44661b; /* Set a background color */
        padding: 5px; /* Some padding */
        border-radius: 10px; /* Rounded corners */
     }
     
  </style>

  <script>
    "use strict";

    var startYear, startMonth, startDay, startHour, startMinute;
    var timestamp_s = null;
    
    var hasDescription = false;
    var hasStarttime = false;
    var hasAllInputs = false;

    function reload() {
       location.reload();
    }
    
    function enableScheduler() {
       var newSchdArea = document.getElementById("newSchedule");
       var schdArea = document.getElementById("scheduled");
       var btn = document.getElementById("plus");
       var cal = document.getElementById("calendar");
       var chSelector = document.getElementById("channelSelector");
       schdArea.className = schdArea.className.replace(" w3-show", " w3-hide");
       newSchdArea.className = newSchdArea.className.replace(" w3-hide", " w3-show");
       cal.className = cal.className.replace(" w3-hide", " w3-show");
       btn.className = btn.className.replace(" w3-show", " w3-hide");
       chSelector.className = chSelector.className.replace("w3-hide", "w3-show");
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

    function deleteAction(id) {
       //console.log("id: "+id);
       var schdArea = document.getElementById("scheduled");
       schdArea.className = schdArea.className.replace(" w3-show", " w3-hide");
       window.location.replace('./schedules_del.php', "", "", true);
    }
    
    async function forceLogin() {
      open('./login.php',"_self");
    }
    
  </script>

  </head>
  
  <body class="bg" 

    <?php
      if (isset($_COOKIE['login_user'])) {
        echo 'onload="init()">';
      } else {
        echo 'onload="forceLogin()">';
      }
       

      $ini = parse_ini_file("./config.ini");
      $DbBase = $ini['couchbase'];
      $Db = "dvr";
      $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';
      
      $enabled = array(
	'live' => false,
	'library' => false,
	'recording' => false,
	'scheduled' => true
      );
	      
      echo renderMenu($enabled, $_COOKIE['login_user']);
      ?>
 
    <div id="scheduled" class="w3-container w3-display-middle w3-show">
      
      <div class="w3-panel w3-card w3-white w3-padding-16 w3-round-large">

	<?php
	   $url = $DbViewBase.'/scheduled';

	   $editAction = array(
	      "onclick" => "editAction",
	      "src" => "img/edit2.png",
	      "title" => "Edit"
	   );
	   $deleteAction = array(
	      "onclick" => "deleteAction",
	      "src" => "img/trashcan.png",
	      "title" => "Delete"
	   );
	   echo renderRecordingsTable(json_decode(file_get_contents($url), true)['rows'],
	                              array($editAction, $deleteAction));
	?>
      </div>
	
      <div class="w3-panel w3-padding-16">
	<br>
        <img id="plus" onclick="enableScheduler()" src="img/plus.png"
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
				  style="color:blue" size="25" placeholder="'.$place.'">';
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
	  <img id="cancelSchedule" onclick="reload()" src="img/cancel.png"
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
