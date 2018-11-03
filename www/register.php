<!DOCTYPE html>
<html>
  <head>
    <?php
       include('dvr_utils.php');

       echo renderLookAndFeel();
       ?>

    <link href="./login.css" media="all" rel="stylesheet">
    
  </head>
  
  <script>
    // Get the modal
    var modal = document.getElementById('id01');

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
      }
    }
  </script>
    
  <body class="bg" onload="document.getElementById('id01').style.display='block'">
    <!-- The Modal -->
    <div id="id01" class="modal">
      <span onclick="document.getElementById('id01').style.display='none'"
            class="close" title="Close Modal">&times;</span>

      <!-- Modal Content -->
      <form class="modal-content animate" action="./login_action.php" method="post">
        <div class="container">
	  <label><b>First name</b></label>
	  <input type="text" placeholder="Enter First name" name="fname" required>
	  <label><b>Last name</b></label>
	  <input type="text" placeholder="Enter Last name" name="lname" required>
	  <label><b>email</b></label>
	  <input type="text" placeholder="Enter email" name="email" required>
          <label><b>Username</b></label>
	  <input type="text" placeholder="Choose your username" name="uname" required>
          <button type="submit">Register</button>
	</div>

      </form>
    </div>
  </body>
</html>
