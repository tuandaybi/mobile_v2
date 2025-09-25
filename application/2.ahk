#Requires AutoHotkey v2.0
#Include "Lib\JSON.ahk"

; --- GUI Biến ---
global ddlDevices, ddlColors, ddlCapacities, device, color, capacity  ; nếu dùng GUI dropdown
global input_mb_imei, input_tenkh, input_sdtkh, input_mb_giaban
global input_mb_ghichuban, input_mb_pin, input_mb_mamay, ddlNguonnhap, ddlBaohanh_May
global input_mb_gianhap, input_mb_ngaynhap, input_mb_ghichunhap
global input_id_device, input_id_color, input_id_capacity, input_mb_no, input_imei
global input_tendichvu, input_dv_tenkh, input_dv_sdtkh, input_dv_giaban, input_dv_no, input_dv_ghichu, input_dv_chiphi, ddlBaohanh, input_dv_ngayban
global input_Server, input_token, btnConnect
global mobile_in_id := 0

;-----------------------------GUI-----------------------------

device   := LoadListFromFile("devices.json", "name", "id")
color    := LoadListFromFile("colors.json", "en_name", "id")
capacity := LoadListFromFile("capacities.json", "name", "id")
baohanh	 := LoadListFromFile("baohanh.json", "name_baohanh", "id")
settings := LoadListFromFile("settings.json", "setting_name", "setting_key")
nguonnhap := LoadListFromFile("nguonnhap.json", "name", "id")

if settings.names.Length > 0 {

	Global TesseractPath := settings.map["TesseractPath"]
	Global BASE_API_URL := settings.map["Server_URL"]
	Global Token_key := settings.map["Token"]
}


global myGui := Gui(, "Xuất Nhập - Thiết Bị - Dịch Vụ")
myGui.SetFont("s10", "Segoe UI")
tab := myGui.AddTab3("x10 y10 w490 h550", ["Phụ kiện & sửa chữa", "Bán máy", "Nhập máy", "Settings"])

;--------------------------------------------------------------TAB 1: Phụ kiện & sửa chữa--------------------------------------------------------
tab.UseTab("Phụ kiện & sửa chữa")

marginX := 40
inputW := 280
labelW := 150
y := 50
spaceY := 40

myGui.AddText("x" marginX " y" y, "Tên dịch vụ:")
input_tendichvu := myGui.AddEdit("x" (marginX + labelW) " y" (y - 5) " w" inputW)

y += spaceY
myGui.AddText("x" marginX " y" y, "Tên khách hàng:")
input_dv_tenkh := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW, "Khách lẻ")

y += spaceY
myGui.AddText("x" marginX " y" y, "SĐT khách hàng:")
input_dv_sdtkh := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

y += spaceY
myGui.AddText("x" marginX " y" y, "Giá bán:")
input_dv_giaban := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

input_dv_giaban.OnEvent("Change", (*) => 
    AttachMoneyFormatter(input_dv_giaban)
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Chi phí:")
input_dv_chiphi := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

input_dv_chiphi.OnEvent("Change", (*) => 
    AttachMoneyFormatter(input_dv_chiphi)
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Số tiền nợ:")
input_dv_no := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

input_dv_no.OnEvent("Change", (*) => 
    AttachMoneyFormatter(input_dv_no)
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Ngày bán (Y-m-d):")
input_dv_ngayban := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW, FormatTime(A_Now, "yyyy-MM-dd"))

y += spaceY
myGui.AddText("x" marginX " y" y, "Bảo hành:")
ddlBaohanh := myGui.AddDropDownList("x" (marginX + labelW) " y" y - 5 " w" inputW " Choose1", baohanh.names)

y += spaceY
myGui.AddText("x" marginX " y" y, "Ghi chú bán hàng:")
input_dv_ghichu := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW " h" 100)

y += 145
btnLuuDV := myGui.AddButton("x" (marginX + labelW) " y" y " w120", "💾 Lưu")
btnLuuDV.OnEvent("Click", (*) => 
    SendToAPI("service", "/services")
)

btnXoa := myGui.AddButton("x350 y" y " w120", "♻️Xóa")
btnXoa.OnEvent("Click", (*) => 
    XoaInput()
)

;--------------------------------------------------------------TAB 2: BÁN HÀNG--------------------------------------------------------
tab.UseTab("Bán máy")

marginX := 40
inputW := 280
labelW := 150
y := 50
spaceY := 40

myGui.AddText("x" marginX " y" y, "IMEI thiết bị:")
input_imei := myGui.AddEdit("x" (marginX + labelW) " y" (y - 5) " w" 200)
btnCheck := myGui.AddButton("x" (marginX + labelW + 215) " y" y - 5 " h28", "Kiểm Tra")

btnCheck.OnEvent("Click", (*) => 
    CurlGetJson("GET", "/mobile-in/search-imei/" . input_imei.Text . "?sold=0")
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Tên khách hàng:")
input_tenkh := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

y += spaceY
myGui.AddText("x" marginX " y" y, "SĐT khách hàng:")
input_sdtkh := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

y += spaceY
myGui.AddText("x" marginX " y" y, "Ngày bán (Y-m-d):")
input_mb_ngayban := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW, FormatTime(A_Now, "yyyy-MM-dd"))

y += spaceY
myGui.AddText("x" marginX " y" y, "Giá bán:")
input_mb_giaban := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)
input_mb_giaban.OnEvent("Change", (*) => 
    AttachMoneyFormatter(input_mb_giaban)
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Chi phí:")
input_mb_chiphi := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)
input_mb_chiphi.OnEvent("Change", (*) => 
    AttachMoneyFormatter(input_mb_chiphi)
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Số tiền nợ:")
input_mb_no := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)
input_mb_no.OnEvent("Change", (*) => 
    AttachMoneyFormatter(input_mb_no)
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Bảo hành:")
ddlBaohanh_May := myGui.AddDropDownList("x" (marginX + labelW) " y" y - 5 " w" inputW " Choose7", baohanh.names)

y += spaceY
;myGui.AddGroupBox("x" (marginX) " y" (y - 10) " w" (labelW + inputW + 20) " h50", "Phương thức thanh toán:")
myGui.AddText("x" marginX " y" y, "Phương thức thanh toán:")
rbTienmat := myGui.AddRadio("x" (marginX + labelW) " y" (y) " w80", "Tiền mặt")
rbChuyenkhoan := myGui.AddRadio("x" (marginX + labelW + 80) " y" (y - 10) " w100", "Chuyển khoản")
rbTragop := myGui.AddRadio("x" (marginX + labelW + 200) " y" (y) " w100", "Trả góp")

rbChuyenkhoan.Value := true

y += spaceY
myGui.AddText("x" marginX " y" y, "Ghi chú bán hàng:")
input_mb_ghichuban := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW " h" 100)

