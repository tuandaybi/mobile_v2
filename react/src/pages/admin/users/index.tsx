import MainLayout from "../../../components/layout/MainLayout";
import { useState, useEffect } from 'react';
import api from '../../../../axiosConfig'; // Import axios config
import PageTable from "@/components/shared/PageTable";

import {
  Button,
  Space,
  Modal,
  Form,
  Input,
  Select,
  Popconfirm,
  message,
  Tag,
} from "antd";
import {
  EditOutlined,
  DeleteOutlined,
  PlusOutlined,
  CheckOutlined,
  StopOutlined,
} from "@ant-design/icons";


interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  permissions: string[];
  is_active: boolean;
}

export default function UsersPage() {
  const [form] = Form.useForm();
  const [search, setSearch] = useState("");
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);
  const [allUsers, setAllUsers] = useState<User[]>([]); 
  const [allRoles, setAllRoles] = useState<string[]>([]); 
  const [allPermissions, setAllPermissions] = useState<string[]>([]);
  const [loadingId, setLoadingId] = useState<number | null>(null);
  const [isModalLoading, setIsModalLoading] = useState(false);
  const [loading, setLoading] = useState(false);

  const normalizeUser = (u: any) => ({
    id: u.id,
    name: u.name,
    email: u.email,
    role: u.role ?? u.roles?.[0]?.name ?? "", // ép về string
    permissions: Array.isArray(u.permissions)
      ? u.permissions.map((p: any) => (typeof p === 'string' ? p : p.name))
      : [],
    is_active: Boolean(u.is_active),
  });

  useEffect(() => {
    const fetchData = async () => {
    setLoading(true);
      try {
        // Lấy danh sách users
        const { data } = await api.get("admin/users");
        setAllUsers(data.users);
        setAllRoles(data.roles);
        setAllPermissions(data.permissions);
      } catch (err) {
        message.error("Lỗi khi lấy dữ liệu từ server.");
      }finally {
        setLoading(false);
      }
    };

    fetchData();
  }, []); 

  const filteredData = allUsers.filter(
    (user) =>
      user.name.toLowerCase().includes(search.toLowerCase()) ||
      user.email.toLowerCase().includes(search.toLowerCase())
  );

  const openAddModal = () => {
    setEditingUser(null);
    form.resetFields();
    setIsModalOpen(true);
  };

  const openEditModal = (record: User) => {
    setEditingUser(record);
    form.setFieldsValue(record);
    setIsModalOpen(true);
  };

  const handleSave = async () => {
    try {
        const values = await form.validateFields();
        setIsModalLoading(true); // Bắt đầu loading
        
        if (editingUser) {
            const response = await api.put(`admin/user/${editingUser.id}`, values);
            const updatedUser = normalizeUser(response.data.user);
            setAllUsers(prev =>
              prev.map(u => String(u.id) === String(updatedUser.id) ? { ...u, ...updatedUser } : u)
            );
            message.success("Cập nhật user thành công");
        } else {
            const response = await api.post('admin/user', values);
            const newUser = normalizeUser(response.data.user);
            setAllUsers(prev => [...prev, newUser]);
            message.success("Thêm user thành công");
        }
        
        setIsModalOpen(false);
    }catch (error: any) {
    console.error("Lỗi khi lưu user:", error);

    if (error.response) {
        // Lỗi từ Laravel (status 4xx, 5xx)
        const errMsg = error.response.data?.message 
            || error.response.data?.error
            || 'Đã xảy ra lỗi khi lưu user.';
        message.error(errMsg);
    } else if (error.request) {
        // Request đã gửi nhưng không có phản hồi
        message.error('Không nhận được phản hồi từ server.');
    } else {
        // Lỗi khi chuẩn bị request
        message.error('Có lỗi xảy ra khi gửi yêu cầu.');
    }
    } finally {
        setIsModalLoading(false); // Kết thúc loading dù thành công hay thất bại
    }
};

  const handleDelete = async(id: number) => {
    try{
      
      await api.delete(`admin/user/${id}`);
      setAllUsers((prev) => prev.filter((user) => user.id !== id));
      message.success("Xóa user thành công");
    }catch (error: unknown) {
      if (error instanceof Error) {
            message.error(error.message || "Đã xảy ra lỗi khi xóa user.");
        } else {
            message.error("Đã xảy ra lỗi khi lưu user.");
        }
        console.error("Lỗi khi xóa user:", error);
    }

  };

  const handleSearch = (value: string) => {
      setSearch(value);
  };

  const toggleActive = async (id: number) => {
    try {
      setLoadingId(id);
      const response = await api.put(`/admin/user/${id}/active`);
      const updatedUser = normalizeUser(response.data.user);
      setAllUsers(prev =>
        prev.map(u => String(u.id) === String(id) ? { ...u, ...updatedUser } : u)
      );
      message.success("Cập nhật trạng thái thành công");
    } catch (error) {
      message.error("Lỗi khi cập nhật trạng thái.");
      console.error(error);
    }finally {
      setLoadingId(null); // Kết thúc loading
    }
  };

  const columns = [
    {
      title: "Họ tên",
      dataIndex: "name",
      sorter: (a: User, b: User) => a.name.localeCompare(b.name),
    },
    { title: "Email", dataIndex: "email" },
    {
      title: "Role",
      dataIndex: "role", // hoặc render từ roles nếu đổi shape
      render: (_: any, record: any) => (
        Array.isArray(record.roles)
          ? record.roles.map((r: any) => <Tag key={r.name}>{r.name}</Tag>)
          : <Tag>{record.role}</Tag>
      )
    },
    {
      title: "Trạng thái",
      dataIndex: "is_active",
      render: (is_active: User['is_active']) =>
        is_active ? (
          <Tag color="green">Đã duyệt</Tag>
        ) : (
          <Tag color="orange">Chưa duyệt</Tag>
        ),
    },
    {
      title: "Hành động",
      render: (_: any, record: User) => (
        <Space.Compact>
          <Button
            icon={<EditOutlined />}
            onClick={() => openEditModal(record)}
            size="small"
            type="primary"
          >
            Sửa
          </Button>
          <Popconfirm
            title="Xóa user này?"
            onConfirm={() => handleDelete(record.id)}
          >
            <Button danger icon={<DeleteOutlined />} 
                    size="small"
                    type="primary">
              Xóa
            </Button>
          </Popconfirm>
          <Button
            type={record.is_active ? "default" : "primary"}
            icon={
              record.is_active ? <StopOutlined /> : <CheckOutlined />
            }
            size="small"
            onClick={() => toggleActive(record.id)}
            loading={loadingId === record.id} // Thêm thuộc tính loading
            disabled={loadingId !== null && loadingId !== record.id} 
          >
            {record.is_active ? "Hủy duyệt" : "Duyệt"}
          </Button>
        </Space.Compact>
      ),
    },
  ];

  return (
    <MainLayout>
      <div>
        <PageTable<User>
          title="Quản lý người dùng"
          data={filteredData}
          columns={columns}
          pageSize={14}
          rowKey="id"
          onSearch={handleSearch}      // bỏ prop này nếu không cần search
          scrollX="max-content"
          loading={loading}
          extra={<Button type="primary" icon={<PlusOutlined />} onClick={openAddModal}>Thêm</Button>}
        />

        <Modal
          title={editingUser ? "Sửa User" : "Thêm User"}
          open={isModalOpen}
          onOk={handleSave}
          onCancel={() => setIsModalOpen(false)}
          confirmLoading={isModalLoading} // Thêm prop này
        >
          <Form form={form} layout="vertical">
            <Form.Item
              label="Họ tên"
              name="name"
              rules={[{ required: true, message: "Nhập họ tên" }]}
            >
              <Input />
            </Form.Item>
            <Form.Item
              label="Email"
              name="email"
              rules={[
                { required: true, message: "Nhập email" },
                { type: "email", message: "Email không hợp lệ" },
              ]}
            >
              <Input />
            </Form.Item>
            <Form.Item
              label="Role"
              name="role"
              rules={[{ required: true, message: "Chọn role" }]}
            >
              <Select>
                {allRoles.map((role) => (
                  <Select.Option key={role} value={role}>
                    {role}
                  </Select.Option>
                ))}
              </Select>
            </Form.Item>
            <Form.Item label="Permissions" name="permissions">
              <Select mode="multiple" allowClear>
                {allPermissions.map((perm) => (
                  <Select.Option key={perm} value={perm}>
                    {perm}
                  </Select.Option>
                ))}
              </Select>
            </Form.Item>
          </Form>
        </Modal>
      </div>
    </MainLayout>
  );
}
