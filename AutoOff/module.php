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
		$this->RegisterPropertyInteger("TargetIntensityVariableId",0);
		$this->RegisterPropertyInteger("TargetIntensity",0);
		$this->RegisterPropertyBoolean("SetIntensity",false);
		$this->RegisterPropertyBoolean("AbortTimerIfIntensityWasModified",false);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyString("TriggerVariables", "");
		$this->RegisterPropertyString("StopVariables", "");
		
		// Variable profiles
		$variableProfileTimeout = "AUTOOFF.Timeout";
		if (IPS_VariableProfileExists($variableProfileTimeout) ) {
		
			IPS_DeleteVariableProfile($variableProfileTimeout);
		}			
		IPS_CreateVariableProfile($variableProfileTimeout, 1);
		IPS_SetVariableProfileIcon($variableProfileTimeout, "Clock");
		IPS_SetVariableProfileAssociation($variableProfileTimeout, 60, "1 Minute", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileTimeout, 90, "90 Sekunden", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileTimeout, 120, "2 Minuten", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileTimeout, 300, "5 Minuten", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileTimeout, 900, "15 Minuten", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileTimeout, 1800, "30 Minuten", "", -1);
		IPS_SetVariableProfileAssociation($variableProfileTimeout, 3600, "60 Minuten", "", -1);

		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableBoolean("DetectionEnabled","Motion Detection Enabled","~Switch");
		$this->RegisterVariableInteger("LastTrigger","Last Trigger","~UnixTimestamp");
		$this->RegisterVariableInteger("Timeout","Timeout","AUTOOFF.Timeout");

		// Default Actions
		$this->EnableAction("Status");
		$this->EnableAction("DetectionEnabled");
		$this->EnableAction("Timeout");

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
		
		if (is_array($triggerVariables)) {
		
			foreach($triggerVariables as $currentVariable) {
				
				$this->LogMessage("Registering Message Sink for Variable ID " . $currentVariable->VariableId, "DEBUG");
				$this->RegisterMessage($currentVariable->VariableId, VM_UPDATE);
			}
		}
		
		// Also register the target variable to keep track of change events
		$this->RegisterMessage($this->ReadPropertyInteger("TargetStatusVariableId"), VM_UPDATE);
		
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
		
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "TargetStatusVariableId", "caption" => "Status variable of target device");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "SetIntensity", "caption" => "Dim to specific intensity instead of switching on");
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "TargetIntensityVariableId", "caption" => "Intensity variable of target device");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "TargetIntensity", "caption" => "Intensity level");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "AbortTimerIfIntensityWasModified", "caption" => "Abort the Auto off timer if the intensity was modified manually during runtime");
		
		$sensorListColumns = Array(
			Array(
				"caption" => "Variable Id",
				"name" => "VariableId",
				"width" => "650px",
				"edit" => Array("type" => "SelectVariable"),
				"add" => 0
			)
		);
		$form['elements'][] = Array(
			"type" => "List", 
			"columns" => $sensorListColumns, 
			"name" => "TriggerVariables", 
			"caption" => "Trigger Variables", 
			"add" => true, 
			"delete" => true,
			"rowCount" => 5
		);
		
		$stopVariablesColumns = Array(
			Array(
				"caption" => "Variable Id",
				"name" => "VariableId",
				"width" => "650px",
				"edit" => Array("type" => "SelectVariable"),
				"add" => 0
			),
			Array(
				"caption" => "Stop State",
				"name" => "StopState",
				"width" => "100px",
				"edit" => Array("type" => "CheckBox"),
				"add" => true
			)
		);
		$form['elements'][] = Array(
			"type" => "List", 
			"columns" => $stopVariablesColumns, 
			"name" => "StopVariables", 
			"caption" => "Stop Variables", 
			"add" => true, 
			"delete" => true,
			"rowCount" => 5
		);
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'AUTOOFF_RefreshInformation($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Trigger On", "onClick" => 'AUTOOFF_Trigger($id);');

		// Return the completed form
		return json_encode($form);

	}
	
	protected function LogMessage($message, $severity = 'INFO') {
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		
		IPS_LogMessage($this->ReadPropertyString('Sender') . " - " . $this->InstanceID, $messageComplete);
	}

	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		
		if (! GetValue($this->GetIDForIdent("DetectionEnabled"))) {
			
			if (GetValue($this->GetIDForIdent("Status") )) {
				
				$this->SetTimerInterval("CheckTimeout", 0);
				SetValue($this->GetIDForIdent("Status"), false);
				
				return;
			}
		}
		
		if (GetValue($this->GetIDForIdent("Status"))) {
			
			if (! GetValue($this->ReadPropertyInteger("TargetStatusVariableId"))) {
				
				// Triger a manual abort if the device was turned on manually
				$this->LogMessage("The target device was manually switched off before timer expiration");
				$this->Abort();
				
				return;
			}
			
			if ($this->GetTimerInterval("CheckTimeout") == 0) {
				
				// It looks like the timer is not running but the status variable is still on.
				// This can happen if IPS crashes in a high load situation.
				$this->LogMessage("It seems that the CheckTimeout timer got lost. Triggering again");
				$this->Trigger();
				
				return;
			}
		}
		else {
			
			if (GetValue($this->ReadPropertyInteger("TargetStatusVariableId"))) {

				// Activate the timer if motion detection is active and the device is on but Status is off. Maybe the message was not processed
				$this->LogMessage("The target device was manually switched on but the event was missed");
				$this->Trigger();
				
				return;
			}
		}
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				// If switch on
				if ($Value) {
					
					$this->Trigger();
				}
				else {
				
					$this->Abort();
				}
				break;
			case "DetectionEnabled":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			case "Timeout":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		// $this->LogMessage("$TimeStamp - $SenderId - $Message", "DEBUG");
		
		if ($SenderId == $this->ReadPropertyInteger("TargetStatusVariableId")) {
			
			$this->RefreshInformation();
			return;
		}
		
		$triggerVariablesJson = $this->ReadPropertyString("TriggerVariables");
		$triggerVariables = json_decode($triggerVariablesJson);
		
		if (is_array($triggerVariables)) {
			
			$isTriggerVariable = false;
			
			foreach ($triggerVariables as $currentVariable) {
				
				if ($SenderId == $currentVariable->VariableId) {
					
					$isTriggerVariable = true;
					break;
				}
			}
			
			if ($isTriggerVariable) {
				
				$this->LogMessage("Triggered by Variable $SenderId","DEBUG");
				$this->Trigger();
				return;
			}
		}		
	}
	
	public function Trigger() {
		
		if (! GetValue($this->GetIDForIdent("DetectionEnabled"))) {
			
			$this->LogMessage("Ignoring Trigger because Detection is disabled", "DEBUG");
			return;
		}
		
		$stopVariablesJson = $this->ReadPropertyString("StopVariables");
		$stopVariables = json_decode($stopVariablesJson);
		
		if (is_array($stopVariables)) {
		
			$stopConditionFound = false;
		
			foreach($stopVariables as $currentVariable) {
				
				if (GetValue($currentVariable->VariableId) == $currentVariable->StopState) {
				
					$this->LogMessage("Stop Condition hit for variable " . $currentVariable->VariableId, "DEBUG");
					$stopConditionFound = true;
				}
			}
			
			if ($stopConditionFound) {
				
				$this->LogMessage("Ignoring Trigger because at least on stop condition was hit", "DEBUG");
				return;
			}
		}
		
		$this->LogMessage("Triggering Timer", "DEBUG");
		SetValue($this->GetIDForIdent("LastTrigger"), time());
		
		// Set Time to timeout with some security margin (2 seconds)
		$newInterval = ( GetValue($this->GetIDForIdent("Timeout") ) + 2 ) * 1000;
		$this->SetTimerInterval("CheckTimeout", $newInterval);
		SetValue($this->GetIDForIdent("Status"), true);
		
		if (! GetValue($this->ReadPropertyInteger("TargetStatusVariableId"))) {
			
			if ($this->ReadPropertyBoolean("SetIntensity")) {
				
				$this->LogMessage("Dimming target device to intensity level " . $this->ReadPropertyInteger("TargetIntensity"),"DEBUG");
				RequestAction($this->ReadPropertyInteger("TargetIntensityVariableId"), $this->ReadPropertyInteger("TargetIntensity"));				
			}
			else {
			
				$this->LogMessage("Switching on target device","DEBUG");
				RequestAction($this->ReadPropertyInteger("TargetStatusVariableId"), true);
			}
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
			
			$this->LogMessage("Timer has expired", "DEBUG");
			$this->SetTimerInterval("CheckTimeout", 0);
			SetValue($this->GetIDForIdent("Status"), false);
			
			if ($this->ReadPropertyBoolean("AbortTimerIfIntensityWasModified")) {
				
				if (GetValue($this->ReadPropertyInteger("TargetIntensityVariableId")) != $this->ReadPropertyInteger("TargetIntensity")) {
					
					$this->LogMessage("Stopping AutoOff timer but the target device will not be switched off as the Intensity level was manually modified");
					return;
				}	
			}
			
			$this->LogMessage("Turning off device","DEBUG");
			RequestAction($this->ReadPropertyInteger("TargetStatusVariableId"), false);
			
		}
		else {
			
			$timeDelta = time() - $timestampTimeout;
			$this->LogMessage("Timer has not yet expired, $timeDelta seconds left", "DEBUG");
		}
	}
	
	public function Abort() {
		
		if (! GetValue($this->GetIDForIdent("DetectionEnabled"))) {
			
			$this->LogMessage("Ignoring Manual abort because Detection is off", "INFO");
			return;
		}
		
		$this->LogMessage("Aborting timer before expiration, turning off device", "DEBUG");
		$this->SetTimerInterval("CheckTimeout", 0);
		SetValue($this->GetIDForIdent("Status"), false);
		
		// Turn device off is on
		if (GetValue($this->ReadPropertyInteger("TargetStatusVariableId"))) {
			
			RequestAction($this->ReadPropertyInteger("TargetStatusVariableId"), false);
		}
	}

}
