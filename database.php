<?php
#-------------------------------------------------------------------------------------
class General {
#-------------------------------------------------------------------------------------
   private $_Connection;

   function __construct() {
   #-------------------------------------------------------------------------------------
   # CONSTRUCTOR
   #-------------------------------------------------------------------------------------
      $this->_Connection = mysqli_connect (Db_Host, Db_User, Db_Password)
         or die ("<p><center>DB not available</center></p>");

      $this->_CurrentRow = 0;
      $this->_NumberOfRows = 0;
      $this->_AffectedRows = 0;
      $this->_InsertID = "ID";

      mysqli_set_charset ($this->_Connection , "utf8");

      return(true);
   }

   #-------------------------------------------------------------------------------------
   function SetConnection ($Link) {
   #-------------------------------------------------------------------------------------
      $this->_Connection = $Link;
   }

   #-------------------------------------------------------------------------------------
   function SetQuery($Query) {
   #-------------------------------------------------------------------------------------
      $this->_QueryString = $Query;
   }

   #-------------------------------------------------------------------------------------
   function GetQuery() {
   #-------------------------------------------------------------------------------------
      return ($this->_QueryString);
   }

   #-------------------------------------------------------------------------------------
   function GetError() {
   #-------------------------------------------------------------------------------------
      return (mysqli_errno ($this->_Connection));
   }

   #-------------------------------------------------------------------------------------
   function GetErrorString() {
   #-------------------------------------------------------------------------------------
      return (mysqli_error ($this->_Connection));
   }

   #-------------------------------------------------------------------------------------
   function DoQuery($NoCount = 0, $Real = 0) {
   #-------------------------------------------------------------------------------------
      $this->_QueryString = str_replace (array("[App]", "[Sys]", "'{null}'"), array(Db_App, Db_Sys, "null"), $this->_QueryString);

      if ($Real) {
         $this->Result = mySQLi_Real_Query($this->_Connection, $this->_QueryString);
      } else {
         $this->Result = mySQLi_Query($this->_Connection, $this->_QueryString);
      }

      if (!$this->Result){
         if (isset ($this->_DontDie) && $this->_DontDie) {
            return (-2);
         } else {
            die("Invalid query: [" . $this->_QueryString . "] resulted in :" . mysqli_error($this->_Connection));
         }
      }

      if ($Real) {
         $this->Result = mysqli_store_result ($this->_Connection);
      }

      switch ($NoCount) {
      case 0:
         $this->_NumberOfRows = mySQLi_Num_Rows($this->Result);
         $this->FirstRecord();
         $resultCode = $this->_NumberOfRows;
         break;
      case 1:
         $this->_AffectedRows = mysqli_affected_rows ($this->_Connection);
         $resultCode = $this->_AffectedRows;
         break;
      case 2:
         $this->Set ($this->_InsertID, mySQLi_Insert_Id($this->_Connection));
         $this->_AffectedRows = mysqli_affected_rows ($this->_Connection);
         $resultCode = $this->_AffectedRows;
         break;
      }
      return ($resultCode);
   }

   #-------------------------------------------------------------------------------------
   function FirstRecord() {
   #-------------------------------------------------------------------------------------
      $this->_CurrentRow = 0;
      $rc = false;

      if ($this->_NumberOfRows > 0) {
         if (mySQLi_Data_Seek ($this->Result, $this->_CurrentRow)) {
            $this->RowArray = mySQLi_Fetch_Array ($this->Result, MYSQLI_ASSOC);
            $rc = true;
         }
      }
      return ($rc);
   }

   #-------------------------------------------------------------------------------------
   function NextRecord() {
   #-------------------------------------------------------------------------------------
      $this->_CurrentRow++;
      $rc = false;

      if ($this->_NumberOfRows > 0 && $this->_CurrentRow < $this->_NumberOfRows) {
         if (mySQLi_Data_Seek ($this->Result, $this->_CurrentRow)) {
            $this->RowArray = mySQLi_Fetch_Array ($this->Result, MYSQLI_ASSOC);
            $rc = true;
         }
      }
      return ($rc);
   }

   #-------------------------------------------------------------------------------------
   function PreviousRecord() {
   #-------------------------------------------------------------------------------------
      $this->_CurrentRow--;
      $rc = false;

      if ($this->_NumberOfRows > 0 && $this->_CurrentRow > 0) {
         if (mySQLi_Data_Seek ($this->Result, $this->_CurrentRow)) {
            $this->RowArray = mySQLi_Fetch_Array ($this->Result, MYSQLI_ASSOC);
            $rc = true;
         }
      }
      return ($rc);
   }

   #-------------------------------------------------------------------------------------
   function LastRecord() {
   #-------------------------------------------------------------------------------------
      $this->_CurrentRow = $this->_NumberOfRows - 1;
      $rc = false;

      if ($this->_NumberOfRows > 0) {
         if (mySQLi_Data_Seek ($this->Result, $this->_CurrentRow)) {
            $this->RowArray = mySQLi_Fetch_Array ($this->Result, MYSQLI_ASSOC);
            $rc = true;
         }
      }
      return ($rc);
   }

   #-------------------------------------------------------------------------------------
   function GetCurrentRow() {
   #-------------------------------------------------------------------------------------
      return ($this->_CurrentRow);
   }

   #-------------------------------------------------------------------------------------
   function Get($Key) {
   #-------------------------------------------------------------------------------------
      return($this->RowArray[$Key]);
   }

   #-------------------------------------------------------------------------------------
   function Set($Key, $Value) {
   #-------------------------------------------------------------------------------------
      $this->RowArray[$Key] = $Value;
   }

   #-------------------------------------------------------------------------------------
   function EscapeString($i_String) {
   #-------------------------------------------------------------------------------------
      return (mysqli_real_escape_string ($this->_Connection , $i_String));
   }

   #-------------------------------------------------------------------------------------
   function GetCallString() {
   #-------------------------------------------------------------------------------------
      return ($this->_CallString);
   }

   #-------------------------------------------------------------------------------------
   function SetInsertID($i_InsertID) {
   #-------------------------------------------------------------------------------------
      $this->_InsertID = $i_InsertID;
   }

   #-------------------------------------------------------------------------------------
   function GetInsertID() {
   #-------------------------------------------------------------------------------------
      return ($this->Get ($this->_InsertID));
   }

   #-------------------------------------------------------------------------------------
   function GetNumberOfRows() {
   #-------------------------------------------------------------------------------------
      return ($this->_NumberOfRows);
   }

   #-------------------------------------------------------------------------------------
   function GetAffectedRows() {
   #-------------------------------------------------------------------------------------
      return ($this->_AffectedRows);
   }

   #-------------------------------------------------------------------------------------
   function FreeResult() {
   #-------------------------------------------------------------------------------------
      mysqli_free_result ($this->Result);
   }

   #-------------------------------------------------------------------------------------
   function DontDie($i_DontDie = true) {
   #-------------------------------------------------------------------------------------
      $this->_DontDie = $i_DontDie;
   }

   #-------------------------------------------------------------------------------------
   function Close() {
   #-------------------------------------------------------------------------------------
      mysqli_close ($this->_Connection);
   }
}
?>
