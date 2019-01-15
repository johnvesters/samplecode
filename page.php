<?php
   //----------------------------------------------------------------
   // Force redirect to secure page
   //----------------------------------------------------------------
   if ($_SERVER["SERVER_PORT"] != '443' && $_SERVER["SERVER_ADDR"] != "127.0.0.1" && $_SERVER["HTTP_HOST"] != "localhost" && $_SERVER["SERVER_ADDR"] != "172.16.55.132" && strncmp($_SERVER["SERVER_ADDR"], "192.168", 7) != 0) {
      header ("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);
      exit;
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
   require_once ("page/sendmail.php");

   //----------------------------------------------------------------
   // For debug timing
   //----------------------------------------------------------------
   global $l_StartTime;
   $l_StartTime = time ();

   global $g_App;
   $g_App = new StdClass;
   //----------------------------------------------------------------
   // Process
   //----------------------------------------------------------------
   if (ValidUser ()) {
      $l_Permissions = new StdClass;

      if (!empty ($_REQUEST["fs"])) {
         //---------------------------------
         // Cache settings
         //---------------------------------
         $l_ETag = md5 ($_REQUEST["fs"]);
         header('Cache-control: max-age=' . (60 * 60 * 24 * 365));
         header('Expires: '. gmdate (DATE_RFC1123, time() + 60 * 60 * 24 * 365));
         header("Etag: $l_ETag");

         if (trim ($_SERVER['HTTP_IF_NONE_MATCH']) == $l_ETag && 0) {
            //------------------------------------
            // Browser has it so use it from cache
            //------------------------------------
            header("HTTP/1.1 304 Not Modified");
         } else {
            $c_Database = new General ();
            $l_Uuid = $c_Database->EscapeString ($_REQUEST["fs"]);

            //---------------------------------
            // Directory should exist
            //---------------------------------
            if (is_dir (Document_Base)) {
               //---------------------------------
               // Set parameters for DataObject()
               //---------------------------------
               $l_Object = new StdClass;
               $l_Object->uuid = "object_file";
               $l_Object->uuiddatainstance = $l_Uuid;

               //---------------------------------
               // Get data
               //---------------------------------
               $do_Object = new DataObject ($l_Object);
               if ($do_Object->GetNumberOfRows () == 1) {
                  $do_Object->FirstRecord ();

                  //---------------------------------
                  // Get result JSON
                  //---------------------------------
                  foreach ($do_Object->GetResultJson () as $l_FileObject) {
                     break;
                  }

                  //---------------------------------
                  // When preview get right file
                  //---------------------------------
                  if (isset ($_REQUEST["pv"])) {
                     CheckFileName ($l_FileObject);
                  }

                  //---------------------------------
                  // Set parameters and stream file
                  //---------------------------------
                  $l_Name = urlencode ($l_FileObject->name->dbvalue);
                  $l_FileType = $l_FileObject->filetype->dbvalue;
                  if ($l_FileType == "") {
                     $l_FileType = "application/octet-stream";
                  }

                  $l_Location = sprintf ("%s/%s", Document_Base, $l_FileObject->href->dbvalue);

                  if (file_exists ($l_Location) && is_file ($l_Location)) {
                     //---------------------------------
                     // Stream content
                     //---------------------------------
                     $l_Contents = file_get_contents ($l_Location);
                     header("Content-type: $l_FileType");
                     header("Content-disposition: inline;filename=$l_Name");
                     echo $l_Contents;
                  } else {
                     //---------------------------------
                     // Page not found
                     //---------------------------------
                     $l_Html = application_html ("page_not_found");
                     $l_Html .= sprintf ("<p>%s</p>", Document_Base);
                     echo $l_Html;
                  }
               }
            } else {
               //---------------------------------
               // Page not found
               //---------------------------------
               $l_Html = application_html ("page_not_found");
               $l_Html .= sprintf ("<p>%s</p>", Document_Base);
               echo $l_Html;
            }
         }
         exit;
      }

      if (!empty ($_FILES)) {
         date_default_timezone_set('UTC');

         $l_File = new StdClass;
         $l_File->object_file = new StdClass;
         $l_File->object_file->uuid = $_REQUEST["uuid"];
         $l_File->object_file->datetime = $_REQUEST["datetime"];
         $l_File->object_file->upload = date ("Y-m-d\TH:i:s\Z", time() - date('Z'));
         $l_File->object_file->name = $_FILES["file"]["name"];
         $l_File->object_file->filetype = $_FILES["file"]["type"];
         $l_File->object_file->tmpname = $_FILES["file"]["tmp_name"];
         $l_File->object_file->error = $_FILES["file"]["error"];
         $l_File->object_file->size = $_FILES["file"]["size"];

         SaveFile ($l_File);
         exit;
      }

      //------------------------
      // Process Submitted Json
      //------------------------
      if (!empty ($_REQUEST["j"])) {
         $l_Contents = json_decode ($_REQUEST["j"]);
         ProcessSubmittedJson ($l_Contents);
         exit;
      }

      if (!empty ($_REQUEST["s"])) { // special, custom
         $c_Database = new General ();
         $l_PanelObject = $c_Database->EscapeString ($_REQUEST["s"]);
         $l_PanelType = "script";
         $l_Uuid = $c_Database->EscapeString ($_REQUEST["uuid"]);

         $g_App->{$l_PanelObject} = $l_Uuid;

         $l_Permissions = GetAllowPanel ($l_PanelObject);
         if ($l_Permissions->view) {
            //---------------------------------
            // Get Topbar filters
            //---------------------------------
            if (!empty ($_REQUEST["fv"])) {
               $l_Filter = json_decode ($_REQUEST["fv"])->filter;
            } else {
               $l_Filter = null;
            }

            //---------------------------------
            // Open the script file
            //---------------------------------
            $l_Html = "";
            $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, $l_PanelObject);
            require_once ($l_ScriptFile);

            //---------------------------------
            // Actions
            //---------------------------------
            $l_Html = '<?xml encoding="utf-8" ?>' . $l_Html;

            libxml_use_internal_errors(true);
            $l_Document = new DOMDocument();
            $l_Document->preserveWhiteSpace = false;
            $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $l_Html = SetAllowedActions ($l_Document, $l_Permissions);
         } else {
            $l_Html = application_html ("page_access_denied");
         }
      }

      if (!empty ($_REQUEST["i"])) { // iframe
         $c_Database = new General ();
         $l_PanelObject = $c_Database->EscapeString ($_REQUEST["i"]);
         $l_PanelType = "script";
         $l_Uuid = $c_Database->EscapeString ($_REQUEST["uuid"]);

         $g_App->{$l_PanelObject} = $l_Uuid;

         $l_Permissions = GetAllowPanel ($l_PanelObject);
         if ($l_Permissions->view) {
            //---------------------------------
            // Get Topbar filters
            //---------------------------------
            if (!empty ($_REQUEST["fv"])) {
               $l_Filter = json_decode ($_REQUEST["fv"])->filter;
            } else {
               $l_Filter = null;
            }

            //---------------------------------
            // Open the script file
            //---------------------------------
            $l_Html = "";
            $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, $l_PanelObject);
            require_once ($l_ScriptFile);
         } else {
            $l_Html = application_html ("page_access_denied");
         }
      }

      if (!empty ($_REQUEST["t"])) {
         //---------------------------------
         // Request parameters
         //---------------------------------
         $c_Database = new General ();
         $l_PanelObject = $c_Database->EscapeString ($_REQUEST["t"]);
         $l_PanelType = "edit";
         $l_Uuid = $c_Database->EscapeString ($_REQUEST["uuid"]);

         $g_App->{$l_PanelObject} = $l_Uuid;

         $l_Permissions = GetAllowPanel ($l_PanelObject);
         if ($l_Permissions->view) {
            //---------------------------------
            // Get the HTML
            //---------------------------------
            $l_Html = application_html ($l_PanelObject);

            //---------------------------------
            // Get Topbar filters
            //---------------------------------
            if (!empty ($_REQUEST["fv"])) {
               $l_Filter = json_decode ($_REQUEST["fv"])->filter;
            } else {
               $l_Filter = null;
            }

            //---------------------------------
            // Populate the selects
            //---------------------------------
            libxml_use_internal_errors(true);
            $l_Document = new DOMDocument();
            $l_Document->preserveWhiteSpace = false;
            $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $l_Html = PopulateSelects ($l_Document, $l_Filter);
            $l_HtmlForTemplate = preg_replace ('/<script>/', '<!-- no script ', $l_Html);
            $l_HtmlForTemplate = preg_replace ('/<\/script>/', '-->', $l_HtmlForTemplate);

            //---------------------------------
            // Use DOM to set up the HTML
            //---------------------------------
            libxml_use_internal_errors(true);
            $l_Document = new DOMDocument();
            $l_Document->preserveWhiteSpace = false;
            $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            //---------------------------------
            // Get First Fieldset and populate
            //---------------------------------
            $l_Fieldsets = $l_Document->getElementsByTagName ("fieldset");
            foreach ($l_Fieldsets as $l_Fieldset) {
               $l_DataObject = $l_Fieldset->getAttribute ("data-object");

               //------------
               // Force Uuid
               //------------
               if ($l_Fieldset->getAttribute ("data-force")) { // force uuid
                  $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, "Forced");
                  if (file_exists ($l_ScriptFile)) {
                     require_once ($l_ScriptFile);
                     $l_Uuid = GetForcedUuid ($l_DataObject);
                     $l_Filter->{$l_DataObject} = $l_Uuid;
                  } else {
                     $l_Uuid = "undefined";
                  }
               }

               //-------------------------------------
               // Set Uuid from filter when undefined
               //-------------------------------------
               if ($l_Uuid == "undefined" && $l_Filter != null && isset ($l_DataObject) && isset ($l_Filter->{$l_DataObject}) && $l_Filter->{$l_DataObject} != "") {
                  $l_Uuid = $l_Filter->{$l_DataObject};
               }

               $l_Html = PopulateHTML ($l_Document, $l_Fieldset, $l_Uuid, $l_PanelObject, $l_Filter);
            }

            //---------------------------------
            // Actions
            //---------------------------------
            libxml_use_internal_errors(true);
            $l_Document = new DOMDocument();
            $l_Document->preserveWhiteSpace = false;
            $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $l_Html = SetAllowedActions ($l_Document, $l_Permissions);

            //---------------------------------
            // Uploaded file information
            // will be returned without
            // continuing to rest
            //---------------------------------
            if (isset ($_REQUEST["f"])) {
               echo $l_Html;
               exit;
            }
         } else {
            $l_Html = application_html ("page_access_denied");
         }
      }

      if (!empty ($_REQUEST["p"])) {
         $c_Database = new General ();
         $l_PanelObject = $c_Database->EscapeString ($_REQUEST["p"]);
         $l_PanelType = "script";
         $l_Uuid = $c_Database->EscapeString ($_REQUEST["uuid"]);

         $g_App->{$l_PanelObject} = $l_Uuid;

         $l_Permissions = GetAllowPanel ($l_PanelObject);
         if ($l_Permissions->view) {
            //---------------------------------
            // Get Topbar filters
            //---------------------------------
            if (!empty ($_REQUEST["fv"])) {
               $l_Filter = json_decode ($_REQUEST["fv"])->filter;
            } else {
               $l_Filter = null;
            }

            //---------------------------------
            // Open the script file
            //---------------------------------
            $l_PrintJson = "";
            $l_Html = "";

            $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, $l_PanelObject);
            require_once ($l_ScriptFile);

            //---------------------------------
            // Call the print process
            //---------------------------------
         } else {
            $l_Html = application_html ("page_access_denied");
         }
      }

      if (!empty ($_REQUEST["l"])) {
         //---------------------------------
         // Request parameters
         //---------------------------------
         $c_Database = new General ();
         $l_PanelObject = $c_Database->EscapeString ($_REQUEST["l"]);
         $l_PanelType = "list";
         $l_Uuid = $c_Database->EscapeString ($_REQUEST["uuid"]);

         $g_App->{$l_PanelObject} = $l_Uuid;

         $l_Permissions = GetAllowPanel ($l_PanelObject);
         if ($l_Permissions->view) {
            //---------------------------------
            // Get Topbar filters
            //---------------------------------
            if (!empty ($_REQUEST["fv"])) {
               $l_Filter = json_decode ($_REQUEST["fv"])->filter;
            } else {
               $l_Filter = null;
            }

            //---------------------------------
            // Get the HTML
            //---------------------------------
            $l_Html = application_html ($l_PanelObject);
            //$l_HtmlForTemplate = $l_Html;

            $l_Html = ListHtml ($l_Html, $l_Filter);

            //---------------------------------
            // Actions
            //---------------------------------
            libxml_use_internal_errors(true);
            $l_Document = new DOMDocument();
            $l_Document->preserveWhiteSpace = false;
            $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $l_Html = SetAllowedActions ($l_Document, $l_Permissions);
         } else {
            $l_Html = application_html ("page_access_denied");
         }
      }

      if (!empty ($_REQUEST["o"])) {
         //---------------------------------
         // Request parameters
         //---------------------------------
         $c_Database = new General ();
         $l_PanelObject = $c_Database->EscapeString ($_REQUEST["o"]);
         $l_PanelType = "edit";
         $l_Uuid = $c_Database->EscapeString ($_REQUEST["uuid"]);

         $g_App->{$l_PanelObject} = $l_Uuid;

         $l_Permissions = GetAllowPanel ($l_PanelObject);
         if ($l_Permissions->view) {
            //---------------------------------
            // Get the HTML
            //---------------------------------
            $l_Fieldset = new StdClass;
            $l_Fieldset->uuid = htmlentities ($l_PanelObject);
            GetPanelObject ($l_Fieldset);
            BuildHtml ($l_Fieldset, $l_Html);
            $l_HtmlForTemplate = $l_Html;

            //---------------------------------
            // Use DOM to set up the HTML
            //---------------------------------
            libxml_use_internal_errors(true);
            $l_Document = new DOMDocument();
            $l_Document->preserveWhiteSpace = false;
            $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            //---------------------------------
            // Get First Fieldset and populate
            //---------------------------------
            $l_Fieldsets = $l_Document->getElementsByTagName ("fieldset");
            foreach ($l_Fieldsets as $l_Fieldset) {
               $l_Html = PopulateHTML ($l_Document, $l_Fieldset, $l_Uuid, $l_PanelObject);
            }
         } else {
            $l_Html = application_html ("page_access_denied");
         }
      }

      if (!empty ($_REQUEST["x"])) {
         //---------------------------------
         // Request parameters
         //---------------------------------
         $c_Database = new General ();
         $l_PanelObject = $c_Database->EscapeString ($_REQUEST["x"]);
         $l_PanelType = "custom";
         $l_Uuid = $c_Database->EscapeString ($_REQUEST["uuid"]);

         $g_App->{$l_PanelObject} = $l_Uuid;

         $l_Permissions = GetAllowPanel ($l_PanelObject);
         if ($l_Permissions->view) {
            //---------------------------------
            // Get Topbar filters
            //---------------------------------
            if (!empty ($_REQUEST["fv"])) {
               $l_Filter = json_decode ($_REQUEST["fv"])->filter;
            } else {
               $l_Filter = null;
            }

            //---------------------------------
            // Get the HTML
            //---------------------------------
            $l_Html = application_html ($l_PanelObject);

            //---------------------------------
            // Actions
            //---------------------------------
            libxml_use_internal_errors(true);
            $l_Document = new DOMDocument();
            $l_Document->preserveWhiteSpace = false;
            $l_Document->loadHTML ($l_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $l_Html = SetAllowedActions ($l_Document, $l_Permissions);
         } else {
            $l_Html = application_html ("page_access_denied");
         }
      }

      //----------------------------------------------------------------
      // Template
      //----------------------------------------------------------------
      printf ("<div class=\"template\">");
      echo $l_HtmlForTemplate;
      printf ("</div>\n");

      //----------------------------------------------------------------
      // Form
      //----------------------------------------------------------------
      printf ("<div class=\"form\">");
      printf ("<form data-name=\"%s\">\n", $l_PanelObject);
      echo $l_Html;
      printf ("<i name=\"anchor\"></i>\n");
      printf ("</form>");
      printf ("</div>");

      //----------------------------------------------------------------
      // Pop disable box
      //----------------------------------------------------------------
      printf ("<div class=\"disableBox\"></div>");
   } else {
      if (isset ($_REQUEST["x"])) {
         $l_Tags = new StdClass;
         $l_Tags->refresh_time = time ();

         switch ($_REQUEST["x"]) {
         case "login_standard":
            echo application_html ("login_standard", $l_Tags);
            break;
         }
         exit;
      }

      $l_Html = application_html ("page_access_overruled");
      //----------------------------------------------------------------
      // Form
      //----------------------------------------------------------------
      printf ("<div class=\"form\">");
      echo $l_Html;
      printf ("</div>");
   }

   exit;