y += spaceY + 65
btnLuu := myGui.AddButton("x210 y" y " w100", "💾 Lưu")
btnLuu.OnEvent("Click", (*) => 
    SendToAPI("sell", "/mobile-out")
)
btnLuu.Enabled := false

;----------------------------------------------------------TAB 3: NHẬP HÀNG-----------------------------------------------------------
tab.UseTab("Nhập máy")
y := 50

myGui.AddText("x" marginX " y" y, "Thiết bị:")
ddlDevices := myGui.AddDropDownList("x" (marginX + labelW) " y" y - 5 " w" inputW, device.names)

y += spaceY
myGui.AddText("x" marginX " y" y, "IMEI:")
input_mb_imei := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

y += spaceY
myGui.AddText("x" marginX " y" y, "Màu sắc:")
ddlColors := myGui.AddDropDownList("x" (marginX + labelW) " y" y - 5 " w" inputW, color.names)

y += spaceY
myGui.AddText("x" marginX " y" y, "Dung lượng:")
ddlCapacities := myGui.AddDropDownList("x" (marginX + labelW) " y" y - 5 " w" inputW, capacity.names)

y += spaceY
myGui.AddText("x" marginX " y" y, "PIN:")
input_mb_pin := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

y += spaceY
myGui.AddText("x" marginX " y" y, "Mã Máy:")
input_mb_mamay := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)

y += spaceY
myGui.AddText("x" marginX " y" y, "Nguồn Nhập:")
ddlNguonnhap := myGui.AddComboBox("x" (marginX + labelW) " y" y - 5 " w" inputW " Choose1", nguonnhap.names)

y += spaceY
myGui.AddText("x" marginX " y" y, "Giá Nhập:")
input_mb_gianhap := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW)
input_mb_gianhap.OnEvent("Change", (*) => 
    AttachMoneyFormatter(input_mb_gianhap)
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Ngày Nhập (Y-m-d):")
input_mb_ngaynhap := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW, FormatTime(A_Now, "yyyy-MM-dd"))

y += spaceY
myGui.AddText("x" marginX " y" y, "Ghi chú:")
input_mb_ghichunhap := myGui.AddEdit("x" (marginX + labelW) " y" y - 5 " w" inputW " h" 100)

y += 105
myGui.AddButton("x" marginX " y" y " w120", "📥 Lấy dữ liệu").OnEvent("Click", (*) => HandleScreenOCR())
myGui.AddButton("x" (marginX + 155) " y" y " w120", "🚀 Lưu sản phẩm").OnEvent("Click", (*) => SendToAPI("add", "/mobile-in"))
myGui.AddButton("x" (marginX + 310) " y" y " w120", "♻️ Xóa").OnEvent("Click", (*) =>XoaInputNhapMay())

y += 50


tab.UseTab()
btnConnect := myGui.AddButton("x10 y565 h28 w150", "🔌 Kiểm tra kết nối")
btnConnect.OnEvent("Click", (*) => 
    TestConnect("/ping")
)
myGui.Add("Checkbox", "x330 y570 vAutoStartCheckbox", "Khởi động cùng Windows")
myGui["AutoStartCheckbox"].OnEvent("Click", CheckboxToggled)

;--------------------------------------------------------------TAB 4: Settings--------------------------------------------------------
tab.UseTab("Settings")

marginX := 40
inputW := 220
labelW := 150
y := 50
spaceY := 40

myGui.AddText("x" marginX " y" y, "Tên máy chủ:")
input_Server := myGui.AddEdit("x" (marginX + labelW) " y" (y - 5) " w" 220,BASE_API_URL)
btnServer := myGui.AddButton("x" (marginX + labelW + 225) " y" y - 6 " h28", "Lưu lại")

btnServer.OnEvent("Click", (*) => 
    SetSettingValue("Server_URL", Trim(input_Server.Value))
)

y += spaceY
myGui.AddText("x" marginX " y" y, "Token:")
input_token := myGui.AddEdit("x" (marginX + labelW) " y" (y - 5) " w" 220 " h" 80, Token_key)
btnToken := myGui.AddButton("x" (marginX + labelW + 225) " y" y - 6 " h28", "Lưu lại")
btnToken.OnEvent("Click", (*) => 
    SetSettingValue("Token", Trim(input_token.Value))
)

