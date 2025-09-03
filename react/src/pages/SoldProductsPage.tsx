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
  device_name: string;
  country_code?: string;
  storage_gb?: number;
  color_name?: string;
  imei?: string;
  customer_name?: string;
  customer_phone?: string;
  sale_date?: string; // ISO
  price: number;
  note?: string;
  warranty?: number;
  raw?: any;
};

// ====== utils ======
const fmtDate = (v: unknown) => {
  if (v == null) return "—";
  // @ts-ignore
  if (dayjs.isDayjs?.(v)) return (v as dayjs.Dayjs).format("DD/MM/YYYY");
  if (typeof v === "string") {
    const normalized = v.includes(".") ? v.replace(/\.\d+Z$/, "Z") : v;
    const m = dayjs(normalized);
    return m.isValid() ? m.format("DD/MM/YYYY") : "—";
  }
  if (v instanceof Date) {
    const m = dayjs(v);
    return m.isValid() ? m.format("DD/MM/YYYY") : "—";
  }
  if (typeof v === "number") {
    const m = dayjs(new Date(v));
    return m.isValid() ? m.format("DD/MM/YYYY") : "—";
  }
  const m = dayjs((v as any)?.toString?.() ?? (v as any));
  return m.isValid() ? m.format("DD/MM/YYYY") : "—";
};

const addMonthsEOM = (date: Date, months: number) => {
  const y = date.getFullYear();
  const m = date.getMonth();
  const d = date.getDate();
  const targetMonth = m + months;
  const targetYear = y + Math.floor(targetMonth / 12);
  const normMonth = ((targetMonth % 12) + 12) % 12;
  const daysInTarget = new Date(targetYear, normMonth + 1, 0).getDate();
  const newDay = Math.min(d, daysInTarget);
  return new Date(targetYear, normMonth, newDay);
};

const warrantyEndDate = (saleDate?: string | Date | null, months?: number | null): Date | null => {
  if (!saleDate || !months) return null;
  const base = typeof saleDate === "string" ? new Date(saleDate) : saleDate;
  if (!(base instanceof Date) || Number.isNaN(base.getTime())) return null;
  return addMonthsEOM(base, Number(months));
};

const currency = (v: any) => `${Number(v ?? 0).toLocaleString()} đ`;

const buildProduct = (r: SoldRow) => {
  const parts = [
    r.device_name,
    r.country_code ? `${r.country_code}` : undefined,
    r.storage_gb ? `${r.storage_gb}GB` : undefined,
    r.color_name ? `(${r.color_name})` : undefined,
    r.imei ? `${r.imei}` : undefined,
  ].filter(Boolean);
  return parts.join(" - ");
};

// --- paginator parser (chuẩn Laravel Resource + fallback) ---
function pickLastDefined<T = any>(arr: T[]): T | undefined {
  for (let i = arr.length - 1; i >= 0; i--) {
    const v = arr[i];
    if (v !== null && v !== undefined) return v;
  }
  return undefined;
}
function parsePagination(payload: any) {
  const pick = (v: any) => (Array.isArray(v) ? pickLastDefined<any>(v) : v);
  const asNum = (v: any, fallback = 0) => {
    const n = Number(pick(v));
    return Number.isFinite(n) ? n : fallback;
  };
  if (payload?.meta) {
    const m = payload.meta;
    const perPage = asNum(m.per_page, 0);
    const current = asNum(m.current_page, 1);
    const last = asNum(m.last_page, 0);
    const from = asNum(m.from, 0);
    const to = asNum(m.to, 0);
    let total = asNum(m.total, -1);
    if (total <= 0) {
      if (current === last && to) total = to;
      else if (last && perPage) total = last * perPage;
      else if (to && from) total = to - from + 1;
      else total = 0;
    }
    return { total, perPage, current };
  }
  if ((payload?.total ?? payload?.per_page ?? payload?.current_page) !== undefined) {
    return {
      total: asNum(payload.total),
      perPage: asNum(payload.per_page),
      current: asNum(payload.current_page ?? payload.page, 1),
    };
  }
  return null;
}

