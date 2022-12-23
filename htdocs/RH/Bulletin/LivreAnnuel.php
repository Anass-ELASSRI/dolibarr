<?php

// Load Dolibarr environment
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

if (!$user->rights->salaries->read) {
    accessforbidden("you don't have right for this page");
}


// Load translation files required by page
$langs->loadLangs(array('users', 'companies', 'hrm'));

$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'userlist'; // To manage different context of search

// Security check (for external users)
$socid = 0;
if ($user->socid > 0) {
    $socid = $user->socid;
}

// Load mode employee
$mode = GETPOST("mode", 'alpha');

// Load variable for paginationRayane@
$limit = GETPOST('limit', 'int') ? GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'alpha');
$sortorder = GETPOST('sortorder', 'alpha');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1) {
    $page = 0;
}
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) $sortfield = "u.login";
if (!$sortorder) $sortorder = "ASC";

// Define value to know what current user can do on users
$canadduser = (!empty($user->admin) || $user->rights->user->user->creer);

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$object = new User($db);
$hookmanager->initHooks(array('userlist'));
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

$userstatic = new User($db);
$companystatic = new Societe($db);
$form = new Form($db);

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
    'u.login' => "Login",
    'u.lastname' => "Lastname",
    'u.firstname' => "Firstname",
    'u.accountancy_code' => "AccountancyCode",
    'u.email' => "EMail",
    'u.note' => "Note",
);
if (!empty($conf->api->enabled)) {
    $fieldstosearchall['u.api_key'] = "ApiKey";
}

// Definition of fields for list
$arrayfields = array(
    'u.login' => array('label' => $langs->trans("Login"), 'checked' => 1),
    'u.lastname' => array('label' => $langs->trans("Lastname"), 'checked' => 1),
    'u.firstname' => array('label' => $langs->trans("Firstname"), 'checked' => 1),
    'u.gender' => array('label' => $langs->trans("Gender"), 'checked' => 0),
    'u.employee' => array('label' => $langs->trans("Employee"), 'checked' => ($mode == 'employee' ? 1 : 0)),
    'u.accountancy_code' => array('label' => $langs->trans("AccountancyCode"), 'checked' => 0),
    'u.email' => array('label' => $langs->trans("EMail"), 'checked' => 1),
    'u.api_key' => array('label' => $langs->trans("ApiKey"), 'checked' => 0, "enabled" => ($conf->api->enabled && $user->admin)),
    'u.fk_soc' => array('label' => $langs->trans("Company"), 'checked' => 1),
    'u.entity' => array('label' => $langs->trans("Entity"), 'checked' => 1, 'enabled' => (!empty($conf->multicompany->enabled) && empty($conf->global->MULTICOMPANY_TRANSVERSE_MODE))),
    'u.fk_user' => array('label' => $langs->trans("HierarchicalResponsible"), 'checked' => 1),
    'u.datelastlogin' => array('label' => $langs->trans("LastConnexion"), 'checked' => 1, 'position' => 100),
    'u.datepreviouslogin' => array('label' => $langs->trans("PreviousConnexion"), 'checked' => 0, 'position' => 110),
    'u.datec' => array('label' => $langs->trans("DateCreation"), 'checked' => 0, 'position' => 500),
    'u.tms' => array('label' => $langs->trans("DateModificationShort"), 'checked' => 0, 'position' => 500),
    'u.statut' => array('label' => $langs->trans("Status"), 'checked' => 1, 'position' => 1000),
);


/*
 * Actions
 */



$action = GETPOST('action');
$id = GETPOST('id', 'int');


$mydate = getdate(date("U"));
// print_r($mydate);
$day = (GETPOST('reday') != '') ? GETPOST('reday') : $mydate['mday'];
$prev_year = (GETPOST('reyear') != '') ? GETPOST('reyear') : $mydate['year'];
$showAll = GETPOST('showAll');

$start = 1;

/*
 * View
 */

$htmlother = new FormOther($db);

$user2 = new User($db);

