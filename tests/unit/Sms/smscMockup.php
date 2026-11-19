<?php

/**
 * SMSC mockup of SmppTest: a minimal SMPP 3.4 server on a free local port, for one connection.
 * Prints the port, answers every PDU of the client with a success response and prints the PDU as a JSON line.
 * Ends when the client disconnects (or after 10 seconds without a client / a PDU).
 */
const TIMEOUT = 10;

function readBytes($connection, $length) {
	$data = '';
	while (strlen($data) < $length) {
		$chunk = fread($connection, $length - strlen($data));
		if ($chunk === false || $chunk === '') { // the client disconnected or the timeout passed
			return null;
		}
		$data .= $chunk;
	}
	return $data;
}

function cString($body, &$offset) {
	$end = strpos($body, "\0", $offset);
	$value = substr($body, $offset, $end - $offset);
	$offset = $end + 1;
	return $value;
}

$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $error);
if (!$server) {
	fwrite(STDERR, "SMSC mockup: cannot listen on a local port: $error\n");
	exit(1);
}
echo substr(strrchr(stream_socket_get_name($server, false), ':'), 1) . "\n";

$connection = @stream_socket_accept($server, TIMEOUT);
if (!$connection) {
	exit(0);
}
stream_set_timeout($connection, TIMEOUT);
$commands = [0x02 => 'bind_transmitter', 0x04 => 'submit_sm', 0x06 => 'unbind'];
while (($header = readBytes($connection, 16)) !== null) {
	$pdu = unpack('Nlength/Nid/Nstatus/Nsequence', $header);
	$body = $pdu['length'] > 16 ? readBytes($connection, $pdu['length'] - 16) : '';
	if ($body === null) {
		break;
	}
	$received = ['command' => $commands[$pdu['id']] ?? sprintf('0x%08x', $pdu['id'])];
	$responseBody = '';
	$offset = 0;
	if ($received['command'] === 'bind_transmitter') {
		$received['system_id'] = cString($body, $offset);
		$received['password'] = cString($body, $offset);
		$responseBody = "SMSC\0"; // system_id of the SMSC
	} else if ($received['command'] === 'submit_sm') {
		cString($body, $offset); // service_type
		$offset += 2; // source ton, npi
		$received['from'] = cString($body, $offset);
		$offset += 2; // destination ton, npi
		$received['to'] = cString($body, $offset);
		$offset += 3; // esm_class, protocol_id, priority_flag
		cString($body, $offset); // schedule_delivery_time
		cString($body, $offset); // validity_period
		$offset += 4; // registered_delivery, replace_if_present_flag, data_coding, sm_default_msg_id
		$received['text'] = substr($body, $offset + 1, ord($body[$offset])); // sm_length, short_message
		$responseBody = "MSG-1\0"; // message_id
	}
	echo json_encode($received) . "\n";
	fwrite($connection, pack('NNNN', 16 + strlen($responseBody), 0x80000000 | $pdu['id'], 0, $pdu['sequence']) . $responseBody);
}
