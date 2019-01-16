<?php
#-------------------------------------------------------------------------------------
class DataObject {
#-------------------------------------------------------------------------------------
   private $p_DataObject;
   public $p_DataObjectField;
   private $p_DataInstance;
   private $p_Index;
   private $p_Key;
   private $p_DataExtension;
   private $p_ErrNo;

   function __construct($i_Object) {
   #-------------------------------------------------------------------------------------
   # CONSTRUCTOR
   #-------------------------------------------------------------------------------------
      $c_EscapeString = new General ();

      $p_DataObject = new StdClass;
      $this->p_DataObjectField = Array();
      $this->p_DataInstance = Array();
      $this->p_ErrNo = 0;

      #-----------------------------------------------
      # EscapeString
      #-----------------------------------------------
      if (isset ($i_Object->dataextension)) {
         $p_DataExtension = $c_EscapeString->EscapeString ($i_Object->dataextension);
      } else {
         $p_DataExtension = $_SESSION["DataExtension"];
      }

      if (isset ($i_Object->uuid) && $i_Object->uuid != "") {
         $i_Object->uuid = $c_EscapeString->EscapeString ($i_Object->uuid);
      }
      if (isset ($i_Object->uuiddatainstance) && $i_Object->uuiddatainstance != "") {
         $i_Object->uuiddatainstance = $c_EscapeString->EscapeString ($i_Object->uuiddatainstance);
      } else {
         $i_Object->uuiddatainstance = "";
      }

      if (isset ($i_Object->uuidparent) && $i_Object->uuidparent != "") {
         $i_Object->uuidparent = $c_EscapeString->EscapeString ($i_Object->uuidparent);
      }
      if (isset ($i_Object->uuiddatainstanceparent) && $i_Object->uuiddatainstanceparent != "") {
         $i_Object->uuiddatainstanceparent = $c_EscapeString->EscapeString ($i_Object->uuiddatainstanceparent);
      }

      if (isset ($i_Object->uuidchild) && $i_Object->uuidchild != "") {
         $i_Object->uuidchild = $c_EscapeString->EscapeString ($i_Object->uuidchild);
      }
      if (isset ($i_Object->uuiddatainstancechild) && $i_Object->uuiddatainstancechild != "") {
         $i_Object->uuiddatainstancechild = $c_EscapeString->EscapeString ($i_Object->uuiddatainstancechild);
      }

      #-----------------------------------------------
      # DataObject
      #-----------------------------------------------
      $c_DataObject = new General ();
      $c_DataObject->SetQuery ("select    DataObject.Uuid as ObjectUuid,
                                          DataObject.TypeName as ObjectType,
                                          DataObject.Json as ObjectJson
                                from      [App].DataObject
                                where     DataObject.Uuid = '$i_Object->uuid'");

      $c_DataObject->DoQuery ();
      if ($c_DataObject->GetNumberOfRows() == 1) {
         #-----------------------------------------------
         # DataObject attributes
         #-----------------------------------------------
         $p_DataObject->uuid = $c_DataObject->Get ("ObjectUuid");
         $p_DataObject->type = $c_DataObject->Get ("ObjectType");
         $p_DataObject->json = json_decode ($c_DataObject->Get ("ObjectJson"));
         $p_DataObject->class = $p_DataObject->json->class;
      }
      $c_DataObject->FreeResult();

      #-----------------------------------------------
      # DataObjectField
      #-----------------------------------------------
      $c_DataObjectField = new General ();
      $c_DataObjectField->SetQuery ("select    DataObjectField.Uuid as ObjectFieldUuid,
                                               DataObjectField.UuidDataField as DataFieldUuid,
                                               DataObjectField.Json as ObjectFieldJson,
                                               DataField.Json as DataFieldJson
                                     from      [App].DataObjectField,
                                               [App].DataObject,
                                               [App].DataField
                                     where     DataObject.Uuid = '$i_Object->uuid'
                                     and       DataObjectField.UuidDataObject = '$i_Object->uuid'
                                     and       DataField.Uuid = DataObjectField.UuidDataField
                                     order by  DataObjectField.Sequence");

      $c_DataObjectField->DoQuery ();
      if ($c_DataObjectField->GetNumberOfRows() > 0) {
         $p_DataObject->fieldcount = $c_DataObjectField->GetNumberOfRows ();
         do {
            $p_Index = $c_DataObjectField->Get ("ObjectFieldUuid");
            #-----------------------------------------------
            # DataObjectField attributes
            #-----------------------------------------------
            $this->p_DataObjectField[$p_Index] = new StdClass;
            $this->p_DataObjectField[$p_Index]->objectfield = new StdClass;
            $this->p_DataObjectField[$p_Index]->objectfield->uuid = $c_DataObjectField->Get ("ObjectFieldUuid");
            $this->p_DataObjectField[$p_Index]->objectfield->json = json_decode ($c_DataObjectField->Get ("ObjectFieldJson"));
            $this->p_DataObjectField[$p_Index]->datafield = new StdClass;
            $this->p_DataObjectField[$p_Index]->datafield->uuid = $c_DataObjectField->Get ("DataFieldUuid");
            $this->p_DataObjectField[$p_Index]->datafield->json = json_decode ($c_DataObjectField->Get ("DataFieldJson"));
            $this->p_DataObjectField[$p_Index]->equalized= new StdClass;
            $this->p_DataObjectField[$p_Index]->equalized->json = $this->EqualizeJson ($this->p_DataObjectField[$p_Index]->datafield->json, $this->p_DataObjectField[$p_Index]->objectfield->json);
         } while ($c_DataObjectField->NextRecord ());
      }
      $c_DataObjectField->FreeResult();

      #-----------------------------------------------
      # DataInstance
      #-----------------------------------------------
      if (isset ($i_Object->mail_imap)) {
         require_once ("mailobject.php");
         $c_DataInstance = new MailObject ($i_Object);
      } elseif (isset ($i_Object->soap_app)) {
         require_once ("soapobject.php");
         $c_DataInstance = new SoapObject ($i_Object, $this->p_DataObjectField);
      } elseif (isset ($i_Object->uuidparent)) {
         $c_DataInstance = new General ();
         $c_DataInstance->SetQuery ("select    DataInstance$p_DataExtension.Uuid as InstanceUuid,
                                               DataInstance$p_DataExtension.Json as InstanceJson
                                     from      [App].DataInstance$p_DataExtension,
                                               [App].DataObject,
                                               [App].DataLink$p_DataExtension
                                     where     DataObject.Uuid = '$i_Object->uuidparent'
                                     and       DataLink$p_DataExtension.UuidDataObject1 = '$i_Object->uuidparent'
                                     and       if('$i_Object->uuiddatainstanceparent'='',1,DataLink$p_DataExtension.UuidDataInstance1 = '$i_Object->uuiddatainstanceparent')
                                     and       DataLink$p_DataExtension.UuidDataObject2 = '$i_Object->uuid'
                                     and       DataInstance$p_DataExtension.Uuid = DataLink$p_DataExtension.UuidDataInstance2");
      } elseif (isset ($i_Object->uuidchild)) {
         $c_DataInstance = new General ();
         $c_DataInstance->SetQuery ("select    DataInstance$p_DataExtension.Uuid as InstanceUuid,
                                               DataInstance$p_DataExtension.Json as InstanceJson
                                     from      [App].DataInstance$p_DataExtension,
                                               [App].DataObject,
                                               [App].DataLink$p_DataExtension
                                     where     DataObject.Uuid = '$i_Object->uuid'
                                     and       DataLink$p_DataExtension.UuidDataObject2 = '$i_Object->uuidchild'
                                     and       if('$i_Object->uuiddatainstancechild'='',1,DataLink$p_DataExtension.UuidDataInstance2 = '$i_Object->uuiddatainstancechild')
                                     and       DataLink$p_DataExtension.UuidDataObject1 = '$i_Object->uuid'
                                     and       DataInstance$p_DataExtension.Uuid = DataLink$p_DataExtension.UuidDataInstance1");
      } else {
         if (isset ($i_Object->filterstring)) {
            $l_And = sprintf (" %s ", $i_Object->filterstring);
         } else {
            $l_And = "";
         }
         if (isset ($i_Object->row_limit)) {
            $l_Limit = sprintf ("\nlimit %d", $i_Object->row_limit);
         } else {
            $l_Limit = "";
         }
         if (isset ($i_Object->offset)) {
            $l_Offset = sprintf ("\noffset %d", $i_Object->offset);
         } else {
            $l_Offset = "";
         }

         if (isset ($i_Object->freesearchpattern)) {
           $p_FreeSearchPattern = $c_EscapeString->EscapeString ($i_Object->freesearchpattern);

           $c_DataInstance = new General ();
           $c_DataInstance->DontDie ();
           $c_DataInstance->SetQuery ("select    DataInstance$p_DataExtension.Uuid as InstanceUuid,
                                                 DataInstance$p_DataExtension.Json as InstanceJson,
                                                 match (DataInstance$p_DataExtension.FreeText) against ('$p_FreeSearchPattern' in boolean mode) as Relevance
                                       from      [App].DataInstance$p_DataExtension,
                                                 [App].DataObject
                                       where     DataObject.Uuid = '$i_Object->uuid'
                                       and       DataInstance$p_DataExtension.UuidDataObject = '$i_Object->uuid'
                                       and       match (DataInstance$p_DataExtension.FreeText) against ('$p_FreeSearchPattern' in boolean mode)
                                       $l_And $l_Limit $l_Offset
                                       order by  Relevance desc");
         } else {
           $c_DataInstance = new General ();
           $c_DataInstance->SetQuery ("select    DataInstance$p_DataExtension.Uuid as InstanceUuid,
                                                 DataInstance$p_DataExtension.Json as InstanceJson,
                                                 1 as Relevance
                                       from      [App].DataInstance$p_DataExtension,
                                                 [App].DataObject
                                       where     DataObject.Uuid = '$i_Object->uuid'
                                       and       DataInstance$p_DataExtension.UuidDataObject = '$i_Object->uuid'
                                       and       if('$i_Object->uuiddatainstance'='',1,DataInstance$p_DataExtension.Uuid = '$i_Object->uuiddatainstance')
                                       $l_And $l_Limit $l_Offset");
         }
      }

      $c_DataInstance->DoQuery ();
      switch ($c_DataInstance->GetError ()) {
      case 1064: // parse error
         $this->p_ErrNo = $c_DataInstance->GetError ();
         return (false);
      }

      if ($c_DataInstance->GetNumberOfRows () == 0) { // no records found
         if ($i_Object->action == "new") { // new record
            $p_Index = $i_Object->uuiddatainstance;
            $this->p_DataInstance[$p_Index] = new StdClass;
            $this->p_DataInstance[$p_Index]->dataset = $i_Object->uuid;
            $this->p_DataInstance[$p_Index]->uuid = $i_Object->uuiddatainstance;

            foreach ($this->p_DataObjectField as $p_FieldName => $p_Field) {
               $this->p_DataInstance[$p_Index]->{$p_FieldName} = new StdClass;
               $this->p_DataInstance[$p_Index]->{$p_FieldName}->name = $p_Field->equalized->json->name;
               $this->p_DataInstance[$p_Index]->{$p_FieldName}->type = $p_Field->equalized->json->type;
               $this->p_DataInstance[$p_Index]->{$p_FieldName}->maxlength = (isset ($p_Field->equalized->json->maxlength))? $p_Field->equalized->json->maxlength: "";

               $this->p_DataInstance[$p_Index]->{$p_FieldName}->dbvalue = null;
               $this->p_DataInstance[$p_Index]->{$p_FieldName}->undefined = true;
            }
         }
      } else {
         $c_DataInstance->FirstRecord ();
         do {
            $p_Index = $c_DataInstance->Get ("InstanceUuid");
            $this->p_DataInstance[$p_Index] = new StdClass;
            $this->p_DataInstance[$p_Index]->dataset = $i_Object->uuid;
            $this->p_DataInstance[$p_Index]->uuid = $c_DataInstance->Get ("InstanceUuid");
            $this->p_DataInstance[$p_Index]->relevance = $c_DataInstance->Get ("Relevance");

            $p_Json = json_decode ($c_DataInstance->Get ("InstanceJson"));

            #-----------------------------------------------------------------------
            # Loop the object data fields and locate the value in the data instance
            #-----------------------------------------------------------------------
            foreach ($this->p_DataObjectField as $p_FieldName => $p_Field) {
               $this->p_DataInstance[$p_Index]->{$p_FieldName} = new StdClass;
               $this->p_DataInstance[$p_Index]->{$p_FieldName}->name = $p_Field->equalized->json->name;
               $this->p_DataInstance[$p_Index]->{$p_FieldName}->type = $p_Field->equalized->json->type;
               $this->p_DataInstance[$p_Index]->{$p_FieldName}->maxlength = (isset ($p_Field->equalized->json->maxlength))? $p_Field->equalized->json->maxlength: "";

               if (isset ($p_Json->{$i_Object->uuid}->{$p_FieldName})) {
                  if ($p_Json->{$i_Object->uuid}->{$p_FieldName} == null) {
                     $this->p_DataInstance[$p_Index]->{$p_FieldName}->dbvalue = null;
                  } else {
                     $this->p_DataInstance[$p_Index]->{$p_FieldName}->dbvalue = $p_Json->{$i_Object->uuid}->{$p_FieldName};
                  }
               } else {
                  $this->p_DataInstance[$p_Index]->{$p_FieldName}->dbvalue = null;
                  $this->p_DataInstance[$p_Index]->{$p_FieldName}->undefined = true;
               }
            }

            #-----------------------------------------------------------------------
            # Filter out records
            #-----------------------------------------------------------------------
            if (isset ($i_Object->filterfield)) {
               foreach ($i_Object->filterfield as $flt_FieldName => $flt_Filter) {
                  $l_Valid = false;
                  foreach ($flt_Filter->value as $flt_FilterValue) {
                     if ($this->p_DataInstance[$p_Index]->{$flt_FieldName}->dbvalue == $flt_FilterValue) {
                        $l_Valid = true;
                     }
                  }
                  if ($l_Valid == false) {
                     # Remove record from result set
                     unset ($this->p_DataInstance[$p_Index]);
                  }
               }
            }
         } while ($c_DataInstance->NextRecord ());
      }
      $c_DataInstance->FreeResult();

      #-----------------------------------------------------------------------
      # Sort result set
      #-----------------------------------------------------------------------
      if (isset ($i_Object->sortfield)) {
         if (isset ($i_Object->sortorder)) {
            $this->SortResultJson ($i_Object->sortfield, $i_Object->sortorder);
         } else {
            $this->SortResultJson ($i_Object->sortfield);
         }
      }
   }

   #-------------------------------------------------------------------------------------
   private function EqualizeJson($i_Parent, $i_Child)
   #-------------------------------------------------------------------------------------
   {
      foreach ($i_Parent as $l_Key => $l_Value) {
         if (is_object ($l_Value)) {
            if (!isset ($i_Child->{$l_Key})) {
               $i_Child->{$l_Key} = new StdClass;
               $this->EqualizeJson ($i_Parent->{$l_Key}, $i_Child->{$l_Key});
            }
         } else {
            if (!isset ($i_Child->{$l_Key})) {
               $i_Child->{$l_Key} = $i_Parent->{$l_Key};
            }
         }
      }
      return ($i_Child);
   }

   #-------------------------------------------------------------------------------------
   function FirstRecord() {
   #-------------------------------------------------------------------------------------
      if (reset ($this->p_DataInstance) === false) {
         $l_Return = false;
      } else {
         $this->p_Key = key ($this->p_DataInstance);
         $l_Return = true;
      }
      return ($l_Return);
   }

   #-------------------------------------------------------------------------------------
   function NextRecord() {
   #-------------------------------------------------------------------------------------
      if (next ($this->p_DataInstance) === false) {
         $l_Return = false;
      } else {
         $this->p_Key = key ($this->p_DataInstance);
         $l_Return = true;
      }
      return ($l_Return);
   }

   #-------------------------------------------------------------------------------------
   function PreviousRecord() {
   #-------------------------------------------------------------------------------------
      if (prev ($this->p_DataInstance) === false) {
         $l_Return = false;
      } else {
         $this->p_Key = key ($this->p_DataInstance);
         $l_Return = true;
      }
      return ($l_Return);
   }

   #-------------------------------------------------------------------------------------
   function LastRecord() {
   #-------------------------------------------------------------------------------------
      if (end ($this->p_DataInstance) === false) {
         $l_Return = false;
      } else {
         $this->p_Key = key ($this->p_DataInstance);
         $l_Return = true;
      }
      return ($l_Return);
   }

   #-------------------------------------------------------------------------------------
   function GetCurrentRow() {
   #-------------------------------------------------------------------------------------
      return ($this->p_Key);
   }

   #-------------------------------------------------------------------------------------
   function GetErrNo() {
   #-------------------------------------------------------------------------------------
      return ($this->p_ErrNo);
   }

   #-------------------------------------------------------------------------------------
   function Get($i_Field) {
   #-------------------------------------------------------------------------------------
      return ($this->p_DataInstance[$this->p_Key]->{$i_Field}->dbvalue);
   }

   #-------------------------------------------------------------------------------------
   function Set($i_Field, $i_Value) {
   #-------------------------------------------------------------------------------------
      $this->p_DataInstance[$this->p_Key]->{$i_Field}->newvalue = $i_Value;
      unset ($this->p_DataInstance[$this->p_Key]->{$i_Field}->undefined);
   }

   #-------------------------------------------------------------------------------------
   function GetID() {
   #-------------------------------------------------------------------------------------
      return ($this->p_Key);
   }

   #-------------------------------------------------------------------------------------
   function SetID($i_Uuid) {
   #-------------------------------------------------------------------------------------
      $this->p_Key = $i_Uuid;
   }

   #-------------------------------------------------------------------------------------
   function GetRelevance() {
   #-------------------------------------------------------------------------------------
      return ($this->p_DataInstance[$this->p_Key]->relevance);
   }

   #-------------------------------------------------------------------------------------
   function IsField($i_Field) {
   #-------------------------------------------------------------------------------------
      return (isset ($this->p_DataInstance[$this->p_Key]->{$i_Field}));
   }

   #-------------------------------------------------------------------------------------
   function GetNumberOfRows() {
   #-------------------------------------------------------------------------------------
      return (count ($this->p_DataInstance));
   }

   #-------------------------------------------------------------------------------------
   function GetFieldJson($i_Field) {
   #-------------------------------------------------------------------------------------
      return ($this->p_DataObjectField[$i_Field]->equalized->json);
   }

   #-------------------------------------------------------------------------------------
   function GetFields() {
   #-------------------------------------------------------------------------------------
      $l_Fields = Array();
      foreach ($this->p_DataObjectField as $l_FieldName => $l_Json) {
         $l_Fields[$l_FieldName] = $this->GetFieldJson ($l_FieldName);
      }
      return ($l_Fields);
   }

   #-------------------------------------------------------------------------------------
   function GetResultJson() {
   #-------------------------------------------------------------------------------------
      return ($this->p_DataInstance);
   }

   #-------------------------------------------------------------------------------------
   function SortResultJson($i_Field, $i_SortOrder = "") {
   #-------------------------------------------------------------------------------------
      if ($i_SortOrder == "desc") {
         uasort ($this->p_DataInstance, function($i_Left, $i_Right) use ($i_Field) {
            if ($i_Left->{$i_Field}->type == "number") {
               return ($i_Left->{$i_Field}->dbvalue < $i_Right->{$i_Field}->dbvalue) ? 1 : -1;
            } else {
               return -1 * intval(strcasecmp ($i_Left->{$i_Field}->dbvalue, $i_Right->{$i_Field}->dbvalue));
            }
         });
      } else {
         uasort ($this->p_DataInstance, function($i_Left, $i_Right) use ($i_Field) {
            if ($i_Left->{$i_Field}->type == "number") {
               return ($i_Left->{$i_Field}->dbvalue < $i_Right->{$i_Field}->dbvalue) ? -1 : 1;
            } else {
               return strcasecmp ($i_Left->{$i_Field}->dbvalue, $i_Right->{$i_Field}->dbvalue);
            }
         });
      }
   }

   #-------------------------------------------------------------------------------------
   function SaveRecord() {
   #-------------------------------------------------------------------------------------
      if (isset ($this->p_Key)) {
         $l_DataObjectUuid = $this->p_DataInstance[$this->p_Key]->dataset;
         $l_Uuid = $this->p_DataInstance[$this->p_Key]->uuid;

         $l_InstanceObject = new StdClass;
         $l_InstanceObject->{$l_DataObjectUuid} = new StdClass;
         $l_InstanceObject->{$l_DataObjectUuid}->uuid = $l_Uuid;

         foreach ($this->p_DataInstance[$this->p_Key] as $p_FieldName => $p_Field) {
            if (isset($p_Field->newvalue)) {
               $l_InstanceObject->{$l_DataObjectUuid}->{$p_FieldName} = $p_Field->newvalue; // take new value
            } else {
               $l_InstanceObject->{$l_DataObjectUuid}->{$p_FieldName} = $p_Field->dbvalue; // keep DB value
            }
         }

         $l_Instance = new StdClass;
         $l_Instance->uuid = $l_Uuid;
         $l_Instance->uuiddataobject = $l_DataObjectUuid;
         $l_Instance->json = json_encode ($l_InstanceObject, JSON_PRETTY_PRINT);
         SaveDataInstance ($l_Instance);
      }
   }
}
?>