$buttonviewhierarchy = '<form action="' . DOL_URL_ROOT . '/user/hierarchy.php' . (($search_statut != '' && $search_statut >= 0) ? '?search_statut=' . $search_statut : '') . '" method="POST"><input type="submit" class="button" style="width:120px" name="viewcal" value="' . dol_escape_htmltag($langs->trans("HierarchicView")) . '"></form>';

$sql0 = "SELECT DISTINCT u.rowid, u.lastname, u.firstname, u.admin, u.fk_soc, u.login,  u.gender, u.photo, u.salary, u.dateemploymentend, u.dateemployment,";
$sql0 .= " u.statut";
//$sql0 .= " ex.name";

// Add fields from extrafields
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
    foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) $sql0 .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? ", ef." . $key . ' as options_' . $key : '');
}

// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters); // Note that $action and $object may have been modified by hook
$sql0 .= $hookmanager->resPrint;
$sql0 .= " FROM " . MAIN_DB_PREFIX . "user as u";
if (is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) $sql0 .= " LEFT JOIN " . MAIN_DB_PREFIX . $object->table_element . "_extrafields as ef on (u.rowid = ef.fk_object)";
$sql0 .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as u2 ON u.fk_user = u2.rowid";
if (!empty($search_categ) || !empty($catid)) $sql0 .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "categorie_user as cu ON u.rowid = cu.fk_user"; // We'll need this table joined to the select in order to filter by categ
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printUserListWhere', $parameters); // Note that $action and $object may have been modified by hook
if ($reshook > 0) {
    $sql0 .= $hookmanager->resPrint;
} else {
    $sql0 .= " WHERE u.entity IN (" . getEntity('user') . ")";
}
if ($socid > 0) $sql0 .= " AND u.fk_soc = " . $socid;

//specifie the status (1=Employee)
$sql0 .= " AND ef.status  = '1'";
$sql0 .= " AND YEAR(u.dateemployment)  <= $prev_year";

//if ($search_user != '')       $sql0.=natural_search(array('u.login', 'u.lastname', 'u.firstname'), $search_user);
if ($search_supervisor > 0)   $sql0 .= " AND u.fk_user IN (" . $db->escape($search_supervisor) . ")";
if ($search_thirdparty != '') $sql0 .= natural_search(array('s.nom'), $search_thirdparty);
if ($search_login != '')      $sql0 .= natural_search("u.login", $search_login);
if ($search_lastname != '')   $sql0 .= natural_search("u.lastname", $search_lastname);
if ($search_firstname != '')  $sql0 .= natural_search("u.firstname", $search_firstname);
if ($search_gender != '' && $search_gender != '-1')     $sql0 .= " AND u.gender = '" . $search_gender . "'";
if (is_numeric($search_employee) && $search_employee >= 0) {
    $sql0 .= ' AND u.employee = ' . (int) $search_employee;
}
if ($search_accountancy_code != '')  $sql0 .= natural_search("u.accountancy_code", $search_accountancy_code);
if ($search_email != '')             $sql0 .= natural_search("u.email", $search_email);
if ($search_api_key != '')           $sql0 .= natural_search("u.api_key", $search_api_key);
if ($search_statut != '' && $search_statut >= 0) $sql0 .= " AND u.statut IN (" . $db->escape($search_statut) . ")";
if ($sall)                           $sql0 .= natural_search(array_keys($fieldstosearchall), $sall);
if ($catid > 0)     $sql0 .= " AND cu.fk_categorie = " . $catid;
if ($catid == -2)   $sql0 .= " AND cu.fk_categorie IS NULL";
if ($search_categ > 0)   $sql0 .= " AND cu.fk_categorie = " . $db->escape($search_categ);
if ($search_categ == -2) $sql0 .= " AND cu.fk_categorie IS NULL";

// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters); // Note that $action and $object may have been modified by hook
$sql0 .= $hookmanager->resPrint;
$sql0 .= $db->order($sortfield, $sortorder);

$nbtotalofrecords = 0;

$param = '';

llxHeader("", "Livre de Paie Global");
$text = "Livre de Paie Global";

//peroide
$periode = sprintf("%02d", $start) . '/' . $prev_year . ' - '  . sprintf("%02d", $start + 11) .  '/' . $prev_year;

