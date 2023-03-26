<?php

ini_set('max_execution_time', 120);

// Load Dolibarr environment
require '../../main.inc.php';
require_once '../../vendor/autoload.php';

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/holiday/class/holiday.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';



if (!$user->rights->salaries->read) {
	accessforbidden("you don't have right for this page");
}




$action = GETPOST('action', 'aZ09'); // The action 'add', 'create', 'edit', 'update', 'view', ...
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'userlist'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page


// Security check (for external users)
$socid = 0;
if ($user->socid > 0) {
	$socid = $user->socid;
}

$currentUser = $user;

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
	$sortfield = "ef.matricule";
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
		if ($key != 'matricule') {
			if (!empty($extrafields->attributes[$object->table_element]['list'][$key]))
				$arrayfields["ef." . $key] = array('label' => $extrafields->attributes[$object->table_element]['label'][$key], 'checked' => (($extrafields->attributes[$object->table_element]['list'][$key] < 0) ? 0 : 1), 'position' => $extrafields->attributes[$object->table_element]['pos'][$key], 'enabled' => (abs($extrafields->attributes[$object->table_element]['list'][$key]) != 3 && $extrafields->attributes[$object->table_element]['perms'][$key]));
		}
	}
}

$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');

// Init search fields
$sall = trim((GETPOST('search_all', 'alphanohtml') != '') ? GETPOST('search_all', 'alphanohtml') : GETPOST('sall', 'alphanohtml'));
$search_user = GETPOST('search_user', 'alpha');
$search_login = GETPOST('search_login', 'alpha');
$search_options_matricule = GETPOST('search_options_matricule', 'alpha');
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
		$search_options_matricule = '0';
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
	$search_options_matricule = '0';
}



/*
 * Actions
 */



//get date filter
$mydate = getdate(date("U"));
$month = (GETPOST('month') != '') ? GETPOST('month') : $mydate['mon'];
$year = (GETPOST('year') != '') ? GETPOST('year') : $mydate['year'];
$day = $mydate['mday'];
if (strlen($month) == 1) {
	$month = '0' . $month;
}

if ($action == 'filter') {
	$dateFiltre = GETPOST('date');
	$year = explode('-', $dateFiltre)[0];
	$month = explode('-', $dateFiltre)[1];
}

$startdayarray = dol_get_prev_month($month, $year);

$prev = $startdayarray;
$prev_year  = $prev['year'];
$prev_month = $prev['month'];
$prev_day   = 1;

//Save date to use in generate module
$_SESSION['dateg'] = $month . "-" . $year;

$next = dol_get_next_month($month, $year);
$next_year  = $next['year'];
$next_month = $next['month'];
$next_day   = 1;







llxHeader("", "Consulter Bulletin de Paie");

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


// Actions to build doc
$action = GETPOST('action', 'aZ09');
$_SESSION['cloture']=0;

$upload_dir = DOL_DATA_ROOT . '/grh/BulletinDePaie';
$permissiontoadd = 1;
$donotredirect = 1;
include DOL_DOCUMENT_ROOT . '/core/actions_builddoc.inc.php';


$_SESSION['dateg'] = null;


/*
 * View
 */


$formother = new FormOther($db);

$help_url = 'EN:Module_Users|FR:Module_Utilisateurs|ES:M&oacute;dulo_Usuarios|DE:Modul_Benutzer';

$text = "Consulter Bulletin de Paie";
$french_months = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');


$user2 = new User($db);