y += spaceY + 60
myGui.AddText("x" marginX " y" y, "Đường dẫn OCR:")
input_orc := myGui.AddEdit("x" (marginX + labelW) " y" (y - 5) " w" 220 " h" 80, TesseractPath)
btnOrc := myGui.AddButton("x" (marginX + labelW + 225) " y" y - 6 " h28", "Lưu lại")
btnOrc.OnEvent("Click", (*) => 
    SetSettingValue("TesseractPath", Trim(input_orc.Value))
)

y += spaceY + 60
myGui.AddText("x" marginX " y" y, "Thiết bị:")
ddlDevices1 := myGui.AddDropDownList("x" (marginX + labelW) " y" y - 5 " w" inputW, device.names)
btnDevices := myGui.AddButton("x" (marginX + labelW + 225) " y" y - 6 " h28", "Làm mới")
btnDevices.OnEvent("Click", (*) => 
    CurlGetJson("GET", "admin/devices", "devices.json")
)

y += spaceY 
myGui.AddText("x" marginX " y" y, "Màu sắc:")
ddlColors1 := myGui.AddDropDownList("x" (marginX + labelW) " y" y - 5 " w" inputW, color.names)
btnColors := myGui.AddButton("x" (marginX + labelW + 225) " y" y - 6 " h28", "Làm mới")
btnColors.OnEvent("Click", (*) => 
    CurlGetJson("GET", "admin/colors", "colors.json")
)

y += spaceY 
myGui.AddText("x" marginX " y" y, "Dung lượng:")
ddlCapacities1 := myGui.AddDropDownList("x" (marginX + labelW) " y" y - 5 " w" inputW, capacity.names)
btnCapacities := myGui.AddButton("x" (marginX + labelW + 225) " y" y - 6 " h28", "Làm mới")
btnCapacities.OnEvent("Click", (*) => 
    CurlGetJson("GET", "admin/storages", "capacities.json")
)
y += spaceY + 50
btnReload := myGui.AddButton("x210 y" y " w100", "🔃 Reload")
btnReload.OnEvent("Click", (*) => 
    ReloadScript()
)


myGui.Show("w510 h600")
myGui.Show("Center")

;-----------------------------------------------Xóa trường dữ liệu---------------------------------------------------------------------
XoaInput(){
	input_tendichvu.Value := ""
	input_dv_tenkh.Value := "Khách lẻ"
	input_dv_sdtkh.Value := "" 
	input_dv_giaban.Value := "" 
	input_dv_no.Value := "" 
	input_dv_ghichu.Value := "" 
	input_dv_chiphi.Value := "" 
	ddlBaohanh.Text := "Không bảo hành"
}

XoaInputNhapMay(){
	ddlDevices.Text := ""
	input_mb_imei.Value := ""
	ddlColors.Text := "" 
	ddlCapacities.Text := "" 
	input_mb_pin.Value := "" 
	input_mb_mamay.Value := "" 
	ddlNguonnhap.Text := "Khách lẻ" 
	input_mb_gianhap.Value := "" 
	input_mb_ngaynhap.Value := FormatTime(A_Now, "yyyy-MM-dd")
	input_mb_ghichunhap.Value := ""
}

FormatCurrency(num) {
    clean := RegExReplace(num, "[^\d]")
    if (clean = "")
        return ""
    return RegExReplace(clean, "\B(?=(\d{3})+(?!\d))", ",")
}

; NHẬN ctrl trực tiếp từ OnEvent, và có * để nhận thêm tham số dư nếu có
MoneyChange(ctrl, *) {
    old := ctrl.Value
    fmt := FormatCurrency(old)
    if (fmt != old) {
        ctrl.Value := fmt
        len := StrLen(ctrl.Value)
        ; Đưa caret về cuối
        SendMessage(0xB1, len, len, , "ahk_id " ctrl.Hwnd)  ; EM_SETSEL
    }
}

AttachMoneyFormatter(editCtrl) {
    ; KHÔNG dùng Bind ở đây
    editCtrl.OnEvent("Change", MoneyChange)
}


;-----------------------------------------------Sửa JSON file---------------------------------------------------------------------------
GetSettingValue(settingName, filePath := "settings.json") {
    try {
        data := JSON.Parse(FileRead(filePath, "UTF-8"))
        for item in data {
            if item["setting_name"] = settingName
                return item["setting_key"]
        }
    } catch {
        MsgBox "❌ Không đọc được setting từ file"
    }
    return ""
}

