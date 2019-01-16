<?php
//require_once ("functions_mail.php");

#------------------------------------------------------------------------------------- 
class SoapObject {
#------------------------------------------------------------------------------------- 
   private $p_SoapInstance;
   private $p_Index;
   private $p_Key;
   private $p_SoapClient;
   private $p_Object;
   private $p_DataObjectField;
   
   function SoapObject($i_Object, $i_DataObjectField) {
   #------------------------------------------------------------------------------------- 
   # CONSTRUCTOR                                                                          
   #------------------------------------------------------------------------------------- 
      ini_set('memory_limit','256M');

      $l_Ssl = array(
                  'ssl' => array("ciphers" => "SSLv3",
                                 "verify_peer" => false,
                                 "verify_peer_name" => false)
               );

      $this->p_Object = $i_Object;
      $this->p_DataObjectField = $i_DataObjectField;
      $this->p_SoapClient = new SoapClient ($this->p_Object->soap_wsdl, array ("encoding" => "UTF-8", "stream_context" => stream_context_create($l_Ssl)));
   }                                                                                      

   #-------------------------------------------------------------------------------------       
   function DoQuery() {
   #-------------------------------------------------------------------------------------
      switch ($this->p_Object->uuid) {
      case "object_soap_cash_admin":
      
         $l_SoapResult = $this->p_SoapClient->ListAdministrations ('', $this->p_Object->soap_identity, $this->p_Object->soap_username, $this->p_Object->soap_password);
         $l_XmlResult = simplexml_load_string($l_SoapResult["administrationList"]);
         $l_ResultJson = json_decode(json_encode($l_XmlResult, JSON_PRETTY_PRINT));

         if (is_array($l_ResultJson->Dir->Adms->Adm)) {  // more than 1 result
            foreach ($l_ResultJson->Dir->Adms->Adm as $l_Index => $l_Json) {
               $this->SetArray ($l_Json);
            }
         } else { // 1 result
            $this->SetArray ($l_ResultJson->Dir->Adms->Adm);
         }
         
         break;
      case "object_soap_cash_relation":
      case "object_soap_cash_relation_outstanding":
      case "object_soap_cash_ledger":
      case "object_soap_cash_ledger_booking":
         $l_Administration = array(
            'admCode' => $this->p_Object->soap_administration,
            'admMap' => ''
         );

         switch ($this->p_Object->uuid) {
         case "object_soap_cash_relation":
            $l_RequestName = "0101";
            $l_RecordName = "R0101";
            if ($this->p_Object->directfilter) {
               $l_RequestName .= sprintf ("|%06d", $this->p_Object->uuiddatainstance);
            }
            break;
         case "object_soap_cash_relation_outstanding":
            $l_RequestName = "0311T";
            $l_RecordName = "R0311";
            if ($this->p_Object->uuiddatainstanceparent) {
               $l_RequestName .= sprintf ("|%06d", $this->p_Object->uuiddatainstanceparent);
            }
            break;
         case "object_soap_cash_ledger":
            $l_RequestName = "0201";
            $l_RecordName = "R0201";
            break;
         case "object_soap_cash_ledger_booking":
            $l_RequestName = "0301";
            $l_RecordName = "R0301";
            break;
         }
                  
         $l_SoapResult = $this->p_SoapClient->Export ('', $this->p_Object->soap_identity, $this->p_Object->soap_username, $this->p_Object->soap_password, $l_RequestName, $l_Administration);
         $l_XmlResult = simplexml_load_string (str_replace (array("Content-type: text/xml "), array(""), $l_SoapResult["exportResult"]));
         $l_ResultJson = json_decode(json_encode($l_XmlResult, JSON_PRETTY_PRINT));


         if (is_array($l_ResultJson->{$l_RecordName})) {  // more than 1 result
            foreach ($l_ResultJson->{$l_RecordName} as $l_Index => $l_Json) {
               $this->SetArray ($l_Json);
            }
         } else { // 1 result
            $this->SetArray ($l_ResultJson->{$l_RecordName});
         }

         break;
      }

      return ($this->GetNumberOfRows ());
   }

