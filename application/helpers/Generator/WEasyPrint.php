<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Generator PDF with WeasyPrint pdf generator.
 *
 * Extends Generator_WkPdf for full backward compatibility.
 * The key differences vs wkhtmltopdf:
 *  - WeasyPrint does not accept --header-html / --footer-html CLI flags.
 *    Header and footer are injected directly into the invoice HTML using
 *    CSS "position: running()" / "@page" margin-box syntax before conversion.
 *  - Page numbers use CSS counters (counter(page) / counter(pages)) rather
 *    than wkhtmltopdf's JavaScript-substituted <span class="page"> elements.
 *  - Default CLI flags differ (wkhtmltopdf flags are not valid for WeasyPrint).
 *
 * Configuration keys (mirror the existing wkpdf keys):
 *   weasyprint.exec           – path/name of the weasyprint binary (default: 'weasyprint')
 *   weasyprint.exporter_flags – extra CLI flags passed verbatim (default: '')
 *
 * @package  Billing
 * @since    5.x
 */
class Generator_WEasyPrint extends Generator_WkPdf {

	/**
	 * Path / name of the WeasyPrint executable.
	 * @var string
	 */
	protected $weasyprint_exec;

	public function __construct($options) {
		parent::__construct($options);

		$this->weasyprint_exec = Billrun_Util::getFieldVal(
			$options['exec'],
			Billrun_Factory::config()->getConfigValue('weasyprint.exec', 'weasyprint')
		);

		// Parent sets wkhtmltopdf-specific flags; override with WeasyPrint flags.
		$this->exporterFlags = Billrun_Util::getFieldVal(
			$options['exporter_flags'],
			Billrun_Factory::config()->getConfigValue('weasyprint.exporter_flags', '')
		);
	}

	// -------------------------------------------------------------------------
	// Core generation
	// -------------------------------------------------------------------------

	/**
	 * Generate a single account invoice using WeasyPrint.
	 *
	 * Mirrors the parent implementation but replaces the wkhtmltopdf exec call
	 * with header/footer injection + weasyprint.
	 *
	 * @param Mongodloid_Entity $account
	 * @param mixed             $lines
	 */
	public function generateAccountInvoices($account, $lines = FALSE) {
		Billrun_Factory::dispatcher()->trigger('beforeGeneratorEntity', array($this, &$account, &$lines));

		$this->addFolder($this->paths['html']);
		$this->addFolder($this->paths['pdf']);
		$this->addFolder($this->paths['tmp']);

		$this->view->assign('data', $account);
		$this->view->assign('details_keys', $this->getDetailsKeys());
		$this->view->assign('invoice_extra_params', @$this->invoice_extra_params);
		$this->addExtraParamsToCurrentView($this->invoice_extra_params);
		$this->view->setLines($lines);

		$this->tmp_paths = array(
			'header' => $this->paths['tmp'] . $account['aid'] . 'tmp_header.html',
			'footer' => $this->paths['tmp'] . $account['aid'] . 'tmp_footer.html',
		);

		$file_name = $account['billrun_key'] . "_" . $account['aid'] . "_" . $account['invoice_id'] . ".html";
		if (isset($account['file_name']) && !empty($account['file_name']) && !$this->is_fake_generation) {
			$pdf_name = $account['file_name'];
		} else {
			$pdf_name = $account['billrun_key'] . "_" . $account['aid'] . "_" . $account['invoice_id'] . ".pdf";
		}

		$html = $this->paths['html'] . $file_name;
		$pdf  = $this->paths['pdf']  . $pdf_name;

		$this->accountSpecificViewParams($account);

		Generator_Translations::load();
		Generator_Translations::setLanguage(
			isset($account['attributes']['invoice_language']) ? $account['attributes']['invoice_language'] : null
		);

		$invoice_html = $this->view->render($this->view_path . 'invoice.phtml');
		Billrun_Factory::dispatcher()->trigger('beforeInvoiceHTMLCommit', array(&$invoice_html, $this, $account));
		file_put_contents($html, $invoice_html);
		chmod($html, $this->filePermissions);
		Billrun_Factory::dispatcher()->trigger('afterInvoiceHTMLCommit', array(&$invoice_html, $this, $account));

		// Write header/footer temp files (reusing parent logic).
		$this->updateHtmlDynamicData($account);

		// Inject header/footer into the HTML before passing to WeasyPrint.
		$this->injectHeaderAndFooterIntoHtml($html);

		Billrun_Factory::log(
			'Generating invoice ' . $pdf_name . " to : $pdf (WeasyPrint)",
			Zend_Log::INFO
		);
		exec(escapeshellcmd($this->weasyprint_exec) . " {$this->exporterFlags} " . escapeshellarg($html) . " " . escapeshellarg($pdf));

		if (Billrun_Factory::config()->getConfigValue(self::$type . '.exclude_pages')) {
			$firstPage = $this->view_path . 'first_page/main.phtml';
			$lastPage  = $this->view_path . 'last_page/main.phtml';
			$this->excludeFirstAndLastPages($file_name, $pdf_name, $firstPage, $lastPage);
		}

		chmod($pdf, $this->filePermissions);
		$this->updateInvoicePropertyToBillrun($account, $pdf, $html);
		$this->signPdf($pdf);

		Billrun_Factory::dispatcher()->trigger('afterGeneratorEntity', array($this, &$account, &$lines));
	}

