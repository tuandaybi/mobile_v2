from __future__ import annotations
from dataclasses import dataclass
from typing import Optional, Dict, Any, List

from pymobiledevice3.lockdown import LockdownClient
from pymobiledevice3.usbmux import USBMux
from pymobiledevice3.services.diagnostics import DiagnosticsService

def human_bytes(n: Optional[int]) -> Optional[str]:
    if n is None: 
        return None
    step = 1024.0
    units = ['B','KB','MB','GB','TB','PB']
    s = float(n)
    for u in units:
        if s < step:
            return f"{s:.2f} {u}"
        s /= step
    return f"{s:.2f} EB"

def list_udids() -> List[str]:
    mux = USBMux()
    return [d.udid for d in mux.devices]

@dataclass
class Snapshot:
    raw: Dict[str, Any]
    udid: Optional[str]
    serial: Optional[str]
    device_name: Optional[str]
    product_type: Optional[str]
    ios_version: Optional[str]
    model_number: Optional[str]
    hardware_model: Optional[str]
    region_info: Optional[str]
    wifi_address: Optional[str]
    bt_address: Optional[str]
    phone_number: Optional[str]
    imei: Optional[str]
    iccid: Optional[str]
    baseband: Optional[str]
    battery_percent: Optional[int]
    battery_cycle_count: Optional[int]
    battery_max_capacity: Optional[int]
    disk_total_bytes: Optional[int]
    disk_free_bytes: Optional[int]
    data_total_bytes: Optional[int]
    data_free_bytes: Optional[int]

    def to_json(self) -> Dict[str, Any]:
        return {
            "udid": self.udid,
            "serial": self.serial,
            "device_name": self.device_name,
            "product_type": self.product_type,
            "ios_version": self.ios_version,
            "model_number": self.model_number,
            "hardware_model": self.hardware_model,
            "region_info": self.region_info,
            "wifi_address": self.wifi_address,
            "bluetooth_address": self.bt_address,
            "phone_number": self.phone_number,
            "imei": self.imei,
            "iccid": self.iccid,
            "baseband_version": self.baseband,
            "battery_percent": self.battery_percent,
            "battery_cycle_count": self.battery_cycle_count,
            "battery_max_capacity": self.battery_max_capacity,
            "storage_total_bytes": self.disk_total_bytes,
            "storage_total_human": human_bytes(self.disk_total_bytes),
            "storage_free_bytes": self.disk_free_bytes,
            "storage_free_human": human_bytes(self.disk_free_bytes),
            "data_partition_total": self.data_total_bytes,
            "data_partition_total_human": human_bytes(self.data_total_bytes),
            "data_partition_free": self.data_free_bytes,
            "data_partition_free_human": human_bytes(self.data_free_bytes),
        }

def read_snapshot(udid: Optional[str]=None) -> Snapshot:
    ld = LockdownClient(udid=udid)
    vals = ld.all_values or {}
    batt = {}
    disk = {}
    try:
        diag = DiagnosticsService(ld)
        batt = diag.battery() or {}
        disk = diag.disk_usage() or {}
    except Exception:
        pass

    return Snapshot(
        raw=vals,
        udid=vals.get("UniqueDeviceID"),
        serial=vals.get("SerialNumber"),
        device_name=vals.get("DeviceName"),
        product_type=vals.get("ProductType"),
        ios_version=vals.get("ProductVersion"),
        model_number=vals.get("ModelNumber"),
        hardware_model=vals.get("HardwareModel"),
        region_info=vals.get("RegionInfo"),
        wifi_address=vals.get("WiFiAddress"),
        bt_address=vals.get("BluetoothAddress"),
        phone_number=vals.get("PhoneNumber"),
        imei=vals.get("InternationalMobileEquipmentIdentity") or vals.get("IMEI"),
        iccid=vals.get("IntegratedCircuitCardIdentity") or vals.get("ICCID"),
        baseband=vals.get("BasebandVersion"),
        battery_percent=batt.get("BatteryCurrentCapacity"),
        battery_cycle_count=batt.get("CycleCount"),
        battery_max_capacity=batt.get("NominalChargeCapacity") or batt.get("MaximumCapacity"),
        disk_total_bytes=disk.get("TotalDiskCapacity"),
        disk_free_bytes=disk.get("TotalSystemAvailable"),
        data_total_bytes=disk.get("TotalDataCapacity"),
        data_free_bytes=disk.get("TotalDataAvailable"),
    )
