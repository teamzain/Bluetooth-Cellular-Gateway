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
        } else {
            $message = "Failed to list controllers.";
        }
    } elseif (isset($_POST['scan_controllers_remove'])) {
        exec('sudo /usr/bin/bluetoothctl list 2>&1', $output, $retval);
        if ($retval === 0) {
            $controllerList2 = parseControllerListOutput2($output);
        } else {
            $message = "Failed to list controllers.";
        }
    } elseif (isset($_POST['select_controller_pair'])) {
        $index = $_POST['select_controller_index_pair'];
        $selectedPairController = $_POST["controller_mac_pair_$index"];
        if (selectController($selectedPairController)) { echo "<h2>Selected Controller: $controller</h2>";
 
            $message = "Selected Controller for Pairing: $selectedPairController";
        } else {
            $message = "Failed to select controller: $selectedPairController";
        }
    } elseif (isset($_POST['select_controller_remove'])) {
        $index = $_POST['select_controller_index_remove'];
        $selectedRemoveController = $_POST["controller_mac_remove_$index"];
        if (selectController($selectedRemoveController)) {
            $message = "Selected Controller for Removing: $selectedRemoveController";
        } else {
            $message = "Failed to select controller: $selectedRemoveController";
        }
    } elseif (isset($_POST['scan_devices_pair'])) {
        $selectedPairController = $_POST['controller_mac_pair'];
        if (verifyController($selectedPairController)) {
            $pairDeviceList = scanForDevices($selectedPairController);
        } else {
            $message = "Failed to verify controller: $selectedPairController";
        }
    } elseif (isset($_POST['scan_devices_remove'])) {
        $selectedRemoveController = $_POST['controller_mac_remove'];
        if (verifyController($selectedRemoveController)) {
            $removeDeviceList = scanForConnectedDevices($selectedRemoveController);
        } else {
            $message = "Failed to verify controller: $selectedRemoveController";
        }
    } elseif (isset($_POST['pair_device'])) {
        $selectedPairController = $_POST['controller_mac_pair'];
        $index3 = $_POST['pair_device_index'];
        $deviceMAC = $_POST["device_mac_pair_$index3"];
        if (pairDevice($selectedPairController, $deviceMAC)) {
            $message = "Successfully paired with device: $deviceMAC";
        } else {
            $message = "Failed to pair with device: $deviceMAC";
        }
    } elseif (isset($_POST['remove_device'])) {
        $selectedRemoveController = $_POST['controller_mac_remove'];
        $index4 = $_POST['remove_device_index'];
        $deviceMAC = $_POST["device_mac_remove_$index4"];
        if (removeDevice($selectedRemoveController, $deviceMAC)) {
            $message = "Successfully removed device: $deviceMAC";
            // Refresh device list after removal
            $removeDeviceList = scanForConnectedDevices($selectedRemoveController);
        } else {
            $message = "Failed to remove device: $deviceMAC";
        }
    } elseif (isset($_POST['fetch_bluetooth_info'])) {
        exec('sudo /usr/bin/bluetoothctl scan on 2>&1', $output, $retval);

    // Log output and return value for debugging
    error_log("Bluetooth scan output: " . implode("\n", $output));
    error_log("Return value: " . $retval);

    if ($retval === 0) {
        $message = "";
        // Parse the output to gather unique controller and device information
        $controllers = parseBluetoothOutput($output);
    } else {
        $message = "Failed to start scan.";
    }
       
}

}

/**
 * Parses the output of the Bluetooth scan to extract unique controller and device information.
 *
 * @param array $output The output from the bluetoothctl command.
 * @return array An array of unique controllers with their connected devices.
 */
