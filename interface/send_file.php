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
if ( isset($_GET['disable_db']) AND $_GET['disable_db'] == 1 ) {
	$disable_database_connection = TRUE;
}
require_once('../includes/global.inc.php');
require_once('PEAR.php');
require_once('HTTP/Download.php');

extract        (FormVariables::GetVariables(
                                                                               array   (
                                                                                               'action',
                                                                                               'api', //Called from Flex
                                                                                               'object_type',
                                                                                               'parent_object_type_id',
                                                                                               'object_id',
                                                                                               'parent_id',
                                                                                               ) ) );

if ( Misc::checkValidReferer() == FALSE ) { //Help prevent CSRF attacks with this.
	echo TTi18n::getText( 'Invalid referrer, possible CSRF.' );
	Debug::writeToLog();
	exit;
}

//sendFormIFrameCall (js) passes json data
//Make sure we accept it here GovernmentDocument uses this
if ( isset($_POST['json']) AND $_POST['json'] != '' ) {
	$json_arguments = json_decode( $_POST['json'], TRUE );
	if ( isset($json_arguments['object_id']) ) {
		Debug::Text('JSON overriding object_id...', __FILE__, __LINE__, __METHOD__, 10);
		$object_id = $json_arguments['object_id'];
	}
}

if ( isset($api) AND $api == TRUE ) {
	require_once('../includes/API.inc.php');
}

$object_type = strtolower($object_type);

if ( $object_type != 'primary_company_logo' AND $object_type != 'copyright' AND $object_type != 'smcopyright' AND $object_type != 'copyright_wide' ) {
	$skip_message_check = TRUE;
	require_once(Environment::getBasePath() .'includes/Interface.inc.php');
}

