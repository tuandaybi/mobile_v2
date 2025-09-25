import MainLayout from "../../../components/layout/MainLayout";
import { useState, useEffect } from 'react';
import api from '../../../../axiosConfig';
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
  Typography,
  List,
  Tooltip,
} from "antd";
import {
  EditOutlined,
  DeleteOutlined,
  PlusOutlined,
  CheckOutlined,
  StopOutlined,
  KeyOutlined,
  CopyOutlined,
  ReloadOutlined,
} from "@ant-design/icons";

const { Text } = Typography;

interface User {
  id: number;
  name: string;
  email: string;
  role: string;
  permissions: string[];
  is_active: boolean;
  stores?: { id: number; name: string }[];
}

type TokenItem = {
  id: number;
  name: string;
  created_at?: string | null;
  last_used_at?: string | null;
};

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

  // ====== Tokens state ======
  const [tokenModalOpen, setTokenModalOpen] = useState(false);
  const [tokenUser, setTokenUser] = useState<User | null>(null);
  const [tokens, setTokens] = useState<TokenItem[]>([]);
  const [tokenLoading, setTokenLoading] = useState(false);
  const [createTokenLoading, setCreateTokenLoading] = useState(false);
  const [newTokenName, setNewTokenName] = useState("");
  const [plainTextToken, setPlainTextToken] = useState<string | null>(null); // hiển thị 1 lần sau khi tạo

  const normalizeUser = (u: any) => ({
    id: u.id,
    name: u.name,
    email: u.email,
    role: u.role ?? u.roles?.[0]?.name ?? "",
    permissions: Array.isArray(u.permissions)
      ? u.permissions.map((p: any) => (typeof p === 'string' ? p : p.name))
      : [],
    is_active: Boolean(u.is_active),
    stores: u.stores ?? u.userStores ?? u.branches ?? undefined,
  });

  useEffect(() => {
    const fetchData = async () => {
      setLoading(true);
      try {
        const { data } = await api.get("admin/user");
        setAllUsers(data.users?.map((u: any) => normalizeUser(u)) ?? data.users ?? []);
        setAllRoles(data.roles ?? []);
        setAllPermissions(data.permissions ?? []);
      } catch (err) {
        message.error("Lỗi khi lấy dữ liệu từ server.");
      } finally {
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
    form.setFieldsValue({
      name: record.name,
      email: record.email,
      role: record.role,
      permissions: record.permissions ?? [],
    });
    setIsModalOpen(true);
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setIsModalLoading(true);
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
    } catch (error: any) {
      console.error("Lỗi khi lưu user:", error);
      if (error?.response) {
        const errMsg = error.response.data?.message
          || error.response.data?.error
          || 'Đã xảy ra lỗi khi lưu user.';
        message.error(errMsg);
      } else if (error?.request) {
        message.error('Không nhận được phản hồi từ server.');
      } else {
        message.error('Có lỗi xảy ra khi gửi yêu cầu.');
      }
    } finally {
      setIsModalLoading(false);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await api.delete(`admin/user/${id}`);
      setAllUsers(prev => prev.filter(user => user.id !== id));
      message.success("Xóa user thành công");
    } catch (error: any) {
      console.error("Lỗi khi xóa user:", error);
      message.error(error?.response?.data?.message || error?.message || "Đã xảy ra lỗi khi xóa user.");
    }
  };

  const handleSearch = (value: string) => setSearch(value);

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
    } finally {
      setLoadingId(null);
    }
  };

  // ====== Tokens handlers ======
  const openTokenModal = async (user: User) => {
    setTokenUser(user);
    setPlainTextToken(null);
    setNewTokenName("");
    setTokens([]);
    setTokenModalOpen(true);
    await fetchTokens(user.id);
  };

  const fetchTokens = async (userId: number) => {
    setTokenLoading(true);
    try {
      const { data } = await api.get(`/admin/users/${userId}/tokens`);
      setTokens(Array.isArray(data.tokens) ? data.tokens : []);
    } catch (e) {
      message.error("Không tải được danh sách token.");
    } finally {
      setTokenLoading(false);
    }
  };

  const createToken = async () => {
    if (!tokenUser) return;
    if (!newTokenName?.trim()) {
      message.warning("Nhập tên token trước khi tạo.");
      return;
    }
    setCreateTokenLoading(true);
    try {
      const { data } = await api.post(`/admin/users/${tokenUser.id}/tokens`, {
        token_name: newTokenName.trim(),
      });
      // Hiển thị plain text token (chỉ hiển thị 1 lần)
      if (data?.plain_text_token) {
        setPlainTextToken(data.plain_text_token);
        message.success("Tạo token thành công. Hãy copy ngay, sẽ không hiện lại lần nữa!");
      } else {
        message.success("Tạo token thành công.");
      }
      // refresh list
      await fetchTokens(tokenUser.id);
      setNewTokenName("");
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Tạo token thất bại.");
    } finally {
      setCreateTokenLoading(false);
    }
  };

  const revokeToken = async (tokenId: number) => {
    if (!tokenUser) return;
    try {
      await api.delete(`/admin/users/${tokenUser.id}/tokens/${tokenId}`);
      message.success("Đã thu hồi token.");
      await fetchTokens(tokenUser.id);
    } catch (e: any) {
      message.error(e?.response?.data?.message || "Thu hồi token thất bại.");
    }
  };

  const copyToClipboard = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      message.success("Đã copy vào clipboard");
    } catch {
      message.warning("Trình duyệt không cho phép copy tự động.");
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
      dataIndex: "role",
      render: (_: any, record: any) =>
        Array.isArray(record.roles)
          ? record.roles.map((r: any) => <Tag key={r.name}>{r.name}</Tag>)
          : <Tag>{record.role}</Tag>
    },
    {
      title: "Cửa hàng",
      dataIndex: "stores",
      render: (stores: User['stores']) =>
        Array.isArray(stores)
          ? stores.map((s: any) => <Tag key={s.id}>{s.name}</Tag>)
          : null
    },
    {
      title: "Trạng thái",
      dataIndex: "is_active",
      render: (is_active: User['is_active']) =>
        is_active ? <Tag color="green">Đã duyệt</Tag> : <Tag color="orange">Chưa duyệt</Tag>,
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

          <Popconfirm title="Xóa user này?" onConfirm={() => handleDelete(record.id)}>
            <Button danger icon={<DeleteOutlined />} size="small" type="primary">
              Xóa
            </Button>
          </Popconfirm>

          <Button
            type={record.is_active ? "default" : "primary"}
            icon={record.is_active ? <StopOutlined /> : <CheckOutlined />}
            size="small"
            onClick={() => toggleActive(record.id)}
            loading={loadingId === record.id}
            disabled={loadingId !== null && loadingId !== record.id}
          >
            {record.is_active ? "Hủy duyệt" : "Duyệt"}
          </Button>

          {/* ===== NEW: Tokens button ===== */}
          <Button
            icon={<KeyOutlined />}
            size="small"
            onClick={() => openTokenModal(record)}
          >
            Tokens
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
          onSearch={handleSearch}
          scrollX="max-content"
          loading={loading}
          extra={<Button type="primary" icon={<PlusOutlined />} onClick={openAddModal}>Thêm</Button>}
        />

        {/* Modal tạo/sửa user */}
        <Modal
          title={editingUser ? "Sửa User" : "Thêm User"}
          open={isModalOpen}
          onOk={handleSave}
          onCancel={() => setIsModalOpen(false)}
          confirmLoading={isModalLoading}
          destroyOnClose
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

        {/* ===== Modal quản lý Tokens ===== */}
        <Modal
          title={tokenUser ? `Tokens của ${tokenUser.name}` : "Tokens"}
          open={tokenModalOpen}
          onCancel={() => setTokenModalOpen(false)}
          footer={null}
          destroyOnClose
          width={720}
        >
          <Space style={{ width: '100%', marginBottom: 12 }} wrap>
            <Input
              placeholder="Tên token (vd: API cho T3)"
              value={newTokenName}
              onChange={(e) => setNewTokenName(e.target.value)}
              onPressEnter={createToken}
              style={{ maxWidth: 320 }}
            />
            <Button
              type="primary"
              onClick={createToken}
              loading={createTokenLoading}
              icon={<KeyOutlined />}
            >
              Tạo token
            </Button>
            <Tooltip title="Tải lại danh sách">
              <Button
                onClick={() => tokenUser && fetchTokens(tokenUser.id)}
                icon={<ReloadOutlined />}
              />
            </Tooltip>
          </Space>

          {/* Hiển thị plain text token sau khi tạo (1 lần) */}
          {plainTextToken && (
            <div
              style={{
                padding: 12,
                border: '1px dashed var(--ant-color-border, #d9d9d9)',
                borderRadius: 6,
                marginBottom: 12,
              }}
            >
              <Text strong>Token mới tạo (sao chép ngay, sẽ không hiện lại):</Text>
              <div style={{ display: 'flex', gap: 8, marginTop: 8 }}>
                <Input value={plainTextToken} readOnly />
                <Button
                  icon={<CopyOutlined />}
                  onClick={() => copyToClipboard(plainTextToken)}
                >
                  Copy
                </Button>
              </div>
            </div>
          )}

          <List
            loading={tokenLoading}
            dataSource={tokens}
            locale={{ emptyText: "Chưa có token" }}
            renderItem={(t) => (
              <List.Item
                actions={[
                  <Popconfirm
                    key="revoke"
                    title="Thu hồi token này?"
                    onConfirm={() => revokeToken(t.id)}
                  >
                    <Button danger size="small">Revoke</Button>
                  </Popconfirm>
                ]}
              >
                <List.Item.Meta
                  title={<Text>{t.name}</Text>}
                  description={
                    <Space direction="vertical" size={0}>
                      <Text type="secondary">ID: {t.id}</Text>
                      {t.created_at && <Text type="secondary">Tạo: {t.created_at}</Text>}
                      {t.last_used_at
                        ? <Text type="secondary">Dùng gần nhất: {t.last_used_at}</Text>
                        : <Text type="secondary">Chưa sử dụng</Text>}
                    </Space>
                  }
                />
              </List.Item>
            )}
          />
        </Modal>
      </div>
    </MainLayout>
  );
}
