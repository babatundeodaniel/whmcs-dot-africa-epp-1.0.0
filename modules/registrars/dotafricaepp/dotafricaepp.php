<?php
# Copyright (c) 2018, Upperlink Limited
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

# Lead developer:
# Daniel Babatunde <daniel.babatunde@upperlink.ng>


# ! ! P L E A S E   N O T E  ! !

# * If you make changes to this file, please consider contributing
#   anything useful back to the community. Don't be a sour prick.

# * If you find this module useful please consider making a
#   donation to support modules like this.


# WHMCS hosting, theming, module development, payment gateway
# integration, customizations and consulting all available from
# http://upperlink.ng

// Make sure we not being accssed directly
if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

use WHMCS\Database\Capsule as Capsule;
//echo dirname(__DIR__) . '/../../init.php';
require_once dirname(__DIR__) . '/../../init.php';

# Configuration array
function dotafricaepp_getConfigArray() {
	$configarray = array(
		"Username" => array( "Type" => "text", "Size" => "20", "Description" => "Enter your username here" ),
		"Password" => array( "Type" => "password", "Size" => "20", "Description" => "Enter your password here" ),
		"SSL" => array( "Type" => "yesno" ),
		"Test" => array( "Type" => "yesno" ),
		"Certificate" => array( "Type" => "text", "Description" => "Path of certificate .pem" )
	);
	return $configarray;
}

function dotafricaepp_AdminCustomButtonArray() {
	$buttonarray = array(
		"Approve Transfer" => "ApproveTransfer",
		"Cancel Transfer Request" => "CancelTransferRequest",
		"Reject Transfer" => "RejectTransfer",
		"Recreate Contact" => "RecreateContact",
	);
	return $buttonarray;
}

# Function to return current nameservers
function dotafricaepp_GetNameservers($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");


	# Get client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# Get list of nameservers for domain
		$result = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</epp:info>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($result);
		logModuleCall('DotAfricaepp', 'GetNameservers', $xml, $result);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check the result is ok
		if($coderes != '1000') {
			$values["error"] = "GetNameservers/domain-info($domain): Code ($coderes) $msg";
			return $values;
		}

		# Grab hostname array
		$ns = $doc->getElementsByTagName('hostName');
		# Extract nameservers & build return result
		$i = 1;	$values = array();
		foreach ($ns as $nn) {
			$values["ns{$i}"] = $nn->nodeValue;
			$i++;
		}

		$values["status"] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'GetNameservers/EPP: '.$e->getMessage();
		return $values;
	}


	return $values;
}



