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
 * @package API\Core
 */
class APIException extends APIFactory {
	protected $main_class = 'ExceptionFactory';

	/**
	 * APIException constructor.
	 */
	public function __construct() {
		parent::__construct(); //Make sure parent constructor is always called.

		return TRUE;
	}

	/**
	 * Get options for dropdown boxes.
	 * @param bool|string $name Name of options to return, ie: 'columns', 'type', 'status'
	 * @param mixed $parent Parent name/ID of options to return if data is in hierarchical format. (ie: Province)
	 * @return bool|array
	 */
	function getOptions( $name = FALSE, $parent = NULL ) {
		if ( $name == 'columns'
				AND ( !$this->getPermissionObject()->Check('punch', 'enabled')
					OR !( $this->getPermissionObject()->Check('punch', 'view') OR $this->getPermissionObject()->Check('punch', 'view_child') ) ) ) {
			$name = 'list_columns';
		}

		return parent::getOptions( $name, $parent );
	}

	/**
	 * Get default exception data for creating new exceptiones.
	 * @return array
	 */
	function getExceptionDefaultData() {
		$company_obj = $this->getCurrentCompanyObject();

		Debug::Text('Getting exception default data...', __FILE__, __LINE__, __METHOD__, 10);

		$data = array(
						'company_id' => $company_obj->getId(),
					);

		return $this->returnHandler( $data );
	}

	/**
	 * Get exception data for one or more exceptiones.
	 * @param array $data filter data
	 * @param bool $disable_paging
	 * @return array
	 */
	function getException( $data = NULL, $disable_paging = FALSE ) {
		$data = $this->initializeFilterAndPager( $data, $disable_paging );

		if ( !$this->getPermissionObject()->Check('punch', 'enabled')
				OR !( $this->getPermissionObject()->Check('punch', 'view') OR $this->getPermissionObject()->Check('punch', 'view_own') OR $this->getPermissionObject()->Check('punch', 'view_child') ) ) {
			return $this->getPermissionObject()->PermissionDenied(); //If they don't have permissions to view punches/timesheets, what good is it to show them exceptions?
			//$data['filter_columns'] = $this->handlePermissionFilterColumns( (isset($data['filter_columns'])) ? $data['filter_columns'] : NULL, Misc::trimSortPrefix( $this->getOptions('list_columns') ) );
		}

		$data['filter_data']['permission_children_ids'] = $this->getPermissionObject()->getPermissionChildren( 'punch', 'view' );

		//If no pay period is specified, force to showing exceptions only in non-closed pay periods. This is a performance optimization too.
		if ( !isset($data['filter_data']['pay_period_status_id']) AND !isset($data['filter_data']['pay_period_id']) ) {
			$data['filter_data']['pay_period_status_id'] = array(10, 12, 30); //All but closed
		}

		$blf = TTnew( 'ExceptionListFactory' ); /** @var ExceptionListFactory $blf */

		$type_ids = Misc::trimSortPrefix( $blf->getOptions('type') );
		if ( !isset($data['filter_data']['show_pre_mature']) OR ( isset($data['filter_data']['show_pre_mature']) AND $data['filter_data']['show_pre_mature'] == FALSE ) ) {
			unset( $type_ids[5]);
		}
		$data['filter_data']['type_id'] = array_keys( $type_ids );


		if ( DEPLOYMENT_ON_DEMAND == TRUE ) { $blf->setQueryStatementTimeout( 60000 ); }
		$blf->getAPISearchByCompanyIdAndArrayCriteria( $this->getCurrentCompanyObject()->getId(), $data['filter_data'], $data['filter_items_per_page'], $data['filter_page'], NULL, $data['filter_sort'] );
		Debug::Text('Record Count: '. $blf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
		if ( $blf->getRecordCount() > 0 ) {
			$this->getProgressBarObject()->start( $this->getAMFMessageID(), $blf->getRecordCount() );

			$this->setPagerObject( $blf );

			$retarr = array();
			foreach( $blf as $b_obj ) {
				$retarr[] = $b_obj->getObjectAsArray( $data['filter_columns'], $data['filter_data']['permission_children_ids'] );

				$this->getProgressBarObject()->set( $this->getAMFMessageID(), $blf->getCurrentRow() );
			}

			$this->getProgressBarObject()->stop( $this->getAMFMessageID() );

			return $this->returnHandler( $retarr );
		}

		return $this->returnHandler( TRUE ); //No records returned.
	}

	/**
	 * @param string $format
	 * @param array $data
	 * @param bool $disable_paging
	 * @return array|bool
	 */
	function exportException( $format = 'csv', $data = NULL, $disable_paging = TRUE) {
		$result = $this->stripReturnHandler( $this->getException( $data, $disable_paging ) );
		return $this->exportRecords( $format, 'export_exceptions', $result, ( ( isset($data['filter_columns']) ) ? $data['filter_columns'] : NULL ) );
	}
}
?>
