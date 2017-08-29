<!DOCTYPE html>
<html>
  <head>
    
  <link rel="shortcut icon" type="image/x-icon" href="./img/dvr-favicon.ico" />
    
  <link href="./w3.css" media="all" rel="stylesheet">
  <link href="./style.css" media="all" rel="stylesheet">

<style>
table, th, td {
    border: 1px solid black;
    border-collapse: collapse;
}
th, td {
    padding: 5px;
    text-align: left;
}

#homeBtn {
    display: block; /* Hidden by default */
    position: fixed; /* Fixed/sticky position */
    top: 20px; /* Place the button at the bottom of the page */
    left: 30px; /* Place the button 30px from the right */
    z-index: 99; /* Make sure it does not overlap */
    border: none; /* Remove borders */
    outline: none; /* Remove outline */
    background-color: #93afe7; /* Set a background color */
    color: white; /* Text color */
    cursor: pointer; /* Add a mouse pointer on hover */
    padding: 5px; /* Some padding */
    border-radius: 10px; /* Rounded corners */
}

#homeBtn:hover {
    background-color: #1a56d2; /* Add a dark-grey background on hover */
}
</style>
		
</head>
<body class="bg">

    <div>
       <form action="./index.php">
	  <input id="homeBtn" type="image" src="img/home.png" alt="Submit" width="64" height="64" title="Home">
       </form>
    </div>
    
    <div class="row">
      
      <div id="discover_results" class="row">
        <div class="w3-panel w3-card w3-white w3-round-large w3-display-bottommiddle w3-padding-16">

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
	        echo "<p>No Recordings available.</p>";
	     } else {
	        //echo "</ul>";
		echo '</table>';
	     }
	  ?>

	</div>
      </div>

    </div>
    
</body>
</html>
