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
		$this->RegisterPropertyInteger("TargetColorVariableId",0);
		$this->RegisterPropertyInteger("TargetIntensity",0);
		$this->RegisterPropertyInteger("TargetColor",0);
		$this->RegisterPropertyInteger("BlackoutTime",5);
		$this->RegisterPropertyInteger("StopVariablesFollowUpTime",0);
		$this->RegisterPropertyBoolean("SetIntensity",false);
		$this->RegisterPropertyBoolean("Intensity255",false);
		$this->RegisterPropertyBoolean("AbortTimerIfIntensityWasModified",false);
		$this->RegisterPropertyBoolean("SetColor",false);
		$this->RegisterPropertyBoolean("AbortTimerIfColorWasModified",false);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyString("TriggerVariables", "");
		$this->RegisterPropertyString("StopVariables", "");
		
		//Attributes
		$this->RegisterAttributeInteger("TargetIntensity",0);
		$this->RegisterAttributeInteger("TargetColor",0);
		
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
		$this->RegisterVariableInteger("LastAutoOff","Last AutoOff","~UnixTimestamp");
		$this->RegisterVariableInteger("LastStopConditionMet","Last Stop Condition met","~UnixTimestamp");
		$this->RegisterVariableInteger("LastStopConditionCleared","Last Stop Condition cleared","~UnixTimestamp");
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
		
		if ($this->ReadPropertyBoolean("SetIntensity") ) {
			
			if ($this->ReadPropertyBoolean("Intensity255") ) {
				
				$this->RegisterVariableBoolean("TargetIntensity","Target Intensity","~Intensity.255");
			}
			else {
				
				$this->RegisterVariableBoolean("TargetIntensity","Target Intensity","~Intensity.100");
			}
			$this->EnableAction("TargetIntensity");
		}
		else {
			
			if (@$this->GetIDForIdent("TargetIntensity")) {
	
				$this->DisableAction("TargetIntensity");
				$this->UnregisterVariable("TargetIntensity");
			}
		}
		
		if ($this->ReadPropertyBoolean("SetColor") ) {
			
			$this->RegisterVariableBoolean("TargetColor","Target Color","~HexColor");
			$this->EnableAction("TargetColor");
		}
		else {
			
			if (@$this->GetIDForIdent("TargetColor")) {
	
				$this->DisableAction("TargetColor");
				$this->UnregisterVariable("TargetColor");
			}
		}
		
		$triggerVariables = $this->GetTriggerVariables();
		
		if ($triggerVariables) {
			
			foreach($triggerVariables as $currentVariable) {
				
				$this->LogMessage("Registering Message Sink for Variable ID " . $currentVariable['VariableId'], "DEBUG");
				$this->RegisterMessage($currentVariable['VariableId'], VM_UPDATE);
			}
		}

		$stopVariables = $this->GetStopVariables();
		
		if ($stopVariables) {
			
			foreach ($stopVariables as $currentStopVariable) {
				
				$this->LogMessage("Registering Message Sink for Variable ID " . $currentStopVariable['VariableId'], "DEBUG");
				$this->RegisterMessage($currentStopVariable['VariableId'], VM_UPDATE);
				
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
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "BlackoutTime", "caption" => "Blackout time after last AutoOff");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "SetIntensity", "caption" => "Dim to specific intensity instead of switching on");
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "TargetIntensityVariableId", "caption" => "Intensity variable of target device");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "Intensity255", "caption" => "Use 255 step granularity instead of 100 (e.g. for HUE devices)");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "TargetIntensity", "caption" => "Intensity level");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "AbortTimerIfIntensityWasModified", "caption" => "Abort the Auto off timer if the intensity was modified manually during runtime");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "SetColor", "caption" => "Change to specific color instead of switching on");
		$form['elements'][] = Array("type" => "SelectVariable", "name" => "TargetColorVariableId", "caption" => "color variable of target device");
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "TargetColor", "caption" => "Target Color");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "AbortTimerIfColorWasModified", "caption" => "Abort the Auto off timer if the Color was modified manually during runtime");
		
		$sensorListColumns = Array(
			Array(
				"caption" => "Variable Id",
				"name" => "VariableId",
				"width" => "650px",
				"edit" => Array("type" => "SelectVariable"),
				"add" => 0
			),
			Array(
				"caption" => "Trigger Type",
				"name" => "TriggerType",
				"width" => "auto",
				"add" => "OnChange",
				"edit" => Array(
							"type" => "Select",
							"options" => Array(
									Array(
										"caption" => "OnUpdate - AutoOff gets triggered when the variable get's updated",
										"value" => "OnUpdate"
									),
									Array(
										"caption" => "OnChange - AutoOff gets triggered when the value of the varible changes",
										"value" => "OnChange"
									),
									Array(
										"caption" => "OnTrue - AutoOff gets triggered when the value of the varible changes to true",
										"value" => "OnTrue"
									),
									Array(
										"caption" => "OnSpecificValue - AutoOff gets triggered when the value of the variable changes to a specific value",
										"value" => "OnSpecificValue"
									)
								)
							)
			),
			Array(
				"caption" => "Specific Value",
				"name" => "SpecificValue",
				"width" => "auto",
				"edit" => Array("type" => "ValidationTextBox"),
				"add" => 1
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
		
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "StopVariablesFollowUpTime", "caption" => "Follow Up time when stop condition is reached (turn on happens X seconds later.");
		
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
			),
			Array(
				"caption" => "Stop turn on",
				"name" => "StopTurnOn",
				"width" => "100px",
				"edit" => Array("type" => "CheckBox"),
				"add" => true
			),
			Array(
				"caption" => "Stop turn off",
				"name" => "StopTurnOff",
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
		$form['actions'][] = Array(	"type" => "Button", "label" => "Abort Timer", "onClick" => 'AUTOOFF_Abort($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Enable Detection", "onClick" => 'AUTOOFF_Enable($id);');
		$form['actions'][] = Array(	"type" => "Button", "label" => "Disable Detection", "onClick" => 'AUTOOFF_Disable($id);');

		// Return the completed form
		return json_encode($form);

	}
	
	// Version 1.0
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($messageComplete, $logMappings[$severity]);
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
		
		$timestampBlackout = GetValue($this->GetIDForIdent("LastAutoOff")) + $this->ReadPropertyInteger("BlackoutTime");
		$deltaBlackout = $timestampBlackout - time();
		
		if ($deltaBlackout > 0) {
			
			$this->LogMessage("Ignoring refresh because blackout is still active: $deltaBlackout sec","DEBUG");
			return;
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
			case "TargetIntensity":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			case "TargetColor":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(";",$Data), "DEBUG");
		
		if ($SenderId == $this->ReadPropertyInteger("TargetStatusVariableId")) {
			
			$this->RefreshInformation();
			return;
		}
		
		if ($this->IsTriggerVariable($SenderId)) {
			
			$this->LogMessage("Triggered by Variable $SenderId","DEBUG");
			
			if ($Data[1]) {
				
				if ($this->GetTriggerType($SenderId) == "OnTrue") {
					
					if ($Data[0] == 1) {
				
						$this->LogMessage("Variable has changed to true value","DEBUG");
						$this->Trigger();
						return;
					}
				}
				
				if ($this->GetTriggerType($SenderId) == "OnSpecificValue") {
					
					$specificValue = $this->GetTriggerSpecificValue($SenderId);
					
					if ($Data[0] == $specificValue) {
				
						$this->LogMessage("Variable has changed to value $specificValue","DEBUG");
						$this->Trigger();
						return;
					}
				}
				
				$this->LogMessage("Variable was changed","DEBUG");
				if ($this->GetTriggerType($SenderId) == "OnChange") {
					
					$this->Trigger();
					return;
				}
			}
			else {
				
				$this->LogMessage("Variable was touched","DEBUG");
				if ($this->GetTriggerType($SenderId) == "OnUpdate") {
					
					$this->Trigger();
					return;
				}
			}
			return;
		}
		
		if ($this->IsStopVariable($SenderId)) {
			
			// Only check if things have changed
			if ($Data[1]) {
			
				if (! $this->CheckStopConditions("TurnOff") ) {
					
					// No stop conditions are active anymore
					SetValue($this->GetIDForIdent("LastStopConditionCleared"),time());
					
					$this->LogMessage("Timeout Check because of change in stop variable $SenderId","DEBUG");
					$this->CheckTimeout();
					return;
				}
				
				if (! $this->CheckStopConditions("TurnOn") ) {
					
					SetValue($this->GetIDForIdent("LastStopConditionCleared"), time());
				}
			}
		}	
	}
	
	public function Trigger() {
		
		if (! GetValue($this->GetIDForIdent("DetectionEnabled"))) {
			
			$this->LogMessage("Ignoring Trigger because Detection is disabled", "DEBUG");
			return;
		}
		
		if ( $this->CheckStopConditions("TurnOn") ) {
			
			$this->LogMessage("Ignoring Trigger because step conditions are met", "DEBUG");
			return;
		}
		
		$timestampBlackout = GetValue($this->GetIDForIdent("LastAutoOff")) + $this->ReadPropertyInteger("BlackoutTime");
		$deltaBlackout = $timestampBlackout - time();
		
		if ($deltaBlackout > 0) {
			
			$this->LogMessage("Ignoring Trigger because blackout is still active: $deltaBlackout sec","DEBUG");
			return;
		}
		
		$this->LogMessage("Triggering Timer", "DEBUG");
		SetValue($this->GetIDForIdent("LastTrigger"), time());
		
		// Set Time to timeout with some security margin (2 seconds)
		$newInterval = ( GetValue($this->GetIDForIdent("Timeout") ) + 2 ) * 1000;
		$this->SetTimerInterval("CheckTimeout", $newInterval);
		SetValue($this->GetIDForIdent("Status"), true);
		
		if (! GetValue($this->ReadPropertyInteger("TargetStatusVariableId"))) {
			
			if ($this->ReadPropertyBoolean("SetColor")) {
				
				$targetColor = ReadValue($this->GetIDForIdent("TargetColor"));
				$this->LogMessage("Changing target device to color $targetColor", "DEBUG");
				RequestAction($this->ReadPropertyInteger("TargetColorVariableId"), $targetColor);
				$this->WriteAttributeInteger("TargetColor", $targetColor);
			}
			else {
			
				if ($this->ReadPropertyBoolean("SetIntensity")) {
					
					$targetIntensity = ReadValue($this->GetIDForIdent("TargetIntensity"));
					$this->LogMessage("Dimming target device to intensity level $targetIntensity","DEBUG");
					RequestAction($this->ReadPropertyInteger("TargetIntensityVariableId"), $targetIntensity);				
					$this->WriteAttributeInteger("TargetIntensity", $targetIntensity);
				}
				else {
				
					$this->LogMessage("Switching on target device","DEBUG");
					RequestAction($this->ReadPropertyInteger("TargetStatusVariableId"), true);
				}
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
		
		if ($this->CheckStopConditions("TurnOff") ) {
			
			$this->LogMessage("Ignoring Timeout because a stop condition was met", "DEBUG");
			return;
		}
			
		$timestampTimeout = GetValue($this->GetIDForIdent("LastTrigger")) + GetValue($this->GetIDForIdent("Timeout"));
		
		if ($this->ReadPropertyInteger("StopVariablesFollowUpTime") != 0) {
			
			$timestampTimeoutFollowUp = GetValue($this->GetIDForIdent("LastStopConditionCleared")) + $this->ReadPropertyInteger("StopVariablesFollowUpTime");
		}
		else {
			
			$timestampTimeoutFollowUp = $timestampTimeout;
		}
		
		if ( ($timestampTimeout <= time()) && ($timestampTimeoutFollowUp >= time()) ) {
			
			// The check was triggered between the expiration and the follow-up time
			$this->LogMessage("Timer has expired but Stop Variable cooldown is in place.","DEBUG");
			$this->SetTimerInterval("CheckTimeout", ($this->ReadPropertyInteger("StopVariablesFollowUpTime") + 2) * 1000);
			return;
		}
		
		if ($timestampTimeout <= time()) {
			
			$this->LogMessage("Timer has expired", "DEBUG");
			$this->SetTimerInterval("CheckTimeout", 0);
			SetValue($this->GetIDForIdent("Status"), false);
			
			if ($this->ReadPropertyBoolean("AbortTimerIfColorWasModified")) {
				
				if (GetValue($this->ReadPropertyInteger("TargetColorVariableId")) != $this->ReadAttributeInteger("TargetColor")) {
					
					$this->LogMessage("Stopping AutoOff timer but the target device will not be switched off as the color was manually modified","DEBUG");
					return;
				}	
			}
			
			if ($this->ReadPropertyBoolean("AbortTimerIfIntensityWasModified")) {
				
				if (GetValue($this->ReadPropertyInteger("TargetIntensityVariableId")) != $this->ReadAttributeInteger("TargetIntensity")) {
					
					$this->LogMessage("Stopping AutoOff timer but the target device will not be switched off as the Intensity level was manually modified","DEBUG");
					return;
				}	
			}
			
			$this->LogMessage("Turning off device","DEBUG");
			RequestAction($this->ReadPropertyInteger("TargetStatusVariableId"), false);
			SetValue($this->GetIDForIdent("LastAutoOff"), time() );
			
		}
		else {
			
			$timeDelta = time() - $timestampTimeout;
			$this->LogMessage("Timer has not yet expired, $timeDelta seconds left", "DEBUG");
			// Set the timer to a minute interval
			// $this->SetTimerInterval("CheckTimeout", 10 * 60 * 1000);
		}
	}
	
	protected function UpdateValue($variableId, $newValue) {
		
		if (GetValue($variableId) != $newValue) {
			
			SetValue($variableId, $newValue);
		}
	}
	
	public function Enable() {
		
		$this->UpdateValue($this->GetIDForIdent("DetectionEnabled"), true);
	}
	
	public function Disable() {
		
		$this->UpdateValue($this->GetIDForIdent("DetectionEnabled"), true);
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
			SetValue($this->GetIDForIdent("LastAutoOff"), time() );
		}
	}
	
	protected function CheckStopConditions($mode) {
		
		$stopVariables = $this->GetStopVariables();

		if (! $stopVariables) {
			
			return false;
		}
		
		$stopConditionFound = false;

		foreach($stopVariables as $currentVariable) {
			
			if (GetValue($currentVariable['VariableId']) == $currentVariable['StopState']) {
			
				if ( ($mode == "TurnOn") && ($currentVariable['StopTurnOn']) ) {
					
					$this->LogMessage("Stop Condition hit for variable " . $currentVariable['VariableId'], "DEBUG");
					$stopConditionFound = true;
				}
				
				if ( ($mode == "TurnOff") && ($currentVariable['StopTurnOff']) ) {
					
					$this->LogMessage("Stop Condition hit for variable " . $currentVariable['VariableId'], "DEBUG");
					$stopConditionFound = true;
				}
			}
		}
		
		if ($stopConditionFound) {
			
			// $this->LogMessage("Ignoring Trigger because at least on stop condition was hit", "DEBUG");
			SetValue($this->GetIDForIdent("LastStopConditionMet"), time());
			return true;
		}
		

		// Return false if no stop condition was hit
		return false;
	}
	
	protected function GetTriggerVariables() {
		
		$triggerVariablesJson = $this->ReadPropertyString("TriggerVariables");
		$triggerVariables = json_decode($triggerVariablesJson, true);
		
		if (! is_array($triggerVariables) ) {
			
			return false;
		}
		
		if (count($triggerVariables) == 0 ) {
			
			return false;
		}
		
		return $triggerVariables;
	}
	
	protected function IsTriggerVariable($variableId) {
		
		$triggerVariables = $this->GetTriggerVariables();
		
		$isTriggerVariable = false;
		
		if ($triggerVariables) {
			
			foreach ($triggerVariables as $currentVariable) {
				
				if ($variableId == $currentVariable['VariableId']) {
					
					$isTriggerVariable = true;
					break;
				}
			}
				
			if ($isTriggerVariable) {
				
				return true;
			}
		}

		return false;
	}
	
	protected function GetTriggerType($variableId) {
		
		$triggerVariables = $this->GetTriggerVariables();
		
		if (! $triggerVariables) {
			
			return false;
		}
		
		$triggerType = false;
				
		foreach ($triggerVariables as $currentVariable) {
			
			if ($variableId == $currentVariable['VariableId']) {
				
				$triggerType = $currentVariable['TriggerType'];
				break;
			}
		}
			
		return $triggerType;
	}	

	protected function GetTriggerSpecificValue($variableId) {
		
		$triggerVariables = $this->GetTriggerVariables();
		
		if (! $triggerVariables) {
			
			return false;
		}
		
		$triggerSpecificValue = false;
				
		foreach ($triggerVariables as $currentVariable) {
			
			if ($variableId == $currentVariable['VariableId']) {
				
				$triggerSpecificValue = $currentVariable['SpecificValue'];
				break;
			}
		}
			
		return $triggerSpecificValue;
	}	

	protected function GetStopVariables() {
		
		$stopVariablesJson = $this->ReadPropertyString("StopVariables");
		$stopVariables = json_decode($stopVariablesJson, true);
		
		if (! is_array($stopVariables)) {
			
			return false;
		}
		
		if (count($stopVariables) == 0) {
			
			return false;
		}
		
		return $stopVariables;
	}
	
	protected function IsStopVariable($variableId) {
		
		$stopVariables = $this->GetStopVariables();
		
		$isStopVariable = false;
			
		foreach ($stopVariables as $currentVariable) {
				
			if ($variableId == $currentVariable['VariableId']) {
				
				$isStopVariable = true;
				break;
			}
		}
		
		if ($isStopVariable) {
			
			return true;
		}

		return false;
	}

}
