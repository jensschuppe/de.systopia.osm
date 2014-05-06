<?php
/*
  +--------------------------------------------------------------------+
  | CiviCRM OSM Geocoding module (SYS-OSM)                             |
  +--------------------------------------------------------------------+
  | Copyright SYSTOPIA (c) 2014                                        |
  +--------------------------------------------------------------------+
  | This is free software; you can copy, modify, and distribute it     |
  | under the terms of the GNU Affero General Public License           |
  | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
  |                                                                    |
  | SYS-OSM is distributed in the hope that it will be useful, but     |
  | WITHOUT ANY WARRANTY; without even the implied warranty of         |
  | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
  | See the GNU Affero General Public License for more details.        |
  |                                                                    |
  | You should have received a copy of the GNU Affero General Public   |
  | License and the SYS-OSM Licensing Exception along                  |
  | with this program; if not, contact SYSTOPIA                        |
  | at info[AT]systopia[DOT]de. If you have questions about the        |
  | GNU Affero General Public License or the licensing of CiviCRM,     |
  | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
  +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright SYSTOPIA (c) 2014
 *
 */

/**
 * Class that uses OpenStreetMaps (OSM) API to retrieve the lat/long of an address
 */
class CRM_Utils_Geocode_OpenStreetMapsCoding {

  /**
   * server to retrieve the lat/long
   *
   * @var string
   * @static
   */
  static protected $_server = 'nominatim.openstreetmap.org';

  /**
   * uri of service
   *
   * @var string
   * @static
   */
  static protected $_uri = '/search';

  /**
   * function that takes an address array and gets the latitude / longitude
   * and postal code for this address. Note that at a later stage, we could
   * make this function also clean up the address into a more valid format
   *
   * @param array $values associative array of address data: country, street_address, city, state_province, postal code
   * @param boolean $stateName this params currently has no function
   *
   * @return boolean true if we modified the address, false otherwise
   * @static
   */
  static function format(&$values, $stateName = FALSE) {
    CRM_Utils_System::checkPHPVersion(5, TRUE);

    $config = CRM_Core_Config::singleton();

    $params = array();

    //TODO: is there a more failsafe format for street and street-number?
    if (CRM_Utils_Array::value('street_address', $values)) {
      $params['street'] = $values['street_address'];
    }

    if ($city = CRM_Utils_Array::value('city', $values)) {
      $params['city'] = $city;
    }

    if (CRM_Utils_Array::value('state_province', $values)) {
      if (CRM_Utils_Array::value('state_province_id', $values)) {
        $stateProvince = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_StateProvince', $values['state_province_id']);
      }
      else {
        if (!$stateName) {
          $stateProvince = CRM_Core_DAO::getFieldValue('CRM_Core_DAO_StateProvince',
            $values['state_province'],
            'name',
            'abbreviation'
          );
        }
        else {
          $stateProvince = $values['state_province'];
        }
      }

      // dont add state twice if replicated in city (happens in NZ and other countries, CRM-2632)
      if ($stateProvince != $city) {
        $params['state'] = $stateProvince;
      }
    }

    if (CRM_Utils_Array::value('postal_code', $values)) {
      $params['postalcode'] = $values['postal_code'];
    }

    if (CRM_Utils_Array::value('country', $values)) {
      $params['country'] = $values['country'];
    }

    // There should be at least a city or postal_code, a street and a country
    if (!(array_key_exists('street', $params)
        && array_key_exists('country', $params)
        && (array_key_exists('city', $params) || array_key_exists('postalcode', $params)))) {
      return FALSE;
    }

    $params['addressdetails'] = '1';
    $url = "http://" . self::$_server . self::$_uri;
    $url .= '?format=json';
    foreach ($params as $key => $value) {
      $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    require_once 'HTTP/Request.php';
    $request = new HTTP_Request($url);
    $request->sendRequest();
    $string = $request->getResponseBody();
    $json = json_decode($string);

    // $string couldn't be decoded or array is empty
    if (!$json) {
      CRM_Core_Error::debug_var('Geocoding failed.  No results for: ' . $url);
      return FALSE;
    }
    $place = $json[0];

    // TODO: Process other relevant data to update address
    if (array_key_exists('lat', $place) && array_key_exists('lon', $place)) {
      $values['geo_code_1'] = $place->lat;
      $values['geo_code_2'] = $place->lon;
      return TRUE;
    } else {
      return FALSE;
    }
  }
}
