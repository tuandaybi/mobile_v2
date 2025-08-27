import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Button, Space, message, Modal } from "antd";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import PageTable from "@/components/shared/PageTable";
import api from "../../axiosConfig";
import { useModalStore } from "../store/modalStore";

type SoldRow = {
  id: number;
  device_name: string;     // iPhone 16 Plus
  country_code?: string;   // VN/A, LL/A...
  storage_gb?: number;     // 128
  color_name?: string;     // White
  customer_name?: string;
  customer_phone?: string;
  sale_date?: string;      // ISO (có thể có microseconds)
  price: number;
  note?: string;
  warranty?: number;

  // nếu bạn muốn mở modal sửa bán:
  raw?: any;               // giữ nguyên để truyền vào sellMobile.open(true, raw)
};

const fmtDate = (v: unknown) => {
  if (v == null) return '—';

  // dayjs instance
  // @ts-ignore
  if (dayjs.isDayjs?.(v)) return (v as dayjs.Dayjs).format('DD/MM/YYYY');

  // string (có thể có microseconds ".000000Z")
  if (typeof v === 'string') {
    const normalized = v.includes('.') ? v.replace(/\.\d+Z$/, 'Z') : v;
    const m = dayjs(normalized);
    return m.isValid() ? m.format('DD/MM/YYYY') : '—';
  }

  // Date object
  if (v instanceof Date) {
    const m = dayjs(v);
    return m.isValid() ? m.format('DD/MM/YYYY') : '—';
  }

  // timestamp number (ms)
  if (typeof v === 'number') {
    const m = dayjs(new Date(v));
    return m.isValid() ? m.format('DD/MM/YYYY') : '—';
  }

  // fallback: thử parse “cưỡng bức”
  const m = dayjs((v as any)?.toString?.() ?? v as any);
  return m.isValid() ? m.format('DD/MM/YYYY') : '—';
};

const currency = (v: any) => {
  const n = Number(v ?? 0);
  return `${n.toLocaleString()} đ`;
};

const buildProduct = (r: SoldRow) => {
  const parts = [
    r.device_name,
    r.country_code ? `${r.country_code}` : undefined,
    r.storage_gb ? `${r.storage_gb}GB` : undefined,
    r.color_name ? `(${r.color_name})` : undefined,
  ].filter(Boolean);
  // iPhone 16 Plus - VN/A - 128GB - (White)
  return parts.join(" - ");
};

const SoldMobilesPage: React.FC = () => {
  const { sellMobile } = useModalStore();
  const [rows, setRows] = useState<SoldRow[]>([]);
  const [loading, setLoading] = useState(false);

  const fetchList = async (q?: string) => {
    try {
      setLoading(true);
      const res = await api.get("/mobile-out", { params: { q, limit: 50 } });
      // Chuẩn hoá dữ liệu từ BE
      const list: any[] = Array.isArray(res.data?.data) ? res.data.data
                        : Array.isArray(res.data) ? res.data
                        : [];
      const mapped: SoldRow[] = list.map((x: any) => ({
        id: Number(x.id),
        device_name: x.device_name ?? x.mobile_in?.device?.name ?? "",
        country_code: x.country_code ?? x.mobile_in?.country_code ?? x.device?.country_code,
        storage_gb: Number(x.storage_gb ?? x.mobile_in?.storage?.size_gb ?? x.storage_gb),
        color_name: x.color_name ?? x.mobile_in?.color?.vi_name ?? x.color_name,
        customer_name: x.customer_name ?? x.customer?.name,
        customer_phone: x.customer_phone ?? x.customer?.phone,
        sale_date: x.sale_date ?? x.date ?? x.created_at,
        price: Number(x.price ?? x.total ?? x.amount ?? 0),
        note: x.note ?? "",
        warranty: x.warranty ?? 0,
        raw: {
          ...x,
          mobile_out_id: Number(x.id),
          mobile_in_id: Number(x.mobile_in_id ?? x.mobile_in?.id ?? 0),
        },
      }));
      setRows(mapped);
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message ?? "Lỗi tải danh sách máy đã bán");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchList();
  }, []);

  const handleSearch = (value: string) => {
    fetchList(value);
  };

  const handleEdit = (record: SoldRow) => {
    // mở modal "sửa bán" của bạn (đã có trong modalStore)
    const payload = {
    ...(record.raw ?? {}),
    mobile_out_id: record.raw?.mobile_out_id ?? record.id,
    mobile_in_id: record.raw?.mobile_in_id ?? 0,
  };
    sellMobile.open(true, payload);
  };

  const handleDelete = async (record: SoldRow) => {
    Modal.confirm({
      title: `Xóa đơn bán #${record.id}?`,
      content: "Thao tác sẽ xóa bản ghi nợ liên quan và hoàn trạng thái máy về chưa bán.",
      okText: "Xóa",
      okButtonProps: { danger: true },
      cancelText: "Hủy",
      onOk: async () => {
        try {
          await api.delete(`/mobile-out/${record.id}`);
          message.success("Đã xóa đơn bán & công nợ liên quan");
          await fetchList();
        } catch (e: any) {
          console.error(e);
          message.error(e?.response?.data?.message ?? "Xóa thất bại");
        }
      },
    });
  };

  const columns: ColumnsType<SoldRow> = useMemo(() => [
    { title: "Tên SP", dataIndex: "device_name", key: "device_name", render: (_, r) => buildProduct(r) },
    { title: "Tên Khách Hàng", dataIndex: "customer_name", key: "customer_name" },
    { title: "SĐT Khách hàng", dataIndex: "customer_phone", key: "customer_phone" },
    { title: "Ngày Bán", dataIndex: "sale_date", key: "sale_date", render: (d) => fmtDate(d) },
    { title: "Bảo hành", dataIndex: "warranty", key: "warranty", render: (d) => fmtDate(d) },
    { title: "Giá Bán", dataIndex: "price", key: "price", render: (p) => currency(p) },
    { title: "Ghi chú", dataIndex: "note", key: "note" },
    {
      title: "Chức năng",
      key: "action",
      render: (_, record) => (
        <Space.Compact>
          <Button size="small" onClick={() => handleEdit(record)}>Sửa</Button>
          <Button danger size="small" onClick={() => handleDelete(record)}>Xóa</Button>
        </Space.Compact>
      ),
    },
  ], []);

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<SoldRow>
            title="📦 Danh sách máy đã bán"
            data={rows}
            loading={loading}
            columns={columns}
            pageSize={14}
            rowKey="id"
            onSearch={handleSearch}
            scrollX="max-content"
          />
        </Col>
      </Row>
    </MainLayout>
  );
};

export default SoldMobilesPage;
