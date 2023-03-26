<?php

// Load Dolibarr environment
require '../../main.inc.php';
require_once '../../vendor/autoload.php';

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



if (!$user->rights->salaries->read) {
	accessforbidden("you don't have right for this page");
}




$sites = ['agadir', 'ain sebaa', 'bourgone', 'ennassim', 'jnane bernoussi', 'marrakech', 'oulfa', 'rangement', 'siège', 'temara', 'val fleurie'];
$fileHeaders = ["Matricule", "Site", "CNSS", "Nom", "Prenom", "Date Naissance", "Date Embauche", "Emploi occupé", "RIB"];
$rubsWithName = GETPOST('rubsWithName', 'array'); //get the selected rubriques'id 
$rubsWithId = GETPOST('rubsWithId', 'array'); //get the selected rubriques'id 
$action = GETPOST('action', 'aZ09'); // The action 'add', 'create', 'edit', 'update', 'view', ...
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'userlist'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page

//get rubriques
$rubsNameToId = array();
$rubsName = array();
$rubsId = array();
$sql = "SELECT rub,designation FROM llx_Paie_Rub";
$res = $db->query($sql);
if ($res->num_rows) {
	for ($i = 1; $i <= $res->num_rows; $i++) {
		$row = $res->fetch_assoc();
		$rubsId[$row["rub"]] = $row["designation"];
	}
}
$sql = "SELECT rub,designation FROM llx_Paie_HourSupp";
$res = $db->query($sql);
if ($res->num_rows) {
	for ($i = 1; $i <= $res->num_rows; $i++) {
		$row = $res->fetch_assoc();
		$rubsId[$row["rub"]] = $row["designation"];
	}
}
$sql = "SELECT rub, designation, name FROM llx_Paie_Rubriques";
$res = $db->query($sql);
if ($res->num_rows) {
	for ($i = 1; $i <= $res->num_rows; $i++) {
		$row = $res->fetch_assoc();
		$rubsName[$row["name"]] = $row["designation"];
		$rubsNameToId[$row["name"]] = $row["rub"];
	}
}

// Security check (for external users)
$socid = 0;
if ($user->socid > 0) {
	$socid = $user->socid;
}


// Load variable for pagination
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}     // If $page is not defined, or '' or -1 or if we click on clear filters
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$object = new User($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->user->dir_output . '/temp/massgeneration/' . $user->id;
$hookmanager->initHooks(array('userlist'));

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

if (!$sortfield) {
	$sortfield = "u.login";
}
if (!$sortorder) {
	$sortorder = "ASC";
}

// Initialize array of search criterias
$search_all = GETPOST('search_all', 'alphanohtml') ? GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml');
$search = array();
foreach ($object->fields as $key => $val) {
	if (GETPOST('search_' . $key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_' . $key, 'alpha');
	}
}

$userstatic = new User($db);
$companystatic = new Societe($db);
$form = new Form($db);

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'u.login' => "Login",
	'u.lastname' => "Lastname",
	'u.firstname' => "Firstname",
	'u.accountancy_code' => "AccountancyCode",
	'u.office_phone' => "PhonePro",
	'u.user_mobile' => "PhoneMobile",
	'u.email' => "EMail",
	'u.note' => "Note",
);
if (!empty($conf->api->enabled)) {
	$fieldstosearchall['u.api_key'] = "ApiKey";
}

// Definition of fields for list
$arrayfields = array(
	'u.login' => array('label' => "Login", 'checked' => 1, 'position' => 10),
	'u.lastname' => array('label' => "Lastname", 'checked' => 1, 'position' => 15),
	'u.firstname' => array('label' => "Firstname", 'checked' => 1, 'position' => 20),
	'u.entity' => array('label' => "Entity", 'checked' => 1, 'position' => 50, 'enabled' => (!empty($conf->multicompany->enabled) && empty($conf->global->MULTICOMPANY_TRANSVERSE_MODE))),
	'u.gender' => array('label' => "Gender", 'checked' => 0, 'position' => 22),
	'u.fk_user' => array('label' => "HierarchicalResponsible", 'checked' => 1, 'position' => 27),
	'u.accountancy_code' => array('label' => "AccountancyCode", 'checked' => 0, 'position' => 30),
	'u.office_phone' => array('label' => "PhonePro", 'checked' => 1, 'position' => 31),
	'u.user_mobile' => array('label' => "PhoneMobile", 'checked' => 1, 'position' => 32),
	'u.email' => array('label' => "EMail", 'checked' => 1, 'position' => 35),
	'u.api_key' => array('label' => "ApiKey", 'checked' => 0, 'position' => 40, "enabled" => (!empty($conf->api->enabled) && $user->admin)),
	'u.fk_soc' => array('label' => "Company", 'checked' => ($contextpage == 'employeelist' ? 0 : 1), 'position' => 45),
	'u.salary' => array('label' => "Salary", 'checked' => 1, 'position' => 80, 'enabled' => (!empty($conf->salaries->enabled) && !empty($user->rights->salaries->readall))),
	'u.datelastlogin' => array('label' => "LastConnexion", 'checked' => 1, 'position' => 100),
	'u.datepreviouslogin' => array('label' => "PreviousConnexion", 'checked' => 0, 'position' => 110),
	'u.datec' => array('label' => "DateCreation", 'checked' => 0, 'position' => 500),
	'u.tms' => array('label' => "DateModificationShort", 'checked' => 0, 'position' => 500),
	'u.statut' => array('label' => "Status", 'checked' => 1, 'position' => 1000),
);
// Extra fields
if (is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label']) > 0) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		if (!empty($extrafields->attributes[$object->table_element]['list'][$key]))
			$arrayfields["ef." . $key] = array('label' => $extrafields->attributes[$object->table_element]['label'][$key], 'checked' => (($extrafields->attributes[$object->table_element]['list'][$key] < 0) ? 0 : 1), 'position' => $extrafields->attributes[$object->table_element]['pos'][$key], 'enabled' => (abs($extrafields->attributes[$object->table_element]['list'][$key]) != 3 && $extrafields->attributes[$object->table_element]['perms'][$key]));
	}
}

$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');

