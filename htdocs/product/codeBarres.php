<?php
/* Copyright (C) 2001-2007	Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016	Laurent Destailleur	 <eldy@users.sourceforge.net>
 * Copyright (C) 2005		Eric Seigne		     <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2015	Regis Houssin		 <regis.houssin@capnetworks.com>
 * Copyright (C) 2006		Andre Cianfarani	 <acianfa@free.fr>
 * Copyright (C) 2006		Auguria SARL		 <info@auguria.org>
 * Copyright (C) 2010-2015	Juanjo Menent		 <jmenent@2byte.es>
 * Copyright (C) 2013-2016	Marcos García		 <marcosgdf@gmail.com>
 * Copyright (C) 2012-2013	Cédric Salvador		 <csalvador@gpcsolutions.fr>
 * Copyright (C) 2011-2020	Alexandre Spangaro	 <aspangaro@open-dsi.fr>
 * Copyright (C) 2014		Cédric Gross		 <c.gross@kreiz-it.fr>
 * Copyright (C) 2014-2015	Ferran Marcet		 <fmarcet@2byte.es>
 * Copyright (C) 2015		Jean-François Ferry	 <jfefe@aternatik.fr>
 * Copyright (C) 2015		Raphaël Doursenaud	 <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2016-2022	Charlene Benke		 <charlene@patas-monkey.com>
 * Copyright (C) 2016		Meziane Sof		     <virtualsof@yahoo.fr>
 * Copyright (C) 2017		Josep Lluís Amador	 <joseplluis@lliuretic.cat>
 * Copyright (C) 2019-2022  Frédéric France      <frederic.france@netlogic.fr>
 * Copyright (C) 2019-2020  Thibault FOUCART     <support@ptibogxiv.net>
 * Copyright (C) 2020  		Pierre Ardoin     	 <mapiolca@me.com>
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
 *  \file       htdocs/product/card.php
 *  \ingroup    product
 *  \brief      Page to show product
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/canvas.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/html.formproduct.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/genericobject.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/modules/product/modules_product.class.php';

if (!empty($conf->propal->enabled)) {
	require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
}
if (isModEnabled('facture')) {
	require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
}
if (!empty($conf->commande->enabled)) {
	require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
}
if (!empty($conf->accounting->enabled)) {
	require_once DOL_DOCUMENT_ROOT . '/core/lib/accounting.lib.php';
	require_once DOL_DOCUMENT_ROOT . '/core/class/html.formaccounting.class.php';
	require_once DOL_DOCUMENT_ROOT . '/accountancy/class/accountingaccount.class.php';
}
if (!empty($conf->bom->enabled)) {
	require_once DOL_DOCUMENT_ROOT . '/bom/class/bom.class.php';
}

// Load translation files required by the page
$langs->loadLangs(array('products', 'other'));
if (!empty($conf->stock->enabled)) {
	$langs->load("stocks");
}
if (isModEnabled('facture')) {
	$langs->load("bills");
}
if (!empty($conf->productbatch->enabled)) {
	$langs->load("productbatch");
}

$mesg = '';
$error = 0;
$errors = array();

$refalreadyexists = 0;

$id = GETPOST('id', 'int');
$id_codebareEdited = GETPOST('id_codebare', 'int');
$ref = (GETPOSTISSET('ref') ? GETPOST('ref', 'alpha') : null);
$type = (GETPOSTISSET('type') ? GETPOST('type', 'int') : Product::TYPE_PRODUCT);
$action = (GETPOST('action', 'alpha') ? GETPOST('action', 'alpha') : 'view');
$cancel = GETPOST('cancel', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$socid = GETPOST('socid', 'int');
$duration_value = GETPOST('duration_value', 'int');
$duration_unit = GETPOST('duration_unit', 'alpha');

$accountancy_code_sell = GETPOST('accountancy_code_sell', 'alpha');
$accountancy_code_sell_intra = GETPOST('accountancy_code_sell_intra', 'alpha');
$accountancy_code_sell_export = GETPOST('accountancy_code_sell_export', 'alpha');
$accountancy_code_buy = GETPOST('accountancy_code_buy', 'alpha');
$accountancy_code_buy_intra = GETPOST('accountancy_code_buy_intra', 'alpha');
$accountancy_code_buy_export = GETPOST('accountancy_code_buy_export', 'alpha');

$checkmandatory = GETPOST('accountancy_code_buy_export', 'alpha');
// by default 'alphanohtml' (better security); hidden conf MAIN_SECURITY_ALLOW_UNSECURED_LABELS_WITH_HTML allows basic html
$label_security_check = empty($conf->global->MAIN_SECURITY_ALLOW_UNSECURED_LABELS_WITH_HTML) ? 'alphanohtml' : 'restricthtml';

if (!empty($user->socid)) {
	$socid = $user->socid;
}

// Load object modCodeProduct
$module = (!empty($conf->global->PRODUCT_CODEPRODUCT_ADDON) ? $conf->global->PRODUCT_CODEPRODUCT_ADDON : 'mod_codeproduct_leopard');
if (substr($module, 0, 16) == 'mod_codeproduct_' && substr($module, -3) == 'php') {
	$module = substr($module, 0, dol_strlen($module) - 4);
}
$result = dol_include_once('/core/modules/product/' . $module . '.php');
if ($result > 0) {
	$modCodeProduct = new $module();
}

$object = new Product($db);
$object->type = $type; // so test later to fill $usercancxxx is correct
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref);
	if ($result < 0) {
		dol_print_error($db, $object->error, $object->errors);
	}
	if (!empty($conf->product->enabled)) {
		$upload_dir = $conf->product->multidir_output[$object->entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'product') . dol_sanitizeFileName($object->ref);
	} elseif (!empty($conf->service->enabled)) {
		$upload_dir = $conf->service->multidir_output[$object->entity] . '/' . get_exdir(0, 0, 0, 0, $object, 'product') . dol_sanitizeFileName($object->ref);
	}

	if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {    // For backward compatiblity, we scan also old dirs
		if (!empty($conf->product->enabled)) {
			$upload_dirold = $conf->product->multidir_output[$object->entity] . '/' . substr(substr("000" . $object->id, -2), 1, 1) . '/' . substr(substr("000" . $object->id, -2), 0, 1) . '/' . $object->id . "/photos";
		} else {
			$upload_dirold = $conf->service->multidir_output[$object->entity] . '/' . substr(substr("000" . $object->id, -2), 1, 1) . '/' . substr(substr("000" . $object->id, -2), 0, 1) . '/' . $object->id . "/photos";
		}
	}
}

$modulepart = 'product';

// Get object canvas (By default, this is not defined, so standard usage of dolibarr)
$canvas = !empty($object->canvas) ? $object->canvas : GETPOST("canvas");
$objcanvas = null;
if (!empty($canvas)) {
	require_once DOL_DOCUMENT_ROOT . '/core/class/canvas.class.php';
	$objcanvas = new Canvas($db, $action);
	$objcanvas->getCanvas('product', 'card', $canvas);
}

// Security check
$fieldvalue = (!empty($id) ? $id : (!empty($ref) ? $ref : ''));
$fieldtype = (!empty($id) ? 'rowid' : 'ref');

if ($object->id > 0) {
	if ($object->type == $object::TYPE_PRODUCT) {
		restrictedArea($user, 'produit', $object->id, 'product&product', '', '');
	}
	if ($object->type == $object::TYPE_SERVICE) {
		restrictedArea($user, 'service', $object->id, 'product&product', '', '');
	}
} else {
	restrictedArea($user, 'produit|service', 0, 'product&product', '', '', $fieldtype);
}

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('productcard', 'globalcard'));

$usercanread = (($object->type == Product::TYPE_PRODUCT && $user->rights->produit->lire) || ($object->type == Product::TYPE_SERVICE && $user->rights->service->lire));
$usercancreate = (($object->type == Product::TYPE_PRODUCT && $user->rights->produit->creer) || ($object->type == Product::TYPE_SERVICE && $user->rights->service->creer));
$usercandelete = (($object->type == Product::TYPE_PRODUCT && $user->rights->produit->supprimer) || ($object->type == Product::TYPE_SERVICE && $user->rights->service->supprimer));


/*
 * Actions
 */

