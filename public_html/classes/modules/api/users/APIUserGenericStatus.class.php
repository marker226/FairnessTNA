<?php
/*********************************************************************************
 * This file is part of "Fairness", a Payroll and Time Management program.
 * Fairness is Copyright 2013 Aydan Coskun (aydan.ayfer.coskun@gmail.com)
 * Portions of this software are Copyright of T i m e T r e x Software Inc.
 * Fairness is a fork of "T i m e T r e x Workforce Management" Software.
 *
 * Fairness is free software; you can redistribute it and/or modify it under the
 * terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation, either version 3 of the License, or (at you option )
 * any later version.
 *
 * Fairness is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along
 * with this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 ********************************************************************************/


/**
 * @package API\Users
 */
class APIUserGenericStatus extends APIFactory
{
    protected $main_class = 'UserGenericStatusFactory';

    public function __construct()
    {
        parent::__construct(); //Make sure parent constructor is always called.

        return true;
    }


    /**
     * Get user generic status data for one or more .
     * @param array $data filter data
     * @return array
     */
    public function getUserGenericStatus($data = null, $disable_paging = false)
    {
        $data = $this->initializeFilterAndPager($data, $disable_paging);

        $user_id = $this->getCurrentUserObject()->getId();
        if ($data['filter_data']['batch_id'] != '') {
            $batch_id = $data['filter_data']['batch_id'];
            $ugslf = TTnew('UserGenericStatusListFactory');
            $ugslf->getByUserIdAndBatchId($user_id, $batch_id, $data['filter_items_per_page'], $data['filter_page'], null, $data['filter_sort']);

            Debug::Text('Record Count: ' . $ugslf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);

            if ($ugslf->getRecordCount() > 0) {
                //$status_count_arr = $ugslf->getStatusCountArrayByUserIdAndBatchId( $user_id, $batch_id );

                $this->getProgressBarObject()->start($this->getAMFMessageID(), $ugslf->getRecordCount());
                $this->setPagerObject($ugslf);

                $rows = array();
                foreach ($ugslf as $ugs_obj) {
                    $rows[] = array(
                        'id' => $ugs_obj->getId(),
                        'user_id' => $ugs_obj->getUser(),
                        'batch_id' => $ugs_obj->getBatchId(),
                        'status_id' => $ugs_obj->getStatus(),
                        'status' => Option::getByKey($ugs_obj->getStatus(), $ugs_obj->getOptions('status')),
                        'label' => $ugs_obj->getLabel(),
                        'description' => $ugs_obj->getDescription(),
                        'link' => $ugs_obj->getLink(),
                        'deleted' => $ugs_obj->getDeleted()
                    );
                    $this->getProgressBarObject()->set($this->getAMFMessageID(), $ugslf->getCurrentRow());
                }

                $this->getProgressBarObject()->stop($this->getAMFMessageID());

                return $this->returnHandler($rows);
            } else {
                return $this->returnHandler(true); //No records returned.
            }
        } else {
            return $this->returnHandler(true); //No records returned.
        }
    }

    /**
     * Delete one or more user generic status data.
     * @param array $data
     * @return array
     */
    public function deleteUserGenericStatus($data)
    {
        if (is_numeric($data)) {
            $data = array($data);
        }

        if (!is_array($data)) {
            return $this->returnHandler(false);
        }

        Debug::Text('Received data for: ' . count($data) . ' User Generic Status', __FILE__, __LINE__, __METHOD__, 10);
        Debug::Arr($data, 'Data: ', __FILE__, __LINE__, __METHOD__, 10);

        $total_records = count($data);
        $validator = $save_result = false;
        $validator_stats = array('total_records' => $total_records, 'valid_records' => 0);
        if (is_array($data) and $total_records > 0) {
            $this->getProgressBarObject()->start($this->getAMFMessageID(), $total_records);

            foreach ($data as $key => $id) {
                $primary_validator = new Validator();
                $lf = TTnew('UserGenericStatusListFactory');
                $lf->StartTransaction();
                if (is_numeric($id)) {
                    //Modifying existing object.
                    //Get branch object, so we can only modify just changed data for specific records if needed.
                    $lf->getByIdAndCompanyId($id, $this->getCurrentCompanyObject()->getId());
                    if ($lf->getRecordCount() == 1) {
                        //Object exists, check edit permissions
                        if ($this->getPermissionObject()->Check('user', 'delete')
                            or ($this->getPermissionObject()->Check('user', 'delete_own') and $this->getPermissionObject()->isOwner($lf->getCurrent()->getCreatedBy(), $lf->getCurrent()->getID()) === true)
                        ) {
                            Debug::Text('Record Exists, deleting record: ', $id, __FILE__, __LINE__, __METHOD__, 10);
                            $lf = $lf->getCurrent();
                        } else {
                            $primary_validator->isTrue('permission', false, TTi18n::gettext('Delete permission denied'));
                        }
                    } else {
                        //Object doesn't exist.
                        $primary_validator->isTrue('id', false, TTi18n::gettext('Delete permission denied, record does not exist'));
                    }
                } else {
                    $primary_validator->isTrue('id', false, TTi18n::gettext('Delete permission denied, record does not exist'));
                }

                //Debug::Arr($lf, 'AData: ', __FILE__, __LINE__, __METHOD__, 10);

                $is_valid = $primary_validator->isValid();
                if ($is_valid == true) { //Check to see if all permission checks passed before trying to save data.
                    Debug::Text('Attempting to delete record...', __FILE__, __LINE__, __METHOD__, 10);
                    $lf->setDeleted(true);

                    $is_valid = $lf->isValid();
                    if ($is_valid == true) {
                        Debug::Text('Record Deleted...', __FILE__, __LINE__, __METHOD__, 10);
                        $save_result[$key] = $lf->Save();
                        $validator_stats['valid_records']++;
                    }
                }

                if ($is_valid == false) {
                    Debug::Text('Data is Invalid...', __FILE__, __LINE__, __METHOD__, 10);

                    $lf->FailTransaction(); //Just rollback this single record, continue on to the rest.

                    $validator[$key] = $this->setValidationArray($primary_validator, $lf);
                }

                $lf->CommitTransaction();

                $this->getProgressBarObject()->set($this->getAMFMessageID(), $key);
            }

            $this->getProgressBarObject()->stop($this->getAMFMessageID());

            return $this->handleRecordValidationResults($validator, $validator_stats, $key, $save_result);
        }

        return $this->returnHandler(false);
    }

    public function getUserGenericStatusCountArray($user_id, $batch_id)
    {
        $user_id = $this->getCurrentUserObject()->getId();
        if ($batch_id != '') {
            $ugslf = TTnew('UserGenericStatusListFactory');
            $status_count_arr = $ugslf->getStatusCountArrayByUserIdAndBatchId($user_id, $batch_id);

            return $this->returnHandler($status_count_arr);
        }

        return $this->returnHandler(false);
    }
}