   #-------------------------------------------------------------------------------------       
   private function SetArray($i_Json) {
   #-------------------------------------------------------------------------------------
      if (!isset ($this->p_SoapInstance)) {
         $this->p_SoapInstance = Array();
         $p_Index = 0;
      }

      switch ($this->p_Object->uuid) {
      case "object_soap_cash_admin":
      case "object_soap_cash_relation":
      case "object_soap_cash_relation_outstanding":
      case "object_soap_cash_ledger":
      case "object_soap_cash_ledger_booking":
         reset ($this->p_DataObjectField);
         $l_FieldName = key ($this->p_DataObjectField);
         if (isset ($this->p_DataObjectField[$l_FieldName]->equalized->json->tagname)) {
            $l_TagName = $this->p_DataObjectField[$l_FieldName]->equalized->json->tagname;
         } else {
            $l_TagName = $l_FieldName;
         }
         $p_Index = $i_Json->{$l_TagName};
            
         $this->p_SoapInstance[$p_Index] = new StdClass;
         $this->p_SoapInstance[$p_Index]->dataset = $this->p_Object->uuid;
         $this->p_SoapInstance[$p_Index]->uuid = sprintf("%s:%s", $p_Index, $this->p_Object->uuidsoapsetup);

         $this->p_SoapInstance[$p_Index]->json = new StdClass;
         $this->p_SoapInstance[$p_Index]->json->{$this->p_Object->uuid} = new StdClass;

         foreach ($this->p_DataObjectField as $l_FieldName => $l_Settings) {
            if (isset ($this->p_DataObjectField[$l_FieldName]->equalized->json->tagname)) {
               $l_TagName = $this->p_DataObjectField[$l_FieldName]->equalized->json->tagname;
            } else {
               $l_TagName = $l_FieldName;
            }
            switch ($this->p_DataObjectField[$l_FieldName]->equalized->json->type) {
            case "number":
               $i_Json->{$l_TagName} = str_replace (",", ".", $i_Json->{$l_TagName});
               break;
            case "date":
               if (isset ($i_Json->{$l_TagName})) {
                  $i_Json->{$l_TagName} = sprintf ("%s-%s-%s", substr ($i_Json->{$l_TagName}, 6, 4), substr ($i_Json->{$l_TagName}, 3, 2), substr ($i_Json->{$l_TagName}, 0, 2));
               }
               break;
            }
            $this->p_SoapInstance[$p_Index]->json->{$this->p_Object->uuid}->{$l_FieldName} = $i_Json->{$l_TagName};
         }
         break;
      }
   }
   
   #-------------------------------------------------------------------------------------
   function FirstRecord() {
   #-------------------------------------------------------------------------------------
      if (reset ($this->p_SoapInstance) === false) {
         $l_Return = false;
      } else {
         $this->p_Key = key ($this->p_SoapInstance);
         $l_Return = true;
      }
      return ($l_Return);
   }

   #-------------------------------------------------------------------------------------
   function NextRecord() {
   #-------------------------------------------------------------------------------------
      if (next ($this->p_SoapInstance) === false) {
         $l_Return = false;
      } else {
         $this->p_Key = key ($this->p_SoapInstance);
         $l_Return = true;
      }
      return ($l_Return);
   }

   #-------------------------------------------------------------------------------------
   function PreviousRecord() {
   #-------------------------------------------------------------------------------------
      if (prev ($this->p_SoapInstance) === false) {
         $l_Return = false;
      } else {
         $this->p_Key = key ($this->p_SoapInstance);
         $l_Return = true;
      }
      return ($l_Return);
   }

   #-------------------------------------------------------------------------------------
   function LastRecord() {
   #-------------------------------------------------------------------------------------
      if (end ($this->p_SoapInstance) === false) {
         $l_Return = false;
      } else {
         $this->p_Key = key ($this->p_SoapInstance);
         $l_Return = true;
      }
      return ($l_Return);
   }

   #-------------------------------------------------------------------------------------
   function Get($i_Field) {
   #-------------------------------------------------------------------------------------
      if ($i_Field == "InstanceUuid") {
         return ($this->p_SoapInstance[$this->p_Key]->uuid);
      }
      if ($i_Field == "InstanceJson") {
         return (json_encode ($this->p_SoapInstance[$this->p_Key]->json));
      }
   }

   #-------------------------------------------------------------------------------------
   function GetNumberOfRows() {
   #-------------------------------------------------------------------------------------
      return (count ($this->p_SoapInstance));
   }
   
   #-------------------------------------------------------------------------------------
   function FreeResult() {
   #-------------------------------------------------------------------------------------
      unset ($this->p_SoapInstance);
   }
}
?>