//add filter by date
datefilter();

$morehtmlright .= dolGetButtonTitle($langs->trans("HierarchicView"), '', 'fa fa-sitemap paddingleft', DOL_URL_ROOT . '/user/hierarchy.php' . (($search_statut != '' && $search_statut >= 0) ? '?search_statut=' . $search_statut : ''));

print_barre_liste($text, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, "", $num, $nbtotalofrecords, 'setup', 0, $morehtmlright . ' ' . $newcardbutton, '', 0, 0, 1);


$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters); // Note that $action and $object may have been modified by hook
print $hookmanager->resPrint;

//Save date to use in generate module
$_SESSION['start'] = $start;

$next = dol_get_next_month($month, $year);
$next_year  = $next['year'];
$next_month = $next['month'];
$next_day   = 1;


// Actions to build doc
$action = GETPOST('action', 'aZ09');
$upload_dir = DOL_DATA_ROOT . '/grh/LivreGlobal';
$permissiontoadd = 1;
$donotredirect = 1;
include DOL_DOCUMENT_ROOT . '/core/actions_builddoc.inc.php';

$_SESSION['start'] = null;

GenerateDocuments();

include "../class/LivreAnnuel_Class.php";

function GenerateDocuments()
{
    global $day, $month, $prev_year, $start;
    print '<form id="frmgen" name="generateDocs" method="post">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="generateOrderDeVerement">';
    print '<input type="hidden" name="model" value="LivreAnnuel">';
    print '<input type="hidden" name="day" value="' . $day . '">';
    print '<input type="hidden" name="remonth" value="' . $month . '">';
    print '<input type="hidden" name="reyear" value="' . $prev_year . '">';
    print '<input type="hidden" name="start" value="' . $start . '">';
    // print '<div class="right"  style="margin-bottom: 100px; margin-right: 20%;"><input type="submit" id="btngen" class="button" value="génerer"/></div>';
    print '</form>';
}

function ShowDocuments()
{
    global $db, $object, $conf, $month, $prev_year, $societe, $showAll, $prev_month, $prev_year, $start;
    print '<div class="fichecenter"><div class="fichehalfleft">';
    $formfile = new FormFile($db);

    $name = ($start == 1) ? "01-$prev_year" : "02-$prev_year";

    $subdir = get_exdir($object->id, 2, 0, 0, $object, 'RH');
    $filedir = DOL_DATA_ROOT . '/grh/LivreGlobal' . '/' . $subdir;

    $urlsource = $_SERVER['PHP_SELF'] . '';
    $genallowed = 0;
    $delallowed = 1;
    $modelpdf = (!empty($object->modelpdf) ? $object->modelpdf : (empty($conf->global->RH_ADDON_PDF) ? '' : $conf->global->RH_ADDON_PDF));

    if (!$showAll) {
        $_SESSION["filterDoc"] = $name;
    }

    print $formfile->showdocuments('LivreGlobal', $subdir, $filedir, $urlsource, $genallowed, $delallowed, $modelpdf, 1, 0, 0, 40, 0, 'remonth=' . $month . '&amp;reyear=' . $prev_year, '', '', $societe->default_lang);
    $somethingshown = $formfile->numoffiles;

    $_SESSION["filterDoc"] = null;
    // Show links to link elements
    //$linktoelem = $form->showLinkToObjectBlock($object, null, array('RH'));
}

// From Rubs
print '<form action="" method="post">
    <input type="hidden" name="action" value="saveRubs">
    <table>
        <tr>
            <td>INDEMNITE DE REPRESENTATION</td>
            <td><input type="number" name="indemRepr" id="indemRepr" value="' . $indemReprCode . '"></td>
            <td rowspan="2"><input type="submit" value="enregistrer" class="button"></td>
        </tr>
        <tr>
            <td>INDEMNITE DE TRANSPORT</td>
            <td><input type="number" name="indemTrans" id="indemTrans" value="' . $indemTransCode . '"></td>
        </tr>
            <td>TAXE PROFESSIONEL</td>
            <td><input type="number" name="taxePro" id="taxePro" value="' . $taxeProCode . '"></td>
        </"tr>
    </table>
