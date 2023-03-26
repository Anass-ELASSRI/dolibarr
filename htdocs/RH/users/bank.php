<?php
/* Copyright (C) 2002-2004  Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003       Jean-Louis Bergamo   <jlb@j1b.org>
 * Copyright (C) 2004-2015  Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009  Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2013       Peter Fontaine       <contact@peterfontaine.fr>
 * Copyright (C) 2015-2016  Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2015       Alexandre Spangaro   <aspangaro@open-dsi.fr>
 * Copyright (C) 2021       Gauthier VERDOL      <gauthier.verdol@atm-consulting.fr>
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
 *	    \file       htdocs/user/bank.php
 *      \ingroup    HRM
 *		\brief      Tab for HRM
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/userbankaccount.class.php';
if (!empty($conf->holiday->enabled)) {
	require_once DOL_DOCUMENT_ROOT . '/holiday/class/holiday.class.php';
}
if (!empty($conf->expensereport->enabled)) {
	require_once DOL_DOCUMENT_ROOT . '/expensereport/class/expensereport.class.php';
}
if (!empty($conf->salaries->enabled)) {
	require_once DOL_DOCUMENT_ROOT . '/salaries/class/salary.class.php';
	require_once DOL_DOCUMENT_ROOT . '/salaries/class/paymentsalary.class.php';
}

// Load translation files required by page
$langs->loadLangs(array('companies', 'commercial', 'banks', 'bills', 'trips', 'holiday', 'salaries'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alphanohtml');
$bankid = GETPOST('bankid', 'int');
$action = GETPOST("action", 'alpha');
$cancel = GETPOST('cancel', 'alpha');

// Security check
$socid = 0;
if ($user->socid > 0) {
	$socid = $user->socid;
}
$feature2 = (($socid && $user->rights->user->self->creer) ? '' : 'user');

$object = new User($db);
if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref, '', 1);
	$object->getrights();
}

$account = new UserBankAccount($db);
if (!$bankid) {
	$account->fetch(0, '', $id);
} else {
	$account->fetch($bankid);
}
if (empty($account->userid)) {
	$account->userid = $object->id;
}

//Get salary parameters 
$sql = "SELECT * from llx_Paie_UserInfo WHERE userid=" . $object->id;
$res = $db->query($sql);
if ($res->num_rows > 0) {
	$salaireInfo = $res->fetch_assoc();
}


// Define value to know what current user can do on users
$canadduser = (!empty($user->admin) || $user->rights->user->user->creer || $user->rights->hrm->write_personal_information->write);
$permissiontoaddbankaccount = (!empty($user->rights->salaries->write) || !empty($user->rights->hrm->employee->write));

// Ok if user->rights->salaries->read or user->rights->hrm->read
//$result = restrictedArea($user, 'salaries|hrm', $object->id, 'user&user', $feature2);
$ok = false;
if ($user->id == $id) {
	$ok = true; // A user can always read its own card
}
if (!empty($user->rights->salaries->read)) {
	$ok = true;
}
if (!empty($user->rights->hrm->read)) {
	$ok = true;
}
if (!empty($user->rights->expensereport->lire) && ($user->id == $object->id || $user->rights->expensereport->readall)) {
	$ok = true;
}
if (!$ok) {
	accessforbidden();
}


/*
 *	Actions
 */

if ($action == 'add' && !$cancel) {
	$account->userid          = $object->id;

	$account->bank            = GETPOST('bank', 'alpha');
	$account->label           = GETPOST('label', 'alpha');
	$account->courant         = GETPOST('courant', 'alpha');
	$account->code_banque     = GETPOST('code_banque', 'alpha');
	$account->code_guichet    = GETPOST('code_guichet', 'alpha');
	$account->number          = GETPOST('number', 'alpha');
	$account->cle_rib         = GETPOST('cle_rib', 'alpha');
	$account->bic             = GETPOST('bic', 'alpha');
	$account->iban            = GETPOST('iban', 'alpha');
	$account->domiciliation   = GETPOST('domiciliation', 'alpha');
	$account->proprio         = GETPOST('proprio', 'alpha');
	$account->owner_address   = GETPOST('owner_address', 'alpha');

	$result = $account->create($user);

	if (!$result) {
		setEventMessages($account->error, $account->errors, 'errors');
		$action = 'edit'; // Force chargement page edition
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		$action = '';
	}
}

