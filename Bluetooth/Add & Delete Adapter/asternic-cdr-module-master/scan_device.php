<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$message = '';
$controllerList = [];
$pairDeviceList = [];
$removeDeviceList = [];
$selectedPairController = '';
$selectedRemoveController = '';

// Handling POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['scan_controllers_pair'])) {
        exec('sudo /usr/bin/bluetoothctl list 2>&1', $output, $retval);
        if ($retval === 0) {
            $controllerList = parseControllerListOutput($output);
            echo json_encode(['success' => true, 'controllerList' => $controllerList]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to list controllers.']);
            exit;
        }
    } elseif (isset($_POST['scan_controllers_remove'])) {
        exec('sudo /usr/bin/bluetoothctl list 2>&1', $output, $retval);
        if ($retval === 0) {
            $controllerList = parseControllerListOutput($output);
            echo json_encode(['success' => true, 'controllerList' => $controllerList]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to list controllers.']);
            exit;
        }
    } elseif (isset($_POST['select_controller_pair'])) {
        $index = $_POST['select_controller_index_pair'];
        $selectedPairController = $_POST["controller_mac_pair_$index"];
        if (selectController($selectedPairController)) {
            echo json_encode(['success' => true, 'message' => "Selected Controller for Pairing: $selectedPairController"]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => "Failed to select controller: $selectedPairController"]);
            exit;
        }
    } elseif (isset($_POST['select_controller_remove'])) {
        $index = $_POST['select_controller_index_remove'];
        $selectedRemoveController = $_POST["controller_mac_remove_$index"];
        if (selectController($selectedRemoveController)) {
            echo json_encode(['success' => true, 'message' => "Selected Controller for Removing: $selectedRemoveController"]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => "Failed to select controller: $selectedRemoveController"]);
            exit;
        }
    } elseif (isset($_POST['scan_devices_pair'])) {
        $selectedPairController = $_POST['controller_mac_pair'];
        if (verifyController($selectedPairController)) {
            $pairDeviceList = scanForDevices($selectedPairController);
            echo json_encode(['success' => true, 'pairDeviceList' => $pairDeviceList]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => "Failed to verify controller: $selectedPairController"]);
            exit;
        }
    } elseif (isset($_POST['scan_devices_remove'])) {
        $selectedRemoveController = $_POST['controller_mac_remove'];
        if (verifyController($selectedRemoveController)) {
            $removeDeviceList = scanForConnectedDevices($selectedRemoveController);
            echo json_encode(['success' => true, 'removeDeviceList' => $removeDeviceList]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => "Failed to verify controller: $selectedRemoveController"]);
            exit;
        }
    } elseif (isset($_POST['pair_device'])) {
        $selectedPairController = $_POST['controller_mac_pair'];
        $index3 = $_POST['pair_device_index'];
        $deviceMAC = $_POST["device_mac_pair_$index3"];
        if (pairDevice($selectedPairController, $deviceMAC)) {
            echo json_encode(['success' => true, 'message' => "Successfully paired with device: $deviceMAC"]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => "Failed to pair with device: $deviceMAC"]);
            exit;
        }
    } elseif (isset($_POST['remove_device'])) {
        $selectedRemoveController = $_POST['controller_mac_remove'];
        $index4 = $_POST['remove_device_index'];
        $deviceMAC = $_POST["device_mac_remove_$index4"];
        if (removeDevice($selectedRemoveController, $deviceMAC)) {
            $removeDeviceList = scanForConnectedDevices($selectedRemoveController);
            echo json_encode(['success' => true, 'message' => "Successfully removed device: $deviceMAC", 'removeDeviceList' => $removeDeviceList]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => "Failed to remove device: $deviceMAC"]);
            exit;
        }
    }
}

