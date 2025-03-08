<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $controller = escapeshellarg($data['controller']);
    $device = escapeshellarg($data['device']);

    $script = <<<EOT
#!/usr/bin/expect -f
spawn bluetoothctl
expect "Agent registered"
send -- "list\r"
expect "Controller $controller"
send -- "select $controller\r"
send -- "remove $device\r"
expect "Device has been removed"
send -- "scan on\r"
expect "$device"
send -- "pair $device\r"
expect "Pairing successful"
send -- "connect $device\r"
expect "Connection successful"
send -- "trust $device\r"
expect "trust succeeded"
send -- "exit\r"
expect eof
EOT;

    file_put_contents('/tmp/pair_device.exp', $script);
    chmod('/tmp/pair_device.exp', 0755);
    exec('expect /tmp/pair_device.exp', $output, $return_var);

    if ($return_var === 0) {
        echo json_encode(['message' => 'Pairing successful']);
    } else {
        echo json_encode(['message' => 'Pairing failed', 'error' => $output]);
    }
}
?>
