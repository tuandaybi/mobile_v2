import React, { useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import {
  Row,
  Col,
  Card,
  Input,
  Space,
  message,
  Typography,
} from "antd";

const { Text } = Typography;

type ProductInfo = {
  id: number;
  productName: string;
  imei: string;
  importSource: string;
  importDate: string;
  importPrice: number;
  importNote: string;
  buyer: string;
  buyerPhone: string;
  sellDate: string;
  sellPrice: number;
  debt: number;
  installment: string;
  sellNote: string;
  warranty: string;
};

const mockProducts: ProductInfo[] = [
  {
    id: 1,
    productName: "XS 64GB Black LL/A - Pin: 85%",
    imei: "35255",
    importSource: "Cường Kiệt",
    importDate: "2025-08-08",
    importPrice: 3700000,
    importNote: "",
    buyer: "E Quân",
    buyerPhone: "",
    sellDate: "2025-08-09",
    sellPrice: 4000000,
    debt: 4000000,
    installment: "Không",
    sellNote: "",
    warranty: "Bảo hành 6 tháng",
  },
  {
    id: 2,
    productName: "iPhone 13 Pro 256GB Silver",
    imei: "123456789012345",
    importSource: "Kho chính",
    importDate: "2025-07-15",
    importPrice: 18000000,
    importNote: "Hàng mới",
    buyer: "Minh Quân",
    buyerPhone: "0987654321",
    sellDate: "2025-07-20",
    sellPrice: 21000000,
    debt: 0,
    installment: "Có",
    sellNote: "Khách hài lòng",
    warranty: "Bảo hành 12 tháng",
  },
];

const CheckProductPage: React.FC = () => {
  const [imeiSearch, setImeiSearch] = useState("");
  const [foundProducts, setFoundProducts] = useState<ProductInfo[]>([]);

  const handleSearch = () => {
    if (!imeiSearch.trim()) {
      message.warning("Vui lòng nhập IMEI để tìm kiếm");
      return;
    }
    const searchTerm = imeiSearch.trim();
    const results = mockProducts.filter(p =>
      p.imei.endsWith(searchTerm)
    );
    if (results.length === 0) {
      message.info("Không tìm thấy sản phẩm với IMEI này");
    }
    setFoundProducts(results);
  };

  return (
    <MainLayout>
      <Row gutter={16}>
        {/* Cột tìm kiếm */}
        <Col span={8}>
          <Card>
            <Space>
              <Input.Search
                placeholder="Tìm kiếm theo imei ..."
                style={{ width: 300 }}
              />
            </Space>
          </Card>
        </Col>

        {/* Cột hiển thị sản phẩm tìm được */}
        <Col span={16}>
          {foundProducts.length === 0 ? (
            <Card>Chưa có sản phẩm nào được tìm thấy.</Card>
          ) : (
            foundProducts.map((p) => (
              <Card
                    key={p.id}
                    title={p.productName}
                    style={{ marginBottom: 16 }}
                    bordered
                >
                    <Row gutter={16}>
                    {/* Cột trái: Thông tin nhập */}
                    <Col span={12}>
                        <p><Text strong>IMEI:</Text> {p.imei}</p>
                        <p><Text strong>Nguồn nhập:</Text> {p.importSource}</p>
                        <p><Text strong>Ngày nhập:</Text> {new Date(p.importDate).toLocaleDateString()}</p>
                        <p><Text strong>Giá nhập:</Text> {p.importPrice.toLocaleString()} đ</p>
                        <p><Text strong>Ghi chú nhập:</Text> {p.importNote || "-"}</p>
                    </Col>

                    {/* Cột phải: Thông tin bán */}
                    <Col span={12}>
                        <p><Text strong>Người mua:</Text> {p.buyer}</p>
                        <p><Text strong>SĐT người mua:</Text> {p.buyerPhone || "-"}</p>
                        <p><Text strong>Ngày bán:</Text> {new Date(p.sellDate).toLocaleDateString()}</p>
                        <p><Text strong>Giá bán:</Text> {p.sellPrice.toLocaleString()} đ</p>
                        <p><Text strong>Số tiền nợ:</Text> <span style={{color: p.debt > 0 ? "red" : "green"}}>{p.debt.toLocaleString()} đ</span></p>
                        <p><Text strong>Trả góp:</Text> {p.installment}</p>
                        <p><Text strong>Ghi chú bán:</Text> {p.sellNote || "-"}</p>
                        <p><Text strong>Bảo hành:</Text> {p.warranty}</p>
                    </Col>
                    </Row>
                </Card>
            ))
          )}
        </Col>
      </Row>
    </MainLayout>
  );
};

export default CheckProductPage;
