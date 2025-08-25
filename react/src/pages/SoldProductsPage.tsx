import React, { useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import {
  Row,
  Col,
  Button,
  Space,
  message,
} from "antd";
import type { ColumnsType } from "antd/es/table";
import dayjs from "dayjs";
import { useModalStore } from '../store/modalStore';
import PageTable from "@/components/shared/PageTable";

type SoldMobile = {
  id: number;
  productName: string;   // Tên sản phẩm
  importDate: string;    // Ngày nhập
  importPrice: number;   // Giá nhập
  sellPrice: number;     // Giá bán
  sellDate: string;      // Ngày bán
  buyer: string;         // Người mua
  phone: string;         // SĐT
  cost: number;          // Chi phí
  debt: number;          // Công nợ
  sellNote: string;      // Ghi chú bán
};

const mockSoldMobiles: SoldMobile[] = Array.from({ length: 100 }, (_, i) => {
  return {
    id: i + 1,
    productName: `iPhone ${Math.floor(Math.random() * 5) + 12} Pro Max`,
    importDate: new Date(2025, Math.floor(Math.random() * 8), Math.floor(Math.random() * 28) + 1)
      .toISOString()
      .split("T")[0],
    importPrice: (Math.floor(Math.random() * 20) + 10) * 1000000,
    sellPrice: (Math.floor(Math.random() * 20) + 15) * 1000000,
    sellDate: new Date(2025, Math.floor(Math.random() * 8), Math.floor(Math.random() * 28) + 1)
      .toISOString()
      .split("T")[0],
    buyer: ["Tú Bình", "C Hương", "Anh Tuấn", "Minh Quân"][Math.floor(Math.random() * 4)],
    phone: "09" + Math.floor(10000000 + Math.random() * 90000000),
    cost: Math.floor(Math.random() * 2000000),
    debt: Math.random() > 0.7 ? (Math.floor(Math.random() * 10) + 1) * 500000 : 0,
    sellNote: Math.random() > 0.5 ? "Khách hài lòng" : "",
  };
});

const SoldMobilesPage: React.FC = () => {
  const [searchText, setSearchText] = useState("");
  const { mobile, sellMobile } = useModalStore();
  const [data, setData] = useState(mockSoldMobiles);

  const handleSearch = (value: string) => {
    setSearchText(value);
    const filtered = mockSoldMobiles.filter((item) =>
      Object.values(item).some((val) =>
        String(val).toLowerCase().includes(value.toLowerCase())
      )
    );
    setData(filtered);
  };

  const handleActionClick = (action: string, record: SoldMobile) => {
    if (action === "editImport") {
      mobile.open(true)
    } else if (action === "editSell") {
      sellMobile.open(true)
    } else if (action === "refund") {
      message.info(`Trả lại sản phẩm ${record.productName}`);
    }
  };

  const columns: ColumnsType<SoldMobile> = [
    { title: "Tên sản phẩm", dataIndex: "productName", key: "productName" },
    {
      title: "Ngày nhập",
      dataIndex: "importDate",
      key: "importDate",
      render: (date) => dayjs(date).format("DD/MM/YYYY"),
    },
    {
      title: "Giá nhập",
      dataIndex: "importPrice",
      key: "importPrice",
      render: (price) => `${price.toLocaleString()} đ`,
    },
    {
      title: "Giá bán",
      dataIndex: "sellPrice",
      key: "sellPrice",
      render: (price) => `${price.toLocaleString()} đ`,
    },
    {
      title: "Ngày bán",
      dataIndex: "sellDate",
      key: "sellDate",
      render: (date) => dayjs(date).format("DD/MM/YYYY"),
    },
    { title: "Người mua", dataIndex: "buyer", key: "buyer" },
    { title: "SĐT", dataIndex: "phone", key: "phone" },
    {
      title: "Chi phí",
      dataIndex: "cost",
      key: "cost",
      render: (cost) => `${cost.toLocaleString()} đ`,
    },
    {
      title: "Công nợ",
      dataIndex: "debt",
      key: "debt",
      render: (debt) => (
        <span style={{ color: debt > 0 ? "red" : "green" }}>
          {debt.toLocaleString()} đ
        </span>
      ),
    },
    { title: "Ghi chú bán", dataIndex: "sellNote", key: "sellNote" },
    {
      title: "Chức năng",
      key: "action",
      render: (_, record) => (
        <Space.Compact>
          <Button
            type="default"
            size="small"
            onClick={() => handleActionClick("editImport", record)}
          >
            Sửa mua
          </Button>
          <Button
            type="primary"
            size="small"
            onClick={() => handleActionClick("editSell", record)}
          >
            Sửa bán
          </Button>
          <Button
           type="primary"
            danger
            size="small"
            onClick={() => handleActionClick("refund", record)}
          >
            Trả lại
          </Button>
        </Space.Compact>
      ),
    },
  ];

  return (
    <MainLayout>
      <Row gutter={16}>
        <Col span={24}>
          <PageTable<SoldMobile>
            title="📦 Danh sách máy đã bán"
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

export default SoldMobilesPage;
