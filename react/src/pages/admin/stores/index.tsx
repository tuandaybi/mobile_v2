// pages/admin/stores/index.tsx
import MainLayout from "@/components/layout/MainLayout";
import {
  Button,
  Space,
  Modal,
  Form,
  Input,
  Popconfirm,
  Select,
  message,
  Tag,
  Tooltip,
} from "antd";
import {
  EditOutlined,
  DeleteOutlined,
  PlusOutlined,
} from "@ant-design/icons";
import PageTable from "@/components/shared/PageTable";
import { useState, useEffect } from "react";
import api from "../../../../axiosConfig";

interface User {
  id: number;
  name: string;
  email: string;
}

interface Store {
  id: number;
  name: string;
  email: string;
  phone: string;
  address: string;
  users?: User[];
  users_count?: number;
}

export default function StoresPage() {
  const [form] = Form.useForm();
  const [data, setData] = useState<Store[]>([]);
  const [search, setSearch] = useState("");
  const [allUsers, setAllUsers] = useState<User[]>([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingStore, setEditingStore] = useState<Store | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingModal, setLoadingModal] = useState(false);

  // ===== API =====
  const fetchStores = async () => {
    try {
      setLoading(true);
      const res = await api.get("/admin/stores"); // {stores, users}
      const stores: Store[] = Array.isArray(res.data?.stores) ? res.data.stores : [];
      const users: User[] = Array.isArray(res.data?.users) ? res.data.users : [];
      setData(stores);
      setAllUsers(users);
    } catch (e) {
      message.error("Lỗi khi tải danh sách cửa hàng/nhân viên");
      console.error("Error fetching stores:", e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchStores();
  }, []);

  // ===== Helpers =====
  const filteredData = (data ?? []).filter((store) => {
    const q = search.toLowerCase();
    return (
      store.name.toLowerCase().includes(q) ||
      store.email.toLowerCase().includes(q) ||
      store.phone.toLowerCase().includes(q) ||
      store.address.toLowerCase().includes(q) ||
      (store.users ?? []).some(
        (u) =>
          (u.name ?? "").toLowerCase().includes(q) ||
          (u.email ?? "").toLowerCase().includes(q)
      )
    );
  });

  const openAddModal = () => {
    setEditingStore(null);
    setIsModalOpen(true);
  };

  const openEditModal = (record: Store) => {
    setEditingStore(record);
    setIsModalOpen(true); // setFieldsValue sẽ chạy trong afterOpenChange khi modal open
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoadingModal(true);

      const payload = {
        name: values.name,
        email: values.email,
        phone: values.phone,
        address: values.address,
        user_ids: values.user_ids ?? [],
      };

      if (editingStore) {
        await api.put(`/admin/stores/${editingStore.id}`, payload);
        message.success("Cập nhật cửa hàng thành công");
      } else {
        await api.post(`/admin/stores`, payload);
        message.success("Thêm cửa hàng thành công");
      }

      setIsModalOpen(false);
      setEditingStore(null);
      form.resetFields();
      await fetchStores(); // refresh dữ liệu từ server cho chuẩn
    } catch {
      message.error("Lỗi khi lưu cửa hàng");
    } finally {
      setLoadingModal(false);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await api.delete(`/admin/stores/${id}`);
      message.success("Xóa cửa hàng thành công");
      await fetchStores();
    } catch {
      message.error("Lỗi khi xóa cửa hàng");
    }
  };

  const columns = [
    { title: "ID", dataIndex: "id" },
    { title: "Tên cửa hàng", dataIndex: "name" },
    { title: "Email", dataIndex: "email" },
    { title: "Số điện thoại", dataIndex: "phone" },
    { title: "Địa chỉ", dataIndex: "address" },
    {
      title: "Nhân viên",
      key: "users",
      render: (_: any, record: Store) => {
        const users = record.users ?? [];
        if (!users.length) return <Tag>0 nhân viên</Tag>;
        return (
          <Space size={[4, 8]} wrap>
            <Tag color="blue">{record.users_count ?? users.length} NV</Tag>
            <Tooltip
              title={
                <div style={{ maxWidth: 360 }}>
                  {users.map((u) => (
                    <div key={u.id}>• {u.name} <small>({u.email})</small></div>
                  ))}
                </div>
              }
            >
              <Button size="small" type="link">Xem chi tiết</Button>
            </Tooltip>
          </Space>
        );
      },
    },
    {
      title: "Hành động",
      render: (_: any, record: Store) => (
        <Space.Compact>
          <Button
            icon={<EditOutlined />}
            onClick={() => openEditModal(record)}
            type="primary"
            size="small"
          >
            Sửa
          </Button>
          <Popconfirm title="Xóa cửa hàng?" onConfirm={() => handleDelete(record.id)}>
            <Button danger icon={<DeleteOutlined />} type="primary" size="small">
              Xóa
            </Button>
          </Popconfirm>
        </Space.Compact>
      ),
    },
  ];

  return (
    <MainLayout>
      <div>
        <PageTable<Store>
          title="Quản lý cửa hàng"
          data={filteredData}
          columns={columns}
          pageSize={14}
          rowKey="id"
          onSearch={setSearch}
          scrollX="max-content"
          loading={loading}
          extra={
            <Button type="primary" icon={<PlusOutlined />} onClick={openAddModal}>
              Thêm
            </Button>
          }
        />

        <Modal
          title={editingStore ? "Sửa thông tin cửa hàng" : "Thêm mới cửa hàng"}
          open={isModalOpen}
          onOk={handleSave}
          onCancel={() => {
            setIsModalOpen(false);
            setEditingStore(null);
            form.resetFields();
          }}
          confirmLoading={loadingModal}
          destroyOnHidden
          afterOpenChange={(open) => {
            if (open) {
              if (editingStore) {
                form.setFieldsValue({
                  name: editingStore.name,
                  email: editingStore.email,
                  phone: editingStore.phone,
                  address: editingStore.address,
                  user_ids: (editingStore.users ?? []).map((u) => u.id),
                });
              } else {
                form.resetFields();
              }
            }
          }}
        >
          <Form
            form={form}
            layout="vertical"
            preserve={false}
            initialValues={{ user_ids: [] }}
          >
            <Form.Item
              label="Tên cửa hàng"
              name="name"
              rules={[{ required: true, message: "Nhập tên cửa hàng" }]}
            >
              <Input />
            </Form.Item>

            <Form.Item
              label="Địa chỉ email"
              name="email"
              rules={[
                { required: true, message: "Nhập email" },
                { type: "email", message: "Email không hợp lệ" },
              ]}
            >
              <Input />
            </Form.Item>

            <Form.Item
              label="Số điện thoại"
              name="phone"
              rules={[{ required: true, message: "Nhập số điện thoại" }]}
            >
              <Input />
            </Form.Item>

            <Form.Item
              label="Địa chỉ"
              name="address"
              rules={[{ required: true, message: "Nhập địa chỉ" }]}
            >
              <Input.TextArea rows={3} />
            </Form.Item>

            <Form.Item label="Danh sách nhân viên" name="user_ids">
              <Select
                mode="multiple"
                allowClear
                placeholder="Chọn nhân viên"
                options={(allUsers ?? []).map((u) => ({
                  label: `${u.name} — ${u.email}`,
                  value: u.id,
                }))}
              />
            </Form.Item>
          </Form>
        </Modal>
      </div>
    </MainLayout>
  );
}