if ($cancel) {
	$action = '';
}

$createbarcode = empty($conf->barcode->enabled) ? 0 : 1;
if (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && empty($user->rights->barcode->creer_advance)) {
	$createbarcode = 0;
}


// Add a product or service
if ($action == 'add') {
	$error = 0;	
	$fk_barcode_type 	= GETPOST('fk_barcode_type', 'int');
	$barcode 	= GETPOST('barcode', 'text');
	$id = GETPOST('id', 'int');

	if(GETPOSTISSET('cancelAdd')) {
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit();
	}
	
	if (!GETPOST('fk_barcode_type')) {
		setEventMessages("Le champ 'Type de code-barres' est obligatoire", null, 'errors');
		$action = "create";
		$error++;
	}
	if (!GETPOST('barcode') && !GETPOSTISSET('random')) {
		setEventMessages("Le champ 'code-barres' est obligatoire", null, 'errors');
		$action = "create";
		$error++;

		$sql = "SELECT * FROM llx_product_barrecodes WHERE barcode = '$barcode'";
		$res = $db->query($sql);
		$row = $res->fetch_array();
		if ($row ) {
			setEventMessages("barcode deja existe", null, 'errors');
			$action = "create";
			$error++;
		}
	}

	$db->begin();
	if (!$error) {	
		if (GETPOSTISSET('random')){
			// get last id
			$sql1="SELECT id FROM llx_product_barrecodes ORDER BY id DESC LIMIT 1";
			print $sql1;
			$res = $db->query($sql1);
			$result = $res->fetch_assoc();
			$last = $result['id']; 
			$barcode = strval( (int)$last +1);
		}
	
		$sql = "insert into llx_product_barrecodes (barcode,fk_product,fk_barcode_type) VALUES('$barcode', $id, $fk_barcode_type)";
		$result = $db->query($sql);
		if ($result){
			$db->commit();
			
	
			$action = '';
			header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
		}else{
			$db->rollback();
			$action = 'create';
			setEventMessages("Veuillez réessayer barcode n'est pas creé", null, 'errors');
		}

	}
	
}else if($action == 'update'){
	$error = 0;	
	$fk_barcode_type 	= GETPOST('fk_barcode_type', 'int');
	$barcode 	= GETPOST('barcode', 'text');
	$id = GETPOST('id', 'int');
	$id_codebareEdited = GETPOST('id_codebare', 'int');
	
	if(GETPOSTISSET('cancelEdit')) {
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit();
	}

	if (!GETPOST('fk_barcode_type')) {
		setEventMessages("Le champ 'Type de code-barres' est obligatoire", null, 'errors');
		$action = "create";
		$error++;
	}
	if (!GETPOST('barcode')) {
		setEventMessages("Le champ 'code-barres' est obligatoire", null, 'errors');
		$action = "create";
		$error++;
	}
	// $sql = "SELECT * FROM llx_product_barrecodes WHERE barcode = '$barcode'";
	// $res = $db->query($sql);
	// $row = $res->fetch_array();
	// if ($row ) {
	// 	setEventMessages("barcode deja existe", null, 'errors');
	// 	$action = "";
	// 	$error++;
	// }
	$db->begin();
	if (!$error) {
		
		$sql = "UPDATE llx_product_barrecodes SET barcode = '$barcode', fk_barcode_type = '$fk_barcode_type' WHERE id = '$id_codebareEdited'";
		$result = $db->query($sql);
		if ($result){
			$db->commit();
			$action = '';
			header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
		}else{
			$db->rollback();
			$action = '';
			setEventMessages("Veuillez réessayer barcode n'est pas creé", null, 'errors');
		}

	}
}else if($action == 'delete'){
	$error = 0;	
	$id_codebare = GETPOST('id_codebare', 'int');

	$db->begin();
	if (!$error) {
		
		$sql = "update llx_product_barrecodes set deleted_at = now() where id = $id_codebare";
		$result = $db->query($sql);
		if ($result){
			$db->commit();
			$action = '';
			header("Location: ".$_SERVER['PHP_SELF']."?id=".$id);
		}else{
			$db->rollback();
			$action = '';
			setEventMessages("Veuillez réessayer barcode n'est pas Supprimer", null, 'errors');
		}

	}
}