function ProcessSubmittedData($i_DataObject, $i_DataObjectUuid)
{
   if (is_object ($i_DataObject) && is_array ($i_DataObject->data)) {
      foreach ($i_DataObject->data as $l_Index => $l_DataInstance) {  // Data Array
         foreach ($l_DataInstance as $l_DataFieldUuid => $l_DataFieldValue) { // Fields
            if ($l_DataFieldUuid == "action") {
               $l_Action = $l_DataFieldValue;
            } elseif ($l_DataFieldUuid == "uuid") {
               $l_Object = new StdClass;
               $l_Object->uuid = $i_DataObjectUuid;
               $l_Object->uuiddatainstance = $l_DataFieldValue;

               $do_Object = new DataObject ($l_Object);
               if ($do_Object->GetNumberOfRows () == 0) {
                  $l_Object->action = "new";
                  $do_Object = new DataObject ($l_Object);
               }
               $do_Object->FirstRecord ();
            } else {
               if ($do_Object->IsField ($l_DataFieldUuid)) { // Field
                  $do_Object->Set ($l_DataFieldUuid, $l_DataFieldValue->value);
               } else { // Object
                  $l_DataObject = $l_DataFieldValue;
                  $l_DataObjectUuid = $l_DataFieldUuid;

                  $l_DataObject->uuidparent = $l_Object->uuid;
                  $l_DataObject->uuiddatainstanceparent = $l_Object->uuiddatainstance;

                  ProcessSubmittedData ($l_DataObject, $l_DataObjectUuid);
               }
            }
         }

         // Write instance to DB
         if (count ($do_Object->GetResultJson ()) > 0) {
            $i_DataObject->uuidlinkobject = $l_Object->uuid;
            $i_DataObject->uuiddatainstancechild = $l_Object->uuiddatainstance;
            $i_DataObject->uuidchild = $l_Object->uuid;

            $l_InstanceObject = new StdClass;
            $l_InstanceObject->{$i_DataObjectUuid} = new StdClass;
            foreach ($do_Object->GetResultJson () as $l_Uuid) {
               $l_InstanceObject->{$i_DataObjectUuid}->uuid = $l_Object->uuiddatainstance;
               foreach ($l_Uuid as $l_FieldName => $l_Field) {
                  if ($l_Field->type != "fieldset") {
                     if (!(isset($l_Field->newvalue))) { // not in submitted data set
                        $l_InstanceObject->{$i_DataObjectUuid}->{$l_FieldName} = $l_Field->dbvalue; // keep DB value
                     } else {
                        $l_InstanceObject->{$i_DataObjectUuid}->{$l_FieldName} = $l_Field->newvalue; // take new value
                     }
                  }
               }
            }

            $l_Instance = new StdClass;
            $l_Instance->uuid = $l_Object->uuiddatainstance;
            $l_Instance->uuiddataobject = $l_Object->uuid;
            $l_Instance->json = json_encode ($l_InstanceObject, JSON_PRETTY_PRINT);
            switch ($l_Action) {
            case "new":
            case "save":
               SaveDataInstance ($l_Instance);
               break;
            case "delete":
               DeleteDataInstance ($l_Instance);
               break;
            }

            if (isset ($i_DataObject->uuiddatainstanceparent)) {
               $l_Instance->uuidparent = $i_DataObject->uuiddatainstanceparent;
               $l_Instance->uuidlinkobject = $i_DataObject->uuidlinkobject;
               $l_Instance->uuiddataobjectparent = $i_DataObject->uuidparent;
               $l_Instance->uuidchild = $i_DataObject->uuiddatainstancechild;
               $l_Instance->uuiddataobjectchild = $i_DataObject->uuidchild;
               switch ($l_Action) {
               case "new":
               case "save":
                  SaveDataLink ($l_Instance);
                  break;
               case "delete":
                  DeleteDataLink ($l_Instance);
                  break;
               }
            }
         }
      }
   }
}

