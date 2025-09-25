import React, { useState, useEffect } from "react";
import {
  Button,
  Select,
  Space,
  Popconfirm,
  message,
} from "antd";
import {
  DownloadOutlined,
  DeleteOutlined,
  CloudUploadOutlined,
} from "@ant-design/icons";
import MainLayout from "@/components/layout/MainLayout";
import api from '../../../../axiosConfig'; // Sửa đường dẫn import axios
import PageTable from "@/components/shared/PageTable";

interface BackupRecord {
  id: number;
  fileName: string;
  size: string;
  createdAt: string;
}

const BackupManager: React.FC = () => {
  const [backupType, setBackupType] = useState("db");
  const [backupList, setBackupList] = useState<BackupRecord[]>([]);
  const [loading, setLoading] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [downloadingId, setDownloadingId] = useState<number | null>(null);

  const contentBackup = [
    { value: "db", label: "Sao lưu database" },
  ];

  const fetchBackups = async () => {
    setLoading(true);
    try {
      const response = await api.get("/admin/backups");
      if (Array.isArray(response.data.backups)) {
        setBackupList(response.data.backups);
      } else {
        throw new Error("Dữ liệu sao lưu không hợp lệ");
      }
    } catch (error) {
      message.error("Lỗi khi lấy danh sách sao lưu.");
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchBackups();
  }, []);

  const handleCreateBackup = async () => {
    setLoading(true);
    try {
      await api.post("/admin/backups", { type: backupType });
      message.success("Đang tạo sao lưu, vui lòng đợi trong giây lát.");
      await fetchBackups();
    } catch (error) {
      message.error("Lỗi khi tạo sao lưu.");
      console.error(error);
    } finally {
      setLoading(false);
    }
  };

  const handleDownload = async (record: BackupRecord) => {
    setDownloadingId(record.id);
    try {
      const response = await api.get(`/admin/backups/download/${record.id}`, {
        responseType: 'blob',
      });

      if (!(response.data instanceof Blob)) {
        throw new Error("Dữ liệu tải xuống không hợp lệ");
      }

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', record.fileName);
      document.body.appendChild(link);
      link.click();

      if (link.parentNode) {
        link.parentNode.removeChild(link);
      }
      window.URL.revokeObjectURL(url); // Giải phóng URL

      message.success(`Đã tải xuống: ${record.fileName}`);
    } catch (error) {
      message.error(`Lỗi khi tải file: ${record.fileName}`);
      console.error(error);
    } finally {
      setDownloadingId(null);
    }
  };

  const handleDelete = async (record: BackupRecord) => {
    setDeletingId(record.id);
    try {
      await api.delete(`/admin/backups/${record.id}`);
      setBackupList((prev) => prev.filter((item) => item.id !== record.id));
      message.success(`Đã xóa: ${record.fileName}`);
    } catch (error) {
      message.error(`Lỗi khi xóa file: ${record.fileName}`);
      console.error(error);
    } finally {
      setDeletingId(null);
    }
  };

  const columns = [
    { title: "ID", dataIndex: "id", width: 60, align: "center" as const },
    { title: "Tên file", dataIndex: "fileName", ellipsis: true },
    { title: "Kích thước", dataIndex: "size", width: 120, align: "center" as const },
    { title: "Ngày tạo", dataIndex: "createdAt", width: 180, align: "center" as const },
    {
      title: "Hành động",
      key: "action",
      width: 160,
      align: "center" as const,
      render: (_: unknown, record: BackupRecord) => (
        <Space.Compact>
          <Button
            type="primary"
            icon={<DownloadOutlined />}
            size="small"
            onClick={() => handleDownload(record)}
            loading={downloadingId === record.id}
          >
            Tải về
          </Button>
          <Popconfirm
            title="Xóa sao lưu?"
            description="Bạn có chắc muốn xóa file này?"
            okText="Xóa"
            cancelText="Hủy"
            onConfirm={() => handleDelete(record)}
          >
            <Button
              danger
              icon={<DeleteOutlined />}
              size="small"
              loading={deletingId === record.id}
            >
              Xóa
            </Button>
          </Popconfirm>
        </Space.Compact>
      ),
    },
  ];

  return (
    <MainLayout>
      <PageTable<BackupRecord>
        title="Quản lý sao lưu"
        data={backupList}
        columns={columns}
        pageSize={14}
        rowKey="id"
        //onSearch={handleSearch}      // bỏ prop này nếu không cần search
        scrollX="max-content"
        extra={ <Space.Compact>
                  <Select
                    value={backupType}
                    onChange={setBackupType}
                    style={{ width: 220 }}
                    options={contentBackup}   // chú ý mục (2) bên dưới
                  />
                  <Button
                    type="primary"
                    icon={<CloudUploadOutlined />}
                    onClick={handleCreateBackup}
                    loading={loading}
                  >
                    Tạo mới
                  </Button>
                </Space.Compact>}
        loading={loading} 
      />      
    </MainLayout>
  );
};

export default BackupManager;