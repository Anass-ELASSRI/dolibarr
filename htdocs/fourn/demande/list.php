<?php
/* Copyright (C) 2001-2006 Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016 Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2013      Cédric Salvador		<csalvador@gpcsolutions.fr>
 * Copyright (C) 2014      Marcos García		<marcosgdf@gmail.com>
 * Copyright (C) 2014      Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2016      Ferran Marcet		<fmarcet@2byte.es>
 * Copyright (C) 2018-2021 Frédéric France		<frederic.france@netlogic.fr>
 * Copyright (C) 2018-2022 Charlene Benke		<charlene@patas-monkey.com>
 * Copyright (C) 2019      Nicolas Zabouri		<info@inovea-conseil.com>
 * Copyright (C) 2021      Alexandre Spangaro   <aspangaro@open-dsi.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *   \file       htdocs/fourn/commande/list.php
 *   \ingroup    fournisseur
 *   \brief      List of purchase orders
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formorder.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

$langs->loadLangs(array("orders", "sendings", 'deliveries', 'companies', 'compta', 'bills', 'projects', 'suppliers', 'products'));

$action = GETPOST('action', 'aZ09');
$massaction = GETPOST('massaction', 'alpha');
$show_files = GETPOST('show_files', 'int');
$confirm = GETPOST('confirm', 'alpha');
$toselect = GETPOST('toselect', 'array');
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'supplierorderlist';

$search_date_creation_startday = GETPOST('search_date_creation_startday', 'int');
$search_date_creation_startmonth = GETPOST('search_date_creation_startmonth', 'int');
$search_date_creation_startyear = GETPOST('search_date_creation_startyear', 'int');
$search_date_creation_endday = GETPOST('search_date_creation_endday', 'int');
$search_date_creation_endmonth = GETPOST('search_date_creation_endmonth', 'int');
$search_date_creation_endyear = GETPOST('search_date_creation_endyear', 'int');
$search_date_creation_start = dol_mktime(0, 0, 0, $search_date_creation_startmonth, $search_date_creation_startday, $search_date_creation_startyear);	// Use tzserver
$search_date_creation_end = dol_mktime(23, 59, 59, $search_date_creation_endmonth, $search_date_creation_endday, $search_date_creation_endyear);
$search_date_delivery_startday = GETPOST('search_date_delivery_startday', 'int');
$search_date_delivery_startmonth = GETPOST('search_date_delivery_startmonth', 'int');
$search_date_delivery_startyear = GETPOST('search_date_delivery_startyear', 'int');
$search_date_delivery_endday = GETPOST('search_date_delivery_endday', 'int');
$search_date_delivery_endmonth = GETPOST('search_date_delivery_endmonth', 'int');
$search_date_delivery_endyear = GETPOST('search_date_delivery_endyear', 'int');
$search_date_delivery_start = dol_mktime(0, 0, 0, $search_date_delivery_startmonth, $search_date_delivery_startday, $search_date_delivery_startyear);	// Use tzserver
$search_date_delivery_end = dol_mktime(23, 59, 59, $search_date_delivery_endmonth, $search_date_delivery_endday, $search_date_delivery_endyear);

$search_date_valid_startday = GETPOST('search_date_valid_startday', 'int');
$search_date_valid_startmonth = GETPOST('search_date_valid_startmonth', 'int');
$search_date_valid_startyear = GETPOST('search_date_valid_startyear', 'int');
$search_date_valid_endday = GETPOST('search_date_valid_endday', 'int');
$search_date_valid_endmonth = GETPOST('search_date_valid_endmonth', 'int');
$search_date_valid_endyear = GETPOST('search_date_valid_endyear', 'int');
$search_date_valid_start = dol_mktime(0, 0, 0, $search_date_valid_startmonth, $search_date_valid_startday, $search_date_valid_startyear);	// Use tzserver
$search_date_valid_end = dol_mktime(23, 59, 59, $search_date_valid_endmonth, $search_date_valid_endday, $search_date_valid_endyear);


$search_ref = GETPOST('search_ref', 'alpha');
$search_tier = GETPOST('search_tier', 'alpha');
$search_user = GETPOST('search_user', 'int');
$search_multicurrency_code = GETPOST('search_multicurrency_code', 'alpha');
$search_project_ref = GETPOST('search_project_ref', 'alpha');
$search_btn = GETPOST('button_search', 'alpha');
$search_remove_btn = GETPOST('button_removefilter', 'alpha');

if (is_array(GETPOST('search_status', 'none'))) {	// 'none' because we want to know type before sanitizing
	$search_status = join(',', GETPOST('search_status', 'array:intcomma'));
} else {
	$search_status = (GETPOST('search_status', 'intcomma') != '' ? GETPOST('search_status', 'intcomma') : GETPOST('statut', 'intcomma'));
}

// Security check
$orderid = GETPOST('orderid', 'int');
if ($user->socid) {
	$socid = $user->socid;
}
$result = restrictedArea($user, 'fournisseur', $orderid, '', 'commande');

$diroutputmassaction = $conf->fournisseur->commande->dir_output.'/temp/massgeneration/'.$user->id;

$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1 || !empty($search_btn) || !empty($search_remove_btn) || (empty($toselect) && $massaction === '0')) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
	$sortfield = 'da.ref';
}
if (!$sortorder) {
	$sortorder = 'DESC';
}

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$object = new stdClass();
$hookmanager->initHooks(array('supplierorderlist'));

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
// foreach ($object->fields as $key => $val) {
// 	if (!empty($val['searchall'])) {
// 		$fieldstosearchall['da.'.$key] = $val['label'];
// 	}
// }
$fieldstosearchall['pd.description'] = 'Description';
$fieldstosearchall['s.nom'] = "ThirdParty";
$fieldstosearchall['s.name_alias'] = "AliasNameShort";
$fieldstosearchall['s.zip'] = "Zip";
$fieldstosearchall['s.town'] = "Town";
if (empty($user->socid)) {
	$fieldstosearchall["cf.note_private"] = "NotePrivate";
}

$checkedtypetiers = 0;

// Definition of array of fields for columns
$arrayfields = array(
	'da.ref'=>array('label'=>"Ref", 'enabled'=>1, 'position'=>47, 'checked'=>1),
	'da.fk_soc'=>array('label'=>"Tiers", 'enabled'=>1, 'position'=>47, 'checked'=>1),
	'project_ref'=>array('label'=>"Project", 'enabled'=>1, 'position'=>47, 'checked'=>1),
	'da.date_creation'=>array('label'=>"Date Creation", 'enabled'=>1, 'position'=>47, 'checked'=>1),
	'da.date_valid'=>array('label'=>"Date Validation", 'enabled'=>1, 'position'=>47, 'checked'=>1),
	'da.date_livraison'=>array('label'=>"Date livraison", 'enabled'=>1, 'position'=>47, 'checked'=>1),
	'da.multicurrency_code'=>array('label'=>"Devise", 'enabled'=>1, 'position'=>47, 'checked'=>1),
	'da.statut'=>array('label'=>"Statut", 'enabled'=>1, 'position'=>47, 'checked'=>1),
);

$error = 0;

$permissiontoread = ($user->rights->fournisseur->commande->lire || $user->rights->supplier_order->lire);
$permissiontoadd = ($user->rights->fournisseur->commande->creer || $user->rights->supplier_order->creer);
$permissiontodelete = ($user->rights->fournisseur->commande->supprimer || $user->rights->supplier_order->supprimer);
$permissiontovalidate = $permissiontoadd;
$permissiontoapprove = ($user->rights->fournisseur->commande->approuver || $user->rights->supplier_order->approuver);


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list'; $massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend' && $massaction != 'confirm_createsupplierbills') {
	$massaction = '';
}

$parameters = array('socid'=>$socid);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		$search_ref = '';
		$search_tier = '';
		$search_multicurrency_code = '';
		$search_project_ref = '';
		$search_status = '';
		$search_date_creation_startday = '';
		$search_date_creation_startmonth = '';
		$search_date_creation_startyear = '';
		$search_date_creation_endday = '';
		$search_date_creation_endmonth = '';
		$search_date_creation_endyear = '';
		$search_date_creation_start = '';
		$search_date_creation_end = '';
		$search_date_delivery_startday = '';
		$search_date_delivery_startmonth = '';
		$search_date_delivery_startyear = '';
		$search_date_delivery_endday = '';
		$search_date_delivery_endmonth = '';
		$search_date_delivery_endyear = '';
		$search_date_delivery_start = '';
		$search_date_delivery_end = '';
		$search_date_valid_startday = '';
		$search_date_valid_startmonth = '';
		$search_date_valid_startyear = '';
		$search_date_valid_endday = '';
		$search_date_valid_endmonth = '';
		$search_date_valid_endyear = '';
		$search_date_valid_start = '';
		$search_date_valid_end = '';
		$toselect = array();
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = ''; // Protection to avoid mass action if we force a new search during a mass action confirmation
	}

	// Mass actions
	$objectclass = 'CommandeFournisseur';
	$objectlabel = 'SupplierOrders';
	$uploaddir = $conf->fournisseur->commande->dir_output;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';

}


/*
 *	View
 */