// Init search fields
$sall = trim((GETPOST('search_all', 'alphanohtml') != '') ? GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml'));
$search_user = GETPOST('search_user', 'alpha');
$search_login = GETPOST('search_login', 'alpha');
$search_lastname = GETPOST('search_lastname', 'alpha');
$search_firstname = GETPOST('search_firstname', 'alpha');
$search_gender = GETPOST('search_gender', 'alpha');
$search_accountancy_code = GETPOST('search_accountancy_code', 'alpha');
$search_phonepro = GETPOST('search_phonepro', 'alpha');
$search_phonemobile = GETPOST('search_phonemobile', 'alpha');
$search_email = GETPOST('search_email', 'alpha');
$search_api_key = GETPOST('search_api_key', 'alphanohtml');
$search_statut = GETPOST('search_statut', 'intcomma');
$search_thirdparty = GETPOST('search_thirdparty', 'alpha');
$search_warehouse = GETPOST('search_warehouse', 'alpha');
$search_supervisor = GETPOST('search_supervisor', 'intcomma');
$search_categ = GETPOST("search_categ", 'int');
$catid = GETPOST('catid', 'int');


$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
	{
		$search_user = "";
		$search_login = "";
		$search_lastname = "";
		$search_firstname = "";
		$search_gender = "";
		$search_employee = "";
		$search_accountancy_code = "";
		$search_email = "";
		$search_statut = "";
		$search_thirdparty = "";
		$search_supervisor = "";
		$search_api_key = "";
		$search_datelastlogin = "";
		$search_datepreviouslogin = "";
		$search_date_creation = "";
		$search_date_update = "";
		$search_array_options = array();
		$search_categ = 0;
	}
}


$childids = $user->getAllChildIds(1);


// Purge search criteria
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
	$search_user = "";
	$search_login = "";
	$search_lastname = "";
	$search_firstname = "";
	$search_gender = "";
	$search_accountancy_code = "";
	$search_phonepro = "";
	$search_phonemobile = "";
	$search_email = "";
	$search_statut = "";
	$search_thirdparty = "";
	$search_warehouse = "";
	$search_supervisor = "";
	$search_api_key = "";
	$search_datelastlogin = "";
	$search_datepreviouslogin = "";
	$search_date_creation = "";
	$search_date_update = "";
	$search_array_options = array();
	$search_categ = 0;
}



/*
 * Actions
 */



//get date filter
$mydate = getdate(date("U"));

$month = (GETPOST('toMonth') != '') ? GETPOST('toMonth') : $mydate['mon'];
$year = (GETPOST('toYear') != '') ? GETPOST('toYear') : $mydate['year'];


$fromMonth = (GETPOST('fromMonth') != '') ? GETPOST('fromMonth') : $month;
$fromYear = (GETPOST('fromYear') != '') ? GETPOST('fromYear') : $year;


if ($action == 'filter') {
	$from = GETPOST('from');
	$to = GETPOST('to');
	$fromYear = explode('-', $from)[0];
	$fromMonth = explode('-', $from)[1];
	$year = explode('-', $to)[0];
	$month = explode('-', $to)[1];
}


