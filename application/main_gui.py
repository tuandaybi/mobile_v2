import json
import threading
import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import requests

from device_reader import list_udids, read_snapshot, human_bytes

APP_TITLE = "iOS Prober UI"
DEFAULT_API_URL = "http://localhost:8000/api/autohotkey/check-device"

class App(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title(APP_TITLE)
        self.geometry("820x620")
        self.udids = []
        self.current_udid = tk.StringVar()
        self.api_url = tk.StringVar(value=DEFAULT_API_URL)
        self.api_token = tk.StringVar(value="")
        self.verify_tls = tk.BooleanVar(value=True)
        self.last_json = None
        self._build_widgets()
        self._refresh_udids_async()

    def _build_widgets(self):
        pad = {"padx": 8, "pady": 6}
        frm_top = ttk.Frame(self); frm_top.pack(fill="x", **pad)
        ttk.Label(frm_top, text="Thiết bị (UDID):").pack(side="left")
        self.cmb_udid = ttk.Combobox(frm_top, textvariable=self.current_udid, width=42, state="readonly")
        self.cmb_udid.pack(side="left", padx=6)
        ttk.Button(frm_top, text="Làm mới", command=self._refresh_udids_async).pack(side="left")

        frm_api = ttk.LabelFrame(self, text="Laravel API"); frm_api.pack(fill="x", **pad)
        ttk.Label(frm_api, text="API URL:").grid(row=0, column=0, sticky="w", padx=6, pady=4)
        ttk.Entry(frm_api, textvariable=self.api_url, width=60).grid(row=0, column=1, sticky="we", padx=6, pady=4)
        ttk.Label(frm_api, text="Bearer Token:").grid(row=1, column=0, sticky="w", padx=6, pady=4)
        ttk.Entry(frm_api, textvariable=self.api_token, width=60, show="*").grid(row=1, column=1, sticky="we", padx=6, pady=4)
        ttk.Checkbutton(frm_api, text="Verify TLS", variable=self.verify_tls).grid(row=0, column=2, padx=6, pady=4)
        frm_api.grid_columnconfigure(1, weight=1)

        frm_btn = ttk.Frame(self); frm_btn.pack(fill="x", **pad)
        ttk.Button(frm_btn, text="Đọc thông tin", command=self._read_async).pack(side="left")
        ttk.Button(frm_btn, text="Gửi lên API", command=self._send_async).pack(side="left", padx=8)
        ttk.Button(frm_btn, text="Lưu JSON...", command=self._save_json).pack(side="left")
        ttk.Button(frm_btn, text="Tải config...", command=self._load_config).pack(side="right")
        ttk.Button(frm_btn, text="Lưu config", command=self._save_config).pack(side="right", padx=8)

        frm_info = ttk.LabelFrame(self, text="Thông tin thiết bị"); frm_info.pack(fill="both", expand=True, **pad)
        labels = [
            ("Tên máy", "device_name"),
            ("UDID", "udid"),
            ("Serial", "serial"),
            ("Model", "product_type"),
            ("iOS", "ios_version"),
            ("Model number", "model_number"),
            ("Hardware", "hardware_model"),
            ("Region", "region_info"),
            ("IMEI", "imei"),
            ("ICCID", "iccid"),
            ("Baseband", "baseband_version"),
            ("Pin (%)", "battery_percent"),
            ("Chu kỳ sạc", "battery_cycle_count"),
            ("Max capacity (mAh)", "battery_max_capacity"),
            ("Bộ nhớ tổng", "storage_total_human"),
            ("Bộ nhớ trống", "storage_free_human"),
        ]
        self.value_vars = {}
        for r, (label, key) in enumerate(labels):
            ttk.Label(frm_info, text=label + ":").grid(row=r, column=0, sticky="w", padx=6, pady=4)
            v = tk.StringVar(value="")
            self.value_vars[key] = v
            ttk.Entry(frm_info, textvariable=v).grid(row=r, column=1, sticky="we", padx=6, pady=4)
        frm_info.grid_columnconfigure(1, weight=1)

        self.status = tk.StringVar(value="Sẵn sàng.")
        ttk.Label(self, textvariable=self.status, foreground="#444").pack(fill="x", padx=8, pady=4)

    def _refresh_udids_async(self):
        self.status.set("Đang tìm thiết bị...")
        threading.Thread(target=self._refresh_udids, daemon=True).start()

    def _refresh_udids(self):
        try:
            uds = list_udids()
            self.udids = uds
            self.cmb_udid["values"] = uds
            if uds and (self.current_udid.get() not in uds):
                self.current_udid.set(uds[0])
            self.status.set(f"Đã phát hiện {len(uds)} thiết bị.")
        except Exception as e:
            self.status.set(f"Lỗi tìm thiết bị: {e}")

    def _read_async(self):
        threading.Thread(target=self._read, daemon=True).start()

    def _read(self):
        self.status.set("Đang đọc thông tin...")
        try:
            udid = self.current_udid.get() or None
            snap = read_snapshot(udid=udid)
            data = snap.to_json()
            self.last_json = data
            self._fill_fields(data)
            self.status.set("Đã đọc xong.")
        except Exception as e:
            self.status.set(f"Lỗi đọc: {e}")
            messagebox.showerror("Lỗi", f"Không đọc được thông tin thiết bị:\\n{e}")

    def _fill_fields(self, data: dict):
        def val(k):
            v = data.get(k)
            return "" if v is None else str(v)
        if data.get("storage_total_bytes") is not None:
            data["storage_total_human"] = human_bytes(data["storage_total_bytes"])
        if data.get("storage_free_bytes") is not None:
            data["storage_free_human"] = human_bytes(data["storage_free_bytes"])
        for key, var in self.value_vars.items():
            var.set(val(key))

    def _send_async(self):
        threading.Thread(target=self._send, daemon=True).start()

    def _send(self):
        if not self.last_json:
            self._read()
            if not self.last_json:
                return
        api_url = self.api_url.get().strip()
        token = self.api_token.get().strip()
        if not api_url or not token:
            messagebox.showwarning("Thiếu cấu hình", "Hãy nhập API URL và Bearer Token.")
            return
        try:
            headers = {"Authorization": f"Bearer {token}", "Content-Type":"application/json", "Accept":"application/json"}
            verify = bool(self.verify_tls.get())
            r = requests.post(api_url, headers=headers, json=self.last_json, timeout=10, verify=verify)
            self.status.set(f"Kết quả gửi: {r.status_code}")
            messagebox.showinfo("Kết quả", f"{r.status_code} {r.reason}\n\n{r.text[:1000]}")
        except Exception as e:
            self.status.set(f"Gửi thất bại: {e}")
            messagebox.showerror("Lỗi gửi API", str(e))

    def _save_json(self):
        if not self.last_json:
            messagebox.showinfo("Chưa có dữ liệu", "Hãy bấm 'Đọc thông tin' trước.")
            return
        p = filedialog.asksaveasfilename(defaultextension=".json", filetypes=[("JSON","*.json")])
        if not p: return
        with open(p, "w", encoding="utf-8") as f:
            json.dump(self.last_json, f, ensure_ascii=False, indent=2)
        self.status.set(f"Đã lưu {p}")

    def _load_config(self):
        p = filedialog.askopenfilename(filetypes=[("JSON","*.json")])
        if not p: return
        try:
            cfg = json.load(open(p, "r", encoding="utf-8"))
            self.api_url.set(cfg.get("api_url", DEFAULT_API_URL))
            self.api_token.set(cfg.get("bearer_token", ""))
            self.status.set("Đã tải config.")
        except Exception as e:
            messagebox.showerror("Lỗi", f"Không đọc được config:\\n{e}")

    def _save_config(self):
        p = filedialog.asksaveasfilename(defaultextension=".json", filetypes=[("JSON","*.json")])
        if not p: return
        cfg = {"api_url": self.api_url.get().strip() or DEFAULT_API_URL, "bearer_token": self.api_token.get().strip(), "verify_tls": True}
        with open(p, "w", encoding="utf-8") as f:
            json.dump(cfg, f, ensure_ascii=False, indent=2)
        self.status.set(f"Đã lưu config: {p}")

def main():
    app = App()
    app.mainloop()

if __name__ == "__main__":
    main()