SetSettingValue(settingName, newValue, filePath := "settings.json") {
    try {
        jsonText := FileRead(filePath, "UTF-8")
        data := JSON.parse(jsonText)
    } catch {
        MsgBox "❌ Không đọc được file: " . filePath
        return false
    }

    found := false
    for item in data {
        if item["setting_name"] = settingName {
            item["setting_key"] := newValue
            found := true
            break
        }
    }

    if !found {
        MsgBox "⚠️ Không tìm thấy setting: " . settingName
        return false
    }

    try {
        file := FileOpen(filePath, "w", "UTF-8")
        if file {
            file.Write(JSON.stringify(data, 4)) ; dùng stringify thay cho Dump
            file.Close()
			MsgBox "Lưu thành công"
            return true
        } else {
            MsgBox "❌ Không thể mở file để ghi: " . filePath
            return false
        }
    } catch {
        MsgBox "❌ Lỗi khi ghi vào file: " . filePath
        return false
    }
}
SaveNguonNhap(){
    local newValue := ddlNguonnhap.Text
    if !FileExist("nguonnhap.json")
        FileAppend("[]", "nguonnhap.json", "UTF-8")

    newItem := {id: 'nguon', name: newValue}

    local data := JSON.Parse(FileRead("nguonnhap.json", "UTF-8"))
    for item in data {
        if (item is Map && item["name"] = newValue) {
            return
        }
    }
    data.Push(newItem)
    FileDelete("nguonnhap.json")
    FileAppend(JSON.Stringify(data), "nguonnhap.json", "UTF-8")

    ddlNguonnhap.Delete()  ; Xoá toàn bộ item cũ
    data := JSON.Parse(FileRead("nguonnhap.json", "UTF-8"))
    for item in data {
        if (item is Map)
            ddlNguonnhap.Add([item["name"]])
    }
}
TestConnect(url, outputFile := "") {
    try {
        http := ComObject("WinHttp.WinHttpRequest.5.1")
        http.Open("GET", BASE_API_URL . url)
        http.Send()

        if (http.Status != 200) {
            MsgBox "❌ Request thất bại - Status: " http.Status
            return false
        }
        MsgBox "✅ Kết nối thành công"
    } catch {
        MsgBox "❌ Lỗi khi gửi yêu cầu: "
        return false
    }
}
ReloadScript() {
    ; Lấy đường dẫn file AHK đang chạy
    scriptPath := A_ScriptFullPath

    ; Chạy lại file
    Run('"' scriptPath '"')

    ; Thoát script hiện tại
    ExitApp
}
;-----------------------------------------------Auto Startup---------------------------------------------------------------------------
; === Đường dẫn tới script hiện tại ===
scriptPath := A_ScriptFullPath
scriptName := A_ScriptName
startupShortcut := A_Startup "\MyApp.lnk" ; tên shortcut bạn muốn tạo

if FileExist(startupShortcut)
    myGui["AutoStartCheckbox"].Value := 1
; Khi người dùng tick/untick checkbox

CheckboxToggled(ctrl, *)
{
    global startupShortcut, scriptPath
    if ctrl.Value
    {
        ; Tạo shortcut vào thư mục Startup
        try FileCreateShortcut(scriptPath, startupShortcut)
    }
    else
    {
        ; Xoá shortcut nếu có
        if FileExist(startupShortcut)
            FileDelete startupShortcut
    }
}

;-----------------------------------------------LOAD DỮ LIỆU TỪ FILE JSON--------------------------------------------------------------
; Lấy giá trị theo đường dẫn "a.b.c" an toàn
GetByPath(obj, path) {
    if (!path)
        return ""
    parts := StrSplit(path, ".")
    cur := obj
    for _, key in parts {
        if IsObject(cur) && cur.Has(key) {
            cur := cur[key]
        } else {
            return ""
        }
    }
    return cur
}

; Chuẩn hoá JSON paginator/array -> Array để lặp
ExtractListArray(jsonObj) {
    ; Nếu là mảng thuần
    if IsObject(jsonObj) && jsonObj.__Class = "Array"
        return jsonObj
    ; Nếu có khóa "data" là mảng (Laravel paginator)
    if IsObject(jsonObj) && jsonObj.Has("data") && IsObject(jsonObj["data"]) && jsonObj["data"].__Class = "Array"
        return jsonObj["data"]
    ; Một số API dùng "items"
    if IsObject(jsonObj) && jsonObj.Has("items") && IsObject(jsonObj["items"]) && jsonObj["items"].__Class = "Array"
        return jsonObj["items"]
    ; Không tìm thấy mảng phù hợp
    return []
}

; Đọc 1 file JSON và dựng danh sách cho dropdown
; labelField: tên field hiển thị (vd: "name" hoặc "device.name")
; idField:    field id (vd: "id")
; optionalField (tuỳ chọn): field phụ sẽ nối thêm (vd: "size_gb")
; optionalFmt (tuỳ chọn): format ghép, mặc định " {value}" -> ra "Tên 128"
LoadListFromFile(filename, labelField, idField, optionalField := "", optionalFmt := " {value}") {
    items := []
    idMap := Map()

    try {
        jsonObj := JSON.Parse(FileRead(filename, "UTF-8"))
    } catch {
        MsgBox("❌ Không đọc được file hoặc JSON lỗi: " . filename)
        return { names: items, map: idMap }
    }

    arr := ExtractListArray(jsonObj)

    for _, item in arr {
        try {
            label := GetByPath(item, labelField)
            if (label = "")
                continue

            if (optionalField) {
                optVal := GetByPath(item, optionalField)
                if (optVal != "") {
                    label .= StrReplace(optionalFmt, "{value}", optVal)
                }
            }

            id := GetByPath(item, idField)
            if (id = "")
                continue

            items.Push(label)
            idMap[label] := id
        } catch {
            continue
        }
    }
    return { names: items, map: idMap }
}

; Gộp nhiều file (mỗi file có thể là phân trang khác nhau) vào 1 dropdown
LoadListFromFiles(fileArray, labelField, idField, optionalField := "", optionalFmt := " {value}") {
    items := []
    idMap := Map()
    for _, fn in fileArray {
        res := LoadListFromFile(fn, labelField, idField, optionalField, optionalFmt)
        for _, name in res.names {
            ; Tránh trùng label: nếu đã có, thêm " #<id>"
            if idMap.Has(name) {
                id := res.map[name]
                name2 := name " #" id
                items.Push(name2)
                idMap[name2] := id
            } else {
                items.Push(name)
                idMap[name] := res.map[name]
            }
        }
    }
    return { names: items, map: idMap }
}


;-----------------------------------------------API GET----------------------------------------------------------------------------

;Get JSON từ API (dùng cho dropdown)
; Ghép URL an toàn
JoinUrl(base, path) {
    if (SubStr(base, -0) = "/")
        base := SubStr(base, 1, -1)
    if (SubStr(path, 1, 1) != "/")
        path := "/" path
    return base path
}

