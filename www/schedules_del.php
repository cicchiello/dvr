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
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
  
  <link href="./w3.css" media="all" rel="stylesheet">
  
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
     
  </style>

  <script>
    "use strict";

    function reload() {
       window.location.replace('./schedules.php');
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
       window.location.replace('./commitSchdDel.php?id='+id);
    }
    
  </script>

  </head>
  
  <?php
     include('dvr_utils.php');
	     
     $ini = parse_ini_file("./config.ini");
     $DbBase = $ini['couchbase'];
     $Db = "dvr";
     $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';
    ?>
  
  <body class="bg" 

    <?php
      if (isset($_COOKIE['login_user'])) {
        echo 'onload="init()">';
      } else {
        echo 'onload="forceLogin()">';
      }
      ?>
       

    <?php
      $enabled = array(
	'live' => false,
	'library' => false,
	'recording' => false,
	'scheduled' => true
      );
	      
      echo renderMenu($enabled, $_COOKIE['login_user']);
    ?>
 
    <div id="deleteConfirm" class="w3-container w3-display-middle">

      <div class="w3-panel w3-card w3-white w3-padding-16 w3-round-large">
          <p>Are you sure? </p>
	  <br>
          <?php echo renderSchdInfo($_GET['id']); ?>
	  <br>
          <img id="cancelDelete" onclick="reload()" src="img/cancel.png"
               width="64" height="64" title="Cancel" class="popupBtn">
	  <?php
              $q = "'";
              $id = $_GET['id'];
              $r = '<img id="commitDelete" onclick="deleteAction('.$q.$id.$q.')" ';
              $r .= 'src="img/ok.png" align="right" width="64" height="64" title="Delete" class="popupBtn">';
              echo $r;
	    ?>
      </div>
      
    </div>
    
    <div id="channelSelector" style="height:100%; padding:20px; z-index:999" class="w3-hide">
      <iframe id="channelSelectorFrame" src="./channels.php"
	      height="90%" frameborder="1" style="float:right; z-index:999">
	<p>Your browser does not support iframes.</p>
      </iframe>
    </div>
    
  </body>
</html>
