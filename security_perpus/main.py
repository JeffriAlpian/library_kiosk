# -*- coding: utf-8 -*-
"""
Library PC Access & Accountability System - Client Kiosk (Windows)

Alur: Form identitas -> Tes keyboard -> Tes mouse -> Foto wajah -> Unlock PC
      -> Widget "Akhiri Sesi" -> Checkout -> kembali ke Kiosk.

Jalankan:  python main.py          (mode kiosk)
           python main.py --mac    (tampilkan MAC address PC ini)
"""
import atexit
import hashlib
import json
import os
import queue
import random
import re
import socket
import sys
import threading
import time
import tkinter as tk
import uuid
from datetime import datetime, timezone

import cv2
import keyboard
import requests
from dotenv import load_dotenv
from PIL import Image, ImageTk

IS_WIN = sys.platform.startswith("win")
if IS_WIN:
    import winreg

# ----------------------------------------------------------------- CONFIG
BASE_DIR = (os.path.dirname(sys.executable) if getattr(sys, "frozen", False)
            else os.path.dirname(os.path.abspath(__file__)))
load_dotenv(os.path.join(BASE_DIR, ".env"))

KIOSK_API_URL = os.getenv("KIOSK_API_URL", "").rstrip("/")   # mis. https://server-lab.kampus.ac.id
KIOSK_TOKEN = os.getenv("KIOSK_TOKEN", "")                    # dari: php artisan kiosk:register
CAMERA_INDEX = int(os.getenv("CAMERA_INDEX", "0"))
ADMIN_PIN_SHA256 = os.getenv("ADMIN_PIN_SHA256", "").lower()
# File state disimpan di folder yang bisa ditulis akun Standard User (bukan folder exe)
STATE_DIR = os.path.join(os.getenv("LOCALAPPDATA") or BASE_DIR, "LibraryKiosk")
try:
    os.makedirs(STATE_DIR, exist_ok=True)
except OSError:
    STATE_DIR = BASE_DIR
STATE_FILE = os.path.join(STATE_DIR, "active_session.json")

PHRASE = "Saya bertanggung jawab atas unit ini"
MOUSE_HITS = 3

BG, FG, MUTED = "#0f172a", "#f8fafc", "#94a3b8"
ACCENT, DANGER, OK = "#3b82f6", "#ef4444", "#22c55e"
FONT = "Segoe UI"


def get_mac() -> str:
    n = uuid.getnode()
    return ":".join(f"{(n >> i) & 0xFF:02X}" for i in range(40, -1, -8))


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat()


def set_taskmgr_disabled(disabled: bool):
    """Matikan/hidupkan Task Manager lewat registry (HKCU, tanpa admin)."""
    if not IS_WIN:
        return
    try:
        key = winreg.CreateKey(
            winreg.HKEY_CURRENT_USER,
            r"Software\Microsoft\Windows\CurrentVersion\Policies\System")
        winreg.SetValueEx(key, "DisableTaskMgr", 0, winreg.REG_DWORD, 1 if disabled else 0)
        winreg.CloseKey(key)
    except OSError:
        pass


atexit.register(lambda: set_taskmgr_disabled(False))


def save_state(obj: dict):
    try:
        with open(STATE_FILE, "w", encoding="utf-8") as f:
            json.dump(obj, f)
    except OSError:
        pass  # gagal menulis cadangan tidak boleh menggagalkan sesi


# -------------------------------------------------------------------- API
class Api:
    """Klien untuk backend Laravel."""

    def __init__(self):
        self.h = {"X-Kiosk-Token": KIOSK_TOKEN, "Accept": "application/json"}

    @staticmethod
    def _check(r):
        if r.ok:
            return r.json() if r.text else None
        try:
            body = r.json()
            msg = body.get("message", "")
            if body.get("errors"):
                msg += " " + "; ".join(e[0] for e in body["errors"].values())
        except ValueError:
            msg = r.text[:200]
        raise RuntimeError(f"{r.status_code}: {msg}")

    def get_computer(self, mac):
        r = requests.post(f"{KIOSK_API_URL}/api/kiosk/identify",
                          json={"mac_address": mac}, headers=self.h, timeout=15)
        if r.status_code == 403:      # token valid tapi MAC tidak cocok
            return None
        return self._check(r)

    def start_session(self, fields, jpeg_bytes):
        r = requests.post(f"{KIOSK_API_URL}/api/sesi/mulai", data=fields,
                          files={"foto": ("foto.jpg", jpeg_bytes, "image/jpeg")},
                          headers=self.h, timeout=30)
        return self._check(r)["session_id"]

    def end_session(self, session_id, when_iso=None):
        payload = {"session_id": session_id}
        if when_iso:
            payload["jam_selesai"] = when_iso
        self._check(requests.post(f"{KIOSK_API_URL}/api/sesi/selesai",
                                  json=payload, headers=self.h, timeout=15))


