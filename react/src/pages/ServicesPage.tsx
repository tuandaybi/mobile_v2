// src/pages/ServicesPage.tsx
import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Button, Space, Popconfirm, message } from "antd";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import api from "../../axiosConfig";
import { useModalStore } from "../store/modalStore";
import PageTable from "@/components/shared/PageTable";

type Service = {
  id: number;
  name: string;
  price: number;
  customerName: string;
  phone?: string | null;
  cost: number;
  date: string;
  warranty: string;
  note?: string | null;
};

const currency = (n: number) => `${Number(n || 0).toLocaleString()} đ`;
const toNum = (v: any) => {
  if (v == null) return 0;
  const n = Number(String(v).replace(/,/g, ""));
  return Number.isFinite(n) ? n : 0;
};
const warrantyLabel = (months?: number | null) => {
  if (months == null) return "Không bảo hành";
  const m = Number(months);
  return m <= 0 ? "Không bảo hành" : `${m} tháng`;
};

// Chuẩn hoá record trả về từ API (chấp cả 2 kiểu field)
const normalizeService = (r: any): Service => ({
  id: Number(r.id),
  name: r.service_name ?? r.name ?? "",
  price: toNum(r.service_price ?? r.price),
  customerName: r.customer?.name ?? r.customer_name ?? r.customerName ?? "",
  phone: r.customer?.phone ?? r.phone ?? r.phone_number ?? null,
  cost: toNum(r.expense ?? r.cost),
  date: r.service_date ?? r.date ?? r.created_at ?? "",
  warranty: warrantyLabel(r.warranty ?? r.warranty_months ?? r.warranty_month ?? 0),
  note: r.note ?? r.service_note ?? r.description ?? null,
});

// Helper: lấy phần tử "cuối cùng khác null/undefined" từ mảng (hỗ trợ ES2020)
function pickLastDefined<T = any>(arr: T[]): T | undefined {
  for (let i = arr.length - 1; i >= 0; i--) {
    const v = arr[i];
    if (v !== null && v !== undefined) return v;
  }
  return undefined;
}

// Đọc paginator (Resource & thuần) + fallback khi thiếu total/kiểu mảng
function parsePagination(payload: any) {
  const pick = (v: any) => (Array.isArray(v) ? pickLastDefined<any>(v) : v);
  const asNum = (v: any, fallback = 0) => {
    const n = Number(pick(v));
    return Number.isFinite(n) ? n : fallback;
    };

  // Laravel API Resource: { data, links, meta:{ current_page, per_page, last_page, from, to, total? } }
  if (payload?.meta) {
    const m = payload.meta;
    const perPage = asNum(m.per_page, 0);
    const current = asNum(m.current_page, 1);
    const last    = asNum(m.last_page, 0);
    const from    = asNum(m.from, 0);
    const to      = asNum(m.to, 0);
    let total     = asNum(m.total, -1);

    if (total <= 0) {
      if (current === last && to) total = to;
      else if (last && perPage)  total = last * perPage;
      else if (to && from)       total = to - from + 1;
      else                       total = 0;
    }
    return { total, perPage, current };
  }

  // Paginator thuần: { data, total, per_page, current_page, ... }
  if ((payload?.total ?? payload?.per_page ?? payload?.current_page) !== undefined) {
    return {
      total:   asNum(payload.total),
      perPage: asNum(payload.per_page),
      current: asNum(payload.current_page ?? payload.page, 1),
    };
  }

  return null;
}

