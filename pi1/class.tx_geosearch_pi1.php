<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Kerstin Huppenbauer <k.huppenbauer@mapseven.de>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

require_once(PATH_tslib . 'class.tslib_pibase.php');



/**
 * Plugin 'Search within a radius' for the 'geosearch' extension.
 *
 * @author Kerstin Huppenbauer <k.huppenbauer@mapseven.de>
 * @package TYPO3
 * @subpackage tx_geosearch
 */
class tx_geosearch_pi1 extends tslib_pibase {
	var $prefixId = 'tx_geosearch_pi1'; // Same as class name
	var $scriptRelPath = 'pi1/class.tx_geosearch_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'geosearch'; // The extension key.


	/**
	 * The main method of the PlugIn
	 *
	 * @param string $content: The PlugIn content
	 * @param array $conf: The PlugIn configuration
	 * @return string The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->init($conf);
		$content = $this->getContent();
		if($this->piVars['postcode']) {
			$content .= $this->listObjects();
		}
		return $this->pi_wrapInBaseClass($content);
	}


	/**
	 * Initialises the Plugin
	 *
	 * @param array $conf: The PlugIn configuration
	 */
	function init($conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
		$this->pi_initPIflexForm();
  		$this->pi_checkCHash = TRUE;
  		$this->pi_USER_INT_obj = 1;

		$this->setConfig();
	}


