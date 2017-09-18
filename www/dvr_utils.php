<?php

function deltaTimeStr($deltaTime)
{
   $deltaM = floor($deltaTime/60);
   $deltaH = floor($deltaM/60);
   $tstr = '';
   if ($deltaH == 0)
      $tstr .= $deltaM.' min';
   else {
      $tstr .= $deltaH.'hr'.($deltaH>1?'s':'');
      $remainder = $deltaM - 60*$deltaH;
      if ($remainder > 0) $tstr .= ', '.$remainder.' min';
   }

   return $tstr;
}

function renderRecordingsTable($items, $actions)
{
   $result = '';
   $cnt = 0;
   foreach ($items as $item) {
      if ($cnt == 0) {
         $result .= '<table style="width:100%">';
      }

      if ($cnt%2 == 0) 
         $result .= '<tr style="background-color:#b8d7b0">';
      else
         $result .= '<tr style="background-color:#e2f4dd">';

      $id = $item['value']['_id'];
      $start = $item['value']['record-start'];
      $delta = $item['value']['record-end'] - $start;
      $channel = $item['value']['channel'];
      
      $result .= '  <th rowspan="1">'.$item['value']['description'].'</th>';
      $result .= '  <td>';
      $result .= '     <table class="w3-table" style="border-style:hidden;">';
      $result .= '        <tr>';

      $q = "'";
      foreach ($actions as $action) {
         $result .= '           <td style="text-align:center;padding:5px;">';
         $result .= '              <img onclick="'.$action['onclick'].'('.$q.$id.$q.')"';
         $result .= '                     src="'.$action['src'].'" class="Btn"';
         $result .= '                     width="32" height="32" title="'.$action['title'].'">';
         $result .= '           </td>';
      }
      
      $result .= '        </tr>';
      $result .= '     </table>';
      $result .= '  </td>';
      $result .= '</tr>';
      
      if ($cnt%2 == 0) 
         $result .= '<tr style="background-color:#b8d7b0">';
      else
         $result .= '<tr style="background-color:#e2f4dd">';
      $result .= '  <td>Ch '.$channel.' for '.deltaTimeStr($delta).'</td>';
      $result .= '  <td>'.date(" @h:i a \o\\n D M j, 'y",$start).'</td>';
      $result .= '</tr>';
      $cnt += 1;
   }
   if ($cnt == 0) {
      $result .= "<p>No Recordings available.</p>";
   } else {
      $result .= '</table>';
   }
   return $result;
}

?>