$sql = "SELECT DISTINCT u.rowid, u.lastname, u.firstname, u.admin, u.fk_soc, u.login, u.office_phone, u.user_mobile, u.email, u.api_key, u.accountancy_code, u.gender, u.employee, u.photo,u.dateemploymentend, u.dateemployment,";
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
	$sql .= " WHERE u.entity IN (" . getEntity('user') . ")";
}
$sql .= " AND u.employee = 1";
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
if ($search_options_matricule != '') {
	$sql .= natural_search("ef.matricule", $search_options_matricule);
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

// Loop on record
// --------------------------------------------------------------------
$i = 0;
$totalarray = array();
$totalarray['nbfield'] = 0;
$users = array();

$arrayofselected = is_array($toselect) ? $toselect : array();
while ($i < ($limit ? min($num, $limit) : $num)) {
	$obj = $db->fetch_object($resql);
	if (empty($obj)) {
		break; // Should not happen
	}

	if (empty($obj->country_code)) $obj->country_code = '';		// TODO Add join in select with country table to get country_code

	// Store properties in $object
	$object->setVarsFromFetchObj($obj);

	$sql1 = "SELECT s.fk_user FROM llx_payment_salary as s WHERE s.fk_user=" . $obj->rowid . " AND year(datep)=" . $year . " AND month(datep)=" . $month;
	$res1 = $db->query($sql1);
	if ((strtotime($obj->dateemploymentend) < strtotime('23' . '-' . $prev_month . '-' . $prev_year) && $obj->dateemploymentend != '') || $obj->dateemployment == '' || (strtotime($obj->dateemployment) > strtotime('22-' . $month . '-' . $year) && $obj->dateemployment != '') || $obj->statut == 0) {
		$i++;
		continue;
	}

	// see if it's clotured
	$cloture = 0;
	$sql1 = "SELECT cloture FROM llx_Paie_MonthDeclaration WHERE userid=$obj->rowid AND year=$year AND month=$month";
	$res1 = $db->query($sql1);
	if ($res1) {
		$row1 = $res1->fetch_assoc();
		$cloture = $row1["cloture"] > 0 ? $row1["cloture"] : 0;
	}
	if ($cloture > 0) {
		$i++;
		continue;
	}

	$users[$obj->rowid] = $obj;
	$i++;
}

// Output page
// --------------------------------------------------------------------
if ($action != 'changeWorkingDays') {

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



	print "<div><h3>Le mois: " . $french_months[$month - 1] . " </h3></div>";
	print '<form method="POST" id="searchFormList" action="' . $_SERVER["PHP_SELF"] . '">' . "\n";
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
	print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';
	print '<input type="hidden" name="contextpage" value="' . $contextpage . '">';
	print '<input type="hidden" name="year" value="' . $year . '">';
	print '<input type="hidden" name="month" value="' . $month . '">';
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
	print '<table id="tblUsers" class="tagtable liste' . ($moreforfilter ? " listwithfilterbefore" : "") . '">' . "\n";

	// Fields title search
	// --------------------------------------------------------------------
	print '<tr class="liste_titre_filter">';

	// Action column
	print '<td class="liste_titre maxwidthsearch">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';
	print '<td class="liste_titre"><input type="text" name="search_options_matricule" class="maxwidth50" value="' . $search_options_matricule . '"></td>';

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
	print_liste_field_titre("Matricule", $_SERVER['PHP_SELF'], "ef.matricule", $param, "", "", $sortfield, $sortorder);


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

	foreach ($users as $obj) {

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


		$li = '<a href="' . $_SERVER["PHP_SELF"] . '?id=' . $userstatic->id . '&action=show&year=' . $year . '&month=' . $month . '">' . $userstatic->login . '</a>';


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

		// matricule
		print '<td class="tdoverflowmax150" title="' . dol_escape_htmltag($obj->options_matricule) . '">' . dol_escape_htmltag($obj->options_matricule) . '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
		// Login
		if (!empty($arrayfields['u.login']['checked'])) {
			print '<td class="nowraponall tdoverflowmax150"><a href="/RH/Users/card.php?id=' . $obj->rowid . '">';

			if ($obj->gender == "man") {
				print '<span class="nopadding userimg" style="margin-right: 3px;"><img class="photouserphoto userphoto" alt="" src="/public/theme/common/user_man.png"></span>';
			} else {
				print '<span class="nopadding userimg" style="margin-right: 3px;"><img class="photouserphoto userphoto" alt="" src="/public/theme/common/user_woman.png"></span>';
			}
			print $li;
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

		$sql = "SELECT fk_user, bank, number FROM llx_user_rib WHERE fk_user=" . $obj->rowid;
		$res = $db->query($sql);
		$data = ((object)($res))->fetch_assoc();

		print '<td> <input type="hidden" name="idCell" value="' . $obj->rowid . '"></td>';
		print '<td> <input type="hidden" name="salaryCell" value="' . $obj->salary . '"></td>';
		$bank = $data["bank"];
		$bank .= "  / RIB: ";
		$bank .= $data["number"];
		print '<td> <input type="hidden" name="bankCell" value="' . $bank . '"></td>';

		if (!$i) {
			$totalarray['nbfield']++;
		}

		print '</tr>' . "\n";
	}
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


print '</table>
	</div>
</form>';


if ($action == 'changeWorkingDays') {
	changeWorkingDays();
} else {
	GenerateDocuments();
	traitment();
	echo '<form action="" method="get" style="text-align: left; width:200px;">
	<input type="hidden" name="action" value="showallbultins">
	<input type="hidden" name="month" value="' . $month . '">
	<input type="hidden" name="year" value="' . $year . '">
	<button type="submit" style="margin-bottom: 100px; margin-right: 5%;" class="butActionDelete">Afficher tous</button>
	</form>';
}

if ($action == 'confirmeWorkingDays') {
	$action = '';

	$holiday = new Holiday($db);
	$typeleaves = $holiday->getTypes(1, 1);
	$val = array_shift(array_values($typeleaves));;

	foreach ($users as $user) {
		$workingdays = (float)GETPOST("workingdays_$user->rowid", "float");
		$workingHours = (int)GETPOST("workingHours_$user->rowid", "float");
		$joursferie = (int)GETPOST("joursferie_$user->rowid", "float");
		$daysConge = (float)GETPOST("daysConge_$user->rowid", "float");
		//change the working days
		$sqlu = "SELECT joursconge FROM llx_Paie_MonthDeclaration WHERE userid=$user->rowid AND month=$month AND year = $year";
		$resu = $db->query($sqlu);
		if (((object)$resu)->num_rows > 0) {
			$rowu = ((object)$resu)->fetch_assoc();
			$currentConge = (float)$rowu["joursconge"];
		}

		$sql = "select rowid FROM llx_holiday WHERE ((MONTH(date_debut) = $month and YEAR(date_debut) = $year) or (MONTH(date_fin) = $month and YEAR(date_fin) = $year)) and fk_user=$user->rowid and statut=3";
		$res = $db->query($sql);
		$demandeConge = 0;
		if (((object)$res)->num_rows > 0) {
			while ($row = $res->fetch_assoc()) {

				$holiday->fetch($row['rowid']);
				if (date('m', $holiday->date_fin) == $month && date('m', $holiday->date_debut) == $month) {
					$demandeConge +=  num_open_day($holiday->date_debut, $holiday->date_fin, 0, 1, $holiday->halfday);
				} else {
					if (date('m', $holiday->date_debut) == $month) {
						$lastDay =  cal_days_in_month(CAL_GREGORIAN, $month, $year);
						$dateFinMonth = $db->jdate("$year-$month-$lastDay");
						$demandeConge += num_open_day($holiday->date_debut, $dateFinMonth, 0, 1, $holiday->halfday);
					} else if (date('m', $holiday->date_fin) == $month) {
						$firstOfMonth =   $db->jdate("$year-$month-01");
						$demandeConge +=  num_open_day($firstOfMonth, $holiday->date_fin, 0, 1, '0');
					}
				}
			}
		}

		if ($daysConge >= $demandeConge && $daysConge != $currentConge) {
			$prev_solde = price2num($holiday->getCPforUser($user->rowid, $val['rowid']), 5);
			$newchange = $daysConge - $currentConge;
			$newSolde = $prev_solde - $newchange;

			// We add the modification to the log (must be done before the update of balance because we read current value of balance inside this method)
			$result = $holiday->addLogCP($currentUser->id, $user->rowid, $langs->transnoentitiesnoconv('ManualUpdate'), $newSolde, $val['rowid']);
			if ($result < 0) {
				setEventMessages($holiday->error, $holiday->errors, 'errors');
				$error++;
			} elseif ($result == 0) {
				setEventMessages($langs->trans("HolidayQtyNotModified", $user->login), null, 'warnings');
			}

			// Update of the days of the employee
			if ($result > 0) {
				$nbok++;

				$result = $holiday->updateSoldeCP($user->rowid, $newSolde, $val['rowid']);
				if ($result < 0) {
					setEventMessages($holiday->error, $holiday->errors, 'errors');
					$error++;
				}
			}
		} else if ($daysConge < $demandeConge && $demandeConge != $currentConge) {
			$daysConge = $demandeConge;

			$prev_solde = price2num($holiday->getCPforUser($user->rowid, $val['rowid']), 5);
			$newchange = $daysConge - $currentConge;
			$newSolde = $prev_solde - $newchange;

			// We add the modification to the log (must be done before the update of balance because we read current value of balance inside this method)
			$result = $holiday->addLogCP($currentUser->id, $user->rowid, $langs->transnoentitiesnoconv('ManualUpdate'), $newSolde, $val['rowid']);
			if ($result < 0) {
				setEventMessages($holiday->error, $holiday->errors, 'errors');
				$error++;
			} elseif ($result == 0) {
				setEventMessages($langs->trans("HolidayQtyNotModified", $user->login), null, 'warnings');
			}

			// Update of the days of the employee
			if ($result > 0) {
				$nbok++;

				$result = $holiday->updateSoldeCP($user->rowid, $newSolde, $val['rowid']);
				if ($result < 0) {
					setEventMessages($holiday->error, $holiday->errors, 'errors');
					$error++;
				}
			}
		} else {
			$daysConge = $currentConge;
		}
		// see if it's clotured
		$sql1 = "SELECT cloture, avance FROM llx_Paie_MonthDeclaration WHERE userid=$user->rowid AND year=$year AND month=$month";
		$res1 = $db->query($sql1);
		if ($res1) {
			$row1 = $res1->fetch_assoc();
			$avance = $row1["avance"] > 0 ? $row1["avance"] : 0;
		}
		$sql = "REPLACE INTO llx_Paie_MonthDeclaration(userid, year, month, workingDays, workingHours, joursferie, avance, joursconge) VALUES($user->rowid, $year, $month, $workingdays, $workingHours, $joursferie, $avance, $daysConge);";
		$res = $db->query($sql);
		if ($res);
		else print("<br>fail ERR: " . $sql);

		$sql = "SELECT rub FROM llx_Paie_HourSupp";
		$res = $db->query($sql);
		if ($res->num_rows > 0) {
			while ($row = $res->fetch_assoc()) {
				$nhours = (int)GETPOST("hoursupp_" . $row["rub"] . "_$user->rowid", "int");

				$sqlh = "REPLACE INTO llx_Paie_HourSuppDeclaration(userid, rub, year, month, nhours) VALUES($user->rowid, " . $row["rub"] . ", $year, $month, $nhours);";
				$resh = $db->query($sqlh);
				if ($resh);
				else {
					print("<br>fail ERR: " . $sqlh);
					exit;
				}
			}
		}
	}
}


$action = GETPOST('action');
$id = GETPOST('id', 'int');

if ($action == 'showallbultins') {
	ShowAllBulletins();
}
if ($id && $action == 'show') {
	ShowBulletin($id);
}
ShowDocuments();
progressBar();

function progressBar()
{
	echo '
	<style>
	.loader {
		border: 16px solid #f3f3f3;
		border-radius: 50%;
		border-top: 16px solid blue;
		border-bottom: 16px solid blue;
		width: 120px;
		height: 120px;
		-webkit-animation: spin 2s linear infinite;
		animation: spin 2s linear infinite;
		position: fixed;
		top: 45%;
        left: 45%;
		display:none;
	}

	@-webkit-keyframes spin {
		0% { -webkit-transform: rotate(0deg); }
		100% { -webkit-transform: rotate(360deg); }
	}

	@keyframes spin {
		0% { transform: rotate(0deg); }
		100% { transform: rotate(360deg); }
	}
	</style>

	<div class="loader" id="loader"></div>';
}

function GenerateDocuments()
{
	global $day, $month, $year, $limit;
	print '<form id="frmgen" name="generateDocs" method="post">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="generateDocs">';
	print '<input type="hidden" name="model" value="BulletinDePaie">';
	print '<input type="hidden" name="day" value="' . $day . '">';
	print '<input type="hidden" name="month" value="' . $month . '">';
	print '<input type="hidden" name="year" value="' . $year . '">';
	print '<input type="hidden" name="limit" value="' . $limit . '">';
	print '<div class=""  style="margin-bottom: 0px; margin-left: 5%;"><input type="button" id="btngen" class="button" name="save" value="Generer"></div>';

	print "<script>
        $('#btngen').click(function(){
			var elem = document.getElementById('bar');
			var i=0;
			var els=$('#tblUsers  input:checkbox.checkforselect:checked');
			var num=els.length;
			if(num>0){
				$('#loader').css('display', 'block');
			}
            els.each(function () {
                var id = $(this).closest('tr').find('input[name=idCell]').val();
                var salary = $(this).closest('tr').find('input[name=salaryCell]').val();
                var b = $(this).closest('tr').find('input[name=bankCell]').val();
                var nom = $(this).closest('tr').find('td:eq(2)').val();
                $('input[name=id]').remove();
                $('<input type=\"hidden\" name=\"id\" value=\"'+id+'\" >').appendTo('#frmgen');
				$.ajax({
					url: '" . $_SERVER["PHP_SELF"] . "',
					type: 'post',
					data:$('#frmgen').serialize(),
					success:function(){			
					}
				});

            });   
        });
		$(document).ajaxStop(function(){
			window.location.reload();
		});
    </script>";

	print '</form>';
}

function ShowDocuments()
{
	global $db, $object, $conf, $month, $year, $societe;
	print '<div class="fichecenter"><div class="fichehalfleft">';
	$formfile = new FormFile($db);


	$subdir = "$month-$year";
	$filedir = DOL_DATA_ROOT . '/grh/BulletinDePaie/' . $subdir;
	$urlsource = $_SERVER['PHP_SELF'] . '';
	$genallowed = 0;
	$delallowed = 1;
	$modelpdf = (!empty($object->modelpdf) ? $object->modelpdf : (empty($conf->global->RH_ADDON_PDF) ? '' : $conf->global->RH_ADDON_PDF));

	$_SESSION["filterDoc"] = $month . "-" . $year;


	print $formfile->showdocuments('BulletinDePaie', $subdir, $filedir, $urlsource, $genallowed, $delallowed, $modelpdf, 1, 0, 0, 40, 0, 'remonth=' . $month . '&amp;reyear=' . $year, '', '', $societe->default_lang);
	$somethingshown = $formfile->numoffiles;

	// $_SESSION["filterDoc"] = null;
	// Show links to link elements
	//$linktoelem = $form->showLinkToObjectBlock($object, null, array('RH'));
}


function traitment()
{
	global $day, $month, $year, $limit;
	print '</form>';
	print '<hr>';
	print '<form action="' . $_SERVER["PHP_SELF"] . '" method="get" style="">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="changeWorkingDays">';
	print '<input type="hidden" name="day" value="' . $day . '">';
	print '<input type="hidden" name="month" value="' . $month . '">';
	print '<input type="hidden" name="year" value="' . $year . '">';
	print '<input type="hidden" name="limit" value="' . $limit . '">';
	print '<div class="right"  style="margin-bottom: 100px; margin-right: 5%;"><input type="submit" class="butActionDelete" value="Traitement">';
	print '</form>';
}


function changeWorkingDays()
{
	global $db, $day, $month, $year, $users, $limit;

	$sql = "SELECT rub, designation FROM llx_Paie_HourSupp";
	$res = $db->query($sql);
	if (((object)$res)->num_rows > 0) {
		$hrs = ((object)$res)->fetch_all();
	}
	print '<form  action="' . $_SERVER["PHP_SELF"] . '" method="post" style="margin-top:15px;">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="confirmeWorkingDays">';
	print '<input type="hidden" name="day" value="' . $day . '">';
	print '<input type="hidden" name="month" value="' . $month . '">';
	print '<input type="hidden" name="year" value="' . $year . '">';
	print '<input type="hidden" name="limit" value="' . $limit . '">';


	print ' <style>
                .small-td input{
                    width:90%;
                }
				.titlefield{
					min-width:115px;
					max-width:120px;
				}
            </style>
        <table class="noborder editmode" style="width:96%; margin-left:3%;">
    <thead>
        <tr class="liste_titre">
            <th class="titlefield wordbreak">Matricule</th>
            <th class="titlefield wordbreak">Nom Complet</th>
            <th class="titlefield wordbreak">Nombre des jours travaillé <input  style="width:70px;" name="workingdaysAll" id="workingdaysAll" value=""></th>
            <th class="titlefield wordbreak">Nombre des Heures travaillés (horaire)</th>
            <th class="titlefield wordbreak">Nombre des jours de congé <input style="width:70px;" name="congeAll"  id="congeAll" value=""> </th>
            <th class="titlefield wordbreak">Nombre des jours de férié <input style="width:70px;" name="joursferieAll"  id="joursferieAll" value=""></th>';
	foreach ((array)$hrs as $hr) {
		print '<th class="titlefield wordbreak">' . $hr[1] . '</th>';
	}
	print '</tr>
    </thead>
    <tbody>';
	foreach ($users as $user) {
		$salaireParams = "";
		//Get user salaire informations from database
		$sql = "SELECT type from llx_Paie_UserInfo WHERE userid=" . $user->rowid;
		$res = $db->query($sql);
		if (((object)$res)->num_rows > 0) {
			$salaireParams = ((object)$res)->fetch_assoc();
		}
		$type = $salaireParams["type"];


		$workingdays = 26;
		$workingHours = 0;
		$sql = "SELECT workingDays, joursferie, joursconge, workingHours FROM llx_Paie_MonthDeclaration WHERE userid=$user->rowid AND month=$month AND year = $year";
		$res = $db->query($sql);
		if (((object)$res)->num_rows > 0) {
			$row = ((object)$res)->fetch_assoc();
			$workingdays = (float)$row["workingDays"];
			$workingHours = (int)$row["workingHours"];
			$joursferie = (int)$row["joursferie"];
			$daysConge = (float)$row["joursconge"];
		}

		if ($type == 'mensuel') //Mensuel
		{

			print "<tr>
                <td>$user->options_matricule</td>
                <td>$user->lastname $user->firstname</td>
                <td><input type='' name='workingdays_$user->rowid' class='workingdays' value='$workingdays'></td>
                <td>------</td>
				<td class='small-td'><input type='' name='daysConge_$user->rowid' class='congedays' value='$daysConge'></td>
				<td class='small-td'><input type='' name='joursferie_$user->rowid' class='daysferie' value='$joursferie'></td>";
		} else if ($type == 'horaire') //Journalier or Horaire
		{
			print "<tr>
                <td>$user->options_matricule</td>
                <td>$user->lastname $user->firstname</td>
                <td>------</td>
                <td><input type='number'  name='workingHours_$user->rowid' value='$workingHours'></td>
				<td class='small-td'><input type='number' step='0.5' name='daysConge_$user->rowid' value='$daysConge'></td>
				<td class='small-td'><input type='number' name='joursferie_$user->rowid' value='$joursferie'></td>";
		}

		foreach ((array)$hrs as $hr) {
			$sqlh = "SELECT nhours FROM llx_Paie_HourSuppDeclaration  WHERE rub=$hr[0] AND userid=$user->rowid AND month=$month AND year = $year";
			$resh = $db->query($sqlh);
			if (((object)$resh)->num_rows > 0) {
				$nhours = ((object)$resh)->fetch_assoc()["nhours"];
			}
			print "<td class='small-td'><input type='number' name='hoursupp_$hr[0]_$user->rowid' value='$nhours'></td>";
		}
		print '</tr>';
	}

	print '</tbody>
    </table>';



	print '<div class="right"  style="margin-bottom: 100px; margin-right: 5%;"><input type="submit" class="button" value="valider">';

	print '</form>';
}


function ShowBulletin($id)
{
	global $db, $year, $month, $object;

	$object->fetch($id);
	$object->rowid = $object->id;

	// $object = $users[$id];

	include 'Bulletin_Class.php';

	$bulttin = '<style type="text/css">
        table.tableizer-table {
            font-size: 11px;
            margin:auto;
            border-bottom:1px solid #000;
            border-collapse: collapse;
        } 
        .tableizer-table td {
            padding: 4px;
            margin: 3px;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
        }
        .tableizer-table th {
            background-color: #104E8B; 
            color: #FFF;
            font-weight: bold;
        }
        .row-bordered{
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        .row-content{
            background-color: rgb(214, 214, 214);
        }
        .importent-cell{
            background-color: rgb(122, 166, 202);
            font-weight: bold;
        }
        .white-cell{
            background-color: white;
            font-weight: bold;
        }
        </style>
            <table class="tableizer-table">
                <thead><tr class="tableizer-firstrow"><th colspan="7">BULLETIN DE PAIE</th></tr></thead>
                <tbody>
                <tr class="importent-cell row-bordered"><td>&nbsp;</td><td>&nbsp;</td><td>Nom</td><td class="white-cell">' . $object->lastname . ' ' . $object->firstname . '</td><td>Date de naissance</td><td class="white-cell" colspan="2">' . date("d/m/Y", $object->birth) . '</td></tr>
                <tr class="importent-cell row-bordered"><td>&nbsp;</td><td>&nbsp;</td><td>N° CNSS</td><td class="white-cell">' . $salaireParams["cnss"] . '</td><td>Fonction</td><td class="white-cell" colspan="2">' . $object->job . '</td></tr>
                <tr class="importent-cell row-bordered"><td>&nbsp;</td><td>&nbsp;</td><td>Matricule</td><td class="white-cell">' . $extrafields["matricule"] . '</td><td>N° CIMR</td><td class="white-cell" colspan="2">' . $salaireParams["cimr"] . '</td></tr>
                <tr class="importent-cell row-bordered"><td>&nbsp;</td><td>&nbsp;</td><td>Periode</td><td class="white-cell">' . $periode . '</td><td>adresse</td><td class="white-cell" colspan="2">' . $object->address . '</td></tr>
                <tr class="importent-cell row-bordered"><td>&nbsp;</td><td>&nbsp;</td><td>Situation familiale</td><td class="white-cell">' . $situation . '</td><td>nombre d\'enfants</td><td class="white-cell" colspan="2">' . $enfants . '</td></tr>
                <tr class="importent-cell row-bordered"><td rowspan="2">Rub</td><td rowspan="2">Désignation</td><td rowspan="2">Nombre</td><td rowspan="2">Base</td><td colspan="3">Part salariale</td></tr>
                <tr class="importent-cell row-bordered"><td>Taux</td><td>A payer</td><td>A retenues</td></tr>
    ';

	if ($type == "mensuel") {
		$bulttin .= '<tr class="row-content"><td>' . getRebrique("salaireMensuel") . '</td><td>SALAIRE MENSUEL</td><td></td><td>' . price($bases["salaire de base"], 0, '', 1, 1, 2) . '</td><td> ' . $Taux . ' </td><td> ' . price($bases["salaire mensuel"], 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
	}

	if ($type == "horaire") {
		$bulttin .= '<tr class="row-content"><td>' . getRebrique("salaireHoraire") . '</td><td>SALAIRE HORAIRE</td><td>' . $workingHours . '</td><td>' . price($salaireHoraire, 0, '', 1, 1, 2) . '</td><td>  </td><td> ' . price($bases["salaire mensuel"], 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
	}

	if ($soldeConge > 0) {
		$bulttin .= '<tr class="row-content"><td>' . getRebrique("congePaye") . '</td><td>CONGE PAYE</td><td>&nbsp;</td><td>' . price($bases["salaire de base"], 0, '', 1, 1, 2) . '</td><td>' . $congeDays . '</td><td> ' . price($soldeConge, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
	}

	if ($soldeferie > 0) {
		$bulttin .= '<tr class="row-content"><td>' . getRebrique("joursferie") . '</td><td>JOURS FERIE</td><td>&nbsp;</td><td>' . price($bases["salaire de base"], 0, '', 1, 1, 2) . '</td><td>' . $joursFerie . '</td><td> ' . price($soldeferie, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
	}

	foreach ((array)$hrs as $hr) {
		$bulttin .= '<tr class="row-content"><td>' . $hr["rub"] . '</td><td>' . $hr["designation"] . '</td><td>' . price($hr["nombre"], 0, '', 1, 1, 2) . '</td><td>' . price($hr["base"], 0, '', 1, 1, 2) . '</td><td>' . $hr["taux"] . '%</td><td> ' . price($hr["apayer"], 0, '', 1, 1, 2) . ' </td><td>' . price($hr["aretenu"], 0, '', 1, 1, 2) . '</td></tr>';
	}

	if ($primeDancien > 0) {
		$bulttin .= ' <tr class="row-content"><td>' . getRebrique("primeDancien") . '</td><td>PRIME D\'ANCIENNETE</td><td>&nbsp;</td><td>' . price($bases['primeDancien'], 0, '', 1, 1, 2) . '</td><td>' . $primeDancienPercentage . '%</td><td>' . price($primeDancien, 0, '', 1, 1, 2) . '</td><td>&nbsp;</td></tr> ';
	}

	foreach ((array)$enBruts as $enBrut) {
		$base = $enBrut["base"] > 0 ? price($enBrut["base"], 0, '', 1, 1, 2) : "";
		$bulttin .= '<tr class="row-content"><td>' . $enBrut["rub"] . '</td><td>' . $enBrut["designation"] . '</td><td>' . $enBrut["nombre"] . '</td><td>' . $base . '</td><td>' . $enBrut["taux"] . '</td><td> ' . price($enBrut["apayer"], 0, '', 1, 1, 2) . ' </td><td>' . price($enBrut["aretenu"], 0, '', 1, 1, 2) . '</td></tr>';
	}

	if ($primeCommercial > 0) {
		$base = $CA > 0 ? price($CA, 0, '', 1, 1, 2) : "";
		$bulttin .= '<tr class="row-content"><td>' . getRebrique("primeCommercial") . '</td><td>PRIME COMMERCIAL</td><td></td><td>' . $base . '</td><td>' . $percent . '%</td><td> ' . price($primeCommercial, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
	}

	$bulttin .= '<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr class="row-content"><td></td><td>SALAIRE BRUT</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td> ' . price($brutGlobal, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>
             <tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';

	foreach ((array)$cotisations as $cotisation) {
		$base = $cotisation["base"] > 0 ? price($cotisation["base"], 0, '', 1, 1, 2) : "";
		$bulttin .= '<tr class=""><td>' . $cotisation["rub"] . '</td><td>' . $cotisation["designation"] . '</td><td>' . price($cotisation["nombre"], 0, '', 1, 1, 2) . '</td><td>' . $base . '</td><td>' . $cotisation["taux"] . '</td><td>  </td><td>' . price($cotisation["aretenu"], 0, '', 1, 1, 2) . '</td></tr>';
	}

	$bulttin .= ' <tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr class="row-content"><td>' . getRebrique("netImposable") . '</td><td>SALAIRE NET IMPOSABLE</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td> ' . price($netImposable, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>
             <tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';

	if ($chargeFamille != 0) {
		$bulttin .= '<tr class="row-content"><td>' . getRebrique("chargefamille") . '</td><td>CHARGE DE FAMILLE</td><td>&nbsp;</td><td>&nbsp;</td><td>' . $chargeFamilleTaux . '</td><td>' . price($chargeFamille, 0, '', 1, 1, 2) . '</td><td>&nbsp;</td></tr>';
	}

	if ($irNet > 0) {
		$bulttin .= '<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr><td>' . getRebrique("ir") . '</td><td>IR</td><td>&nbsp;</td><td>&nbsp;</td><td>' . $ir['percentIR'] . '%</td><td>&nbsp;</td><td> ' . price($irNet, 0, '', 1, 1, 2) . ' </td></tr>';
	}

	foreach ((array)$pasEnBruts as $pasEnBrut) {
		$base = $pasEnBrut["base"] > 0 ? price($pasEnBrut["base"], 0, '', 1, 1, 2) : "";
		$bulttin .= '<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
		$bulttin .= '<tr class="row-content"><td>' . $pasEnBrut["rub"] . '</td><td>' . $pasEnBrut["designation"] . '</td><td>&nbsp;</td><td>' . price($pasEnBrut["nombre"], 0, '', 1, 1, 2) . '</td><td>' . $base . '</td><td>' . price($pasEnBrut["apayer"], 0, '', 1, 1, 2) . '</td><td>' . price($pasEnBrut["aretenu"], 0, '', 1, 1, 2) . '</td></tr>';

		// $bulttin .= '<tr class="row-content"><td>' . $pasEnBrut["rub"] . '</td><td>' . $pasEnBrut["designation"] . '</td><td>' . price($pasEnBrut["aretenu"], 0, '', 1, 1,2) . '</td></tr>';
	}

	$bulttin .= '<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
             <tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td></td><td class="importent-cell row-bordered"> ' . price($totalBrut, 0, '', 1, 1, 2) . ' </td><td class="importent-cell row-bordered"> ' . price($totalRetenu + $retenueFromBrut, 0, '', 1, 1, 2) . ' </td></tr>
             <tr ><td></td><td>&nbsp;</td><td class="importent-cell row-bordered" colspan="3">Net a payer</td><td class="importent-cell row-bordered" colspan="2"> ' . price($totalNet, 0, '', 1, 1, 2) . ' </td></tr>

             <tr><td></td><td>&nbsp;</td><td>Jours travaillés</td><td>Brut imposable</td><td>Net imposable</td><td>Retenue I.R.</td></tr>
             <tr class="row-bordered row-content"><td></td><td>Mensuel</td><td>' . ($workingdaysdeclaré) . '</td><td>' . price($brutImposable) . '</td><td>' . price($netImposable, 0, '', 1, 1, 2) . '</td><td>' . price($irNet, 0, '', 1, 1, 2) . '</td></tr>
             <tr class="row-bordered row-content"><td></td><td>Annuel</td><td>' . ($comulWorkingDays + $workingdaysdeclaré) . '</td><td>' . price($comulsalaireBrut + $brutImposable, 0, '', 1, 1, 2) . '</td><td>' . price($comulnetImposable + $netImposable, 0, '', 1, 1, 2) . '</td><td>' . price($comulIR + $irNet, 0, '', 1, 1, 2) . '</td></tr>
       </tbody>
    </table>';

	echo $bulttin;
}

function ShowAllBulletins()
{
	global $db, $users, $year, $month;

	$i = 0;
	foreach ($users as $object) {
		ShowBulletin($object->rowid);
		$i++;
		echo '<br><br><br><br>';
	}
	// echo $i;
}

//Les compte comptable
function getRebrique($name)
{
	global $db;
	$sql = "SELECT rub FROM llx_Paie_Rubriques WHERE name = '$name'";
	$res = $db->query($sql);
	if ($res) {
		$row = ((object)$res)->fetch_assoc();
	} else
		print("<br>fail ERR: " . $sql);

	return $row["rub"];
}

// style
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


echo '
<script>
	workingdaysAll.oninput = function(){
		els=document.getElementsByClassName("workingdays");
		for(i=0; i<els.length; i++){
			els.item(i).value=workingdaysAll.value;
		}
	} 
	congeAll.oninput = function(){
		els=document.getElementsByClassName("congedays");
		for(i=0; i<els.length; i++){
			els.item(i).value=congeAll.value;
		}
	} 
	joursferieAll.oninput = function(){
		els=document.getElementsByClassName("daysferie");
		for(i=0; i<els.length; i++){
			els.item(i).value=joursferieAll.value;
		}
	} 
</script>
';



//filter by date
function datefilter()
{
	print '<div class="center">';
	print '<form id="frmfilter" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="filter">';

	// Show navigation bar
	$nav = '<div class="date-container">
				<div class="inp-wrapper">
					<div class="date-wrapper">
						<input type="month" id="date" name="date">
						
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
		$('#date').val('" . $year . "-" . $month . "');	
	});
</script>
<style>
	@media only screen and (min-width: 977px) {
		div.div-table-responsive {
			width: calc(100vw - 285px);
			overflow-x: scroll;
		}
	}
</style>";




$db->free($result);

// End of page
llxFooter();
$db->close();