; Map -> query string: {page:1, per_page:50} => "page=1&per_page=50"
BuildQuery(qMap) {
    if !IsObject(qMap)
        return ""
    qs := ""
    for k, v in qMap {
        if (qs != "")
            qs .= "&"
        qs .= k "=" v  ; (đủ dùng cho số & chuỗi đơn giản)
    }
    return qs
}

; GET JSON với curl. Trả true/false. Nếu outputFile != "" thì lưu body vào file.
; endpoint: "/admin/devices"
; token   : Bearer token (có thể rỗng nếu public)
; query   : Map tham số query (vd: { "page":1, "per_page":50 })
CurlGetJson(method, endpoint, outputFile := "", query := "") {
    fullUrl := JoinUrl(BASE_API_URL, endpoint)
    if (IsObject(query)) {
        qs := BuildQuery(query)
        if (qs != "")
            fullUrl .= "?" qs
    } else if (query != "") {
        ; cho phép truyền chuỗi query sẵn "page=1&per_page=50"
        fullUrl .= "?" query
    }

    tempOut := A_Temp "\curl_out_" A_TickCount ".txt"
    headers .= ' -H "Authorization: Bearer ' Token_key '"'
    curlCmd := 'curl -sS -L -X ' method ' "' fullUrl '" ' headers
             . ' --write-out "HTTPSTATUS:%{http_code}" > "' tempOut '"'
    
    RunWait(EnvGet("ComSpec") . " /C " . curlCmd, , "Hide")

    if !FileExist(tempOut) {
        MsgBox "❌ Không thấy file output của curl."
        return false
    }
    raw := FileRead(tempOut, "UTF-8-RAW")

    sep := "HTTPSTATUS:"
    pos := InStr(raw, sep, , -1)
    if (pos = 0) {
        MsgBox "❌ Không tìm thấy HTTPSTATUS trong output.`n" raw
        return false
    }

    body := SubStr(raw, 1, pos - 1)
    httpStatus := Trim(SubStr(raw, pos + StrLen(sep)))

    if (httpStatus != "200") {
        MsgBox "❌ HTTP " httpStatus "`n" body
        return false
    }
    else{
        data := JSON.Parse(body)
        global mobile_in_id := 0
        item := data[1]

        device  := item["device"]["name"]
        storage := item["storage"]["name"]
        color   := item["color"]["en_name"]
        imei    := item["imei"]
        mobile_in_id := item["id"]

        msg := "Thông tin sản phẩm" "`n"
        msg .= "ID: " mobile_in_id "`n"
        msg .= "Tên: " device "`n"
        msg .= "Dung lượng: " storage "`n"
        msg .= "Màu sắc: " color "`n"
        msg .= "IMEI: " imei
        MsgBox(msg)
        btnLuu.Enabled := true
    }

    if (outputFile != "") {
        fo := FileOpen(outputFile, "w", "UTF-8")
        fo.Write(body), fo.Close()
    }

    if (outputFile != "")
    MsgBox "✅ Đã lưu: " outputFile

    return true
}

;-----------------------------------------------API POST----------------------------------------------------------------------------