function PopulateHTML($i_Document, $i_DocumentFragment, $i_Uuid, $i_PanelObject, $i_Filter = null, $do_Object = null)
{
   $xpath = new DOMXPath ($i_Document);

   if ($do_Object != null) { // Get the current record and process
      //---------------------------------
      // Get current Uuid
      //---------------------------------
      $l_DataObject = $i_DocumentFragment->getAttribute ("data-object");
      $l_Uuid = $do_Object->GetID ();

      $i_DocumentFragment->setAttribute ("data-uuid", $l_Uuid);

      //---------------------------------
      // Set parameters for DataObject()
      //---------------------------------
      $l_Object = new StdClass;
      $l_Object->uuid = $l_DataObject;
      $l_Object->uuiddatainstance = $l_Uuid;

      //---------------------------------
      // Set value in input
      //---------------------------------
      $l_All = $xpath->query ("node ()", $i_DocumentFragment);
      foreach ($l_All as $l_Element) {
         if ($l_Element->nodeName == "td" || $l_Element->nodeName == "th") {
            $l_TdChildren = $xpath->query ("node ()", $l_Element);
            foreach ($l_TdChildren as $l_TdChild) {
               $l_Node = $l_TdChild;
               PopulateHtmlNode ($l_Node, $do_Object, $l_Object, $i_Document, $i_PanelObject, $i_Filter);
            }
         } else {
            $l_Node = $l_Element;
            PopulateHtmlNode ($l_Node, $do_Object, $l_Object, $i_Document, $i_PanelObject, $i_Filter);
         }
      }
   } else { // Select the new set
      $l_DataObject = $i_DocumentFragment->getAttribute ("data-object");
      $l_Uuid = $i_Uuid;

      //---------------------------------
      // Set parameters for DataObject()
      //---------------------------------
      $l_Object = new StdClass;
      $l_Object->uuid = $l_DataObject;
      $l_Object->uuiddatainstance = $l_Uuid;

      if ($i_DocumentFragment->getAttribute ("data-content") == "mail") {
         //-----------------------------------
         // Set additional parameters for Mail
         //-----------------------------------
         $l_Object = GetMailSetup ($l_Object);
      }
      if ($i_DocumentFragment->getAttribute ("data-content") == "soap") {
         //-----------------------------------
         // Set additional parameters for Soap
         //-----------------------------------
         if ($i_DocumentFragment->getAttribute ("data-direct-filter") == "true") {
            $l_Object->directfilter = true;
         }
         $l_Object = GetSoapSetup ($l_Object);
      }

      //-------------------------------------------------
      // Alternative data source
      //-------------------------------------------------
      if ($i_DocumentFragment->getAttribute ("data-source")) { // force uuid
         $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, "DataObject");
         if (file_exists ($l_ScriptFile)) {
            require_once ($l_ScriptFile);
            $do_Object = GetDataObject ($i_DocumentFragment->getAttribute ("data-source"), $l_Object);
         }
      } else {
      //---------------------------------
      // Get data
      //---------------------------------
         $do_Object = new DataObject ($l_Object);
      }

      if ($do_Object->GetNumberOfRows () > 0) {
         $do_Object->FirstRecord ();
         do {
            //---------------------------------
            // Get current Uuid
            //---------------------------------
            $l_Uuid = $do_Object->GetID ();
            $i_DocumentFragment->setAttribute ("data-uuid", $l_Uuid);
            //---------------------------------
            // Set value in input
            //---------------------------------
            $l_All = $xpath->query ("node ()", $i_DocumentFragment);
            foreach ($l_All as $l_Element) {
               if ($l_Element->nodeName == "td" || $l_Element->nodeName == "th") {
                  $l_TdChildren = $xpath->query ("node ()", $l_Element);
                  foreach ($l_TdChildren as $l_TdChild) {
                     $l_Node = $l_TdChild;
                     PopulateHtmlNode ($l_Node, $do_Object, $l_Object, $i_Document, $i_PanelObject, $i_Filter);
                  }
               } else {
                  $l_Node = $l_Element;
                  PopulateHtmlNode ($l_Node, $do_Object, $l_Object, $i_Document, $i_PanelObject, $i_Filter);
               }
            }
         } while ($do_Object->NextRecord ());
      }
   }

   if ($do_Object->GetNumberOfRows () == 0) {
      if ($i_Uuid == "_new_") {
         //---------------------------------------------------
         // set value from topbar filter for data-filter=true
         //---------------------------------------------------
         foreach ($do_Object->GetFields () as $l_FieldName => $l_Json) {
            if (isset ($l_Json->optionuuid) && isset ($i_Filter->{$l_Json->optionuuid})) {
               $l_FoundFields = $xpath->query ("select[@name=\"$l_FieldName\"]", $i_DocumentFragment);
               foreach ($l_FoundFields as $l_FoundField) {
                  if ($l_FoundField->getAttribute ("data-filter") == "true") {
                     $l_FoundField->setAttribute ("data-value", $i_Filter->{$l_Json->optionuuid});
                  }
               }
            }
         }
      } else {
         $i_DocumentFragment->setAttribute ("readonly", "readonly");
         $i_DocumentFragment->setAttribute ("disabled", "disabled");
         $i_DocumentFragment->setAttribute ("data-no-record", "true");
      }
   }
   return ($i_Document->saveHTML ());
}

