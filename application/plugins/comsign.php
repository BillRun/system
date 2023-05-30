<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2017 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Plugin to handle comsign API
 *
 * @package  Application
 * @subpackage Plugins
 * @since    5.6
 */
class comsignPlugin extends Billrun_Plugin_BillrunPluginBase {

    protected $name = 'comsign';

	/**
	 * @param array $params params to return for headers
	 * @param array $invoiceData invoice that must be downloaded
	 *
	 * @return void
	 */
	public function onInvoiceDownload(array &$params, array $invoiceData)
	{
		$pdfPath = $invoiceData['invoice_file'];
		$tmpPath = sys_get_temp_dir() . '/files/comsign/' . $invoiceData['billrun_key'] . '/' . implode('_', [$invoiceData['billrun_key'], $invoiceData['aid'], $invoiceData['invoice_id']]) . '.pdf';
        $signedPath = Billrun_Util::getBillRunSharedFolderPath('files/comsign/signed/' . $invoiceData['billrun_key'] . '/' . implode('_', [$invoiceData['billrun_key'], $invoiceData['aid'], $invoiceData['invoice_id']]) . '.pdf');
		$this->signPdf($signedPath, $pdfPath, $tmpPath);
		$params['filename'] = $signedPath;
	}

    /**
     *  Signing pdf using ComSign API 
     */
    protected function signPdf($signedPath, $pdfPath, $tmpPath) {

        Billrun_Factory::log("ComSign Plugin: Preparing the data for signing PDF in path: " . $pdfPath, Zend_Log::DEBUG);

        // Create request
        $payload = [];
        if (isset($this->options['server']['host']) && isset($this->options['server']['pincode']) && isset($this->options['server']['certID'])) {
            $server_address = $this->options['server']['host'];
            $signUrl = $server_address . "/signature/signature.svc/json/SignPDF_PIN";
            $payload['CertID'] = $this->options['server']['certID'];
            $payload['Pincode'] = $this->options['server']['pincode'];
        } else {
            throw new Exception("ComSign Plugin: Server data is missing.");
        }
        if (isset($this->options['location']['page']) && isset($this->options['location']['top']) && isset($this->options['location']['left'])) {
            $payload['Page'] = $this->options['location']['page'];
            $payload['Top'] = $this->options['location']['top'];
            $payload['Left'] = $this->options['location']['left'];
        } else {
            throw new Exception("ComSign Plugin: Signature location data is missing.");
        }
        if (isset($this->options['size']['width']) && isset($this->options['size']['height'])) {
            $payload['Width'] = $this->options['size']['width'];
            $payload['Height'] = $this->options['size']['height'];
        } else {
            throw new Exception("ComSign Plugin: Signature size data is missing.");
        }
        if (isset($this->options['sign_image_name']) && $this->options['sign_image_name'] != "") {
            Billrun_Factory::log("ComSign Plugin: Loading signature image.", Zend_Log::DEBUG);
            $imagePath = APPLICATION_PATH . '/application/views/comsign/' . basename($this->options['sign_image_name']); 
            $imageData = base64_encode(file_get_contents($imagePath));
            if ($imageData !== false) {
                $payload['Image'] = $imageData;
            } else {
                Billrun_Factory::log("ComSign Plugin: Failed loading image $imagePath , using ComSign default.", Zend_Log::DEBUG);
            }
        } else {
            Billrun_Factory::log("ComSign Plugin: No image provided, using ComSign default.", Zend_Log::DEBUG);
        }
        try {
            $pdfData = base64_encode(file_get_contents($pdfPath));
            if ($pdfData === false) {
			    throw new Exception('ComSign Plugin: Failed loading pdf.');
            } else {
                $payload['InputFile'] = $pdfData;
            }
        } catch (Throwable $t) {
            throw new Exception('ComSign Plugin: Failed loading pdf.');
        }

        $headers = array(
            "Content-Type: application/json",
        );

        // Exec API
        $curl = curl_init();
        $optArray = array(
            CURLOPT_URL => $signUrl,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload),
        );
        if (isset($this->options['server']['port'])) {
            if (is_numeric($this->options['server']['port'])) {
                $optArray[CURLOPT_PORT] = $this->options['server']['port'];
            } else {
                Billrun_Factory::log("ComSign Plugin: Server port provided is not numeric, continue without it.");
            }
        }
        curl_setopt_array($curl, $optArray);
        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Handle response
        if ($response === false) {
            $error = curl_error($curl);
            throw new Exception("ComSign Plugin: Failed to sign the PDF file. Error: " . $error);
        } else {
            $response = json_decode($response, true);
        }