if ($action == 'view' && isset($_POST['download'])) {
	$rubsNameToManipulate = array();
	$rubsIdToManipulate = array();


	//get rubriques with name selected
	foreach ($rubsWithName as $rub) {
		$rubsNameToManipulate[strtolower($rub)] = $rubsName[$rub];
	}

	//get rubriques with id selected
	foreach ($rubsWithId as $rub) {
		if ($rub == 'salaireNet') {
			$rubsIdToManipulate['salaireNet'] = 'Salaire net';
		} elseif ($rub == 'salaireBrut') {
			$rubsIdToManipulate['salaireBrut'] = 'Salaire brut';
		} else {
			$rubsIdToManipulate[$rub] = $rubsId[$rub];
		}
	}




	$spreadsheet = new Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	// $sheet->getColumnDimensionByColumn(1)->setAutoSize(true);

	for ($i = 0, $l = count($fileHeaders); $i < $l; $i++) {
		$sheet->setCellValueByColumnAndRow($i + 1, 2, $fileHeaders[$i]);
	}





	$j = 3;
	foreach ($toselect as $toselectid) {
		$fy = $fromYear;
		$fm = $fromMonth;
		$ty = $year;
		$tm = $month;
		$sql = "select c.rowid as id, a.matricule as matricule,a.site as site,b.cnss as cnss ,c.firstname as prenom, c.lastname as nom,
				c.birth as naissance, c.dateemployment as embauche, c.job as fonctioan, r.number as rib from llx_user_extrafields a, llx_Paie_UserInfo b,
				llx_user c, llx_user_rib r where c.rowid = $toselectid and b.userid = $toselectid and a.fk_object = $toselectid and r.fk_user = $toselectid";
		$res = $db->query($sql);
		if ($res->num_rows) {
			$row = $res->fetch_array();

			for ($i = 1; $i < 10; $i++) {
				if ($i == 2) {
					if (empty(trim($row[$i]))) {
						$sheet->setCellValueByColumnAndRow($i, $j, '');
					} else {
						$index = $row[$i] - 1;
						$sheet->setCellValueByColumnAndRow($i, $j, $sites[$index]);
					}
				} else {
					$sheet->setCellValueByColumnAndRow($i, $j, $row[$i]);
				}
				if (empty(trim($row[$i]))) {
					$column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
					$sheet->getStyle($column . $j)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF0000');
				}
			}

			$firstIndex = 10;
			while ($fy <= $ty) {
				$rubValues = array();
				foreach ($rubsNameToManipulate as $key => $value) {
					$rubValues[$key] = '-';
				}
				foreach ($rubsIdToManipulate as $key => $value) {
					$rubValues[$key] = '-';
				}



				// // see if it's clotured
				// $cloture = 0;
				// $sql1 = "SELECT cloture FROM llx_Paie_MonthDeclaration WHERE userid=$row[0] AND year=$fy AND month=$fm";
				// $res1 = $db->query($sql1);
				// if ($res1) {
				// 	$row1 = $res1->fetch_assoc();
				// 	$cloture = $row1["cloture"] > 0 ? $row1["cloture"] : 0;
				// }

				if (1) {
					$sql = "SELECT * FROM  llx_Paie_MonthDeclaration m, llx_Paie_MonthDeclarationRubs r 
					WHERE r.userid=m.userid AND r.month=m.month AND r.year = m.year 
					AND m.userid= $row[0] AND m.month= $fm AND m.year = $fy";
					$res = $db->query($sql);
					if (((object)$res)->num_rows > 0) {
						$row3 = $res->fetch_assoc();

						if (array_key_exists('salairemensuel', $rubValues)) {
							$rubValues['salairemensuel'] = $row3["salaireMensuel"];
						}
						if (array_key_exists('salairehoraire', $rubValues)) {
							$rubValues['salairehoraire'] = $row3["salaireHoraire"];
						}
						if (array_key_exists('netimposable', $rubValues)) {
							$rubValues['netimposable'] = (float)$row3["netImposable"];
						}
						if (array_key_exists('ir', $rubValues)) {
							$rubValues['ir'] = (float)$row3["ir"];
						}
						if (array_key_exists('joursferie', $rubValues)) {
							$rubValues['joursferie'] = (int)$row3["joursferie"];
						}
						if (array_key_exists('salaireNet', $rubValues)) {
							$rubValues['salaireNet'] = $row3["salaireNet"];
						}
						if (array_key_exists('salaireBrut', $rubValues)) {
							$rubValues['salaireBrut'] = $row3["salaireBrut"];
						}
						$rubriques = $row3["rubs"];

						foreach (explode(";", $rubriques) as $r) {
							$rub = explode(":", $r);
							$idInddex = null;

							if (array_key_exists($rub[0], $rubValues)) {
								$rubValues[$rub[0]] = $rub[2];
								$idInddex = array_search($rub[0], $rubsNameToId);
							}

							// print 'id : '.$idInddex;

							if ($idInddex) {
								$rubValues[strtolower($idInddex)] = $rub[2];
							}
						}
					}
				}
				$sheet->setCellValueByColumnAndRow($firstIndex, 1, $fy . '-' . $fm);
				$fromColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($firstIndex);
				$toColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(($firstIndex + count($rubValues) - 1));
				$sheet->getStyle($fromColumn . '1' . ':' . $toColumn . '1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('D9D9D9');
				$sheet->mergeCells($fromColumn . '1' . ':' . $toColumn . '1');
				$sheet->getStyle($fromColumn . '1' . ':' . $toColumn . '1')->getAlignment()->setHorizontal('center');
				foreach ($rubValues as $key => $value) {
					if (array_key_exists($key, $rubsNameToManipulate)) {
						$sheet->setCellValueByColumnAndRow($firstIndex, 2, $rubsNameToManipulate[$key]);
					}
					if (array_key_exists($key, $rubsIdToManipulate)) {
						$sheet->setCellValueByColumnAndRow($firstIndex, 2, $rubsIdToManipulate[$key]);
					}
					$sheet->setCellValueByColumnAndRow($firstIndex, $j, $value);
					$firstIndex++;
				}
				if ($fy == $ty && $fm == $tm) {
					break;    /* You could also write 'break 1;' here. */
				} else {
					$fm++;
					if ($fm == '13') {
						$fy++;
						$fm = '1';
					}
				}
			}
			$j++;
		}
	}
	foreach ($sheet->getColumnIterator() as $column) {
		$sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
	}

	setlocale(LC_ALL, 'en_US');
	$fy = $fromYear;
	$fm = $fromMonth;
	$ty = $year;
	$tm = $month;
	if ($fromYear == $year && $fromMonth == $month) {
		$fileName = "$year" . "_" . "$month" . "_" . "etat.xlsx";
	} else {
		$fileName = "De_" . $fromYear . "-" . "$fromMonth" . "_à_" . $year . "-" . $month . ".xlsx";
	}
	$writer = new Xlsx($spreadsheet);
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
	$writer->save('php://output');
	exit();
}



llxHeader("", "Etat Base Du Personnel");


//add filter by date
datefilter();

print '
	<style>
	.date-container .inp-wrapper {
		display: flex;
		gap: 1.2em;
		justify-content: center;
	}
	.date-container label {
		color: #0f1e32;
		display: block;
		font-weight: 600;
	}
	.date-container input[type="date"] {
		font-size: 14px;
		padding: 4px;
		color: #242831;
		border: 1px solid rgb(7, 108, 147);
		outline: none;
    	border-radius: 0.2em;
	}
	.date-container ::-webkit-calendar-picker-indicator {
		background-color: #7eceee;
		padding: 0.2em;
		cursor: pointer;
		border-radius: 0.1em;
	}
		
	@media only screen and (min-width: 977px) {
		div.div-table-responsive {
			width: calc(100vw - 285px) !important;
			overflow-x: scroll !important;
		}
	}
	.dropdown ul{
		position: absolute;
		top: 11px;
		left: 1px;
		width: 175px;
	}
	</style>';




/*
 * View
 */


$formother = new FormOther($db);

$help_url = 'EN:Module_Users|FR:Module_Utilisateurs|ES:M&oacute;dulo_Usuarios|DE:Modul_Benutzer';

$text = "Etat Base Du Personnel";

$user2 = new User($db);

$sql = "SELECT DISTINCT u.rowid, u.lastname, u.firstname, u.admin, u.fk_soc, u.login, u.office_phone, u.user_mobile, u.email, u.api_key, u.accountancy_code, u.gender, u.employee, u.photo,";
$sql .= " u.salary, u.datelastlogin, u.datepreviouslogin,";
$sql .= " u.ldap_sid, u.statut, u.entity,";
$sql .= " u.tms as date_update, u.datec as date_creation,";
$sql .= " u2.rowid as id2, u2.login as login2, u2.firstname as firstname2, u2.lastname as lastname2, u2.admin as admin2, u2.fk_soc as fk_soc2, u2.office_phone as ofice_phone2, u2.user_mobile as user_mobile2, u2.email as email2, u2.gender as gender2, u2.photo as photo2, u2.entity as entity2, u2.statut as statut2,";
$sql .= " s.nom as name, s.canvas,";
// Add fields from extrafields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? "ef." . $key . " as options_" . $key . ', ' : '');
	}
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters); // Note that $action and $object may have been modified by hook
$sql .= preg_replace('/^,/', '', $hookmanager->resPrint);
$sql = preg_replace('/,\s*$/', '', $sql);
$sql .= " FROM " . MAIN_DB_PREFIX . "user as u";
if (key_exists('label', $extrafields->attributes[$object->table_element]) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . $object->table_element . "_extrafields as ef on (u.rowid = ef.fk_object)";
}
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON u.fk_soc = s.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as u2 ON u.fk_user = u2.rowid";
if (!empty($search_categ) || !empty($catid)) {
	$sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "categorie_user as cu ON u.rowid = cu.fk_user"; // We'll need this table joined to the select in order to filter by categ
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printUserListWhere', $parameters); // Note that $action and $object may have been modified by hook
if ($reshook > 0) {
	$sql .= $hookmanager->resPrint;
} else {
	$sql .= " WHERE u.entity IN (" . getEntity('user') . ") and u.employee=1";
}
if ($socid > 0) {
	$sql .= " AND u.fk_soc = " . ((int) $socid);
}
//if ($search_user != '')       $sql.=natural_search(array('u.login', 'u.lastname', 'u.firstname'), $search_user);
if ($search_supervisor > 0) {
	$sql .= " AND u.fk_user IN (" . $db->sanitize($search_supervisor) . ")";
}
if ($search_thirdparty != '') {
	$sql .= natural_search(array('s.nom'), $search_thirdparty);
}
if ($search_warehouse > 0) {
	$sql .= natural_search(array('u.fk_warehouse'), $search_warehouse);
}
if ($search_login != '') {
	$sql .= natural_search("u.login", $search_login);
}
if ($search_lastname != '') {
	$sql .= natural_search("u.lastname", $search_lastname);
}
if ($search_firstname != '') {
	$sql .= natural_search("u.firstname", $search_firstname);
}
if ($search_gender != '' && $search_gender != '-1') {
	$sql .= " AND u.gender = '" . $db->escape($search_gender) . "'"; // Cannot use natural_search as looking for %man% also includes woman
}
if ($search_accountancy_code != '') {
	$sql .= natural_search("u.accountancy_code", $search_accountancy_code);
}
if ($search_phonepro != '') {
	$sql .= natural_search("u.office_phone", $search_phonepro);
}
if ($search_phonemobile != '') {
	$sql .= natural_search("u.user_mobile", $search_phonemobile);
}
if ($search_email != '') {
	$sql .= natural_search("u.email", $search_email);
}
if ($search_api_key != '') {
	$sql .= natural_search("u.api_key", $search_api_key);
}
if ($search_statut != '' && $search_statut >= 0) {
	$sql .= " AND u.statut IN (" . $db->escape($search_statut) . ")";
}
if ($sall) {
	$sql .= natural_search(array_keys($fieldstosearchall), $sall);
}
if ($catid > 0) {
	$sql .= " AND cu.fk_categorie = " . ((int) $catid);
}
if ($catid == -2) {
	$sql .= " AND cu.fk_categorie IS NULL";
}
if ($search_categ > 0) {
	$sql .= " AND cu.fk_categorie = " . ((int) $search_categ);
}
if ($search_categ == -2) {
	$sql .= " AND cu.fk_categorie IS NULL";
}
if ($search_warehouse > 0) {
	$sql .= " AND u.fk_warehouse = " . ((int) $search_warehouse);
}
// Add where from extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_sql.tpl.php';
// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
$sql .= $db->order($sortfield, $sortorder);

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
	$resql = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($resql);
	if (($page * $limit) > $nbtotalofrecords) {	// if total of record found is smaller than page * limit, goto and load page 0
		$page = 0;
		$offset = 0;
	}
}
// if total of record found is smaller than limit, no need to do paging and to restart another select with limits set.
if (is_numeric($nbtotalofrecords) && ($limit > $nbtotalofrecords || empty($limit))) {
	$num = $nbtotalofrecords;
} else {
	if ($limit) {
		$sql .= $db->plimit($limit + 1, $offset);
	}

	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
		exit;
	}

	$num = $db->num_rows($resql);
}


