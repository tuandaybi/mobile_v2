#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
ios_prober: Dump ALL info that Python can read from an iPhone/iPad via USB,
compatible with pymobiledevice3 4.24.x

Commands:
  - list                : list device ids (serial/udid) seen by usbmux
  - read [--udid ...]   : basic summary (like before)
  - dump [--udid ...]   : FULL dump (lockdown all_values + diagnostics [+ apps])
      --include-apps    : also include installed apps list (can be slow)
      --pretty          : pretty printed JSON
      --out FILE.json   : write to file
  - send [--udid ...]   : POST summary JSON to your Laravel API (config.json)
"""
import argparse
import json
import sys
import requests
from dataclasses import dataclass
from typing import Optional, List, Dict, Any

from pymobiledevice3.usbmux import list_devices as mux_list_devices
from pymobiledevice3.lockdown import create_using_usbmux
from pymobiledevice3.services.diagnostics import DiagnosticsService

# Installation Proxy (apps list) – tùy phiên bản có thể khác tên; bọc try/except cho chắc
try:
    from pymobiledevice3.services.installation_proxy import InstallationProxyService
except Exception:
    InstallationProxyService = None


def human_bytes(n: Optional[int]):
    if n is None: return None
    step = 1024.0
    units = ['B','KB','MB','GB','TB','PB']
    s = float(n)
    for u in units:
        if s < step: return f"{s:.2f} {u}"
        s /= step
    return f"{s:.2f} EB"


@dataclass
class DeviceSnapshot:
    udid: Optional[str]
    serial: Optional[str]
    device_name: Optional[str]
    product_type: Optional[str]
    ios_version: Optional[str]
    battery_percent: Optional[int]
    storage_total: Optional[int]
    storage_free: Optional[int]

    def to_json(self) -> Dict[str, Any]:
        return {
            "udid": self.udid,
            "serial": self.serial,
            "device_name": self.device_name,
            "product_type": self.product_type,
            "ios_version": self.ios_version,
            "battery_percent": self.battery_percent,
            "storage_total": self.storage_total,
            "storage_total_human": human_bytes(self.storage_total),
            "storage_free": self.storage_free,
            "storage_free_human": human_bytes(self.storage_free),
        }


def list_connected_devices() -> List[str]:
    return [d.serial for d in mux_list_devices()]


def _diagnostics(ld) -> Dict[str, Any]:
    info = {"battery": {}, "disk": {}}
    try:
        diag = DiagnosticsService(ld)
        try:
            info["battery"] = diag.battery() or {}
        except Exception:
            info["battery"] = {}
        try:
            info["disk"] = diag.disk_usage() or {}
        except Exception:
            info["disk"] = {}
    except Exception:
        pass
    return info


def read_device(udid: Optional[str] = None) -> DeviceSnapshot:
    ld = create_using_usbmux(serial=udid, autopair=True)
    vals = ld.all_values or {}

    device_udid  = vals.get("UniqueDeviceID") or getattr(ld, "udid", None)
    device_name  = vals.get("DeviceName") or vals.get("iTunesDeviceName") or "Unknown"
    serial       = vals.get("SerialNumber") or "Unknown"
    product_type = vals.get("ProductType") or getattr(ld, "product_type", None) or "Unknown"
    ios_version  = vals.get("ProductVersion") or getattr(ld, "product_version", None) or "Unknown"

    diag = _diagnostics(ld)
    batt = diag.get("battery", {})
    disk = diag.get("disk", {})

    return DeviceSnapshot(
        udid=device_udid,
        serial=serial,
        device_name=device_name,
        product_type=product_type,
        ios_version=ios_version,
        battery_percent=batt.get("BatteryCurrentCapacity"),
        storage_total=disk.get("TotalDiskCapacity"),
        storage_free=disk.get("TotalSystemAvailable"),
    )


def dump_all(udid: Optional[str] = None, include_apps: bool = False) -> Dict[str, Any]:
    """
    Trả về JSON lớn gồm:
      - lockdown_all: toàn bộ ld.all_values
      - diagnostics: { battery, disk }
      - summary: tóm tắt các trường hay dùng
      - (optional) apps: danh sách app đã cài (nếu include_apps=True)
    """
    ld = create_using_usbmux(serial=udid, autopair=True)
    vals = ld.all_values or {}
    diag = _diagnostics(ld)

    # Tóm tắt nhanh
    summary = {
        "udid": vals.get("UniqueDeviceID") or getattr(ld, "udid", None),
        "serial": vals.get("SerialNumber"),
        "device_name": vals.get("DeviceName") or vals.get("iTunesDeviceName"),
        "product_type": vals.get("ProductType"),
        "ios_version": vals.get("ProductVersion"),
    }

    data: Dict[str, Any] = {
        "summary": summary,
        "lockdown_all": vals,      # toàn bộ khóa/giá trị từ Lockdown/MobileGestalt
        "diagnostics": diag,       # pin + dung lượng
    }

    if include_apps:
        apps = []
        try:
            if InstallationProxyService is not None:
                ip = InstallationProxyService(ld)
                # filter=None để lấy tất cả. Có thể truyền dict filters nếu muốn.
                for app in ip.get_apps():
                    # Mỗi app là dict rất nhiều khóa; thêm nguyên bản
                    apps.append(app)
            else:
                data["apps_error"] = "InstallationProxyService not available in this pymobiledevice3 build."
        except Exception as e:
            data["apps_error"] = f"{type(e).__name__}: {e}"
        data["apps"] = apps

    return data


def send_to_api(snap: DeviceSnapshot, api_url: str, token: str):
    headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}
    r = requests.post(api_url, headers=headers, json=snap.to_json(), timeout=15)
    print(r.status_code, r.text)


def dump_device(udid: Optional[str] = None) -> dict:
    ld = create_using_usbmux(serial=udid, autopair=True)
    return ld.all_values or {}

def main():
    p = argparse.ArgumentParser()
    sub = p.add_subparsers(dest="cmd", required=True)

    sub.add_parser("list")

    sp_read = sub.add_parser("read")
    sp_read.add_argument("--pretty", action="store_true")
    sp_read.add_argument("--udid")

    sp_send = sub.add_parser("send")
    sp_send.add_argument("--udid")

    sp_dump = sub.add_parser("dump")
    sp_dump.add_argument("--pretty", action="store_true")
    sp_dump.add_argument("--udid")

    args = p.parse_args()

    if args.cmd == "list":
        for s in list_connected_devices():
            print(s)

    elif args.cmd == "read":
        snap = read_device(udid=args.udid)
        print(json.dumps(snap.to_json(), ensure_ascii=False,
                         indent=2 if args.pretty else None))

    elif args.cmd == "send":
        cfg = json.load(open("config.json", "r", encoding="utf-8"))
        snap = read_device(udid=args.udid)
        send_to_api(snap, cfg["api_url"], cfg["bearer_token"])

    elif args.cmd == "dump":
        payload = dump_device(udid=args.udid)
        print(json.dumps(payload, ensure_ascii=False,
                         indent=2 if args.pretty else None,
                         default=safe_json))

def safe_json(obj):
    """Convert bất cứ object nào thành kiểu JSON-safe"""
    if isinstance(obj, bytes):
        # bạn chọn: hex cho gọn, hoặc base64
        return obj.hex()
    raise TypeError(f"Object of type {obj.__class__.__name__} "
                    "is not JSON serializable")

if __name__ == "__main__":
    sys.exit(main())
