import MainLayout from "../../../components/layout/MainLayout";
import { useState, useEffect, useMemo } from "react";
import api from "../../../../axiosConfig";
import PageTable from "@/components/shared/PageTable";

import {
  Button,
  Space,
  Modal,
  Form,
  Input,
  Popconfirm,
  message,
  Tag,
} from "antd";
import { EditOutlined, DeleteOutlined, PlusOutlined } from "@ant-design/icons";
import type { ColumnsType } from "antd/es/table";

type Customer = {
  id: number;
  store_id: number;
  name: string;
  phone?: string | null;
  store_name?: string | null;
  amount_spent?: number | null;
  amount_debt?: number | null;
  social_link?: string | null;
  note?: string | null;
  created_at?: string | null; // ISO string
};

export default function CustomersPage() {
  const [form] = Form.useForm<Customer>();

  // ===== Server-side state =====
  const [rows, setRows] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(14);
  const [total, setTotal] = useState(0);

  const [sortBy, setSortBy] = useState<
    "id" | "name" | "phone" | "spent" | "debt" | "store" | "created_at"
  >("id");
  const [sortDir, setSortDir] = useState<"asc" | "desc">("desc");

  const [searchText, setSearchText] = useState("");

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editing, setEditing] = useState<Customer | null>(null);

  // ===== Utils =====
  const fmtDate = (d?: string | null) => {
    if (!d) return "—";
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return "—";
    return dt.toLocaleString("vi-VN");
  };

  const currency = (v: any) => `${Number(v ?? 0).toLocaleString()} đ`;

  const normalize = (x: any): Customer => ({
    id: Number(x.id),
    store_id: Number(x.store_id),
    name: x.name,
    phone: x.phone ?? null,
    store_name: x.store?.name ?? x.store_name ?? null,
    amount_spent: Number(x.spent?.total ?? x.amount_spent ?? 0),
    amount_debt: Number(x.debt?.total ?? x.amount_debt ?? 0),
    social_link: x.social_link ?? null,
    note: x.note ?? null,
    created_at: x.created_at ?? null,
  });

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
        total: Number(payload.total ?? 0),
        perPage: Number(payload.per_page ?? payload.perPage ?? 0),
        current: Number(payload.current_page ?? payload.page ?? 1),
      };
    }
    return null;
  }

  // FE column -> BE sortBy key (whitelist bên BE nên tương ứng)
  const mapFieldToApi = (field?: string): typeof sortBy => {
    switch (field) {
      case "name": return "name";
      case "phone": return "phone";
      case "amount_spent": return "spent";
      case "amount_debt": return "debt";
      case "store_name": return "store";
      case "created_at": return "created_at";
      default: return "id";
    }
  };

  // ===== Fetch =====
  const fetchCustomers = async (
    keyword = searchText,
    nextPage = page,
    nextPerPage = perPage,
    nextSortBy = sortBy,
    nextSortDir = sortDir
  ) => {
    setLoading(true);
    try {
      const params: any = {
        q: keyword?.trim() || undefined,
        page: nextPage,
        perPage: nextPerPage,
        sortBy: nextSortBy,
        sortDir: nextSortDir,
      };
      // index() của bạn trả kiểu Resource paginate => { data, meta }
      const res = await api.get("admin/ad-customers", { params });
      const payload = res.data;

      const list: any[] = Array.isArray(payload?.data)
        ? payload.data
        : Array.isArray(payload)
        ? payload
        : Array.isArray(payload?.result)
        ? payload.result
        : [];

      setRows(list.map(normalize));

      const p = parsePagination(payload);
      if (p) {
        setTotal(p.total);
        setPerPage(p.perPage || nextPerPage);
        setPage(p.current || nextPage);
      } else {
        setTotal(list.length);
        setPerPage(nextPerPage);
        setPage(1);
      }
    } catch (e: any) {
      console.error(e);
      message.error(e?.response?.data?.message || "Lỗi khi tải danh sách khách hàng");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCustomers();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // ===== CRUD UI =====
  const openAdd = () => {
    setEditing(null);
    setIsModalOpen(true);
    setTimeout(() => {
      form.resetFields();
      form.setFieldsValue({
        name: "",
        phone: undefined,
        social_link: undefined,
        note: undefined,
      });
    }, 0);
  };

  const openEdit = (rec: Customer) => {
    setEditing(rec);
    setIsModalOpen(true);
    setTimeout(() => {
      form.setFieldsValue({
        name: rec.name,
        phone: rec.phone ?? undefined,
        social_link: rec.social_link ?? undefined,
        note: rec.note ?? undefined,
      });
    }, 0);
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setSaving(true);

      if (editing) {
        await api.put(`admin/customers/${editing.id}`, values);
        message.success("Cập nhật khách hàng thành công");
        // refresh để cập nhật tổng chi tiêu/nợ
        await fetchCustomers(searchText, page, perPage, sortBy, sortDir);
      } else {
        await api.post("admin/customers", values);
        message.success("Thêm khách hàng thành công");
        // về trang 1 (thường sort id desc thì bản mới lên đầu)
        setPage(1);
        await fetchCustomers(searchText, 1, perPage, sortBy, sortDir);
      }

      setIsModalOpen(false);
      setEditing(null);
      form.resetFields();
    } catch (e: any) {
      const data = e?.response?.data;
      let firstError: string | undefined;
      if (data?.errors && typeof data.errors === "object" && !Array.isArray(data.errors)) {
        const errorValues = Object.values(data.errors);
        if (Array.isArray(errorValues) && errorValues.length > 0 && Array.isArray(errorValues[0])) {
          firstError = (errorValues[0] as string[])[0];
        }
      }
      firstError = firstError || data?.message || "Lỗi khi lưu khách hàng";
      message.error(String(firstError));
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await api.delete(`admin/customers/${id}`);
      message.success("Đã xoá khách hàng");
      // nếu xoá bản cuối 1 trang, có thể cần lùi trang; đơn giản: refetch trang hiện tại
      await fetchCustomers(searchText, page, perPage, sortBy, sortDir);
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Không thể xoá khách hàng");
    }
  };

  const handleSearch = (v: string) => {
    setSearchText(v);
    setPage(1);
    fetchCustomers(v, 1, perPage, sortBy, sortDir);
  };

  // ===== Columns (server-side sorter) =====
  const columns: ColumnsType<Customer> = useMemo(
    () => [
      { title: "Tên KH", dataIndex: "name", key: "name", sorter: true,
        sortOrder: sortBy === "name" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "SĐT", dataIndex: "phone", key: "phone",
        render: (v: string | null) => v || "—",
        sorter: true,
        sortOrder: sortBy === "phone" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Mạng xã hội", dataIndex: "social_link", key: "social_link",
        render: (v: string | null) =>
          v ? (/^https?:\/\//i.test(v) ? <a href={v} target="_blank" rel="noreferrer">{v}</a> : <Tag>{v}</Tag>) : "—" },
      { title: "Tổng chi tiêu", dataIndex: "amount_spent", key: "amount_spent",
        render: (v: number | null) => currency(v),
        sorter: true,
        sortOrder: sortBy === "spent" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Tổng nợ", dataIndex: "amount_debt", key: "amount_debt",
        render: (v: number | null) => currency(v),
        sorter: true,
        sortOrder: sortBy === "debt" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Cửa hàng", dataIndex: "store_name", key: "store_name",
        render: (v: string | null) => v || "—",
        sorter: true,
        sortOrder: sortBy === "store" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Ghi chú", dataIndex: "note", key: "note", ellipsis: true, render: (v: string | null) => v || "—" },
      { title: "Tạo lúc", dataIndex: "created_at", key: "created_at",
        render: (v: string | null) => fmtDate(v), width: 170, sorter: true,
        sortOrder: sortBy === "created_at" ? (sortDir === "asc" ? "ascend" : "descend") : null },
      { title: "Hành động", key: "actions",
        render: (_: any, rec: Customer) => (
          <Space.Compact>
            <Button icon={<EditOutlined />} size="small" type="primary" onClick={() => openEdit(rec)}>Sửa</Button>
            <Popconfirm title="Xoá khách hàng này?" onConfirm={() => handleDelete(rec.id)}>
              <Button danger icon={<DeleteOutlined />} size="small" type="primary">Xoá</Button>
            </Popconfirm>
          </Space.Compact>
        )},
    ],
    [sortBy, sortDir]
  );

  return (
    <MainLayout>
      <PageTable<Customer>
        title="Khách hàng"
        data={rows}
        columns={columns}
        rowKey="id"
        onSearch={handleSearch}
        scrollX="max-content"
        loading={loading || saving}
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={openAdd}>
            Thêm
          </Button>
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
          fetchCustomers(searchText, nextPage, nextPerPage, nextSortBy, nextSortDir);
        }}
      />

      <Modal
        open={isModalOpen}
        title={editing ? "Sửa khách hàng" : "Thêm khách hàng"}
        onOk={handleSave}
        onCancel={() => setIsModalOpen(false)}
        confirmLoading={saving}
        destroyOnClose
        afterOpenChange={(open) => {
          if (!open) return;
          const rec = editing;
          form.setFieldsValue({
            name: rec?.name ?? "",
            phone: rec?.phone ?? undefined,
            social_link: rec?.social_link ?? undefined,
            note: rec?.note ?? undefined,
          });
        }}
      >
        <Form form={form} layout="vertical" preserve={false}>
          <Form.Item
            label="Tên khách hàng"
            name="name"
            rules={[{ required: true, message: "Nhập tên khách hàng" }]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            label="Số điện thoại"
            name="phone"
            rules={[{ max: 20, message: "Tối đa 20 ký tự" }]}
          >
            <Input />
          </Form.Item>
          <Form.Item
            label="Link MXH"
            name="social_link"
            rules={[{ max: 255, message: "Tối đa 255 ký tự" }]}
          >
            <Input placeholder="https://facebook.com/..." />
          </Form.Item>
          <Form.Item label="Ghi chú" name="note">
            <Input.TextArea rows={3} />
          </Form.Item>
        </Form>
      </Modal>
    </MainLayout>
  );
}