function parseBluetoothOutput($output) {
    $controllers = [];
    $current_controller = null;

    foreach ($output as $line) {
        // Check for controller information
        if (preg_match('/Controller ([\w:]+) (.+) \[default\]/', $line, $matches)) {
            if ($current_controller) {
                addController($controllers, $current_controller);
            }
            $current_controller = [
                'mac' => $matches[1],
                'name' => $matches[2],
                'device' => null
            ];
        } elseif (preg_match('/Controller ([\w:]+) (.+)/', $line, $matches)) {
            if ($current_controller) {
                addController($controllers, $current_controller);
            }
            $current_controller = [
                'mac' => $matches[1],
                'name' => $matches[2],
                'device' => null
            ];
        }

        // Check for device information
        if (preg_match('/Device ([\w:]+) (.+)/', $line, $matches)) {
            if ($current_controller) {
                $current_controller['device'] = [
                    'mac' => $matches[1],
                    'name' => $matches[2]
                ];
            }
        }
    }

    // Add the last controller to the list
    if ($current_controller) {
        addController($controllers, $current_controller);
    }

    return $controllers;
}

/**
 * Adds a controller to the list if it's not already present (based on MAC address).
 *
 * @param array $controllers The array of controllers to add to.
 * @param array $controller The controller information to add.
 */
function addController(&$controllers, $controller) {
    // Check if the controller already exists (based on MAC address)
    foreach ($controllers as $existing) {
        if ($existing['mac'] === $controller['mac']) {
            return; // Controller already exists, skip adding
        }
    }
    $controllers[] = $controller;
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
function parseControllerListOutput2($output) {
    $controllerList2 = [];
    foreach ($output as $line) {
        if (preg_match('/Controller ([\w:]+)/', $line, $matches)) {
            $controller_mac = $matches[1];
            if (!in_array($controller_mac, $controllerList2)) {
                $controllerList2[] = $controller_mac;
            }
        }
    }
    return $controllerList2;
}
  // Function to select a Bluetooth controller and perform power on/agent on using expect script
    function selectController($controller_mac) {
        $cmd = "
            #!/usr/bin/expect -f
            spawn sudo /usr/bin/bluetoothctl
            expect -re \"\\[bluetooth\\]\# \"

            # Select the Bluetooth controller
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
            expect -re \"\\[bluetooth\\]\# \"

            # Power on the controller
            send \"power on\r\"
            expect {
                -re \"Changing power on succeeded\" {
                    send_user \"Power on successful\n\"
                }
                timeout {
                    send_user \"Power on timed out or failed\n\"
                    send \"exit\r\"
                    exit 1
                }
            }
            expect -re \"\\[bluetooth\\]\# \"

            # Enable agent
            send \"agent on\r\"
            expect {
                -re \"Agent registered\" {
                    send_user \"Agent registered\n\"
                }
                timeout {
                    send_user \"Agent registration timed out or failed\n\"
                    send \"exit\r\"
                    exit 1
                }
            }
            expect -re \"\\[bluetooth\\]\# \"

            interact   ;# Allow user to interact with bluetoothctl manually if needed
        ";
        return runExpectCommand($cmd);
    }

// Function to run an expect script command
function runExpectCommand($cmd) {
    $tmpFile = tempnam(sys_get_temp_dir(), 'expect_');
    file_put_contents($tmpFile, $cmd);
    chmod($tmpFile, 0700); // Make sure the script is executable

    // Log the path of the temporary file for debugging
    error_log("Temporary file path: $tmpFile");

    // Log the content of the Expect script (optional)
    $scriptContent = file_get_contents($tmpFile);
    error_log("Expect script content:\n$scriptContent");

    // Execute the Expect script and capture output and return value
    exec("expect $tmpFile 2>&1", $output, $retval);

    // Log the command executed
    error_log("Executed command: $cmd");

    // Log the output and return value for debugging
    error_log("Output: " . implode("\n", $output));
    error_log("Return value: $retval");

    unlink($tmpFile); // Remove the temporary file

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

        # Select the Bluetooth controller
        send \"select $controller\r\"
        expect -re \"\\[bluetooth\\]\# \"

        # Start scanning for devices
        send \"scan on\r\"
        sleep 20
        send \"scan off\r\"
        expect -re \"\\[bluetooth\\]\# \"

        # List devices
        send \"devices\r\"
        expect -re \"\\[bluetooth\\]\# \"
        
        # Capture output
        set devices_output {}
        expect {
            -re {Device ([\\w:]+) (.+)} {
                append devices_output \$expect_out(0,string) \"\n\"
                exp_continue
            }
        }
    ";
    $tmpFile = tempnam(sys_get_temp_dir(), 'expect_');
    file_put_contents($tmpFile, $cmd);
    chmod($tmpFile, 0700); // Make sure the script is executable
    exec("expect $tmpFile 2>&1", $output, $retval);
    unlink($tmpFile);

    $deviceList = [];
    foreach ($output as $line) {
        if (preg_match('/Device ([\\w:]+) (.+)/', $line, $matches)) {
            $deviceList[] = ['mac' => $matches[1], 'name' => $matches[2]];
        }
    }
    return $deviceList;
}

