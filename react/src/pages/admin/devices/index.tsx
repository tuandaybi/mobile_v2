import { useState, useEffect } from "react";
import MainLayout from "../../../components/layout/MainLayout";
import {
  Button,
  Space,
  Modal,
  Form,
  Input,
  Switch,
  Popconfirm,
  message,
} from "antd";
import type { ColumnsType } from "antd/es/table";
import { PlusOutlined, EditOutlined, DeleteOutlined } from "@ant-design/icons";
import type { JSX } from "react/jsx-runtime";
import api from '../../../../axiosConfig';
import PageTable from "@/components/shared/PageTable";

interface Device {
  id: number;
  code: string;
  name: string;
  sort_order: number;
  is_active: boolean;
}

export default function DevicePage(): JSX.Element {
  const [form] = Form.useForm();
  const [data, setData] = useState<Device[]>([]);
  const [searchText, setSearchText] = useState("");
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<Device | null>(null);
  const [loading, setLoading] = useState(false);

  // Lấy danh sách thiết bị từ API
  const fetchDevices = async () => {
    try {
      setLoading(true);
      const response = await api.get('/admin/devices');
      setData(response.data.data);
    } catch (error) {
      message.error('Lỗi khi tải danh sách thiết bị');
    } finally {
      setLoading(false);
    }
  };

  // Gọi API khi component mount
  useEffect(() => {
    fetchDevices();
  }, []);

  const filteredData = data.filter(
    (p) =>
      p.code.toLowerCase().includes(searchText.toLowerCase()) ||
      p.name.toLowerCase().includes(searchText.toLowerCase())
  );

  const openAddModal = () => {
    setEditingDevice(null);
    form.resetFields();
    form.setFieldsValue({ is_active: true, sort_order: data.length ? Math.max(...data.map(d => d.sort_order)) + 1 : 1 });
    setIsModalOpen(true);
  };

  const openEditModal = (record: Device) => {
    setEditingDevice(record);
    form.setFieldsValue(record);
    setIsModalOpen(true);
  };

  const handleSave = async () => {
    try {
      const values = await form.validateFields();
      if (editingDevice) {
        // Cập nhật thiết bị
        await api.put(`/admin/devices/${editingDevice.id}`, values);
        setData(prev => prev.map(p => (p.id === editingDevice.id ? { ...p, ...values } : p)));
        message.success("Cập nhật thiết bị thành công");
      } else {
        // Thêm thiết bị mới
        const response = await api.post('/admin/devices', values);
        setData(prev => [...prev, response.data]);
        message.success("Thêm thiết bị thành công");
      }
      setIsModalOpen(false);
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Lỗi khi lưu thiết bị';
      message.error(errorMessage);
    }
  };

  const handleDelete = async (id: number) => {
    try {
      await api.delete(`/admin/devices/${id}`);
      setData(prev => prev.filter(p => p.id !== id));
      message.success("Xóa thiết bị thành công");
    } catch (error) {
      message.error("Lỗi khi xóa thiết bị");
    }
  };
  const handleSearch = (value: string) => {
    setSearchText(value);
  };

  const columns: ColumnsType<Device> = [
    { title: "ID", dataIndex: "id", key: "id", width: 70 },
    { title: "Mã thiết bị", dataIndex: "code", key: "device_code" },
    { title: "Tên thiết bị", dataIndex: "name", key: "device_name" },
    { title: "Thứ tự", dataIndex: "sort_order", key: "sort_order", width: 100, sorter: (a, b) => a.sort_order - b.sort_order, align: "center" },
    {
      title: "Kích hoạt",
      dataIndex: "is_active",
      key: "is_active",
      width: 90,
      render: (is_active: boolean) => (is_active ? "Có" : "Không"),
      filters: [
        { text: "Có", value: true },
        { text: "Không", value: false },
      ],
      onFilter: (value: any, record) => record.is_active === value,
      align: "center",
    },
    {
      title: "Hành động",
      key: "actions",
      width: 180,
      render: (_: any, record: Device) => (
        <Space.Compact>
          <Button icon={<EditOutlined />} onClick={() => openEditModal(record)} type="primary" size="small">Sửa</Button>
          <Popconfirm title="Xác nhận xóa?" onConfirm={() => handleDelete(record.id)}>
            <Button danger icon={<DeleteOutlined />} type="primary" size="small">Xóa</Button>
          </Popconfirm>
        </Space.Compact>
      ),
    },
  ];

  return (
    <MainLayout>
      <div>
        <PageTable<Device>
            title="Quản lý thiết bị"
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
          title={editingDevice ? "Sửa thiết bị" : "Thêm thiết bị"}
          open={isModalOpen}
          onOk={handleSave}
          onCancel={() => setIsModalOpen(false)}
          okText="Lưu"
          cancelText="Hủy"
          afterOpenChange={(open) => {
            if (open) {
              if (editingDevice) {
                console.log('editingDevice', editingDevice);
                form.setFieldsValue({
                  code: editingDevice.code,
                  name: editingDevice.name,
                  sort_order: editingDevice.sort_order,
                  is_active: editingDevice.is_active,
                });
              } else {
                form.resetFields();
              }
            }
          }}
        >
          <Form form={form} layout="vertical" initialValues={{ is_active: true, sort_order: data.length ? Math.max(...data.map(d => d.sort_order)) + 1 : 1 }}>
            <Form.Item name="code" label="Mã thiết bị" rules={[{ required: true, message: "Nhập mã thiết bị" }]}>
              <Input />
            </Form.Item>

            <Form.Item name="name" label="Tên thiết bị">
              <Input />
            </Form.Item>

            <Form.Item name="sort_order" label="Thứ tự" rules={[{ required: true, message: "Nhập thứ tự" }]}>
              <Input type="number" />
            </Form.Item>

            <Form.Item name="is_active" label="Kích hoạt" valuePropName="checked">
              <Switch />
            </Form.Item>
          </Form>
        </Modal>
      </div>
    </MainLayout>
  );
}