/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);
$formproduct = new FormProduct($db);
$formcompany = new FormCompany($db);
if (!empty($conf->accounting->enabled)) {
	$formaccounting = new FormAccounting($db);
}


$title = $langs->trans('ProductServiceCard');
$help_url = '';
$shortlabel = dol_trunc($object->label, 16);
if (GETPOST("type") == '0' || ($object->type == Product::TYPE_PRODUCT)) {
	$title = $langs->trans('Product') . " " . $shortlabel . " - " . $langs->trans('Card');
	$help_url = 'EN:Module_Products|FR:Module_Produits|ES:M&oacute;dulo_Productos|DE:Modul_Produkte';
}
if (GETPOST("type") == '1' || ($object->type == Product::TYPE_SERVICE)) {
	$title = $langs->trans('Service') . " " . $shortlabel . " - " . $langs->trans('Card');
	$help_url = 'EN:Module_Services_En|FR:Module_Services|ES:M&oacute;dulo_Servicios|DE:Modul_Leistungen';
}

llxHeader('', $title, $help_url);

// Load object modBarCodeProduct
$res = 0;
if (!empty($conf->barcode->enabled) && !empty($conf->global->BARCODE_PRODUCT_ADDON_NUM)) {
	$module = strtolower($conf->global->BARCODE_PRODUCT_ADDON_NUM);
	$dirbarcode = array_merge(array('/core/modules/barcode/'), $conf->modules_parts['barcode']);
	foreach ($dirbarcode as $dirroot) {
		$res = dol_include_once($dirroot . $module . '.php');
		if ($res) {
			break;
		}
	}
	if ($res > 0) {
		$modBarCodeProduct = new $module();
	}
}


