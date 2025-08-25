import MainLayout from "@/components/layout/MainLayout";
import { useState, useEffect } from "react";
import { Button, Space, Modal, Form, Input, Popconfirm, message } from "antd";
import { EditOutlined, DeleteOutlined, PlusOutlined } from "@ant-design/icons";
import api from '../../../../axiosConfig';
import PageTable from "@/components/shared/PageTable";

interface Color {
  id: number;
  en_name: string;
  vi_name: string;
}

export default function ColorsPage() {
  const [form] = Form.useForm();
  const [data, setData] = useState<Color[]>([]);
  const [search, setSearch] = useState("");
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingColor, setEditingColor] = useState<Color | null>(null);
  const [loading, setLoading] = useState(false);
  const [loadingModal, setLoadingModal] = useState(false);

  // Fetch colors from API
  const fetchColors = async () => {
    try {
      setLoading(true);
      const response = await api.get('/admin/colors');
      setData(response.data);
    } catch (error) {
      message.error('Lỗi khi tải danh sách màu');
    } finally {
      setLoading(false);
    }
  };

  // Initial fetch
  useEffect(() => {
    fetchColors();
  }, []);

  const filteredData = data.filter(
    (color) =>
      color.en_name.toLowerCase().includes(search.toLowerCase()) ||
      color.vi_name.toLowerCase().includes(search.toLowerCase())
  );

  const openAddModal = () => {
    setEditingColor(null);
    form.resetFields();
    setIsModalOpen(true);
  };

  const openEditModal = (record: Color) => {
    setEditingColor(record);
    form.setFieldsValue(record);
    setIsModalOpen(true);
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      setLoadingModal(true)
      if (editingColor) {
        // Update existing color
        await api.put(`/admin/colors/${editingColor.id}`, values);
        setData((prev) =>
          prev.map((color) =>
            color.id === editingColor.id ? { ...color, ...values } : color
          )
        );
        message.success("Cập nhật màu thành công");
      } else {
        // Create new color
        const response = await api.post('/admin/colors', values);
        setData((prev) => [...prev, response.data]);
        message.success("Thêm màu thành công");
      }
      setIsModalOpen(false);
    } catch (error) {
      message.error("Lỗi khi lưu màu");
    }finally{
      setLoadingModal(false)
    }
  };

  const handleDelete = async (id: number) => {
    try {
      setLoadingModal(true)
      await api.delete(`/admin/colors/${id}`);
      setData((prev) => prev.filter((color) => color.id !== id));
      message.success("Xóa màu thành công");
    } catch (error) {
      message.error("Lỗi khi xóa màu");
    }finally{
      setLoadingModal(false)
    }
  };
  const handleSearch = (value: string) => {
    setSearch(value);
  };

  const columns = [
    { title: "ID", dataIndex: "id" },
    { title: "Tên tiếng Anh", dataIndex: "en_name" },
    { title: "Tên tiếng Việt", dataIndex: "vi_name" },
    {
      title: "Hành động",
      render: (_: any, record: Color) => (
        <Space.Compact>
          <Button
            icon={<EditOutlined />}
            onClick={() => openEditModal(record)}
            type="primary"
            size="small"
          >
            Sửa
          </Button>
          <Popconfirm
            title="Xóa màu này?"
            onConfirm={() => handleDelete(record.id)}
          >
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
        <PageTable<Color>
          title="Quản lý mầu sản phẩm"
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
          title={editingColor ? "Sửa màu" : "Thêm màu"}
          open={isModalOpen}
          onOk={handleSave}
          onCancel={() => setIsModalOpen(false)}
          loading={loadingModal}
        >
          <Form form={form} layout="vertical">
            <Form.Item
              label="Tên tiếng Anh"
              name="en_name"
              rules={[{ required: true, message: "Nhập tên tiếng Anh" }]}
            >
              <Input />
            </Form.Item>
            <Form.Item
              label="Tên tiếng Việt"
              name="vi_name"
              rules={[{ required: true, message: "Nhập tên tiếng Việt" }]}
            >
              <Input />
            </Form.Item>
          </Form>
        </Modal>
      </div>
    </MainLayout>
  );
}