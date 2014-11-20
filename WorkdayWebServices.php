<?php
	include 'WSSoapClient.php';

	/*
		Class: WorkdayWebServices
		Auth: Sean Bailey (sbailey@arista.com)
		Date Created: 10/22/2014
		Date Modified: 10/24/2014
		Purpose: Serves as a way to connect to a Workday instance and update the following user information via SOAP
			1) Update user login name - updateWorkdayAccount()
			2) Update user email address - updateEmail()
			3) Update user phone number - updateWorkPhone()
			4) Update user photo - updatePhoto()
		
		Global vars:
			$api_username - username of the account making the SOAP calls
			$api_passwd - plaintext password of the account making the SOAP calls
			$api_passwdType - specifies to use plaintext password
			$wsdl - reference to the wsdl file being used to make the SOAP calls
			$debugOptions - used for viewing SOAP errors
			$lastCall - array containing the most recent client request and server response
			$construct_params - array to store the parameters used to construct object instance
	
		Functions:
			Private:
				createClient() - creates SOAP client connection at init time
				maintainContactInfo() - called by updateEmail and updatePhone; it performs the actual Workday web service call Maintain_Contact_Information			
				putWorkerPhoto() - called by updatePhoto; it performs the actual Workday web service call Put_Worker_Photo
				updateWorkdayAccount() - called by updateUsername; it performs the actual Workday web service call Update_Workday_Account				
				makeSoapCall() - the actual mechanism that sends the SOAP call to the endpoint via the client connection 

			Public:
				__construct($connection_params) - creates instance of WorkdayWebServices and establishes client connection
				listFunctions() - lists available functions in WSDL file
				listTypes() - lists available types in WSDL file
				getLastCall() - returns an array containing the last client request and server response
				getServerResponse() - returns the last response from the server
				getClientRequest() - returns the last client request
				
				updateEmail() - calls maintainContactInformation to update user email
				updateWorkPhone() - calls maintainContactInformation to update user work phone
				updateUsername() - calls updateWorkdayAccount to update the user's username
				

	*/
	
	class WorkdayWebServices{
		private $api_username;
		private $api_passwd;
		private $api_passwdType;
		private $wsdl;
		private $debugOptions;
		private $lastCall;
		private $construct_params; 
		
		public $client;
	

		/*
			Function: __construct()
			Purpose: Constructs an instance of WorkdayWebServices instance, and calls createClient to establish SOAP client connection.
			@param $params - an array tennant config/connection data

		*/
		public function __construct($params = array()){
			try{
				$this->api_username = $params['api_username'];
				$this->api_passwd = $params['api_passwd'];
				$this->api_passwdType = $params['api_passwdType'];
				$this->wsdl = $params['wsdl'];
				#debug options is the only optional parameter.
				if(!isset($params['debugOptions'])){
					$this->debugOptions = array();
				} else {
					$this->debugOptions = $params['debugOptions'];
				}
				$this->constructor_params = $params;
				
				$this->client = $this->createClient();
			} catch (Exception $e){
				echo 'Invalid constructor/ You\'r missing parameters';
				echo $e;
			}		

		}

		/*
			Function: createClient()
			Purpose: Creates the SOAP client connection with the tennant. Probably need some error handling in case connection fails to establish.
			@return SoapClient oject.
		*/
		private function createClient(){		
			$client = new WSSoapClient($this->wsdl,$this->debugOptions);
			$client->__setUsernameToken($this->api_username,$this->api_passwd,$this->api_passwdType);
			return $client;
		}

		/*
			Function: listFunctions()
			Purpose: Lists all available functions in WSDL file.
			@return: Associative array of all the functions defined in the WSDL

		*/
		public function listFunctions(){
			return $this->client->__getFunctions();
		}	

		/*
			Function: listTypes()
			Purpose: Lists all types available in WSDL file.
			@return: Associative array of all types defined in the WSDL
		*/
		public function listTypes(){
			return $this->client->__getTypes();
		}

		/*
			Function: getLastCall()
			Purpose: Returns the last client request made and server response
			@return: Associative array containing the last client request and server response.
		*/
		public function getLastCall(){
			return $this->lastCall;
		}
	
		/*
			Function: getServerResponse()
			Purpose: Returns the server response of the last request made
			@return: XML envelope of the last server response
		*/
		public function getServerResponse(){
			if(isset($this->lastCall['server_response'])){
				return $this->lastCall['server_response'];
			} else {
				return "Error no request has been made yet. Or no response has been received.";
			}
		}

		/*
			Function: getClientRequest()
			Purpose: Returns the last client request made
			@return: XML envelope of the last client request
		*/
		public function getClientRequest(){
			if(isset($this->lastCall['client_request'])){
				return $this->lastCall['client_request'];
			} else {
				return "Error no request has been made yet.";
			}
		}
	
		/*
			Function: maintainContactInformation()
			Purpose: Defines actual webservice call to workday for maintaining contact information
			Currently it only has logic to update work email and work phone, however could easily be added to.
			@param string $empID - the employee ID # to modify the contact info on.
			@param array $contactInfo - an array full of whatever contact info that need to be updated
			@return array lastCall - an associative array containing the last client request and server response 
		*/
		private function maintainContactInformation($empID,$contactInfo){

			#This portion creates the 'boiler plate' required info for the service to go through
			$request = array(
				'Maintain_Contact_Information_for_Person_Event_Request' => array(
					'Business_Process_Parameters' => array (
						'Auto_Complete' => 'true',
						'Run_Now' => 'true',
					),
					'Maintain_Contact_Information_Data' => array(
						'Worker_Reference' => $this->generateWorkerReference($empID,'ID'),
						'Worker_Contact_Information_Data' => array(),
					),
				),
			);
			#var_dump($request);	

			#This is a reference to what goes inside the 'boiler plate' stuff defined above 
			$innerRequest = &$request['Maintain_Contact_Information_for_Person_Event_Request']['Maintain_Contact_Information_Data']['Worker_Contact_Information_Data'];
			
			#Here we can handle and bit of contact info that needs to be updated. directly below is email, but it doesn't matter so long as 
			#$innerRequest[maintain contact info param] is defined in the WSDL
			if(isset($contactInfo['email'])){
				$innerRequest['Email_Address_Data'] = array(
					'Email_Address' => $contactInfo['email'],
					'Usage_Data' => array(
						'Public' => 1,
						'Type_Data' => array(
							'Primary' => 1,
							'Type_Reference' => array(
								'ID' => array(
									'_' => 'Work',
									'type'=>'Communication_Usage_Type_ID',
								),
							),
						),
					),
		
				);

			}

			#Similar to the above, except this adds phone contact info to the block
			if(isset($contactInfo['phone'])){
				$phoneData = &$contactInfo['phone'];
				$innerRequest['Phone_Data'] = array(
					'International_Phone_Code' => $phoneData['intl_code'],
					'Area_Code' => $phoneData['area_code'],
					'Phone_Number' => $phoneData['numbers'],
					'Phone_Extension' => $phoneData['extension'],
					'Phone_Device_Type_Reference' => array(
						'ID' => array(
							'_' => 'Landline',
							'type' => 'Phone_Device_Type_ID',
						),
					),
					'Usage_Data' => array(
						'Public' => 'true',
						'Type_Data' => array(
							'Primary' => 'true',
							'Type_Reference' => array(
									'ID' => array(
										'type' => 'Communication_Usage_Type_ID',
										'_' => 'Work',
									),
							),
						),
					),
				);
			}
		
			#Make the SOAP call and assign to to lastCall, then return the array containing request and response
			$this->lastCall = $this->makeSoapCall('Maintain_Contact_Information',$request);
			return $this->getLastCall();	
		}

		/*
			Function: updateEmail
			Purpose: Essentially an alias for maintainContactInformation, it simply transforms the contact info 
			parameter which is just a string, into an array and passes it to maintainContactInformation
			@param string $empID - the employee ID # to modify the contact info on.
			@param string $email - the new email address in string format
			@return array lastCall - an associative array containing the last client request and server response 
		*/
		public function updateEmail($empID,$email){
			$contactInfo = array('email' => $email);
			return $this->maintainContactInformation($empID,$contactInfo);
		}


		/*
			Function: updateWorkPhone
			Purpose: Essentially an alias for maintainContactInformation, it simply transforms the contact info 
			parameter which is just a string, into an array and passes it to maintainContactInformation
			@param string $empID - the employee ID # to modify the contact info on.
			@param string $phoneNumber - the new email address in string format; note this assumes the phone number is already formatted properly
			@return array lastCall - an associative array containing the last client request and server response 
		*/
		public function updateWorkPhone($empID, $phoneNumber){
			$contactInfo = array('phone' => $phoneNumber);
			return $this->maintainContactInformation($empID,$contactInfo);
		}
	
		/*
			Function: updatePhoto
			Purpose: Essentially an alias for putWorkerPhoto, made just to maintain a standard 
			@param string $empID - the employee ID # to modify the photo on.
			@param string $photo - location of a photo file to convert to base64
			@return array lastCall - an associative array containing the last client request and server response 

		*/
		public function updatePhoto($empID,$photo){
			return $this->putWorkerPhoto($empID,$photo);
		}	

		/*
			Function: isContractor
			Purpose: Determines if the employee being referenced is a contingent worker or a regular employee
			@param string $empID - the employee ID # to check, contingent workers are prepended with CON######
			@return boolean - if the empID contains any non-numeric characters then returns true, else false
		*/
		private function isContractor($empID){
			if(!is_numeric($empID)){
				return true;
			} 
			return false;
		}

		/*
			Function: generateWorkerReference
			Purpose: Generates the worker reference heading for each of the SOAP calls. Currently there are two different type
			of Worker_References. One references Integration_ID_Reference and the other simply ID. The reference type also varies
			by employee type, this function calls isContractor to determine which to use; contractor or employee, default is employee.
			@param string $empID - the employeeID# of the employee being referenced
			@param string soapIDType - determines if it's an Integration_ID_Reference or ID reference
			@return array Worker_Reference - the Worker_Reference array of the SOAP call; varies by soapIDType.
			
		*/
		private function generateWorkerReference( $empID, $soapIDType){
			/*
				Ways to reference the ID differ by SOAP call add/update workday account use Integrations_ID_Reference while 
				Put_Worker_Photo uses ID with attribute type Employee_ID
			*/

			if($soapIDType == 'Integration_ID_Reference'){
				$type = 'Employee_Reference';

				#If it's not an employee it's assumed to be contingent worker as there is no other option as of WSDL 23.1
				if($this->isContractor($empID)){
					$type = 'Contingent_Worker_Reference';
				}
				return array( $type 
						=> array('Integration_ID_Reference'
                                                                 => array('ID'
                                                                        => array('_' => $empID,
                                                                                'System_ID' => 'wd-emplid'
                                                                        ),
                                                                 ),
                                                ),
				);
			} else {
				$type = 'Employee_ID';
				if($this->isContractor($empID)){
					$type = 'Contingent_Worker_ID';
				}

				return  array('ID' 
						=> array( 
							'_' => $empID, 
							'type' => $type,
						)
				);

			}
		}

		/*
			Function: putWorkerPhoto
			Purpose: Creates the Put_Worker_Photo web service call to Workday for updating an employee's photo
			@param string $empID - the employee ID # to modify the photo on.
                        @param string $photo - location of a photo file to convert to base64
                        @return array lastCall - an associative array containing the last client request and server response 
		*/
		private function putWorkerPhoto( $empID,$photo){
			$request = array('Put_Worker_Photo_Request' 
				=> array(
					'Worker_Reference' => $this->generateWorkerReference($empID,'ID'),
					'Worker_Photo_Data'
						=> array(
							'File' => file_get_contents($photo), #automatically converts to base64!
						),
				)
			);
			$this->lastCall = $this->makeSoapCall('Put_Worker_Photo',$request);
			return $this->getLastCall();
			
		}
	
		/*
			Function: updateUsername
		 	Purpose: Essentially an alias for updateWorkdayAccount, made just to maintain a standard 
                        @param string $empID - the employee ID # to modify the username on.
                        @param string $username - the new username you wish to give to the user
                        @return array lastCall - an associative array containing the last client request and server response 
		*/
		public function updateUsername($empID,$username){
			return $this->updateWorkdayAccount($empID,$username);
		}
	
		/*
			Function: addWorkdayAccount()
			Purpose: Creates a Workday account for an employee record that doesn't already have an account.
			Note this will not succeed if the record already has an account. 
			@param string $empID - the employee ID # to modify the username on.
			@param string $username - the username you wish to give to the user.
			@return array lastCall - an associative array containing the last client request and server reponse
		*/
	
		public function addWorkdayAccount( $empID,$username){
			$Add_Workday_Account_params = 
				array('Workday_Account_for_Worker_Add' 
					 => array(
                                                'Worker_Reference'
                                                 	=> $this->generateWorkerReference($empID,'Integration_ID_Reference'), 
                                                'Workday_Account_for_Worker_Data'
                                                        => array('User_Name'=>$username, 'Password' => $this->generatePassword()),
                                        )
				);
			$this->lastCall = $this->makeSoapCall('Add_Workday_Account', $Add_Workday_Account_params);
			return $this->getLastCall();
		}
	
		/*
			Function: generatePassword()
			Purpose: Generates a random 10 char length password for a workday account. Note this is only needed to
			add a workday account. The user will not know their password as this assumes SSO to be used. 
			@param int $length - the length of the password you want, defaults to 10 chars
			@return string passwd - a 10 char string of composed of "random" letters, numbers and symbols
		*/

		private function generatePassword($length = 10){
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()_+';
    			$passwd = '';
    			for ($i = 0; $i < $length; $i++) {
        			$passwd .= $characters[rand(0, strlen($characters))];
    			}
    			return $passwd;	

		}		

		/*
			Function: updateWorkdayAccount
			Purpose: Creates the Update_Workday_Account web service call to Workday, currently only updates username,
			but has the potential to extend to other actions on the workday account.
			@param string $empID - the employee ID # to modify the username on. 
			@param string $username - the new username you wish to give to the user
			@return array lastCall - an associative array containig the last client request and server response
		*/
		private function updateWorkdayAccount( $empID, $username){
			$Update_Workday_Account_params = 
				array('Workday_Account_for_Worker_Update'
                                	=> array(
                                		'Worker_Reference'
                                               		=> $this->generateWorkerReference($empID,'Integration_ID_Reference'),
                                		'Workday_Account_for_Worker_Data'
                                        		=> array('User_Name'=>$username),
                                	)
				);
			$this->lastCall = $this->makeSoapCall('Update_Workday_Account',$Update_Workday_Account_params);
			return $this->getLastCall();
		}

		/*
			Function: makeSoapCall()
			Purpose: This is the function that actually has the client execute the SOAP call to the server.
			If there is any error it assigns the error to the request array to be used for data checking.
			@param string $wevserviceName - The name of the web service you wish to execute as it appears in the WSDL file
			@param array $webserviceParams - An associative array of parameters for the specified webservice. 
			@return array $request - An associative array containing the client request envelope, server response envelope, 
			and and exceptions thrown in the process. 
		*/
		private function makeSoapCall($webserviceName,$webserviceParams){
			$request = array();
			try {
				$this->client->__call($webserviceName,$webserviceParams);
			 } catch (Exception $e){
				$request['exception'] = $e;
			} finally {
				$request['client_request'] = $this->client->__getLastRequest();
				$request['server_response'] = $this->client->__getLastResponse();
			}
			return $request;
		}
	}
?>