SendToAPI(mode, endpoint) {
	data := Map()
			
		if (mode = "add") {
			d := ddlDevices.Text
			c := ddlColors.Text
			cp := ddlCapacities.Text
            imei := RegExReplace(input_mb_imei.Value, "[^\d]")
			data := Map(
				"device_id", device.map.Has(d)     ? device.map[d]     : 41,
				"imei", imei,
				"color_id", color.map.Has(c)       ? color.map[c]      : 4,
				"storage_id", capacity.map.Has(cp)? capacity.map[cp]  : 4,
				"battery_capacity", RegExReplace(input_mb_pin.Value, "[^\d]"),
				"country_code", input_mb_mamay.Value,
				"supplier", ddlNguonnhap.Text,
				"import_price", RegExReplace(input_mb_gianhap.Value, "[^\d]"),
				"import_date", input_mb_ngaynhap.Value,
				"import_note", input_mb_ghichunhap.Value,
			)
		}

		if (mode = "sell") {

            rbTienmat.MyValue     := 2
            rbChuyenkhoan.MyValue := 0
            rbTragop.MyValue      := 1

            payment := (rbTienmat.Value ? rbTienmat.MyValue
             : rbChuyenkhoan.Value ? rbChuyenkhoan.MyValue
             : rbTragop.Value ? rbTragop.MyValue
             : "")

            bh := ddlBaohanh_May.Text

            data := Map(
			"mobile_in_id", mobile_in_id,
			"customer_name", input_tenkh.Value,
			"phone_number", "T" input_sdtkh.Text,
			"export_price", RegExReplace(input_mb_giaban.Value, "[^\d]"),
			"expense", RegExReplace(input_mb_chiphi.Value, "[^\d]"),
			"debt_amount", RegExReplace(input_mb_no.Value, "[^\d]"),
			"payment", RegExReplace(payment, "[^\d]"),
			"export_date", input_mb_ngayban.Value,
			"warranty", baohanh.map.Has(bh) ? baohanh.map[bh] : 0,
			"note", input_mb_ghichuban.Value,
            )
		}
		if (mode = "service") {
            bh := ddlBaohanh.Text
            phone := "T" . RegExReplace(input_dv_sdtkh.Value . "", "\s+")
            data := Map(
                "name", input_tendichvu.Value,
                "customer_name", input_dv_tenkh.Value,
                "phone_number", phone,
                "price", RegExReplace(input_dv_giaban.Value, "[^\d]"),
                "debt", RegExReplace(input_dv_no.Value, "[^\d]"),
                "service_date", input_dv_ngayban.Value,
                "warranty", baohanh.map.Has(bh) ? baohanh.map[bh] : 0,
                "expense", RegExReplace(input_dv_chiphi.Value, "[^\d]"),
                "note", input_dv_ghichu.Value,
            )
		}
    json_file := MapToJson(data)
    tempFile := A_Temp "\data.json"
	tempOut := A_Temp "\curl_output.txt"
    f := FileOpen(tempFile, "w", "UTF-8-RAW")
    f.Write(json_file)
    f.Close()

    curl := 'curl -X POST "' . BASE_API_URL . endpoint . '" '
        . '-H "Authorization: Bearer ' . input_token.Value . '" '
        . '-H "Accept: application/json" '
        . '-H "Content-Type: application/json" '
        . '-d @"' . tempFile . '"'
        . ' --write-out "HTTPSTATUS:%{http_code}" > "' tempOut '"'
    ;shell := ComObject("WScript.Shell")
    ;exec := shell.Exec(EnvGet("ComSpec") . " /C " . curl)
    ;stdout := exec.StdOut.ReadAll()
	;message_api := JSON.Parse(stdout, "UTF-8")
	RunWait(EnvGet("ComSpec") . " /C " . curl, , "Hide")

	if FileExist(tempOut) {

        raw := FileRead(tempOut, "UTF-8-RAW")

        sep := "HTTPSTATUS:"
        pos := InStr(raw, sep, , -1)
        if (pos = 0) {
            MsgBox "❌ Không tìm thấy HTTPSTATUS trong output.`n" raw
            return false
        }

        body := SubStr(raw, 1, pos - 1)
        httpStatus := Trim(SubStr(raw, pos + StrLen(sep)))

        if (httpStatus != "201") {
            MsgBox "❌ HTTP " httpStatus "`n" body
            return false
        }

		if (mode = "sell") {
            btnLuu.Enabled := false
            input_imei.Value := ""
            input_tenkh.Value := ""
            input_sdtkh.Text := ""
            input_mb_giaban.Value := ""
            input_mb_chiphi.Value := ""
            ddlBaohanh_May.Text := "Không bảo hành"
            input_mb_ngayban.Value := FormatTime(A_Now, "yyyy-MM-dd")
            input_mb_no.Value := ""
            input_mb_ghichuban.Value := ""
            MsgBox("Bán sản phẩm thành công")
		}
		if (mode = "add") {
            SaveNguonNhap()
			ddlDevices.Text := ""
			ddlColors.Text := ""
			ddlCapacities.Text := ""
			input_mb_imei.Value := ""
			input_mb_pin.Value := ""
			input_mb_mamay.Value := ""
			ddlNguonnhap.Text := "Khách lẻ"
            input_mb_ngaynhap.Value := FormatTime(A_Now, "yyyy-MM-dd")
			input_mb_gianhap.Value := ""
			input_mb_ghichunhap.Value := ""
            MsgBox("Thêm sản phẩm thành công")
		}
        if (mode = "service") {
            XoaInput()
            MsgBox("Thêm dịch vụ thành công")
        }
	} else {
        MsgBox("Không đọc được phản hồi từ server!")
    }
}

MapToJson(map) {
    json := "{"
    first := true
    for key, value in map {
        if !first
            json .= ","
        json .= '"' . key . '":'
        if IsNumber(value) {
            json .= value
        } else {
            value := StrReplace(value, '\\', '\\\\')
            value := StrReplace(value, '"', '\\"')
            json .= '"' . value . '"'
        }
        first := false
    }
    json .= "}"
    return json
}


;-------------------------------------------OCR--------------------------------------------------------------------------------------
HandleScreenOCR() {
    try{
        static ScreenSnipperProcessName := "ScreenClippingHost.exe"
        local SavedClip := ClipboardAll()
        A_Clipboard := ""
        RunWait("ms-screenclip:")
        WinWaitActive("ahk_exe " ScreenSnipperProcessName,, 2)
        Loop {
            DllCall("user32.dll\GetCursorPos", "int64P", &pt64 := 0)
            try {
                hWnd := DllCall("user32.dll\WindowFromPoint", "int64", pt64)
                ancestorHwnd := DllCall("GetAncestor", "Ptr", hWnd, "UInt", 2)
                if WinGetProcessName(ancestorHwnd) != ScreenSnipperProcessName
                    break
            } catch {
                break
            }
            Sleep 100
        }
        ClipWait(1, 1)
        Sleep 100
        if !DllCall("IsClipboardFormatAvailable", "uint", 2) {
            A_Clipboard := SavedClip
            MsgBox("Không chụp được ảnh hoặc người dùng đã hủy bỏ!", "Lỗi", 0x10)
            return
        }
        DllCall("OpenClipboard", "ptr", A_ScriptHwnd)
        hData := DllCall("GetClipboardData", "uint", 2, "ptr")
        hBitmap := DllCall("User32.dll\CopyImage", "UPtr", hData, "UInt", 0, "Int", 0, "Int", 0, "UInt", 0x2000, "Ptr")
        DllCall("CloseClipboard")
        tempImagePath := A_Temp "\temp_ocr_image.png"
        SaveBitmapToFile(hBitmap, tempImagePath)
        text := RunTesseract(tempImagePath)
        if (text = "") {
            FileDelete(tempImagePath)
            A_Clipboard := SavedClip
            return
        }
        ShowExtractedInfo(text)
        FileDelete(tempImagePath)
        A_Clipboard := SavedClip
    }
    catch{
        MsgBox("Lỗi khi trích xuất được văn bản từ ảnh")
    }
}

