<?php
   //----------------------------------------------------------------
   // Receive Uploaded Files (FileReceive.php)
   //----------------------------------------------------------------
   @require_once ("Include.php");
   @require_once ("lib/DB.inc");
   @require_once ("page/functions.php");

   //----------------------------------------------------------------
   // Environment parameters
   //----------------------------------------------------------------
   $_CustomerID_ = intval($_SESSION["_CustomerID_"]);
   $_ApplicationID_ = intval($_SESSION["_ApplicationID_"]);
   $_LanguageID_ = intval($_SESSION["_LanguageID_"]);
   $_UserID_ = intval($_SESSION["_UserID_"]);

   //----------------------------------------------------------------
   // Overall access (not panel specific
   //----------------------------------------------------------------
   if (!AccessGranted ($_ApplicationID_, $_UserID_)) {
      AccessDenied ($_ApplicationID_, $_UserID_);
   }
   
   //----------------------------------------------------------------
   // 5 minutes execution time
   //----------------------------------------------------------------
   @set_time_limit (5 * 60);

   //----------------------------------------------------------------
   // Request parameters
   //----------------------------------------------------------------
//   print_r ($_REQUEST);

   $r_TabPageFieldID = intval ($_REQUEST["__TabPageFieldID"]);
   $r_RecordID = intval ($_REQUEST["__RecordID"]);
   $r_FileTypeID = intval ($_REQUEST["__FileTypeID"]);
   $r_DocumentTypeID = intval ($_REQUEST["__DocumentTypeID"]);
   $r_DateTimeString = $_REQUEST["__DateTime"];
   $r_FileReceiveID = intval ($_REQUEST["__FileReceiveID"]);

   $c_Query = new General ();

   //----------------------------------------------------------------
   // Get filename
   //----------------------------------------------------------------
//   print_r ($_FILES["__FileName"]);

   $l_TempFileName = $_FILES["__FileName"]["tmp_name"];
   $l_FileNameOriginal = $c_Query->EscapeString ($_FILES["__FileName"]["name"]);
   $l_FileSize = intval ($_FILES["__FileName"]["size"]);
   $l_FileType = $_FILES["__FileName"]["type"];

   //----------------------------------------------------------------
   // Settings
   //----------------------------------------------------------------
   $c_Query = new General ();
   $c_Query->SetQuery ("select   FileReceive.Location as Location,
                                 FileReceive.DatabaseName as DatabaseName,
                                 FileReceive.TableName as TableName
                        from     [App].FileReceive
                        where    FileReceive.ID = '$r_FileReceiveID'
                       ");
   
   if ($c_Query->DoQuery () == 1) {
      $l_Location = $c_Query->Get ("Location");
      $l_DatabaseName = $c_Query->Get ("DatabaseName");
      $l_TableName = $c_Query->Get ("TableName");
   }

   $l_BaseDir = getcwd ();
   $l_FinalDir = sprintf ("%s%s", $l_BaseDir, $l_Location);

//   printf ("<p>CurrentPWD: %s: %s</p>", getcwd (), $l_FinalDir);
   
   //----------------------------------------------------------------
   // Directory / Files operations
   //----------------------------------------------------------------

   // Create Final dir
   if (!file_exists ($l_FinalDir)) {
      mkdir ($l_FinalDir);
   }

   //----------------------------------------------------------------
   // Moves the file from $targetDir to $finalDir
   //----------------------------------------------------------------
   if ($r_DocumentTypeID == 0) {
      $r_DocumentTypeID = 1;
   }

   $l_DateTime = sprintf ("%s %s", substr($r_DateTimeString, 0 ,10), substr($r_DateTimeString, 11 ,8));

   $c_Insert = new General ();
   $c_Insert->SetQuery ("insert
	   					    into     [App].FileLink
	   					            (DatabaseName,
                                  TableName,
                                  RecordID,
                                  FileReceiveID,
                                  FileTypeID,
                                  DocumentTypeID,
                                  FinalLocation,
                                  FileName,
                                  FileNameOriginal,
                                  FileSize,
                                  FileType,
                                  DateTimeString,
                                  DateTimeFileLastModified,
                                  DateTimeCreate)
                         values  ('$l_DatabaseName',
                                  '$l_TableName',
                                  '$r_RecordID',
                                  '$r_FileReceiveID',
                                  '$r_FileTypeID',
                                  '$r_DocumentTypeID',
                                  '$l_FinalDir',
                                  '000000000',
                                  '$l_FileNameOriginal',
                                  '$l_FileSize',
                                  '$l_FileType',
                                  '$r_DateTimeString',
                                  '$l_DateTime',
                                  now()
                                  )
					    ");

   $c_Insert->DoQuery (2);
   $l_ID = $c_Insert->Get ("ID");

   //----------------------------------------------------------------
   // Set filename
   //----------------------------------------------------------------
   $l_FileName = sprintf ("%09d", $l_ID);

   $c_Update = new General ();
   $c_Update->SetQuery ("update  [App].FileLink
                         set     FileName = '$l_FileName'
					      	 where   ID = '$l_ID'
      						");
   $c_Update->DoQuery (1);

   //----------------------------------------------------------------
   // Move file
   //----------------------------------------------------------------
   move_uploaded_file ("$l_TempFileName", "$l_FinalDir/$l_FileName");
   
   printf ("$l_FileName");
?>