function PopulateHtmlNode ($i_Node, $do_Object, $i_Object, $i_Document, $i_PanelObject, $i_Filter = null)
{
   $xpath = new DOMXPath ($i_Document);

   $l_DataObject = $i_Object->uuid;
   $l_Uuid = $i_Object->uuiddatainstance;

   //-------------------------------------------
   // Set value for node (input,select,textarea
   //-------------------------------------------
   switch ($i_Node->nodeName) {
   case "ul":
      $l_ListItems = $xpath->query ("node ()", $i_Node);

      foreach ($l_ListItems as $l_ListItem) {
         switch ($l_ListItem->nodeName) {
         case "input":
            if ($l_ListItem->getAttribute ("data-type") == "filter") {
               $l_FilterValues[$l_ListItem->getAttribute ("name")] = explode (",", $l_ListItem->getAttribute ("value"));
            }
            if ($l_ListItem->getAttribute ("data-type") == "sort") {
               $l_SortField = $l_ListItem->getAttribute ("name");
               if ($l_ListItem->getAttribute ("data-sort-order")) {
                  $l_SortOrder = $l_ListItem->getAttribute ("data-sort-order");
               }
            }
            break;
         case "li":
            $l_DataObjectChild = $i_Node->getAttribute ("data-object");

            if ($l_DataObjectChild == $l_DataObject) { // Same object, do not use datalink
               PopulateHTML ($i_Document, $l_ListItem, $l_Uuid, $i_PanelObject, $i_Filter);
            } else {
               //---------------------------------
               // Get child records via datalink
               //---------------------------------
               $l_Object = new StdClass;
               $l_Object->uuidparent = $l_DataObject;
               $l_Object->uuiddatainstanceparent = $l_Uuid;
               $l_Object->uuid = $l_DataObjectChild;
               $l_Object->uuiddatainstance = "";
               if (isset ($l_SortField)) {
                  $l_Object->sortfield = $l_SortField;
               }
               if (isset ($l_SortOrder)) {
                  $l_Object->sortorder = $l_SortOrder;
               }
               if (isset ($l_FilterValues)) {
                  $l_Object->filterfield = new StdClass;
                  foreach ($l_FilterValues as $l_FilterFieldName => $l_FilterFieldValues) {
                     $l_Object->filterfield->{$l_FilterFieldName} = new StdClass;
                     $l_Object->filterfield->{$l_FilterFieldName}->value = $l_FilterFieldValues;
                  }
               }

               $do_Children = new DataObject ($l_Object);
               if ($do_Children->GetNumberOfRows () > 0) {
                  $l_ClonedListItem = $l_ListItem->cloneNode (true);
                  $l_CurrentRow= 0;

                  $do_Children->FirstRecord ();
                  do {
                     if ($l_CurrentRow++ == 0) {
                        $l_AddedListItem = $l_ListItem;
                     } else {
                        //---------------------------------
                        // Clone li
                        //---------------------------------
                        $l_NewListItem = $l_ClonedListItem->cloneNode (true);
                        $l_AddedListItem = $i_Node->appendChild ($l_NewListItem);
                     }
                     PopulateHTML ($i_Document, $l_AddedListItem, $do_Children->GetID (), $i_PanelObject, $i_Filter, $do_Children);
                  } while ($do_Children->NextRecord ());
               }
            }
            break;
         }
      }
      break;
   case "table":
      $l_TableSections = $xpath->query ("node ()", $i_Node);
      foreach ($l_TableSections as $l_TableSection) {
         switch ($l_TableSection->nodeName) {
         case "thead":
            $l_Thead = $l_TableSection;
         case "tbody":
            $l_TableRows = $xpath->query ("node ()", $l_TableSection);
            foreach ($l_TableRows as $l_TableRow) {
               switch ($l_TableRow->nodeName) {
               case "input":
                  if ($l_TableRow->getAttribute ("data-type") == "filter") {
                     if ($l_TableRow->getAttribute ("data-filter-type")) {
                        $l_FilterType = $l_TableRow->getAttribute ("data-filter-type");
                     } else {
                        $l_FilterType = "standard";
                     }

                     if ($l_TableRow->getAttribute ("data-type") == "filter") {
                        $l_FilterValues[$l_FilterType][$l_TableRow->getAttribute ("name")] = explode (",", $l_TableRow->getAttribute ("value"));
                     }
                     if ($l_TableRow->getAttribute ("data-filter-field") && isset ($i_Filter)) {
                        list ($l_FilterDataObjectUuid, $l_FilterFieldName) = explode (":", $l_TableRow->getAttribute ("name"));
                        $l_FilterValues[$l_FilterType][$l_TableRow->getAttribute ("data-filter-field")][0] = $i_Filter->{$l_FilterDataObjectUuid};
                     }
                  }

                  if ($l_TableRow->getAttribute ("data-type") == "sort") {
                     $l_SortField = $l_TableRow->getAttribute ("name");
                     if ($l_TableRow->getAttribute ("data-sort-order")) {
                        $l_SortOrder = $l_TableRow->getAttribute ("data-sort-order");
                     }
                  }
                  break;
               case "tr":
                  $l_DataObjectChild = $l_TableRow->getAttribute ("data-object");

                  if (empty ($l_DataObjectChild)) { // data-object not set
                     //--------------------------------------
                     // Field are part current object
                     // Example wnswerknemer: wns_edit_hours
                     //--------------------------------------
                     PopulateHTML ($i_Document, $l_TableRow, $do_Object->GetID (), $i_PanelObject, $i_Filter, $do_Object);
                  } else {
                     //---------------------------------
                     // Get child records via datalink
                     //---------------------------------
                     $l_Object = new StdClass;
                     $l_Object->uuidparent = $l_DataObject;
                     $l_Object->uuiddatainstanceparent = $l_Uuid;
                     $l_Object->uuid = $l_DataObjectChild;
                     $l_Object->uuiddatainstance = "";

                     if ($l_TableRow->getAttribute ("data-content") == "mail" || $l_TableRow->getAttribute ("data-content") == "soap") {
                        if (isset ($l_FilterValues["setup"])) {
                           $l_Object->filterfield = new StdClass;
                           foreach ($l_FilterValues["setup"] as $l_FilterFieldName => $l_FilterFieldValues) {
                              $l_Object->filterfield->{$l_FilterFieldName} = new StdClass;
                              $l_Object->filterfield->{$l_FilterFieldName}->value = $l_FilterFieldValues;
                           }
                        }
                        if ($l_TableRow->getAttribute ("data-content") == "mail") {
                           $l_Object = GetMailSetup ($l_Object);
                        }
                        if ($l_TableRow->getAttribute ("data-content") == "soap") {
                           $l_Object = GetSoapSetup ($l_Object);
                        }
                     }

                     if (isset ($l_SortField)) {
                        $l_Object->sortfield = $l_SortField;
                     }
                     if (isset ($l_SortOrder)) {
                        $l_Object->sortorder = $l_SortOrder;
                     }

                     if (isset ($l_FilterValues["standard"])) {
                        $l_Object->filterfield = new StdClass;
                        foreach ($l_FilterValues["standard"] as $l_FilterFieldName => $l_FilterFieldValues) {
                           $l_Object->filterfield->{$l_FilterFieldName} = new StdClass;
                           $l_Object->filterfield->{$l_FilterFieldName}->value = $l_FilterFieldValues;
                        }
                     }

                     $do_Children = new DataObject ($l_Object);
                     if ($do_Children->GetNumberOfRows () > 0) {
                        $l_ClonedTableRow = $l_TableRow->cloneNode (true);
                        $l_CurrentRow= 0;

                        $do_Children->FirstRecord ();
                        do {
                           if ($l_CurrentRow++ == 0) {
                              $l_Row = $l_TableRow;
                           } else {
                              //---------------------------------
                              // Clone tr
                              //---------------------------------
                              $l_NewTableRow = $l_ClonedTableRow->cloneNode (true);
                              $l_Row = $l_TableSection->appendChild ($l_NewTableRow);
                           }
                           PopulateHTML ($i_Document, $l_Row, $do_Children->GetID (), $i_PanelObject, $i_Filter, $do_Children);
                        } while ($do_Children->NextRecord ());
                     } else {
                        if ($l_TableRow->getAttribute ("data-remove-empty") == "true") {
                           $l_TableSection->removeChild ($l_TableRow);
                           $l_Thead->setAttribute ("style", "display: none;");
                        }
                     }
                  }
                  break;
               }
            }
            break;
         }
      }
      break;
   case "fieldset":
      $l_DataObject = $i_Node->getAttribute ("data-object");

      //------------
      // Force Uuid
      //------------
      if ($i_Node->getAttribute ("data-force")) { // force uuid
         $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, "Forced");
         if (file_exists ($l_ScriptFile)) {
            require_once ($l_ScriptFile);
            $l_Uuid = GetForcedUuid ($l_DataObject);
         } else {
            $l_Uuid = "undefined";
         }
      }

      //-------------------------------------
      // Set Uuid from filter when undefined
      //-------------------------------------
      if ($l_Uuid == "undefined" && $i_Filter != null && isset ($l_DataObject) && isset ($i_Filter->{$l_DataObject}) && $i_Filter->{$l_DataObject} != "") {
         $l_Uuid = $i_Filter->{$l_DataObject};
      }

      PopulateHTML ($i_Document, $i_Node, $l_Uuid, $i_PanelObject, $i_Filter);
      break;
   case "input":
      $l_FieldName = $i_Node->getAttribute ("name");

      switch ($i_Node->getAttribute ("type")) {
      case "datetime-local":
         $i_Node->setAttribute ("value", substr ($do_Object->Get ($l_FieldName), 0, -1));
         break;
      case "checkbox":
         $i_Node->setAttribute ("value", $do_Object->Get ($l_FieldName));
         if ($i_Node->getAttribute ("value") == "true") {
            $i_Node->setAttribute ("checked", "checked");
         }
         break;
      case "radio":
         if ($i_Node->getAttribute ("value") == $do_Object->Get ($l_FieldName)) {
            $i_Node->setAttribute ("checked", "checked");
         }
         break;
      case "file":
         $i_Node->setAttribute ("value", $do_Object->Get ($l_FieldName));
         $l_SaveNodes = $xpath->query ("./preceding-sibling::i[1]", $i_Node);
         foreach ($l_SaveNodes as $l_SaveNode) {
            $l_SaveNode->setAttribute ("data-uuid", $do_Object->Get ($l_FieldName));
            if ($l_SaveNode->getAttribute ("data-uuid") > "") {
               $l_SaveNode->setAttribute ("class", "fa fa-lg fa-file-text-o");
               $i_Node->setAttribute ("type", "hidden");
            }
            break;
         }
         break;
      default:
         $i_Node->setAttribute ("value", $do_Object->Get ($l_FieldName));
      }
      break;
   case "select":
      $l_FieldName = $i_Node->getAttribute ("name");
      $l_SelectDataObject = $i_Node->getAttribute ("data-object");

      $i_Node->setAttribute ("data-value", $do_Object->Get ($l_FieldName));
      break;
   case "textarea":
      $l_FieldName = $i_Node->getAttribute ("name");
      $i_Node->nodeValue = $do_Object->Get ($l_FieldName);
      break;
   case "iframe":
      if ($i_Node->getAttribute ("data-link")) {
         $l_FieldName = $i_Node->getAttribute ("data-link");
         $i_Node->setAttribute ("src", sprintf ("http://%s", $do_Object->Get ($l_FieldName)));
      }
      break;
   case "h2":
   case "h4":
   case "div":
      if ($i_Node->getAttribute ("name")) {
         $l_FieldName = $i_Node->getAttribute ("name");
         $l_FieldJson = $do_Object->GetFieldJson ($l_FieldName);
         switch ($l_FieldJson->type) {
         case "select":
            $l_Td->nodeValue = "";

            $l_Object = new StdClass;
            $l_Object->uuid = $l_FieldJson->optionuuid;
            $l_Object->uuiddatainstance = $do_Object->Get ($l_FieldName);
            $l_Object->optionstring = GetOptionString ($l_Object->uuid);

            //------------
            // Force Uuid
            //------------
            if ($i_Node->getAttribute ("data-force")) { // force uuid
               $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, "Forced");
               if (file_exists ($l_ScriptFile)) {
                  require_once ($l_ScriptFile);
                  $l_Object->uuiddatainstance = GetForcedUuid ($l_Object->uuid);
               }
            }

            $do_Options = new DataObject ($l_Object);
            if ($do_Options->GetNumberOfRows () == 1) {
               $do_Options->FirstRecord ();
               $i_Node->nodeValue = $do_Options->Get ($l_Object->optionstring);
            }
            break;
         case "tel":
            $l_FieldJson->showValue = $do_Object->Get ($l_FieldName);
            $l_Link = $i_Document->createElement ("a", FormatString ($l_FieldJson));
            $l_Link->setAttribute ("href", sprintf ("tel:%s", $do_Object->Get ($l_FieldName)));
            $l_Link->setAttribute ("rel","external");
            $i_Node->appendChild ($l_Link);
            break;
         case "email":
            $l_FieldJson->showValue = $do_Object->Get ($l_FieldName);
            $l_Link = $i_Document->createElement ("a", FormatString ($l_FieldJson));
            $l_Link->setAttribute ("href", sprintf ("mailto:%s", $do_Object->Get ($l_FieldName)));
            $l_Link->setAttribute ("rel","external");
            $i_Node->appendChild ($l_Link);
            break;
         case "url":
            if ($i_Node->getAttribute ("data-description")) {
               $l_FieldNameDescription = $i_Node->getAttribute ("data-description");
               $l_FieldJson->showValue = $do_Object->Get ($l_FieldNameDescription);
            } else {
               $l_FieldJson->showValue = $do_Object->Get ($l_FieldName);
            }
            $l_Link = $i_Document->createElement ("a", FormatString ($l_FieldJson));
            $l_Link->setAttribute ("href", sprintf ("%s", $do_Object->Get ($l_FieldName)));
            $l_Link->setAttribute ("target","_blank");
            $i_Node->appendChild ($l_Link);
            break;
         default:
            $l_FieldJson->showValue = $do_Object->Get ($l_FieldName);
            $i_Node->nodeValue = FormatString ($l_FieldJson);
         }
      }
      break;
   case "span":
      if ($i_Node->getAttribute ("data-name")) {
         $i_Node->nodeValue = $do_Object->Get ($i_Node->getAttribute ("data-name"));
      }
      break;
   case "img":
      $l_FieldName = $i_Node->getAttribute ("name");
      $l_NewObject = new StdClass;
      $l_NewObject->uuid = "object_file";
      $l_NewObject->uuiddatainstance = $do_Object->Get ($l_FieldName);

      $do_FileObject = new DataObject ($l_NewObject);
      if ($do_FileObject->GetNumberOfRows () == 1) {
         $do_FileObject->FirstRecord ();

         foreach ($do_FileObject->GetResultJson () as $l_Uuid) {
            $l_FileObject = $l_Uuid;
         }
      } else {
         $l_FileObject = null;
      }
      CheckFileName ($l_FileObject);

      $i_Node->setAttribute ("alt", $l_FileObject->name->dbvalue);
      if ($l_FileObject->exists) {
         $i_Node->setAttribute ("src", sprintf ("page.php?fs=%s&pv", $do_Object->Get ($l_FieldName)));
      } else {
         $i_Node->setAttribute ("src", $l_FileObject->href->dbvalue);
      }
      if ($l_FileObject->width->dbvalue > 0) {
         $i_Node->setAttribute ("width", $l_FileObject->width->dbvalue);
      }
      if ($l_FileObject->height->dbvalue > 0) {
         $i_Node->setAttribute ("height", $l_FileObject->height->dbvalue);
      }
      break;
   case "a":
      if ($i_Node->getAttribute ("name")) {
         if (count (explode (":", $i_Node->getAttribute ("name"))) == 2) {
            $l_NewObject = new StdClass;
            list ($l_NewObject->uuid, $l_FieldName) = explode (":", $i_Node->getAttribute ("name"));
            $l_NewObject->uuid = "object_file";
            $l_NewObject->uuiddatainstance = $do_Object->Get ($l_FieldName);

            $do_FileObject = new DataObject ($l_NewObject);
            if ($do_FileObject->GetNumberOfRows () == 1) {
               $do_FileObject->FirstRecord ();
               $l_HrefName = sprintf ("page.php?fs=%s", $do_FileObject->GetID ());
            } else {
               $i_Node->setAttribute ("target", "");
               $l_HrefName = "#";
            }
            $i_Node->setAttribute ("href", $l_HrefName);
            $i_Node->nodeValue = $do_Object->Get ($i_Node->getAttribute ("data-name"));
         } else {
            $l_FieldName = $i_Node->getAttribute ("name");
            $l_HrefName = sprintf ("page.php?fs=%s", $do_Object->GetID ());
            $i_Node->setAttribute ("href", $l_HrefName);
            $i_Node->nodeValue = $do_Object->Get ($l_FieldName);
         }
      } elseif ($i_Node->getAttribute ("data-link")) {
         $l_FieldName = $i_Node->getAttribute ("data-link");
         $i_Node->setAttribute ("href", sprintf ("%s%s", (strpos($do_Object->Get ($l_FieldName), "http") === false)? "http://": "", $do_Object->Get ($l_FieldName)));
         $i_Node->setAttribute ("target", "_blank");
         $i_Node->nodeValue = $do_Object->Get ($l_FieldName);
      }
      break;
   }
}