RunTesseract(imagePath) {
    outputPath := A_Temp "\tesseract_output"
    command := Format('"{1}" "{2}" "{3}" -l eng', TesseractPath, imagePath, outputPath)
    RunWait command,, "Hide"
    if FileExist(outputPath . ".txt") {
        text := FileRead(outputPath . ".txt")
        ;FileDelete(outputPath . ".txt")
        return text
    }
    return ""
}

SaveBitmapToFile(hBitmap, filePath) {
    pToken := Gdip_Startup()
    pBitmap := Gdip_CreateBitmapFromHBITMAP(hBitmap)
    Gdip_SaveBitmapToFile(pBitmap, filePath)
    Gdip_DisposeImage(pBitmap)
    Gdip_Shutdown(pToken)
}

; --- GDI+ helper ---
Gdip_Startup() {
    if !DllCall("GetModuleHandle", "str", "gdiplus", "ptr")
        DllCall("LoadLibrary", "str", "gdiplus")
    local si := Buffer(24, 0)
    NumPut("uint", 1, si)
    DllCall("gdiplus\GdiplusStartup", "ptr*", &pToken:=0, "ptr", si, "ptr", 0)
    return pToken
}

Gdip_CreateBitmapFromHBITMAP(hBitmap) {
    DllCall("gdiplus\GdipCreateBitmapFromHBITMAP", "ptr", hBitmap, "ptr", 0, "ptr*", &pBitmap:=0)
    return pBitmap
}

Gdip_SaveBitmapToFile(pBitmap, filePath) {
    static PNG_CLSID := "{557CF406-1A04-11D3-9A73-0000F81EF32E}"
    encoderClsid := Buffer(16)
    DllCall("ole32\CLSIDFromString", "wstr", PNG_CLSID, "ptr", encoderClsid)
    DllCall("gdiplus\GdipSaveImageToFile", "ptr", pBitmap, "wstr", filePath, "ptr", encoderClsid, "ptr", 0)
}

Gdip_DisposeImage(pBitmap) {
    DllCall("gdiplus\GdipDisposeImage", "ptr", pBitmap)
}

Gdip_Shutdown(pToken) {
    DllCall("gdiplus\GdiplusShutdown", "ptr", pToken)
    if hModule := DllCall("GetModuleHandle", "str", "gdiplus", "ptr")
        DllCall("FreeLibrary", "ptr", hModule)
}

;----------------------------------------------Tách OCR lấy thông tin ----------------------------------------------------------------

