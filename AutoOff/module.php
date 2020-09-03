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

		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableInteger("LastTrigger","Last Trigger","~UnixTimestamp");

		// Default Actions
		$this->EnableAction("Status");

		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'AUTOOFF_RefreshInformation($_IPS[\'TARGET\']);');

    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {

		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
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
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function TriggerOn() {
		
		if (GetValue(GetIDForIdent("Status"))) {
			
			$this->LogMessage("Triggering AutoOff", "DEBUG");
			SetValue(GetIDForIdent("LastTrigger"), time());
		}
		{
			
			$this->LogMessage("Ignoring Trigger because Status is off", "DEBUG");
		}
	}

}
