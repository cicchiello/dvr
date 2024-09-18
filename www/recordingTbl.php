<!DOCTYPE html>
<html>
  <head>
  
    <link href="./thumbs.css" media="all" rel="stylesheet" />
    
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
      }
     
      .Btn:hover {
         background-color: #465702; /* Add a dark-grey background on hover */
         outline: none; /* Remove outline */
         cursor: pointer;
      }
      
    </style>
    
    <script>
    
        function infoAction(id) {
	   //console.log("id: "+id);
           var f = parent.document.getElementById("recordingsFrame");
           if (f) 
              f.callback('./recording_info.php?id='+id);
           else
              document.getElementById("result").innerHTML = "no recordingsFrame to pass "+id+" to";
	}
	
        function deleteAction(id) {
	   console.log("id: "+id);
           var f = parent.document.getElementById("recordingsFrame");
           if (f) 
              f.callback('./recording_del.php?id='+id);
           else
              document.getElementById("result").innerHTML = "no recordingsFrame to pass "+id+" to";
	}
	
        function downloadAction(id) {
	   console.log("id: "+id);
           var f = parent.document.getElementById("recordingsFrame");
           if (f) 
              f.callback('./recording_download.php?id='+id);
           else
              document.getElementById("result").innerHTML = "no recordingsFrame to pass "+id+" to";
	}
	
    </script>

  </head>

  <body class="bg">
     <p id="result"></p>
        <table style="width:100%; overflow:scroll">
           <?php
	      include('dvr_utils.php');
	      
	      $ini = parse_ini_file("./config.ini");
	      $DbBase = $ini['couchbase'];
	      $Db = "dvr";
	      $DbViewBase = $DbBase.'/'.$Db.'/_design/dvr/_view';
	      
	      $url = $DbViewBase.'/recordings?descending=true';

              $infoAction = array(
	         "onclick" => "infoAction",
		 "src" => "img/info.png",
		 "title" => "Info"
	      );
	      $downloadAction = array(
	         "onclick" => "downloadAction",
		 "src" => "img/download.png",
		 "title" => "Download"
	      );
	      $deleteAction = array(
	         "onclick" => "deleteAction",
		 "src" => "img/trashcan.png",
		 "title" => "Delete"
	      );
	      echo renderRecordingsTable(json_decode(file_get_contents($url), true)['rows'],
              	   	                 array($infoAction, $downloadAction, $deleteAction));
           ?>
        </table>
  </body>
  
</html>