if (is_object($objcanvas) && $objcanvas->displayCanvasExists($action)) {
	// -----------------------------------------
	// When used with CANVAS
	// -----------------------------------------
	if (empty($object->error) && $id) {
		$result = $object->fetch($id);
		if ($result <= 0) {
			dol_print_error('', $object->error);
		}
	}
	$objcanvas->assign_values($action, $object->id, $object->ref); // Set value for templates
	$objcanvas->display_canvas($action); // Show template
} else {
	// -----------------------------------------
	// When used in standard mode
	// -----------------------------------------
	if ($object->id > 0) {
		/*
		 * Product card
		 */


		// Fiche en mode visu


		$codeBarres = array();
		$sql = "SELECT * FROM llx_product_barrecodes WHERE fk_product = $id and deleted_at IS NULL";
		$res = $db->query($sql);
		if ($res->num_rows) {
			while ($row = $res->fetch_assoc()) {
				array_push($codeBarres, [$row['id'], $row['barcode'], $row['fk_product'], $row['fk_barcode_type']]);
			}
		}

		$codeBarresType = array();
		$sql = "SELECT rowid, libelle FROM llx_c_barcode_type";
		$res = $db->query($sql);
		if ($res->num_rows) {
			while ($row = $res->fetch_assoc()) {
				$codeBarresType[$row['rowid']] = $row['libelle'];
			}
		}



		$head = product_prepare_head($object);
		$titre = $langs->trans("CardProduct" . $object->type);
		$picto = ($object->type == Product::TYPE_SERVICE ? 'service' : 'product');

		print dol_get_fiche_head($head, 'codeBarres', $titre, -1, $picto);

		$linkback = '<a href="' . DOL_URL_ROOT . '/product/list.php?restore_lastsearch_values=1&type=' . $object->type . '">' . $langs->trans("BackToList") . '</a>';
		$object->next_prev_filter = " fk_product_type = " . $object->type;

		$shownav = 1;
		if ($user->socid && !in_array('product', explode(',', $conf->global->MAIN_MODULES_FOR_EXTERNAL))) {
			$shownav = 0;
		}

		dol_banner_tab($object, 'ref', $linkback, $shownav, 'ref');

		if($action == "view"){
			print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=create" method="POST" style="margin-top: 20px;float: right;">' . "\n";
			print '<input type="hidden" name="action" value="create">';
			print '<button type="submit" class="btnAdd"><i class="fas fa-plus"></i></button>';
			print '</form>';

		}

		print '<div class="fichecenter">';

		print '<table class="border allwidth">';
		print "<tr>
				<th>" . $langs->trans('BarcodeType') . "</th>
				<th>" . $langs->trans("BarcodeValue") . " </th>
				<th>Action</th>
			</tr>";
		foreach ($codeBarres as $key => $value) {

			print '<tr>';
			if($id_codebareEdited == $value[0]){
				
				
				// Main official, simple, and not duplicated code
				print '<form id="frmEdit" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" method="POST" style="margin-top: 74px;" name="formprod">';
				print '<input type="hidden" name="token" value="' . newToken() . '">';
				print '<input type="hidden" name="id" value="' . $object->id . '">';
				print '<input type="hidden" name="id_codebare" value="' . $value[0] . '">';
				print '<input type="hidden" id="action" name="action" value="update">';

				print '<td>';
				if (GETPOSTISSET('fk_barcode_type')) {
					$fk_barcode_type = GETPOST('fk_barcode_type') ? GETPOST('fk_barcode_type') : 0;
				} else {
					if (empty($fk_barcode_type) && !empty($conf->global->PRODUIT_DEFAULT_BARCODE_TYPE)) {
						$fk_barcode_type = getDolGlobalInt("PRODUIT_DEFAULT_BARCODE_TYPE");
					} else {
						$fk_barcode_type = 0;
					}
				}
				require_once DOL_DOCUMENT_ROOT . '/core/class/html.formbarcode.class.php';
				$formbarcode = new FormBarCode($db);
				print $formbarcode->selectBarcodeType($fk_barcode_type, 'fk_barcode_type', 1);
				print '</td>';
				print '<td><input class="maxwidth150   " type="text" name="barcode" value="'.$value[1].'"></td>';

				print '
					<td>
						<button type="submit" class="btnEdit" ><i class="fas fa-edit"></i></button>
						<button type="submit" class="btnCancel" name="cancelEdit"><i class="fas fa-ban"></i></button>
					</td>
					</form>';
			}else{
				print '<form id="frmAction'.$value[0].'" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&id_codebare=' . $value[0] . '" method="POST" style="margin-top: 74px;" name="formprod">';
				print '<input type="hidden" name="token" value="' . newToken() . '">';
				print '<input type="hidden" name="id_product" value="' . $object->id . '">';
				print '<input type="hidden" name="id_codebare" value="' . $value[0] . '">';
				print '<input id="action'.$value[0].'" type="hidden" name="action">';
				print '<td>'.$value[3].'</td>';
				print '<td>'.$value[1].'</td>';
				print '<td><button type="button" class="btnDelete" onclick="deleteBareCode(\''.$value[0].'\', \''.$value[1].'\')"><i class="fas fa-trash"></i></button>
				<button id="showedit" type="button" class="btnEdit" value="Modifier" onclick="editBareCode(\''.$value[0].'\')"><i class="fa fa-pencil"></i></button></td></form>';
			}
		}
		print '</tr>';

		// print '</div>';
		// print '</table>';

		// Fiche en mode edition
		if ($action == 'create' && $usercancreate) {
			//WYSIWYG Editor

			// require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';


			$type = $langs->trans('Product');
			if ($object->isService()) {
				$type = $langs->trans('Service');
			}
			//print load_fiche_titre($langs->trans('Modify').' '.$type.' : '.(is_object($object->oldcopy)?$object->oldcopy->ref:$object->ref), "");

			// Main official, simple, and not duplicated code
			print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" method="POST" style="margin-top: 74px;" name="formprod">' . "\n";
			print '<input type="hidden" name="token" value="' . newToken() . '">';
			print '<input type="hidden" name="id_product" value="' . $object->id . '">';
			print '<input type="hidden" name="action" value="add">';


			print '<tr><td>';
		if (GETPOSTISSET('fk_barcode_type')) {
			$fk_barcode_type = GETPOST('fk_barcode_type') ? GETPOST('fk_barcode_type') : 0;
		} else {
			if (empty($fk_barcode_type) && !empty($conf->global->PRODUIT_DEFAULT_BARCODE_TYPE)) {
				$fk_barcode_type = getDolGlobalInt("PRODUIT_DEFAULT_BARCODE_TYPE");
			} else {
				$fk_barcode_type = 0;
			}
		}
		require_once DOL_DOCUMENT_ROOT . '/core/class/html.formbarcode.class.php';
		$formbarcode = new FormBarCode($db);
		print $formbarcode->selectBarcodeType($fk_barcode_type, 'fk_barcode_type', 1);
		print '</td>';
		print '</td>';
		print '<td><input class="maxwidth150" type="text" name="barcode" value=""></td>';
		print '<td>
				<button type="submit" class="btnGenerate" name="random"><i class="fas fa-random"></i></button>
				<button type="submit" class="btnAdd add-green"><i class="fas fa-plus"></i></button>
				<button type="submit" class="btnCancel" name="cancelAdd"><i class="fas fa-ban"></i></button>

				</td>';
		print '</tr>';

		print '</div>';
		print '</table>';


		print '</form>';
		}

		print dol_get_fiche_end();
	}
}


