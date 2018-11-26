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


# Official Website:
# http://devlabs.linuxassist.net/projects/whmcs-coza-epp

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



# This file brings in a few constants we need
require_once dirname(__FILE__) . '/../../../dbconnect.php';
# Setup include dir
$include_path = ROOTDIR . '/modules/registrars/dotafricaepp';
set_include_path($include_path . PATH_SEPARATOR . get_include_path());
# Include EPP stuff we need
require_once 'cozaepp.php';
# Additional functions we need
require_once ROOTDIR . '/includes/functions.php';
# Include registrar functions aswell
require_once ROOTDIR . '/includes/registrarfunctions.php';

require_once 'Net/EPP/Frame.php';
require_once 'Net/EPP/Frame/Command.php';
require_once 'Net/EPP/ObjectSpec.php';

# Grab module parameters
$params = getregistrarconfigoptions('cozaepp');

echo("COZA-EPP Poll Report\n");
echo("---------------------------------------------------\n");

# Request balance from registrar
try {
	$client = _cozaepp_Client();

	# Loop with message queue
	while (!$last) {
		# Request messages
		$request = $client->request('
<epp xmlns="urn:ietf:params:xml:ns:epp-1.0">
	<command>
		<poll op="req"/>
	</command>
</epp>
		');

		# Decode response
		$doc= new DOMDocument();
		$doc->loadXML($request);

		# Pull off code
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		if ($coderes == 1301 || $coderes == 1300) {
			$msgs = $doc->getElementsByTagName('msg');
			for ($m = 0; $m < $msgs->length; $m++) {
					echo "CODE: $coderes, MESSAGE: '".$msgs->item($m)->textContent."'\n";

					// Messages to ignore
					$ignored = array(
						'Command completed successfully; ack to dequeue',
						'Command completed successfully; no messages'
					);

					// Logging message
					if (!in_array(trim($msgs->item($m)->textContent), $ignored)) {
						$message = mysql_real_escape_string(trim($msgs->item($m)->textContent));
						$result = mysql_query("
							INSERT INTO mod_awit_cozaepp_messages
								(
									created,
									code,
									message
								)
							VALUES
								(
									now(),
									'$coderes',
									'$message'
								)
						");
						if (mysql_error($result)) {
							echo "ERROR: couldn't log epp message: " . mysql_error($result);
						}
					}
			}

			# This is the last one
			if ($coderes == 1300) {
				$last = 1;
			}

			$msgq = $doc->getElementsByTagName('msgQ')->item(0);
			if ($msgq) {
				$msgid = $doc->getElementsByTagName('msgQ')->item(0)->getAttribute('id');
				try {
					$res = _cozaepp_ackpoll($client,$msgid);
				} catch (Exception $e) {
					echo("ERROR: ".$e->getMessage()."\n");
				}
			}

		} else {
			$msgid = $doc->getElementsByTagName('svTRID')->item(0)->textContent;
			$msgs = $doc->getElementsByTagName('msg');
			for ($m = 0; $m < $msgs->length; $m++) {
				echo "\n";
					echo "UNKNOWN CODE: $coderes, MESSAGE: '".$msgs->item($m)->textContent."', ID: $msgid\n";
				echo $request;
				echo "\n\n";
			}

		}
	}

} catch (Exception $e) {
	echo("ERROR: ".$e->getMessage(). "\n");
	exit;
}