function PopulateSelects ($i_Document, $i_Filter)
{
   $xpath = new DOMXPath ($i_Document);

   foreach ($i_Document->getElementsByTagName ("select") as $l_SelectField) {
      $l_Options = $xpath->query ("node ()", $l_SelectField);
      foreach ($l_Options as $l_Option) {
         switch ($l_Option->nodeName) {
         case "option":
            $l_UuidDatainstance = "";

            //-------------------------------------------------
            // Force Uuid, set data-value and uuiddatainstance
            //-------------------------------------------------
            if ($l_SelectField->getAttribute ("data-force")) { // force uuid
               $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, "Forced");
               if (file_exists ($l_ScriptFile)) {
                  require_once ($l_ScriptFile);
                  $l_UuidDatainstance = GetForcedUuid ($l_SelectField->getAttribute ("data-object"));
                  $l_SelectField->setAttribute ("data-value", $l_UuidDatainstance);
               }
            }

            $l_Object = new StdClass;
            $l_Object->uuid = $l_SelectField->getAttribute ("data-object");
            $l_Object->uuiddatainstance = $l_UuidDatainstance;
            $l_Object->optionstring = GetOptionString ($l_Object->uuid);
            if ($l_SelectField->getAttribute ("data-sort-field")) {
               $l_Object->sortfield = $l_SelectField->getAttribute ("data-sort-field");
               if ($l_SelectField->getAttribute ("data-sort-order")) {
                  $l_Object->sortorder = $l_SelectField->getAttribute ("data-sort-order");
               }
            } else {
               $l_Object->sortfield = $l_Object->optionstring;
            }

            if ($l_SelectField->getAttribute ("data-filter") && $l_SelectField->getAttribute ("data-filter-field") && isset ($i_Filter)) {
               list ($l_FilterDataObjectUuid, $l_FilterFieldName) = explode (":", $l_SelectField->getAttribute ("data-filter"));
               $l_FilterValues[$l_SelectField->getAttribute ("data-filter-field")][0] = $i_Filter->{$l_FilterDataObjectUuid};
            }

            if (isset ($l_FilterValues)) {
               $l_Object->filterfield = new StdClass;
               foreach ($l_FilterValues as $l_FilterFieldName => $l_FilterFieldValues) {
                  $l_Object->filterfield->{$l_FilterFieldName} = new StdClass;
                  $l_Object->filterfield->{$l_FilterFieldName}->value = $l_FilterFieldValues;
               }
            }

            //-------------------------------------------------
            // Alternative data source
            //-------------------------------------------------
            if ($l_SelectField->getAttribute ("data-source")) { // other datasource
               $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, "DataObject");
               if (file_exists ($l_ScriptFile)) {
                  require_once ($l_ScriptFile);
                  $do_Options = GetDataObject ($l_SelectField->getAttribute ("data-source"), $l_Object);
               }
            } else {
               $do_Options = new DataObject ($l_Object);
            }

            if ($do_Options->GetNumberOfRows () > 0) {
               $do_Options->FirstRecord ();
               do {
                  $l_NewOption = $l_Option->cloneNode (true);
                  $l_NewOption->setAttribute ("value", $do_Options->GetID ());
                  $l_NewOption->nodeValue = htmlspecialchars ($do_Options->Get ($l_Object->optionstring), ENT_QUOTES);
                  $l_Added = $l_SelectField->appendChild ($l_NewOption);
               } while ($do_Options->NextRecord ());
            }
            break;
         }
      }
   }
   return ($i_Document->saveHTML ());
}

