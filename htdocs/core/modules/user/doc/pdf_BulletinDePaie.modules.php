<?php
/* Copyright (C) 2010-2011  Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2010-2014  Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2015       Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2018       Frédéric France      <frederic.france@netlogic.fr>
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
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/supplier_invoice/doc/pdf_canelle.modules.php
 *	\ingroup    fournisseur
 *	\brief      Class file to generate the supplier invoices with the canelle model
 */

use Stripe\BankAccount;

require_once DOL_DOCUMENT_ROOT . '/core/modules/user/modules_user.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

require_once DOL_DOCUMENT_ROOT . '/societe/class/companybankaccount.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/userbankaccount.class.php';


/**
 *	Class to generate the supplier invoices PDF with the template canelle
 */
class pdf_BulletinDePaie extends ModelePDFUser
{
	/**
	 * @var DoliDb Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int 	Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * @var array Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.5 = array(5, 5)
	 */
	public $phpmin = array(5, 5);

	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var int page_largeur
	 */
	public $page_largeur;

	/**
	 * @var int page_hauteur
	 */
	public $page_hauteur;

	/**
	 * @var array format
	 */
	public $format;

	/**
	 * @var int marge_gauche
	 */
	public $marge_gauche;

	/**
	 * @var int marge_droite
	 */
	public $marge_droite;

	/**
	 * @var int marge_haute
	 */
	public $marge_haute;

	/**
	 * @var int marge_basse
	 */
	public $marge_basse;

	/**
	 * Issuer
	 * @var Societe object that emits
	 */
	public $emetteur;