const Services: React.FC = () => {
  const openService = useModalStore((s) => s.service.open);
  const servicesVersion = useModalStore((s) => s.servicesVersion);

  const [rows, setRows] = useState<Service[]>([]);
  const [loading, setLoading] = useState(false);

  // Server-side pagination/sort/search
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(14);
  const [total, setTotal] = useState(0);
  const [sortBy, setSortBy] = useState<"name" | "customer_name" | "phone" | "price" | "cost" | "date">("date");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("desc");
  const [searchText, setSearchText] = useState("");

  // Map field FE -> sortBy BE (khớp controller)
  const mapFieldToApi = (field?: string): typeof sortBy => {
    switch (field) {
      case "customerName": return "customer_name";
      case "phone":
      case "price":
      case "cost":
      case "date":
      case "name":         return field as any;
      default:             return "date";
    }
  };

  const fetchServices = async (
    keyword = searchText,
    nextPage = page,
    nextPerPage = perPage,
    nextSortBy = sortBy,
    nextSortDir = sortDir
  ) => {
    try {
      setLoading(true);
      const params: any = { page: nextPage, perPage: nextPerPage, sortBy: nextSortBy, sortDir: nextSortDir };
      const q = (keyword ?? "").trim();
      if (q) params.q = q;

      const res = await api.get("/services", { params });
      const payload = res.data;

      const arr: any[] = Array.isArray(payload?.data)
        ? payload.data
        : Array.isArray(payload)
        ? payload
        : Array.isArray(payload?.result)
        ? payload.result
        : [];

      setRows(arr.map(normalizeService));

      const p = parsePagination(payload);
      if (p) {
        setTotal(p.total);
        setPerPage(p.perPage || nextPerPage);
        setPage(p.current || nextPage);
      } else {
        setTotal(arr.length);
        setPerPage(nextPerPage);
        setPage(1);
      }
    } catch (e: any) {
      console.error("Services fetch error:", e);
      message.error(e?.response?.data?.message || "Không tải được dữ liệu Service");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchServices(searchText, page, perPage, sortBy, sortDir);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [servicesVersion]);

  const handleSearch = (value: string) => {
    setSearchText(value);
    setPage(1);
    fetchServices(value, 1, perPage, sortBy, sortDir);
  };

  const onEdit = (record: Service) => {
    openService(true, record);
  };

  const onDelete = async (record: Service) => {
    try {
      await api.delete(`/services/${record.id}`);
      message.success("Xoá thành công");
      await fetchServices(searchText, page, perPage, sortBy, sortDir);
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Xoá thất bại");
    }
  };

  const columns: ColumnsType<Service> = useMemo(
    () => [
      { title: "Tên dịch vụ", dataIndex: "name", key: "name", sorter: true,
        sortOrder: sortBy === "name" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Tên khách", dataIndex: "customerName", key: "customerName", sorter: true,
        sortOrder: sortBy === "customer_name" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "SĐT", dataIndex: "phone", key: "phone", sorter: true,
        sortOrder: sortBy === "phone" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Tiền thu khách", dataIndex: "price", key: "price", sorter: true,
        render: (price: number) => currency(price),
        sortOrder: sortBy === "price" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Chi phí", dataIndex: "cost", key: "cost", sorter: true,
        render: (cost: number) => currency(cost),
        sortOrder: sortBy === "cost" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Ngày tháng", dataIndex: "date", key: "date", sorter: true,
        render: (date: string) => (date ? dayjs(date).format("DD/MM/YYYY") : "-"),
        sortOrder: sortBy === "date" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Bảo hành", dataIndex: "warranty", key: "warranty" },
      { title: "Ghi chú", dataIndex: "note", key: "note" },
      { title: "Chức năng", key: "action",
        render: (_, record) => (
          <Space.Compact>
            <Button size="small" onClick={() => onEdit(record)}>Sửa</Button>
            <Popconfirm title="Xác nhận xoá?" onConfirm={() => onDelete(record)} okText="Xoá" cancelText="Huỷ">
              <Button type="primary" danger size="small">Xoá</Button>
            </Popconfirm>
          </Space.Compact>
        )},
    ],
    [sortBy, sortDir]
  );

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<Service>
            title="🛠 Danh sách dịch vụ"
            data={rows}
            columns={columns}
            pageSize={perPage}
            rowKey="id"
            loading={loading}
            onSearch={handleSearch}
            scrollX="max-content"
            pagination={{
              current: page,
              pageSize: perPage,
              total,
              showSizeChanger: true,
              pageSizeOptions: [10, 14, 20, 30, 50, 100],
            }}
            onTableChange={(pagination, _filters, sorter) => {
              const nextPage = pagination.current || 1;
              const nextPerPage = pagination.pageSize || perPage;

              let nextSortBy = sortBy;
              let nextSortDir: "asc" | "desc" = sortDir;

              const s: any = Array.isArray(sorter) ? sorter[0] : sorter;
              if (s && s.field) {
                nextSortBy = mapFieldToApi(s.field);
                nextSortDir = s.order === "ascend" ? "asc" : "desc";
              }

              setPage(nextPage);
              setPerPage(nextPerPage);
              setSortBy(nextSortBy);
              setSortDir(nextSortDir);
              fetchServices(searchText, nextPage, nextPerPage, nextSortBy, nextSortDir);
            }}
          />
        </Col>
      </Row>
    </MainLayout>
  );
};

export default Services;
