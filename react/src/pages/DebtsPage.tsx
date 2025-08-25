import React, { useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import {
  Row,
  Col,
  Button,
  Space,
  message
} from "antd";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import PageTable from "@/components/shared/PageTable";

// Kiểu dữ liệu chung cho cả điện thoại và dịch vụ
type DebtRecord = {
  id: number;
  productName: string; // Tên sản phẩm
  buyer: string;       // Người mua
  phone: string;       // SĐT
  sellDate: string;    // Ngày bán
  importPrice: number; // Giá nhập
  sellPrice: number;   // Giá bán
  debt: number;        // Nợ lại
  cost: number;        // Chi phí
  importNote: string;  // Ghi chú nhập
  sellNote: string;    // Ghi chú bán
};

// Giả lập dữ liệu từ "Quản lý điện thoại"
const phones: DebtRecord[] = Array.from({ length: 50 }, (_, i) => {
  const debt = Math.random() > 0.6 ? (Math.floor(Math.random() * 10) + 1) * 500000 : 0;
  return {
    id: i + 1,
    productName: `iPhone ${Math.floor(Math.random() * 5) + 12} Pro Max`,
    buyer: ["Tú Bình", "C Hương", "Anh Tuấn", "Minh Quân"][Math.floor(Math.random() * 4)],
    phone: "09" + Math.floor(10000000 + Math.random() * 90000000),
    sellDate: new Date(2025, Math.floor(Math.random() * 8), Math.floor(Math.random() * 28) + 1)
      .toISOString()
      .split("T")[0],
    importPrice: (Math.floor(Math.random() * 20) + 10) * 1000000,
    sellPrice: (Math.floor(Math.random() * 20) + 15) * 1000000,
    debt,
    cost: Math.floor(Math.random() * 2000000),
    importNote: Math.random() > 0.5 ? "Hàng đẹp" : "",
    sellNote: Math.random() > 0.5 ? "Khách hài lòng" : ""
  };
});

// Giả lập dữ liệu từ "Quản lý dịch vụ"
const services: DebtRecord[] = Array.from({ length: 50 }, (_, i) => {
  const debt = Math.random() > 0.6 ? (Math.floor(Math.random() * 10) + 1) * 50000 : 0;
  return {
    id: i + 100,
    productName: ["Sấy 12 Pro", "Thay pin iPhone 13", "CL 11 PRM"][Math.floor(Math.random() * 3)],
    buyer: ["Hồng Anh", "Bảo Ngọc", "Hữu Lộc", "Khánh Vy"][Math.floor(Math.random() * 4)],
    phone: "09" + Math.floor(10000000 + Math.random() * 90000000),
    sellDate: new Date(2025, Math.floor(Math.random() * 8), Math.floor(Math.random() * 28) + 1)
      .toISOString()
      .split("T")[0],
    importPrice: (Math.floor(Math.random() * 5) + 1) * 100000,
    sellPrice: (Math.floor(Math.random() * 10) + 5) * 100000,
    debt,
    cost: Math.floor(Math.random() * 100000),
    importNote: "",
    sellNote: ""
  };
});

// Kết hợp và lọc chỉ những bản ghi có nợ > 0
const debtData: DebtRecord[] = [...phones, ...services].filter((item) => item.debt > 0);

const DebtManagement: React.FC = () => {
  const [_, setSearchText] = useState("");
  const [data, setData] = useState(debtData);

  const handleSearch = (value: string) => {
    setSearchText(value);
    const filtered = debtData.filter((item) =>
      Object.values(item).some((val) =>
        String(val).toLowerCase().includes(value.toLowerCase())
      )
    );
    setData(filtered);
  };

  const handleActionClick = (key: string, record: DebtRecord) => {
    if (key === "partial") {
      message.info(`Trả 1 phần nợ cho ${record.buyer}`);
    } else if (key === "full") {
      message.success(`Tất toán nợ cho ${record.buyer}`);
    }
  };

  const columns: ColumnsType<DebtRecord> = [
    { title: "Tên sản phẩm", dataIndex: "productName", key: "productName" },
    { title: "Người mua", dataIndex: "buyer", key: "buyer" },
    { title: "SĐT", dataIndex: "phone", key: "phone" },
    {
      title: "Ngày bán",
      dataIndex: "sellDate",
      key: "sellDate",
      render: (date) => dayjs(date).format("DD/MM/YYYY")
    },
    {
      title: "Giá nhập",
      dataIndex: "importPrice",
      key: "importPrice",
      render: (price) => `${price.toLocaleString()} đ`
    },
    {
      title: "Giá bán",
      dataIndex: "sellPrice",
      key: "sellPrice",
      render: (price) => `${price.toLocaleString()} đ`
    },
    {
      title: "Nợ lại",
      dataIndex: "debt",
      key: "debt",
      render: (debt) => (
        <span style={{ color: debt > 0 ? "red" : "green" }}>
          {debt.toLocaleString()} đ
        </span>
      )
    },
    {
      title: "Chi phí",
      dataIndex: "cost",
      key: "cost",
      render: (cost) => `${cost.toLocaleString()} đ`
    },
    { title: "Ghi chú nhập", dataIndex: "importNote", key: "importNote" },
    { title: "Ghi chú bán", dataIndex: "sellNote", key: "sellNote" },
    {
      title: "Chức năng",
      key: "action",
      render: (_, record) => (
        <Space.Compact>
          <Button
            type="default"
            size="small"
            onClick={() => handleActionClick("partial", record)}
          >
            Trả 1 phần
          </Button>
          <Button
            type="primary"
            size="small"
            onClick={() => handleActionClick("full", record)}
          >
            Tất toán
          </Button>
        </Space.Compact>
      )
    }
  ];

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<DebtRecord>
            title="📒 Quản lý công nợ"
            data={data}
            columns={columns}
            pageSize={14}
            rowKey="id"
            onSearch={handleSearch}      // bỏ prop này nếu không cần search
            scrollX="max-content"
            //extra={<Button type="primary" icon={<PlusOutlined />}>Thêm</Button>}
          />
        </Col>
      </Row>
    </MainLayout>
  );
};

export default DebtManagement;
