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

require_once( dirname(__FILE__) . DIRECTORY_SEPARATOR .'..'. DIRECTORY_SEPARATOR .'..'. DIRECTORY_SEPARATOR .'includes'. DIRECTORY_SEPARATOR .'global.inc.php');
require_once( dirname(__FILE__) . DIRECTORY_SEPARATOR .'..'. DIRECTORY_SEPARATOR .'..'. DIRECTORY_SEPARATOR .'includes'. DIRECTORY_SEPARATOR .'CLI.inc.php');

//
//
// Main
//
//
if ( $argc <= 1 OR in_array ($argv[1], array('--help', '-help', '-h', '-?') ) ) {
	$help_output = "Usage: export_pay_stubs.php [OPTIONS] [Output Directory]\n";
	$help_output .= "  Options:\n";
	$help_output .= "    -c [Company ID]			Defaults to 1\n";
	$help_output .= "    -t [Pay Period Date Type]		Start/End/Transaction/Last\n";
	$help_output .= "                              		Last will use the latest pay period\n";
	$help_output .= "    -d [Pay Period Date]		Format: YYYYMMDD\n";

	echo $help_output;

} else {
	//FIXME: Use Pears Console_GetArgs package to handle these better.

	//Handle command line arguments
	$last_arg = count($argv)-1;

	if ( in_array('-c', $argv) ) {
		$company_id = strtolower( trim($argv[array_search('-c', $argv)+1]) );
	} else {
		$company_id = 1;
	}

	if ( in_array('-t', $argv) ) {
		$date_type = strtolower( trim($argv[array_search('-t', $argv)+1]) );
	} else {
		$date_type = 'last';
		echo "Pay Period Date Type not specified, assuming: Last\n";
	}

	$pay_period_date = NULL;
	if ( in_array('-d', $argv) ) {
		$pay_period_date = TTDate::getBeginDayEpoch( strtotime( trim($argv[array_search('-d', $argv)+1]) ) );
	} else {
		echo "Pay Period Date not specified, assuming: Last\n";
	}

	if ( isset($argv[$last_arg]) AND $argv[$last_arg] != '' ) {
		if ( !file_exists( $argv[$last_arg] ) OR !is_writable( $argv[$last_arg] ) ) {
			echo "Output Directory: ". $argv[$last_arg] ." does not exists or is not writable!\n";
			exit;
		} else {
			$output_directory = $argv[$last_arg];
		}
	}

	if ( $date_type != 'last' ) {
		echo "Searching for Pay Period ". ucfirst($date_type) ." Date: ". TTDate::getDate('DATE', $pay_period_date ) ."...\n";
	} else {
		echo "Searching for Last Pay Period...\n";
	}

	$pplf = new PayPeriodListFactory();
	$pplf->getPayPeriodsWithPayStubsByCompanyId( $company_id, NULL, array('a.start_date' => 'desc') );
	if ( $pplf->getRecordCount() > 0 ) {
		$x=0;
		$found_pay_period = FALSE;
		foreach( $pplf as $pp_obj ) {

			if ( $date_type == 'start' AND TTDate::getBeginDayEpoch($pp_obj->getStartDate()) == $pay_period_date ) {
				$found_pay_period = TRUE;
			} elseif ( $date_type == 'end' AND TTDate::getBeginDayEpoch($pp_obj->getEndDate()) == $pay_period_date ) {
				$found_pay_period = TRUE;
			} elseif ( $date_type == 'transaction' AND TTDate::getBeginDayEpoch($pp_obj->getTransactionDate()) == $pay_period_date ) {
				$found_pay_period = TRUE;
			} elseif ( $date_type == 'last' ) {
				//Last pay period
				$found_pay_period = TRUE;
			}

			if ( $found_pay_period == TRUE ) {
				echo "Found Pay Period: Start: ". TTDate::getDate('DATE', $pp_obj->getStartDate() ) .' End: '. TTDate::getDate('DATE', $pp_obj->getEndDate() ) .' Transaction: '. TTDate::getDate('DATE', $pp_obj->getTransactionDate() ) ."\n";
				$pay_period_id = $pp_obj->getId();
				break;
			}

			$x++;
		}
	}

	if ( isset($pay_period_id) ) {
		$pslf = new PayStubListFactory();
		$pslf->getByCompanyIdAndPayPeriodId( $company_id, $pay_period_id );
		if ( $pslf->getRecordCount() > 0 ) {
			echo "Export Directory: ". $output_directory ."\n";
			$i=1;
			foreach( $pslf as $tmp_ps_obj ) {
				$pslf_b = new PayStubListFactory();
				$pslf_b->getById( $tmp_ps_obj->getId() );
				if ( $pslf_b->getRecordCount() > 0 ) {
					$ps_obj = $pslf_b->getCurrent();

					if ( is_object( $ps_obj->getUserObject() ) ) {
						$file_name = $output_directory . DIRECTORY_SEPARATOR . 'pay_stub_'. $ps_obj->getUserObject()->getUserName() .'_'. date('Ymd', $ps_obj->getStartDate() ) .'.pdf';

						$output = $pslf->getPayStub( $pslf_b, TRUE );
						if ( $output !== FALSE ) {
							echo "  $i. Exporting Pay Stub for: ". $ps_obj->getUserObject()->getFullName(). "\t\tFile: ". $file_name ."\n";

							file_put_contents( $file_name, $output );
							unset($output);

							$i++;
						} else {
							echo "  ERROR: Unable to Export Pay Stub.\n";
						}
					}
				}
			}
		} else {
			echo "ERROR: No Pay Stubs Found in Pay Period!\n";
		}
	} else {
		echo "ERROR: No Pay Period Found Matching that Date!!\n";
	}

	echo "Done!\n";
}
//Debug::Display();
?>