function scanForConnectedDevices($controller) {
    $cmd = "
        #!/usr/bin/expect -f

        spawn sudo /usr/bin/bluetoothctl
        expect -re \"\\[bluetooth\\]\# \"
        send \"select $controller\r\"
        expect -re \"\\[bluetooth\\]\# \"
        send \"devices\r\"
        expect {
            -re \"Device ([\\w:]+) (.+)\\n\" {
                set mac \$expect_out(1,string)
                set name \$expect_out(2,string)
                # Check if device is connected to the controller
                if {[isDeviceConnected $controller \$mac]} {
                    lappend devices [list \$mac \$name]
                }
                exp_continue
            }
            -re \"\\[bluetooth\\]\# \" {
                send \"quit\r\"
                expect eof
            }
        }
    ";

    $tmpFile = tempnam(sys_get_temp_dir(), 'expect_');
    file_put_contents($tmpFile, $cmd);
    chmod($tmpFile, 0700); // Make sure the script is executable

    exec("expect $tmpFile", $output, $retval);
    unlink($tmpFile);

    $deviceList = [];
    $seenDevices = []; // Track seen devices to avoid duplicates
    foreach ($output as $line) {
        if (preg_match('/Device ([\w:]+) (.+)/', $line, $matches)) {
            $mac = $matches[1];
            $name = $matches[2];
            // Ensure device is not already added
            if (!in_array($mac, $seenDevices)) {
                $deviceList[] = [
                    'mac' => $mac,
                    'name' => $name,
                    'controller' => $controller,
                ];
                $seenDevices[] = $mac; // Mark device as seen
            }
        }
    }
    return $deviceList;
}

// Function to pair with a Bluetooth device
/// Function to pair with a Bluetooth device
function pairDevice($controller, $deviceMAC) {
    // Construct the expect script command
    $cmd = "
        #!/usr/bin/expect -f

      
        expect -re \"\[bluetooth\]# \"
         # Power on the controller
            send \"power on\r\"
            expect {
                -re \"Changing power on succeeded\" {
                    send_user \"Power on successful\n\"
                }
                timeout {
                    send_user \"Power on timed out or failed\n\"
                    send \"exit\r\"
                    exit 1
                }
            }
            expect -re \"\\[bluetooth\\]\# \"

            # Enable agent
            send \"agent on\r\"
            expect {
                -re \"Agent registered\" {
                    send_user \"Agent registered\n\"
                }
                timeout {
                    send_user \"Agent registration timed out or failed\n\"
                    send \"exit\r\"
                    exit 1
                }
            }
                expect -re \"\[bluetooth\]# \"
        send \"pair $deviceMAC\r\"
        expect {
            -re \"Attempting to pair with $deviceMAC\" {
                exp_continue
            }
            \"\[bluetooth\]# \" {
                # Automatically respond with 'yes' to confirmation prompts
                send \"yes\r\"
                exp_continue
            }
            -re \"Failed to pair\" {
                send_user \"Failed to pair: $deviceMAC\n\"
                send \"exit\r\"
                exit 1
            }
        }
        expect -re \"\[bluetooth\]# \" ; # Wait for prompt to ensure completion
    ";
    

    
    // Call the function to execute the expect command and return its output
    return runExpectCommand($cmd);
}

