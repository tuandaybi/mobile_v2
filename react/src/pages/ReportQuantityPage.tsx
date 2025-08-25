import React, { useState } from "react";
import MainLayout from "../components/layout/MainLayout";
import { Row, Col, Card, Select } from "antd";
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";

const { Option } = Select;

// Mock data sản lượng bán từng model theo tháng
const mockSalesData = [
  { year: 2025, month: "1", model: "15 Pro Max", quantity: 5 },
  { year: 2025, month: "1", model: "14 Pro", quantity: 3 },
  { year: 2025, month: "1", model: "12", quantity: 2 },

  { year: 2025, month: "2", model: "15 Pro Max", quantity: 8 },
  { year: 2025, month: "2", model: "14 Pro", quantity: 4 },
  { year: 2025, month: "2", model: "13 Pro Max", quantity: 1 },

  { year: 2025, month: "3", model: "15 Pro Max", quantity: 6 },
  { year: 2025, month: "3", model: "15 Plus", quantity: 3 },
  { year: 2025, month: "3", model: "14 Pro", quantity: 2 },
];

// Danh sách năm
const getYearOptions = () => {
  const currentYear = new Date().getFullYear();
  return [currentYear - 1, currentYear];
};

// Danh sách tháng
const getMonthOptions = () => {
  return [...Array(12).keys()].map((m) => (m + 1).toString());
};

const ReportProductionPage: React.FC = () => {
  const currentYear = new Date().getFullYear();
  const currentMonth = (new Date().getMonth() + 1).toString();

  // Mặc định chọn năm và tháng hiện tại
  const [years, setYears] = useState<number[]>([currentYear]);
  const [months, setMonths] = useState<string[]>([currentMonth]);

  // Lọc dữ liệu theo nhiều năm / tháng
  const filteredData = React.useMemo(() => {
    return mockSalesData.filter(
      (item) =>
        years.includes(item.year) &&
        (months.length === 0 || months.includes(item.month))
    );
  }, [years, months]);

  // Tổng hợp số lượng theo model
  const aggregatedData = React.useMemo(() => {
    const mapModelToQuantity: Record<string, number> = {};

    filteredData.forEach(({ model, quantity }) => {
      if (!mapModelToQuantity[model]) {
        mapModelToQuantity[model] = 0;
      }
      mapModelToQuantity[model] += quantity;
    });

    return Object.entries(mapModelToQuantity).map(([model, quantity]) => ({
      model,
      quantity,
    }));
  }, [filteredData]);

  return (
    <MainLayout>
      <Row gutter={[16, 16]}>
        <Col span={24}>
          <Card title="📦 Báo cáo sản lượng máy bán">
            <Row gutter={16} style={{ marginBottom: 24 }}>
              <Col span={6}>
                <label>Năm:</label>
                <Select
                  mode="multiple"
                  value={years}
                  onChange={(val) => setYears(val)}
                  style={{ width: "100%" }}
                  placeholder="Chọn năm"
                >
                  {getYearOptions().map((y) => (
                    <Option key={y} value={y}>
                      {y}
                    </Option>
                  ))}
                </Select>
              </Col>

              <Col span={6}>
                <label>Tháng:</label>
                <Select
                  mode="multiple"
                  value={months}
                  onChange={(val) => setMonths(val)}
                  style={{ width: "100%" }}
                  placeholder="Chọn tháng"
                >
                  {getMonthOptions().map((m) => (
                    <Option key={m} value={m}>
                      Tháng {m}
                    </Option>
                  ))}
                </Select>
              </Col>
            </Row>

            <div style={{ width: "100%", height: 400 }}>
              <ResponsiveContainer>
                <BarChart
                  data={aggregatedData}
                  margin={{ top: 20, right: 30, left: 20, bottom: 5 }}
                >
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="model" />
                  <YAxis allowDecimals={false} />
                  <Tooltip />
                  <Bar dataKey="quantity" fill="#1890ff" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>
        </Col>
      </Row>
    </MainLayout>
  );
};

export default ReportProductionPage;