// Output page
// --------------------------------------------------------------------


$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
	$param .= '&amp;contextpage=' . urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&amp;limit=' . urlencode($limit);
}
if ($sall != '') {
	$param .= '&amp;sall=' . urlencode($sall);
}
if ($search_user != '') {
	$param .= "&amp;search_user=" . urlencode($search_user);
}
if ($search_login != '') {
	$param .= "&amp;search_login=" . urlencode($search_login);
}
if ($search_lastname != '') {
	$param .= "&amp;search_lastname=" . urlencode($search_lastname);
}
if ($search_firstname != '') {
	$param .= "&amp;search_firstname=" . urlencode($search_firstname);
}
if ($search_gender != '') {
	$param .= "&amp;search_gender=" . urlencode($search_gender);
}
if ($search_accountancy_code != '') {
	$param .= "&amp;search_accountancy_code=" . urlencode($search_accountancy_code);
}
if ($search_phonepro != '') {
	$param .= "&amp;search_phonepro=" . urlencode($search_phonepro);
}
if ($search_phonemobile != '') {
	$param .= "&amp;search_phonemobile=" . urlencode($search_phonemobile);
}
if ($search_email != '') {
	$param .= "&amp;search_email=" . urlencode($search_email);
}
if ($search_api_key != '') {
	$param .= "&amp;search_api_key=" . urlencode($search_api_key);
}
if ($search_supervisor > 0) {
	$param .= "&amp;search_supervisor=" . urlencode($search_supervisor);
}
if ($search_statut != '') {
	$param .= "&amp;search_statut=" . urlencode($search_statut);
}
if ($search_categ > 0) {
	$param .= '&amp;search_categ=' . urlencode($search_categ);
}
if ($search_warehouse > 0) {
	$param .= '&amp;search_warehouse=' . urlencode($search_warehouse);
}
// Add $param from extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_param.tpl.php';