	/**
	 * Sets the Configuration
	 *
	 */
	function setConfig() {
		$this->extFolder = 'uploads/tx_geosearch/';
		$datasource = 'geonames';
		$this->arrConf['datasource']['title'] = $datasource;
		switch ($datasource) {
			case 'geonames':
				$this->arrConf['datasource']['countryname'] = $this->conf['countryname'];
			break;
		}

		$this->arrConf['templateCode'] = $this->cObj->fileResource($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'template_file', 'sGeneral') ? $this->extFolder . $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'template_file', 'sGeneral') : $this->conf['templateFile']);
		$this->arrConf['cssFile'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'css_file', 'sGeneral') ? $this->extFolder . $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'css_file', 'sGeneral') : $this->conf['cssFile'];
		$GLOBALS['TSFE']->additionalHeaderData[] = '<link href="' . $this->arrConf['cssFile'] . '" rel="stylesheet" type="text/css" />';

		$this->arrConf['pid'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'startingpoint', 'sGeneral') ? $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'startingpoint', 'sGeneral') : $this->conf['startingPoint'] ? $this->conf['startingPoint'] : $GLOBALS['TSFE']->id;
		$this->arrConf['limit'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'limit', 'sList') ? $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'limit', 'sList') : $this->conf['limit'];

		$this->arrConf['zipComplete'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'zipComplete', 'sForm') ? $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'zipComplete', 'sForm') : $this->conf['zipComplete'];
		$countries = explode(',', $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'countries', 'sForm') ? $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'countries', 'sForm') : $this->conf['countries']);
		if (is_array($countries)) {
			foreach ($countries as $item) {
				$row = $this->pi_getRecord('static_countries', $item);
				$this->arrConf['countries'][$row[$this->arrConf['datasource']['countryname']]] = $row['cn_short_local'];
			}
		}
		$this->arrConf['maxRadius'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'maxRadius', 'sForm') ? $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'maxRadius', 'sForm') : $this->conf['maxRadius'];
		$this->arrConf['unit'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'unit', 'sForm');
		if ($this->piVars['submit_button']) {
			unset($this->piVars['first']);
		}
	}


	/**
	 * Lists the fitting objects from the given postcode,radius and country
	 *
	 * @return string
	 */
	function listObjects() {
		$this->piVars['radius'] = $this->arrConf['maxRadius'] ? t3lib_utility_Math::forceIntegerInRange($this->piVars['radius'], 1, $this->arrConf['maxRadius']) : $this->piVars['radius'];
		if ($this->arrConf['zipComplete']) {
			if ($this->validateZip()) {
				$coords = $this->getCoordinates();
				if (is_array($coords)) {
					$objects = $this->getObjects($coords);
				} else {
					$error = 'errorPostalcode';
				}
			} else {
				$error = 'errorPostalcode_invalid';
			}
		} else {
			if ($this->validateZip()) {
				$coords = $this->getCoordinates();
				if (is_array($coords)) {
					$objects = $this->getObjects($coords);
				} else {
					$error = 'errorPostalcode';
				}
			} else {
				$objects = $this->getObjects();
			}
		}
		if (isset($objects) && !empty($objects)) {
			$content = $this->getList($objects);
		} else {
			if (!isset($error)) {
				$error = 'errorObjects';
			}
			$content = $this->getError($error);
		}
		return $content;
	}


	/**
	 * wrapper function for geocoding
	 *
	 * @return array
	 */
	function getCoordinates() {
		switch ($this->arrConf['datasource']['title']) {
			case 'geonames':
				$coords = $this->geocodeGeonames();
			break;
			}
		return $coords;
	}


	/**
	 * Gets the coordinates of a given postcode from geonames-database
	 *
	 * @return array
	 */
	function geocodeGeonames() {
		$postcode = $GLOBALS['TYPO3_DB']->fullQuoteStr($this->piVars['postcode'], 'tx_geosearch_coordinates');
		$country = $GLOBALS['TYPO3_DB']->fullQuoteStr($this->piVars['country'], 'tx_geosearch_coordinates');
		$coordinates = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'latitude, longitude',
			'tx_geosearch_coordinates',
			'postal_code = ' . $postcode . ' AND country_code = ' . $country,
			'',
			''
		);
		return $coordinates[0];
	}


	/**
	 * wrapper function for getting objects depending on complete postcode or not
	 *
	 * @param string $data
	 * @return mixed
	 */
	function getObjects($data = '') {
		$sql['column'] = 'tx_geosearch_objects.*';
		$sql['table'] = 'tx_geosearch_objects, tx_geosearch_coordinates';
		$sql['where'] = 'tx_geosearch_objects.postcode = tx_geosearch_coordinates.postal_code AND tx_geosearch_coordinates.country_code = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($this->piVars['country'], 'tx_geosearch_coordinates') . ' AND tx_geosearch_objects.pid = ' . intval($this->arrConf['pid']);
		$sql['groupBy'] = 'tx_geosearch_objects.uid';
		if ($data) {
			$objects = $this->getNearestObjects($data, $sql);
		} else {
			$objects = $this->getZipObjects($sql);
		}
		return $objects;
	}


	/**
	 * get the objects of a given postcode
	 *
	 * @param array $data
	 * @param array $sql
	 * @return array
	 */
	function getNearestObjects($data, $sql) {
		$GLOBALS['TYPO3_DB']->store_lastBuiltQuery = TRUE;

		$radius = $GLOBALS['TYPO3_DB']->fullQuoteStr($this->piVars['radius'], 'tx_geosearch_objects');

		$laenge = $data['longitude'] / 180 * M_PI; // Umrechnung von GRAD IN RAD
		$breite = $data['latitude'] / 180 * M_PI; // Umrechnung von GRAD IN RAD
		$earthRadius = 6367.41;
		if ($this->arrConf['unit'] == 'miles') {
			$earthRadius = $earthRadius / 1.609;
		}
		$sql['sorting'] = 'distance';
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			$sql['column'] . ', ROUND(' . $earthRadius . ' * SQRT(2 * (1 - cos(RADIANS(latitude)) * cos(' . $breite . ') * (sin(RADIANS(longitude)) * sin(' . $laenge . ') + cos(RADIANS(longitude)) * cos(' . $laenge . ')) - sin(RADIANS(latitude)) * sin(' . $breite . '))), 1) AS distance',
			$sql['table'],
			$sql['where'] . ' AND tx_geosearch_objects.deleted = 0 AND tx_geosearch_objects.hidden = 0 AND (' . $earthRadius . ' * SQRT(2 * (1 - cos(RADIANS(latitude)) * cos(' . $breite . ') * (sin(RADIANS(longitude)) * sin(' . $laenge . ') + cos(RADIANS(longitude)) * cos(' . $laenge . ')) - sin(RADIANS(latitude)) * sin(' . $breite . ')))) < ' . $radius,
			$sql['groupBy'],
			$sql['sorting']
		);
		return $res;
	}


	/**
	 * if the given postcode is not complete, get all objects with the same beginning postcode
	 *
	 * @param array $sql
	 * @return array
	 */
	function getZipObjects($sql) {
	    $postcode = $GLOBALS['TYPO3_DB']->escapeStrForLike($GLOBALS['TYPO3_DB']->quoteStr($this->piVars['postcode'], 'tx_geosearch_objects'), 'tx_geosearch_objects');
	    $country = $GLOBALS['TYPO3_DB']->fullQuoteStr($this->piVars['country'], 'tx_geosearch_objects');
		$sql['sorting'] = 'postcode';
		$query = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			$sql['column'],
			$sql['table'],
			$sql['where'] . ' AND tx_geosearch_objects.deleted = 0 AND tx_geosearch_objects.hidden = 0 AND country IN(SELECT uid from static_countries WHERE ' . $this->conf['countryname'] . '=' . $country . ') AND postcode LIKE "' . $postcode . '%"',
			$sql['groupBy'],
			$sql['sorting']
		);
		return $query;
	}


	/**
	 * Initial function for templating
	 *
	 * @return string
	 */
	function getContent(){
		//Get the parts out of the template
		$t['total'] = $this->cObj->getSubpart($this->arrConf['templateCode'], '###GEOSEARCH_FORM###');
		$content = $this->getForm($t['total']);
		return $content;
	}


	/**
	 * Builds the Search-Form
	 *
	 * @param string $form
	 * @return string
	 */
	function getForm($form) {
		$t['country'] = $this->cObj->getSubpart($form, '###COUNTRY###');
		$t['country_options'] = $this->cObj->getSubpart($form, '###COUNTRY_OPTIONS###');
		$t['country_hidden'] = $this->cObj->getSubpart($form, '###COUNTRY_HIDDEN###');

		$subpartArray['###COUNTRY###'] = '';
		$subpartArray['###COUNTRY_HIDDEN###'] = '';

		$markerArray['###URL###'] = t3lib_div::getIndpEnv('TYPO3_REQUEST_URL');

		$markerArray['###FORM_INPUT_ZIPCODE_LABEL###'] = htmlspecialchars($this->pi_getLL('postalcode'));
		$markerArray['###FORM_INPUT_ZIPCODE_NAME###'] = $this->prefixId.'[postcode]';
		$markerArray['###FORM_INPUT_RADIUS_LABEL###'] = htmlspecialchars($this->pi_getLL('radius'));
		$markerArray['###FORM_INPUT_RADIUS_NAME###'] = $this->prefixId.'[radius]';
		$markerArray['###FORM_SUBMIT_VALUE###'] = htmlspecialchars($this->pi_getLL('submit_button_label'));
		$markerArray['###FORM_SUBMIT_NAME###'] = $this->prefixId.'[submit_button]';
		$markerArray['###UNIT###'] = htmlspecialchars($this->pi_getLL($this->arrConf['unit']));
		$markerArray['###VALUE_ZIPCODE###'] = $this->piVars['postcode'];
		$markerArray['###VALUE_RADIUS###'] = $this->piVars['radius'];

		if (count($this->arrConf['countries']) > 1) {
			$markerArray['###FORM_SELECT_COUNTRY_LABEL###'] = htmlspecialchars($this->pi_getLL('country'));
			$markerArray['###FORM_SELECT_COUNTRY_NAME###'] = $this->prefixId . '[country]';
			$option = '';
			foreach($this->arrConf['countries'] as $key => $name) {
				$markerArray['###FORM_OPTION_COUNTRY_VALUE###'] = $key;
				$markerArray['###FORM_OPTION_COUNTRY_NAME###'] = $name;
				$markerArray['###SELECTED###'] = $this->piVars['country'] == $key ? 'selected="selected"' : '';
				$option .= $this->cObj->substituteMarkerArrayCached($t['country_options'], $markerArray);
			}
			$subsubpartArray['###COUNTRY_OPTIONS###'] = $option;
			$subpartArray['###COUNTRY###'] = $this->cObj->substituteMarkerArrayCached($t['country'], $markerArray, $subsubpartArray);
		} else {
			$countryArr = array_keys($this->arrConf['countries']);
			$markerArray['###FORM_HIDDEN_COUNTRY_NAME###'] = $this->prefixId . '[country]';
			$markerArray['###FORM_HIDDEN_COUNTRY_VALUE###'] = $countryArr[0];
			$subpartArray['###COUNTRY_HIDDEN###'] = $this->cObj->substituteMarkerArrayCached($t['country_hidden'], $markerArray);
		}

		$content = $this->cObj->substituteMarkerArrayCached($form, $markerArray, $subpartArray);
		return $content;
	}

	/**
	 * Builds the object listing
	 *
	 * @param array $objects
	 * @return string
	 */
	function getList($objects) {
		//Get the parts out of the template
		$t['total'] = $this->cObj->getSubpart($this->arrConf['templateCode'], '###GEOSEARCH_LIST###');

		$t['found'] = $this->cObj->getSubpart($t['total'], '###GEOSEARCH_FOUND###');
		$t['show'] = $this->cObj->getSubpart($t['total'], '###GEOSEARCH_SHOW###');
		$t['content'] = $this->cObj->getSubpart($t['total'], '###GEOSEARCH_CONTENT###');
		$t['navigation'] = $this->cObj->getSubpart($t['total'], '###GEOSEARCH_NAVIGATION###');
		$this->contentCode = $t['content'];
		$first = $this->piVars['first'] ? $this->piVars['first'] : 0;
		$last = $this->arrConf['limit'] && $this->arrConf['limit'] < count($objects) && $first + $this->arrConf['limit'] < count($objects) ? $first + $this->arrConf['limit'] : count($objects);

		$markerArray['###NUMBER_OF_HITS###'] = count($objects) > 1 ? sprintf(htmlspecialchars($this->pi_getLL('numbersOfHits')), count($objects)) : sprintf(htmlspecialchars($this->pi_getLL('numberOfHits')), count($objects));
		$markerArray['###SHOW###'] = sprintf(htmlspecialchars($this->pi_getLL('show')), $first + 1, $last);

		$address = '';
		for($i = $first; $i < $last; $i++){
			$subpartArray['###GEOSEARCH_DISTANCE###'] = $this->getMarker($objects, $i, 'distance', 'unit', 4);
			$subpartArray['###GEOSEARCH_NAME###'] = $this->getMarker($objects, $i,'name', 'name', 1);
			$subpartArray['###GEOSEARCH_STREET###'] = $this->getMarker($objects, $i, 'street', 'street', 1);
			$subpartArray['###GEOSEARCH_CITY###'] = $this->getMarker($objects, $i, 'postcode, city', 'city', 2);
			$subpartArray['###GEOSEARCH_COUNTRY###'] = $this->getMarker($objects, $i, 'country', 'country', 3);
			$subpartArray['###GEOSEARCH_TELEPHONE###'] = $this->getMarker($objects, $i, 'telephone', 'telephone', 1);
			$subpartArray['###GEOSEARCH_MOBILE###'] = $this->getMarker($objects, $i, 'mobile', 'mobile', 1);
			$subpartArray['###GEOSEARCH_FAX###'] = $this->getMarker($objects, $i, 'fax', 'fax', 1);
			$subpartArray['###GEOSEARCH_EMAIL###'] = $this->getMarker($objects, $i, 'email', 'email', 1);
			$subpartArray['###GEOSEARCH_WWW###'] = $this->getMarker($objects, $i, 'www', 'www', 1);

			$address .= $this->cObj->substituteMarkerArrayCached($t['content'], $markerArray, $subpartArray);
		}

		$subpartArray['###GEOSEARCH_FOUND###'] = $this->cObj->substituteMarkerArrayCached($t['found'], $markerArray);
		$subpartArray['###GEOSEARCH_SHOW###'] = $this->cObj->substituteMarkerArrayCached($t['show'], $markerArray);
		$subpartArray['###GEOSEARCH_CONTENT###'] = $address;
		$subpartArray['###GEOSEARCH_NAVIGATION###'] = $this->getPageBrowser($objects, $t['navigation'], $first, $last);

		$content = $this->cObj->substituteMarkerArrayCached($t['total'], $markerArray, $subpartArray);

		return $content;
	}


	/**
	 * Builds the object listing
	 *
	 * @param string $error
	 * @return string
	 */
	function getError($error) {
		//Get the parts out of the template
		$t['total'] = $this->cObj->getSubpart($this->arrConf['templateCode'], '###GEOSEARCH_ERROR###');
		$markerArray['###ERROR###'] = $this->pi_getLL($error);

		$content = $this->cObj->substituteMarkerArrayCached($t['total'], $markerArray);

		return $content;
	}


	/**
	 * Helper function for filling the markers with content
	 *
	 * @param array $objects
	 * @param integer $id
	 * @param string $value
	 * @param string $label
	 * @param integer $type
	 * @param integer $imgId
	 * @return string
	 */
	function getMarker($objects, $id, $value, $label, $type, $imgId = 0) {
		switch ($type) {
			case 1:
				if ($objects[$id][$value]) {
					$markerArray['###ADDRESS_' . strtoupper($value) . '###'] = $objects[$id][$value];
					$markerArray['###' . strtoupper($label) . '_LABEL###'] = htmlspecialchars($this->pi_getLL($label));
				}
			break;
			case 2:
				$valueArr = t3lib_div::trimExplode(',', $value);
				foreach ($valueArr as $item) {
					if ($objects[$id][$item]) {
						$markerArray['###ADDRESS_' . strtoupper($item) . '###'] = $objects[$id][$item];
					}
				}
				if (is_array($markerArray)) {
					$markerArray['###' . strtoupper($label) . '_LABEL###'] = htmlspecialchars($this->pi_getLL($label));
				}
				$value = $label;
			break;
			case 3:
				if ($objects[$id]['country']) {
					$row = $this->pi_getRecord('static_countries', $objects[$id]['country']);
					$markerArray['###ADDRESS_' . strtoupper($value) . '###'] = $row['cn_short_local'];
					$markerArray['###' . strtoupper($label) . '_LABEL###'] = htmlspecialchars($this->pi_getLL($label));
				}
			break;
			case 4:
				if ($objects[$id][$value]) {
					$markerArray['###' . strtoupper($value) . '###'] = $objects[$id][$value] . ' ' . htmlspecialchars($this->pi_getLL($this->arrConf[$label]));
				}
			break;
		}
		if (is_array($markerArray)) {
			$tmpl = $this->cObj->getSubpart($this->contentCode, '###GEOSEARCH_' . strtoupper($value) . '###');
			$output = $this->cObj->substituteMarkerArrayCached($tmpl,$markerArray);
			return $output;
		}
	}


	/**
	 * Builds the Pagebrowser
	 *
	 * @param array $objects
	 * @param string $navigation
	 * @param integer $first
	 * @param integer $last
	 * @return string
	 */
	function getPageBrowser($objects, $navigation, $first, $last) {
		$t['prev'] = $this->cObj->getSubpart($navigation, '###GEOSEARCH_PREV###');
		$t['browse'] = $this->cObj->getSubpart($navigation, '###GEOSEARCH_BROWSE###');
		$t['browse1'] = $this->cObj->getSubpart($navigation, '###GEOSEARCH_BROWSE1###');
		$t['next'] = $this->cObj->getSubpart($navigation, '###GEOSEARCH_NEXT###');
		unset($this->piVars['submit_button']);
		$limit=$this->arrConf['limit'] ? $this->arrConf['limit'] : count($objects);
		$countObjects = count($objects);
		$countPages = ceil($countObjects/$limit);
		if ($countPages >1) {
			$browseLink = '';
			$browse=0;
			for($i=0; $i<$countPages; $i++) {
				$counter = $i + 1;
				$link = $this->pi_linkTP_keepPIVars($counter, array('first' => $browse), 1);
				$markerArray['###BROWSE_LINK###'] = ($first == $browse) ? $counter : $link;
				$browseLink .= $this->cObj->substituteMarkerArrayCached($t['browse'], $markerArray);
				$browse += $this->arrConf['limit'];
			}
			$subpartArray['###GEOSEARCH_BROWSE###'] = $browseLink;

			$prev = $first - $limit;
			$next = $last;

			if ($first > 0) {
				$browsePrev = $this->pi_linkTP_keepPIVars('<<', array('first' => $prev), 1);
				$markerArray['###PREV_LINK###'] = $browsePrev;
			} else {
				$markerArray['###PREV_LINK###'] = '<<';
			}
			$subpartArray['###GEOSEARCH_PREV###'] = $this->cObj->substituteMarkerArrayCached($t['prev'], $markerArray, $subpartArray);

			if ($last < $countObjects) {
				$browseNext = $this->pi_linkTP_keepPIVars('>>', array('first' => $next), 1);
				$markerArray['###NEXT_LINK###'] = $browseNext;
			} else {
				$markerArray['###NEXT_LINK###'] = '>>';
			}
			$subpartArray['###GEOSEARCH_NEXT###'] = $this->cObj->substituteMarkerArrayCached($t['next'], $markerArray, $subpartArray);
		} else {
			$subpartArray['###GEOSEARCH_BROWSE###'] = '';
			$subpartArray['###GEOSEARCH_PREV###'] = '';
			$subpartArray['###GEOSEARCH_NEXT###'] = '';
		}
		return $this->cObj->substituteMarkerArrayCached($navigation, $markerArray, $subpartArray);
	}

	/**
	 * Validates Zip Code
	 *
	 * @return boolean
	 */
	function validateZip() {
		switch ($this->piVars['country']) {
			case 'AT':
			case 'AU':
			case 'BE':
			case 'DK':
			case 'NO':
			case 'PT':
			case 'CH':
				if (preg_match('/[0-9]{4}/', $this->piVars['postcode'])) {
					return TRUE;
				}
			break;
			case 'FI':
			case 'FR':
			case 'DE':
			case 'IT':
			case 'ES':
			case 'US':
				if (preg_match('/[0-9]{5}/', $this->piVars['postcode'])) {
					return TRUE;
				}
			break;
			case 'GR':
			case 'SE':
				if (preg_match('/[0-9]{3}[ ][0-9]{2}/', $this->piVars['postcode'])) {
					return TRUE;
				}
			break;
			case 'NL':
				if (preg_match('/[0-9]{4}[ ][A-Z]{2}/', $this->piVars['postcode'])) {
					return TRUE;
				}
			break;
			case 'PL':
				if (preg_match('/[0-9]{2}-[0-9]{3}/', $this->piVars['postcode'])) {
					return TRUE;
				}
			break;
			case 'GB':
				if (preg_match('/(GIR0AA)|(TDCU1ZZ)|((([A-PR-UWYZ][0-9][0-9]?)|'
					. '(([A-PR-UWYZ][A-HK-Y][0-9][0-9]?)|'
					. '(([A-PR-UWYZ][0-9][A-HJKSTUW])|'
					. '([A-PR-UWYZ][A-HK-Y][0-9][ABEHMNPRVWXY]))))'
					. '[0-9][ABD-HJLNP-UW-Z]{2})/', $this->piVars['postcode'])) {
					return TRUE;
				}
			break;
			default:
				$data = file('http://ws.geonames.org/postalCodeSearch?postalcode=' . $this->piVars['postcode'] . '&country=' . $this->piVars['country']);
				$dataArr = t3lib_div::xml2array(implode('', $data));
				if ($dataArr['totalResultsCount'] === 0) {
					return FALSE;
				} else {
					return TRUE;
				}
		}
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/geosearch/pi1/class.tx_geosearch_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/geosearch/pi1/class.tx_geosearch_pi1.php']);
}

?>