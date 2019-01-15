<?php
   //----------------------------------------------------------------
   // Force redirect to secure page
   //----------------------------------------------------------------
   if ($_SERVER["SERVER_PORT"] != '443' && $_SERVER["SERVER_ADDR"] != "127.0.0.1" && $_SERVER["SERVER_ADDR"] != "172.16.55.132" && $_SERVER["HTTP_HOST"] != "localhost" && strncmp($_SERVER["SERVER_ADDR"], "192.168", 7) != 0) {
      header ("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
      exit();
   }

   //----------------------------------------------------------------
   // Start Session
   //----------------------------------------------------------------
   session_start ();

   //----------------------------------------------------------------
   // Include
   //----------------------------------------------------------------
   require_once ("page/include.php");
   require_once ("page/functions.php");
   require_once ("page/html.php");
   require_once ("page/database.php");
   require_once ("page/dataobject.php");
   require_once ("page/glogin.php");

   if (ValidUser ()) {
      $l_Tags = new StdClass;
      $l_Tags->refresh_time = time ();

      //---------------------------------
      // Get the header
      //---------------------------------
      echo application_html ("header", $l_Tags);
      //---------------------------------
      // Get the topbar
      //---------------------------------
      $l_Html = application_html ("topbar");
      libxml_use_internal_errors(true);
      $l_Document = new DOMDocument();
      $l_Document->preserveWhiteSpace = false;
      $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      echo SetMenuOptions ($l_Document);
      //---------------------------------
      // Get the menu
      //---------------------------------
      $l_Html = application_html ("menu");
      libxml_use_internal_errors(true);
      $l_Document = new DOMDocument();
      $l_Document->preserveWhiteSpace = false;
      $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
      echo SetMenuOptions ($l_Document);
      //---------------------------------
      // Get the panel area
      //---------------------------------
      echo application_html ("panel");
      //---------------------------------
      // Get the footer
      //---------------------------------
      echo application_html ("footer");
   } else {
      CheckCredentials ();
   }

   exit;

function SetMenuOptions($i_Document, $i_DocumentFragment = null)
{
   $xpath = new DOMXPath ($i_Document);

   if ($i_DocumentFragment == null) {
      $i_DocumentFragments = $xpath->query ("node()");

      foreach ($i_DocumentFragments as $l_DocumentFragment) {
         if ($l_DocumentFragment->nodeName == "nav" ||
             $l_DocumentFragment->nodeName == "li" ||
             $l_DocumentFragment->nodeName == "div") {
            SetMenuOptions($i_Document, $l_DocumentFragment);
            return ($i_Document->saveHTML ());
         }
      }
   } else {
      $l_All = $xpath->query ("node ()", $i_DocumentFragment);
      foreach ($l_All as $l_Element) {
         switch ($l_Element->nodeName) {
         case "ul":
            SetMenuOptions ($i_Document, $l_Element);
            break;
         case "li":
            SetMenuOptions ($i_Document, $l_Element);
            break;
         case "a":
            if ($l_Element->getAttribute ("data-active") && $l_Element->getAttribute ("data-active") == "true") {
               if ($l_Element->getAttribute ("data-panel")) {
                  $l_Permissions = GetAllowPanel ($l_Element->getAttribute ("data-panel"));
                  if ($l_Permissions->view) {
                     if ($l_Element->getAttribute ("data-display-field")) {  // go to span
                        SetMenuOptions ($i_Document, $l_Element);
                     }
                  } else {
                     $l_Element->setAttribute ("data-active", "false");
                  }
               }
            }
            break;
         case "span":
            break;
         }
      }
   }
}
?>