// List of mass actions available
$arrayofmassactions = array();
$arrayofmassactions['disable'] = img_picto('', 'close_title', 'class="pictofixedwidth"') . $langs->trans("DisableUser");

$massactionbutton = $form->selectMassAction('', $arrayofmassactions);







print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">' . "\n";
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<input type="hidden" name="contextpage" value="' . $contextpage . '">';
print '<input type="hidden" name="fromYear" value="' . $fromYear . '">';
print '<input type="hidden" name="fromMonth" value="' . $fromMonth . '">';
print '<input type="hidden" name="toYear" value="' . $year . '">';
print '<input type="hidden" name="toMonth" value="' . $month . '">';
print '<input type="hidden" name="action" value="view">';


$moreparam = array('morecss' => 'marginleftonly');
$morehtmlright .= dolGetButtonTitle($langs->trans("HierarchicView"), '', 'fa fa-sitemap paddingleft', DOL_URL_ROOT . '/RH/Users/hierarchy.php' . (($search_statut != '' && $search_statut >= 0) ? '?search_statut=' . $search_statut : ''), '', 1, $moreparam);

print_barre_liste($text, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'setup', 0, $morehtmlright . ' ' . $newcardbutton, '', $limit, 0, 0, 1);

// Add code for pre mass action (confirmation or email presend form)
$topicmail = "SendUserRef";
$modelmail = "user";
$objecttmp = new User($db);
$trackid = 'use' . $object->id;
include DOL_DOCUMENT_ROOT . '/core/tpl/massactions_pre.tpl.php';

if (!empty($catid)) {
	print "<div id='ways'>";
	$c = new Categorie($db);
	$ways = $c->print_all_ways(' &gt; ', 'RH/Users/list.php');
	print " &gt; " . $ways[0] . "<br>\n";
	print "</div><br>";
}

if ($search_all) {
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
	}
	print '<div class="divsearchfieldfilter">' . $langs->trans("FilterOnInto", $search_all) . join(', ', $fieldstosearchall) . '</div>';
}

$moreforfilter = '';

// Filter on categories
if (!empty($conf->categorie->enabled) && $user->rights->categorie->lire) {
	$moreforfilter .= '<div class="divsearchfield">';
	$tmptitle = $langs->trans('Category');
	$moreforfilter .= img_picto($langs->trans("Category"), 'category', 'class="pictofixedwidth"') . $formother->select_categories(Categorie::TYPE_USER, $search_categ, 'search_categ', 1, $tmptitle);
	$moreforfilter .= '</div>';
}
// Filter on warehouse
if (!empty($conf->stock->enabled) && !empty($conf->global->MAIN_DEFAULT_WAREHOUSE_USER)) {
	require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
	$formproduct = new FormProduct($db);
	$moreforfilter .= '<div class="divsearchfield">';
	$tmptitle = $langs->trans('Warehouse');
	$moreforfilter .= img_picto($tmptitle, 'stock', 'class="pictofixedwidth"') . $formproduct->selectWarehouses($search_warehouse, 'search_warehouse', '', $tmptitle, 0, 0, $tmptitle);
	$moreforfilter .= '</div>';
}

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object); // Note that $action and $object may have been modified by hook
if (empty($reshook)) {
	$moreforfilter .= $hookmanager->resPrint;
} else {
	$moreforfilter = $hookmanager->resPrint;
}

if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">';
	print $moreforfilter;
	print '</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
print '<table class="tagtable nobottomiftotal liste' . ($moreforfilter ? " listwithfilterbefore" : "") . '">' . "\n";

// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre_filter">';

// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';

