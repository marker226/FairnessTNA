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
 * @package Modules\Users
 */
class UserGenericDataListFactory extends UserGenericDataFactory implements IteratorAggregate {

	/**
	 * @param int $limit Limit the number of records returned
	 * @param int $page Page number of records to return for pagination
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return $this
	 */
	function getAll( $limit = NULL, $page = NULL, $where = NULL, $order = NULL) {
		$query = '
					select	*
					from	'. $this->getTable() .'
					WHERE deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order );

		$this->rs = $this->ExecuteSQL( $query, NULL, $limit, $page );

		return $this;
	}

	/**
	 * @param string $id UUID
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getById( $id, $where = NULL, $order = NULL) {
		if ( $id == '') {
			return FALSE;
		}

		$ph = array(
					'id' => TTUUID::castUUID($id),
					);


		$query = '
					select	*
					from	'. $this->getTable() .'
					where	id = ?
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $id UUID
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByUserId( $id, $where = NULL, $order = NULL) {
		if ( $id == '') {
			return FALSE;
		}

		$ph = array(
					'user_id' => TTUUID::castUUID($id),
					);

		$query = '
					select	*
					from	'. $this->getTable() .'
					where	user_id = ?
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $user_id UUID
	 * @param string $id UUID
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByUserIdAndId( $user_id, $id, $where = NULL, $order = NULL) {
		if ( $user_id == '') {
			return FALSE;
		}

		if ( $id == '') {
			return FALSE;
		}

		$ph = array(
					'user_id' => TTUUID::castUUID($user_id),
					'id' => TTUUID::castUUID($id),
					);

		$query = '
					select	*
					from	'. $this->getTable() .'
					where	user_id = ?
						AND id = ?
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $company_id UUID
	 * @param string $user_id UUID
	 * @param string $id UUID
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByCompanyIdAndUserIdAndId( $company_id, $user_id, $id, $where = NULL, $order = NULL) {
		if ( $company_id == '') {
			return FALSE;
		}

		if ( $id == '') {
			return FALSE;
		}

		$ph = array(
					'company_id' => TTUUID::castUUID($company_id),
					'id' => TTUUID::castUUID($id),
					);

		$query = '
					select	*
					from	'. $this->getTable() .'
					where	company_id = ?
						AND id = ? ';

		//Allow getting company wide data if user_id == ''
		if ( $user_id != '' ) {
			$ph[] = TTUUID::castUUID($user_id);
			$query .= '		AND user_id = ?';
		} else {
			$query .= '		AND ( user_id = \''. TTUUID::getZeroID() .'\' OR user_id IS NULL )';
		}

		$query .= ' AND deleted = 0';

		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $user_id UUID
	 * @param $script
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByUserIdAndScript( $user_id, $script, $where = NULL, $order = NULL) {
		if ( $user_id == '') {
			return FALSE;
		}

		if ( $script == '') {
			return FALSE;
		} else {
			$script = self::handleScriptName( $script );
		}

		if ( $order == NULL ) {
			$order = array( 'is_default' => 'desc', 'name' => 'asc' );
			$strict = FALSE;
		} else {
			$strict = TRUE;
		}

		$ph = array(
					'user_id' => TTUUID::castUUID($user_id),
					'script' => $script,
					);

		$query = '
					select	*
					from	'. $this->getTable() .'
					where	user_id = ?
						AND script = ?
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order, $strict );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $user_id UUID
	 * @param $script
	 * @param bool $default
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByUserIdAndScriptAndDefault( $user_id, $script, $default = TRUE, $where = NULL, $order = NULL) {
		if ( $user_id == '') {
			return FALSE;
		}

		if ( $script == '') {
			return FALSE;
		} else {
			$script = self::handleScriptName( $script );
		}

		if ( $order == NULL ) {
			$order = array( 'updated_date' => 'desc' );
			$strict = FALSE;
		} else {
			$strict = TRUE;
		}

		$ph = array(
					'user_id' => TTUUID::castUUID($user_id),
					'script' => $script,
					'default' => $this->toBool($default),
					);

		$query = '
					select	*
					from	'. $this->getTable() .'
					where	user_id = ?
						AND script = ?
						AND is_default = ?
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order, $strict );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $user_id UUID
	 * @param $script
	 * @param bool $include_blank
	 * @return array|bool
	 */
	function getByUserIdAndScriptArray( $user_id, $script, $include_blank = TRUE) {

		$ugdlf = new UserGenericDataListFactory();
		$ugdlf->getByUserIdAndScript($user_id, $script);

		$list = array();
		if ( $include_blank == TRUE ) {
			$list[TTUUID::getZeroID()] = '--';
		}

		foreach ($ugdlf as $ugd_obj) {
			if ( $ugd_obj->getDefault() == TRUE ) {
				$default = ' (Default)';
			} else {
				$default = NULL;
			}
			$list[$ugd_obj->getID()] = $ugd_obj->getName().$default;
		}

		if ( empty($list) == FALSE ) {
			return $list;
		}

		return FALSE;
	}

	/*

		Company List Functions

	*/
	/**
	 * @param string $id UUID
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByCompanyId( $id, $where = NULL, $order = NULL) {
		if ( $id == '') {
			return FALSE;
		}

		$ph = array(
					'id' => TTUUID::castUUID($id),
					);


		$query = '
					select	*
					from	'. $this->getTable() .'
					where	company_id = ?
						AND ( user_id = \''. TTUUID::getZeroID() .'\' OR user_id is NULL )
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $company_id UUID
	 * @param string $id UUID
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByCompanyIdAndId( $company_id, $id, $where = NULL, $order = NULL) {
		if ( $company_id == '') {
			return FALSE;
		}

		if ( $id == '') {
			return FALSE;
		}

		$ph = array(
					'company_id' => TTUUID::castUUID($company_id),
					'id' => TTUUID::castUUID($id),
					);

		$query = '
					select	*
					from	'. $this->getTable() .'
					where	company_id = ?
						AND ( user_id = \''. TTUUID::getZeroID() .'\' OR user_id is NULL )
						AND id = ?
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $company_id UUID
	 * @param $script
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByCompanyIdAndScript( $company_id, $script, $where = NULL, $order = NULL) {
		if ( $company_id == '') {
			return FALSE;
		}

		if ( $script == '') {
			return FALSE;
		} else {
			$script = self::handleScriptName( $script );
		}

		if ( $order == NULL ) {
			$order = array( 'updated_date' => 'desc' );
			$strict = FALSE;
		} else {
			$strict = TRUE;
		}

		$ph = array(
					'company_id' => TTUUID::castUUID($company_id),
					'script' => $script
					);

		$query = '
					select	*
					from	'. $this->getTable() .'
					where	company_id = ?
						AND ( user_id = \''. TTUUID::getZeroID() .'\' OR user_id is NULL )
						AND script = ?
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order, $strict );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $company_id UUID
	 * @param $script
	 * @param bool $default
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getByCompanyIdAndScriptAndDefault( $company_id, $script, $default = TRUE, $where = NULL, $order = NULL) {
		if ( $company_id == '') {
			return FALSE;
		}

		if ( $script == '') {
			return FALSE;
		} else {
			$script = self::handleScriptName( $script );
		}

		if ( $order == NULL ) {
			$order = array( 'updated_date' => 'desc' );
			$strict = FALSE;
		} else {
			$strict = TRUE;
		}

		$ph = array(
					'company_id' => TTUUID::castUUID($company_id),
					'script' => $script,
					'default' => $this->toBool($default)
					);

		$query = '
					select	*
					from	'. $this->getTable() .'
					where	company_id = ?
						AND ( user_id = \''. TTUUID::getZeroID() .'\' OR user_id is NULL )
						AND script = ?
						AND is_default = ?
						AND deleted = 0';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order, $strict );

		$this->rs = $this->ExecuteSQL( $query, $ph );

		return $this;
	}

	/**
	 * @param string $company_id UUID
	 * @param $filter_data
	 * @param int $limit Limit the number of records returned
	 * @param int $page Page number of records to return for pagination
	 * @param array $where Additional SQL WHERE clause in format of array( $column => $filter, ... ). ie: array( 'id' => 1, ... )
	 * @param array $order Sort order passed to SQL in format of array( $column => 'asc', 'name' => 'desc', ... ). ie: array( 'id' => 'asc', 'name' => 'desc', ... )
	 * @return bool|UserGenericDataListFactory
	 */
	function getAPISearchByCompanyIdAndArrayCriteria( $company_id, $filter_data, $limit = NULL, $page = NULL, $where = NULL, $order = NULL ) {
		if ( $company_id == '') {
			return FALSE;
		}

		if ( !is_array($order) ) {
			//Use Filter Data ordering if its set.
			if ( isset($filter_data['sort_column']) AND $filter_data['sort_order']) {
				$order = array(Misc::trimSortPrefix($filter_data['sort_column']) => $filter_data['sort_order']);
			}
		}

		$additional_order_fields = array();
		if ( $order == NULL ) {
			$order = array( 'a.name' => 'asc' ); //Default to sort by name for saved reports and such.
			$strict = FALSE;
		} else {
			$strict = TRUE;
		}
		//Debug::Arr($order, 'Order Data:', __FILE__, __LINE__, __METHOD__, 10);
		//Debug::Arr($filter_data, 'Filter Data:', __FILE__, __LINE__, __METHOD__, 10);
		$uf = new UserFactory();
		$ph = array(
					'company_id' => TTUUID::castUUID($company_id),
					);

		$query = '
					select	a.*
					from	'. $this->getTable() .' as a
						LEFT JOIN '. $uf->getTable() .' as y ON ( a.created_by = y.id AND y.deleted = 0 )
						LEFT JOIN '. $uf->getTable() .' as z ON ( a.updated_by = z.id AND z.deleted = 0 )
					where	a.company_id = ?
					';

		$query .= ( isset($filter_data['id']) ) ? $this->getWhereClauseSQL( 'a.id', $filter_data['id'], 'uuid_list', $ph ) : NULL;
		$query .= ( isset($filter_data['exclude_id']) ) ? $this->getWhereClauseSQL( 'a.id', $filter_data['exclude_id'], 'not_uuid_list', $ph ) : NULL;

		if ( isset($filter_data['user_id']) ) {
			$query	.=	' AND a.user_id in ('. $this->getListSQL($filter_data['user_id'], $ph, 'uuid') .') ';
		} else {
			$query	.=	' AND ( a.user_id = \''. TTUUID::getZeroID() .'\' OR a.user_id is NULL ) ';
		}

		$query .= ( isset($filter_data['script']) ) ? $this->getWhereClauseSQL( 'a.script', $filter_data['script'], 'lower_text_list', $ph ) : NULL;
		$query .= ( isset($filter_data['name']) ) ? $this->getWhereClauseSQL( 'a.name', $filter_data['name'], 'lower_text_list', $ph ) : NULL;
		$query .= ( isset($filter_data['is_default']) ) ? $this->getWhereClauseSQL( 'a.is_default', $filter_data['is_default'], 'boolean', $ph ) : NULL;

		$query .= ( isset($filter_data['created_by']) ) ? $this->getWhereClauseSQL( array('a.created_by', 'y.first_name', 'y.last_name'), $filter_data['created_by'], 'user_id_or_name', $ph ) : NULL;
		$query .= ( isset($filter_data['updated_by']) ) ? $this->getWhereClauseSQL( array('a.updated_by', 'z.first_name', 'z.last_name'), $filter_data['updated_by'], 'user_id_or_name', $ph ) : NULL;

		$query .=	' AND a.deleted = 0 ';
		$query .= $this->getWhereSQL( $where );
		$query .= $this->getSortSQL( $order, $strict, $additional_order_fields );

		//Debug::Query($query, $ph, __FILE__, __LINE__, __METHOD__, 10);
		$this->rs = $this->ExecuteSQL( $query, $ph, $limit, $page );

		return $this;
	}

	/**
	 * @param $lf
	 * @param bool $include_blank
	 * @return array|bool
	 */
	function getArrayByListFactory( $lf, $include_blank = TRUE ) {
		if ( !is_object($lf) ) {
			return FALSE;
		}

		$list = array();
		if ( $include_blank == TRUE ) {
			$list[TTUUID::getZeroID()] = '--';
		}

		foreach ($lf as $obj) {
			$list[$obj->getID()] = $obj->getName();
		}

		if ( empty($list) == FALSE ) {
			return $list;
		}

		return FALSE;
	}

	/**
	 * @param string $company_id UUID
	 * @param $script
	 * @param bool $include_blank
	 * @return array|bool
	 */
	function getByCompanyIdAndScriptArray( $company_id, $script, $include_blank = TRUE) {

		$ugdlf = new UserGenericDataListFactory();
		$ugdlf->getByUserIdAndScript($company_id, $script);

		$list = array();
		if ( $include_blank == TRUE ) {
			$list[TTUUID::getZeroID()] = '--';
		}

		foreach ($ugdlf as $ugd_obj) {
			if ( $ugd_obj->getDefault() == TRUE ) {
				$default = ' (Default)';
			} else {
				$default = NULL;
			}
			$list[$ugd_obj->getID()] = $ugd_obj->getName().$default;
		}

		if ( empty($list) == FALSE ) {
			return $list;
		}

		return FALSE;
	}
}
?>
