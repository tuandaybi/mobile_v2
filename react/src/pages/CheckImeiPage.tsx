import React, { useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import {
  Row,
  Col,
  Card,
  Input,
  Space,
  message,
  Typography,
  Table,
  Button,
} from "antd";
import type { ColumnsType } from "antd/es/table";
import api from "../../axiosConfig";

const { Text } = Typography;

/** ====== Helpers ====== */
const toNum = (v: any) => {
  if (v == null) return 0;
  const n = Number(String(v).replace(/,/g, ""));
  return Number.isFinite(n) ? n : 0;
};
const currency = (v: any) => `${toNum(v).toLocaleString()} đ`;

const fmtDate = (d?: string | Date | null) => {
  if (!d) return "-";
  const dt = typeof d === "string" ? new Date(d) : d;
  if (Number.isNaN(dt.getTime())) return "-";
  return dt.toLocaleDateString("vi-VN");
};

const paymentMap: Record<string | number, string> = {
  0: "Chuyển khoản",
  1: "Trả góp",
  2: "Tiền mặt",
};

/** ====== API Types (MobileIn là chính) ====== */
type ApiDevice = { id: number; name?: string; code?: string };
type ApiColor = { id: number; vi_name?: string; en_name?: string };
type ApiStorage = { id: number; size_gb?: number };
type ApiCustomer = { id: number; name?: string; phone?: string };

type ApiMobileOut = {
  id: number;
  // BE của bạn đang select: export_date, export_price (có thể chưa có các field dưới)
  export_date?: string | null;
  export_price?: number | null;

  // Nếu BE có mở rộng:
  customer?: ApiCustomer | null;
  payment?: number | string | null; // 0,1,2
  warranty?: number | null; // tháng
  note?: string | null;
};

type ApiMobileIn = {
  id: number;
  imei: string;
  supplier?: string | null;          // nguồn nhập
  import_date?: string | null;       // YYYY-MM-DD
  import_price?: number | null;
  import_note?: string | null;

  battery_capacity?: number | null;  // % pin nếu bạn đang lưu %
  country_code?: string | null;      // KH/A

  device?: ApiDevice | null;
  color?: ApiColor | null;
  storage?: ApiStorage | null;
  mobile_out?: ApiMobileOut | null;  // nếu BE with()
};

/** ====== UI Row Type ====== */
type ProductRow = {
  key: number;
  id: number;
  productName: string; // "12PRM 128GB Graphite KH/A - Pin: 74%"
  imei: string;

  importSource: string;
  importDate?: string | null;
  importPrice?: number | null;
  importNote?: string | null;

  buyer?: string | null;
  buyerPhone?: string | null;
  sellDate?: string | null;
  sellPrice?: number | null;
  payment?: string | number | null;
  warrantyMonths?: number | null;
  sellNote?: string | null;
};

/** Gộp dữ liệu từ API thành 1 dòng UI */
const toRow = (m: ApiMobileIn): ProductRow => {
  const nameParts: string[] = [];
  const model = m.device?.name || m.device?.code || "";
  const gb = m.storage?.size_gb ? `${m.storage.size_gb}GB` : "";
  const colorName = m.color?.vi_name || m.color?.en_name || "";
  const region = m.country_code ? `${m.country_code}` : "";

  const battery =
    m.battery_capacity != null ? ` - Pin: ${m.battery_capacity}%` : "";

  if (model) nameParts.push(model);
  if (gb) nameParts.push(gb);
  if (colorName) nameParts.push(colorName);
  if (region) nameParts.push(region);

  const productName = (nameParts.filter(Boolean).join(" ") || "(Không rõ tên sản phẩm)") + battery;

  const out = m.mobile_out ?? null;

  return {
    key: m.id,
    id: m.id,
    productName,
    imei: m.imei,

    importSource: m.supplier || "-",
    importDate: m.import_date || null,
    importPrice: m.import_price ?? null,
    importNote: m.import_note || null,

    buyer: out?.customer?.name ?? null,
    buyerPhone: out?.customer?.phone ?? null,
    sellDate: out?.export_date ?? null,
    sellPrice: out?.export_price ?? null,
    payment: out?.payment ?? null,
    warrantyMonths: out?.warranty ?? null,
    sellNote: out?.note ?? null,
  };
};

/** ====== Page Component ====== */
const CheckProductPage: React.FC = () => {
  const [imeiSearch, setImeiSearch] = useState("");
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<ProductRow[]>([]);

  /** ✅ Chỉ gọi mobile_in */
  const fetchMobileIns = async (searchTerm: string): Promise<ApiMobileIn[]> => {
    const res = await api.get(`/mobile-in/search-imei/${encodeURIComponent(searchTerm)}`);
    console.log("API /mobile-in/search-imei res =", res);
    let list: ApiMobileIn[] = Array.isArray(res.data?.data)
      ? res.data.data
      : Array.isArray(res.data)
      ? res.data
      : [];
    // Lọc chắc chắn theo đuôi IMEI ở FE (phòng BE trả rộng)
    list = list.filter((x) => String(x.imei || "").endsWith(searchTerm));
    return list;
  };

  const handleSearch = async (value?: string) => {
    const raw = (value ?? imeiSearch).trim();
    if (!raw) {
      message.warning("Vui lòng nhập IMEI để tìm kiếm");
      return;
    }
    const term = raw.replace(/\s+/g, "");

    try {
      setLoading(true);
      const mobileIns = await fetchMobileIns(term);
      const mapped = mobileIns.map(toRow);
      if (mapped.length === 0) {
        message.info("Không tìm thấy sản phẩm với IMEI này");
      }
      setRows(mapped);
    } catch (e: any) {
      const resp = e?.response?.data;
      console.error('API error:', resp || e);
      const msg =
        resp?.hint || resp?.message || e?.message || "Lỗi khi tìm kiếm. Vui lòng thử lại.";
      message.error(String(msg));
    } finally {
      setLoading(false);
    }
  };

  /** Cột Table */
  const columns: ColumnsType<ProductRow> = useMemo(
    () => [
      {
        title: "Tên sản phẩm",
        dataIndex: "productName",
        key: "productName",
        render: (v: string) => <Text strong>{v}</Text>,
      },
      {
        title: "IMEI",
        dataIndex: "imei",
        key: "imei",
        width: 160,
        render: (v: string) => <Text code>{v}</Text>,
      },
      {
        title: "Ngày nhập",
        dataIndex: "importDate",
        key: "importDate",
        width: 120,
        render: (v) => fmtDate(v),
      },
      {
        title: "Người mua",
        dataIndex: "buyer",
        key: "buyer",
        width: 160,
        render: (v) => v || <span style={{ opacity: 0.6 }}>Chưa bán</span>,
      },
      {
        title: "Ngày bán",
        dataIndex: "sellDate",
        key: "sellDate",
        width: 120,
        render: (v) => (v ? fmtDate(v) : "-"),
      },
      {
        title: "Giá bán",
        dataIndex: "sellPrice",
        key: "sellPrice",
        width: 120,
        render: (v) => (v != null ? currency(v) : "-"),
      },
    ],
    []
  );

  /** Nội dung expand: 2 cột */
  const expandedRowRender = (p: ProductRow) => (
    <Card style={{ background: "#fafafa" }}>
      <Row gutter={16}>
        {/* Cột trái: Thông tin nhập */}
        <Col span={12}>
          <p>
            <Text strong>Tên sản phẩm: </Text> {p.productName}
          </p>
          <p>
            <Text strong>IMEI: </Text> {p.imei}
          </p>
          <p>
            <Text strong>Nguồn nhập: </Text> {p.importSource}
          </p>
          <p>
            <Text strong>Ngày nhập: </Text> {fmtDate(p.importDate)}
          </p>
          <p>
            <Text strong>Giá nhập: </Text> {currency(p.importPrice)}
          </p>
          <p>
            <Text strong>Ghi chú nhập: </Text> {p.importNote || "-"}
          </p>
        </Col>

        {/* Cột phải: Thông tin bán */}
        <Col span={12}>
          <p>
            <Text strong>Người mua: </Text> {p.buyer || "-"}
          </p>
          <p>
            <Text strong>SĐT người mua: </Text> {p.buyerPhone || "-"}
          </p>
          <p>
            <Text strong>Ngày bán: </Text> {fmtDate(p.sellDate)}
          </p>
          <p>
            <Text strong>Giá bán: </Text>{" "}
            {p.sellPrice != null ? currency(p.sellPrice) : "-"}
          </p>
          <p>
            <Text strong>Hình thức thanh toán: </Text>{" "}
            {p.payment != null ? paymentMap[p.payment] ?? p.payment : "-"}
          </p>
          <p>
            <Text strong>Thời gian bảo hành: </Text>{" "}
            {p.warrantyMonths != null ? `Bảo hành ${p.warrantyMonths} tháng` : "-"}
          </p>
          <p>
            <Text strong>Ghi chú bán hàng: </Text> {p.sellNote || "-"}
          </p>
        </Col>
      </Row>
    </Card>
  );

  return (
    <MainLayout>
      <Row gutter={16}>
        {/* Tìm kiếm */}
        <Col span={24}>
          <Card>
            <Space.Compact style={{ width: "350px" }}>
              <Input
                placeholder="Nhập đuôi IMEI, ví dụ: 8688"
                value={imeiSearch}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) => setImeiSearch(e.target.value)}
                onPressEnter={() => handleSearch()}
              />
              <Button type="primary" onClick={() => handleSearch()} loading={loading}>
                Tìm
              </Button>
            </Space.Compact>
            <div style={{ marginTop: 8, fontSize: 12, opacity: 0.7 }}>
              Gợi ý: có thể nhập một phần đuôi IMEI để tìm nhiều bản ghi.
            </div>
          </Card>
        </Col>
      </Row>
      <Row gutter={16} style={{ marginTop: 16 }}>
        {/* Kết quả */}
        <Col span={24}>
          <Card>
            <Table<ProductRow>
              rowKey="key"
              columns={columns}
              dataSource={rows}
              loading={loading}
              pagination={{ pageSize: 10, showSizeChanger: true }}
              expandable={{ expandedRowRender }}
              locale={{ emptyText: "Chưa có sản phẩm nào được tìm thấy." }}
              scroll={{ x: "max-content" }}
            />
          </Card>
        </Col>
      </Row>
    </MainLayout>
  );
};

export default CheckProductPage;
