import MainLayout from "@/components/layout/MainLayout";
import { useState, useEffect } from "react";
import { Button, Space, Modal, Form, Input, InputNumber, Switch, Popconfirm, message, Tag } from "antd";
import { EditOutlined, DeleteOutlined, PlusOutlined } from "@ant-design/icons";
import api from "../../../../axiosConfig";
import PageTable from "@/components/shared/PageTable";

interface ExpenseCategory {
  id: number;
  name: string;
  code?: string | null;
  is_active: boolean;
  sort_order: number;
}

export default function ExpenseCategoriesPage() {
  const [form] = Form.useForm();
  const [data, setData] = useState<ExpenseCategory[]>([]);
  const [search, setSearch] = useState("");
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editing, setEditing] = useState<ExpenseCategory | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingModal, setLoadingModal] = useState(false);

  const fetchData = async () => {
    try {
      setLoading(true);
      const res = await api.get("/expense-categories");
      setData(Array.isArray(res.data) ? res.data : []);
    } catch (e) {
      message.error("Lỗi khi tải danh sách loại chi phí");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const filtered = data.filter(
    (r) =>
      r.name.toLowerCase().includes(search.toLowerCase()) ||
      (r.code ?? "").toLowerCase().includes(search.toLowerCase()),
  );

  const openAdd = () => {
    setEditing(null);
    form.resetFields();
    form.setFieldsValue({ is_active: true, sort_order: 0 });
    setIsModalOpen(true);
  };

  const openEdit = (rec: ExpenseCategory) => {
    setEditing(rec);
    form.setFieldsValue(rec);
    setIsModalOpen(true);
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoadingModal(true);
      if (editing) {
        const res = await api.put(`/admin/expense-categories/${editing.id}`, values);
        setData((prev) => prev.map((r) => (r.id === editing.id ? { ...r, ...res.data } : r)));
        message.success("Cập nhật thành công");
      } else {
        const res = await api.post("/admin/expense-categories", values);
        setData((prev) => [...prev, res.data]);
        message.success("Thêm loại chi phí thành công");
      }
      setIsModalOpen(false);
    } catch (e: any) {
      if (e?.errorFields) return; // form validation
      const msg = e?.response?.data?.message || e?.response?.data?.errors
        ? (typeof e.response.data.errors === "object"
            ? Object.values(e.response.data.errors).flat().join("\n")
            : e.response.data.message)
        : "Lỗi khi lưu";
      message.error(msg);
    } finally {
      setLoadingModal(false);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      setLoadingModal(true);
      await api.delete(`/admin/expense-categories/${id}`);
      setData((prev) => prev.filter((r) => r.id !== id));
      message.success("Xoá thành công");
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Lỗi khi xoá");
    } finally {
      setLoadingModal(false);
    }
  };

  const columns = [
    { title: "ID", dataIndex: "id", width: 60 },
    { title: "Tên loại", dataIndex: "name" },
    { title: "Code", dataIndex: "code", render: (c: string | null) => c || "-" },
    { title: "Thứ tự", dataIndex: "sort_order", width: 90 },
    {
      title: "Trạng thái",
      dataIndex: "is_active",
      width: 110,
      render: (v: boolean) =>
        v ? <Tag color="green">Đang dùng</Tag> : <Tag color="default">Tắt</Tag>,
    },
    {
      title: "Hành động",
      width: 160,
      render: (_: any, record: ExpenseCategory) => (
        <Space.Compact>
          <Button icon={<EditOutlined />} onClick={() => openEdit(record)} type="primary" size="small">
            Sửa
          </Button>
          <Popconfirm title="Xoá loại này?" onConfirm={() => handleDelete(record.id)}>
            <Button danger icon={<DeleteOutlined />} type="primary" size="small">
              Xoá
            </Button>
          </Popconfirm>
        </Space.Compact>
      ),
    },
  ];

  return (
    <MainLayout>
      <PageTable<ExpenseCategory>
        title="Quản lý loại chi phí"
        data={filtered}
        columns={columns}
        pageSize={14}
        rowKey="id"
        onSearch={setSearch}
        scrollX="max-content"
        loading={loading}
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={openAdd}>
            Thêm loại
          </Button>
        }
      />
      <Modal
        title={editing ? "Sửa loại chi phí" : "Thêm loại chi phí"}
        open={isModalOpen}
        onOk={handleSave}
        onCancel={() => setIsModalOpen(false)}
        loading={loadingModal}
        maskClosable={false}
      >
        <Form form={form} layout="vertical">
          <Form.Item
            label="Tên loại"
            name="name"
            rules={[{ required: true, message: "Nhập tên loại" }, { max: 100 }]}
          >
            <Input placeholder="VD: Marketing, Lương nhân viên..." />
          </Form.Item>
          <Form.Item
            label="Code (slug, tuỳ chọn)"
            name="code"
            rules={[{ max: 50 }]}
            tooltip="Mã định danh không dấu, dùng để map dữ liệu. Không có cũng được."
          >
            <Input placeholder="marketing" />
          </Form.Item>
          <Form.Item label="Thứ tự hiển thị" name="sort_order">
            <InputNumber min={0} style={{ width: "100%" }} />
          </Form.Item>
          <Form.Item label="Đang dùng" name="is_active" valuePropName="checked">
            <Switch />
          </Form.Item>
        </Form>
      </Modal>
    </MainLayout>
  );
}