# -------------------------------------------------------------------- APP
class KioskApp:
    def __init__(self):
        self.api = Api()
        self.q = queue.Queue()
        self.pc = None
        self.session_id = None
        self.in_session = False
        self.data, self.hw = {}, {}
        self.cap = None
        self.jpeg = None
        self._confirm_end = False

        self.root = tk.Tk()
        self.root.title("Library PC Kiosk")
        self.root.configure(bg=BG)
        self.root.overrideredirect(True)
        self.root.attributes("-topmost", True)
        self.root.protocol("WM_DELETE_WINDOW", lambda: None)
        self.container = tk.Frame(self.root, bg=BG)
        self.container.pack(fill="both", expand=True)

        self.root.after(100, self._poll)
        self.root.after(1000, self._keep_front)
        self.fullscreen()
        self.apply_blocks(True)
        self.boot()

    # ------------------------------------------------------ infrastruktur
    def run_bg(self, fn, cb):
        def worker():
            try:
                res = (True, fn())
            except Exception as e:  # noqa: BLE001
                res = (False, e)
            self.q.put((cb, res))
        threading.Thread(target=worker, daemon=True).start()

    def call_main(self, fn):
        self.q.put((lambda *_: fn(), (True, None)))

    def _poll(self):
        try:
            while True:
                cb, res = self.q.get_nowait()
                cb(*res)
        except queue.Empty:
            pass
        self.root.after(100, self._poll)

    def _keep_front(self):
        if not self.in_session:
            self.root.lift()
            try:
                self.root.focus_force()
            except tk.TclError:
                pass
        self.root.after(1000, self._keep_front)

    def fullscreen(self):
        w, h = self.root.winfo_screenwidth(), self.root.winfo_screenheight()
        self.root.geometry(f"{w}x{h}+0+0")

    def apply_blocks(self, lock: bool):
        keyboard.unhook_all()
        if lock:
            for k in ("left windows", "right windows", "menu"):
                try:
                    keyboard.block_key(k)
                except Exception:  # noqa: BLE001
                    pass
            for combo in ("alt+tab", "alt+esc", "alt+f4", "ctrl+esc", "ctrl+shift+esc"):
                try:
                    keyboard.add_hotkey(combo, lambda: None, suppress=True)
                except Exception:  # noqa: BLE001
                    pass
            # tombol rahasia admin untuk keluar dari kiosk
            keyboard.add_hotkey("ctrl+shift+f12", lambda: self.call_main(self.admin_exit))
        set_taskmgr_disabled(lock)

    # ---------------------------------------------------------- UI helper
    def clear(self):
        for w in self.container.winfo_children():
            w.destroy()
        self.body = tk.Frame(self.container, bg=BG)
        self.body.place(relx=0.5, rely=0.5, anchor="center")

    def lbl(self, text, size=14, bold=False, fg=FG, parent=None, **kw):
        l = tk.Label(parent or self.body, text=text, bg=BG, fg=fg,
                     font=(FONT, size, "bold" if bold else "normal"), **kw)
        return l

    def btn(self, text, cmd, color=ACCENT, size=14, parent=None, **kw):
        return tk.Button(parent or self.body, text=text, command=cmd, bg=color, fg="white",
                         activebackground=color, activeforeground="white", relief="flat",
                         font=(FONT, size, "bold"), padx=24, pady=10, cursor="hand2", **kw)

    def screen_message(self, text, sub="", color=FG):
        self.clear()
        self.lbl(text, 26, True, color, wraplength=900, justify="center").pack(pady=10)
        if sub:
            self.lbl(sub, 13, False, MUTED, wraplength=900, justify="center").pack()

    # --------------------------------------------------------------- boot
    def boot(self):
        self.screen_message("Menghubungkan ke server...")
        self.run_bg(self._boot_work, self._boot_done)

    def _boot_work(self):
        self.recover_last_session()
        return self.api.get_computer(get_mac())

    def _boot_done(self, ok, res):
        retry = lambda: self.run_bg(self._boot_work, self._boot_done)  # noqa: E731
        if not ok:
            self.screen_message("Tidak dapat terhubung ke server", str(res)[:200], DANGER)
            self.root.after(10000, retry)
        elif res is None:
            self.screen_message("PC ini belum terdaftar",
                                f"Hubungi pustakawan. MAC Address: {get_mac()}", DANGER)
            self.root.after(10000, retry)
        elif res["status"] == "maintenance":
            self.screen_message("PC sedang dalam perbaikan",
                                f"Unit: {res['pc_name']}. Silakan gunakan PC lain.", DANGER)
            self.root.after(10000, retry)
        else:
            self.pc = res
            self.screen_form()

    def recover_last_session(self):
        """Jika app/PC mati saat sesi berjalan, tutup sesi tsb saat start berikutnya."""
        if not os.path.exists(STATE_FILE):
            return
        try:
            with open(STATE_FILE, encoding="utf-8") as f:
                st = json.load(f)
            self.api.end_session(st["session_id"], st.get("end_time"))
            os.remove(STATE_FILE)
        except Exception:  # noqa: BLE001
            pass  # dicoba lagi pada start berikutnya

    # ------------------------------------------------ 1. FORM IDENTITAS
    def screen_form(self):
        self.clear()
        self.lbl("Silakan Isi Data untuk Menggunakan PC", 30, True).pack(pady=(0, 4))
        self.lbl(f"Unit: {self.pc['pc_name']}", 14, False, MUTED).pack(pady=(0, 24))

        form = tk.Frame(self.body, bg=BG)
        form.pack()
        self.vars = {}
        fields = [("nama", "Nama Lengkap"), ("npm", "NPM"),
                  ("fakultas", "Fakultas"), ("prodi", "Program Studi")]
        for i, (key, text) in enumerate(fields):
            self.lbl(text, 13, False, MUTED, parent=form).grid(row=i, column=0, sticky="e", padx=10, pady=8)
            var = tk.StringVar(value=self.data.get(key, ""))
            self.vars[key] = var
            tk.Entry(form, textvariable=var, width=34, font=(FONT, 14)).grid(row=i, column=1, pady=8)

        self.err = self.lbl("", 12, False, DANGER)
        self.err.pack(pady=(14, 0))
        self.btn("Lanjut", self.submit_form).pack(pady=14)

    def submit_form(self):
        d = {k: v.get().strip() for k, v in self.vars.items()}
        if len(d["nama"]) < 3:
            return self.err.config(text="Nama minimal 3 karakter.")
        if not re.fullmatch(r"\d{5,20}", d["npm"]):
            return self.err.config(text="NPM harus berupa angka (5-20 digit).")
        if not d["fakultas"] or not d["prodi"]:
            return self.err.config(text="Fakultas dan Prodi wajib diisi.")
        self.data = d
        self.hw = {}
        self.screen_keyboard()

    # --------------------------------------------- 2. VALIDASI KEYBOARD
    def screen_keyboard(self):
        self.clear()
        self.lbl("Tes Keyboard", 28, True).pack()
        self.lbl("Ketik kalimat berikut dengan tepat:", 14, False, MUTED).pack(pady=(10, 4))
        self.lbl(f"\"{PHRASE}\"", 20, True, ACCENT).pack(pady=8)

        self.kb_var = tk.StringVar()
        e = tk.Entry(self.body, textvariable=self.kb_var, width=44, font=(FONT, 16))
        e.pack(pady=12)
        for seq in ("<<Paste>>", "<Control-v>", "<Shift-Insert>", "<Button-2>"):
            e.bind(seq, lambda ev: "break")  # larang copy-paste
        e.focus_set()

        self.next_btn = self.btn("Lanjut", lambda: self._kb_done(True), state="disabled")
        self.next_btn.pack(pady=10)
        self.kb_var.trace_add("write", self._kb_check)
        self.btn("Keyboard bermasalah (laporkan & lewati)", lambda: self._kb_done(False),
                 color="#475569", size=11).pack(pady=(14, 0))

    def _kb_check(self, *_):
        ok = self.kb_var.get().strip().lower() == PHRASE.lower()
        self.next_btn.config(state="normal" if ok else "disabled")

    def _kb_done(self, ok):
        self.hw["keyboard"] = ok
        self.screen_mouse()

    # ------------------------------------------------- 3. VALIDASI MOUSE
    def screen_mouse(self):
        self.clear()
        self.hits = 0
        self.lbl("Tes Mouse", 28, True).pack()
        self.lbl(f"Klik lingkaran biru sebanyak {MOUSE_HITS} kali", 14, False, MUTED).pack(pady=8)
        self.canvas = tk.Canvas(self.body, width=900, height=400, bg="#1e293b", highlightthickness=0)
        self.canvas.pack(pady=10)
        self.progress = self.lbl(f"0 / {MOUSE_HITS}", 14, True)
        self.progress.pack()
        self.btn("Mouse bermasalah (laporkan & lewati)", lambda: self._mouse_done(False),
                 color="#475569", size=11).pack(pady=(14, 0))
        self._new_target()

    def _new_target(self):
        c, r = self.canvas, 34
        c.delete("all")
        x, y = random.randint(r, 900 - r), random.randint(r, 400 - r)
        c.create_oval(x - r, y - r, x + r, y + r, fill=ACCENT, outline="", tags="target")
        c.tag_bind("target", "<Button-1>", self._hit)

    def _hit(self, _e):
        self.hits += 1
        self.progress.config(text=f"{self.hits} / {MOUSE_HITS}")
        if self.hits >= MOUSE_HITS:
            self._mouse_done(True)
        else:
            self._new_target()

    def _mouse_done(self, ok):
        self.hw["mouse"] = ok
        self.screen_camera()

    # ---------------------------------------------------- 4. CAPTURE WAJAH
    def screen_camera(self):
        self.clear()
        self.lbl("Lihat ke kamera", 28, True).pack()
        self.preview = tk.Label(self.body, bg="black")
        self.preview.pack(pady=10)
        self.count_lbl = self.lbl("3", 60, True, ACCENT)
        self.count_lbl.pack()

        self.cap = cv2.VideoCapture(CAMERA_INDEX, cv2.CAP_DSHOW if IS_WIN else 0)
        if not self.cap.isOpened():
            self.cap = None
            self.preview.destroy()
            self.count_lbl.config(text="Kamera tidak terdeteksi", font=(FONT, 20, "bold"), fg=DANGER)
            self.btn("Coba lagi", self.screen_camera).pack(pady=10)
            return
        self.cam_start, self.last_frame = time.time(), None
        self._cam_loop()

    def _cam_loop(self):
        if self.cap is None:
            return
        ok, frame = self.cap.read()
        if ok:
            self.last_frame = frame
            rgb = cv2.cvtColor(cv2.resize(frame, (640, 480)), cv2.COLOR_BGR2RGB)
            self.tkimg = ImageTk.PhotoImage(Image.fromarray(rgb))
            self.preview.configure(image=self.tkimg)
        remain = 3 - int(time.time() - self.cam_start)
        if remain > 0:
            self.count_lbl.config(text=str(remain))
            self.root.after(30, self._cam_loop)
        else:
            self._capture()

    def _capture(self):
        frame = self.last_frame
        self.cap.release()
        self.cap = None
        if frame is None:
            self.screen_message("Gagal mengambil foto", "Kamera tidak mengirim gambar.", DANGER)
            self.root.after(2500, self.screen_camera)
            return
        ok, buf = cv2.imencode(".jpg", frame, [cv2.IMWRITE_JPEG_QUALITY, 85])
        self.jpeg = buf.tobytes()
        self.submit_session()

    # ------------------------------------------------ 5. MULAI SESI
    def submit_session(self):
        self.screen_message("Menyimpan data...", "Mohon tunggu sebentar.")
        self.run_bg(self._submit_work, self._submit_done)

    def _submit_work(self):
        d = self.data
        ok = bool(self.hw.get("keyboard") and self.hw.get("mouse"))
        return self.api.start_session({
            "nama": d["nama"], "npm": d["npm"],
            "fakultas": d["fakultas"], "prodi": d["prodi"],
            "hardware_ok": "1" if ok else "0",
        }, self.jpeg)

    def _submit_done(self, ok, res):
        if not ok:
            self.screen_message("Gagal menyimpan data", str(res)[:250], DANGER)
            self.btn("Coba lagi", self.submit_session).pack(pady=16)
            self.btn("Kembali ke awal", self.screen_form, color="#475569", size=11).pack()
            return
        self.session_id = res
        save_state({"session_id": self.session_id, "pc": self.pc["pc_name"]})
        self.unlock()

    def unlock(self):
        self.apply_blocks(False)
        self.in_session = True
        self.session_start = time.time()
        self._confirm_end = False
        w, h = 260, 120
        x, y = self.root.winfo_screenwidth() - w - 16, self.root.winfo_screenheight() - h - 70
        self.root.geometry(f"{w}x{h}+{x}+{y}")
        self.clear()
        self.body.place(relx=0.5, rely=0.5, anchor="center")
        self.lbl(self.data["nama"][:28], 11, True).pack()
        self.timer_lbl = self.lbl("00:00:00", 16, True, OK)
        self.timer_lbl.pack(pady=2)
        self.end_btn = self.btn("Akhiri Sesi", self._end_click, color=DANGER, size=11)
        self.end_btn.pack(pady=4)
        self._tick()

    def _tick(self):
        if not self.in_session:
            return
        s = int(time.time() - self.session_start)
        self.timer_lbl.config(text=f"{s // 3600:02d}:{s % 3600 // 60:02d}:{s % 60:02d}")
        self.root.after(1000, self._tick)

    # ------------------------------------------------ 6. AKHIRI SESI
    def _end_click(self):
        if not self._confirm_end:
            self._confirm_end = True
            self.end_btn.config(text="Klik lagi: konfirmasi")
            self.root.after(4000, self._reset_end_btn)
        else:
            self.end_session()

    def _reset_end_btn(self):
        if self.in_session:
            self._confirm_end = False
            self.end_btn.config(text="Akhiri Sesi")

    def end_session(self):
        self.end_btn.config(state="disabled", text="Menyimpan...")
        end_time = now_iso()
        save_state({"session_id": self.session_id, "end_time": end_time})  # cadangan bila gagal kirim

        def work():
            last = None
            for _ in range(3):
                try:
                    self.api.end_session(self.session_id, end_time)
                    return True
                except Exception as e:  # noqa: BLE001
                    last = e
                    time.sleep(2)
            raise last

        def done(ok, _res):
            if ok and os.path.exists(STATE_FILE):
                os.remove(STATE_FILE)
            # jika gagal, file state dipertahankan dan dikirim ulang saat boot berikutnya
            self.lock()

        self.run_bg(work, done)

    def lock(self):
        self.in_session = False
        self.session_id = None
        self.data, self.hw, self.jpeg = {}, {}, None
        self.fullscreen()
        self.apply_blocks(True)
        self.screen_form()

    # ------------------------------------------------------ ADMIN EXIT
    def admin_exit(self):
        if self.in_session or not ADMIN_PIN_SHA256:
            return
        win = tk.Toplevel(self.root, bg=BG)
        win.attributes("-topmost", True)
        win.geometry("320x150+40+40")
        win.overrideredirect(True)
        tk.Label(win, text="PIN Admin", bg=BG, fg=FG, font=(FONT, 12, "bold")).pack(pady=8)
        pin = tk.StringVar()
        e = tk.Entry(win, textvariable=pin, show="*", font=(FONT, 14), justify="center")
        e.pack()
        win.grab_set()
        e.focus_force()

        def check(_=None):
            if hashlib.sha256(pin.get().encode()).hexdigest() == ADMIN_PIN_SHA256:
                keyboard.unhook_all()
                set_taskmgr_disabled(False)
                self.root.destroy()
                os._exit(0)
            win.destroy()

        e.bind("<Return>", check)
        tk.Button(win, text="Keluar", command=check).pack(pady=8)
        tk.Button(win, text="Batal", command=win.destroy).pack()

    def run(self):
        self.root.mainloop()


