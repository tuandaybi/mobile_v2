// src/pages/ExpensesPage.tsx
import React, { useEffect, useMemo, useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Button, Space, Popconfirm, message, Select, DatePicker, Tag } from "antd";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import api from "../../axiosConfig";
import { useModalStore } from "../store/modalStore";
import PageTable from "@/components/shared/PageTable";

type ExpenseCategoryRef = {
  id: number;
  name: string;
  code?: string | null;
};

type Expense = {
  id: number;
  category_id: number;
  category: ExpenseCategoryRef | null;
  name: string;
  amount: number;
  date: string;
  userName?: string | null;
  note?: string | null;
};

const currency = (n: number) => `${Number(n || 0).toLocaleString()} đ`;
const toNum = (v: any) => {
  if (v == null) return 0;
  const n = Number(String(v).replace(/,/g, ""));
  return Number.isFinite(n) ? n : 0;
};

const tagColorFor = (code?: string | null): string => {
  switch (code) {
    case "fixed":
      return "blue";
    case "inventory":
      return "orange";
    case "other":
      return "default";
    default:
      return "geekblue";
  }
};

const normalizeExpense = (r: any): Expense => ({
  id: Number(r.id),
  category_id: Number(r.category_id ?? r.category?.id ?? 0),
  category: r.category
    ? { id: Number(r.category.id), name: r.category.name ?? "", code: r.category.code ?? null }
    : null,
  name: r.name ?? "",
  amount: toNum(r.amount),
  date: r.date ?? r.created_at ?? "",
  userName: r.user?.name ?? null,
  note: r.note ?? null,
});

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