</form>';

print "<hr/><hr/>";

$french_months = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');

$Livre = '<style type="text/css">
    table.tableizer-table {
        font-size: 14px;
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
            <thead><tr class="tableizer-firstrow"><th colspan="13">LIVRE DE PAIE Annuel</th></tr></thead>
            <tbody>
			 <tr class="importent-cell row-bordered"><td  colspan="13">Periode: ' . $periode . '</td></tr>
             <tr class="importent-cell row-bordered"><td>Nom Prenom</td><td>CIN</td><td>N° CNSS</td><td>Adresse</td><td>Brut globale</td><td>Indeminite Non Imposable</td><td>Brute Imposable</td><td>Taxe Professionel</td><td>Cotisation CNSS-AMO</td><td>N° Deduction</td><td>Net Imposable</td><td>IR</td><td>N de jours</td></tr>';

            // print $sql0;
            // $result = $db->query($sql0);
            // print_r($result);
            // print_r($data);

            foreach ($data as $row) {
                // print price($brutGlobalTot);
                $Livre .= '<tr><td>'.$row['lastname'].' '.$row['firstname'].'</td><td>'.$row['cin'].'</td><td>'.$row['cnss'].'</td><td>'.$row['address'].
                '</td><td>'.$row['brutGlobalTot'].'</td><td>'.$row['indemTotal'].'</td><td>'.$row['brutImposableTot'].'</td><td>'.$row['taxeProTot'].'</td><td>'.$row['amo_cnss'].'</td><td>'.$row['enfants'].
                '</td><td>'.$row['TotNetImposable'].'</td><td>'.$row['TotIR'].'</td><td>'.$row['TotWorkingdays'].'</td></tr>';
            }


$Livre .=       '</tbody>
            </table>';

print $Livre;

ShowDocuments();

//filter by date
function datefilter()
{
    global $form, $dateinvoice, $month, $year, $prev_year, $start, $prev_day, $next_year, $next_month, $next_day, $periode, $langs;
    print '<div class="center">';
    print '<form id="frmfilter" name="filter" action="' . $_SERVER["PHP_SELF"] . '" method="POST">';
    print '<input type="hidden" name="action" value="filter">';

    // Show navigation bar
    $next_yearClick = ($start == 1) ? $prev_year + 1 : $prev_year;
    $prev_yearClick = ($start == 1) ? $prev_year - 1 : $prev_year;
    $nav = '<a class="inline-block valignmiddle" href="?reyear=' . $prev_yearClick . "&start=" . 1 . '">' . img_previous($langs->trans("Previous")) . "</a>\n";
    $nav .= " <span id=\"month_name\">" . $periode . " </span>\n";
    $nav .= '<a class="inline-block valignmiddle" href="?reyear=' . $next_yearClick . "&start=" . 1 . '">' . img_next($langs->trans("Next")) . "</a>\n";
    //$nav.=" &nbsp; (<a href=\"?year=".$nowyear."&month=".$nowmonth."&day=".$nowday.$param."\">".$langs->trans("Today")."</a>)";
    //$nav.=' <input type="submit" name="submitdateselect" class="button" value="'.$langs->trans("Refresh").'">';
    $nav .= ' <button type="submit" name="button_search_x" value="x" class="bordertransp"><span class="fa fa-search"></span></button>';

    print $nav;

    //print '<input type="submit" class="button" value="Filtrer">';

    print '<br><input type="checkbox" id="showAll" name="showAll" value="1">';
    print '<label for="showAll">Show ALL</label>';
    print '</div>';
    print '</form>';
}

print "<script>
		$(document).ready(function(){
			$('#re').val('" . $month . "/" . $prev_year . "');	
		});
	</script>";


//Les compte comptable
function getRebrique($name)
{
    global $db;
    $sql = "SELECT rub FROM llx_Paie_Rubriques WHERE name = '$name'";
    $res = $db->query($sql);
    if ($res) {
        $row = $res->fetch_assoc();
    } else
        print("<br>fail ERR: " . $sql);

    return $row["rub"];
}

$db->free($result);

// End of page
llxFooter();
$db->close();
