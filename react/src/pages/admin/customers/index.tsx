import MainLayout from "../../../components/layout/MainLayout";
import { useState, useEffect } from "react";
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

type Customer = {
  id: number;
  store_id: number;
  name: string;
  phone?: string | null;
  social_link?: string | null;
  note?: string | null;
  created_at?: string | null; // ISO string
};

export default function CustomersPage() {
  const [form] = Form.useForm<Customer>();
  const [search, setSearch] = useState("");
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editing, setEditing] = useState<Customer | null>(null);
  const [rows, setRows] = useState<Customer[]>([]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);

  const fmtDate = (d?: string | null) => {
    if (!d) return "—";
    const dt = new Date(d);
    if (Number.isNaN(dt.getTime())) return "—";
    return dt.toLocaleString("vi-VN");
  };

  const normalize = (x: any): Customer => ({
    id: x.id,
    store_id: x.store_id,
    name: x.name,
    phone: x.phone ?? null,
    social_link: x.social_link ?? null,
    note: x.note ?? null,
    created_at: x.created_at ?? null,
  });

  const fetchCustomers = async (term = "") => {
    setLoading(true);
    try {
      // index() của bạn trả kiểu Resource paginate => data.data
      const res = await api.get("admin/customers", {
        params: {
          q: term || undefined,
          perPage: 200, // client paginate bằng PageTable
          sortBy: "id",
          sortDir: "desc",
        },
      });
      const list: any[] = Array.isArray(res.data?.data) ? res.data.data : (Array.isArray(res.data) ? res.data : []);
      setRows(list.map(normalize));
    } catch (e: any) {
      console.error(e);
      message.error(
        e?.response?.data?.message || "Lỗi khi tải danh sách khách hàng"
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchCustomers();
  }, []);

  const filtered = rows.filter(
    (c) =>
      c.name.toLowerCase().includes(search.toLowerCase()) ||
      (c.phone || "").toLowerCase().includes(search.toLowerCase())
  );

  const openAdd = () => {
    setEditing(null);
    setIsModalOpen(true);
    setTimeout(() => {
      form.resetFields();
      form.setFieldsValue({
        name: '',
        phone: undefined,
        social_link: undefined,
        note: undefined,
      });
    }, 0);
  };

  const openEdit = (rec: Customer) => {
    setEditing(rec);
    setIsModalOpen(true);
    // chờ modal render xong tick kế tiếp rồi set form
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
        const res = await api.put(`admin/customers/${editing.id}`, values);
        const cu = normalize(res.data?.data ?? res.data);
        setRows((prev) => prev.map((x) => (x.id === cu.id ? cu : x)));
        message.success("Cập nhật khách hàng thành công");
      } else {
        const res = await api.post("admin/customers", values);
        const cu = normalize(res.data?.data ?? res.data);
        setRows((prev) => [cu, ...prev]);
        message.success("Thêm khách hàng thành công");
      }

      setIsModalOpen(false);
    } catch (e: any) {
      // gom lỗi validation 422 nếu có
      const data = e?.response?.data;
      let firstError: string | undefined;
      if (
        data?.errors &&
        typeof data.errors === "object" &&
        !Array.isArray(data.errors)
      ) {
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
      setRows((prev) => prev.filter((x) => x.id !== id));
      message.success("Đã xoá khách hàng");
    } catch (e: any) {
      message.error(
        e?.response?.data?.message || "Không thể xoá khách hàng"
      );
    }
  };

  const handleSearch = (v: string) => {
    setSearch(v);
    // nếu muốn search server-side theo gõ:
    // fetchCustomers(v);
  };

  const columns = [
    { title: "Tên KH", dataIndex: "name", key: "name" },
    {
      title: "SĐT",
      dataIndex: "phone",
      key: "phone",
      render: (v: string | null) => v || "—",
    },
    {
      title: "Mạng xã hội",
      dataIndex: "social_link",
      key: "social_link",
      render: (v: string | null) =>
        v ? (
          /^https?:\/\//i.test(v) ? (
            <a href={v} target="_blank" rel="noreferrer">
              {v}
            </a>
          ) : (
            <Tag>{v}</Tag>
          )
        ) : (
          "—"
        ),
    },
    {
      title: "Ghi chú",
      dataIndex: "note",
      key: "note",
      ellipsis: true,
      render: (v: string | null) => v || "—",
    },
    {
      title: "Tạo lúc",
      dataIndex: "created_at",
      key: "created_at",
      render: (v: string | null) => fmtDate(v),
      width: 170,
    },
    {
      title: "Hành động",
      key: "actions",
      render: (_: any, rec: Customer) => (
        <Space.Compact>
          <Button
            icon={<EditOutlined />}
            size="small"
            type="primary"
            onClick={() => openEdit(rec)}
          >
            Sửa
          </Button>
          <Popconfirm
            title= "Xoá khách hàng này?"
            onConfirm={() => handleDelete(rec.id)}
          >
            <Button danger icon={<DeleteOutlined />} size="small" type="primary">
              Xoá
            </Button>
          </Popconfirm>
        </Space.Compact>
      ),
    },
  ];

  return (
    <MainLayout>
      <PageTable<Customer>
        title="Khách hàng"
        data={filtered}
        columns={columns}
        pageSize={14}
        rowKey="id"
        onSearch={handleSearch}
        scrollX="max-content"
        loading={loading || saving}
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={openAdd}>
            Thêm
          </Button>
        }
      />

      <Modal
        open={isModalOpen}
        title={editing ? "Sửa khách hàng" : "Thêm khách hàng"}
        onOk={handleSave}
        onCancel={() => setIsModalOpen(false)}
        confirmLoading={saving}
        destroyOnHidden
        afterOpenChange={(open) => {
          if (!open) return;
          const rec = editing;
          form.setFieldsValue({
            name: rec?.name ?? '',
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
            rules={[
              { max: 20, message: "Tối đa 20 ký tự" },
              // optional: pattern VN
              // { pattern: /^(0|\+84)\d{8,11}$/, message: "SĐT không hợp lệ" },
            ]}
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