        if ($statusCode === 200) {
            // Save the signed PDF file
            if (!file_exists(dirname($tmpPath))) {
                mkdir(dirname($tmpPath), 0777, true);
            }
            chmod($tmpPath, 0777);

            $fileHandle = fopen($tmpPath, 'w+');
            if ($fileHandle !== false) {
                $bytesWritten = fwrite($fileHandle, base64_decode($response['SignedBytes']));
            
                if ($bytesWritten !== false) {
                    Billrun_Factory::log("ComSign Plugin: Bytes written to file: $bytesWritten");
                } else {
                    throw new Exception("ComSign Plugin: Failed to write bytes to file.");
                }
            
                fclose($fileHandle);
            } else {
                throw new Exception("ComSign Plugin: Failed to open the temp file.");
            }

            if (!file_exists(dirname($signedPath))) {
                mkdir(dirname($signedPath), 0777, true);
            }
            chmod($signedPath, 0777);

            if (rename($tmpPath, $signedPath)) {
                Billrun_Factory::log("ComSign Plugin: The PDF is signed in path " . $signedPath, Zend_Log::DEBUG);
            } else {
                throw new Exception("ComSign Plugin: Failed moving the file from: " . $tmpPath ." to: " . $signedPath);
            }
        } else {
            $error = curl_error($curl);
            throw new Exception("ComSign Plugin: Failed to sign the PDF file with status code " . $statusCode . " and error " . $error, Zend_Log::DEBUG);
        }
        curl_close($curl);
    }

    public function getConfigurationDefinitions() {
		return [[
				"type" => "text",
				"field_name" => "server.certID",
				"title" => "Certification ID",
				"editable" => true,
				"display" => true,
				"nullable" => false,
				"mandatory" => true
        ], [
            "type" => "password",
            "field_name" => "server.pincode",
            "title" => "Pincode",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            "mandatory" => true
        ], [
            "type" => "number",
            "field_name" => "server.host",
            "title" => "Host",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            "mandatory" => true
        ], [
            "type" => "text",
            "field_name" => "server.port",
            "title" => "Port",
            "editable" => true,
            "display" => true
        ], [
            "type" => "number",
            "field_name" => "location.page",
            "title" => "Page (signature location)",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            "mandatory" => true,
            "default_value" => 1
        ], [
            "type" => "number",
            "field_name" => "location.top",
            "title" => "Top (signature location)",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            "mandatory" => true,
            "default_value" => 0
        ], [
            "type" => "number",
            "field_name" => "location.left",
            "title" => "Left (signature location)",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            "mandatory" => true,
            "default_value" => 0
        ], [
            "type" => "number",
            "field_name" => "size.width",
            "title" => "Width (signature size)",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            "mandatory" => true,
            "default_value" => 100
        ], [
            "type" => "number",
            "field_name" => "size.height",
            "title" => "Height (signature size)",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            "mandatory" => true,
            "default_value" => 100
        ], [
            "type" => "string",
            "field_name" => "sign_image_name",
            "title" => "Image name",
            "editable" => true,
            "display" => true,
            "nullable" => false,
            "mandatory" => true
        ]];
        }
}