// Function to verify if a Bluetooth controller is selected and active
function verifyController($controller_mac) {
    $cmd = "
        #!/usr/bin/expect -f

        spawn sudo /usr/bin/bluetoothctl
        expect -re \"\\[bluetooth\\]\# \"
        send \"show $controller_mac\r\"
        expect {
            -re \"Controller $controller_mac .+\" { 
                send_user \"Controller verified: $controller_mac\n\" 
            }
            -re \".*\\[bluetooth\\].*not available.*\" { 
                send_user \"Controller not available: $controller_mac\n\" 
                send \"exit\r\"
                exit 1 
            }
            -re \".*\\[bluetooth\\].*No such device.*\" {
                send_user \"Controller not found: $controller_mac\n\"
                send \"exit\r\"
                exit 1
            }
        }
        expect -re \"\\[bluetooth\\]\# \" ; # Wait for prompt to ensure completion
    ";
    return runExpectCommand($cmd);
}

// Function to parse the list of Bluetooth controllers
function parseControllerListOutput($output) {
    $controllerList = [];
    foreach ($output as $line) {
        if (preg_match('/Controller ([\w:]+)/', $line, $matches)) {
            $controller_mac = $matches[1];
            if (!in_array($controller_mac, $controllerList)) {
                $controllerList[] = $controller_mac;
            }
        }
    }
    return $controllerList;
}

// Function to select a Bluetooth controller using expect script
function selectController($controller_mac) {
    $cmd = "
        #!/usr/bin/expect -f

        spawn sudo /usr/bin/bluetoothctl
        expect -re \"\\[bluetooth\\]\# \"
        send \"select $controller_mac\r\"
        expect {
            -re \"Controller $controller_mac .+\" { 
                send_user \"Controller selected: $controller_mac\n\" 
            }
            -re \".*\\[bluetooth\\].*failed.*\" { 
                send_user \"Failed to select controller: $controller_mac\n\" 
                send \"exit\r\"
                exit 1 
            }
        }
        expect -re \"\\[bluetooth\\]\# \" ; # Wait for prompt to ensure completion
        interact   # Allow user to interact with bluetoothctl manually if needed
    ";
    return runExpectCommand($cmd);
}

// Function to run an expect script command
// Helper function to run expect commands
function runExpectCommand($cmd) {
    $tmpFile = tempnam(sys_get_temp_dir(), 'expect_');
    file_put_contents($tmpFile, $cmd);
    chmod($tmpFile, 0700); // Make sure the script is executable
    exec("expect $tmpFile 2>&1", $output, $retval);
    unlink($tmpFile);

    return [
        'output' => $output,
        'retval' => $retval
    ];
}

// Function to scan for Bluetooth devices
function scanForDevices($controller) {
    $cmd = "
        #!/usr/bin/expect -f

        spawn sudo /usr/bin/bluetoothctl
        expect -re \"\\[bluetooth\\]\# \"
        send \"select $controller\r\"
        expect -re \"\\[bluetooth\\]\# \"
        send \"scan on\r\"
        expect -re \"\\[bluetooth\\]\# \"
        sleep 2
        send \"scan off\r\"
        expect -re \"\\[bluetooth\\]\# \"
        send \"devices\r\"
        expect {
            -re {Device ([\\w:]+) (.+)} {
                set device_mac \$expect_out(1,string)
                set device_name \$expect_out(2,string)
                lappend devices \$device_mac \$device_name
            }
        }
        expect -re \"\\[bluetooth\\]\# \" ; # Wait for prompt to ensure completion
    ";
    $tmpFile = tempnam(sys_get_temp_dir(), 'expect_');
    file_put_contents($tmpFile, $cmd);
    chmod($tmpFile, 0700); // Make sure the script is executable
    exec("expect $tmpFile 2>&1", $output, $retval);
    unlink($tmpFile);

    $deviceList = [];
    foreach ($output as $line) {
        if (preg_match('/Device ([\\w:]+) (.+)/', $line, $matches)) {
            $deviceList[$matches[1]] = $matches[2];
        }
    }
    return $deviceList;
}

// Function to pair a Bluetooth device
function pairDevice($controller, $device_mac) {
    exec("sudo /usr/bin/bluetoothctl pair $device_mac", $output, $retval);
    if ($retval === 0) {
        return true;
    } else {
        return false;
    }
}

// Function to remove a paired Bluetooth device
function removeDevice($controller, $device_mac) {
    exec("sudo /usr/bin/bluetoothctl remove $device_mac", $output, $retval);
    if ($retval === 0) {
        return true;
    } else {
        return false;
    }
}
?>