if ($action == 'update' && !$cancel) {
	$account->userid = $object->id;

	$account->bank            = GETPOST('bank', 'alpha');
	$account->label           = GETPOST('label', 'alpha');
	$account->courant         = GETPOST('courant', 'alpha');
	$account->code_banque     = GETPOST('code_banque', 'alpha');
	$account->code_guichet    = GETPOST('code_guichet', 'alpha');
	$account->number          = GETPOST('number', 'alpha');
	$account->cle_rib         = GETPOST('cle_rib', 'alpha');
	$account->bic             = GETPOST('bic', 'alpha');
	$account->iban            = GETPOST('iban', 'alpha');
	$account->domiciliation   = GETPOST('domiciliation', 'alpha');
	$account->proprio         = GETPOST('proprio', 'alpha');
	$account->owner_address   = GETPOST('owner_address', 'alpha');

	$result = $account->update($user);

	if (!$result) {
		setEventMessages($account->error, $account->errors, 'errors');
		$action = 'edit'; // Force chargement page edition
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		$action = '';
	}
}

if ($action == 'updateSalary' && !$cancel) {
	$error = 0;
	$mode_paie = GETPOST('modePaiement', 'alpha');
	$type = $db->escape(GETPOST('type', 'alpha'));
	$cnss = $db->escape(GETPOST('cnss', 'int'));
	$mutuelle = $db->escape(GETPOST('mutuelle', 'alpha'));
	$cimr = $db->escape(GETPOST('cimr', 'alpha'));
	// $transport = (float)GETPOST('transport', 'float');
	// $representation = (GETPOST('representation', 'float') == 'on') ? 1 : 0;
	// $nonConcurrence = (float)GETPOST('nonConcurrence', 'float');

	$salaire = str_replace(',', '.', $db->escape(GETPOST('salaire', 'alpha')));
	$thm = str_replace(',', '.', $db->escape(GETPOST('thm', 'alpha')));
	$tjm = str_replace(',', '.', $db->escape(GETPOST('tjm', 'alpha')));

	//update user salire and tarifs
	$sql = "UPDATE llx_user Set salary='$salaire', thm='$thm', tjm='$tjm' where rowid = $object->id";
	$res = $db->query($sql);
	if ($res);
	else $error + 1;


	$sql = "REPLACE into llx_Paie_UserInfo(userid, cnss, mutuelle, cimr, type, mode_paiement) values ($object->id, '$cnss','$mutuelle','$cimr', '$type', '$mode_paie');";
	$res = $db->query($sql);
	if ($res);
	else print("<br>fail ERR: " . $sql);

	//Get les rubriques
	$sql = "SELECT * FROM llx_Paie_Rub WHERE auFiche=1";
	$resr = $db->query($sql);

	if ($resr->num_rows > 0) {
		while ($row = $resr->fetch_assoc()) {
			if ($row["rub"] != '710' && $row["rub"] != '711' && $row["rub"] != '712' && $row["rub"] != '713') {
				if ($row["calcule"] == 1 || $row["cotisation"] == 1) {
					$checked = (GETPOST($row["rub"], 'float') == "on") ? 1 : 0;
					$sql = "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, " . $row['rub'] . ", $checked);";
					$res = $db->query($sql);
					if ($res);
					else print("<br>fail ERR: " . $sql);
				} else {
					$amount = (float)GETPOST($row["rub"], 'float');
					if ($row["rub"] == '14' && $amount > (0.1 * $salaire)) {
						$amount = 0;
						setEventMessages("La représentation est > 10 %", array(), 'errors');
					}
					$sql = "REPLACE into llx_Paie_UserParameters(userid, rub, amount) values ($object->id, " . $row['rub'] . ", $amount);";
					$res = $db->query($sql);
					if ($res);
					else print("<br>fail ERR: " . $sql);
				}
			}
		}
	}
	$cimrChecked = GETPOST('cimr', 'int');
	$sql = "";
	if ($cimrChecked == '710') {
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '710', 1);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '711', 1);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '712', 0);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '713', 0)";
	} else if ($cimrChecked == '712') {
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '710', 0);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '711', 0);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '712', 1);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '713', 1)";
	} else {
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '710', 0);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '711', 0);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '712', 0);";
		$sql .= "REPLACE into llx_Paie_UserParameters(userid, rub, checked) values ($object->id, '713', 0)";
	}
	$requests = explode(';', $sql);
	foreach ($requests as $request) {
		$res = $db->query($request);
		if ($res);
		else print("<br>fail ERR: " . $request);
	}



	if (!$res) {
		setEventMessages($account->error, $account->errors, 'errors');
		$action = 'editSalary'; // Force chargement page edition
	} else {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		$action = '';
	}
}