print '
<div id="overflow"></div>

<div id="deleteConfirmation">
    <input type="hidden" id="deletedCodeBare"/>
	<div class="confirmation-title">
		<h2></h2>
	</div>
	<div class="confirmation-footer">
		<button class="confirm">Supprimer</button>
		<button class="cancel">Cancel</button>
	</div>
</div>';



print "
	<style>
	tr{
		text-align: center;
	}
	tr:nth-of-type(even) {
		background: #eeeeee;
	}
	tr:nth-of-type(odd) {
		background: #f7f7f7;
	}
	  
	th {
		background-color: #708090;
		color: white;
		font-weight: bold;
		padding: 0.5em 0.375em;
		text-align: center;
		border: 1px solid #aaa;
	}
	td{
		border-right: 1px solid #d5cccc7d;
		border-bottom: none;
		padding-top: 0;
		padding-top: 0px !important;
		border-bottom: none !important;
		height: auto;
	}



	.btnDelete{
		color: #fff;
		padding: 4px 6px;
		cursor: pointer;
		background-color: transparent;
		border: none;
		font-size: 15px;
	}
	.btnDelete i{
		color: #bd686d !important;
	}
	.btnCancel{
		color: #fff;
		padding: 4px 6px;
		cursor: pointer;
		background-color: transparent;
		border: none;
		font-size: 15px;
	}
	.btnCancel i{
		color: #bd686d !important;
	}
	.btnAdd{
		color: #fff;
		padding: 4px 6px;
		cursor: pointer;
		background-color: transparent;
		border: none;
		font-size: 28px;
	}
	.btnAdd i{
		color: #6b8190 !important;
		font-size: 21px;
	}
	.btnAdd.add-green i{
		color: #7ed972 !important;
		font-size: 18px;
	}
	.btnEdit{
		color: #53b360;
		padding: 4px 6px;
		cursor: pointer;
		background-color: transparent;
		border: none;
		font-size: 15px;
	}
	.btnEdit i{
		color: #7ed972;
	}
	.btnGenerate{
		color: #53b360;
		padding: 4px 6px;
		cursor: pointer;
		background-color: transparent;
		border: none;
		font-size: 15px;
	}
	.btnGenerate i{
		color: #d5b134;
	}




	#deleteConfirmation{
		padding: 18px;
		border-radius: 8px;
		height: 120px;
		background-color: #e7e7e7;
		position: fixed;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		display: none;
		flex-direction: column;
		justify-content: space-between;
	}
	#deleteConfirmation button{
		border: 0;
		border-radius: 0.25em;
		background: initial;
		color: #fff;
		font-size: 16px;
		padding: 9px;
		cursor: pointer;
	}
	#deleteConfirmation button.confirm{
		background-color: #548734;
	}
	#deleteConfirmation button.cancel{
		background-color: #dc3741;
	}
	#deleteConfirmation .confirmation-footer{
		display: flex;
		justify-content: space-evenly;
	}
	#overflow {
		display: none;
		position: fixed;
		top: 0;
		left: 0;
		height: 100%;
		width: 100%;
		background-color: #2e2e2ebd;
		overflow: unset;
	}


	</style>";

	// print '<script>
	// $(document).ready(function(){
	//    $("#showedit").click(function(){
	// 		$("#action").val("edit");

	// 		$("#frmAction").submit();

	// 	});
	//    $("#showDelete").click(function(){
	// 		$("#action").val("delete");
	// 		$("#frmAction").submit();
	// 	});
	// })
	// </script>';


?>
<script>
    function deleteBareCode(id, designation) {
        $('html, body').css({
            overflow: 'hidden',
        });
        $("#overflow").css("display", "block");
        $("#deleteConfirmation").css("display", "flex");
        $("#deleteConfirmation .confirmation-title").html("<h2 style='font-size: 18px;'>Voulez-vous supprimer Code Barre '" + designation + "'</h2>");
		$("#deletedCodeBare").val(id);
	}
    // cancel supprission
    $("#deleteConfirmation").on('click', ".cancel", function() {
		$("#deleteConfirmation").css("display", "none");
        $("#overflow").css("display", "none");
        $('html, body').css({
			overflow: 'auto',
        });
    });
	
    // confirm supprission
    $("#deleteConfirmation").on('click', ".confirm", function() {
		let id = $("#deletedCodeBare").val();

		$("#action"+id).val("delete");
		$("#frmAction"+id).submit();
    });


	// edit code bare
	function editBareCode(id) {
		$("#action"+id).val("edit");

		$("#frmAction"+id).submit();

	};
	// cancel edit code bare
	function cancelEdit() {
		window.location.href = 'product/codeBarres.php?id=215';
	}

</script>
<?php

// End of page
llxFooter();
$db->close();

?>

