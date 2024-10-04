<!DOCTYPE html>
<html>
  <head>
    <?php
       include('dvr_utils.php');

       echo renderLookAndFeel();

       $id = $_POST['id'];
       $fname = $_POST['fname'];
       $lname = $_POST['lname'];
      ?>

    <link href="./login.css" media="all" rel="stylesheet">
    
    <script src="./dvr_utils.js"></script>
  
  </head>
  
  <script>

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
      var modal = document.getElementById('id01');
      if (event.target == modal) {
        modal.style.display = "none";
        open('./profile.php',"_self");
      }
    }

    function onCancel() {
      open('./profile.php',"_self");
    }

    async function onCommit(id) {
      post("./commit_name.php", {
          "id": id,
          "fname":document.getElementById('fname').value,
          "lname":document.getElementById('lname').value
      });
    }
    
  </script>
    
  <body class="bg" 

    <?php       
       if (isset($_COOKIE['login_user'])) {
         echo 'onload="document.getElementById('."'id01'".').style.display='."'block'".'">';
       } else {
         echo 'onload="onCancel()">';
       }
     ?>

    <!-- The Modal -->
    <div id="id01" class="modal">
      <span onclick="onCancel()" class="close" title="Close Modal">&times;</span>

      <!-- Modal Content -->
        <div class="modal-content animate w3-container w3-round-large w3-padding">
	  <?php
	    echo '<label><b>First name</b></label>';
	    echo '<input id="fname" type="text" value="'.$fname.'" name="fname">';
	    echo '<label><b>Last name</b></label>';
	    echo '<input id="lname" type="text" value="'.$lname.'" name="lname">';
	    echo '<br>';
	    echo '<img onclick="onCancel()" src="img/cancel.png" width="48" height="48"';
	    echo '     title="Cancel" class="popupBtn" align="left">';
	    echo '<img onclick="onCommit('."'".$id."'".')" src="img/ok.png" width="48" height="48"';
	    echo '     title="Done" class="popupBtn" align="right">';
	   ?>
	</div>

    </div>
  </body>
</html>