# Function to save set of nameservers
function dotafricaepp_SaveNameservers($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");

	# Generate XML for nameservers
	if ($nameserver1 = $params["ns1"]) {
		$add_hosts = '
<domain:hostAttr>
	<domain:hostName>'.$nameserver1.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver2 = $params["ns2"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver2.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver3 = $params["ns3"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver3.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver4 = $params["ns4"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver4.'</domain:hostName>
</domain:hostAttr>';
	}
	if ($nameserver5 = $params["ns5"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver5.'</domain:hostName>
</domain:hostAttr>';
	}

	# Get client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# Grab list of current nameservers
		$request = $client->request( $xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</epp:info>
	</epp:command>
</epp:epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'SaveNameservers', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check if result is ok
		if($coderes != '1000') {
			$values["error"] = "SaveNameservers/domain-info($domain): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

		# Generate list of nameservers to remove
		$hostlist = $doc->getElementsByTagName('hostName');
		foreach ($hostlist as $host) {
			$rem_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$host->nodeValue.'</domain:hostName>
</domain:hostAttr>
	';
		}

		# Build request
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xmlns:cozadomain="http://co.za/epp/extensions/cozadomain-1-0"
		xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:update>
			<domain:update>
				<domain:name>'.$domain.'</domain:name>
				<domain:add>
					<domain:ns>'.$add_hosts.' </domain:ns>
				</domain:add>
				<domain:rem>
					<domain:ns>'.$rem_hosts.'</domain:ns>
				</domain:rem>
			</domain:update>
		</epp:update>
		<epp:extension>
			<cozadomain:update xsi:schemaLocation="http://co.za/epp/extensions/cozadomain-1-0 coza-domain-1.0.xsd">
			<cozadomain:chg><cozadomain:autorenew>false</cozadomain:autorenew></cozadomain:chg></cozadomain:update>
		</epp:extension>
	</epp:command>
</epp:epp>
	');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'SaveNameservers', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check if result is ok
		if($coderes != '1001') {
			$values["error"] = "SaveNameservers/domain-update($domain): Code ($coderes) $msg";
			return $values;
		}

		$values['status'] = "Domain update Pending. Based on .co.za policy, the estimated time taken is around 5 days.";

	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



# NOT IMPLEMENTED
function dotafricaepp_GetRegistrarLock($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");

	# Get lock status
	$lock = 0;
	if ($lock=="1") {
		$lockstatus="locked";
	} else {
		$lockstatus="unlocked";
	}
	return $lockstatus;
}



# NOT IMPLEMENTED
function dotafricaepp_SaveRegistrarLock($params) {
	$values["error"] = "SaveRegistrarLock: Current .africa policy does not allow for the addition of client-side statuses on domains.";
	return $values;
}



# Function to retrieve an available contact id
function _dotafricaepp_CheckContact($domain) {
	$prehash = $domain . time() . rand(0, 1000000);
	$contactid = substr(md5($prehash), 0,15);

	# Get client instance and check for available contact id
	try {
		$client = _dotafricaepp_Client($domain);

		$contactAvailable = 0;
		$count = 0;

		while ($contactAvailable == 0) {

			# Check if contact exists
			$request = $client->request($xml = '
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
	<command>
		<check>
			<contact:check xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">
			<contact:id>'.$contactid.'</contact:id>
			</contact:check>
		</check>
	</command>
</epp>
');

			# Parse XML result
			$doc= new DOMDocument();
			$doc->loadXML($request);
			logModuleCall('DotAfricaepp', 'RegisterDomain:CheckContact', $xml, $request);

			# Pull off status
			$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
			$contactAvailable = $doc->getElementsByTagName('id')->item(0)->getAttribute('avail');
			$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
			if($coderes == '1000') {
				$values['contact'] = 'Contact Created';
			} else if($coderes == '2302') {
				$values['contact'] = 'Contact Already exists';
			} else if($coderes == '2201') {
				$values['contact'] = 'Contact Already exists and is not owned by you';
			} else {
				$values["error"] = "RegisterDomain/contact-check($contactid): Code ($coderes) $msg";
				return $values;
			}

			$values["status"] = $msg;

			# If contact still isn't available attempt to add a random time again rehash and return
			if ($contactAvailable == 0) {
				$contactAvailable = substr(md5($prehash . time() . rand(0, 1000000) . $count), 0,15);
			}

			if ($count >= 10) {
				break;
			}

			$count++;
		}

		return $contactid;

	} catch (Exception $e) {
		$values["error"] = 'RegisterDomain/EPP: '.$e->getMessage().$e->getCode().$e->getLine();
		return $values;
	}

}



# Function to register domain
function dotafricaepp_RegisterDomain($params) {
	# Grab varaibles
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");
	$regperiod = $params["regperiod"];

	# Get registrant details
	$RegistrantFirstName = $params["firstname"];
	$RegistrantLastName = $params["lastname"];
	$RegistrantAddress1 = $params["address1"];
	$RegistrantAddress2 = $params["address2"];
	$RegistrantCity = $params["city"];
	$RegistrantStateProvince = $params["state"];
	$RegistrantPostalCode = $params["postcode"];
	$RegistrantCountry = $params["country"];
	$RegistrantEmailAddress = $params["email"];
	$RegistrantPhone = $params["fullphonenumber"];
	# Get admin details
	$AdminFirstName = $params["adminfirstname"];
	$AdminLastName = $params["adminlastname"];
	$AdminAddress1 = $params["adminaddress1"];
	$AdminAddress2 = $params["adminaddress2"];
	$AdminCity = $params["admincity"];
	$AdminStateProvince = $params["adminstate"];
	$AdminPostalCode = $params["adminpostcode"];
	$AdminCountry = $params["admincountry"];
	$AdminEmailAddress = $params["adminemail"];
	$AdminPhone = $params["adminfullphonenumber"];

	# Registrar contactid hash
	$contactid = substr(md5($domain), 0,15);

	# Admin/Tech/Billing contactid hash
	$additional_contactid = substr(md5($AdminFirstName.$AdminLastName), 0,15);

	# Generate XML for namseverss
	if ($nameserver1 = $params["ns1"]) {
		$add_hosts = '
<domain:hostAttr>
	<domain:hostName>'.$nameserver1.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver2 = $params["ns2"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver2.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver3 = $params["ns3"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver3.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver4 = $params["ns4"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver4.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver5 = $params["ns5"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver5.'</domain:hostName>
</domain:hostAttr>
';
	}

	# Get client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# registry.net.za expects 'coza' as the password
		$pw = "coza";

		# Send registration
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:contact="urn:ietf:params:xml:ns:contact-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:create>
			<contact:create xsi:schemaLocation="urn:ietf:params:xml:ns:contact-1.0 contact-1.0.xsd">
				<contact:id>'.$contactid.'</contact:id>
				<contact:postalInfo type="loc">
					<contact:name>'.$RegistrantFirstName.' '.$RegistrantLastName.'</contact:name>
					<contact:addr>
						<contact:street>'.$RegistrantAddress1.'</contact:street>
						<contact:street>'.$RegistrantAddress2.'</contact:street>
						<contact:city>'.$RegistrantCity.'</contact:city>
						<contact:sp>'.$RegistrantStateProvince.'</contact:sp>
						<contact:pc>'.$RegistrantPostalCode.'</contact:pc>
						<contact:cc>'.$RegistrantCountry.'</contact:cc>
					</contact:addr>
				</contact:postalInfo>
				<contact:voice>'.$RegistrantPhone.'</contact:voice>
				<contact:fax></contact:fax>
				<contact:email>'.$RegistrantEmailAddress.'</contact:email>
				<contact:authInfo>
					<contact:pw>'.$pw.'</contact:pw>
				</contact:authInfo>
			</contact:create>
		</epp:create>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RegisterDomain', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes == '1000') {
			$values['contact'] = 'Contact Created';
		} else if($coderes == '2302') {
			$values['contact'] = 'Contact Already exists';
		} else {
			$values["error"] = "RegisterDomain/contact-create($contactid): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

		$domaincreate = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xmlns:cozadomain="http://co.za/epp/extensions/cozadomain-1-0"
		xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:create>
			<domain:create xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name>'.$domain.'</domain:name>
				<domain:ns>'.$add_hosts.'</domain:ns>
				<domain:registrant>'.$contactid.'</domain:registrant>';

		# Some SLDs require the presence of admin, billing and tech contacts.
		# Check if our domain has the requirement and get/create the required contact id.
		$_server = _dotafricaepp_SldLookup($domain);

		if ($_server['additional_contacts'])  {
			# Generate a random password to be used for the additional contact
			$pw = substr(md5($domain . time() . rand(0, 1000000)), 0,15);

			$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:contact="urn:ietf:params:xml:ns:contact-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:create>
			<contact:create xsi:schemaLocation="urn:ietf:params:xml:ns:contact-1.0 contact-1.0.xsd">
				<contact:id>'.$additional_contactid.'</contact:id>
				<contact:postalInfo type="loc">
					<contact:name>'.$AdminFirstName.' '.$AdminLastName.'</contact:name>
					<contact:addr>
						<contact:street>'.$AdminAddress1.'</contact:street>
						<contact:street>'.$AdminAddress2.'</contact:street>
						<contact:city>'.$AdminCity.'</contact:city>
						<contact:sp>'.$AdminStateProvince.'</contact:sp>
						<contact:pc>'.$AdminPostalCode.'</contact:pc>
						<contact:cc>'.$AdminCountry.'</contact:cc>
					</contact:addr>
				</contact:postalInfo>
				<contact:voice>'.$AdminPhone.'</contact:voice>
				<contact:fax></contact:fax>
				<contact:email>'.$AdminEmailAddress.'</contact:email>
				<contact:authInfo>
					<contact:pw>'.$pw.'</contact:pw>
				</contact:authInfo>
			</contact:create>
		</epp:create>
	</epp:command>
</epp:epp>
');

			# Parse XML result
			$doc= new DOMDocument();
			$doc->loadXML($request);
			logModuleCall('DotAfricaepp', 'RegisterDomain', $xml, $request);

			# Pull off status
			$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
			$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
			if($coderes == '1000') {
				$values['contact'] = 'Contact Created';
			} else if($coderes == '2302') {
				$values['contact'] = 'Contact Already exists';
			} else {
				$values["error"] = "RegisterDomain/admincontact-create($additional_contactid): Code ($coderes) $msg";
				return $values;
			}

			$domaincreate .= '
				<domain:contact type="admin">'.$additional_contactid.'</domain:contact>
				<domain:contact type="tech">'.$additional_contactid.'</domain:contact>
				<domain:contact type="billing">'.$additional_contactid.'</domain:contact>';
		}

		# registry.net.za expects 'coza' as the password
		$pw = "coza";

		$domaincreate .= '
				<domain:authInfo>
					<domain:pw>'.$pw.'</domain:pw>
				</domain:authInfo>
			</domain:create>
		</epp:create>
		<epp:extension>
			<cozadomain:create>
				<cozadomain:autorenew>false</cozadomain:autorenew>
			</cozadomain:create>
		</epp:extension>
	</epp:command>
</epp:epp>
';

		$request = $client->request($domaincreate);
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RegisterDomain', $domaincreate, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "RegisterDomain/domain-create($domain): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;
	} catch (Exception $e) {
		$values["error"] = 'RegisterDomain/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}

# Function to Transfer domain V2
/*function dotafricaepp_TransferDomain($params) {
	# Grab varaibles
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");
	$regperiod = $params["regperiod"];

	# Get registrant details
	$RegistrantFirstName = $params["firstname"];
	$RegistrantLastName = $params["lastname"];
	$RegistrantAddress1 = $params["address1"];
	$RegistrantAddress2 = $params["address2"];
	$RegistrantCity = $params["city"];
	$RegistrantStateProvince = $params["state"];
	$RegistrantPostalCode = $params["postcode"];
	$RegistrantCountry = $params["country"];
	$RegistrantEmailAddress = $params["email"];
	$RegistrantPhone = $params["fullphonenumber"];
	# Get admin details
	$AdminFirstName = $params["adminfirstname"];
	$AdminLastName = $params["adminlastname"];
	$AdminAddress1 = $params["adminaddress1"];
	$AdminAddress2 = $params["adminaddress2"];
	$AdminCity = $params["admincity"];
	$AdminStateProvince = $params["adminstate"];
	$AdminPostalCode = $params["adminpostcode"];
	$AdminCountry = $params["admincountry"];
	$AdminEmailAddress = $params["adminemail"];
	$AdminPhone = $params["adminfullphonenumber"];

	# Registrar contactid hash
	$contactid = substr(md5($domain), 0,15);

	# Admin/Tech/Billing contactid hash
	$additional_contactid = substr(md5($AdminFirstName.$AdminLastName), 0,15);

	# Generate XML for namseverss
	if ($nameserver1 = $params["ns1"]) {
		$add_hosts = '
<domain:hostAttr>
	<domain:hostName>'.$nameserver1.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver2 = $params["ns2"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver2.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver3 = $params["ns3"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver3.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver4 = $params["ns4"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver4.'</domain:hostName>
</domain:hostAttr>
';
	}
	if ($nameserver5 = $params["ns5"]) {
		$add_hosts .= '
<domain:hostAttr>
	<domain:hostName>'.$nameserver5.'</domain:hostName>
</domain:hostAttr>
';
	}

	# Get client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# registry.net.za expects 'coza' as the password
		$pw = "coza";

		# Send registration
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:contact="urn:ietf:params:xml:ns:contact-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:transfer>
			<contact:create xsi:schemaLocation="urn:ietf:params:xml:ns:contact-1.0 contact-1.0.xsd">
				<contact:id>'.$contactid.'</contact:id>
				<contact:postalInfo type="loc">
					<contact:name>'.$RegistrantFirstName.' '.$RegistrantLastName.'</contact:name>
					<contact:addr>
						<contact:street>'.$RegistrantAddress1.'</contact:street>
						<contact:street>'.$RegistrantAddress2.'</contact:street>
						<contact:city>'.$RegistrantCity.'</contact:city>
						<contact:sp>'.$RegistrantStateProvince.'</contact:sp>
						<contact:pc>'.$RegistrantPostalCode.'</contact:pc>
						<contact:cc>'.$RegistrantCountry.'</contact:cc>
					</contact:addr>
				</contact:postalInfo>
				<contact:voice>'.$RegistrantPhone.'</contact:voice>
				<contact:fax></contact:fax>
				<contact:email>'.$RegistrantEmailAddress.'</contact:email>
				<contact:authInfo>
					<contact:pw>'.$pw.'</contact:pw>
				</contact:authInfo>
			</contact:create>
		</epp:create>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RegisterDomain', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes == '1000') {
			$values['contact'] = 'Contact Created';
		} else if($coderes == '2302') {
			$values['contact'] = 'Contact Already exists';
		} else {
			$values["error"] = "RegisterDomain/contact-create($contactid): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

		$domaincreate = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xmlns:cozadomain="http://co.za/epp/extensions/cozadomain-1-0"
		xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:transfer>
			<domain:create xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name>'.$domain.'</domain:name>
				<domain:ns>'.$add_hosts.'</domain:ns>
				<domain:registrant>'.$contactid.'</domain:registrant>';

		# Some SLDs require the presence of admin, billing and tech contacts.
		# Check if our domain has the requirement and get/create the required contact id.
		$_server = _dotafricaepp_SldLookup($domain);

		if ($_server['additional_contacts'])  {
			# Generate a random password to be used for the additional contact
			$pw = substr(md5($domain . time() . rand(0, 1000000)), 0,15);

			$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:contact="urn:ietf:params:xml:ns:contact-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:create>
			<contact:create xsi:schemaLocation="urn:ietf:params:xml:ns:contact-1.0 contact-1.0.xsd">
				<contact:id>'.$additional_contactid.'</contact:id>
				<contact:postalInfo type="loc">
					<contact:name>'.$AdminFirstName.' '.$AdminLastName.'</contact:name>
					<contact:addr>
						<contact:street>'.$AdminAddress1.'</contact:street>
						<contact:street>'.$AdminAddress2.'</contact:street>
						<contact:city>'.$AdminCity.'</contact:city>
						<contact:sp>'.$AdminStateProvince.'</contact:sp>
						<contact:pc>'.$AdminPostalCode.'</contact:pc>
						<contact:cc>'.$AdminCountry.'</contact:cc>
					</contact:addr>
				</contact:postalInfo>
				<contact:voice>'.$AdminPhone.'</contact:voice>
				<contact:fax></contact:fax>
				<contact:email>'.$AdminEmailAddress.'</contact:email>
				<contact:authInfo>
					<contact:pw>'.$pw.'</contact:pw>
				</contact:authInfo>
			</contact:create>
		</epp:create>
	</epp:command>
</epp:epp>
');

			# Parse XML result
			$doc= new DOMDocument();
			$doc->loadXML($request);
			logModuleCall('DotAfricaepp', 'RegisterDomain', $xml, $request);

			# Pull off status
			$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
			$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
			if($coderes == '1000') {
				$values['contact'] = 'Contact Created';
			} else if($coderes == '2302') {
				$values['contact'] = 'Contact Already exists';
			} else {
				$values["error"] = "RegisterDomain/admincontact-create($additional_contactid): Code ($coderes) $msg";
				return $values;
			}

			$domaincreate .= '
				<domain:contact type="admin">'.$additional_contactid.'</domain:contact>
				<domain:contact type="tech">'.$additional_contactid.'</domain:contact>
				<domain:contact type="billing">'.$additional_contactid.'</domain:contact>';
		}

		# registry.net.za expects 'coza' as the password
		$pw = "coza";

		$domaincreate .= '
				<domain:authInfo>
					<domain:pw>'.$pw.'</domain:pw>
				</domain:authInfo>
			</domain:create>
		</epp:create>
		<epp:extension>
			<cozadomain:create>
				<cozadomain:autorenew>false</cozadomain:autorenew>
			</cozadomain:create>
		</epp:extension>
	</epp:command>
</epp:epp>
';

		$request = $client->request($domaincreate);
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RegisterDomain', $domaincreate, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "RegisterDomain/domain-create($domain): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;
	} catch (Exception $e) {
		$values["error"] = 'RegisterDomain/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}*/



# Function to transfer a domain
function dotafricaepp_TransferDomain($params) {
	# Grab variables
	$testmode = $params["TestMode"];
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");

	# Domain info
	$regperiod = $params["regperiod"];
	$transfersecret = $params["transfersecret"];
	$nameserver1 = $params["ns1"];
	$nameserver2 = $params["ns2"];
	# Registrant Details
	$RegistrantFirstName = $params["firstname"];
	$RegistrantLastName = $params["lastname"];
	$RegistrantAddress1 = $params["address1"];
	$RegistrantAddress2 = $params["address2"];
	$RegistrantCity = $params["city"];
	$RegistrantStateProvince = $params["state"];
	$RegistrantPostalCode = $params["postcode"];
	$RegistrantCountry = $params["country"];
	$RegistrantEmailAddress = $params["email"];
	$RegistrantPhone = $params["fullphonenumber"];
	# Admin details
	$AdminFirstName = $params["adminfirstname"];
	$AdminLastName = $params["adminlastname"];
	$AdminAddress1 = $params["adminaddress1"];
	$AdminAddress2 = $params["adminaddress2"];
	$AdminCity = $params["admincity"];
	$AdminStateProvince = $params["adminstate"];
	$AdminPostalCode = $params["adminpostcode"];
	$AdminCountry = $params["admincountry"];
	$AdminEmailAddress = $params["adminemail"];
	$AdminPhone = $params["adminphonenumber"];
	# Our details
	$contactid = substr(md5($domain), 0,15);

	$pw = $params['eppcode'];

	# Get client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# Initiate transfer
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:transfer op="request">
			<domain:transfer>
				<domain:name>'.$domain.'</domain:name>
                <domain:authInfo>
                    <domain:pw>'.$pw.'</domain:pw>
                </domain:authInfo>
			</domain:transfer>
		</epp:transfer>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'TransferDomain', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# We should get a 1001 back
		if($coderes != '1001') {
			$values["error"] = "TransferDomain/domain-transfer($domain): Code ($coderes) $msg";
			return $values;
		}

	} catch (Exception $e) {
		$values["error"] = 'TransferDomain/EPP: '.$e->getMessage();
		return $values;
	}

	$values["status"] = $msg;

	return $values;
}



# Function to renew domain
function dotafricaepp_RenewDomain($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];
	$regperiod = $params["regperiod"];
	$domain = strtolower("$sld.$tld");


	# Get client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# Send renewal request
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</epp:info>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RenewDomain', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "RenewDomain/domain-info($domain)): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

		# Sanitize expiry date
		$expdate = substr($doc->getElementsByTagName('exDate')->item(0)->nodeValue,0,10);
		if (empty($expdate)) {
			$values["error"] = "RenewDomain/domain-info($domain): Domain info not available";
			return $values;
		}

		# Send request to renew
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:renew>
			<domain:renew>
				<domain:name>'.$domain.'</domain:name>
				<domain:curExpDate>'.$expdate.'</domain:curExpDate>
			</domain:renew>
		</epp:renew>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RenewDomain', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "RenewDomain/domain-renew($domain,$expdate): Code (".$coderes.") ".$msg;
			return $values;
		}

		$values["status"] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'RenewDomain/EPP: '.$e->getMessage();
		return $values;
	}

	# If error, return the error message in the value below
	return $values;
}

function _getContactDetails($domain, $client = null) {
	# Get client instance
	try {
		if (!isset($client)) {
			$client = _dotafricaepp_Client($domain);
		}

		# Grab domain info
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</epp:info>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', '_GetContactDetails', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1000') {
			$values["error"] = "_GetContactDetails/domain-info($domain): Code (".$coderes.") ".$msg;
			return $values;
		}

		# Grab contact info
		$registrant = $doc->getElementsByTagName('registrant')->item(0)->nodeValue;
		if (empty($registrant)) {
			$values["error"] = "_GetContactDetails/domain-info($domain): Registrant info not available";
			return $values;
		}

		# Grab contact info
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">
	<epp:command>
		<epp:info>
			<contact:info>
				<contact:id>'.$registrant.'</contact:id>
			</contact:info>
		</epp:info>
	</epp:command>
</epp:epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', '_GetContactDetails', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check result
		if($coderes != '1000') {
			$values["error"] = "_GetContactDetails/contact-info($registrant): Code (".$coderes.") ".$msg;
			return $values;
		}

		$nodes = $doc->getElementsByTagName('postalInfo');
		for ($i = 0; ($i < $nodes->length); $i++) {
			if ($nodes->item($i)->getAttributeNode('type')->nodeValue == 'loc') {
				$childNodes = $nodes->item($i);
				$results["Registrant"]["Contact Name"] = $childNodes->getElementsByTagName('name')->item(0)->nodeValue;
				$results["Registrant"]["Organisation"] = $childNodes->getElementsByTagName('org')->item(0)->nodeValue;
				$results["Registrant"]["Address line 1"] = $childNodes->getElementsByTagName('street')->item(0)->nodeValue;
				$results["Registrant"]["Address line 2"] = $childNodes->getElementsByTagName('street')->item(1)->nodeValue;
				$results["Registrant"]["TownCity"] = $childNodes->getElementsByTagName('city')->item(0)->nodeValue;
				$results["Registrant"]["State"] = $childNodes->getElementsByTagName('sp')->item(0)->nodeValue;
				$results["Registrant"]["Zip code"] = $childNodes->getElementsByTagName('pc')->item(0)->nodeValue;
				$results["Registrant"]["Country Code"] = $childNodes->getElementsByTagName('cc')->item(0)->nodeValue;
			}
		}

		$results["Registrant"]["Phone"] = $doc->getElementsByTagName('voice')->item(0)->nodeValue;
		$results["Registrant"]["Email"] = $doc->getElementsByTagName('email')->item(0)->nodeValue;

		return $results;
	} catch (Exception $e) {
		$values["error"] = 'GetContactDetails/EPP: '.$e->getMessage();
		return $values;
	}
}



# Function to grab contact details
function dotafricaepp_GetContactDetails($params) {
	# Grab variables
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");

	# Fetching contact details
	$results = _getContactDetails($domain);

	# If there was an error return it
	if (isset($results["error"])) {
		return $results;
	}

	# What we going to do here is make sure all the attirbutes we return back are set
	# If we don't do this WHMCS won't display the options for editing
	foreach (
			array("Contact Name","Organisation","Address line 1","Address line 2","TownCity","State","Zip code","Country Code","Phone","Email")
			as $item
	) {
		# Check if the item is set
		if ($results["Registrant"][$item] == "") {
			# Just set it to -
			$values["Registrant"][$item] = "-";
		} else {
			# We setting this here so we maintain the right order, else we get the set
			# things first and all the unsets second, which looks crap
			$values["Registrant"][$item] = $results["Registrant"][$item];
		}
	}

	return $values;
}



/**
 * Catching all the different variations of params as encountered by clients
 * This has only been reported to have occured in WHMCS 5.2 and 5.3.7
 * References to the occurences of this particular issue can be found at the followinf urls:
 * https://gitlab.devlabs.linuxassist.net/awit-whmcs/whmcs-coza-epp/issues/11#note_630
 * http://lists.linuxassist.net/pipermail/whmcs-coza-epp_lists.linuxassist.net/2013-April/000319.html
 */
function _getContactDetailsFromParams($params) {
	$results = array();

	$results["Contact Name"] = $params["contactdetails"]["Registrant"]["Contact Name"];

	// Catching different variations of Organisation
	if (isset($params["contactdetails"]["Registrant"]["Organisation"])) {
		$results["Organisation"] = $params["contactdetails"]["Registrant"]["Organisation"];
	} else if (isset($params["contactdetails"]["Registrant"]["Company Name"])) {
		$results["Organisation"] = $params["contactdetails"]["Registrant"]["Company Name"];
	} else {
		$results["Organisation"] = "";
	}

	// Catching different variations of Address line 1
	if (isset($params["contactdetails"]["Registrant"]["Address line 1"])) {
		$results["Address line 1"] = $params["contactdetails"]["Registrant"]["Address line 1"];
	} else if (isset($params["contactdetails"]["Registrant"]["Address 1"])) {
		$results["Address line 1"] = $params["contactdetails"]["Registrant"]["Address 1"];
	} else {
		$results["Address line 1"] = "";
	}

	// Catching different variations of Address line 2
	if (isset($params["contactdetails"]["Registrant"]["Address line 2"])) {
		$results["Address line 2"] = $params["contactdetails"]["Registrant"]["Address line 2"];
	} else if (isset($params["contactdetails"]["Registrant"]["Address 2"])) {
		$results["Address line 2"] = $params["contactdetails"]["Registrant"]["Address 2"];
	} else {
		$results["Address line 2"] = "";
	}

	// Catching different variations of TownCity
	if (isset($params["contactdetails"]["Registrant"]["TownCity"])) {
		$results["TownCity"] = $params["contactdetails"]["Registrant"]["TownCity"];
	} else if (isset($params["contactdetails"]["Registrant"]["City"])) {
		$results["TownCity"] = $params["contactdetails"]["Registrant"]["City"];
	} else {
		$results["TownCity"] = "";
	}

	$results["State"] = $params["contactdetails"]["Registrant"]["State"];

	// Catching different variations of Postal Code
	if (isset($params["contactdetails"]["Registrant"]["Zip code"])) {
		$results["Zip code"] = $params["contactdetails"]["Registrant"]["Zip code"];
	} else if (isset($params["contactdetails"]["Registrant"]["ZIP Code"])) {
		$results["Zip code"] = $params["contactdetails"]["Registrant"]["ZIP Code"];
	} else if (isset($params["contactdetails"]["Registrant"]["Postcode"])) {
		$results["Zip code"] = $params["contactdetails"]["Registrant"]["Postcode"];
	} else {
		$results["Zip code"] = "";
	}

	// Catching different variations of Country Code
	if (isset($params["contactdetails"]["Registrant"]["Country Code"])) {
		$results["Country Code"] = $params["contactdetails"]["Registrant"]["Country Code"];
	} else if (isset($params["contactdetails"]["Registrant"]["Country"])) {
		$results["Country Code"] = $params["contactdetails"]["Registrant"]["Country"];
	} else {
		$results["Country Code"] = "";
	}

	$results["Phone"] = $params["contactdetails"]["Registrant"]["Phone"];
	$results["Email"] = $params["contactdetails"]["Registrant"]["Email"];

	return $results;
}



# Function to save contact details
function dotafricaepp_SaveContactDetails($params) {
	# Grab variables
	$tld = $params["tld"];
	$sld = $params["sld"];
	$domain = strtolower("$sld.$tld");
	# Registrant details

	$contactDetails = _getContactDetailsFromParams($params);

	$registrant_name = $contactDetails["Contact Name"];
	$registrant_org = $contactDetails["Organisation"];
	$registrant_address1 = $contactDetails["Address line 1"];
	$registrant_address2 = $contactDetails["Address line 2"];
	$registrant_town = $contactDetails["TownCity"];
	$registrant_state = $contactDetails["State"];
	$registrant_zipcode = $contactDetails["Zip code"];
	$registrant_countrycode = $contactDetails["Country Code"];
	$registrant_phone = $contactDetails["Phone"];
	#$registrant_fax = '',
	$registrant_email = $contactDetails["Email"];

	# Get client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# Grab domain info
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</epp:info>
	</epp:command>
</epp:epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'SaveContactDetails', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "SaveContactDetails/domain-info($domain): Code (".$coderes.") ".$msg;
			return $values;
		}

		$values["status"] = $msg;

		# Time to do the update
		$registrant = $doc->getElementsByTagName('registrant')->item(0)->nodeValue;

		# Save contact details
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:contact="urn:ietf:params:xml:ns:contact-1.0">
	<epp:command>
		<epp:update>
			<contact:update>
				<contact:id>'.$registrant.'</contact:id>
				<contact:chg>
					<contact:postalInfo type="loc">
						<contact:name>'.$registrant_name.'</contact:name>
						<contact:org>'.$registrant_org.'</contact:org>
						<contact:addr>
							<contact:street>'.$registrant_address1.'</contact:street>
							<contact:street>'.$registrant_address2.'</contact:street>
							<contact:city>'.$registrant_town.'</contact:city>
							<contact:sp>'.$registrant_state.'</contact:sp>
							<contact:pc>'.$registrant_zipcode.'</contact:pc>
							<contact:cc>'.$registrant_countrycode.'</contact:cc>
						</contact:addr>
						</contact:postalInfo>
						<contact:voice>'.$registrant_phone.'</contact:voice>
						<contact:fax></contact:fax>
						<contact:email>'.$registrant_email.'</contact:email>
				</contact:chg>
			</contact:update>
		</epp:update>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'SaveContactDetails', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1001') {
			$values["error"] = "SaveContactDetails/contact-update($registrant): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'SaveContactDetails/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



# NOT IMPLEMENTED
function dotafricaepp_GetEPPCode($params) {
	# Grab variables
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");

	$values["eppcode"] = '';

	# If error, return the error message in the value below
	//$values["error"] = 'error';
	return $values;
}



# Function to register nameserver
function dotafricaepp_RegisterNameserver($params) {
	# Grab varaibles
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$sld = $params["sld"];
	$tld = $params["tld"];
	$domain = strtolower("$sld.$tld");
	$nameserver = $params["nameserver"];
	$ipaddress = $params["ipaddress"];


	# Grab client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# Register nameserver
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:update>
			<domain:update>
				<domain:name>'.$domain.'</domain:name>
				<domain:add>
					<domain:ns>
						<domain:hostAttr>
							<domain:hostName>'.$nameserver.'</domain:hostName>
							<domain:hostAddr ip="v4">'.$ipaddress.'</domain:hostAddr>
						</domain:hostAttr>
					</domain:ns>
				</domain:add>
			</domain:update>
		</epp:update>
	</epp:command>
</epp:epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RegisterNameserver', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check if result is ok
		if($coderes != '1001') {
			$values["error"] = "RegisterNameserver/domain-update($domain): Code ($coderes) $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}


	return $values;
}



# Modify nameserver
function dotafricaepp_ModifyNameserver($params) {
	# Grab variables
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	$domain = strtolower("$sld.$tld");
	$nameserver = $params["nameserver"];
	$currentipaddress = $params["currentipaddress"];
	$newipaddress = $params["newipaddress"];


	# Grab client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# Modify nameserver
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:update>
			<domain:update>
				<domain:name>'.$domain.'</domain:name>
				<domain:add>
					<domain:ns>
						<domain:hostAttr>
							<domain:hostName>'.$nameserver.'</domain:hostName>
							<domain:hostAddr ip="v4">'.$newipaddress.'</domain:hostAddr>
						</domain:hostAttr>
					</domain:ns>
				</domain:add>
			</domain:update>
		</epp:update>
	</epp:command>
</epp:epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'ModifyNameserver', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check if result is ok
		if($coderes != '1001') {
			$values["error"] = "ModifyNameserver/domain-update($domain): Code ($coderes) $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}


# Delete nameserver
function dotafricaepp_DeleteNameserver($params) {
	# Grab variables
	$username = $params["Username"];
	$password = $params["Password"];
	$testmode = $params["TestMode"];
	$tld = $params["tld"];
	$sld = $params["sld"];
	$domain = strtolower("$sld.$tld");
	$nameserver = $params["nameserver"];


	# Grab client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# If we were given hostname. blow away all of the stuff behind it and allow us to remove hostname
		$nameserver = preg_replace('/\.\.\S+/','',$nameserver);

		# Delete nameserver
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:update>
			<domain:update>
				<domain:name>'.$domain.'</domain:name>
				<domain:rem>
					<domain:ns>
						<domain:hostAttr>
							<domain:hostName>'.$nameserver.'</domain:hostName>
						</domain:hostAttr>
					</domain:ns>
				</domain:rem>
			</domain:update>
		</epp:update>
	</epp:command>
</epp:epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'DeleteNameserver', $xml, $request);

		# Pull off status
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check if result is ok
		if($coderes != '1001') {
			$values["error"] = "DeleteNameserver/domain-update($domain): Code ($coderes) $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'SaveNameservers/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



# Function to return meaningful message from response code
function _dotafricaepp_message($code) {

	return "Code $code";

}



# Ack a POLL message
function _dotafricaepp_ackpoll($client,$msgid) {
	# Ack poll message
	$request = $client->request($xml = '
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
	<command>
		<poll op="ack" msgID="'.$msgid.'"/>
	</command>
</epp>
');

	# Decipher XML
	$doc = new DOMDocument();
	$doc->loadXML($request);
	logModuleCall('DotAfricaepp', 'ackpoll', $xml, $request);

	$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
	$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

	# Check result
	if($coderes != '1301' && $coderes != '1300' && $coderes != 1000) {
		throw new Exception("ackpoll/poll-ack($id): Code ($coderes) $msg");
	}
}



# Helper function to centrally provide data and information about different SLDs.
function _dotafricaepp_SldLookup($domain) {
	# TLD server data provided by ZACR
	$tldservers = array(
		'.africa' => array(
			'fqdn' => 'africa-epp.registry.net.za',
			'fqdn_test' => 'africa-otande.registry.net.za',
			'port' => 3121,
			'additional_contacts' => true
		),
	);

    return $tldservers['.africa'];
}



# Function to create internal DOT AFRICA EPP request
function _dotafricaepp_Client($domain=null) {
	# Setup include dir
	$include_path = ROOTDIR . '/modules/registrars/dotafricaepp';
	set_include_path($include_path . PATH_SEPARATOR . get_include_path());
	# Include EPP stuff we need
	require_once 'Net/EPP/Client.php';
	require_once 'Net/EPP/Protocol.php';

	# Grab module parameters
	$params = getregistrarconfigoptions('dotafricaepp');

	# Set server address and port based on parsed domain name
	$_server = _dotafricaepp_SldLookup($domain);

	# Check if module parameters are sane
	if (empty($params['Username']) || empty($params['Password'])) {
		throw new Exception('System configuration error(1), please contact your provider');
	}

	# Create SSL context
	# Create SSL context
        $arrContextOptions=array(
		    "ssl"=>array(
		        "verify_peer"=>true,
		        "verify_peer_name"=>true,
		    ),
		); 
	$context = stream_context_create($arrContextOptions);
	# Are we using ssl?
	$use_ssl = false;
	if (!empty($params['SSL']) && $params['SSL'] == 'on') {
		$use_ssl = true;
	}
	# Set certificate if we have one
	if ($use_ssl && !empty($params['Certificate'])) {
		if (!file_exists($params['Certificate'])) {
			throw new Exception("System configuration error(3), please contact your provider");
		}
		# Set client side certificate
		stream_context_set_option($context, 'ssl', 'local_cert', $params['Certificate']);
	}

	# Create EPP client
	$client = new Net_EPP_Client();

	# Connect
    $epp_domain = (!empty($params['Test']) && $params['Test'] == 'on') ? $_server['fqdn_test'] : $_server['fqdn'];
	$res = $client->connect($epp_domain, $_server['port'], 60, $use_ssl, $context);

	# Perform login
	$request = $client->request($xml = '
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
	<command>
		<login>
			<clID>'.$params['Username'].'</clID>
			<pw>'.$params['Password'].'</pw>
			<options>
			<version>1.0</version>
			<lang>en</lang>
			</options>
			<svcs>
				<objURI>urn:ietf:params:xml:ns:domain-1.0</objURI>
				<objURI>urn:ietf:params:xml:ns:contact-1.0</objURI>
			</svcs>
		</login>
	</command>
</epp>
');
	logModuleCall('DotAfricaepp', 'Connect', $xml, $request);

	return $client;
}



function dotafricaepp_TransferSync($params) {
	$domainid = $params['domainid'];
	$domain = $params['domain'];
	$sld = $params['sld'];
	$tld = $params['tld'];
	$domain = strtolower("$sld.$tld");
	$registrar = $params['registrar'];
	$regperiod = $params['regperiod'];
	$status = $params['status'];
	$dnsmanagement = $params['dnsmanagement'];
	$emailforwarding = $params['emailforwarding'];
	$idprotection = $params['idprotection'];

	# Other parameters used in your _getConfigArray() function would also be available for use in this function

	# Grab domain info
	try {
		$client = _dotafricaepp_Client($domain);
		# Grab domain info
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</epp:info>
	</epp:command>
</epp:epp>
');

		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'TransferSync', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check result
		if ($coderes == '2303') {
			$values['error'] = "TransferSync/domain-info($domain): Domain not found";
			return $values;
		} else if ($coderes != '1000') {
			$values['error'] = "TransferSync/domain-info($domain): Code("._dotafricaepp_message($coderes).") $msg";
			return $values;
		}

		# Check if we can get a status back
		if ($doc->getElementsByTagName('status')->item(0)) {
			$statusres = $doc->getElementsByTagName('status')->item(0)->getAttribute('s');
			$createdate = substr($doc->getElementsByTagName('crDate')->item(0)->nodeValue,0,10);
			$nextduedate = substr($doc->getElementsByTagName('exDate')->item(0)->nodeValue,0,10);
		} else {
			$values['error'] = "TransferSync/domain-info($domain): Domain not found";
			return $values;
		}

		$values['status'] = $msg;

		# Check status and update
		if ($statusres == "ok") {
			$values['completed'] = true;

		} else {
			$values['error'] = "TransferSync/domain-info($domain): Unknown status code '$statusres' (File a bug report here: http://gitlab.devlabs.linuxassist.net/awit-whmcs/whmcs-coza-epp/issues/new)";
		}

		$values['expirydate'] = $nextduedate;

	} catch (Exception $e) {
		$values["error"] = 'TransferSync/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



function dotafricaepp_RecreateContact($params) {
	# Grab variables
	$tld = $params["tld"];
	$sld = $params["sld"];
	$domain = strtolower("$sld.$tld");

	# Get client instance
	try {
		$client = _dotafricaepp_Client($domain);

		# Fetching contact details
		$contact = _getContactDetails($domain, $client);

		# If there was an error return it
		if (isset($contact["error"])) {
			return $contact;
		}

		# Check for available contact id
		$registrant = _dotafricaepp_CheckContact($domain);

		# Generate a random password for the contact
		$pw = substr(md5($domain . time() . rand(0, 1000000)), 0,15);

		# Recreate contact
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:contact="urn:ietf:params:xml:ns:contact-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:create>
			<contact:create xsi:schemaLocation="urn:ietf:params:xml:ns:contact-1.0 contact-1.0.xsd">
				<contact:id>'.$registrant.'</contact:id>
				<contact:postalInfo type="loc">
				<contact:name>'.$contact["Registrant"]["Contact Name"].'</contact:name>
				<contact:org>'.$contact["Registrant"]["Organisation"].'</contact:org>
				<contact:addr>
					<contact:street>'.$contact["Registrant"]["Address line 1"].'</contact:street>
					<contact:street>'.$contact["Registrant"]["Address line 2"].'</contact:street>
					<contact:city>'.$contact["Registrant"]["TownCity"].'</contact:city>
					<contact:sp>'.$contact["Registrant"]["State"].'</contact:sp>
					<contact:pc>'.$contact["Registrant"]["Zip code"].'</contact:pc>
					<contact:cc>'.$contact["Registrant"]["Country Code"].'</contact:cc>
				</contact:addr>
				</contact:postalInfo>
				<contact:voice>'.$contact["Registrant"]["Phone"].'</contact:voice>
				<contact:fax></contact:fax>
				<contact:email>'.$contact["Registrant"]["Email"].'</contact:email>
				<contact:authInfo>
					<contact:pw>'.$pw.'</contact:pw>
				</contact:authInfo>
			</contact:create>
		</epp:create>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RecreateContact', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1000') {
			$values["error"] = "RecreateContact/contact-create($registrant): Code ($coderes) $msg";
			return $values;
		}

		$values["status"] = $msg;

		# Update domain registrant
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:update>
			<domain:update>
				<domain:name>'.$domain.'</domain:name>
				<domain:chg>
					<domain:registrant>'.$registrant.'</domain:registrant>
				</domain:chg>
			</domain:update>
		</epp:update>
	</epp:command>
</epp:epp>
');
		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RecreateContact', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		if($coderes != '1001') {
			$values["error"] = "RecreateContact/domain-info($domain): Code (".$coderes.") ".$msg;
			return $values;
		}

		$values["status"] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'RecreateContact/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



function dotafricaepp_Sync($params) {
	$domainid = $params['domainid'];
	$domain = $params['domain'];
	$sld = $params['sld'];
	$tld = $params['tld'];
	$domain = strtolower("$sld.$tld");
	$registrar = $params['registrar'];
	$regperiod = $params['regperiod'];
	$status = $params['status'];
	$dnsmanagement = $params['dnsmanagement'];
	$emailforwarding = $params['emailforwarding'];
	$idprotection = $params['idprotection'];

	# Other parameters used in your _getConfigArray() function would also be available for use in this function

	# Grab domain info
	try {
		$client = _dotafricaepp_Client($domain);
		# Grab domain info
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:info>
			<domain:info xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name hosts="all">'.$domain.'</domain:name>
			</domain:info>
		</epp:info>
	</epp:command>
</epp:epp>
');

		$doc= new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'Sync', $xml, $request);

		# Initialize the owningRegistrar which will contain the owning registrar
		# The <domain:clID> element contains the unique identifier of the registrar that owns the domain.
		$owningRegistrar = NULL;

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;
		# Check result
		if ($coderes == '2303') {
			# Code 2303, domain not found
			$values['error'] = "TransferSync/domain-info($domain): Domain not found";
			return $values;
		} else if ($coderes == '1000') {
			# Code 1000, success
			if (
				$doc->getElementsByTagName('infData') &&
				$doc->getElementsByTagName('infData')->item(0)->getElementsByTagName('ns')->item(0) &&
				$doc->getElementsByTagName('infData')->item(0)->getElementsByTagName('clID')
			) {
				$owningRegistrar = $doc->getElementsByTagName('infData')->item(0)->getElementsByTagName('clID')->item(0)->nodeValue;
			}
		} else {
			$values['error'] = "Sync/domain-info($domain): Code("._dotafricaepp_message($coderes).") $msg";
			return $values;
		}

		# Check if we can get a status back
		if ($doc->getElementsByTagName('status')->item(0)) {
			$statusres = $doc->getElementsByTagName('status')->item(0)->getAttribute('s');
			$createdate = substr($doc->getElementsByTagName('crDate')->item(0)->nodeValue,0,10);
			$nextduedate = substr($doc->getElementsByTagName('exDate')->item(0)->nodeValue,0,10);
		} else if (!empty($owningRegistrar) && $owningRegistrar != $username) {
			# If we got an owningRegistrar back and we're not the owning registrar, return error
			$values['error'] = "Sync/domain-info($domain): Domain belongs to a different registrar, (owning registrar: $owningRegistrar, your registrar: $username)";
			return $values;
		} else {
			$values['error'] = "Sync/domain-info($domain): Domain not found";
			return $values;
		}

		$values['status'] = $msg;

		# Check status and update
		if ($statusres == "ok") {
			$values['active'] = true;

		} elseif ($statusres == "pendingUpdate") {

		} elseif ($statusres == "serverHold") {

		} elseif ($statusres == "expired" || $statusres == "pendingDelete" || $statusres == "inactive") {
			$values['expired'] = true;

		} else {
			$values['error'] = "Sync/domain-info($domain): Unknown status code '$statusres' (File a bug report here: http://gitlab.devlabs.linuxassist.net/awit-whmcs/whmcs-coza-epp/issues/new)";
		}

		$values['expirydate'] = $nextduedate;

	} catch (Exception $e) {
		$values["error"] = 'Sync/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



function dotafricaepp_RequestDelete($params) {
	$sld = $params['sld'];
	$tld = $params['tld'];
	$domain = strtolower("$sld.$tld");

	# Grab domain info
	try {
		$client = _dotafricaepp_Client($domain);

		# Grab domain info
		$request = $client->request($xml = '
<epp:epp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:epp="urn:ietf:params:xml:ns:epp-1.0"
		xmlns:domain="urn:ietf:params:xml:ns:domain-1.0" xsi:schemaLocation="urn:ietf:params:xml:ns:epp-1.0 epp-1.0.xsd">
	<epp:command>
		<epp:delete>
			<domain:delete xsi:schemaLocation="urn:ietf:params:xml:ns:domain-1.0 domain-1.0.xsd">
				<domain:name>'.$domain.'</domain:name>
			</domain:delete>
		</epp:delete>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RequestDelete', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1001') {
			$values['error'] = 'RequestDelete/domain-info('.$domain.'): Code('._dotafricaepp_message($coderes).") $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'RequestDelete/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



function dotafricaepp_ApproveTransfer($params) {
	$sld = $params['sld'];
	$tld = $params['tld'];
	$domain = strtolower("$sld.$tld");

	# Grab domain info
	try {
		$client = _dotafricaepp_Client($domain);

		# Grab domain info
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:transfer op="approve">
			<domain:transfer>
				<domain:name>'.$domain.'</domain:name>
			</domain:transfer>
		</epp:transfer>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'ApproveTransfer', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1000') {
			$values['error'] = 'ApproveTransfer/domain-info('.$domain.'): Code('._dotafricaepp_message($coderes).") $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'ApproveTransfer/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



function dotafricaepp_CancelTransferRequest($params) {
	$sld = $params['sld'];
	$tld = $params['tld'];
	$domain = strtolower("$sld.$tld");

	# Grab domain info
	try {
		$client = _dotafricaepp_Client($domain);

		# Grab domain info
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:transfer op="cancel">
			<domain:transfer>
				<domain:name>'.$domain.'</domain:name>
			</domain:transfer>
		</epp:transfer>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'CancelTransferRequest', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1000') {
			$values['error'] = 'CancelTransferRequest/domain-info('.$domain.'): Code('._dotafricaepp_message($coderes).") $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'CancelTransferRequest/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}



function dotafricaepp_RejectTransfer($params) {
	$sld = $params['sld'];
	$tld = $params['tld'];
	$domain = strtolower("$sld.$tld");

	# Grab domain info
	try {
		$client = _dotafricaepp_Client($domain);

		# Grab domain info
		$request = $client->request($xml = '
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:domain="urn:ietf:params:xml:ns:domain-1.0">
	<epp:command>
		<epp:transfer op="reject">
			<domain:transfer>
				<domain:name>'.$domain.'</domain:name>
			</domain:transfer>
		</epp:transfer>
	</epp:command>
</epp:epp>
');

		# Parse XML result
		$doc = new DOMDocument();
		$doc->loadXML($request);
		logModuleCall('DotAfricaepp', 'RejectTransfer', $xml, $request);

		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		$msg = $doc->getElementsByTagName('msg')->item(0)->nodeValue;

		# Check result
		if($coderes != '1000') {
			$values['error'] = 'RejectTransfer/domain-info('.$domain.'): Code('._dotafricaepp_message($coderes).") $msg";
			return $values;
		}

		$values['status'] = $msg;

	} catch (Exception $e) {
		$values["error"] = 'RejectTransfer/EPP: '.$e->getMessage();
		return $values;
	}

	return $values;
}