/*
 *	View
 */

$form = new Form($db);

$childids = $user->getAllChildIds(1);

llxHeader(null, $langs->trans("BankAccounts"));

$head = user_prepare_head_rh($object);

if ($id && $bankid && $action == 'edit') {
	print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" method="post">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="' . GETPOST("id", 'int') . '">';
	print '<input type="hidden" name="bankid" value="' . $bankid . '">';
}
if ($id && $action == 'create') {
	print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" method="post">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="bankid" value="' . $bankid . '">';
}
if ($id && $action == 'editSalary') {
	print '<form action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '" method="post">';
	print '<input type="hidden" name="token" value="' . newToken() . '">';
	print '<input type="hidden" name="action" value="updateSalary">';
}


// View
if ($action != 'edit' && $action != 'create' && $action != 'editSalary') {		// If not bank account yet, $account may be empty
	// get last updates
	$sql = "SELECT * from llx_Paie_UserInfo WHERE userid=" . $object->id;
	$res = $db->query($sql);
	if ($res->num_rows > 0) {
		$salaireInfo = $res->fetch_assoc();
	}
	$object = new User($db);
	if ($id > 0 || !empty($ref)) {
		$result = $object->fetch($id, $ref, '', 1);
		$object->getrights();
	}
	$title = $langs->trans("User");
	print dol_get_fiche_head($head, 'bank', $title, -1, 'user');

	$linkback = '';

	if ($user->rights->user->user->lire || $user->admin) {
		$linkback = '<a href="' . DOL_URL_ROOT . '/RH/Users/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';
	}

	$morehtmlref = '<a href="' . DOL_URL_ROOT . '/user/vcard.php?id=' . $object->id . '" class="refid">';
	$morehtmlref .= img_picto($langs->trans("Download") . ' ' . $langs->trans("VCard"), 'vcard.png', 'class="valignmiddle marginleftonly paddingrightonly"');
	$morehtmlref .= '</a>';

	dol_banner_tab($object, 'id', $linkback, $user->rights->user->user->lire || $user->admin, 'rowid', 'ref', $morehtmlref);

	print '<div class="fichecenter"><div class="fichehalfleft">';

	print '<div class="underbanner clearboth"></div>';

	print '<table class="border centpercent tableforfield">';






	print '<tr><td class="nowrap"> Type de salaire </td>';
	print '<td>' . $salaireInfo["type"] . '</td></tr>';

	print '<tr><td class="nowrap"> Mode Paiment </td>';
	print '<td>' . $salaireInfo["mode_paiement"] . '</td></tr>';

	print '<tr><td class="nowrap">' . $langs->trans("Salary") . '</td>';
	print '<td>' . ($object->salary == "" ? "-" : price($object->salary, '', $langs, 1, -1, -1, $conf->currency)) . '</td></tr>';

	print '<tr><td class="nowrap">' . $langs->trans("THM") . '</td>';
	print '<td>' . ($object->thm != '' ? price($object->thm, '', $langs, 1, -1, -1, $conf->currency) : '-') . '</td></tr>';

	print '<tr><td class="nowrap">' . $langs->trans("TJM") . '</td>';
	print '<td>' . ($object->tjm != '' ? price($object->tjm, '', $langs, 1, -1, -1, $conf->currency) : '-') . '</td></tr>';

	print '<tr><td class="titlefield"> N° CNSS </td>';
	print '<td>' . ($salaireInfo["cnss"] == "" ? "-" : $salaireInfo["cnss"]) . '</td></tr>';

	print '<tr><td class="nowrap"> Mutuelle </td>';
	print '<td>' . ($salaireInfo["mutuelle"] == "" ? "-" : $salaireInfo["mutuelle"]) . '</td></tr>';

	print '<tr><td class="nowrap"> N° CIMR </td>';
	print '<td>' . ($salaireInfo["mutuelle"] == "" ? "-" : $salaireInfo["cimr"]) . '</td></tr>';

	$sql = "SELECT r.rub, r.designation, r.calcule, r.auFiche, r.cotisation, s.amount, s.checked FROM llx_Paie_Rub r, llx_Paie_UserParameters s WHERE r.rub=s.rub AND s.userid=$object->id";
	$res = $db->query($sql);
	if ($res->num_rows > 0) {
		while ($row = $res->fetch_assoc()) {
			if ($row["rub"] == '710' || $row["rub"] == '711' || $row["rub"] == '712' || $row["rub"] == '713') {
				if ($row["rub"] == '710' && $row["checked"] == 1) {
					print '<tr><td class="nowrap"> ' . $row["designation"] . ' </td><td colspan="4"><input type="checkbox" checked disabled></td></tr>';
				} else if ($row["rub"] == '712' && $row["checked"] == 1) {
					print '<tr><td class="nowrap"> ' . $row["designation"] . ' </td><td colspan="4"><input type="checkbox" checked disabled></td></tr>';
				}
			} else if ($row["calcule"] == 1 || ($row["cotisation"] == 1 && $row["auFiche"] == 1)) {
				print '<tr><td class="nowrap"> ' . $row["designation"] . ' </td>';
				$checked = '';
				if ($row["checked"] == 1) {
					$checked = 'checked';
				}
				print '<td colspan="4"><input type="checkbox" name="' . $row["rub"] . '" ' . $checked . ' disabled></td></tr>';
			} else {
				print '<tr><td class="nowrap"> ' . $row["designation"] . ' </td>';
				print '<td>' . $row["amount"] . '</td></tr>';
			}
		}
	}





	print '</table>';

	print '</div><div class="fichehalfright">';

	// Max number of elements in small lists
	$MAXLIST = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;

	// Latest payments of salaries
	if (
		!empty($conf->salaries->enabled) &&
		(($user->rights->salaries->read && (in_array($object->id, $childids) || $object->id == $user->id)) || (!empty($user->rights->salaries->readall)))
	) {
		$payment_salary = new PaymentSalary($db);
		$salary = new Salary($db);

		$sql = "SELECT s.rowid as sid, s.ref as sref, s.label, s.datesp, s.dateep, s.paye, s.amount, SUM(ps.amount) as alreadypaid";
		$sql .= " FROM " . MAIN_DB_PREFIX . "salary as s";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "payment_salary as ps ON (s.rowid = ps.fk_salary)";
		$sql .= " WHERE s.fk_user = " . ((int) $object->id);
		$sql .= " AND s.entity IN (" . getEntity('salary') . ")";
		$sql .= " GROUP BY s.rowid, s.ref, s.label, s.datesp, s.dateep, s.paye, s.amount";
		$sql .= " ORDER BY s.dateep DESC";

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);

			print '<div class="div-table-responsive-no-min">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
			print '<table class="noborder centpercent">';

			print '<tr class="liste_titre">';
			print '<td colspan="5"><table class="nobordernopadding centpercent"><tr><td>' . $langs->trans("LastSalaries", ($num <= $MAXLIST ? "" : $MAXLIST)) . '</td><td class="right"><a class="notasortlink" href="' . DOL_URL_ROOT . '/salaries/list.php?search_user=' . $object->login . '">' . $langs->trans("AllSalaries") . '<span class="badge marginleftonlyshort">' . $num . '</span></a></td>';
			print '</tr></table></td>';
			print '</tr>';

			$i = 0;
			while ($i < $num && $i < $MAXLIST) {
				$objp = $db->fetch_object($resql);

				$salary->id = $objp->sid;
				$salary->ref = $objp->sref ? $objp->sref : $objp->sid;
				$salary->label = $objp->label;
				$salary->datesp = $db->jdate($objp->datesp);
				$salary->dateep = $db->jdate($objp->dateep);
				$salary->paye = $objp->paye;
				$salary->amount = $objp->amount;

				$payment_salary->id = $objp->rowid;
				$payment_salary->ref = $objp->ref;
				$payment_salary->datep = $db->jdate($objp->datep);

				print '<tr class="oddeven">';
				print '<td class="nowraponall">';
				print $salary->getNomUrl(1);
				print '</td>';
				print '<td class="right nowraponall">' . dol_print_date($db->jdate($objp->datesp), 'day') . "</td>\n";
				print '<td class="right nowraponall">' . dol_print_date($db->jdate($objp->dateep), 'day') . "</td>\n";
				print '<td class="right nowraponall"><span class="amount">' . price($objp->amount) . '</span></td>';
				print '<td class="right nowraponall">' . $salary->getLibStatut(5, $objp->alreadypaid) . '</td>';
				print '</tr>';
				$i++;
			}
			$db->free($resql);

			if ($num <= 0) {
				print '<td colspan="5"><span class="opacitymedium">' . $langs->trans("None") . '</span></a>';
			}
			print "</table>";
			print "</div>";
		} else {
			dol_print_error($db);
		}
	}

	// Latest leave requests
	if (!empty($conf->holiday->enabled) && ($user->rights->holiday->readall || ($user->rights->holiday->read && $object->id == $user->id))) {
		$holiday = new Holiday($db);

		$sql = "SELECT h.rowid, h.statut as status, h.fk_type, h.date_debut, h.date_fin, h.halfday";
		$sql .= " FROM " . MAIN_DB_PREFIX . "holiday as h";
		$sql .= " WHERE h.fk_user = " . ((int) $object->id);
		$sql .= " AND h.entity IN (" . getEntity('holiday') . ")";
		$sql .= " ORDER BY h.date_debut DESC";

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);

			print '<div class="div-table-responsive-no-min">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
			print '<table class="noborder centpercent">';

			print '<tr class="liste_titre">';
			print '<td colspan="4"><table class="nobordernopadding centpercent"><tr><td>' . $langs->trans("LastHolidays", ($num <= $MAXLIST ? "" : $MAXLIST)) . '</td><td class="right"><a class="notasortlink" href="' . DOL_URL_ROOT . '/holiday/list.php?id=' . $object->id . '">' . $langs->trans("AllHolidays") . '<span class="badge marginleftonlyshort">' . $num . '</span></a></td>';
			print '</tr></table></td>';
			print '</tr>';

			$i = 0;
			while ($i < $num && $i < $MAXLIST) {
				$objp = $db->fetch_object($resql);

				$holiday->id = $objp->rowid;
				$holiday->ref = $objp->rowid;

				$holiday->fk_type = $objp->fk_type;
				$holiday->statut = $objp->status;
				$holiday->status = $objp->status;

				$nbopenedday = num_open_day($db->jdate($objp->date_debut, 'gmt'), $db->jdate($objp->date_fin, 'gmt'), 0, 1, $objp->halfday);

				print '<tr class="oddeven">';
				print '<td class="nowraponall">';
				print $holiday->getNomUrl(1);
				print '</td><td class="right nowraponall">' . dol_print_date($db->jdate($objp->date_debut), 'day') . "</td>\n";
				print '<td class="right nowraponall">' . $nbopenedday . ' ' . $langs->trans('DurationDays') . '</td>';
				print '<td class="right nowraponall">' . $holiday->LibStatut($objp->status, 5) . '</td>';
				print '</tr>';
				$i++;
			}
			$db->free($resql);

			if ($num <= 0) {
				print '<td colspan="4"><span class="opacitymedium">' . $langs->trans("None") . '</span></a>';
			}
			print "</table>";
			print "</div>";
		} else {
			dol_print_error($db);
		}
	}

	// Latest expense report
	if (
		!empty($conf->expensereport->enabled) &&
		($user->rights->expensereport->readall || ($user->rights->expensereport->lire && $object->id == $user->id))
	) {
		$exp = new ExpenseReport($db);

		$sql = "SELECT e.rowid, e.ref, e.fk_statut as status, e.date_debut, e.total_ttc";
		$sql .= " FROM " . MAIN_DB_PREFIX . "expensereport as e";
		$sql .= " WHERE e.fk_user_author = " . ((int) $object->id);
		$sql .= " AND e.entity = " . ((int) $conf->entity);
		$sql .= " ORDER BY e.date_debut DESC";

		$resql = $db->query($sql);
		if ($resql) {
			$num = $db->num_rows($resql);

			print '<div class="div-table-responsive-no-min">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
			print '<table class="noborder centpercent">';

			print '<tr class="liste_titre">';
			print '<td colspan="4"><table class="nobordernopadding centpercent"><tr><td>' . $langs->trans("LastExpenseReports", ($num <= $MAXLIST ? "" : $MAXLIST)) . '</td><td class="right"><a class="notasortlink" href="' . DOL_URL_ROOT . '/expensereport/list.php?id=' . $object->id . '">' . $langs->trans("AllExpenseReports") . '<span class="badge marginleftonlyshort">' . $num . '</span></a></td>';
			print '</tr></table></td>';
			print '</tr>';

			$i = 0;
			while ($i < $num && $i < $MAXLIST) {
				$objp = $db->fetch_object($resql);

				$exp->id = $objp->rowid;
				$exp->ref = $objp->ref;
				$exp->status = $objp->status;

				print '<tr class="oddeven">';
				print '<td class="nowraponall">';
				print $exp->getNomUrl(1);
				print '</td><td class="right nowraponall">' . dol_print_date($db->jdate($objp->date_debut), 'day') . "</td>\n";
				print '<td class="right nowraponall"><span class="amount">' . price($objp->total_ttc) . '</span></td>';
				print '<td class="right nowraponall">' . $exp->LibStatut($objp->status, 5) . '</td>';
				print '</tr>';
				$i++;
			}
			$db->free($resql);

			if ($num <= 0) {
				print '<td colspan="4"><span class="opacitymedium">' . $langs->trans("None") . '</span></a>';
			}
			print "</table>";
			print "</div>";
		} else {
			dol_print_error($db);
		}
	}

	print '</div></div>';
	print '<div style="clear:both"></div>';

	print dol_get_fiche_end();

	// List of bank accounts (Currently only one bank account possible for each employee)

	$morehtmlright = '';
	if ($account->id == 0) {
		if ($permissiontoaddbankaccount) {
			$morehtmlright = dolGetButtonTitle($langs->trans('Add'), '', 'fa fa-plus-circle', $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&amp;action=create');
		} else {
			$morehtmlright = dolGetButtonTitle($langs->trans('Add'), $langs->trans('NotEnoughPermissions'), 'fa fa-plus-circle', '', '', -2);
		}
	} else {
		$morehtmlright = dolGetButtonTitle($langs->trans('Add'), $langs->trans('AlreadyOneBankAccount'), 'fa fa-plus-circle', '', '', -2);
	}

	print load_fiche_titre($langs->trans("BankAccounts"), $morehtmlright, 'bank_account');

	print '<div class="div-table-responsive-no-min">'; // You can use div-table-responsive-no-min if you dont need reserved height for your table
	print '<table class="liste centpercent">';

	print '<tr class="liste_titre">';
	print_liste_field_titre("LabelRIB");
	print_liste_field_titre("Bank");
	print_liste_field_titre("RIB");
	print_liste_field_titre("IBAN");
	print_liste_field_titre("BIC");
	print_liste_field_titre('', $_SERVER["PHP_SELF"], "", '', '', '', '', '', 'maxwidthsearch ');
	print "</tr>\n";

	if ($account->id > 0) {
		print '<tr class="oddeven">';
		// Label
		print '<td>' . $account->label . '</td>';
		// Bank name
		print '<td>' . $account->bank . '</td>';
		// Account number
		print '<td>';
		$string = '';
		foreach ($account->getFieldsToShow() as $val) {
			if ($val == 'BankCode') {
				$string .= $account->code_banque . ' ';
			} elseif ($val == 'BankAccountNumber') {
				$string .= $account->number . ' ';
			} elseif ($val == 'DeskCode') {
				$string .= $account->code_guichet . ' ';
			} elseif ($val == 'BankAccountNumberKey') {
				$string .= $account->cle_rib . ' ';
			}
		}
		if (!empty($account->label) && $account->number) {
			if (!checkBanForAccount($account)) {
				$string .= ' ' . img_picto($langs->trans("ValueIsNotValid"), 'warning');
			} else {
				$string .= ' ' . img_picto($langs->trans("ValueIsValid"), 'info');
			}
		}

		print $string;
		print '</td>';
		// IBAN
		print '<td>' . $account->iban;
		if (!empty($account->iban)) {
			if (!checkIbanForAccount($account)) {
				print ' ' . img_picto($langs->trans("IbanNotValid"), 'warning');
			}
		}
		print '</td>';
		// BIC
		print '<td>' . $account->bic;
		if (!empty($account->bic)) {
			if (!checkSwiftForAccount($account)) {
				print ' ' . img_picto($langs->trans("SwiftNotValid"), 'warning');
			}
		}
		print '</td>';

		// Edit/Delete
		print '<td class="right nowraponall">';
		if ($permissiontoaddbankaccount) {
			print '<a class="editfielda" href="' . $_SERVER["PHP_SELF"] . '?id=' . $object->id . '&bankid=' . $account->id . '&action=edit&token=' . newToken() . '">';
			print img_picto($langs->trans("Modify"), 'edit');
			print '</a>';
		}
		print '</td>';

		print '</tr>';
	}


	if ($account->id == 0) {
		$colspan = 6;
		print '<tr><td colspan="' . $colspan . '"><span class="opacitymedium">' . $langs->trans("NoBANRecord") . '</span></td></tr>';
	}

	print '</table>';
	print '</div>';
	print dolGetButtonAction($langs->trans('Modify'), '', 'default', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&amp;action=editSalary', '', true, $params);
}

// Edit info
if ($action == 'editSalary') {
	$title = $langs->trans("User");
	dol_fiche_head($head, 'bank', $title, 0, 'user');

	$linkback = '<a href="' . DOL_URL_ROOT . '/RH/Users/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

	dol_banner_tab($object, 'id', $linkback, $user->rights->user->user->lire || $user->admin);



	dol_fiche_end();

	//Les parametres de salaire:
	print '<div class="clearboth"></div>';
	print '<h2>Les parametres de salaire:</h1>';
	print '<table class="border centpercent">';

	//$types=array('mensuel'=>'', 'hebdomadaire'=>'', 'jornalier'=>'', 'horaire'=>'', 'tache'=>'');
	$types[$salaireInfo["type"]] = 'selected';

	print '<tr><td class="titlefield fieldrequired"> Type de Salaire </td>';
	print '<td colspan="4">
			<select name="type" id="types">
				<option value="mensuel" ' . $types["mensuel"] . '>Mensuel</option>
				<option value="horaire" ' . $types["horaire"] . '>Horaire</option>
			</select></td></tr>';
	// <option value="hebdomadaire" ' . $types["hebdomadaire"] . '>Hebdomadaire</option>
	// <option value="jornalier" ' . $types["jornalier"] . '>Jornalier</option>
	// <option value="tache" ' . $types["tache"] . '>Par Tache</option>

	print '<tr><td class="titlefield fieldrequired">' . $langs->trans("Salary") . '</td>
		<td colspan="4"><input size="30" type="text" name="salaire" value="' . (str_replace(" ", "", price($object->salary))) . '"></td>';
	$modes[$salaireInfo["mode_paiement"]] = 'selected';
	print '<tr><td class="titlefield fieldrequired"> Mode Paiment </td>';
	print '<td colspan="4"><select name="modePaiement">
				<option value=""></option>
				<option value="cheque" ' . $modes["cheque"] . '>Chéque</option>
				<option value="espece" ' . $modes["espece"] . '>Espece</option>
				<option value="virement" ' . $modes["virement"] . '>Virement</option>
			</select></td></tr>';

	print '<tr><td class="titlefield fieldrequired">' . $langs->trans("THM") . '</td>
		<td colspan="4"><input size="30" type="text" name="thm" value="' . (str_replace(" ", "", price($object->thm))) . '"></td>';

	print '<tr><td class="titlefield fieldrequired">' . $langs->trans("TJM") . '</td>
		<td colspan="4"><input size="30" type="text" name="tjm" value="' . (str_replace(" ", "", price($object->tjm))) . '"></td>';

	print '<tr><td class="titlefield fieldrequired"> N° CNSS </td>
		<td colspan="4"><input size="30" type="text" name="cnss" value="' . $salaireInfo["cnss"] . '"></td>';
	print '<tr><td class="titlefield fieldrequired"> Mutuelle </td>
		<td colspan="4"><input size="30" type="text" name="mutuelle" value="' . $salaireInfo["mutuelle"] . '"></td>';
	print '<tr><td class="titlefield fieldrequired"> N° CIMR </td>
		<td colspan="4"><input size="30" type="text" name="cimr" value="' . $salaireInfo["cimr"] . '"></td>';

	//Get les rubriques
	$sql = "SELECT * FROM llx_Paie_Rub WHERE auFiche=1";
	$res = $db->query($sql);
	$rubSelected = '0';

	if ($res->num_rows > 0) {
		while ($row = $res->fetch_assoc()) {
			if ($row["rub"] == '711' || $row["rub"] == '713') {
			} else if ($row["rub"] == '710' || $row["rub"] == '712') {
				//Get value
				$sql = "SELECT * from llx_Paie_UserParameters WHERE userid=" . $object->id . " AND rub=" . $row["rub"];
				$res1 = $db->query($sql);
				if ($res1->num_rows > 0) {
					$salaire = $res1->fetch_assoc();
					$amount =  $salaire["amount"];
					$checked = $salaire["checked"];
				} else {
					$amount = "";
					$checked = '';
				}
				if ($checked == 1) {
					$rubSelected = $row["rub"];
				}
			} else {
				//Get value
				$sql = "SELECT * from llx_Paie_UserParameters WHERE userid=" . $object->id . " AND rub=" . $row["rub"];
				$res1 = $db->query($sql);
				if ($res1->num_rows > 0) {
					$salaire = $res1->fetch_assoc();
					$amount =  $salaire["amount"];
					$checked = $salaire["checked"];
				} else {
					$amount = "";
					$checked = '';
				}
				print '<tr><td class="titlefield fieldrequired"> ' . $row["designation"] . ' </td>';
				if ($row["calcule"] == 1 || $row["cotisation"] == 1) {
					if ($checked == 1) {
						$checked = 'checked';
					}
					print '<td colspan="4"><input type="checkbox" name="' . $row["rub"] . '" ' . $checked . '></td></tr>';
				} else {
					print '<td colspan="4"><input size="30" type="text" name="' . $row["rub"] . '" value="' . $amount . '"></td></tr>';
				}
			}
		}
		print '
			<tr><td class="titlefield fieldrequired">COTISATION CIMR</td><td>
				<select name="cimr">	
					<option value="0" ' . ($rubSelected == '0' ? 'selected' : '') . '></option>
					<option value="710" ' . ($rubSelected == '710' ? 'selected' : '') . '>3%</option>
					<option value="712" ' . ($rubSelected == '712' ? 'selected' : '') . '>6%</option>
				</select></td>
			</tr>';
	}
	print '</table>';
	print '<hr>';
	print '</div>';

	print '<div class="center">';
	print '<input class="button" value="' . $langs->trans("Modify") . '" type="submit">';
	print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	print '<input class="button" name="cancel" value="' . $langs->trans("Cancel") . '" type="submit">';
	print '</div>';
	print "</form>";
}


// Edit
if ($id && ($action == 'edit' || $action == 'create')) {
	$title = $langs->trans("User");
	print dol_get_fiche_head($head, 'bank', $title, 0, 'user');

	$linkback = '<a href="' . DOL_URL_ROOT . '/RH/Users/list.php?restore_lastsearch_values=1">' . $langs->trans("BackToList") . '</a>';

	dol_banner_tab($object, 'id', $linkback, $user->rights->user->user->lire || $user->admin);

	//print '<div class="fichecenter">';

	print '<div class="underbanner clearboth"></div>';
	print '<table class="border centpercent">';

	print '<tr><td class="titlefield fieldrequired">' . $langs->trans("LabelRIB") . '</td>';
	print '<td colspan="4"><input size="30" type="text" name="label" value="' . $account->label . '"></td></tr>';

	print '<tr><td class="fieldrequired">' . $langs->trans("BankName") . '</td>';
	print '<td><input size="30" type="text" name="bank" value="' . $account->bank . '"></td></tr>';

	// Show fields of bank account
	foreach ($account->getFieldsToShow() as $val) {
		if ($val == 'BankCode') {
			$name = 'code_banque';
			$size = 8;
			$content = $account->code_banque;
		} elseif ($val == 'DeskCode') {
			$name = 'code_guichet';
			$size = 8;
			$content = $account->code_guichet;
		} elseif ($val == 'BankAccountNumber') {
			$name = 'number';
			$size = 18;
			$content = $account->number;
		} elseif ($val == 'BankAccountNumberKey') {
			$name = 'cle_rib';
			$size = 3;
			$content = $account->cle_rib;
		}

		print '<td>' . $langs->trans($val) . '</td>';
		print '<td><input size="' . $size . '" type="text" class="flat" name="' . $name . '" value="' . $content . '"></td>';
		print '</tr>';
	}

	// IBAN
	print '<tr><td class="fieldrequired">' . $langs->trans("IBAN") . '</td>';
	print '<td colspan="4"><input size="30" type="text" name="iban" value="' . $account->iban . '"></td></tr>';

	print '<tr><td class="fieldrequired">' . $langs->trans("BIC") . '</td>';
	print '<td colspan="4"><input size="12" type="text" name="bic" value="' . $account->bic . '"></td></tr>';

	print '<tr><td class="tdtop">' . $langs->trans("BankAccountDomiciliation") . '</td><td colspan="4">';
	print '<textarea name="domiciliation" rows="4" class="quatrevingtpercent">';
	print dol_escape_htmltag($account->domiciliation);
	print "</textarea></td></tr>";

	print '<tr><td>' . $langs->trans("BankAccountOwner") . '</td>';
	print '<td colspan="4"><input size="30" type="text" name="proprio" value="' . $account->proprio . '"></td></tr>';
	print "</td></tr>\n";

	print '<tr><td class="tdtop">' . $langs->trans("BankAccountOwnerAddress") . '</td><td colspan="4">';
	print '<textarea name="owner_address" rows="4" class="quatrevingtpercent">';
	print dol_escape_htmltag($account->owner_address);
	print "</textarea></td></tr>";

	print '</table>';

	//print '</div>';

	print dol_get_fiche_end();

	print $form->buttonsSaveCancel("Modify");
}

if ($id && $action == 'edit') {
	print '</form>';
}

if ($id && $action == 'create') {
	print '</form>';
}
if ($id && $action == 'updateSalary') {
	print '</form>';
}

// End of page
llxFooter();
$db->close();