// ================== Component ==================
const SoldMobilesPage: React.FC = () => {
  const [rows, setRows] = useState<SoldRow[]>([]);
  const [loading, setLoading] = useState(false);

  // server-side paging/sort/search
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(14);
  const [total, setTotal] = useState(0);
  const [sortBy, setSortBy] = useState<
    "id" | "date" | "price" | "warranty" | "device_name" | "imei" | "customer_name" | "phone"
  >("date");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("desc");
  const [searchText, setSearchText] = useState("");

  // FE field -> BE sortBy key
  const mapFieldToApi = (field?: string): typeof sortBy => {
    switch (field) {
      case "device_name":
      case "imei":
      case "price":
      case "warranty":
        return field as any;
      case "customer_name":
        return "customer_name";
      case "customer_phone":
        return "phone";
      case "sale_date":
        return "date";
      default:
        return "date";
    }
  };

  const normalize = (x: any): SoldRow => ({
    id: Number(x.id),
    device_name: x.device_name ?? x.mobile_in?.device?.name ?? "",
    country_code: x.country_code ?? x.mobile_in?.country_code ?? x.device?.country_code,
    storage_gb: Number(x.storage_gb ?? x.mobile_in?.storage?.size_gb ?? x.storage_gb ?? 0),
    imei: x.imei ?? x.mobile_in?.imei ?? "",
    color_name: x.color_name ?? x.mobile_in?.color?.vi_name ?? x.color_name,
    customer_name: x.customer_name ?? x.customer?.name,
    customer_phone: x.customer_phone ?? x.customer?.phone,
    sale_date: x.export_date ?? x.sale_date ?? x.date ?? x.created_at,
    price: Number(x.price ?? x.export_price ?? x.total ?? x.amount ?? 0),
    note: x.note ?? "",
    warranty: Number(x.warranty ?? 0),
    raw: {
      ...x,
      mobile_out_id: Number(x.id),
      mobile_in_id: Number(x.mobile_in_id ?? x.mobile_in?.id ?? 0),
    },
  });

  const fetchList = async (
    keyword = searchText,
    nextPage = page,
    nextPerPage = perPage,
    nextSortBy = sortBy,
    nextSortDir = sortDir
  ) => {
    try {
      setLoading(true);
      const params: any = {
        page: nextPage,
        perPage: nextPerPage,
        sortBy: nextSortBy,
        sortDir: nextSortDir,
      };
      const q = (keyword ?? "").trim();
      if (q) params.q = q;

      const res = await api.get("/mobile-out", { params });
      const payload = res.data;

      const arr: any[] = Array.isArray(payload?.data)
        ? payload.data
        : Array.isArray(payload)
        ? payload
        : Array.isArray(payload?.result)
        ? payload.result
        : [];
      setRows(arr.map(normalize));

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
      console.error(e);
      message.error(e?.response?.data?.message ?? "Lỗi tải danh sách máy đã bán");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchList();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const handleSearch = (value: string) => {
    setSearchText(value);
    setPage(1);
    fetchList(value, 1, perPage, sortBy, sortDir);
  };

  const handleEdit = (record: SoldRow) => {
    const payload = {
      ...(record.raw ?? {}),
      mobile_out_id: record.raw?.mobile_out_id ?? record.id,
      mobile_in_id: record.raw?.mobile_in_id ?? 0,
    };
    useModalStore.getState().sellMobile.open(true, payload);
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
          await fetchList(searchText, page, perPage, sortBy, sortDir);
        } catch (e: any) {
          console.error(e);
          message.error(e?.response?.data?.message ?? "Xóa thất bại");
        }
      },
    });
  };

  const columns: ColumnsType<SoldRow> = useMemo(
    () => [
      {
        title: "Tên SP",
        dataIndex: "device_name",
        key: "device_name",
        render: (_, r) => buildProduct(r),
        sorter: true,
        sortOrder: sortBy === "device_name" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Tên Khách Hàng",
        dataIndex: "customer_name",
        key: "customer_name",
        sorter: true,
        sortOrder: sortBy === "customer_name" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "SĐT Khách hàng",
        dataIndex: "customer_phone",
        key: "customer_phone",
        sorter: true,
        sortOrder: sortBy === "phone" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Ngày Bán",
        dataIndex: "sale_date",
        key: "sale_date",
        render: (d) => fmtDate(d),
        sorter: true,
        sortOrder: sortBy === "date" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Bảo hành",
        dataIndex: "warranty",
        key: "warranty",
        render: (m: number | null, row: SoldRow) => {
          if (!m) return "—";
          const end = warrantyEndDate(row.sale_date, m);
          return end ? fmtDate(end) : "—";
        },
        sorter: true,
        sortOrder: sortBy === "warranty" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Giá Bán",
        dataIndex: "price",
        key: "price",
        render: (p) => currency(p),
        sorter: true,
        sortOrder: sortBy === "price" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      { title: "Ghi chú", dataIndex: "note", key: "note" },
      {
        title: "Chức năng",
        key: "action",
        render: (_, record) => (
          <Space.Compact>
            <Button size="small" onClick={() => handleEdit(record)}>
              Sửa
            </Button>
            <Button danger size="small" onClick={() => handleDelete(record)}>
              Xóa
            </Button>
          </Space.Compact>
        ),
      },
    ],
    [sortBy, sortDir]
  );

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<SoldRow>
            title="📦 Danh sách máy đã bán"
            data={rows}
            loading={loading}
            columns={columns}
            rowKey="id"
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
              fetchList(searchText, nextPage, nextPerPage, nextSortBy, nextSortDir);
            }}
          />
        </Col>
      </Row>
    </MainLayout>
  );
};

export default SoldMobilesPage;
