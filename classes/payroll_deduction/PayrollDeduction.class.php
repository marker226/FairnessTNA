<?php
/*********************************************************************************
 * FairnessTNA is a Workforce Management program forked from TimeTrex in 2013,
 * copyright Aydan Coskun. Original code base is copyright TimeTrex Software Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * You can contact Aydan Coskun via issue tracker on github.com/aydancoskun
 ********************************************************************************/


/**
 * @package PayrollDeduction
 */
class PayrollDeduction {
	var $obj = NULL;
	var $data = NULL;

	protected $version = '1.0.47';
	protected $data_version = '20190101';

	function __construct( $country, $province, $district = NULL ) {
		$this->setCountry( $country );
		$this->setProvince( $province );
		$this->setDistrict( $district );

		$base_path = Environment::getBasePath();
		$base_file_name = $base_path . '/classes/payroll_deduction/PayrollDeduction_Base.class.php';
		$province_file_name = $base_path . '/classes/payroll_deduction/' . $this->getCountry() . '/' . $this->getProvince() . '.class.php';
		$district_file_name = $base_path . '/classes/payroll_deduction/' . $this->getCountry() . '/' . $this->getProvince() . '_' . $this->getDistrict() . '.class.php';
		$country_file_name = $base_path . '/classes/payroll_deduction/' . $this->getCountry() . '.class.php';
		$data_file_name = $base_path . '/classes/payroll_deduction/' . $this->getCountry() . '/Data.class.php';

		if ( $this->getDistrict() != '' AND $this->getDistrict() != '00' ) {
			$class_name = 'PayrollDeduction_' . $this->getCountry() . '_' . $this->getProvince() . '_' . $this->getDistrict();
		} elseif ( $this->getProvince() != '' ) {
			$class_name = 'PayrollDeduction_' . $this->getCountry() . '_' . $this->getProvince();
		} else {
			$class_name = 'PayrollDeduction_' . $this->getCountry();
		}

		//Debug::text('Country: '. $country_file_name .' Province: '. $province_file_name .' District: '. $district_file_name .' Class: '. $class_name, __FILE__, __LINE__, __METHOD__, 10);
		if ( ( file_exists( $country_file_name ) OR ( $this->getProvince() != '' AND file_exists( $province_file_name ) ) OR ( $this->getDistrict() != '' AND file_exists( $district_file_name ) ) ) AND file_exists( $data_file_name ) ) {
			//Debug::text('Country File Exists: '. $country_file_name .' Province File Name: '. $province_file_name .' Data File: '. $data_file_name, __FILE__, __LINE__, __METHOD__, 10);

			include_once( $base_file_name );
			include_once( $data_file_name );

			if ( file_exists( $country_file_name ) ) {
				include_once( $country_file_name );
			}
			if ( $this->getProvince() != '' AND file_exists( $province_file_name ) ) {
				include_once( $province_file_name );
			}
			if ( $this->getDistrict() != '' AND file_exists( $district_file_name ) ) {
				include_once( $district_file_name );
			}

			if ( class_exists( $class_name ) ) {
				$this->obj = new $class_name;
				$this->obj->setCountry( $this->getCountry() );
				$this->obj->setProvince( $this->getProvince() );
				$this->obj->setDistrict( $this->getDistrict() );

				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			Debug::text( 'File DOES NOT Exists Country File Name: ' . $country_file_name . ' Province File: ' . $province_file_name, __FILE__, __LINE__, __METHOD__, 10 );
		}

		return FALSE;
	}

	function getVersion() {
		return $this->version;
	}

	function getDataVersion() {
		return $this->data_version;
	}

	private function getObject() {
		if ( is_object( $this->obj ) ) {
			return $this->obj;
		}

		return FALSE;
	}

	private function setCountry( $country ) {
		$this->data['country'] = strtoupper( substr( trim( $country ), 0, 2 ) ); //Sanitize country to at least be close to a country code.

		return TRUE;
	}

	function getCountry() {
		if ( isset( $this->data['country'] ) ) {
			return $this->data['country'];
		}

		return FALSE;
	}

	private function setProvince( $province ) {
		$this->data['province'] = strtoupper( substr( trim( $province ), 0, 2 ) ); //Sanitize province to at least be close to a country code.

		return TRUE;
	}

	function getProvince() {
		if ( isset( $this->data['province'] ) ) {
			return $this->data['province'];
		}

		return FALSE;
	}

	private function setDistrict( $district ) {
		$this->data['district'] = strtoupper( substr( trim( $district ), 0, 15 ) ); //Sanitize district to at least be close to a district code.

		return TRUE;
	}

	function getDistrict() {
		if ( isset( $this->data['district'] ) ) {
			return $this->data['district'];
		}

		return FALSE;
	}

	function __call( $function_name, $args = array() ) {
		if ( $this->getObject() !== FALSE ) {
			//Debug::text('Calling Sub-Class Function: '. $function_name, __FILE__, __LINE__, __METHOD__, 10);
			if ( is_callable( array($this->getObject(), $function_name) ) ) {
				$return = call_user_func_array( array($this->getObject(), $function_name), $args );

				return $return;
			}
		}

		Debug::text( 'Sub-Class Function Call FAILED!:' . $function_name, __FILE__, __LINE__, __METHOD__, 10 );

		return FALSE;
	}
}

?>
