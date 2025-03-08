#!/bin/bash

# Function to scan for Bluetooth devices and controllers
function scan_bluetooth {
    echo "Scanning for Bluetooth devices and controllers..."
    echo "power on" | bluetoothctl  # Ensure Bluetooth is powered on
    echo "scan on" | bluetoothctl   # Start scanning for devices
    sleep 10                       # Adjust scan duration as needed
    echo "scan off" | bluetoothctl  # Stop scanning
    bluetoothctl devices           # List discovered devices
}

# Function to select a Bluetooth controller
function select_controller {
    local controller_mac="$1"
    echo "Selecting Bluetooth controller with MAC: $controller_mac"
    echo "select $controller_mac" | bluetoothctl
}

# Function to pair a device with the selected controller
function pair_device {
    local controller_mac="$1"
    local device_mac="$2"
    echo "Pairing device with MAC: $device_mac to controller with MAC: $controller_mac"
    echo "pair $device_mac" | bluetoothctl
}

# Function to remove a device from the selected controller
function remove_device {
    local controller_mac="$1"
    local device_mac="$2"
    echo "Removing device with MAC: $device_mac from controller with MAC: $controller_mac"
    echo "remove $device_mac" | bluetoothctl
}

# Main execution starts here

case "$1" in
    scan)
        scan_bluetooth
        ;;
    select)
        select_controller "$2"
        ;;
    pair)
        pair_device "$2" "$3"
        ;;
    remove)
        remove_device "$2" "$3"
        ;;
    *)
        echo "Usage: $0 {scan|select <controller_mac>|pair <controller_mac> <device_mac>|remove <controller_mac> <device_mac>}"
        exit 1
        ;;
esac

exit 0
