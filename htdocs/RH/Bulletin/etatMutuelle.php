<?php

// Load Dolibarr environment
require '../../main.inc.php';
require_once '../../vendor/autoload.php';

require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



if (!$user->rights->salaries->read) {
    accessforbidden("you don't have right for this page");
}



$trimestres = ['1'=>[1,2,3], '2'=>[4,5,6], '3'=>[7,8,9], '4'=>[10,11,12]];
$fileHeaders = ["N° Affilié", "Nom", "Prénom", "Date Naissance\nJJMMAAAA", "Date Entrée\nJJMMAAAA", "Exercice", "Trimestre", "Salaire"];
$action = GETPOST('action', 'aZ09'); // The action 'add', 'create', 'edit', 'update', 'view', ...
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'userlist'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page

//get 
// Security check (for external users)
$socid = 0;
if ($user->socid > 0) {
	$socid = $user->socid;
}


// Load variable for pagination
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
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
$diroutputmassaction = $conf->user->dir_output.'/temp/massgeneration/'.$user->id;
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
	if (GETPOST('search_'.$key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
}

$userstatic = new User($db);
$companystatic = new Societe($db);
$form = new Form($db);

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'u.login'=>"Login",
	'u.lastname'=>"Lastname",
	'u.firstname'=>"Firstname",
	'u.accountancy_code'=>"AccountancyCode",
	'u.office_phone'=>"PhonePro",
	'u.user_mobile'=>"PhoneMobile",
	'u.email'=>"EMail",
	'u.note'=>"Note",
);
if (!empty($conf->api->enabled)) {
	$fieldstosearchall['u.api_key'] = "ApiKey";
}