def needs_setup() -> bool:
    """True jika KIOSK_API_URL atau KIOSK_TOKEN belum terisi di .env."""
    return not KIOSK_API_URL or not KIOSK_TOKEN


def run_setup_wizard():
    """
    Wizard Tkinter untuk mendaftarkan PC ini ke server (self-registration).
    Tampil hanya saat pertama kali — sebelum kiosk mode aktif.
    Setelah berhasil, menulis .env di folder yang sama dengan exe/script,
    lalu me-reload variabel lingkungan agar KioskApp bisa langsung jalan.
    """
    import tkinter.messagebox as mb

    ENV_PATH = os.path.join(BASE_DIR, ".env")

    root = tk.Tk()
    root.title("Setup Kiosk — Daftarkan PC ke Server")
    root.configure(bg=BG)
    root.resizable(False, False)

    # Tengahkan window
    root.update_idletasks()
    w, h = 520, 440
    sw, sh = root.winfo_screenwidth(), root.winfo_screenheight()
    root.geometry(f"{w}x{h}+{(sw - w) // 2}+{(sh - h) // 2}")

    pad = {"padx": 30, "pady": 6}

    tk.Label(root, text="Pendaftaran Awal PC Kiosk", bg=BG, fg=FG,
             font=(FONT, 20, "bold")).pack(pady=(28, 4))
    tk.Label(root, text="Isi data di bawah untuk mendaftarkan PC ini ke server.\n"
             "Hubungi admin untuk mendapatkan URL server dan password pendaftaran.",
             bg=BG, fg=MUTED, font=(FONT, 11), justify="center").pack(pady=(0, 18))

    frame = tk.Frame(root, bg=BG)
    frame.pack(**pad)

    fields_cfg = [
        ("Nama PC",               socket.gethostname(), "pc_name"),
        ("URL Server",            "https://server-lab.kampus.ac.id", "api_url"),
        ("Password Pendaftaran",  "", "secret"),
    ]
    entries: dict[str, tk.Entry] = {}

    for i, (label, default, key) in enumerate(fields_cfg):
        tk.Label(frame, text=label, bg=BG, fg=MUTED,
                 font=(FONT, 12), anchor="e", width=22).grid(row=i, column=0, pady=8, sticky="e")
        show = "*" if key == "secret" else ""
        e = tk.Entry(frame, font=(FONT, 13), width=28, show=show)
        e.insert(0, default)
        e.grid(row=i, column=1, pady=8, padx=(8, 0))
        entries[key] = e

    err_lbl = tk.Label(root, text="", bg=BG, fg=DANGER, font=(FONT, 11), wraplength=460, justify="center")
    err_lbl.pack(pady=(4, 0))

    status_lbl = tk.Label(root, text="", bg=BG, fg=MUTED, font=(FONT, 10))
    status_lbl.pack()

    registered = {"done": False}

    def do_register():
        pc_name = entries["pc_name"].get().strip()
        api_url = entries["api_url"].get().strip().rstrip("/")
        secret = entries["secret"].get().strip()

        err_lbl.config(text="")
        if not pc_name:
            err_lbl.config(text="Nama PC tidak boleh kosong.")
            return
        if not api_url.startswith(("http://", "https://")):
            err_lbl.config(text="URL Server harus diawali https:// (atau http:// untuk lokal).")
            return
        if not secret:
            err_lbl.config(text="Password Pendaftaran tidak boleh kosong.")
            return

        btn_register.config(state="disabled", text="Mendaftar...")
        status_lbl.config(text="Menghubungi server...")
        root.update()

        try:
            resp = requests.post(
                f"{api_url}/api/kiosk/self-register",
                json={"pc_name": pc_name, "mac_address": get_mac(), "secret": secret},
                headers={"Accept": "application/json"},
                timeout=20,
            )
        except requests.exceptions.ConnectionError:
            err_lbl.config(text="Tidak dapat terhubung ke server. Periksa URL dan koneksi jaringan.")
            btn_register.config(state="normal", text="Daftar")
            status_lbl.config(text="")
            return
        except requests.exceptions.Timeout:
            err_lbl.config(text="Koneksi timeout. Server tidak merespons dalam 20 detik.")
            btn_register.config(state="normal", text="Daftar")
            status_lbl.config(text="")
            return

        if resp.status_code == 201:
            data = resp.json()
            token = data["token"]
            admin_pin_hash = data.get("admin_pin_hash") or ""

            # Tulis / perbarui .env di folder exe
            try:
                from dotenv import set_key as _set_key
                # Buat file jika belum ada
                if not os.path.exists(ENV_PATH):
                    open(ENV_PATH, "w").close()
                _set_key(ENV_PATH, "KIOSK_API_URL", api_url)
                _set_key(ENV_PATH, "KIOSK_TOKEN", token)
                _set_key(ENV_PATH, "CAMERA_INDEX", "0")
                if admin_pin_hash:
                    _set_key(ENV_PATH, "ADMIN_PIN_SHA256", admin_pin_hash)
            except OSError as exc:
                err_lbl.config(text=f"Gagal menulis .env: {exc}")
                btn_register.config(state="normal", text="Daftar")
                status_lbl.config(text="")
                return

            # Reload variabel lingkungan agar session KioskApp baca nilai baru
            os.environ["KIOSK_API_URL"] = api_url
            os.environ["KIOSK_TOKEN"] = token
            if admin_pin_hash:
                os.environ["ADMIN_PIN_SHA256"] = admin_pin_hash

            registered["done"] = True
            status_lbl.config(text="")
            mb.showinfo(
                "Berhasil",
                f"PC '{pc_name}' berhasil terdaftar!\n\n"
                f"MAC: {get_mac()}\n"
                "Kiosk akan segera dimulai.",
                parent=root,
            )
            root.destroy()

        elif resp.status_code == 403:
            err_lbl.config(text="Password Pendaftaran salah. Hubungi admin.")
            btn_register.config(state="normal", text="Daftar")
            status_lbl.config(text="")
        elif resp.status_code == 409:
            try:
                msg = resp.json().get("message", "Konflik nama PC.")
            except ValueError:
                msg = "Konflik nama PC."
            err_lbl.config(text=msg)
            btn_register.config(state="normal", text="Daftar")
            status_lbl.config(text="")
        elif resp.status_code == 422:
            try:
                errs = resp.json().get("errors", {})
                msg = "; ".join(v[0] for v in errs.values()) if errs else resp.text[:200]
            except ValueError:
                msg = resp.text[:200]
            err_lbl.config(text=f"Data tidak valid: {msg}")
            btn_register.config(state="normal", text="Daftar")
            status_lbl.config(text="")
        else:
            err_lbl.config(text=f"Kesalahan server ({resp.status_code}). Coba lagi atau hubungi admin.")
            btn_register.config(state="normal", text="Daftar")
            status_lbl.config(text="")

    btn_register = tk.Button(
        root, text="Daftar", command=do_register,
        bg=ACCENT, fg="white", activebackground=ACCENT, activeforeground="white",
        relief="flat", font=(FONT, 13, "bold"), padx=28, pady=10, cursor="hand2",
    )
    btn_register.pack(pady=(14, 4))

    tk.Label(root, text=f"MAC Address PC ini: {get_mac()}", bg=BG, fg=MUTED,
             font=(FONT, 10)).pack(pady=(8, 0))

    # Enter = submit
    root.bind("<Return>", lambda _e: do_register())

    root.mainloop()
    return registered["done"]


def main():
    if "--save-mac" in sys.argv:
        # Untuk pendataan banyak PC:  LibraryKiosk.exe --save-mac E:\macs.csv
        from tkinter import messagebox
        i = sys.argv.index("--save-mac")
        path = sys.argv[i + 1] if len(sys.argv) > i + 1 else os.path.join(BASE_DIR, "mac_list.csv")
        is_new = not os.path.exists(path)
        with open(path, "a", encoding="utf-8", newline="") as f:
            if is_new:
                f.write("pc_name,mac_address\n")
            f.write(f"{socket.gethostname()},{get_mac()}\n")
        r = tk.Tk(); r.withdraw()
        messagebox.showinfo("Tersimpan", f"{socket.gethostname()}  {get_mac()}\n-> {path}")
        return
    if "--mac" in sys.argv:
        from tkinter import messagebox
        r = tk.Tk(); r.withdraw()
        messagebox.showinfo("MAC Address PC ini", get_mac())
        return

    # Jika .env belum terisi, tampilkan wizard self-registration
    if needs_setup():
        success = run_setup_wizard()
        if not success:
            # Pengguna menutup wizard tanpa mendaftar
            return

    KioskApp().run()


if __name__ == "__main__":
    main()