ShowExtractedInfo(text) {
    extractedInfo := Map()

    ; --- Chuẩn hóa ---
    local normalizedText := StrLower(text)
    normalizedText := RegExReplace(normalizedText, "l[i1/\s]*a|lu[a]", "ll/a")
    normalizedText := RegExReplace(normalizedText, "\s+", " ") ; bỏ khoảng trắng dư thừa

    local textForCountryCodeAndIMEI := normalizedText

    ; MsgBox để debug nếu cần
    ; Msgbox(textForCountryCodeAndIMEI)

    ; Serial: dài 10–12 ký tự, gồm chữ + số
	global foundSerial := ""
    extractedInfo["SERIAL"] := foundSerial
    for each, word in StrSplit(textForCountryCodeAndIMEI, [" ", "`n", "`r", "`t"]) {
        if RegExMatch(word, "^[a-z0-9]{10,12}$") {
            digitCount := 0
            for char in StrSplit(word) {
                if RegExMatch(char, "^\d$")
                    digitCount++
            }

            if (digitCount >= 1 && RegExMatch(word, "[a-z]")) {
                foundSerial := StrUpper(word)
                extractedInfo["SERIAL"] := foundSerial
                break
            }
        }
    }

	; --- Ưu tiên IMEI đúng 15 số ---
	imeiNumber := ""
	if RegExMatch(textForCountryCodeAndIMEI, "\b\d{15}\b", &imeiMatch) {
		imeiNumber := imeiMatch[0] ; IMEI hợp lệ 15 chữ số
	} else if (foundSerial != "") {
		imeiNumber := foundSerial ; fallback
	} else {
		imeiNumber := ""
	}
	extractedInfo["IMEI"] := imeiNumber

	; --- Lấy Mã quốc gia
	foundCountryCode := "Không tìm thấy"

	; Bắt mã có 1-3 chữ cái trước /a
	if RegExMatch(textForCountryCodeAndIMEI, "\b([a-z]{1,3})/a\b", &match) {
		rawCode := StrUpper(match[1]) . "/A"  ; vd: vn → VN/A

		; Nếu dài hơn 2 ký tự → lấy 2 ký tự đầu
		if (StrLen(match[1]) > 2)
			foundCountryCode := SubStr(rawCode, 1, 2) . "/A"
		else
			foundCountryCode := rawCode
	}

	extractedInfo["Mã quốc gia"] := foundCountryCode


	; --- Lấy % pin từ Battery Life (KHÔNG lấy từ PC Charging) ---
	foundBatteryLife := ""
	if RegExMatch(normalizedText, "\s+(\d{1,3})% details", &batteryMatch) {
		foundBatteryLife := batteryMatch[1] . "%"
	}
	extractedInfo["Pin"] := foundBatteryLife

    ; --- Lấy số lần sạc từ Charge Times ---
    local foundChargeTimes := ""
    if RegExMatch(normalizedText, "\s*(\d+) times", &chargeMatch) {
        foundChargeTimes := chargeMatch.1 . " lần"
    }
    if RegExMatch(normalizedText, "details \s*(\d+) times", &chargeMatch) {
        foundChargeTimes := chargeMatch.1 . " lần"
    }
    if RegExMatch(normalizedText, "charge times \s*(\d+) times", &chargeMatch) {
        foundChargeTimes := chargeMatch.1 . " lần"
    }
    extractedInfo["Số lần sạc"] := foundChargeTimes
	
	; --- Lấy Tên thiết bị ---
	if RegExMatch(text, "(?i)\b(iphone|ipad)(?:\s+\w+){0,3}", &deviceMatch) {
	deviceName := StrTitle(deviceMatch[0])
        if RegExMatch(deviceName, "^(?i)ipad") {
            deviceName := "iPad"
        } else {
            ; Chuẩn hóa các từ khoá thường gặp
            deviceName := RegExReplace(deviceName, "\bIphone\b", "iPhone")
            deviceName := RegExReplace(deviceName, "\bXs\b", "XS")
            deviceName := RegExReplace(deviceName, "\bXr\b", "XR")
            deviceName := RegExReplace(deviceName, "\bSe\b", "SE")
            deviceName := RegExReplace(deviceName, "\bPro Max\b", "Pro Max")
            deviceName := RegExReplace(deviceName, "\bPro\b", "Pro")
            deviceName := RegExReplace(deviceName, "\bPlus\b", "Plus")
            deviceName := RegExReplace(deviceName, "\bMax\b", "Max")
            deviceName := RegExReplace(deviceName, "\bMini\b", "mini")
            deviceName := RegExReplace(deviceName, "\bAir\b", "Air")
        }

        extractedInfo["Tên thiết bị"] := Trim(deviceName)
    } else {
        extractedInfo["Tên thiết bị"] := ""
    }

	; --- Lấy Dung lượng ---
	if RegExMatch(text, "i)\b(\d{2,4})(?=\s*(g(?:b|8)?|c(?:b|8)?)(?=\b|\s|[\|\)\]\}\,_]|$))", &capMatch) {
		extractedInfo["Dung lượng"] := capMatch[1] . "GB"
	}
	; Bắt lỗi OCR: ví dụ "12868" → "128g8"
	else if RegExMatch(text, "\b(\d{3})(6|g)(8|b)\b", &capMatch) {
		; Dạng "12868" → "128g8" hoặc "128gb"
		extractedInfo["Dung lượng"] := capMatch[1] . "GB"
	}
	else {
		extractedInfo["Dung lượng"] := ""
	}

	; --- Lấy Màu sắc ---
	rawColor := ""

	if RegExMatch(text, "i)\b\d{2,4}\s*(?:gb|g8|g)\s*[\|\)\-]?\s*([a-z\s]{3,30}?)(?=\s+(pc|charging|ios|jailbroken|version|product|$))", &colorMatch) {
		rawColor := Trim(StrLower(colorMatch[1]))
	} else {

		for index, name in color.names {
			pattern := "\b" . RegExReplace(StrLower(name), "\s+", "\s+") . "\b"  ; xử lý tên có dấu cách
			if RegExMatch(StrLower(text), pattern) {
				rawColor := StrLower(name)
				break
			}
		}
	}

	found := false
	for label, id in color.map {
		if (StrLower(label) = rawColor) {
			extractedInfo["Màu sắc"] := label
			extractedInfo["ID màu"] := id
			found := true
			break
		}
	}

	if !found {
		extractedInfo["Màu sắc"] := "Không tìm thấy"
		extractedInfo["ID màu"] := ""
	}

    local resultText := "Kết quả trích xuất:`n"
    resultText .= "Tên thiết bị: " . extractedInfo["Tên thiết bị"] . "`n"
    resultText .= "Dung lượng: " . extractedInfo["Dung lượng"] . "`n"
    resultText .= "Màu sắc: " . extractedInfo["Màu sắc"] . "`n"
    resultText .= "Imei: " . extractedInfo["IMEI"] . "`n"
    resultText .= "Serial: " . extractedInfo["SERIAL"] . "`n"
    resultText .= "Mã quốc gia: " . extractedInfo["Mã quốc gia"] . "`n"
    resultText .= "Pin: " . extractedInfo["Pin"] . "`n"
    resultText .= "Số lần sạc: " . extractedInfo["Số lần sạc"]

    ; Hiển thị MsgBox có nút OK / Cancel
    userResponse := MsgBox(resultText, "Xác nhận dữ liệu", "OKCancel 64")

    if (userResponse = "OK") {
        ; --- Gán vào GUI nếu chọn OK ---
        input_mb_imei.Value := extractedInfo["IMEI"]
        input_mb_pin.Value := extractedInfo["Pin"]
        input_mb_mamay.Value := extractedInfo["Mã quốc gia"]
        input_mb_gianhap.Value := 0
        input_mb_ghichunhap.Value := "Serial: " . extractedInfo["SERIAL"] . " Số lần sạc: " . extractedInfo["Số lần sạc"]
        if device.map.Has(extractedInfo["Tên thiết bị"])
            ddlDevices.Choose(FindIndex(device.names, extractedInfo["Tên thiết bị"]))
        if color.map.Has(extractedInfo["Màu sắc"])
            ddlColors.Choose(FindIndex(color.names, extractedInfo["Màu sắc"]))
        if capacity.map.Has(extractedInfo["Dung lượng"])
            ddlCapacities.Choose(FindIndex(capacity.names, extractedInfo["Dung lượng"]))
    }
	
	FindIndex(arr, value) {
		for index, item in arr {
			if (item = value)
				return index
		}
		return 0 ; Không tìm thấy
	}
}

;----------------------------------------------Phím tắt--------------------------------------------------------------------

^1::
{
    HandleScreenOCR()
}

^2::
{
    myGui.Show("Center")
}
^3::
{
    ExitApp
}