	// -------------------------------------------------------------------------
	// Header / footer injection
	// -------------------------------------------------------------------------

	/**
	 * Injects the rendered header and footer into the main invoice HTML file.
	 *
	 * WeasyPrint supports the "CSS Generated Content for Paged Media" draft spec:
	 * elements marked with `position: running(name)` are removed from normal
	 * flow and repeated in the corresponding @page margin box on every page.
	 *
	 * The CSS added here also maps wkhtmltopdf's `.page` / `.topage` spans to
	 * CSS counters so existing templates continue to display page numbers.
	 *
	 * @param string $htmlFile Absolute path to the invoice HTML file.
	 */
	protected function injectHeaderAndFooterIntoHtml($htmlFile) {
		$htmlContent = $this->sanitizeFilePaths(file_get_contents($htmlFile));

		$headerBody = $this->sanitizeFilePaths($this->extractBodyContent(file_get_contents($this->tmp_paths['header'])));
		$footerBody = $this->sanitizeFilePaths($this->extractBodyContent(file_get_contents($this->tmp_paths['footer'])));

		$marginTop    = Billrun_Factory::config()->getConfigValue('weasyprint.margin_top_px',    80);
		$marginBottom = Billrun_Factory::config()->getConfigValue('weasyprint.margin_bottom_px', 80);

		// CSS running elements + compatibility shim for wkhtmltopdf page-number spans.
		$pageStyle = "
<style>
/* WeasyPrint: repeat header/footer in @page margin boxes on every page */
@page {
    margin-top:    {$marginTop}px;
    margin-bottom: {$marginBottom}px;
    @top-center    { content: element(weasyprint-header); }
    @bottom-center { content: element(weasyprint-footer); }
}
.weasyprint-header-running { position: running(weasyprint-header); }
.weasyprint-footer-running { position: running(weasyprint-footer); }

/* Compatibility: map wkhtmltopdf page-number spans to CSS counters */
.page::after   { content: counter(page); }
.topage::after { content: counter(pages); }
</style>";

		$headerDiv = "<div class='weasyprint-header-running'>{$headerBody}</div>";
		$footerDiv = "<div class='weasyprint-footer-running'>{$footerBody}</div>";

		// Inject styles before </head>.
		$htmlContent = str_replace('</head>', $pageStyle . "\n</head>", $htmlContent);

		// Inject running elements as the first children of <body>.
		$htmlContent = preg_replace(
			'/<body([^>]*)>/i',
			'<body$1>' . "\n" . $headerDiv . "\n" . $footerDiv,
			$htmlContent,
			1
		);

		file_put_contents($htmlFile, $htmlContent);
	}

	/**
	 * Removes trailing slashes from file paths inside href and src attributes.
	 *
	 * wkhtmltopdf footer/header templates sometimes contain paths like
	 * href="[[invoiceTemplateFontAwesomeStyle]]/" where the trailing slash is
	 * left over after placeholder substitution. WeasyPrint treats such paths as
	 * directories and fails with a file-not-found error.
	 *
	 * @param  string $html
	 * @return string
	 */
	protected function sanitizeFilePaths($html) {
		// Quoted attributes: href="/path/" → href="/path"
		$html = preg_replace('/((?:href|src)=["\'])([^"\']*?)\\/+(["\'])/', '$1$2$3', $html);
		// Unquoted attributes: href=/path/> or href=/path/ > → href=/path>
		// This catches the wkhtmltopdf template pattern: href=<php echo $path />
		$html = preg_replace('/((?:href|src)=)([^"\'\s>]+?)\\/+(\s*\/?>)/', '$1$2$3', $html);
		return $html;
	}

	/**
	 * Extracts the inner content of a <body> element.
	 * Falls back to the full string when no <body> tag is present.
	 *
	 * @param  string $html
	 * @return string
	 */
	protected function extractBodyContent($html) {
		if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $matches)) {
			return trim($matches[1]);
		}
		return trim($html);
	}

	// -------------------------------------------------------------------------
	// Header content override
	// -------------------------------------------------------------------------

	/**
	 * Returns the default header HTML.
	 *
	 * Overrides the parent to strip wkhtmltopdf's JavaScript page-number spans;
	 * page numbers are handled via the `.page` / `.topage` CSS counter shim
	 * injected in injectHeaderAndFooterIntoHtml().
	 *
	 * @return string
	 */
	protected function getInvoiceHeaderContent() {
		return "
			{$this->tanent_css}
			<div class='table'>
				<table>
					<tbody>
					<tr>
						<td><img src='" . $this->logo_path . "' alt='' style='width:100px;object-fit:contain;'>&nbsp;&nbsp;" . $this->getCompanyName() . "</td>
						<td><div class='paging'> " . Generator_Translations::stranslate('DEF_INV_PAGE') . " <span class='page'></span>  " . Generator_Translations::stranslate('DEF_INV_OF') . " <span class='topage'></span></div></td>
					</tr>
					</tbody>
				</table>
			</div>";
	}
}