$now = dol_now();

$form = new Form($db);
$thirdpartytmp = new Fournisseur($db);
$formfile = new FormFile($db);
$formorder = new FormOrder($db);

$title = "Demandes D'achat";


$help_url = '';
// llxHeader('',$title,$help_url);

$sql = 'SELECT';
if ($sall || $search_product_category > 0) {
	$sql = 'SELECT DISTINCT';
}
$sql .= " s.rowid as socid, s.nom, s.email, da.id, da.ref, da.statut, da.fk_user_author, date(da.date_creation) as date_demande, date(da.date_livraison) as date_livraison, date(da.date_valid) as date_valid, da.multicurrency_code,";
$sql .= " p.rowid as project_id, p.ref as project_ref, p.title as project_title,";
$sql .= " u.firstname, u.lastname, u.login, u.email as user_email, u.statut as user_status from ";

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
$sql .= MAIN_DB_PREFIX."demande_achat as da";

$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON da.fk_user_author = u.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON da.fk_soc = s.rowid";
$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."projet as p ON p.rowid = da.fk_projet where da.deleted = 0";
// We'll need this table joined to the select in order to filter by sale
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object); // Note that $action and $object may have been modified by hook
$sql .= $hookmanager->resPrint;
if ($search_ref) {
	$sql .= natural_search('da.ref', $search_ref);
}
if ($search_request_author) {
	$sql .= natural_search(array('u.lastname', 'u.firstname', 'u.login'), $search_request_author);
}
//Required triple check because statut=0 means draft filter
if (GETPOST('statut', 'intcomma') !== '') {
	$sql .= " AND da.statut IN (".$db->sanitize($db->escape($db->escape(GETPOST('statut', 'intcomma')))).")";
}
if ($search_status != '' && $search_status != '-1') {
	$sql .= " AND da.statut IN (".$db->sanitize($db->escape($search_status)).")";
}
if ($search_date_creation_start) {
	$sql .= " AND da.date_creation >= '".$db->idate($search_date_creation_start)."'";
}
if ($search_date_creation_end) {
	$sql .= " AND da.date_creation <= '".$db->idate($search_date_creation_end)."'";
}
if ($search_date_delivery_start) {
	$sql .= " AND da.date_livraison >= '".$db->idate($search_date_delivery_start)."'";
}
if ($search_date_delivery_end) {
	$sql .= " AND da.date_livraison <= '".$db->idate($search_date_delivery_end)."'";
}
if ($search_date_valid_start) {
	$sql .= " AND da.date_valid >= '".$db->idate($search_date_valid_start)."'";
}
if ($search_date_valid_end) {
	$sql .= " AND da.date_valid <= '".$db->idate($search_date_valid_end)."'";
}
if ($search_multicurrency_code != '') {
	$sql .= " AND da.multicurrency_code = '".$db->escape($search_multicurrency_code)."'";
}
if ($search_project_ref != '') {
	$sql .= natural_search("p.ref", $search_project_ref);
}
if ($search_tier) {
	$sql .= natural_search('s.nom', $search_tier);
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
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);
	if (($page * $limit) > $nbtotalofrecords) {	// if total resultset is smaller then paging size (filtering), goto and load page 0
		$page = 0;
		$offset = 0;
	}
}

