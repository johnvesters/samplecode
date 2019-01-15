<?php
   //----------------------------------------------------------------
   //force redirect to secure page
   //----------------------------------------------------------------
   if ($_SERVER["SERVER_PORT"] != '443' && $_SERVER["SERVER_ADDR"] != "127.0.0.1" && strncmp($_SERVER["SERVER_ADDR"], "192.168", 7) != 0) {
      header ("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
      exit();
   }

   //-----------------------------
   // Routing request app/<route>
   //-----------------------------
   $l_RequestUri = $_REQUEST['page'];

   //-----------------------------
   // Router
   //-----------------------------
   switch ($l_RequestUri) {
   case 'bagels':
      $l_Tags = new StdClass;
      $l_Tags->refresh_time = time ();
      echo application_html ("header", $l_Tags);
      echo application_html ("bagels", $l_Tags);
      echo application_html ("footer", $l_Tags);
      break;
   case 'burgers':
      $l_Tags = new StdClass;
      $l_Tags->refresh_time = time ();
      echo application_html ("header", $l_Tags);
      echo application_html ("burgers", $l_Tags);
      echo application_html ("footer", $l_Tags);
      break;
   case 'iceisnice':
      $l_Tags = new StdClass;
      $l_Tags->refresh_time = time ();
      echo application_html ("header", $l_Tags);
      echo application_html ("iceisnice", $l_Tags);
      echo application_html ("footer", $l_Tags);
      break;
   case 'sweethearts':
      $l_Tags = new StdClass;
      $l_Tags->refresh_time = time ();
      echo application_html ("header", $l_Tags);
      echo application_html ("sweethearts", $l_Tags);
      echo application_html ("footer", $l_Tags);
      break;
   default:
      $l_Tags = new StdClass;
      $l_Tags->refresh_time = time ();
      echo application_html ("header", $l_Tags);
      echo application_html ("home", $l_Tags);
      echo application_html ("footer", $l_Tags);
   }

   function GetHTML($i_PageUrl)
   {
      $l_DirName = "html";
      $l_File = sprintf ("%s/%s", $l_DirName, $i_PageUrl);
      $l_Page = mb_convert_encoding (file_get_contents ($l_File), "HTML-ENTITIES", "UTF-8");
      return ($l_Page);
   }

   function application_html ($i_Page, $i_Tags = null)
   {
      $l_Html = GetHTML ("$i_Page.html");
      if ($i_Tags != null) {
         foreach ($i_Tags as $l_TagName => $l_TagValue) {
            $l_ArraySearch[] = sprintf ("{[%s]}", $l_TagName);
            $l_ArrayReplace[] = $l_TagValue;
         }
         $l_Html = str_replace ($l_ArraySearch, $l_ArrayReplace, $l_Html);
      }
      return ($l_Html);
   }
?>