// Definition of fields for list
$arrayfields = array(
	'u.login'=>array('label'=>"Login", 'checked'=>1, 'position'=>10),
	'u.lastname'=>array('label'=>"Lastname", 'checked'=>1, 'position'=>15),
	'u.firstname'=>array('label'=>"Firstname", 'checked'=>1, 'position'=>20),
	'u.entity'=>array('label'=>"Entity", 'checked'=>1, 'position'=>50, 'enabled'=>(!empty($conf->multicompany->enabled) && empty($conf->global->MULTICOMPANY_TRANSVERSE_MODE))),
	'u.gender'=>array('label'=>"Gender", 'checked'=>0, 'position'=>22),
	'u.fk_user'=>array('label'=>"HierarchicalResponsible", 'checked'=>1, 'position'=>27),
	'u.accountancy_code'=>array('label'=>"AccountancyCode", 'checked'=>0, 'position'=>30),
	'u.office_phone'=>array('label'=>"PhonePro", 'checked'=>1, 'position'=>31),
	'u.user_mobile'=>array('label'=>"PhoneMobile", 'checked'=>1, 'position'=>32),
	'u.email'=>array('label'=>"EMail", 'checked'=>1, 'position'=>35),
	'u.api_key'=>array('label'=>"ApiKey", 'checked'=>0, 'position'=>40, "enabled"=>(!empty($conf->api->enabled) && $user->admin)),
	'u.fk_soc'=>array('label'=>"Company", 'checked'=>($contextpage == 'employeelist' ? 0 : 1), 'position'=>45),
	'u.salary'=>array('label'=>"Salary", 'checked'=>1, 'position'=>80, 'enabled'=>(!empty($conf->salaries->enabled) && !empty($user->rights->salaries->readall))),
	'u.datelastlogin'=>array('label'=>"LastConnexion", 'checked'=>1, 'position'=>100),
	'u.datepreviouslogin'=>array('label'=>"PreviousConnexion", 'checked'=>0, 'position'=>110),
	'u.datec'=>array('label'=>"DateCreation", 'checked'=>0, 'position'=>500),
	'u.tms'=>array('label'=>"DateModificationShort", 'checked'=>0, 'position'=>500),
	'u.statut'=>array('label'=>"Status", 'checked'=>1, 'position'=>1000),
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

$month = $mydate['mon'];
$year = $mydate['year'];







if ($action == 'view' && isset($_POST['download'])) {
	$yearSeleted = GETPOST('year', 'int');
    $trimestre = GETPOST('trimestre', 'int');
    $months = implode(',', $trimestres[$trimestre]);

    if (($yearSeleted == $year && $month >= end($trimestres[$trimestre])) || $yearSeleted<$year){
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        // $sheet->getColumnDimensionByColumn(1)->setAutoSize(true);
    
        for ($i = 0, $l = count($fileHeaders); $i < $l; $i++) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $fileHeaders[$i]);
        }
        $sheet->getStyle('D1')->getAlignment()->setWrapText(true);
        $sheet->getStyle('E1')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:H1')->getAlignment()->setVertical('center');
        
    
        
    
            
        $j=2;
        foreach ($toselect as $toselectid) {
            $sql = "select b.mutuelle, c.lastname as nom, c.firstname as prenom, c.birth as naissance, c.dateemployment as embauche
             from llx_Paie_UserInfo b, llx_user c where c.rowid = $toselectid and b.userid = $toselectid";
            $res = $db->query($sql);
            if ($res->num_rows) {
                $row = $res->fetch_array();
                for ($i = 0; $i < 5; $i++) { 
                    if (($i == 3 || $i == 4) && !empty($row[$i])){
                        $dateSeperated = '';
                        $dateSeperated = explode('-', $row[$i]);
                        $sheet->setCellValueByColumnAndRow($i +1, $j, $dateSeperated[2].$dateSeperated[1].$dateSeperated[0]);
                    }else{
                        $sheet->setCellValueByColumnAndRow($i +1, $j, $row[$i]);
                    }
                }
                $sheet->setCellValueByColumnAndRow(6, $j, $yearSeleted);
                $sheet->setCellValueByColumnAndRow(7, $j, $trimestre);
                $sql1 = "SELECT sum(salaireBrut) as salaire FROM llx_Paie_MonthDeclaration WHERE userid=$toselectid AND year=$yearSeleted AND month in ($months)";
                    $res1 = $db->query($sql1);
                    if ($res1) {
                        $row1 = $res1->fetch_assoc();
                        $salaire = $row1["salaire"] > 0 ? $row1["salaire"] : 0;
                    }else{
                        $salaire = 0;
                    }
                    $sheet->setCellValueByColumnAndRow(8, $j, $salaire);
                $j++;	
            }
    
    
        }
        foreach ($sheet->getColumnIterator() as $column) {
            $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        $sheet->getStyle('A1:H1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('96ABC0');
        $sheet->getStyle('A:H')->getAlignment()->setHorizontal('center');

    
        setlocale(LC_ALL, 'en_US');
        $fileName = "Etat_mutuelle_".$yearSeleted."_".$trimestre.".xlsx";
        $writer = new Xlsx($spreadsheet);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'. urlencode($fileName).'"');
        $writer->save('php://output');
        exit();
    }else{
        setEventMessages("On peut pas generer l'etat pour cette trimestre", array(), 'errors');
    }



}



llxHeader("", "Etat Mutuelle");



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

$text = "Etat Mutuelle";

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
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? "ef.".$key." as options_".$key.', ' : '');
	}
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters); // Note that $action and $object may have been modified by hook
$sql .= preg_replace('/^,/', '', $hookmanager->resPrint);
$sql = preg_replace('/,\s*$/', '', $sql);
$sql .= " FROM ".MAIN_DB_PREFIX."user as u";
if (key_exists('label', $extrafields->attributes[$object->table_element]) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (u.rowid = ef.fk_object)";
}
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON u.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u2 ON u.fk_user = u2.rowid";
if (!empty($search_categ) || !empty($catid)) {
	$sql .= ' LEFT JOIN '.MAIN_DB_PREFIX."categorie_user as cu ON u.rowid = cu.fk_user"; // We'll need this table joined to the select in order to filter by categ
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printUserListWhere', $parameters); // Note that $action and $object may have been modified by hook
if ($reshook > 0) {
	$sql .= $hookmanager->resPrint;
} else {
	$sql .= " WHERE u.entity IN (".getEntity('user').") and u.employee=1";
}
if ($socid > 0) {
	$sql .= " AND u.fk_soc = ".((int) $socid);
}
//if ($search_user != '')       $sql.=natural_search(array('u.login', 'u.lastname', 'u.firstname'), $search_user);
if ($search_supervisor > 0) {
	$sql .= " AND u.fk_user IN (".$db->sanitize($search_supervisor).")";
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
	$sql .= " AND u.gender = '".$db->escape($search_gender)."'"; // Cannot use natural_search as looking for %man% also includes woman
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
	$sql .= " AND u.statut IN (".$db->escape($search_statut).")";
}
if ($sall) {
	$sql .= natural_search(array_keys($fieldstosearchall), $sall);
}
if ($catid > 0) {
	$sql .= " AND cu.fk_categorie = ".((int) $catid);
}
if ($catid == -2) {
	$sql .= " AND cu.fk_categorie IS NULL";
}
if ($search_categ > 0) {
	$sql .= " AND cu.fk_categorie = ".((int) $search_categ);
}
if ($search_categ == -2) {
	$sql .= " AND cu.fk_categorie IS NULL";
}
if ($search_warehouse > 0) {
	$sql .= " AND u.fk_warehouse = ".((int) $search_warehouse);
}
// Add where from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
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
	$param .= '&amp;contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&amp;limit='.urlencode($limit);
}
if ($sall != '') {
	$param .= '&amp;sall='.urlencode($sall);
}
if ($search_user != '') {
	$param .= "&amp;search_user=".urlencode($search_user);
}
if ($search_login != '') {
	$param .= "&amp;search_login=".urlencode($search_login);
}
if ($search_lastname != '') {
	$param .= "&amp;search_lastname=".urlencode($search_lastname);
}
if ($search_firstname != '') {
	$param .= "&amp;search_firstname=".urlencode($search_firstname);
}
if ($search_gender != '') {
	$param .= "&amp;search_gender=".urlencode($search_gender);
}
if ($search_accountancy_code != '') {
	$param .= "&amp;search_accountancy_code=".urlencode($search_accountancy_code);
}
if ($search_phonepro != '') {
	$param .= "&amp;search_phonepro=".urlencode($search_phonepro);
}
if ($search_phonemobile != '') {
	$param .= "&amp;search_phonemobile=".urlencode($search_phonemobile);
}
if ($search_email != '') {
	$param .= "&amp;search_email=".urlencode($search_email);
}
if ($search_api_key != '') {
	$param .= "&amp;search_api_key=".urlencode($search_api_key);
}
if ($search_supervisor > 0) {
	$param .= "&amp;search_supervisor=".urlencode($search_supervisor);
}
if ($search_statut != '') {
	$param .= "&amp;search_statut=".urlencode($search_statut);
}
if ($search_categ > 0) {
	$param .= '&amp;search_categ='.urlencode($search_categ);
}
if ($search_warehouse > 0) {
	$param .= '&amp;search_warehouse='.urlencode($search_warehouse);
}
// Add $param from extra fields
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

