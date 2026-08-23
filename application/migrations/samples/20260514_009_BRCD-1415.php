<?php

/**
 * BRCD-1415 - Add the 'invoice_ready' email template to config if absent.
 */
return new class extends Billrun_Migration_Base {

	public function getTaskCode() {
		return 'BRCD-1415';
	}

	public function run() {
		if (isset($this->lastConfig['email_templates']['invoice_ready'])) {
			return;
		}
		if (!isset($this->lastConfig['email_templates']) || !is_array($this->lastConfig['email_templates'])) {
			$this->lastConfig['email_templates'] = [];
		}
		$this->lastConfig['email_templates']['invoice_ready'] = [
			'subject' => 'Your invoice is ready',
			'content' => "<pre>\nHello [[customer_firstname]],\n\nThe invoice for [[cycle_range]] is ready and is attached to this email.\nFor any questions, please contact us at [[company_email]].\n\n[[company_name]]</pre>\n",
			'html_translation' => [
				'invoice_id',
				'invoice_total',
				'invoice_due_date',
				'cycle_range',
				'company_email',
				'company_name',
			],
		];
	}

};