const Expenses: React.FC = () => {
  const openExpense = useModalStore((s) => s.expense.open);
  const expensesVersion = useModalStore((s) => s.expensesVersion);

  const [rows, setRows] = useState<Expense[]>([]);
  const [loading, setLoading] = useState(false);
  const [categories, setCategories] = useState<ExpenseCategoryRef[]>([]);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(14);
  const [total, setTotal] = useState(0);
  const [sortBy, setSortBy] = useState<"name" | "amount" | "date" | "category" | "user_name">("date");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("desc");
  const [searchText, setSearchText] = useState("");

  const [filterCategoryId, setFilterCategoryId] = useState<number | undefined>(undefined);
  const [dateFrom, setDateFrom] = useState<string | undefined>(undefined);
  const [dateTo, setDateTo] = useState<string | undefined>(undefined);

  const mapFieldToApi = (field?: string): typeof sortBy => {
    switch (field) {
      case "userName":
        return "user_name";
      case "categoryName":
        return "category";
      case "name":
      case "amount":
      case "date":
        return field as any;
      default:
        return "date";
    }
  };

  const fetchCategories = async () => {
    try {
      const res = await api.get("/expense-categories");
      const arr: any[] = Array.isArray(res.data) ? res.data : [];
      setCategories(arr.map((c) => ({ id: Number(c.id), name: c.name ?? "", code: c.code ?? null })));
    } catch {
      setCategories([]);
    }
  };

  const fetchExpenses = async (
    keyword = searchText,
    nextPage = page,
    nextPerPage = perPage,
    nextSortBy = sortBy,
    nextSortDir = sortDir,
    nextCategoryId = filterCategoryId,
    nextDateFrom = dateFrom,
    nextDateTo = dateTo,
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
      if (nextCategoryId) params.category_id = nextCategoryId;
      if (nextDateFrom) params.date_from = nextDateFrom;
      if (nextDateTo) params.date_to = nextDateTo;

      const res = await api.get("/expenses", { params });
      const payload = res.data;

      const arr: any[] = Array.isArray(payload?.data)
        ? payload.data
        : Array.isArray(payload)
        ? payload
        : [];

      setRows(arr.map(normalizeExpense));

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
      console.error("Expenses fetch error:", e);
      message.error(e?.response?.data?.message || "Không tải được dữ liệu Chi phí");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCategories();
  }, []);

  useEffect(() => {
    fetchExpenses();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [expensesVersion]);

  const handleSearch = (value: string) => {
    setSearchText(value);
    setPage(1);
    fetchExpenses(value, 1, perPage, sortBy, sortDir, filterCategoryId, dateFrom, dateTo);
  };

  const handleCategoryChange = (val?: number) => {
    setFilterCategoryId(val);
    setPage(1);
    fetchExpenses(searchText, 1, perPage, sortBy, sortDir, val, dateFrom, dateTo);
  };

  const handleDateRangeChange = (range: any) => {
    const from = range?.[0] ? dayjs(range[0]).format("YYYY-MM-DD") : undefined;
    const to = range?.[1] ? dayjs(range[1]).format("YYYY-MM-DD") : undefined;
    setDateFrom(from);
    setDateTo(to);
    setPage(1);
    fetchExpenses(searchText, 1, perPage, sortBy, sortDir, filterCategoryId, from, to);
  };

  const onEdit = (record: Expense) => {
    openExpense(true, record);
  };

  const onDelete = async (record: Expense) => {
    try {
      await api.delete(`/expenses/${record.id}`);
      message.success("Xoá thành công");
      await fetchExpenses();
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Xoá thất bại");
    }
  };

  const totalAmount = useMemo(
    () => rows.reduce((s, r) => s + (r.amount || 0), 0),
    [rows],
  );

  const columns: ColumnsType<Expense> = useMemo(
    () => [
      {
        title: "Loại",
        key: "categoryName",
        sorter: true,
        render: (_: any, r: Expense) => {
          if (!r.category) return "-";
          return <Tag color={tagColorFor(r.category.code)}>{r.category.name}</Tag>;
        },
        sortOrder: sortBy === "category" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Tên chi phí",
        dataIndex: "name",
        key: "name",
        sorter: true,
        sortOrder: sortBy === "name" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Số tiền",
        dataIndex: "amount",
        key: "amount",
        sorter: true,
        render: (a: number) => currency(a),
        sortOrder: sortBy === "amount" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Ngày chi",
        dataIndex: "date",
        key: "date",
        sorter: true,
        render: (d: string) => (d ? dayjs(d).format("DD/MM/YYYY") : "-"),
        sortOrder: sortBy === "date" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      {
        title: "Người nhập",
        dataIndex: "userName",
        key: "userName",
        sorter: true,
        sortOrder: sortBy === "user_name" ? (sortDir === "asc" ? "ascend" : "descend") : null,
      },
      { title: "Ghi chú", dataIndex: "note", key: "note" },
      {
        title: "Chức năng",
        key: "action",
        render: (_, record) => (
          <Space.Compact>
            <Button size="small" onClick={() => onEdit(record)}>Sửa</Button>
            <Popconfirm title="Xác nhận xoá?" onConfirm={() => onDelete(record)} okText="Xoá" cancelText="Huỷ">
              <Button type="primary" danger size="small">Xoá</Button>
            </Popconfirm>
          </Space.Compact>
        ),
      },
    ],
    [sortBy, sortDir],
  );

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<Expense>
            title={
              <span>
                💸 Quản lý chi phí
                <span style={{ marginLeft: 16, fontSize: 14, color: "#666" }}>
                  Tổng (đang xem): <strong>{currency(totalAmount)}</strong>
                </span>
              </span>
            }
            data={rows}
            columns={columns}
            pageSize={perPage}
            rowKey="id"
            loading={loading}
            onSearch={handleSearch}
            scrollX="max-content"
            extra={
              <Space>
                <DatePicker.RangePicker
                  format="DD/MM/YYYY"
                  onChange={handleDateRangeChange}
                  allowClear
                  placeholder={["Từ ngày", "Đến ngày"]}
                />
                <Select
                  allowClear
                  placeholder="Lọc loại"
                  style={{ width: 180 }}
                  value={filterCategoryId}
                  onChange={handleCategoryChange}
                  options={categories.map((c) => ({ value: c.id, label: c.name }))}
                  showSearch
                  optionFilterProp="label"
                />
              </Space>
            }
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
              fetchExpenses(searchText, nextPage, nextPerPage, nextSortBy, nextSortDir, filterCategoryId, dateFrom, dateTo);
            }}
          />
        </Col>
      </Row>
    </MainLayout>
  );
};

export default Expenses;