	/**
	 *	Constructor
	 *
	 *  @param	DoliDB		$db     	Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs, $mysoc;

		// Translations
		$langs->loadLangs(array("main", "bills"));

		$this->db = $db;
		$this->name = "Bulletin de Paie";
		$this->description = $langs->trans('SuppliersInvoiceModel');

		// Dimension page
		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = isset($conf->global->MAIN_PDF_MARGIN_LEFT) ? $conf->global->MAIN_PDF_MARGIN_LEFT : 10;
		$this->marge_droite = isset($conf->global->MAIN_PDF_MARGIN_RIGHT) ? $conf->global->MAIN_PDF_MARGIN_RIGHT : 10;
		$this->marge_haute = isset($conf->global->MAIN_PDF_MARGIN_TOP) ? $conf->global->MAIN_PDF_MARGIN_TOP : 10;
		$this->marge_basse = isset($conf->global->MAIN_PDF_MARGIN_BOTTOM) ? $conf->global->MAIN_PDF_MARGIN_BOTTOM : 10;

		$this->option_logo = 1; // Affiche logo
		$this->option_tva = 1; // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 1; // Affiche mode reglement
		$this->option_condreg = 1; // Affiche conditions reglement
		$this->option_codeproduitservice = 1; // Affiche code produit-service
		$this->option_multilang = 1; // Dispo en plusieurs langues

		// Define column position
		$this->posxdesc = $this->marge_gauche + 1;
		$this->posxtva = 112;
		$this->posxup = 126;
		$this->posxqty = 145;
		$this->posxunit = 162;
		$this->posxdiscount = 162;
		$this->postotalht = 174;

		if ($conf->global->PRODUCT_USE_UNITS) {
			$this->posxtva = 99;
			$this->posxup = 114;
			$this->posxqty = 130;
			$this->posxunit = 147;
		}
		//if (! empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT)) $this->posxtva=$this->posxup;
		$this->posxpicture = $this->posxtva - (empty($conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH) ? 20 : $conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH); // width of images
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$this->posxpicture -= 20;
			$this->posxtva -= 20;
			$this->posxup -= 20;
			$this->posxqty -= 20;
			$this->posxunit -= 20;
			$this->posxdiscount -= 20;
			$this->postotalht -= 20;
		}

		$this->tva = array();
		$this->localtax1 = array();
		$this->localtax2 = array();
		$this->atleastoneratenotnull = 0;
		$this->atleastonediscount = 0;
	}


	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		FactureFournisseur	$object				Object to generate
	 *  @param		Translate			$outputlangs		Lang output object
	 *  @param		string				$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int					$hidedetails		Do not show line details
	 *  @param		int					$hidedesc			Do not show desc
	 *  @param		int					$hideref			Do not show ref
	 *  @return		int										1=OK, 0=KO
	 */
	public function write_file($object, $outputlangs = '', $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $db, $month, $year, $user, $langs, $conf, $mysoc, $hookmanager, $nblines, $cloture;

		error_log("work?");
		// Get source company
		$currency = !empty($currency) ? $currency : $conf->currency;
		if (!is_object($object->thirdparty)) $object->fetch_thirdparty();
		if (!is_object($object->thirdparty)) $object->thirdparty = $mysoc; // If fetch_thirdparty fails, object has no socid (specimen)
		$this->emetteur = $object->thirdparty;
		if (!$this->emetteur->country_code) $this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined

		if (!is_object($outputlangs)) $outputlangs = $langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (!empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output = 'ISO-8859-1';

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products"));

		if (1) {
			$object->fetch_thirdparty();
			// Definition of $dir and $file
			if ($object->specimen) {
				$dir = $conf->fournisseur->facture->dir_output;
				$file = $dir . "/SPECIMEN.pdf";
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				$objectrefsupplier = dol_sanitizeFileName($object->ref_supplier);

				//get date
				$dateg = $_SESSION['dateg'];
				$dir = DOL_DATA_ROOT . '/grh/BulletinDePaie/' . $dateg;

				$file = $dir . "/" . $object->login . "_" . $dateg . "_BulletinDePaie.pdf";
				if (!empty($conf->global->SUPPLIER_REF_IN_NAME)) $file = $dir . "/" . $objectref . ($objectrefsupplier ? "_" . $objectrefsupplier : "") . ".pdf";
			}

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir)) {
				// Add pdfgeneration hook
				if (!is_object($hookmanager)) {
					include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Create pdf instance
				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
				$pdf->SetAutoPageBreak(1, 0);

				$heightforinfotot = 50 + (4 * $nbpayments); // Height reserved to output the info and total part and payment part
				$heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
				if ($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS > 0) $heightforfooter += 6;

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				// Set path to the background PDF File
				if (!empty($conf->global->MAIN_ADD_PDF_BACKGROUND)) {
					$pagecount = $pdf->setSourceFile($conf->mycompany->multidir_output[$object->entity] . '/' . $conf->global->MAIN_ADD_PDF_BACKGROUND);
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
				$pdf->SetCreator("Dolibarr " . DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("PdfInvoiceTitle") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
				if (!empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Ri$periodeght

				// Set $this->atleastonediscount if you have at least one discount
				for ($i = 0; $i < $nblines; $i++) {
					if ($object->lines[$i]->remise_percent) {
						$this->atleastonediscount++;
					}
				}
				if (empty($this->atleastonediscount)) {
					$delta = ($this->postotalht - $this->posxdiscount);
					$this->posxpicture += $delta;
					$this->posxtva += $delta;
					$this->posxup += $delta;
					$this->posxqty += $delta;
					$this->posxunit += $delta;
					$this->posxdiscount += $delta;
					// post of fields after are not modified, stay at same position
				}

				$object->rowid = $object->id;

				//include bulltin class that have all calculations
				include DOL_DOCUMENT_ROOT . '/RH/Bulletin/Bulletin_Class.php';

				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;
				$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs, $totalNet, $periode);
				$pdf->SetY($pdf->GetY() + 5);
				$pdf->SetX(10);
				$pdf->SetTextColor(0, 0, 0);

				// $tab_top = 90 + $top_shift;
				// $tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD) ? 42 + $top_shift : 10);

				// body

				$bulttin = '<style type="text/css">
					table.tableizer-table {
						font-size: 8px;
						border-bottom:1px solid #000;
						border-collapse: collapse;
						width:100%;
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
					.center-row td{
						text-align: center;
					}
					.right-td{
						text-align: right;
					}
					.rub-td{
						width:40px;
					}
					.disignation-parent-td{
						width:190px;
					}
					.disignation-td{
						width:130px;
					}
					.nombre-td{
						width:60px;
					}
					</style>
						<table class="tableizer-table">
							<thead><tr class="tableizer-firstrow"><th colspan="7" style="width:100%;"></th></tr></thead>
							<tbody>
            					 <tr class="importent-cell row-bordered"><td class="rub-td">&nbsp;</td><td class="disignation-parent-td" colspan="2">&nbsp;</td><td class="white-cell"></td><td></td><td class="white-cell" colspan="2"></td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2">Nom</td><td class="white-cell">' . $object->lastname . ' ' . $object->firstname . '</td><td>Date de naissance</td><td class="white-cell" colspan="2">' . date("d/m/Y", $object->birth) . '</td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2"></td><td class="white-cell"></td><td></td><td class="white-cell" colspan="2"></td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2">N° CNSS</td><td class="white-cell">' . $salaireParams["cnss"] . '</td><td>Fonction</td><td class="white-cell" colspan="2">' . $role . '</td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2"></td><td class="white-cell"></td><td></td><td class="white-cell" colspan="2"></td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2">Matricule</td><td class="white-cell">' . $extrafields["matricule"] . '</td><td>N° CIMR</td><td class="white-cell" colspan="2">' . $salaireParams["cimr"] . '</td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2"></td><td class="white-cell"></td><td></td><td class="white-cell" colspan="2"></td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2">Mode Paiement</td><td class="white-cell">' . $salaireParams["mode_paiement"] . '</td><td></td><td class="white-cell" colspan="2"></td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2"></td><td class="white-cell"></td><td></td><td class="white-cell" colspan="2"></td></tr>
								 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2">Periode</td><td class="white-cell">' . $periode . '</td><td>adresse</td><td class="white-cell" colspan="2">' . $object->address . '</td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2"></td><td class="white-cell"></td><td></td><td class="white-cell" colspan="2"></td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2">Situation familiale</td><td class="white-cell">' . $situation . '</td><td>nombre d\'enfants</td><td class="white-cell" colspan="2">' . $extrafields["enfants"] . '</td></tr>
            					 <tr class="importent-cell row-bordered"><td>&nbsp;</td><td colspan="2"></td><td class="white-cell"></td><td></td><td class="white-cell" colspan="2"></td></tr>
            					 <tr class="importent-cell row-bordered"><td>Rub</td><td class="disignation-td">Désignation</td><td class="nombre-td">Nombre</td><td>Base</td><td>Taux</td><td>A payer</td><td>A retenues</td></tr>';

				$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';

				if ($type == "mensuel") {
					$bulttin .= '<tr class=""><td class="right-td">' . getRebrique("salaireMensuel") . '</td><td>SALAIRE MENSUEL</td><td></td><td  class="right-td">' . price($object->salary, 0, '', 1, 1, 2) . '</td><td  class="right-td"> ' . $Taux . ' </td><td  class="right-td"> ' . price($bases["salaire mensuel"], 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
				}

				if ($type == "horaire") {
					$bulttin .= '<tr class=""><td class="right-td">' . getRebrique("salaireHoraire") . '</td><td>SALAIRE HORAIRE</td><td>' . $workingHours . '</td><td>' . price($object->thm, 0, '', 1, 1, 2) . '</td><td>  </td><td> ' . price($bases["salaire mensuel"], 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
				}

				// 				 <tr class="row-content"><td class="right-td">' . getRebrique("salaireMensuel") . '</td><td>SALAIRE MENSUEL</td><td></td><td class="right-td">' . price($object->salary) . '</td><td class="right-td"> ' . $Taux . ' </td><td class="right-td"> ' . price($bases["salaire mensuel"]) . ' </td><td>&nbsp;</td></tr>';

				if ($soldeConge > 0) {
					$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
					<tr class=""><td class="right-td">' . getRebrique("congesPayes") . '</td><td>CONGE PAYE</td><td>&nbsp;</td><td class="right-td">' . price($bases["salaire de base"], 0, '', 1, 1, 2) . '</td><td class="right-td">' . $congeDays . '</td><td class="right-td"> ' . price($soldeConge, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
				}

				if ($soldeferie > 0) {
					$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
					<tr class=""><td class="right-td">' . getRebrique("joursferie") . '</td><td>LES JOURS FERIE</td><td>&nbsp;</td><td class="right-td">' . price($bases["salaire de base"], 0, '', 1, 1, 2) . '</td><td class="right-td">' . $joursFerie . '</td><td class="right-td"> ' . price($soldeferie, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
				}

				foreach ($hrs as $hr) {
					$bulttin .= '<tr class=""><td class="right-td">' . $hr["rub"] . '</td><td>' . $hr["designation"] . '</td><td class="right-td">' . price($hr["nombre"], 0, '', 1, 1, 2) . '</td><td class="right-td">' . price($hr["base"], 0, '', 1, 1, 2) . '</td><td class="right-td">' . $hr["taux"] . '%</td><td class="right-td"> ' . price($hr["apayer"], 0, '', 1, 1, 2) . ' </td><td class="right-td" >' . $hr["aretenu"] . '</td></tr>';
				}

				if ($primeDancien > 0) {
					$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
						 <tr class=""><td class="right-td">' . getRebrique("primeDancien") . '</td><td>PRIME D\'ANCIENNETE</td><td>&nbsp;</td><td class="right-td">' . price($bases["salaire mensuel"], 0, '', 1, 1, 2) . '</td><td class="right-td">' . $primeDancienPercentage . '%</td><td class="right-td">' . price($primeDancien, 0, '', 1, 1, 2) . '</td><td>&nbsp;</td></tr> ';
				}

				foreach ($enBruts as $enBrut) {
					if ($enBrut["surbulletin"] == 1) {
						$base = $enBrut["base"] > 0 ? price($enBrut["base"], 0, '', 1, 1, 2) : "";
						$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
						$bulttin .= '<tr class=""><td class="right-td">' . $enBrut["rub"] . '</td><td>' . $enBrut["designation"] . '</td><td class="right-td">' . $enBrut["nombre"] . '</td><td class="right-td">' . $base . '</td><td class="right-td">' . $enBrut["taux"] . '</td><td class="right-td"> ' . price($enBrut["apayer"], 0, '', 1, 1, 2) . ' </td><td class="right-td">' . price($enBrut["aretenu"], 0, '', 1, 1, 2) . '</td></tr>';
					}
				}

				if ($primeCommercial > 0) {
					$base = $CA > 0 ? price($CA, 0, '', 1, 1, 2) : "";
					$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
					$bulttin .= '<tr class=""><td class="right-td">' . getRebrique("primeCommercial") . '</td><td>PRIME COMMERCIAL</td><td></td><td class="right-td">' . $base . '</td><td class="right-td">' . $percent . '%</td><td class="right-td"> ' . price($primeCommercial, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>';
				}

				$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
				<tr class="row-content"><td></td><td>SALAIRE BRUT</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td  class="right-td"> ' . price($brutGlobal, 0, '', 1, 1, 2) . ' </td><td>&nbsp;</td></tr>
				<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';

				foreach ($cotisations as $cotisation) {
					if ($cotisation["surbulletin"] == 1) {
						$base = $cotisation["base"] > 0 ? price($cotisation["base"], 0, '', 1, 1, 2) : "";
						$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
						$bulttin .= '<tr class=""><td class="right-td">' . $cotisation["rub"] . '</td><td>' . $cotisation["designation"] . '</td><td class="right-td">' . price($cotisation["nombre"], 0, '', 1, 1, 2) . '</td><td class="right-td">' . $base . '</td><td class="right-td">' . $cotisation["taux"] . '</td><td class="right-td"> ' . $cotisation["apayer"] . ' </td><td class="right-td">' . price($cotisation["aretenu"], 0, '', 1, 1, 2) . '</td></tr>';
					}
				}

				$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
				<tr class="row-content"><td></td><td>TOTAL DES COTISATIONS</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td  class="right-td"></td><td class="right-td">' . price($totalCotisations, 0, '', 1, 1, 2) . '</td></tr>
				<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';

				// if ($chargeFamille != 0) {
				// 	$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
				// 	$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
				// 	$bulttin .= '<tr class="row-content"><td class="right-td">' . getRebrique("chargefamille") . '</td><td>CHARGE DE FAMILLE</td><td>&nbsp;</td><td>&nbsp;</td><td class="right-td">' . $chargeFamilleTaux . '</td><td class="right-td">' . price($chargeFamille, 0, '', 1, 1, 2) . '</td><td>&nbsp;</td></tr>';
				// }

				if ($irNet > 0) {
					$bulttin .= '<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
					<tr class=""><td class="right-td">' . getRebrique("ir") . '</td><td>IR</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td class="right-td"> ' . price($irNet, 0, '', 1, 1, 2) . ' </td></tr>';
					$bulttin .= '<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
				}

				foreach ($pasEnBruts as $pasEnBrut) {
					if ($pasEnBrut["surbulletin"] == 1) {
						$base = $pasEnBrut["base"] > 0 ? price($pasEnBrut["base"], 0, '', 1, 1, 2) : "";
						$bulttin .= '<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
						$bulttin .= '<tr class=""><td class="right-td">' . $pasEnBrut["rub"] . '</td><td>' . $pasEnBrut["designation"] . '</td><td class="right-td">' . $pasEnBrut["nombre"] . '</td><td class="right-td">' . $base . '</td><td class="right-td">' . $pasEnBrut["taux"] . '</td><td class="right-td"> ' . price($pasEnBrut["apayer"], 0, '', 1, 1, 2) . ' </td><td class="right-td">' .  price($pasEnBrut["aretenu"], 0, '', 1, 1, 2) . '</td></tr>';
					}
				}




				$bulttin .= '<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
				<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
				<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
				<tr><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
				<tr class="center-row"><td></td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td></td><td class="importent-cell row-bordered"> ' . price($totalBrut, 0, '', 1, 1, 2) . ' </td><td class="importent-cell row-bordered"> ' . price($totalRetenu, 0, '', 1, 1, 2) . ' </td></tr>
				<tr class="center-row"><td></td><td>&nbsp;</td><td class="importent-cell row-bordered" colspan="3">Net a payer</td><td class="importent-cell row-bordered" colspan="2"> ' . price($totalNet, 0, '', 1, 1, 2) . ' </td></tr>
				
				<tr class="center-row"><td></td><td>&nbsp;</td><td>Jours travaillés</td><td>Brut imposable</td><td>Net imposable</td><td colspan="2">Retenue I.R.</td></tr>
				<tr class="row-bordered row-content center-row"><td></td><td>Mensuel</td><td>' . ($workingdaysdeclaré) . '</td><td>' . price($brutImposable) . '</td><td>' . price($netImposable, 0, '', 1, 1, 2) . '</td><td colspan="2">' . price($irNet, 0, '', 1, 1, 2) . '</td></tr>
				<tr class="row-bordered row-content center-row"><td></td><td>Annuel</td><td>' . ($comulWorkingDays + $workingdaysdeclaré) . '</td><td>' . price($comulsalaireBrut + $brutImposable, 0, '', 1, 1, 2) . '</td><td>' . price($comulnetImposable + $netImposable, 0, '', 1, 1, 2) . '</td><td colspan="2">' . price($comulIR + $irNet, 0, '', 1, 1, 2) . '</td></tr>
				</tbody>
	   			</table>';

				$pdf->writeHTML($bulttin);

				// Pagefoot
				$this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) $pdf->AliasNbPages();

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				if (!empty($conf->global->MAIN_UMASK))
					@chmod($file, octdec($conf->global->MAIN_UMASK));

				$this->result = array('fullpath' => $file);

				//Cloturé le moi
				if (isset($_SESSION['cloture']) && $_SESSION['cloture'] === 1) {
					$sql = "UPDATE llx_Paie_MonthDeclaration SET cloture=1 WHERE userid=$object->id AND year=$year AND month=$month;";
					$res = $db->query($sql);
					if ($res);
					else print("<br>fail ERR: " . $sql);

					$sql2 = "update llx_Paie_UserParameters set amount = '0' where userid=$object->id and  rub in (select rub from llx_Paie_Rub where reset = 1)";
					$res = $db->query($sql2);
					if ($res);
					else print("<br>fail ERR: " . $sql2);
				}

				return 1; // No error
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "SUPPLIER_OUTPUTDIR");
			return 0;
		}
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

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show top header of page.
	 *
	 *  @param  PDF                 $pdf            Object PDF
	 *  @param  FactureFournisseur  $object         Object to show
	 *  @param  int                 $showaddress    0=no, 1=yes
	 *  @param  Translate           $outputlangs    Object lang for output
	 *  @return void
	 */
	protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $totalNet, $periode)
	{
		global $langs, $conf, $mysoc, $month, $year;

		//get date
		$dateg = $_SESSION['dateg'];
		//set date of salary
		$date = date("M Y", strtotime(date("d") . '-' . $dateg));

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "orders", "companies", "bills"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Do not add the BACKGROUND as this is for suppliers
		//pdf_pagehead($pdf,$outputlangs,$this->page_hauteur);

		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', $default_font_size + 3);

		$posy = $this->marge_haute;
		$posx = $this->page_largeur - $this->marge_droite - 100;

		$pdf->SetXY($this->marge_gauche, $posy);

		// Logo

		$logo = $conf->mycompany->dir_output . '/logos/' . $mysoc->logo;
		if ($mysoc->logo) {
			if (is_readable($logo)) {
				$height = pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $this->marge_gauche, $posy, 70, 0);	// width=0 (auto)
			} else {
				$pdf->SetTextColor(200, 0, 0);
				$pdf->SetFont('', 'B', $default_font_size - 2);
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
				$pdf->MultiCell(100, 3, $outputlangs->transnoentities("ErrorGoToModuleSetup"), 0, 'L');
			}
		} else {
			$text = 'Mafitis';
			$pdf->MultiCell(100, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->MultiCell(200, 2, "Bulletin de Paie", 0, "C");
		$pdf->SetFont('', '', $default_font_size + 3);
		$pdf->MultiCell(200, 30, $date, 0, "C");

		$pdf->SetXY($posx, $posy);
		$pdf->SetTextColor(0, 0, 60);
		$posy += 1;


		// set style for barcode
		$style = array(
			'border' => 2,
			'vpadding' => 'auto',
			'hpadding' => 'auto',
			'fgcolor' => array(0, 0, 0),
			'bgcolor' => false, //array(255,255,255)
			'module_width' => 1, // width of a single module in points
			'module_height' => 1 // height of a single module in points
		);

		$user = new User($this->db);
		$user->fetch($object->id);

		$Ubank = new UserBankAccount($this->db);
		$Ubank->fetch(0, '', $object->id);
		//peroide
		//$periode = '01/' . sprintf("%02d", $prev_month) . '-' . date("t", strtotime($prev_month . '/01/' . $prev_year)) . '/' . sprintf("%02d", $prev_month);

		$info = "Nom: " . $object->firstname . " " . $object->lastname . ". Periode: " . $periode . ". Salaire Net: " . price($totalNet, 0);

		// QRCODE,L : QR-CODE Low error correction
		$pdf->write2DBarcode($info, 'QRCODE,L', 160, 6, 30, 30, $style, 'N');

		//Date
		$posy += 2;
		$pdf->SetXY($posx, 40);
		$pdf->SetTextColor(0, 0, 60);
		$pdf->MultiCell(100, 1, "Date.... " . date("d/m/Y"), '', 'R');

		$posy += 1;
		$pdf->SetTextColor(0, 0, 60);

		$top_shift = 0;
		// Show list of linked objects
		$current_y = $pdf->getY();
		$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, 100, 3, 'R', $default_font_size);
		if ($current_y < $pdf->getY()) {
			$top_shift = $pdf->getY() - $current_y;
		}

		return $top_shift;
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show footer of page. Need this->emetteur object
	 *
	 *  @param  PDF                 $pdf                PDF
	 *  @param  FactureFournisseur  $object             Object to show
	 *  @param  Translate           $outputlangs        Object lang for output
	 *  @param  int                 $hidefreetext       1=Hide free text
	 *  @return int                                     Return height of bottom margin including footer text
	 */
	protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		global $conf;
		$showdetails = $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
		return pdf_pagefoot($pdf, $outputlangs, 'SUPPLIER_INVOICE_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}
}