switch ($object_type) {
	case 'document':
		Debug::Text('Document...', __FILE__, __LINE__, __METHOD__, 10);

		//RateLimit failed download attempts to prevent brute force.
		$rl = TTNew('RateLimit'); /** @var RateLimit $rl */
		$rl->setID( 'document_'. Misc::getRemoteIPAddress() );
		$rl->setAllowedCalls( 25 );
		$rl->setTimeFrame( 900 ); //15 minutes
		if ( $rl->check() == FALSE ) {
			Debug::Text('Excessive document download attempts... Preventing downloads from: '. Misc::getRemoteIPAddress() .' for up to 15 minutes...', __FILE__, __LINE__, __METHOD__, 10);
			sleep(5); //Excessive download attempts, sleep longer.
		} else {
			if ( ( $permission->Check( 'document', 'view') OR $permission->Check('document', 'view_own') OR $permission->Check('document', 'view_child') OR $permission->Check('document', 'view_private') )
					OR ( isset( $parent_object_type_id ) AND $parent_object_type_id == 400 //400=Expense
							AND ( $permission->Check('expense', 'view_own') OR $permission->Check('expense', 'view_child') OR $permission->Check('expense', 'view') ) )
			) {

				$filter_data = array('filter_data' => array( 'id' => $parent_id, 'filter_items_per_page' => 1, 'filter_columns' => array( 'id' => TRUE ) ) );
				if ( isset($parent_object_type_id) AND $parent_object_type_id != '' ) {
					$filter_data['filter_data']['object_type_id'] = $parent_object_type_id;
				}

				//Make sure user has access to this document first, before checking for any revisions.
				$api_f = TTNew('APIDocument'); /** @var APIDocument $api_f */
				$result = $api_f->stripReturnHandler( $api_f->getDocument( $filter_data ) );
				if ( isset($result[0]) AND count($result[0]) > 0 ) {
					$parent_id = $result[0]['id'];

					// The attached documents to expenses all be marked as 'Private', and the regular employee no have the 'view_private' permission, so need to set the view_private to TRUE.
					if ( isset( $parent_object_type_id ) AND $parent_object_type_id == 400 ) {
						$private_allowed = TRUE;
					} else {
						$private_allowed = $permission->Check('document', 'view_private');
					}

					$drlf = TTnew( 'DocumentRevisionListFactory' ); /** @var DocumentRevisionListFactory $drlf */
					$drlf->getByCompanyIdAndIdAndDocumentIdAndPrivateAllowed( $current_company->getId(), $object_id, $parent_id, $private_allowed );
					Debug::Text('Record Count: '. $drlf->getRecordCount(), __FILE__, __LINE__, __METHOD__, 10);
					if ( $drlf->getRecordCount() == 1 ) {
						$dr_obj = $drlf->getCurrent();

						$file_name = $dr_obj->getStoragePath().$dr_obj->getLocalFileName();
						Debug::Text('File Name: '. $file_name .' Mime: '. $dr_obj->getMimeType(), __FILE__, __LINE__, __METHOD__, 10);
						if ( file_exists($file_name) ) {
							$rl->delete(); //Clear download rate limit upon successful download.

							//Log document downloads in audit report, just so people can see who has viewed which revision.
							//Make sure we link this to the main document_id so its viewed in the main document audit tab.
							TTLog::addEntry( (int)$parent_id, 5, TTi18n::getText('Downloaded Revision: %1', array( $dr_obj->getRevision() ) ), NULL, $dr_obj->getTable(), $dr_obj );

							$params['file'] = $file_name;
							$params['ContentType'] = $dr_obj->getMimeType();
							$params['ContentDisposition'] = array( HTTP_DOWNLOAD_ATTACHMENT, basename( $dr_obj->getRemoteFileName() ) );
							$params['cache'] = FALSE;
						} else {
							Debug::Text('File does not exist... File Name: '. $file_name .' Mime: '. $dr_obj->getMimeType(), __FILE__, __LINE__, __METHOD__, 10);
						}
					} else {
						Debug::text('Document Downloads Failed! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
						sleep( ($rl->getAttempts() * 0.5) );
					}
				} else {
					Debug::text('ERROR: User does not have access to document! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
					sleep( ($rl->getAttempts() * 0.5) );
				}
			} else {
				Debug::text('Permissions failed!', __FILE__, __LINE__, __METHOD__, 10);
			}
		}
		Debug::writeToLog(); //Write to log when downloading documents.
		break;
	case 'client_payment_signature':
		Debug::Text('Client Payment Signature...', __FILE__, __LINE__, __METHOD__, 10);

		//RateLimit failed download attempts to prevent brute force.
		$rl = TTNew('RateLimit'); /** @var RateLimit $rl */
		$rl->setID( 'document_'. Misc::getRemoteIPAddress() );
		$rl->setAllowedCalls( 25 );
		$rl->setTimeFrame( 900 ); //15 minutes
		if ( $rl->check() == FALSE ) {
			Debug::Text('Excessive document download attempts... Preventing downloads from: '. Misc::getRemoteIPAddress() .' for up to 15 minutes...', __FILE__, __LINE__, __METHOD__, 10);
			sleep(5); //Excessive download attempts, sleep longer.
		} else {
			$cplf = TTnew( 'ClientPaymentListFactory' ); /** @var ClientPaymentListFactory $cplf */
			$cplf->getByIdAndClientId($object_id, $parent_id);
			if ( $cplf->getRecordCount() == 1 ) {
				//echo "File Name: $file_name<br>\n";
				$cp_obj = $cplf->getCurrent();

				$file_name = $cp_obj->getSignatureFileName();
				Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
				if ( file_exists($file_name) ) {
					$rl->delete(); //Clear download rate limit upon successful download.

					$params['file'] = $file_name;
					$params['ContentType'] = 'image/png';
					$params['ContentDisposition'] = array( HTTP_DOWNLOAD_ATTACHMENT, 'signature.png' );
					$params['cache'] = FALSE;
				} else {
					Debug::text('Document Downloads Failed! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
					sleep( ($rl->getAttempts() * 0.5) );
				}
			}
		}
		break;
	case 'invoice_config':
		Debug::Text('Invoice Config...', __FILE__, __LINE__, __METHOD__, 10);

		$icf = TTNew('InvoiceConfigFactory'); /** @var InvoiceConfigFactory $icf */
		$file_name = $icf->getLogoFileName( $current_company->getId() );
		Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
		if ( file_exists($file_name) ) {
			$params['file'] = $file_name;
			$params['ContentType'] = Misc::getMimeType( $file_name );
			//$params['ContentType'] = 'image/'. strtolower( pathinfo($file_name, PATHINFO_EXTENSION) );
			$params['ContentDisposition'] = array( HTTP_DOWNLOAD_INLINE, basename( $file_name ) );
			$params['cache'] = TRUE;
		}
		break;
	case 'company_logo':
		Debug::Text('Company Logo...', __FILE__, __LINE__, __METHOD__, 10);
		header_remove('Expires'); //Allow caching.

		$cf = TTnew( 'CompanyFactory' ); /** @var CompanyFactory $cf */
		$file_name = $cf->getLogoFileName( $current_company->getId() );
		Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
		if ( $file_name != '' AND file_exists($file_name) ) {
			$params['file'] = $file_name;
			$params['ContentType'] = Misc::getMimeType( $file_name );
			//$params['ContentType'] = 'image/'. strtolower( pathinfo($file_name, PATHINFO_EXTENSION) );
			$params['ContentDisposition'] = array( HTTP_DOWNLOAD_INLINE, basename( $file_name ) );
			$params['cache'] = TRUE;
		}
		break;
	case 'legal_entity_logo':
		Debug::Text('Legal Entity Logo ['. $object_id .']...', __FILE__, __LINE__, __METHOD__, 10);
		header_remove('Expires'); //Allow caching.

		$lef = TTnew( 'LegalEntityFactory' ); /** @var LegalEntityFactory $lef */
		$file_name = $lef->getLogoFileName( $object_id );
		Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
		if ( $file_name != '' AND file_exists($file_name) ) {
			$params['file'] = $file_name;
			$params['ContentType'] = Misc::getMimeType( $file_name );
			//$params['ContentType'] = 'image/'. strtolower( pathinfo($file_name, PATHINFO_EXTENSION) );
			$params['ContentDisposition'] = array( HTTP_DOWNLOAD_INLINE, basename( $file_name ) );
			$params['cache'] = TRUE;
		}
		break;
	case 'primary_company_logo':
		Debug::Text('Primary Company Logo...', __FILE__, __LINE__, __METHOD__, 10);
		header_remove('Expires'); //Allow caching.

		$cf = TTnew( 'CompanyFactory' ); /** @var CompanyFactory $cf */
		$file_name = $cf->getLogoFileName( PRIMARY_COMPANY_ID, TRUE, TRUE );
		Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
		if ( $file_name != '' AND file_exists($file_name) ) {
			$params['file'] = $file_name;
			$params['ContentType'] = Misc::getMimeType( $file_name );
			//$params['ContentType'] = 'image/'. strtolower( pathinfo($file_name, PATHINFO_EXTENSION) );
			$params['ContentDisposition'] = array( HTTP_DOWNLOAD_INLINE, basename( $file_name ) );
			$params['cache'] = TRUE;
		}
		break;
	case 'user_photo':
		Debug::Text('User Photo...', __FILE__, __LINE__, __METHOD__, 10);

		//RateLimit failed download attempts to prevent brute force.
		$rl = TTNew('RateLimit'); /** @var RateLimit $rl */
		$rl->setID( 'user_photo_'. Misc::getRemoteIPAddress() );
		$rl->setAllowedCalls( 25 );
		$rl->setTimeFrame( 900 ); //15 minutes
		if ( $rl->check() == FALSE ) {
			Debug::Text('Excessive document download attempts... Preventing downloads from: '. Misc::getRemoteIPAddress() .' for up to 15 minutes...', __FILE__, __LINE__, __METHOD__, 10);
			sleep(5); //Excessive download attempts, sleep longer.
		} else {
			if ( $permission->Check('user', 'view')
					OR $permission->Check('user', 'view_own')
					OR $permission->Check('user', 'view_child') ) {

				$api_f = TTNew('APIUser'); /** @var APIUser $api_f */
				$result = $api_f->stripReturnHandler( $api_f->getUser( array('filter_data' => array( 'id' => $object_id ) ) ) );
				if ( isset($result[0]) AND count($result[0]) > 0 ) {
					$uf = TTnew( 'UserFactory' ); /** @var UserFactory $uf */
					$file_name = $uf->getPhotoFileName( $current_company->getId(), $object_id );
					Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
					if ( $file_name != '' AND file_exists($file_name) ) {
						$rl->delete(); //Clear download rate limit upon successful download.

						$params['file'] = $file_name;
						$params['ContentType'] = Misc::getMimeType( $file_name );
						//$params['ContentType'] = 'image/'. strtolower( pathinfo($file_name, PATHINFO_EXTENSION) );
						$params['ContentDisposition'] = array( HTTP_DOWNLOAD_INLINE, basename( $file_name ) );
						$params['cache'] = TRUE;
					} else {
						Debug::text('aPhoto Download Failed! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
						sleep( ($rl->getAttempts() * 0.5) );
					}
				} else {
					Debug::text('bPhoto Downloads Failed! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
					sleep( ($rl->getAttempts() * 0.5) );
				}
			}
		}
		break;
	case 'remittance_source_account':
		Debug::Text('Remittance Source Account Signature...', __FILE__, __LINE__, __METHOD__, 10);

		//RateLimit failed download attempts to prevent brute force.
		$rl = TTNew('RateLimit'); /** @var RateLimit $rl */
		$rl->setID( 'remittance_source_account_'. Misc::getRemoteIPAddress() );
		$rl->setAllowedCalls( 25 );
		$rl->setTimeFrame( 900 ); //15 minutes
		if ( $rl->check() == FALSE ) {
			Debug::Text('Excessive document download attempts... Preventing downloads from: '. Misc::getRemoteIPAddress() .' for up to 15 minutes...', __FILE__, __LINE__, __METHOD__, 10);
			sleep(5); //Excessive download attempts, sleep longer.
		} else {
			if ( $permission->Check('remittance_source_account', 'view')
					OR $permission->Check('remittance_source_account', 'view_own')
					OR $permission->Check('remittance_source_account', 'view_child') ) {

				$api_f = TTNew('APIRemittanceSourceAccount'); /** @var APIRemittanceSourceAccount $api_f */
				$result = $api_f->stripReturnHandler( $api_f->getRemittanceSourceAccount( array('filter_data' => array( 'id' => $object_id ) ) ) );
				if ( isset($result[0]) AND count($result[0]) > 0 ) {
					$rsaf = TTnew( 'RemittanceSourceAccountFactory' ); /** @var RemittanceSourceAccountFactory $rsaf */
					$file_name = $rsaf->getSignatureFileName( $current_company->getId(), $object_id );
					Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
					if ( $file_name != '' AND file_exists($file_name) ) {
						$rl->delete(); //Clear download rate limit upon successful download.

						$params['file'] = $file_name;
						$params['ContentType'] = Misc::getMimeType( $file_name );
						//$params['ContentType'] = 'image/'. strtolower( pathinfo($file_name, PATHINFO_EXTENSION) );
						$params['ContentDisposition'] = array( HTTP_DOWNLOAD_INLINE, basename( $file_name ) );
						$params['cache'] = TRUE;
					} else {
						Debug::text('aSignature Download Failed! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
						sleep( ($rl->getAttempts() * 0.5) );
					}
				} else {
					Debug::text('bSignature Downloads Failed! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
					sleep( ($rl->getAttempts() * 0.5) );
				}
			}
		}
		break;
	case 'government_document':
		Debug::Text('Government Document...', __FILE__, __LINE__, __METHOD__, 10);
		//RateLimit failed download attempts to prevent brute force.
		$rl = TTNew('RateLimit'); /** @var RateLimit $rl */
		$rl->setID( 'document_'. Misc::getRemoteIPAddress() );
		$rl->setAllowedCalls( 25 );
		$rl->setTimeFrame( 900 ); //15 minutes
		if ( $rl->check() == FALSE ) {
			Debug::Text('Excessive document download attempts... Preventing downloads from: '. Misc::getRemoteIPAddress() .' for up to 15 minutes...', __FILE__, __LINE__, __METHOD__, 10);
			sleep(5); //Excessive download attempts, sleep longer.
		} else {
			if ( $permission->Check( 'government_document', 'view' )
					OR $permission->Check( 'government_document', 'view_own' )
					OR $permission->Check( 'government_document', 'view_child' ) ) {

				$api_f = TTNew( 'APIGovernmentDocument' ); /** @var APIGovernmentDocument $api_f */
				$result = $api_f->stripReturnHandler( $api_f->getGovernmentDocument( array('filter_data' => array('id' => $object_id)) ) );

				if ( isset( $result ) AND is_array($result)  AND count( $result ) > 0 ) {
					$rl->delete(); //Clear download rate limit upon successful download.

					$files = array();
					foreach ( $result as $doc ) {
						$gf = TTnew( 'GovernmentDocumentFactory' ); /** @var GovernmentDocumentFactory $gf */
						$file_name = $gf->getFileName( $current_company->getId(), $doc['type_id'], $doc['user_id'], $doc['id'] );
						if ( $file_name != '' AND file_exists( $file_name ) ) {
							$file = array();
							$file['file_name'] = $doc['type'] . '_' . TTDate::getYear( TTDate::parseDateTime( $doc['date'] ) ) . '_' . $gf->Validator->stripNonAlphaNumeric( $doc['last_name'] ) . '_' . $gf->Validator->stripNonAlphaNumeric( $doc['first_name'] ) . '.pdf';
							$file['data'] = file_get_contents( $file_name );
							$file['mime_type'] = 'application/pdf';
							$files[] = $file;
						}
					}

					if ( count($files) > 0 ) {
						$zip_file = Misc::zip( $files, FALSE, TRUE );

						$zip_file_name = 'government_documents.zip';
						if ( count( $files ) == 1 ) {
							$zip_file_name = $zip_file['file_name'];
						}
						Misc::APIFileDownload( $zip_file_name, $zip_file['mime_type'], $zip_file['data'] );
					} else {
						Debug::text( 'ERROR: No file to download! File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10 );
					}

					Debug::writeToLog();
					die();
				} else {
					Debug::text( 'bDocument Downloads Failed! Attempt: ' . $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10 );
					sleep( ( $rl->getAttempts() * 0.5 ) );
				}
			}
		}
		break;
	case 'punch_image':
		Debug::Text('Punch Image...', __FILE__, __LINE__, __METHOD__, 10);

		//RateLimit failed download attempts to prevent brute force.
		$rl = TTNew('RateLimit'); /** @var RateLimit $rl */
		$rl->setID( 'punch_image_'. Misc::getRemoteIPAddress() );
		$rl->setAllowedCalls( 25 );
		$rl->setTimeFrame( 900 ); //15 minutes
		if ( $rl->check() == FALSE ) {
			Debug::Text('Excessive document download attempts... Preventing downloads from: '. Misc::getRemoteIPAddress() .' for up to 15 minutes...', __FILE__, __LINE__, __METHOD__, 10);
			sleep(5); //Excessive download attempts, sleep longer.
		} else {
			if ( $permission->Check('punch', 'view')
					OR $permission->Check('punch', 'view_own')
					OR $permission->Check('punch', 'view_child') ) {

				$api_f = TTNew('APIPunch'); /** @var APIPunch $api_f */
				$result = $api_f->stripReturnHandler( $api_f->getPunch( array('filter_data' => array( 'id' => $object_id ) ) ) );
				if ( isset($result[0]) AND count($result[0]) > 0 ) {
					$pf = TTnew( 'PunchFactory' ); /** @var PunchFactory $pf */
					$file_name = $pf->getImageFileName( $current_company->getId(), $parent_id, $object_id );
					Debug::Text('File Name: '. $file_name .' Company ID: '. $current_company->getId() .' User ID: '. $parent_id, __FILE__, __LINE__, __METHOD__, 10);
					if ( $file_name != '' AND file_exists($file_name) ) {
						$rl->delete(); //Clear download rate limit upon successful download.

						$params['file'] = $file_name;
						$params['ContentType'] = Misc::getMimeType( $file_name );
						//$params['ContentType'] = 'image/'. strtolower( pathinfo($file_name, PATHINFO_EXTENSION) );
						$params['ContentDisposition'] = array( HTTP_DOWNLOAD_INLINE, basename( $file_name ) );
						$params['cache'] = TRUE;
					} else {
						Debug::text('aPunch image Downloads Failed! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
						sleep( ($rl->getAttempts() * 0.5) );
					}
				} else {
					Debug::text('bPunch image Downloads Failed! Attempt: '. $rl->getAttempts(), __FILE__, __LINE__, __METHOD__, 10);
					sleep( ($rl->getAttempts() * 0.5) );
				}
			}
		}
		break;
	case 'copyright':
		Debug::Text('Copyright Logo...', __FILE__, __LINE__, __METHOD__, 10);
		header_remove('Expires'); //Allow caching.
		$file_name = Environment::getImagesPath().'/powered_by.jpg';
		Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
		if ( $file_name != '' AND file_exists($file_name) ) { 
			$params['file'] = $file_name;$params['ContentType'] = 'image/jpeg';
			$params['ContentDisposition'] = array( HTTP_DOWNLOAD_ATTACHMENT, 'pro_copyright.jpg' );
			$params['data'] = file_get_contents($file_name);
			$params['cache'] = TRUE;
		}
		break;
	case 'copyright_wide':
	case 'smcopyright':
		Debug::Text('Copyright Logo...', __FILE__, __LINE__, __METHOD__, 10);
		header_remove('Expires'); //Allow caching.

		$file_name = Environment::getImagesPath().'/powered_by_wide.png';
		Debug::Text('File Name: '. $file_name, __FILE__, __LINE__, __METHOD__, 10);
		if ( $file_name != '' AND file_exists($file_name) ) {
			$params['file'] = $file_name;$params['ContentType'] = 'image/png';
			$params['ContentDisposition'] = array( HTTP_DOWNLOAD_ATTACHMENT, 'pro_copyright_wide.png' );
			$params['data'] = file_get_contents($file_name);
			$params['cache'] = TRUE;
		}
		break;
	default:
		break;
}

//Debug::Arr($params, 'Download Params:', __FILE__, __LINE__, __METHOD__, 10);
if ( isset($params) ) {
	$retval = HTTP_Download::staticSend($params);
	if ( $retval !== TRUE ) {
		Debug::Arr($params, 'Download Params:', __FILE__, __LINE__, __METHOD__, 10);
		Debug::Text('ERROR: Download Failed: '. $retval->message, __FILE__, __LINE__, __METHOD__, 10);
		Debug::writeToLog();
	}
} else {
	echo "File does not exist, unable to download!<br>\n";
	Debug::writeToLog();
}
?>