// Function to remove a Bluetooth device
function removeDevice($controller, $deviceMAC) {
    $cmd = "
        #!/usr/bin/expect -f

        spawn sudo /usr/bin/bluetoothctl
        expect -re \"\\[bluetooth\\]\# \"
        send \"select $controller\r\"
        expect -re \"\\[bluetooth\\]\# \"
        send \"remove $deviceMAC\r\"
        expect {
            -re \"Device has been removed\" { 
                send_user \"Device removed: $deviceMAC\n\" 
                send \"exit\r\"
                exit 1 
            }
            -re \"Failed to remove device\" { 
                send_user \"Failed to remove device: $deviceMAC\n\" 
                send \"exit\r\"
                exit 1 
            }
        }
        expect -re \"\\[bluetooth\\]\# \" ; # Wait for prompt to ensure completion
    ";
    return runExpectCommand($cmd);
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Show Data</title>
    <style>
        @import url('https://fonts.googleapis.com/css?family=Poppins:400,500,600,700,800,900');
        body {
            font-family: 'Poppins', sans-serif;
            font-weight: 400;
            font-size: 15px;
            line-height: 1.7;
            color: #1f2029;
            background-color: #fff;
            background-image: url('https://assets.codepen.io/1462889/back-page.svg');
            background-position: center;
            background-repeat: no-repeat;
            background-size: 101%;
        }
        .button {
            position: relative;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 15px;
            line-height: 2;
            height: 50px;
            transition: all 200ms linear;
            border-radius: 4px;
            width: 240px;
            letter-spacing: 1px;
            display: inline-flex; /* Display buttons inline */
            margin-right: 10px; /* Adjust margin between buttons */
            justify-content: center;
            align-items: center;
            text-align: center;
            border: none;
            cursor: pointer;
            background-color: #102770;
            color: #ffeba7;
            box-shadow: 0 12px 35px 0 rgba(16, 39, 112, .25);
        }
        .button:hover {
            background-color: #ffeba7;
            color: #102770;
        }
        .form-container {
            margin-bottom: 20px; /* Add some bottom margin for spacing */
        }
        .form-container form {
            display: inline-block; /* Ensure forms display in a line */
            margin-right: 20px; /* Adjust margin between forms */
        }
        .content-table {
            width: 80%;
            margin: 25px auto;
            border-collapse: collapse;
            font-size: 0.9em;
            border-radius: 5px 5px 0 0;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
        }
        .content-table thead tr {
            background-color: #009879;
            color: #ffffff;
            text-align: left;
            font-weight: bold;
        }
        .content-table th,
        .content-table td {
            padding: 12px 15px;
            border: 1px solid #dddddd; /* Add border to cells */
        }
        .content-table tbody tr {
            border-bottom: 1px solid #dddddd;
        }
        .content-table tbody tr:nth-of-type(even) {
            background-color: #f3f3f3;
        }
        .content-table tbody tr:hover {
            background-color: #f1f1f1; /* Add hover effect */
        }
        .content-table tbody tr:last-of-type {
            border-bottom: 2px solid #009879;
        }
        .content-table tbody tr.active-row {
            font-weight: bold;
            color: #009879;
        }
    </style>
</head>
<body>
<h2>Bluetooth Device Management</h2>
<?php if ($message): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
<?php endif; ?>

<div class="form-container">
    <!-- Form to pair a device -->
    <form method="POST">
        <button type="submit" name="scan_controllers_pair" class="button">Pair a Device</button>
    </form>

    <!-- Form to remove a device -->
    <form method="POST">
        <button type="submit" name="scan_controllers_remove" class="button">Remove a Device</button>
    </form>

    <!-- Add a new button to fetch Bluetooth adapter info -->
    <form method="POST">
        <button type="submit" name="fetch_bluetooth_info" class="button">Display  Adapter</button>
    </form>

</div>

<!-- Display controller list for pairing -->
<?php if (!empty($controllerList)): ?>
    <form method="POST">
        <h4>Select a Controller</h4>
        <ul>
            <?php foreach ($controllerList as $index => $controller): ?>
                <li>
                    <label>
                        <input type="radio" name="select_controller_index_pair" value="<?php echo $index; ?>">
                        <?php echo htmlspecialchars($controller); ?>
                    </label>
                    <input type="hidden" name="controller_mac_pair_<?php echo $index; ?>" value="<?php echo htmlspecialchars($controller); ?>">
                </li>
            <?php endforeach; ?>
        </ul>
        <button type="submit" name="select_controller_pair" class="button">Select Controller</button>
    </form>
<?php endif; ?>