function SetAllowedActions ($i_Document, $i_Permissions)
{
   $xpath = new DOMXPath ($i_Document);

   $l_Elements = $xpath->query ("node ()", $i_Document);
   foreach ($l_Elements as $l_Element) {
      switch ($l_Element->nodeName) {
      case "fieldset":
         if ($l_Element->hasAttribute ("data-no-required")) {
            $l_Element->removeAttribute ("data-no-required");
            $i_Permissions->new = false;
            $i_Permissions->save = false;
            $i_Permissions->delete = false;
         }
         if ($l_Element->hasAttribute ("data-no-record")) {
            $l_Element->removeAttribute ("data-no-record");
            $i_Permissions->save = false;
            $i_Permissions->delete = false;
         }
         break;
      }
   }

   $l_Elements = $xpath->query ("node ()");
   foreach ($l_Elements as $l_Element) {
      switch ($l_Element->nodeName) {
      case "input":
         if (
            ($l_Element->getAttribute ("data-action") == "load" && !$i_Permissions->load) ||
            ($l_Element->getAttribute ("data-action") == "new" && !$i_Permissions->new) ||
            ($l_Element->getAttribute ("data-action") == "save" && !$i_Permissions->save) ||
            ($l_Element->getAttribute ("data-action") == "delete" && !$i_Permissions->delete)
            ) {
            $l_Top = $i_Document->documentElement;
            $l_Top->removeChild ($l_Element);
         }
         break;
      }
   }
   return ($i_Document->saveHTML ());
}

