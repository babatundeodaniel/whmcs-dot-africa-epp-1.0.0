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
# http://allworldit.com



# Function to implement the cozaepp balance widget
function widget_dotafricaepp_balance($vars) {
	# Setup include dir
	$include_path = ROOTDIR . '/modules/registrars/dotafricaepp';
	set_include_path($include_path . PATH_SEPARATOR . get_include_path());
	# Include EPP stuff we need
	require_once 'dotafricaepp.php';
	# Include registrar functions aswell
	require_once ROOTDIR . '/includes/registrarfunctions.php';


	# Grab module parameters
	$params = getregistrarconfigoptions('dotafricaepp');

	# Set widget contents
	$title = "DotAfrica EPP Balance";
	$template = '<p align = "center" class="textblack"><strong>%s</strong></p>';

	# Request balance from registrar
	try {
		$client = _dotafricaepp_Client();

		$output = $client->request('
<epp:epp xmlns:epp="urn:ietf:params:xml:ns:epp-1.0" xmlns:contact="urn:ietf:params:xml:ns:contact-1.0"
		xmlns:cozacontact="http://co.za/epp/extensions/cozacontact-1-0">
	<epp:command>
		<epp:info>
			<contact:info>
				<contact:id>'.$params['Username'].'</contact:id>
			</contact:info>
		</epp:info>
		<epp:extension>
			<cozacontact:info>
				<cozacontact:balance>true</cozacontact:balance>
			</cozacontact:info>
		</epp:extension>
	</epp:command>
</epp:epp>
	');

		# Parse XML result
		$doc= new DOMDocument();
		$doc->loadXML($output);
		$coderes = $doc->getElementsByTagName('result')->item(0)->getAttribute('code');
		if ($coderes == '1000') {
			$balancestr = "Current registrar balance is R ".$doc->getElementsByTagName('balance')->item(0)->nodeValue;
		} else {
			$balancestr = 'ERROR: Parsing';
		}

	} catch (Exception $e) {
		return array('title'=>$title,'content'=>sprintf($template,"ERROR: ".$e->getMessage()));
	}

	return array('title'=>$title,'content'=>sprintf($template,$balancestr));
}

add_hook("AdminHomeWidgets",1,"widget_dotafricaepp_balance");