<!-- Form to scan for devices to pair -->
<?php if ($selectedPairController): ?>
    <form method="POST">
        <h4>Selected Controller: <?php echo htmlspecialchars($selectedPairController); ?></h4>
        <input type="hidden" name="controller_mac_pair" value="<?php echo htmlspecialchars($selectedPairController); ?>">
        <button type="submit" name="scan_devices_pair" class="button">Scan for Devices</button>
    </form>
<?php endif; ?>

<!-- Display list of devices to pair -->
<?php if (!empty($pairDeviceList)): ?>
    <form method="POST">
        <h4>Available Devices</h4>
        <ul>
            <?php foreach ($pairDeviceList as $index => $device): ?>
                <li>
                    <label>
                        <input type="radio" name="pair_device_index" value="<?php echo $index; ?>">
                        <?php echo htmlspecialchars($device['name']); ?> (<?php echo htmlspecialchars($device['mac']); ?>)
                    </label>
                    <input type="hidden" name="device_mac_pair_<?php echo $index; ?>" value="<?php echo htmlspecialchars($device['mac']); ?>">
                </li>
            <?php endforeach; ?>
        </ul>
        <input type="hidden" name="controller_mac_pair" value="<?php echo htmlspecialchars($selectedPairController); ?>">
        <button type="submit" name="pair_device" class="button">Pair Device</button>
    </form>
<?php endif; ?>

<!-- Display controller list for removing -->
<?php if (!empty($controllerList2)): ?>
    <form method="POST">
    <h4>Select a Controller (Please, select a controller where a device is connected):</h4>

        <ul>
            <?php foreach ($controllerList2 as $index => $controller): ?>
                <li>
                    <label>
                        <input type="radio" name="select_controller_index_remove" value="<?php echo $index; ?>">
                        <?php echo htmlspecialchars($controller); ?>
                    </label>
                    <input type="hidden" name="controller_mac_remove_<?php echo $index; ?>" value="<?php echo htmlspecialchars($controller); ?>">
                </li>
            <?php endforeach; ?>
        </ul>
        <button type="submit" name="select_controller_remove" class="button">Select Controller</button>
    </form>
<?php endif; ?>

<!-- Form to scan for connected devices to remove -->
<?php if ($selectedRemoveController): ?>
    <form method="POST">
        <h4>Selected Controller: <?php echo htmlspecialchars($selectedRemoveController); ?></h4>
        <input type="hidden" name="controller_mac_remove" value="<?php echo htmlspecialchars($selectedRemoveController); ?>">
        <button type="submit" name="scan_devices_remove" class="button">Scan for Connected Devices</button>
    </form>
<?php endif; ?>

<!-- Display list of connected devices to remove -->
<?php if (!empty($removeDeviceList)): ?>
    <form method="POST">
        <h4>Connected Devices</h4>
        <ul>
            <?php foreach ($removeDeviceList as $index => $device): ?>
                <li>
                    <label>
                        <input type="radio" name="remove_device_index" value="<?php echo $index; ?>">
                        <?php echo htmlspecialchars($device['name']); ?> (<?php echo htmlspecialchars($device['mac']); ?>) - Controller: <?php echo htmlspecialchars($device['controller']); ?>
                    </label>
                    <input type="hidden" name="device_mac_remove_<?php echo $index; ?>" value="<?php echo htmlspecialchars($device['mac']); ?>">
                </li>
            <?php endforeach; ?>
        </ul>
        <input type="hidden" name="controller_mac_remove" value="<?php echo htmlspecialchars($selectedRemoveController); ?>">
        <button type="submit" name="remove_device" class="button">Remove Device</button>
    </form>
<?php endif; ?>
<?php if (!empty($controllers)) { ?>
        <table class="content-table">
            <thead>
                <tr>
                    <th>Controller</th>
                    <th>Device</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php foreach ($controllers as $controller) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($controller['mac']) . " - " . htmlspecialchars($controller['name']); ?></td>
                        <td>
                            <?php if ($controller['device']) {
                                echo htmlspecialchars($controller['device']['mac']) . " - " . htmlspecialchars($controller['device']['name']);
                            } else {
                                echo "No device connected";
                            } ?>
                        </td>
                       
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>

</body>
</html>