function ListHtml($i_Html, $i_Filter)
{
   libxml_use_internal_errors(true);

   global $l_StartTime;

   //---------------------------------
   // Use DOM to set up the HTML
   //---------------------------------
   libxml_use_internal_errors(true);
   $l_Document = new DOMDocument();
   $l_Document->preserveWhiteSpace = false;
   $l_Document->loadHTML ($i_Html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

   //---------------------------------
   // Get tbody and tr
   //---------------------------------
   $xpath = new DOMXPath ($l_Document);
   $l_Fieldsets = $xpath->query ("node ()", $l_Document);

   foreach ($l_Fieldsets as $l_Fieldset) {
      if ($l_Fieldset->nodeName == "fieldset") {
         $l_Tables = $xpath->query ("node ()", $l_Fieldset);
         foreach ($l_Tables as $l_Table) {
            switch ($l_Table->nodeName) {
            case "input":
               $l_Element = $l_Table;
               if ($l_Element->getAttribute ("data-type") == "required") {
                  //---------------------------------
                  // Check if required records exist
                  //---------------------------------
                  if ($l_Element->getAttribute ("name") && isset ($i_Filter)) {
                     $l_RequiredObject = new StdClass;
                     $l_RequiredObject->uuid = $l_Element->getAttribute ("name");
                     $l_RequiredObject->uuiddatainstance = $i_Filter->{$l_Element->getAttribute ("name")};
                     $do_RequiredObject = new DataObject ($l_RequiredObject);
                     if ($do_RequiredObject->GetNumberOfRows () == 0 || $i_Filter->{$l_Element->getAttribute ("name")} == "") {
                        $l_Fieldset->setAttribute ("readonly", "readonly");
                        $l_Fieldset->setAttribute ("disabled", "disabled");
                        $l_Fieldset->setAttribute ("data-no-required", "true");
                     }
                  }
               }
               break;
            case "table":
               $l_DataObjectUuid = $l_Table->getAttribute ("data-object");

               $l_Tbodies = $xpath->query ("node ()", $l_Table);
               foreach ($l_Tbodies as $l_Tbody) {
                  if ($l_Tbody->nodeName == "tfoot") {
                     $l_RowList = $xpath->query ("node ()", $l_Tbody);
                     foreach ($l_RowList as $l_Element) {
                        switch ($l_Element->nodeName) {
                        case "tr":
                           //---------------------------------
                           // Loop through td to set Totals
                           //---------------------------------
                           $l_Tds = $l_Element->getElementsByTagName ("td");

                           foreach ($l_Tds as $l_Td) {
                              $l_FieldName = $l_Td->getAttribute ("data-name");
                              $l_Json = $do_Object->GetFieldJson ($l_FieldName);
                              $l_Json->showValue = $l_Total[$l_FieldName];

                              if (isset ($l_Total[$l_FieldName])) {
                                 if (!isset ($l_Json->type)) { // none existing fiels like e.g. dof_tot
                                    $l_Json->numberofdecimals = $l_Td->getAttribute ("data-math-number-of-decimals");
                                    $l_Json->type = "number";
                                    if ($l_Td->getAttribute ("data-format")) {
                                       $l_Json->format = $l_Td->getAttribute ("data-format");
                                    }
                                 }
                                 $l_Td->nodeValue = FormatString ($l_Json);
                              }
                           }
                           break;
                        }
                     }
                  }

                  if ($l_Tbody->nodeName == "tbody") {
                     $l_RowList = $xpath->query ("node ()", $l_Tbody);
                     foreach ($l_RowList as $l_Element) {
                        switch ($l_Element->nodeName) {
                        case "input":
                           $l_Element = $l_Element;
                           if ($l_Element->getAttribute ("data-type") == "filter") {
                              if ($l_Element->getAttribute ("data-filter-type")) {
                                 $l_FilterType = $l_Element->getAttribute ("data-filter-type");
                              } else {
                                 $l_FilterType = "standard";
                              }

                              if ($l_Element->getAttribute ("value")) {
                                 $l_FilterValues[$l_FilterType][$l_Element->getAttribute ("data-filter-field")] = explode (",", $l_Element->getAttribute ("value"));
                              } else if ($l_Element->getAttribute ("name") && isset ($i_Filter)) {
                                 list ($l_FilterDataObjectUuid, $l_FilterFieldName) = explode (":", $l_Element->getAttribute ("name"));
                                 $l_FilterValues[$l_FilterType][$l_Element->getAttribute ("data-filter-field")][0] = $i_Filter->{$l_FilterDataObjectUuid};
                              }
                           }
                           if ($l_Element->getAttribute ("data-type") == "sort") {
                              $l_SortField = $l_Element->getAttribute ("name");
                           }
                           break;
                        case "tr":
                           $l_TableRow = $l_Element;

                           //---------------------------------
                           // Set parameters for DataObject()
                           //---------------------------------
                           $l_Object = new StdClass;
                           $l_Object->uuid = $l_DataObjectUuid;

                           if ($l_Element->getAttribute ("data-content") == "mail" || $l_Element->getAttribute ("data-content") == "soap") {
                              if (isset ($l_FilterValues["setup"])) {
                                 $l_Object->filterfield = new StdClass;
                                 foreach ($l_FilterValues["setup"] as $l_FilterFieldName => $l_FilterFieldValues) {
                                    $l_Object->filterfield->{$l_FilterFieldName} = new StdClass;
                                    $l_Object->filterfield->{$l_FilterFieldName}->value = $l_FilterFieldValues;
                                 }
                              }
                              if ($l_Element->getAttribute ("data-content") == "mail") {
                                 $l_Object = GetMailSetup ($l_Object);
                              }
                              if ($l_Element->getAttribute ("data-content") == "soap") {
                                 $l_Object = GetSoapSetup ($l_Object);
                              }
                           }

                           if (isset ($l_SortField)) {
                              $l_Object->sortfield = $l_SortField;
                           }

                           if (isset ($l_FilterValues["standard"])) {
                              $l_Object->filterfield = new StdClass;
                              foreach ($l_FilterValues["standard"] as $l_FilterFieldName => $l_FilterFieldValues) {
                                 $l_Object->filterfield->{$l_FilterFieldName} = new StdClass;
                                 $l_Object->filterfield->{$l_FilterFieldName}->value = $l_FilterFieldValues;
                              }
                           }

//printf ("Time before select: %d \n<br>", time() - $l_StartTime);
                           //-------------------------------------------------
                           // Alternative data source
                           //-------------------------------------------------
                           if ($l_Element->getAttribute ("data-source")) { // force uuid
                              $l_ScriptFile = sprintf ("application/%s/php/%s.php", App_Env, "DataObject");
                              if (file_exists ($l_ScriptFile)) {
                                 require_once ($l_ScriptFile);
                                 $do_Object = GetDataObject ($l_Element->getAttribute ("data-source"), $l_Object);
                              }
                           } else {
                           //---------------------------------
                           // Get data
                           //---------------------------------
                              $do_Object = new DataObject ($l_Object);
                           }

//printf ("Time after select: %d \n<br>", time() - $l_StartTime);
                           if ($do_Object->GetNumberOfRows () > 0) {
                              $l_ClonedTableRow = $l_TableRow->cloneNode (true);
                              $l_CurrentRow = 0;

                              $do_Object->FirstRecord ();
                              do {
                                 if ($l_CurrentRow++ == 0) {
                                    $l_Row = $l_TableRow;
                                 } else {
                                    //---------------------------------
                                    // Clone tr
                                    //---------------------------------
                                    $l_NewTableRow = $l_ClonedTableRow->cloneNode (true);
                                    $l_Row = $l_Tbody->appendChild ($l_NewTableRow);
                                 }

                                 //---------------------------------
                                 // Clone tr and set uuid
                                 //---------------------------------
                                 $l_NewRow = $l_Row->cloneNode (true);
                                 $l_Row->setAttribute ("data-uuid", $do_Object->GetID ());
                                 $l_Row->setAttribute ("data-display-field-value", $do_Object->Get ($l_Row->getAttribute ("data-display-field")));

                                 //---------------------------------
                                 // Loop through td
                                 //---------------------------------
                                 $l_Tds = $l_Row->getElementsByTagName ("td");

                                 foreach ($l_Tds as $l_Td) {
                                    //---------------------------------
                                    // Loop through input
                                    //---------------------------------
                                    $l_FieldName = $l_Td->getAttribute ("data-name");
                                    $l_FieldJson = $do_Object->GetFieldJson ($l_FieldName);
                                    switch ($l_FieldJson->type) {
                                    case "select":
                                       $l_Object = new StdClass;
                                       $l_Object->uuid = $l_FieldJson->optionuuid;
                                       $l_Object->uuiddatainstance = $do_Object->Get ($l_FieldName);
                                       $l_Object->optionstring = GetOptionString ($l_Object->uuid);

                                       $do_Options = new DataObject ($l_Object);
                                       if ($do_Options->GetNumberOfRows () == 1) {
                                          $do_Options->FirstRecord ();
                                          $l_Td->nodeValue = htmlspecialchars ($do_Options->Get ($l_Object->optionstring), ENT_QUOTES);
                                       } else {
                                          if ($l_Object->uuiddatainstance != "") {
                                             $l_Td->nodeValue = "- broken -";
                                          } else {
                                             $l_Td->nodeValue = "";
                                          }
                                       }
                                       break;
                                    default:
                                       if ($l_Td->getAttribute ("data-role")) {
                                          list ($l_LinkType, $l_LinkRole, $l_LinkDataObject, $l_LinkFieldName, $l_Separator) = explode (":", $l_Td->getAttribute ("data-role"));

                                          if ($l_LinkRole == "parent") {
                                             $l_FieldValueObject = new StdClass;
                                             $l_FieldValueObject->uuidchild = $l_DataObjectUuid;
                                             $l_FieldValueObject->uuiddatainstancechild = $do_Object->GetID ();
                                             $l_FieldValueObject->uuid = $l_LinkDataObject;
                                             $l_FieldValueObject->uuiddatainstance = "";
                                             $l_FieldValueObject->sortfield = $l_FieldName;
                                             if (!isset ($l_Separator)) {
                                                $l_Separator = "\n";
                                             }
                                             $l_Td->nodeValue = GetFieldValueViaLink ($l_FieldValueObject, $l_LinkFieldName, $l_Separator);
                                          }
                                          if ($l_LinkRole == "child") {
                                             $l_FieldValueObject = new StdClass;
                                             $l_FieldValueObject->uuidparent = $l_DataObjectUuid;
                                             $l_FieldValueObject->uuiddatainstanceparent = $do_Object->GetID ();
                                             $l_FieldValueObject->uuid = $l_LinkDataObject;
                                             $l_FieldValueObject->uuiddatainstance = "";
                                             $l_FieldValueObject->sortfield = $l_FieldName;
                                             if (!isset ($l_Separator)) {
                                                $l_Separator = "\n";
                                             }
                                             $l_Td->nodeValue = GetFieldValueViaLink ($l_FieldValueObject, $l_LinkFieldName, $l_Separator);
                                          }
                                       } else {
                                          $l_Json = $do_Object->GetFieldJson ($l_FieldName);
                                          $l_Json->showValue = $do_Object->Get ($l_FieldName);

                                          switch ($l_FieldJson->type) {
                                          case "checkbox":
                                             // Span to help datatables to be able to sort
                                             $l_Span = $l_Document->createElement ("span", "");
                                             $l_Span->setAttribute ("class", "hidden");
                                             $l_Span->nodeValue = FormatString ($l_Json);
                                             $l_Td->appendChild ($l_Span);

                                             // I to show an icon for the value
                                             $l_I = $l_Document->createElement ("i", "");
                                             if ($l_Json->showValue == "false") {
                                                $l_I->setAttribute ("class", "fa fa-fw fa-square-o");
                                             } else {
                                                $l_I->setAttribute ("class", "fa fa-fw fa-check-square-o");
                                             }
                                             $l_Td->appendChild ($l_I);
                                             $l_Td->setAttribute ("data-sort", FormatString ($l_Json));
                                             break;
                                          default:
                                             if ($l_Td->getAttribute ("data-math-row")) {
                                                $l_MathFields = preg_split("/[\+\-\*\/]/i", $l_Td->getAttribute ("data-math-rule"), -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
                                                foreach ($l_MathFields as $l_MathFieldIndex => $l_MathFieldsElement) {
                                                   $l_ReplaceValues[$l_MathFieldIndex] = (float)$do_Object->Get (str_replace (array ("{", "}", " "), "", $l_MathFields[$l_MathFieldIndex]));
                                                }
                                                $l_Json->showValue = CalculateFromString (str_replace ($l_MathFields, $l_ReplaceValues, $l_Td->getAttribute ("data-math-rule")));
                                                $l_Json->numberofdecimals = $l_Td->getAttribute ("data-math-number-of-decimals");
                                                $l_Json->type = "number";
                                                if ($l_Td->getAttribute ("data-format")) {
                                                   $l_Json->format = $l_Td->getAttribute ("data-format");
                                                }
                                             }

                                             $l_Td->nodeValue = FormatString ($l_Json);
                                             $l_Td->setAttribute ("data-sort", $l_Json->showValue);
                                          }
                                          if ($l_Td->getAttribute ("data-math-column")) {
                                             $l_Total[$l_FieldName] += (float)$l_Json->showValue;
                                          }
                                       }
                                    }
                                 }
                              } while ($do_Object->NextRecord ());
                           }
//printf ("Time after loop: %d \n<br>", time() - $l_StartTime);

                           break;
                        }
                     }
                  }
               }
               break;
            }
         }
      }
   }

   //---------------------------------
   // Get edited html
   //---------------------------------
   return ($l_Document->saveHTML());
}

function CalculateFromString($i_String)
{
   $l_Function = create_function("", "return (" . $i_String . ");" );
   return ((float)$l_Function ());
}


function GetFieldValueViaLink($i_Object, $i_LinkFieldName, $i_Separator = "\n")
{
   $l_String =  "";

   $do_Object = new DataObject ($i_Object);
   if ($do_Object->GetNumberOfRows () > 0) {
      $do_Object->FirstRecord ();
      do {
         $l_FieldJson = $do_Object->GetFieldJson ($i_LinkFieldName);
         switch ($l_FieldJson->type) {
         case "select":
            $l_Object = new StdClass;
            $l_Object->uuid = $l_FieldJson->optionuuid;
            $l_Object->uuiddatainstance = $do_Object->Get ($i_LinkFieldName);
            $l_Object->optionstring = GetOptionString ($l_Object->uuid);

            $do_Options = new DataObject ($l_Object);
            if ($do_Options->GetNumberOfRows () == 1) {
               $do_Options->FirstRecord ();
               $l_FieldValue = $do_Options->Get ($l_Object->optionstring);
            }
            break;
         default:
            $l_Json->showValue = $do_Object->Get ($i_LinkFieldName);
            $l_FieldValue = FormatString ($l_Json);
            break;
         }
         if ($l_String !=  "") {
            $l_String .= $i_Separator;
         }
         $l_String .=  $l_FieldValue;
      } while ($do_Object->NextRecord ());
   }

   return ($l_String);
}

function GetMailSetup($i_Object)
{
   $l_MailSetup = new StdClass;
   $l_MailSetup->uuid = "object_mail_setup";
   if (isset ($i_Object->uuiddatainstance)) {
      list ($i_Object->uuiddatainstance, $l_MailSetup->uuiddatainstance) = explode (":", $i_Object->uuiddatainstance);
   }

   $l_MailSetup->filterfield = new StdClass;
   foreach ($i_Object->filterfield as $l_FilterFieldName => $l_FilterValues) {
      $l_MailSetup->filterfield->{$l_FilterFieldName} = new StdClass;
      $l_MailSetup->filterfield->{$l_FilterFieldName}->value = $l_FilterValues->value;
   }

   $do_MailSetup = new DataObject ($l_MailSetup);
   $do_MailSetup->FirstRecord ();
   if ($do_MailSetup->GetNumberOfRows () > 0) {
      $i_Object->object = "object_mail";
      $i_Object->mail_imap = (strtolower($do_MailSetup->Get ("mail_setup_type")) == "imap")?true:false;
      $i_Object->mail_host = $do_MailSetup->Get ("mail_setup_host");
      $i_Object->mail_port = $do_MailSetup->Get ("mail_setup_port");
      $i_Object->mail_username = $do_MailSetup->Get ("mail_setup_username");
      $i_Object->mail_password = $do_MailSetup->Get ("mail_setup_password");
      $i_Object->mail_folder = $do_MailSetup->Get ("mail_setup_folder");
      $i_Object->mail_ssl = ($do_MailSetup->Get ("mail_setup_folder") == "true")?true:false;
      $i_Object->uuidmailsetup = $do_MailSetup->GetID ();
      unset ($i_Object->filterfield);
   }

   return ($i_Object);
}

function GetSoapSetup($i_Object)
{
   $l_SoapSetup = new StdClass;
   $l_SoapSetup->uuid = "object_soap_setup";
   if (isset ($i_Object->uuiddatainstance)) {
      list ($i_Object->uuiddatainstance, $l_SoapSetup->uuiddatainstance) = explode (":", $i_Object->uuiddatainstance);
   }

   $l_SoapSetup->filterfield = new StdClass;
   foreach ($i_Object->filterfield as $l_FilterFieldName => $l_FilterValues) {
      $l_SoapSetup->filterfield->{$l_FilterFieldName} = new StdClass;
      $l_SoapSetup->filterfield->{$l_FilterFieldName}->value = $l_FilterValues->value;
   }
   $do_SoapSetup = new DataObject ($l_SoapSetup);
   $do_SoapSetup->FirstRecord ();
   if ($do_SoapSetup->GetNumberOfRows () > 0) {
      $i_Object->object = "object_soap_cash";
      $i_Object->soap_app = "cash";
      $i_Object->soap_wsdl = $do_SoapSetup->Get ("soap_setup_wsdl");
      $i_Object->soap_identity = $do_SoapSetup->Get ("soap_setup_identity");
      $i_Object->soap_username = $do_SoapSetup->Get ("soap_setup_username");
      $i_Object->soap_password = $do_SoapSetup->Get ("soap_setup_password");
      $i_Object->soap_administration = $do_SoapSetup->Get ("soap_setup_administration");
      $i_Object->uuidsoapsetup = $do_SoapSetup->GetID ();
      unset ($i_Object->filterfield);
   }

   return ($i_Object);
}

function ProcessSubmittedJson($i_Json)
{
//   WriteJSON ("json/PostObject.json", $i_Json);
//   $i_Json = GetJSON ("json/PostObject.json");
   foreach ($i_Json as $l_PanelObjectUuid => $l_Panel) { // Panel
      if ( $l_PanelObjectUuid == "_post") {
      } else {
         foreach ($l_Panel as $l_DataObjectUuid => $l_DataObject) { // Object
            ProcessSubmittedData ($l_DataObject, $l_DataObjectUuid);
         }
      }
   }
}
?>
