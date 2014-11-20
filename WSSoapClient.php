<?php
 
/**
 * This class can add WSSecurity authentication support to SOAP clients
 * implemented with the PHP 5 SOAP extension.
 *
 * It extends the PHP 5 SOAP client support to add the necessary XML tags to
 * the SOAP client requests in order to authenticate on behalf of a given
 * user with a given password.
 *
 * This class was tested with Axis and WSS4J servers.
 *
 * @author Roger Veciana - http://www.phpclasses.org/browse/author/233806.html
 * @author John Kary <johnkary@gmail.com>
 * @author Alberto Martínez  - https://github.com/Turin86
 * @see http://stackoverflow.com/questions/2987907/how-to-implement-ws-security-1-1-in-php5
 */
class WSSoapClient extends SoapClient
{
	private $OASIS = 'http://docs.oasis-open.org/wss/2004/01';
 
	/**
	 * WS-Security Username
	 * @var string
	 */
	private $username;
	
	/**
	 * WS-Security Password
	 * @var string
	 */
	private $password;
	 
	/**
	 * WS-Security PasswordType
	 * @var string
	 */
	private $passwordType;
	 
	/**
	 * Set WS-Security credentials
	 * 
	 * @param string $username
	 * @param string $password
	 * @param string $passwordType
	 */
	public function __setUsernameToken($username, $password, $passwordType)
	{
		$this->username = $username;
		$this->password = $password;
		$this->passwordType = $passwordType;
	}
	   
	/**
	 * Overwrites the original method adding the security header.
	 * As you can see, if you want to add more headers, the method needs to be modified.
	 */
	public function __call($function_name, $arguments)
	{
		$this->__setSoapHeaders($this->generateWSSecurityHeader());
		return parent::__call($function_name, $arguments);
	}
	    
	/**
	 * Generate password digest.
	 * 
	 * Using the password directly may work also, but it's not secure to transmit it without encryption.
	 * And anyway, at least with axis+wss4j, the nonce and timestamp are mandatory anyway.
	 * 
	 * @return string   base64 encoded password digest
	 */
	private function generatePasswordDigest()
	{
		$this->nonce = mt_rand();
		$this->timestamp = gmdate('Y-m-d\TH:i:s\Z');
		
		$packedNonce = pack('H*', $this->nonce);
		$packedTimestamp = pack('a*', $this->timestamp);
		$packedPassword = pack('a*', $this->password);
		
		$hash = sha1($packedNonce . $packedTimestamp . $packedPassword);
		$packedHash = pack('H*', $hash);
		
		return base64_encode($packedHash);
	}
	
	/**
	 * Generates WS-Security headers
	 * 
	 * @return SoapHeader
	 */
	private function generateWSSecurityHeader()
	{
		if ($this->passwordType === 'PasswordDigest')
		{
			$password = $this->generatePasswordDigest();
			$nonce = sha1($this->nonce);
		}
		elseif ($this->passwordType === 'PasswordText')
		{
			$password = $this->password;
			$nonce = sha1(mt_rand());
		}
		else
		{
			return '';
		}
 
		$xml = '
<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="' . $this->OASIS . '/oasis-200401-wss-wssecurity-secext-1.0.xsd">
	<wsse:UsernameToken>
	<wsse:Username>' . $this->username . '</wsse:Username>
	<wsse:Password Type="' . $this->OASIS . '/oasis-200401-wss-username-token-profile-1.0#' . $this->passwordType . '">' . $password . '</wsse:Password>
	<wsse:Nonce EncodingType="' . $this->OASIS . '/oasis-200401-wss-soap-message-security-1.0#Base64Binary">' . $nonce . '</wsse:Nonce>';
		
		if ($this->passwordType === 'PasswordDigest')
		{
			$xml .= "\n\t" . '<wsu:Created xmlns:wsu="' . $this->OASIS . '/oasis-200401-wss-wssecurity-utility-1.0.xsd">' . $this->timestamp . '</wsu:Created>';
		}
		
		$xml .= '
	</wsse:UsernameToken>
</wsse:Security>';
		
		return new SoapHeader(
			$this->OASIS . '/oasis-200401-wss-wssecurity-secext-1.0.xsd',
			'Security',
			new SoapVar($xml, XSD_ANYXML),
			true);
	}
}
?>
