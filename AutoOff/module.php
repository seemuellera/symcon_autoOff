<?php

// Klassendefinition
class AutoOff extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","AutoOff");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyInteger("TargetStatusVariableId",0);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyString("TriggerVariables", "");

		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableBoolean("DetectionEnabled","Motion Detection Enabled","~Switch");
		$this->RegisterVariableInteger("LastTrigger","Last Trigger","~UnixTimestamp");
		$this->RegisterVariableInteger("Timeout","Timeout");

		// Default Actions
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'AUTOOFF_RefreshInformation($_IPS[\'TARGET\']);');
		$this->RegisterTimer("CheckTimeout", 0 , 'AUTOOFF_CheckTimeout($_IPS[\'TARGET\']);');

    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {

		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		$triggerVariablesJson = $this->ReadPropertyString("TriggerVariables");
		$triggerVariables = json_decode($triggerVariablesJson);
		
		foreach($triggerVariables as $currentVariable) {
			
			$this->LogMessage("Registering Message Sink for Variable ID " . $currentVariable->VariableId, "DEBUG");
			$this->RegisterMessage($currentVariable->VariableId, VM_UPDATE);
		}
		
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}


	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "TargetStatusVariableId", "caption" => "Status vaiable of target device");
		
		$sensorListColumns = Array(
			Array(
				"caption" => "Variable Id",
				"name" => "VariableId",
				"width" => "650px",
				"edit" => Array("type" => "SelectVariable"),
				"add" => 0
			)
		);
		$form['elements'][] = Array("type" => "List", "columns" => $sensorListColumns, "name" => "TriggerVariables", "caption" => "Trigger Variables", "add" => true, "delete" => true);
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'AUTOOFF_RefreshInformation($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Trigger On", "onClick" => 'AUTOOFF_TriggerOn($id);');

		// Return the completed form
		return json_encode($form);

	}
	
	protected function LogMessage($message, $severity = 'INFO') {
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		
		IPS_LogMessage($this->ReadPropertyString('Sender'), $messageComplete);
	}

	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
		
				// Neuen Wert in die Statusvariable schreiben
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			case "DetectionEnabled":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		// $this->LogMessage("$TimeStamp - $SenderId - $Message", "DEBUG");
		
		$triggerVariablesJson = $this->ReadPropertyString("TriggerVariables");
		$triggerVariables = json_decode($triggerVariablesJson);
		
		$isTriggerVariable = false;
		
		foreach ($triggerVariables as $currentVariable) {
			
			if ($SenderId == $currentVariable->VariableId) {
				
				$isTriggerVariable = true;
				break;
			}
		}
		
		if ($isTriggerVariable) {
			
			$this->LogMessage("Triggered by Variable $SenderId","DEBUG");
			$this->TriggerOn();
			return;
		}
	}
	
	public function TriggerOn() {
		
		if (! GetValue($this->GetIDForIdent("DetectionEnabled"))) {
			
			$this->LogMessage("Ignoring Trigger because Detection is disabled", "DEBUG");
			return;
		}
		
		$this->LogMessage("Triggering Timer", "DEBUG");
		SetValue($this->GetIDForIdent("LastTrigger"), time());
		
		// Set Time to timeout with some security margin (2 seconds)
		$newInterval = ( GetValue($this->GetIDForIdent("Timeout") ) + 2 ) * 1000;
		$this->SetTimerInterval("CheckTimeout", $newInterval);
		SetValue($this->GetIDForIdent("Status", true);
		
		if (! GetValue($this->ReadPropertyInteger("TargetStatusVariableId"))) {
			
			$this->LogMessage("Switching on target device","DEBUG");
			RequestAction($this->ReadPropertyInteger("TargetStatusVariableId"), true);
		}
		else {
			
			$this->LogMessage("Device is already switched on","DEBUG");
		}
	}
	
	public function CheckTimeout() {
		
		if (! GetValue($this->GetIDForIdent("DetectionEnabled"))) {
			
			$this->LogMessage("Ignoring Timeout because Detection is off", "DEBUG");
			return;
		}
		
		$timestampTimeout = GetValue($this->GetIDForIdent("LastTrigger")) + GetValue($this->GetIDForIdent("Timeout"));
		
		if ($timestampTimeout <= time()) {
			
			$this->LogMessage("Timer has expired, turning off device", "DEBUG");
			RequestAction($this->ReadPropertyInteger("TargetStatusVariableId"), false);
			$this->SetTimerInterval("CheckTimeout", 0);
			SetValue($this->GetIDForIdent("Status", false);
		}
		else {
			
			$timeDelta = time() - $timestampTimeout;
			$this->LogMessage("Timer has not yet expired, $timeDelta seconds left", "DEBUG");
		}
	}

}
