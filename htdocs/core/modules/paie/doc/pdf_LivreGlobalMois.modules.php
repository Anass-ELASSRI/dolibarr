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
class pdf_LivreGlobalMois extends ModelePDFUser
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
		$this->name = "OrderDeVirementGlobal";
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
	public function write_file($object, $outputlangs = '', $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0, $moreparams)
	{
		// phpcs:enable
		global $object, $user, $langs, $conf, $mysoc, $hookmanager, $nblines, $action, $prev_month, $prev_year, $month, $periode, $year, $db, $sql0;
		error_log("work?");

		if (!is_object($outputlangs)) $outputlangs = $langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (!empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output = 'ISO-8859-1';

		// Load translation files required by the page
		$outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products"));

		if (true) {

			include DOL_DOCUMENT_ROOT . "/RH/class/LivreGlobalMois_Class.php";

			$name = sprintf("%02d", $month) . "-$year";

			// Definition of $dir and $file
			if ($object->specimen) {
				//$dir = $conf->fournisseur->facture->dir_output;
				//$file = $dir . "/SPECIMEN.pdf";
			} else {
				$objectref = dol_sanitizeFileName($object->ref);
				$objectrefsupplier = dol_sanitizeFileName($object->ref_supplier);
				$dir = DOL_DATA_ROOT . '/grh/LivreGlobalMois/';

				$file = $dir . "/mafitis_" . $name . "_LivreDePaieGlobalParMois.pdf";
				if (!empty($conf->global->SUPPLIER_REF_IN_NAME)) $file = $dir . "/" . $objectref . ($objectrefsupplier ? "_" . $objectrefsupplier : "") . ".pdf";
			}

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir)) {

				// Create pdf instance
				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
				$pdf->SetAutoPageBreak(1, 0);

				$heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
				if ($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS > 0) $heightforfooter += 6;

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));

				if (!is_object($object->thirdparty)) $object->fetch_thirdparty();
				if (!is_object($object->thirdparty)) $object->thirdparty = $mysoc; // If fetch_thirdparty fails, object has no socid (specimen)
				$this->emetteur = $object->thirdparty;
				if (!$this->emetteur->country_code) $this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				$pdf->SetTitle($outputlangs->convToOutputCharset('Livre de Paie Global'));
				$pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
				$pdf->SetCreator("Dolibarr " . DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				if (!empty($conf->global->MAIN_DISABLE_PDF_COMPRESSION)) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right


				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;
				$top = $this->_pagehead($pdf, $object, 1, $outputlangs, $totalNet, $periode);
				$pdf->SetY($pdf->GetY() + 10);
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 90;
				$tab_top_newpage = (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD) ? 42 : 10);


				// body

				$french_months = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');

				$Livre = '<style type="text/css">
							table.tableizer-table {
								width:100%;
								font-size: 10px;
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
							.center-row td,th{
								text-align: center;
							}

							.right-td{
								text-align: right;
							}
							.rub-td{
								width:30px;
							}
							.disignation-td{
								width:200px;
							}
							.nombre-td{
								width:70px;
							}
							.nombre-colspan3-td{
								width:100%;
							}
							.center-td{
								text-align: center;
							}
							</style>
							<table class="tableizer-table">
							<thead><tr class="tableizer-firstrow center-row"><th colspan="7" class="nombre-colspan3-td">LIVRE DE PAIE GLOBAL</th></tr></thead>
							<tbody>
							 <tr class="importent-cell"><td></td><td class="disignation-td">Periode</td><td class="white-cell" colspan="3">' . $periode . '</td></tr>
							 <tr class="importent-cell row-bordered">
								<td>Rub</td>
								<td >Désignation</td>
								<td class="nombre-td">BASE</td>
								<td class="nombre-td">DEBIT</td>
								<td class="nombre-td">CREDIT</td>
							</tr>
							 <tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
									<tr class="row-content"><td></td><td >NOMBRE DES JOURS</td><td > ' . price($workingdaysdeclaréTot, 0, '', 1, 1, 2) . ' </td><td></td><td></td></tr>
									<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
									<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>';



				// '<thead><tr class="tableizer-firstrow center-row"><th colspan="3" class="nombre-colspan3-td">LIVRE DE PAIE GLOBAL</th></tr></thead>
				// <tbody>
				// <tr class="importent-cell row-bordered"><td class="nombre-td"></td><td  class="disignation-td">Periode</td><td class="white-cell">' . $periode . '</td></tr>
				// <tr class="importent-cell row-bordered"><td>Rub</td><td >Désignation</td><td>A Payer</td></tr>
				// <tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
				// <tr class="row-content"><td></td><td >NOMBRE DES JOURS</td><td > ' . price($workingdaysdeclaréTot, 0, '', 1, 1, 2) . ' </td><td></td><td></td></tr>
				// <tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
				// <tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>';

				//Get les rubriques en brut global
				foreach ($enBrutsRubs as $rub) {
					$Livre .= '<tr class="row-content"><td>' . $rub["rub"] . '</td><td >' . $rub["designation"] . '</td><td>' . price($rubBases[$rub["rub"]], 0, '', 1, 1, 2) . '</td><td >' . price($enBruts[$rub["rub"]], 0, '', 1, 1, 2) . '</td><td></td></tr>';
				}
				$Livre .= '<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
				<tr class="row-content"><td>&nbsp;</td><td >TOTAL BRUT</td><td></td><td > ' . price($brutGlobalTot, 0, '', 1, 1, 2) . ' </td><td></td></tr>
				<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>';
				
				// //Get les rubriques cotisations
				// foreach ($cotisationsRubs as $rub) {
				// 	$Livre .= '<tr class=""><td class="right-td">' . $rub["rub"] . '</td><td >' . $rub["designation"] . '</td><td class="right-td">' . price($cotisations[$rub["rub"]]) . '</td></tr>';
				// }
				//Get les rubriques cotisations
				foreach ($cotisationsRubs as $rub) {
					$Livre .= '<tr class=""><td class="right-td">' . $rub["rub"] . '</td><td >' . $rub["designation"] . '</td><td class="right-td">' . price($rubBases[$rub["rub"]], 0, '', 1, 1, 2) . '</td><td></td><td class="right-td">' . price(abs($cotisations[$rub["rub"]]), 0, '', 1, 1, 2) . '</td></tr>';
				}


				$Livre .= '<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
				<tr class="row-content"><td>' . getRebrique("netImposable") . '</td><td >SALAIRE NET IMPOSABLE</td><td></td><td > ' . price($netImposableTot, 0, '', 1, 1, 2) . ' </td><td></td></tr>
				<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>';

				$Livre .= '<tr class=""><td>' . getRebrique("chargefamille") . '</td><td >DECUCTION</td><td></td><td >' . price($chargeFamilleTot, 0, '', 1, 1, 2) . '</td><td></td></tr>';

				$Livre .= '<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
						<tr class=""><td>' . getRebrique("ir") . '</td><td >RETENU IGR </td><td>' . price($irbase, 0, '', 1, 1, 2) . '</td><td></td><td> ' . price($irNetTot, 0, '', 1, 1, 2) . ' </td></tr>';

				$Livre .= '<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>';

				// //Get rubriques pas en brut et pas imposable
				// foreach ($pasEnBrutRubs as $rub) {
				// 	$Livre .= '<tr class="row-content"><td class="right-td">' . $rub["rub"] . '</td><td >' . $rub["designation"] . '</td><td class="right-td">' . price($pasEnBruts[$rub["rub"]]) . '</td></tr>';
				// }
				//Get rubriques pas en brut et pas imposable
				foreach ($pasEnBrutRubs as $rub) {
					if ($pasEnBruts[$rub["rub"]] > 0)
						$Livre .= '<tr class=""><td class="right-td">' . $rub["rub"] . '</td><td >' . $rub["designation"] . '</td><td></td><td class="right-td">' . price($pasEnBruts[$rub["rub"]], 0, '', 1, 1, 2) . '</td><td></td></tr>';
					elseif ($pasEnBruts[$rub["rub"]] < 0)
						$Livre .= '<tr class=""><td class="right-td">' . $rub["rub"] . '</td><td >' . $rub["designation"] . '</td><td></td><td></td><td class="right-td">' . price(abs($pasEnBruts[$rub["rub"]]), 0, '', 1, 1, 2) . '</td></tr>';
				}

				// $Livre .= '<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td></tr>
				// 		<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td></tr>
				// 		<tr  class="importent-cell row-bordered" ><td>&nbsp;</td><td class="center-td">Net a payer</td><td class="center-td"> ' . price($totalNetTot) . ' </td></tr>
				// 		</tbody>
				// 	 </table>';

				$Livre .= '<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
					 <tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
					 <tr  class="row-content" ><td>&nbsp;</td><td>Net a payer</td><td></td><td class="right-td" >  </td><td>' . price($totalNetTot, 0, '', 1, 1, 2) . '</td></tr>';

				$Livre .= '<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
								<tr><td>&nbsp;</td><td >&nbsp;</td><td >&nbsp;</td><td></td><td></td></tr>
			 					<tr  class="importent-cell row-bordered" ><td colspan="2">Net a payer</td><td class="right-td">' . price($baseTot, 0, '', 1, 1, 2) . '</td><td  class="right-td"> ' . price(abs($debitTot), 0, '', 1, 1, 2) . ' </td><td> ' . price(abs($creditTot), 0, '', 1, 1, 2) . ' </td></tr>
							</tbody>
							</table>';

				$pdf->SetY($pdf->GetY() + 5);
				$pdf->SetX($this->posxdesc + 20);
				$pdf->writeHTML($Livre);


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
		global $langs, $conf, $mysoc, $prev_month, $prev_year;


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
			$text = $this->emetteur->name;
			$pdf->MultiCell(100, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
		}

		$pdf->SetFont('', 'B', $default_font_size + 3);
		$pdf->MultiCell(200, 2, "Livre de Paie Global", 0, "C");
		$pdf->SetFont('', '', $default_font_size + 3);
		$pdf->MultiCell(200, 30, $periode, 0, "C");

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
		global $conf, $object;
		$showdetails = $conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS;
		return pdf_pagefoot($pdf, $outputlangs, '', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
	}
}