if (!empty($arrayfields['u.login']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_login" class="maxwidth50" value="' . $search_login . '"></td>';
}
if (!empty($arrayfields['u.lastname']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_lastname" class="maxwidth50" value="' . $search_lastname . '"></td>';
}
if (!empty($arrayfields['u.firstname']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_firstname" class="maxwidth50" value="' . $search_firstname . '"></td>';
}
if (!empty($arrayfields['u.gender']['checked'])) {
	print '<td class="liste_titre">';
	$arraygender = array('man' => $langs->trans("Genderman"), 'woman' => $langs->trans("Genderwoman"), 'other' => $langs->trans("Genderother"));
	print $form->selectarray('search_gender', $arraygender, $search_gender, 1);
	print '</td>';
}
// Supervisor
if (!empty($arrayfields['u.fk_user']['checked'])) {
	print '<td class="liste_titre">';
	print $form->select_dolusers($search_supervisor, 'search_supervisor', 1, array(), 0, '', 0, 0, 0, 0, '', 0, '', 'maxwidth200');
	print '</td>';
}
if (!empty($arrayfields['u.accountancy_code']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_accountancy_code" class="maxwidth50" value="' . $search_accountancy_code . '"></td>';
}
if (!empty($arrayfields['u.office_phone']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_phonepro" class="maxwidth50" value="' . $search_phonepro . '"></td>';
}
if (!empty($arrayfields['u.user_mobile']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_phonemobile" class="maxwidth50" value="' . $search_phonemobile . '"></td>';
}
if (!empty($arrayfields['u.email']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_email" class="maxwidth75" value="' . $search_email . '"></td>';
}
if (!empty($arrayfields['u.api_key']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_api_key" class="maxwidth50" value="' . $search_api_key . '"></td>';
}
if (!empty($arrayfields['u.fk_soc']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_thirdparty" class="maxwidth75" value="' . $search_thirdparty . '"></td>';
}
if (!empty($arrayfields['u.entity']['checked'])) {
	print '<td class="liste_titre"></td>';
}
if (!empty($arrayfields['u.salary']['checked'])) {
	print '<td class="liste_titre"></td>';
}
if (!empty($arrayfields['u.datelastlogin']['checked'])) {
	print '<td class="liste_titre"></td>';
}
if (!empty($arrayfields['u.datepreviouslogin']['checked'])) {
	print '<td class="liste_titre"></td>';
}
// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_input.tpl.php';
// Fields from hook
$parameters = array('arrayfields' => $arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
if (!empty($arrayfields['u.datec']['checked'])) {
	// Date creation
	print '<td class="liste_titre">';
	print '</td>';
}
if (!empty($arrayfields['u.tms']['checked'])) {
	// Date modification
	print '<td class="liste_titre">';
	print '</td>';
}
if (!empty($arrayfields['u.statut']['checked'])) {
	// Status
	print '<td class="liste_titre center">';
	print $form->selectarray('search_statut', array('-1' => '', '0' => $langs->trans('Disabled'), '1' => $langs->trans('Enabled')), $search_statut, 0, 0, 0, '', 0, 0, 0, '', 'minwidth75imp');
	print '</td>';
}
print '</tr>';




print '<tr class="liste_titre">';
// Action column
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ') . "\n";

if (!empty($arrayfields['u.login']['checked'])) {
	print_liste_field_titre("Login", $_SERVER['PHP_SELF'], "u.login", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.lastname']['checked'])) {
	print_liste_field_titre("Lastname", $_SERVER['PHP_SELF'], "u.lastname", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.firstname']['checked'])) {
	print_liste_field_titre("FirstName", $_SERVER['PHP_SELF'], "u.firstname", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.gender']['checked'])) {
	print_liste_field_titre("Gender", $_SERVER['PHP_SELF'], "u.gender", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.employee']['checked'])) {
	print_liste_field_titre("Employee", $_SERVER['PHP_SELF'], "u.employee", $param, "", "", $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['u.fk_user']['checked'])) {
	print_liste_field_titre("HierarchicalResponsible", $_SERVER['PHP_SELF'], "u.fk_user", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.accountancy_code']['checked'])) {
	print_liste_field_titre("AccountancyCode", $_SERVER['PHP_SELF'], "u.accountancy_code", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.office_phone']['checked'])) {
	print_liste_field_titre("PhonePro", $_SERVER['PHP_SELF'], "u.office_phone", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.user_mobile']['checked'])) {
	print_liste_field_titre("PhoneMobile", $_SERVER['PHP_SELF'], "u.user_mobile", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.email']['checked'])) {
	print_liste_field_titre("EMail", $_SERVER['PHP_SELF'], "u.email", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.api_key']['checked'])) {
	print_liste_field_titre("ApiKey", $_SERVER['PHP_SELF'], "u.api_key", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.fk_soc']['checked'])) {
	print_liste_field_titre("Company", $_SERVER['PHP_SELF'], "u.fk_soc", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.entity']['checked'])) {
	print_liste_field_titre("Entity", $_SERVER['PHP_SELF'], "u.entity", $param, "", "", $sortfield, $sortorder);
}
if (!empty($arrayfields['u.salary']['checked'])) {
	print_liste_field_titre("Salary", $_SERVER['PHP_SELF'], "u.salary", $param, "", "", $sortfield, $sortorder, 'right ');
}
if (!empty($arrayfields['u.datelastlogin']['checked'])) {
	print_liste_field_titre("LastConnexion", $_SERVER['PHP_SELF'], "u.datelastlogin", $param, "", '', $sortfield, $sortorder, 'center ');
}
if (!empty($arrayfields['u.datepreviouslogin']['checked'])) {
	print_liste_field_titre("PreviousConnexion", $_SERVER['PHP_SELF'], "u.datepreviouslogin", $param, "", '', $sortfield, $sortorder, 'center ');
}
// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_title.tpl.php';
// Hook fields
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;
if (!empty($arrayfields['u.datec']['checked'])) {
	print_liste_field_titre("DateCreationShort", $_SERVER["PHP_SELF"], "u.datec", "", $param, '', $sortfield, $sortorder, 'center nowrap ');
}
if (!empty($arrayfields['u.tms']['checked'])) {
	print_liste_field_titre("DateModificationShort", $_SERVER["PHP_SELF"], "u.tms", "", $param, '', $sortfield, $sortorder, 'center nowrap ');
}
if (!empty($arrayfields['u.statut']['checked'])) {
	print_liste_field_titre("Status", $_SERVER["PHP_SELF"], "u.statut", "", $param, '', $sortfield, $sortorder, 'center ');
}
print '</tr>' . "\n";


// Detect if we need a fetch on each output line
$needToFetchEachLine = 0;
if (key_exists('computed', $extrafields->attributes[$object->table_element]) && is_array($extrafields->attributes[$object->table_element]['computed']) && count($extrafields->attributes[$object->table_element]['computed']) > 0) {
	foreach ($extrafields->attributes[$object->table_element]['computed'] as $key => $val) {
		if (preg_match('/\$object/', $val)) {
			$needToFetchEachLine++; // There is at least one compute field that use $object
		}
	}
}


// Loop on record
// --------------------------------------------------------------------
$i = 0;
$totalarray = array();
$totalarray['nbfield'] = 0;
$arrayofselected = is_array($toselect) ? $toselect : array();
while ($i < ($limit ? min($num, $limit) : $num)) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	if (empty($obj->country_code)) $obj->country_code = '';		// TODO Add join in select with country table to get country_code

	// Store properties in $object
	$object->setVarsFromFetchObj($obj);

	$userstatic->id = $obj->rowid;
	$userstatic->admin = $obj->admin;
	$userstatic->ref = $obj->rowid;
	$userstatic->login = $obj->login;
	$userstatic->statut = $obj->statut;
	$userstatic->office_phone = $obj->office_phone;
	$userstatic->user_mobile = $obj->user_mobile;
	$userstatic->email = $obj->email;
	$userstatic->gender = $obj->gender;
	$userstatic->socid = $obj->fk_soc;
	$userstatic->firstname = $obj->firstname;
	$userstatic->lastname = $obj->lastname;
	$userstatic->employee = $obj->employee;
	$userstatic->photo = $obj->photo;

	$li = $userstatic->getNomUrl(-1, '', 0, 0, 24, 1, 'login', '', 1);

	$canreadhrmdata = 0;
	if ((!empty($conf->salaries->enabled) && !empty($user->rights->salaries->read) && in_array($obj->rowid, $childids))
		|| (!empty($conf->salaries->enabled) && !empty($user->rights->salaries->readall))
		|| (!empty($conf->hrm->enabled) && !empty($user->rights->hrm->employee->read))
	) {
		$canreadhrmdata = 1;
	}
	$canreadsecretapi = 0;
	if ($user->id == $obj->rowid || !empty($user->admin)) {	// Current user or admin
		$canreadsecretapi = 1;
	}

	print '<tr class="oddeven">';

	// Action column
	print '<td class="nowrap center">';
	$selected = 0;
	if (in_array($object->id, $arrayofselected)) {
		$selected = 1;
	}
	print '<input id="cb' . $object->id . '" class="flat checkforselect" type="checkbox" name="toselect[]" value="' . $object->id . '"' . ($selected ? ' checked="checked"' : '') . '>';
	print '</td>';



	// Login
	if (!empty($arrayfields['u.login']['checked'])) {
		print '<td class="nowraponall tdoverflowmax150"><a href="/RH/Users/card.php?id=' . $obj->rowid . '">';

		if ($obj->gender == "man") {
			print '<span class="nopadding userimg" style="margin-right: 3px;"><img class="photouserphoto userphoto" alt="" src="/public/theme/common/user_man.png"></span>';
		} else {
			print '<span class="nopadding userimg" style="margin-right: 3px;"><img class="photouserphoto userphoto" alt="" src="/public/theme/common/user_woman.png"></span>';
		}
		print $obj->login;
		if (!empty($conf->multicompany->enabled) && $obj->admin && !$obj->entity) {
			print img_picto($langs->trans("SuperAdministrator"), 'redstar', 'class="valignmiddle paddingleft"');
		} elseif ($obj->admin) {
			print img_picto($langs->trans("Administrator"), 'star', 'class="valignmiddle paddingleft"');
		}
		print '</a></td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.lastname']['checked'])) {
		print '<td class="tdoverflowmax150" title="' . dol_escape_htmltag($obj->lastname) . '">' . dol_escape_htmltag($obj->lastname) . '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.firstname']['checked'])) {
		print '<td class="tdoverflowmax150" title="' . dol_escape_htmltag($obj->lastname) . '">' . dol_escape_htmltag($obj->firstname) . '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.gender']['checked'])) {
		print '<td>';
		if ($obj->gender) {
			print $langs->trans("Gender" . $obj->gender);
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	// Employee yes/no
	if (!empty($arrayfields['u.employee']['checked'])) {
		print '<td class="center">' . yn($obj->employee) . '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	// Supervisor
	if (!empty($arrayfields['u.fk_user']['checked'])) {
		// Resp
		print '<td class="nowrap">';
		if ($obj->login2) {
			$user2->id = $obj->id2;
			$user2->login = $obj->login2;
			$user2->lastname = $obj->lastname2;
			$user2->firstname = $obj->firstname2;
			$user2->gender = $obj->gender2;
			$user2->photo = $obj->photo2;
			$user2->admin = $obj->admin2;
			$user2->office_phone = $obj->office_phone;
			$user2->user_mobile = $obj->user_mobile;
			$user2->email = $obj->email2;
			$user2->socid = $obj->fk_soc2;
			$user2->statut = $obj->statut2;
			print $user2->getNomUrlRh(-1, '', 0, 0, 24, 0, '', '', 1);
			if (!empty($conf->multicompany->enabled) && $obj->admin2 && !$obj->entity2) {
				print img_picto($langs->trans("SuperAdministrator"), 'redstar', 'class="valignmiddle paddingleft"');
			} elseif ($obj->admin2) {
				print img_picto($langs->trans("Administrator"), 'star', 'class="valignmiddle paddingleft"');
			}
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	if (!empty($arrayfields['u.accountancy_code']['checked'])) {
		print '<td>' . $obj->accountancy_code . '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	if (!empty($arrayfields['u.office_phone']['checked'])) {
		print '<td>' . dol_print_phone($obj->office_phone, $obj->country_code, 0, $obj->rowid, 'AC_TEL', ' ', 'phone') . "</td>\n";
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.user_mobile']['checked'])) {
		print '<td>' . dol_print_phone($obj->user_mobile, $obj->country_code, 0, $obj->rowid, 'AC_TEL', ' ', 'mobile') . "</td>\n";
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.email']['checked'])) {
		print '<td class="tdoverflowmax150">' . dol_print_email($obj->email, $obj->rowid, $obj->fk_soc, 'AC_EMAIL', 0, 0, 1) . "</td>\n";
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.api_key']['checked'])) {
		print '<td>';
		if ($obj->api_key) {
			if ($canreadsecretapi) {
				print $obj->api_key;
			} else {
				print '<span class="opacitymedium">' . $langs->trans("Hidden") . '</span>';
			}
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.fk_soc']['checked'])) {
		print '<td class="tdoverflowmax200">';
		if ($obj->fk_soc > 0) {
			$companystatic->id = $obj->fk_soc;
			$companystatic->name = $obj->name;
			$companystatic->canvas = $obj->canvas;
			print $companystatic->getNomUrl(1);
		} elseif ($obj->ldap_sid) {
			print '<span class="opacitymedium">' . $langs->trans("DomainUser") . '</span>';
		} else {
			print '<span class="opacitymedium">' . $langs->trans("InternalUser") . '</span>';
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	// Multicompany enabled
	if (!empty($conf->multicompany->enabled) && is_object($mc) && empty($conf->global->MULTICOMPANY_TRANSVERSE_MODE)) {
		if (!empty($arrayfields['u.entity']['checked'])) {
			print '<td>';
			if (!$obj->entity) {
				print $langs->trans("AllEntities");
			} else {
				$mc->getInfo($obj->entity);
				print $mc->label;
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
	}

	// Salary
	if (!empty($arrayfields['u.salary']['checked'])) {
		print '<td class="nowraponall right amount">';
		if ($obj->salary) {
			if ($canreadhrmdata) {
				print price($obj->salary);
			} else {
				print '<span class="opacitymedium">' . $langs->trans("Hidden") . '</span>';
			}
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	// Date last login
	if (!empty($arrayfields['u.datelastlogin']['checked'])) {
		print '<td class="nowrap center">' . dol_print_date($db->jdate($obj->datelastlogin), "dayhour") . '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	// Date previous login
	if (!empty($arrayfields['u.datepreviouslogin']['checked'])) {
		print '<td class="nowrap center">' . dol_print_date($db->jdate($obj->datepreviouslogin), "dayhour") . '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	// Extra fields
	include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_print_fields.tpl.php';
	// Fields from hook
	$parameters = array('arrayfields' => $arrayfields, 'object' => $object, 'obj' => $obj, 'i' => $i, 'totalarray' => &$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	// Date creation
	if (!empty($arrayfields['u.datec']['checked'])) {
		print '<td class="center">';
		print dol_print_date($db->jdate($obj->date_creation), 'dayhour', 'tzuser');
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	// Date modification
	if (!empty($arrayfields['u.tms']['checked'])) {
		print '<td class="center">';
		print dol_print_date($db->jdate($obj->date_update), 'dayhour', 'tzuser');
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	// Status
	if (!empty($arrayfields['u.statut']['checked'])) {
		$userstatic->statut = $obj->statut;
		print '<td class="center">' . $userstatic->getLibStatut(5) . '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!$i) {
		$totalarray['nbfield']++;
	}

	print '</tr>' . "\n";

	$i++;
}

// Show total line
include DOL_DOCUMENT_ROOT . '/core/tpl/list_print_total.tpl.php';

// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="' . $colspan . '" class="opacitymedium">' . $langs->trans("NoRecordFound") . '</td></tr>';
}


$db->free($resql);

$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>';
print '</div>';


print "
	<div style='display: grid;grid-template-columns: auto 120px;align-items: flex-end;' id='formRubrique'>
		<div>
			<h3>selectionner les rubriques</h3>
			<label class='ckeckboxContainer' style='font-weight: bold;font-size: 17px;'>
				<input type='checkbox' id='selectAll'>Tous selectionner
				<span class='checkmark'></span>
			</label><br>";

foreach ($rubsName as $key => $value) {
	echo "
		<label class='ckeckboxContainer'>" . ucfirst(mb_strtolower($value)) . "
			<input type='checkbox' name='rubsWithName[]' value='$key' " . ((in_array($key, $rubsWithName)) ? ' checked' : '') . ">
			<span class='checkmark'></span>
		</label><br>";
}
foreach ($rubsId as $key => $value) {
	echo "
		<label class='ckeckboxContainer'>" . ucfirst(mb_strtolower($value)) . "
			<input type='checkbox' name='rubsWithId[]' value='$key' " . ((in_array($key, $rubsWithId)) ? ' checked' : '') . ">
			<span class='checkmark'></span>
		</label><br>";
}

// salaire net
print
	"
	<label class='ckeckboxContainer'>
		<input type='checkbox' name='rubsWithId[]' value='salaireNet'>Salaire net
		<span class='checkmark'></span>
	</label><br>
	";

// salaire brute
print
	"
	<label class='ckeckboxContainer'>
		<input type='checkbox' name='rubsWithId[]' value='salaireBrut'>Salaire brut
		<span class='checkmark'></span>
	</label><br>
	";

print '
		</div>
		<input type="submit" name="download" style="" class="button small hideobject massaction massactionconfirmed" value="Confirmer">
	</div>
</form>';


print '
	<style>
	/* Customize the label (the container) */
	.ckeckboxContainer {
		display: inline-block;
		position: relative;
		padding-left: 23px;
		margin-bottom: 5px;
		cursor: pointer;
		font-size: 15px;
		-webkit-user-select: none;
		-moz-user-select: none;
		-ms-user-select: none;
		user-select: none;
	}
	
	/* Hide the browsers default checkbox */
	.ckeckboxContainer input {
	  position: absolute;
	  opacity: 0;
	  cursor: pointer;
	  height: 0;
	  width: 0;
	}
	
	/* Create a custom checkbox */
	.checkmark {
	  position: absolute;
	  top: 50%;
	  left: 0;
	  transform:translateY(-50%);
	  height: 15px;
	  width: 15px;
	  background-color: #dbdbdb;
	}
	
	/* On mouse-over, add a grey background color */
	.ckeckboxContainer:hover input ~ .checkmark {
	  background-color: #ccc;
	}
	
	/* When the checkbox is checked, add a blue background */
	.ckeckboxContainer input:checked ~ .checkmark {
	  background-color: #2196F3;
	}
	
	/* Create the checkmark/indicator (hidden when not checked) */
	.checkmark:after {
	  content: "";
	  position: absolute;
	  display: none;
	}
	
	/* Show the checkmark when checked */
	.ckeckboxContainer input:checked ~ .checkmark:after {
	  display: block;
	}
	
	/* Style the checkmark/indicator */
	.ckeckboxContainer .checkmark:after {
		left: 4px;
		top: 0px;
		width: 3px;
		height: 9px;
		border: solid white;
		border-width: 0 2.5px 2.5px 0;
		-webkit-transform: rotate(45deg);
		-ms-transform: rotate(45deg);
		transform: rotate(45deg);
	}
	</style>
';






//filter by date
function datefilter()
{
	print '<div class="center">';
	print '<form id="frmfilter" name="filter" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
	print '<input type="hidden" name="action" value="filter">';

	// Show navigation bar
	$nav = '<div class="date-container">
				<div class="inp-wrapper">
					<div class="date-wrapper">
						<label for="date-1">De</label>
						<input type="month" id="date-1"/ name="from">
					</div>
					<div class="date-wrapper">
						<label for="date-2">à</label>
						<input type="month" id="date-2" name="to">
					</div>
					<button style="cursor:pointer;" type="submit" name="button_search_x" value="x" class="bordertransp"><span class="fa fa-search"></span></button>
				</div>
			</div>';



	print $nav;

	print '</form>';
	print '</div>';
}

print "
    <script>
        $(document).ready(function(){
            $('#date-1').val('" . $fromYear . "-" . $fromMonth . "');	
            $('#date-2').val('" . $year . "-" . $month . "');	
            $( '#selectAll' ).click(function() {
				if ($(this).is(':checked')){
					$('#formRubrique input:not(#selectAll)').each(function(){
						$(this).prop('checked', true);
					});
				}else{
					$('#formRubrique input:not(#selectAll)').each(function(){
						$(this).prop('checked', false);
					});
				}
			});
        });
    </script>";

$db->free($result);

// End of page
llxFooter();
$db->close();