// List of mass actions available
$arrayofmassactions = array();
$arrayofmassactions['disable'] = img_picto('', 'close_title', 'class="pictofixedwidth"').$langs->trans("DisableUser");

$massactionbutton = $form->selectMassAction('', $arrayofmassactions);







print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="fromYear" value="' . $fromYear . '">';
print '<input type="hidden" name="fromMonth" value="' . $fromMonth . '">';
print '<input type="hidden" name="toYear" value="' . $year . '">';
print '<input type="hidden" name="toMonth" value="' . $month . '">';
print '<input type="hidden" name="action" value="view">';


$moreparam = array('morecss'=>'marginleftonly');
$morehtmlright .= dolGetButtonTitle($langs->trans("HierarchicView"), '', 'fa fa-sitemap paddingleft', DOL_URL_ROOT.'/RH/Users/hierarchy.php'.(($search_statut != '' && $search_statut >= 0) ? '?search_statut='.$search_statut : ''), '', 1, $moreparam);

print_barre_liste($text, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'setup', 0, $morehtmlright.' '.$newcardbutton, '', $limit, 0, 0, 1);

// Add code for pre mass action (confirmation or email presend form)
$topicmail = "SendUserRef";
$modelmail = "user";
$objecttmp = new User($db);
$trackid = 'use'.$object->id;
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if (!empty($catid)) {
	print "<div id='ways'>";
	$c = new Categorie($db);
	$ways = $c->print_all_ways(' &gt; ', 'RH/Users/list.php');
	print " &gt; ".$ways[0]."<br>\n";
	print "</div><br>";
}