$sql .= $db->plimit($limit + 1, $offset);
//print $sql;

$resql = $db->query($sql);
if ($resql) {
	$num = $db->num_rows($resql);

	$arrayofselected = is_array($toselect) ? $toselect : array();

	if ($num == 1 && !empty($conf->global->MAIN_SEARCH_DIRECT_OPEN_IF_ONLY_ONE) && $sall) {
		$obj = $db->fetch_object($resql);
		$id = $obj->rowid;
		header("Location: ".DOL_URL_ROOT.'/fourn/demande/card.php?id='.$id);
		exit;
	}

	llxHeader('', $title, $help_url);

	$param = '';
	if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
		$param .= '&contextpage='.urlencode($contextpage);
	}
	if ($limit > 0 && $limit != $conf->liste_limit) {
		$param .= '&limit='.urlencode($limit);
	}
	if ($search_date_creation_startday) {
		$param .= '&search_date_creation_startday='.urlencode($search_date_creation_startday);
	}
	if ($search_date_creation_startmonth) {
		$param .= '&search_date_creation_startmonth='.urlencode($search_date_creation_startmonth);
	}
	if ($search_date_creation_startyear) {
		$param .= '&search_date_creation_startyear='.urlencode($search_date_creation_startyear);
	}
	if ($search_date_creation_endday) {
		$param .= '&search_date_creation_endday='.urlencode($search_date_creation_endday);
	}
	if ($search_date_creation_endmonth) {
		$param .= '&search_date_creation_endmonth='.urlencode($search_date_creation_endmonth);
	}
	if ($search_date_creation_endyear) {
		$param .= '&search_date_creation_endyear='.urlencode($search_date_creation_endyear);
	}
	if ($search_date_delivery_startday) {
		$param .= '&search_date_delivery_startday='.urlencode($search_date_delivery_startday);
	}
	if ($search_date_delivery_startmonth) {
		$param .= '&search_date_delivery_startmonth='.urlencode($search_date_delivery_startmonth);
	}
	if ($search_date_delivery_startyear) {
		$param .= '&search_date_delivery_startyear='.urlencode($search_date_delivery_startyear);
	}
	if ($search_date_delivery_endday) {
		$param .= '&search_date_delivery_endday='.urlencode($search_date_delivery_endday);
	}
	if ($search_date_delivery_endmonth) {
		$param .= '&search_date_delivery_endmonth='.urlencode($search_date_delivery_endmonth);
	}
	if ($search_date_delivery_endyear) {
		$param .= '&search_date_delivery_endyear='.urlencode($search_date_delivery_endyear);
	}
	if ($search_date_valid_startday) {
		$param .= '&search_date_valid_startday='.urlencode($search_date_valid_startday);
	}
	if ($search_date_valid_startmonth) {
		$param .= '&search_date_valid_startmonth='.urlencode($search_date_valid_startmonth);
	}
	if ($search_date_valid_startyear) {
		$param .= '&search_date_valid_startyear='.urlencode($search_date_valid_startyear);
	}
	if ($search_date_valid_endday) {
		$param .= '&search_date_valid_endday='.urlencode($search_date_valid_endday);
	}
	if ($search_date_valid_endmonth) {
		$param .= '&search_date_valid_endmonth='.urlencode($search_date_valid_endmonth);
	}
	if ($search_date_valid_endyear) {
		$param .= '&search_date_valid_endyear='.urlencode($search_date_valid_endyear);
	}
	if ($search_ref) {
		$param .= '&search_ref='.urlencode($search_ref);
	}
	if ($search_user > 0) {
		$param .= '&search_user='.urlencode($search_user);
	}
	if ($search_multicurrency_code != '') {
		$param .= '&search_multicurrency_code='.urlencode($search_multicurrency_code);
	}
	if ($search_status != '' && $search_status != '-1') {
		$param .= "&search_status=".urlencode($search_status);
	}
	if ($search_project_ref >= 0) {
		$param .= "&search_project_ref=".urlencode($search_project_ref);
	}
	if ($search_tier) {
		$param .= '&search_tier='.urlencode($search_tier);
	}

	// Add $param from extra fields
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object); // Note that $action and $object may have been modified by hook
	$param .= $hookmanager->resPrint;

	// // List of mass actions available
	// $arrayofmassactions = array(
	// 	'generate_doc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("ReGeneratePDF"),
	// 	'builddoc'=>img_picto('', 'pdf', 'class="pictofixedwidth"').$langs->trans("PDFMerge"),
	// 	'presend'=>img_picto('', 'email', 'class="pictofixedwidth"').$langs->trans("SendByMail"),
	// );

	// if ($permissiontovalidate) {
	// 	if ($permissiontoapprove && empty($conf->global->SUPPLIER_ORDER_NO_DIRECT_APPROVE)) {
	// 		$arrayofmassactions['prevalidate'] = img_picto('', 'check', 'class="pictofixedwidth"').$langs->trans("ValidateAndApprove");
	// 	} else {
	// 		$arrayofmassactions['prevalidate'] = img_picto('', 'check', 'class="pictofixedwidth"').$langs->trans("Validate");
	// 	}
	// }

	// if ($user->rights->fournisseur->facture->creer || $user->rights->supplier_invoice->creer) {
	// 	$arrayofmassactions['createbills'] = img_picto('', 'bill', 'class="pictofixedwidth"').$langs->trans("CreateInvoiceForThisSupplier");
	// }
	// if ($permissiontodelete) {
	// 	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
	// }
	// if (in_array($massaction, array('presend', 'predelete', 'createbills'))) {
	// }
	$arrayofmassactions = array();
	$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

	$url = DOL_URL_ROOT.'/fourn/demande/card.php?action=create';
	$newcardbutton = dolGetButtonTitle($langs->trans('NewSupplierOrderShort'), '', 'fa fa-plus-circle', $url, '', $permissiontoadd);

	// Lines of title fields
	print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
	if ($optioncss != '') {
		print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	}
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
	print '<input type="hidden" name="socid" value="'.$socid.'">';

	print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'supplier_order', 0, $newcardbutton, '', $limit, 0, 0, 1);

	$topicmail = "SendOrderRef";
	$modelmail = "order_supplier_send";
	$objecttmp = new CommandeFournisseur($db);
	$trackid = 'sord'.$object->id;
	include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

	// if ($massaction == 'prevalidate') {
	// 	print $form->formconfirm($_SERVER["PHP_SELF"].$fieldstosearchall, $langs->trans("ConfirmMassValidation"), $langs->trans("ConfirmMassValidationQuestion"), "validate", null, '', 0, 200, 500, 1);
	// }

	// if ($massaction == 'createbills') {
	// 	//var_dump($_REQUEST);
	// 	print '<input type="hidden" name="massaction" value="confirm_createsupplierbills">';

	// 	print '<table class="noborder" width="100%" >';
	// 	print '<tr>';
	// 	print '<td class="titlefield">';
	// 	print $langs->trans('DateInvoice');
	// 	print '</td>';
	// 	print '<td>';
	// 	print $form->selectDate('', '', '', '', '', '', 1, 1);
	// 	print '</td>';
	// 	print '</tr>';
	// 	print '<tr>';
	// 	print '<td>';
	// 	print $langs->trans('CreateOneBillByThird');
	// 	print '</td>';
	// 	print '<td>';
	// 	print $form->selectyesno('createbills_onebythird', '', 1);
	// 	print '</td>';
	// 	print '</tr>';
	// 	print '<tr>';
	// 	print '<td>';
	// 	print $langs->trans('ValidateInvoices');
	// 	print '</td>';
	// 	print '<td>';
	// 	print $form->selectyesno('validate_invoices', 1, 1);
	// 	print '</td>';
	// 	print '</tr>';
	// 	print '</table>';

	// 	print '<br>';
	// 	print '<div class="center">';
	// 	print '<input type="submit" class="button" id="createbills" name="createbills" value="'.$langs->trans('CreateInvoiceForThisCustomer').'">  ';
	// 	print '<input type="submit" class="button button-cancel" id="cancel" name="cancel" value="'.$langs->trans("Cancel").'">';
	// 	print '</div>';
	// 	print '<br>';
	// }


	$moreforfilter = '';

	// If the user can view prospects other than his'
	// if ($user->rights->user->user->lire) {
	// 	$langs->load("commercial");
	// 	$moreforfilter .= '<div class="divsearchfield">';
	// 	$tmptitle = $langs->trans('ThirdPartiesOfSaleRepresentative');
	// 	$moreforfilter .= img_picto($tmptitle, 'user', 'class="pictofixedwidth"').$formother->select_salesrepresentatives($search_sale, 'search_sale', $user, 0, $tmptitle, 'maxwidth250 widthcentpercentminusx');
	// 	$moreforfilter .= '</div>';
	// }
	// // If the user can view other users
	// if ($user->rights->user->user->lire) {
	// 	$moreforfilter .= '<div class="divsearchfield">';
	// 	$tmptitle = $langs->trans('LinkedToSpecificUsers');
	// 	$moreforfilter .= img_picto($tmptitle, 'user', 'class="pictofixedwidth"').$form->select_dolusers($search_user, 'search_user', $tmptitle, '', 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth250 widthcentpercentminusx');
	// 	$moreforfilter .= '</div>';
	// }
	// // If the user can view prospects other than his'
	// if (!empty($conf->categorie->enabled) && $user->rights->categorie->lire && ($user->rights->produit->lire || $user->rights->service->lire)) {
	// 	include_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
	// 	$moreforfilter .= '<div class="divsearchfield">';
	// 	$tmptitle = $langs->trans('IncludingProductWithTag');
	// 	$cate_arbo = $form->select_all_categories(Categorie::TYPE_PRODUCT, null, 'parent', null, null, 1);
	// 	$moreforfilter .= img_picto($tmptitle, 'category', 'class="pictofixedwidth"').$form->selectarray('search_product_category', $cate_arbo, $search_product_category, $tmptitle, 0, 0, '', 0, 0, 0, 0, 'maxwidth300 widthcentpercentminusx', 1);
	// 	$moreforfilter .= '</div>';
	// }
	$parameters = array();
	$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters); // Note that $action and $object may have been modified by hook


	$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
	$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage); // This also change content of $arrayfields
	$selectedfields .= $form->showCheckAddButtons('checkforselect', 1);

	if (GETPOST('autoselectall', 'int')) {
		$selectedfields .= '<script>';
		$selectedfields .= '   $(document).ready(function() {';
		$selectedfields .= '        console.log("Autoclick on checkforselects");';
		$selectedfields .= '   		$("#checkforselects").click();';
		$selectedfields .= '        $("#massaction").val("createbills").change();';
		$selectedfields .= '   });';
		$selectedfields .= '</script>';
	}

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

	print '<tr class="liste_titre_filter">';
	// Ref
	if (!empty($arrayfields['da.ref']['checked'])) {
		print '<td class="liste_titre"><input size="8" type="text" class="flat maxwidth75" name="search_ref" value="'.$search_ref.'"></td>';
	}
	// Tier
	if (!empty($arrayfields['da.fk_soc']['checked'])) {
		print '<td class="liste_titre"><input size="8" type="text" class="flat maxwidth75" name="search_tier" value="'.$search_tier.'"></td>';
	}
	// Project ref
	if (!empty($arrayfields['project_ref']['checked'])) {
		print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_project_ref" value="'.$search_project_ref.'"></td>';
	}
	// Date creation
	if (!empty($arrayfields['da.date_creation']['checked'])) {
		print '<td class="liste_titre center">';
		print '<div class="nowrap">';
		print $form->selectDate($search_date_creation_start ? $search_date_creation_start : -1, 'search_date_creation_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		print '</div>';
		print '<div class="nowrap">';
		print $form->selectDate($search_date_creation_end ? $search_date_creation_end : -1, 'search_date_creation_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		print '</div>';
		print '</td>';
	}
	
	// Date validation
	if (!empty($arrayfields['da.date_valid']['checked'])) {
		print '<td class="liste_titre center">';
		print '<div class="nowrap">';
		print $form->selectDate($search_date_valid_start ? $search_date_valid_start : -1, 'search_date_valid_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		print '</div>';
		print '<div class="nowrap">';
		print $form->selectDate($search_date_valid_end ? $search_date_valid_end : -1, 'search_date_valid_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		print '</div>';
		print '</td>';
	}
	
	// Date delivery
	if (!empty($arrayfields['da.date_livraison']['checked'])) {
		print '<td class="liste_titre center">';
		print '<div class="nowrap">';
		print $form->selectDate($search_date_delivery_start ? $search_date_delivery_start : -1, 'search_date_delivery_start', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		print '</div>';
		print '<div class="nowrap">';
		print $form->selectDate($search_date_delivery_end ? $search_date_delivery_end : -1, 'search_date_delivery_end', 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		print '</div>';
		print '</td>';
	}
	if (!empty($arrayfields['da.multicurrency_code']['checked'])) {
		// Currency
		print '<td class="liste_titre">';
		print $form->selectMultiCurrency($search_multicurrency_code, 'search_multicurrency_code', 1);
		print '</td>';
	}
	// Status
	if (!empty($arrayfields['da.statut']['checked'])) {
		print '<td class="liste_titre right">';
		$formorder->selectdemandeAchatStatus($search_status, 1, 'search_status');
		print '</td>';
	}
	// Action column
	print '<td class="liste_titre middle">';
	$searchpicto = $form->showFilterButtons();
	print $searchpicto;
	print '</td>';

	print "</tr>\n";

	// Fields title
	print '<tr class="liste_titre">';
	if (!empty($arrayfields['da.ref']['checked'])) {
		print_liste_field_titre($arrayfields['da.ref']['label'], $_SERVER["PHP_SELF"], "da.ref", "", $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['da.fk_soc']['checked'])) {
		print_liste_field_titre($arrayfields['da.fk_soc']['label'], $_SERVER["PHP_SELF"], "da.fk_soc", "", $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['project_ref']['checked'])) {
		print_liste_field_titre($arrayfields['project_ref']['label'], $_SERVER["PHP_SELF"], "project_ref", "", $param, '', $sortfield, $sortorder, 'tdoverflowmax100imp ');
	}
	if (!empty($arrayfields['da.date_creation']['checked'])) {
		print_liste_field_titre($arrayfields['da.date_creation']['label'], $_SERVER["PHP_SELF"], "da.date_creation", "", $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!empty($arrayfields['da.date_valid']['checked'])) {
		print_liste_field_titre($arrayfields['da.date_valid']['label'], $_SERVER["PHP_SELF"], "da.date_valid", "", $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!empty($arrayfields['da.date_livraison']['checked'])) {
		print_liste_field_titre($arrayfields['da.date_livraison']['label'], $_SERVER["PHP_SELF"], 'da.date_livraison', '', $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!empty($arrayfields['da.fk_author']['checked'])) {
		print_liste_field_titre($arrayfields['da.fk_author']['label'], $_SERVER["PHP_SELF"], "da.fk_author", "", $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['da.date_commande']['checked'])) {
		print_liste_field_titre($arrayfields['da.date_commande']['label'], $_SERVER["PHP_SELF"], "da.date_commande", "", $param, '', $sortfield, $sortorder, 'center ');
	}
	if (!empty($arrayfields['da.multicurrency_code']['checked'])) {
		print_liste_field_titre($arrayfields['da.multicurrency_code']['label'], $_SERVER['PHP_SELF'], 'da.multicurrency_code', '', $param, '', $sortfield, $sortorder);
	}
	if (!empty($arrayfields['da.statut']['checked'])) {
		print_liste_field_titre($arrayfields['da.statut']['label'], $_SERVER["PHP_SELF"], "da.statut", "", $param, '', $sortfield, $sortorder, 'right ');
	}
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"], "", '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ');
	print "</tr>\n";

	$projectstatic = new Project($db);

	$i = 0;

	$imaxinloop = ($limit ? min($num, $limit) : $num);
	while ($i < $imaxinloop) {
		$obj = $db->fetch_object($resql);

		$notshippable = 0;
		$warning = 0;
		$text_info = '';
		$text_warning = '';
		$nbprod = 0;

		print '<tr class="oddeven">';
		if ($obj->statut == 0){
			$badge = 'badge-status1';
			$titreDemande = 'Brouillon';
			$titreDemandeShow = 'Brouillon (à valider)';
		}else if ($obj->statut == 1){
			$badge = 'badge-status4';
			$titreDemande = 'Validé';
			$titreDemandeShow = 'Validé';
		}else{
			$badge = 'badge-status8';
			$titreDemande = 'Refusé';
			$titreDemandeShow = 'Refusé';
		}

		// Ref
		if (!empty($arrayfields['da.ref']['checked'])) {
			print '<td class="nowrap">';

			// Picto + Ref
			print "<a href='/fourn/demande/card.php?id=$obj->id&action=view' title='<u class=&quot;paddingrightonly&quot;>Demande Achat</u> <span class=&quot;badge $badge badge-status&quot; title=&quot;$titreDemande&quot;>$titreDemandeShow</span><br><b>Réf.:</b> $obj->ref<br><b>Date de livraison:</b> $obj->date_livraison' class='classfortooltip'><span class='fas fa-dol-order_supplier infobox-order_supplier paddingright classfortooltip'></span>$obj->ref</a>";

			print '</td>'."\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		// Thirdparty
		if (!empty($arrayfields['da.fk_soc']['checked'])) {
			print '<td class="tdoverflowmax150">';
			if ($obj->socid  > 0){
				$thirdpartytmp->id = $obj->socid;
				$thirdpartytmp->name = $obj->nom;
				$thirdpartytmp->email = $obj->email;
				print $thirdpartytmp->getNomUrl(1, 'supplier');
			}
			print '</td>'."\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		
		// Project
		if (!empty($arrayfields['project_ref']['checked'])) {
			$projectstatic->id = $obj->project_id;
			$projectstatic->ref = $obj->project_ref;
			$projectstatic->title = $obj->project_title;
			print '<td>';
			if ($obj->project_id > 0) {
				print $projectstatic->getNomUrl(1);
			}
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		// creation date
		if (!empty($arrayfields['da.date_creation']['checked'])) {
			print '<td class="center">';
			print "$obj->date_demande";
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		// validation date
		if (!empty($arrayfields['da.date_creation']['checked'])) {
			print '<td class="center">';
			print "$obj->date_valid";
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		// delivery date
		if (!empty($arrayfields['da.date_livraison']['checked'])) {
			print '<td class="center">';
			print "$obj->date_livraison";
			print '</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}
		// Currency
		if (!empty($arrayfields['da.multicurrency_code']['checked'])) {
			print '<td class="nowrap">'.$obj->multicurrency_code.' - '.$langs->trans('Currency'.$obj->multicurrency_code)."</td>\n";
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Status
		if (!empty($arrayfields['da.statut']['checked'])) {
			if ($obj->statut == '0'){
				$etat = 'Brouillon';
			}else if ($obj->statut == '1'){
				$etat = "Validé";
			}else{
				$etat = 'Refusé';
			}
			print '<td class="right nowrap">'.$etat.'</td>';
			if (!$i) {
				$totalarray['nbfield']++;
			}
		}

		// Action column
		print '<td class="nowrap center">';
		if ($massactionbutton || $massaction) {   // If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
			$selected = 0;
			if (in_array($obj->rowid, $arrayofselected)) {
				$selected = 1;
			}
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected ? ' checked="checked"' : '').'>';
		}
		print '</td>';

		print "</tr>\n";

		$i++;
	}


	// If no record found
	if ($num == 0) {
		$colspan = 1;
		foreach ($arrayfields as $key => $val) {
			if (!empty($val['checked'])) {
				$colspan++;
			}
		}
		print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
	}

	$db->free($resql);

	$parameters = array('arrayfields'=>$arrayfields, 'sql'=>$sql);
	$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters); // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	print '</table>'."\n";
	print '</div>';

	print '</form>'."\n";

	// $hidegeneratedfilelistifempty = 1;
	// if ($massaction == 'builddoc' || $action == 'remove_file' || $show_files) {
	// 	$hidegeneratedfilelistifempty = 0;
	// }

	// Show list of available documents
	$urlsource = $_SERVER['PHP_SELF'].'?sortfield='.$sortfield.'&sortorder='.$sortorder;
	$urlsource .= str_replace('&amp;', '&', $param);

	$filedir = $diroutputmassaction;
	$genallowed = $permissiontoread;
	$delallowed = $permissiontoadd;

	// print $formfile->showdocuments('massfilesarea_supplier_order', '', $filedir, $urlsource, 0, $delallowed, '', 1, 1, 0, 48, 1, $param, $title, '', '', '', null, $hidegeneratedfilelistifempty);
} else {
	dol_print_error($db);
}

// End of page
llxFooter();
$db->close();