if ($search_all) {
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
	}
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $search_all).join(', ', $fieldstosearchall).'</div>';
}

$moreforfilter = '';

// Filter on categories
if (!empty($conf->categorie->enabled) && $user->rights->categorie->lire) {
	$moreforfilter .= '<div class="divsearchfield">';
	$tmptitle = $langs->trans('Category');
	$moreforfilter .= img_picto($langs->trans("Category"), 'category', 'class="pictofixedwidth"').$formother->select_categories(Categorie::TYPE_USER, $search_categ, 'search_categ', 1, $tmptitle);
	$moreforfilter .= '</div>';
}
// Filter on warehouse
if (!empty($conf->stock->enabled) && !empty($conf->global->MAIN_DEFAULT_WAREHOUSE_USER)) {
	require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';
	$formproduct = new FormProduct($db);
	$moreforfilter .= '<div class="divsearchfield">';
	$tmptitle = $langs->trans('Warehouse');
	$moreforfilter .= img_picto($tmptitle, 'stock', 'class="pictofixedwidth"').$formproduct->selectWarehouses($search_warehouse, 'search_warehouse', '', $tmptitle, 0, 0, $tmptitle);
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
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre_filter">';

// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';

if (!empty($arrayfields['u.login']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_login" class="maxwidth50" value="'.$search_login.'"></td>';
}
if (!empty($arrayfields['u.lastname']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_lastname" class="maxwidth50" value="'.$search_lastname.'"></td>';
}
if (!empty($arrayfields['u.firstname']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_firstname" class="maxwidth50" value="'.$search_firstname.'"></td>';
}
if (!empty($arrayfields['u.gender']['checked'])) {
	print '<td class="liste_titre">';
	$arraygender = array('man'=>$langs->trans("Genderman"), 'woman'=>$langs->trans("Genderwoman"), 'other'=>$langs->trans("Genderother"));
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
	print '<td class="liste_titre"><input type="text" name="search_accountancy_code" class="maxwidth50" value="'.$search_accountancy_code.'"></td>';
}
if (!empty($arrayfields['u.office_phone']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_phonepro" class="maxwidth50" value="'.$search_phonepro.'"></td>';
}
if (!empty($arrayfields['u.user_mobile']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_phonemobile" class="maxwidth50" value="'.$search_phonemobile.'"></td>';
}
if (!empty($arrayfields['u.email']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_email" class="maxwidth75" value="'.$search_email.'"></td>';
}
if (!empty($arrayfields['u.api_key']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_api_key" class="maxwidth50" value="'.$search_api_key.'"></td>';
}
if (!empty($arrayfields['u.fk_soc']['checked'])) {
	print '<td class="liste_titre"><input type="text" name="search_thirdparty" class="maxwidth75" value="'.$search_thirdparty.'"></td>';
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
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';
// Fields from hook
$parameters = array('arrayfields'=>$arrayfields);
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
	print $form->selectarray('search_statut', array('-1'=>'', '0'=>$langs->trans('Disabled'), '1'=>$langs->trans('Enabled')), $search_statut, 0, 0, 0, '', 0, 0, 0, '', 'minwidth75imp');
	print '</td>';
}
print '</tr>';




print '<tr class="liste_titre">';
// Action column
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";

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
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
// Hook fields
$parameters = array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
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
print '</tr>'."\n";


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
		|| (!empty($conf->hrm->enabled) && !empty($user->rights->hrm->employee->read))) {
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
	print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
	print '</td>';
	


	// Login
	if (!empty($arrayfields['u.login']['checked'])) {
		print '<td class="nowraponall tdoverflowmax150"><a href="/RH/Users/card.php?id='.$obj->rowid.'">';

		if($obj->gender == "man"){
			print '<span class="nopadding userimg" style="margin-right: 3px;"><img class="photouserphoto userphoto" alt="" src="/public/theme/common/user_man.png"></span>';
		}else{
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
		print '<td class="tdoverflowmax150" title="'.dol_escape_htmltag($obj->lastname).'">'.dol_escape_htmltag($obj->lastname).'</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.firstname']['checked'])) {
		print '<td class="tdoverflowmax150" title="'.dol_escape_htmltag($obj->lastname).'">'.dol_escape_htmltag($obj->firstname).'</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.gender']['checked'])) {
		print '<td>';
		if ($obj->gender) {
			print $langs->trans("Gender".$obj->gender);
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	// Employee yes/no
	if (!empty($arrayfields['u.employee']['checked'])) {
		print '<td class="center">'.yn($obj->employee).'</td>';
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
		print '<td>'.$obj->accountancy_code.'</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	if (!empty($arrayfields['u.office_phone']['checked'])) {
		print '<td>'.dol_print_phone($obj->office_phone, $obj->country_code, 0, $obj->rowid, 'AC_TEL', ' ', 'phone')."</td>\n";
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.user_mobile']['checked'])) {
		print '<td>'.dol_print_phone($obj->user_mobile, $obj->country_code, 0, $obj->rowid, 'AC_TEL', ' ', 'mobile')."</td>\n";
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!empty($arrayfields['u.email']['checked'])) {
		print '<td class="tdoverflowmax150">'.dol_print_email($obj->email, $obj->rowid, $obj->fk_soc, 'AC_EMAIL', 0, 0, 1)."</td>\n";
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
				print '<span class="opacitymedium">'.$langs->trans("Hidden").'</span>';
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
			print '<span class="opacitymedium">'.$langs->trans("DomainUser").'</span>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans("InternalUser").'</span>';
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
				print '<span class="opacitymedium">'.$langs->trans("Hidden").'</span>';
			}
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	// Date last login
	if (!empty($arrayfields['u.datelastlogin']['checked'])) {
		print '<td class="nowrap center">'.dol_print_date($db->jdate($obj->datelastlogin), "dayhour").'</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	// Date previous login
	if (!empty($arrayfields['u.datepreviouslogin']['checked'])) {
		print '<td class="nowrap center">'.dol_print_date($db->jdate($obj->datepreviouslogin), "dayhour").'</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}

	// Extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
	// Fields from hook
	$parameters = array('arrayfields'=>$arrayfields, 'object'=>$object, 'obj'=>$obj, 'i'=>$i, 'totalarray'=>&$totalarray);
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
		print '<td class="center">'.$userstatic->getLibStatut(5).'</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	if (!$i) {
		$totalarray['nbfield']++;
	}

	print '</tr>'."\n";

	$i++;
}

// Show total line
include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';

// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
}


$db->free($resql);

$parameters = array('arrayfields'=>$arrayfields, 'sql'=>$sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

print '</table>';
print '</div>';


			
print '
<div class="filter">
        <label>Anneé</label>
        <input type="number" name="year" min="1900" max="'.$year.'" step="1" value="'.$year.'" />
        <label>trimestre</label>
        <select name="trimestre" id="trimestre">
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
        </select>
    </div>
		<input type="submit" name="download" style="" class="button small hideobject massaction massactionconfirmed" value="Telecharger">
	</div>
</form>';










$db->free($result);

// End of page
llxFooter();
$db->close();
